<?php
session_start();
include 'db.php';
include 'session.php';  // protect page
include 'header.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['uploaded_file_ids']) || count($_SESSION['uploaded_file_ids']) < 2) {
    die("No uploaded files found.");
}

$fileIds = $_SESSION['uploaded_file_ids'];
$col1 = $_POST['col1'] ?? null;
$col2 = $_POST['col2'] ?? null;

if (!$col1 || !$col2) die("Please select columns to compare.");

$userId = $_SESSION['user_id']; // logged-in user ID

// Fetch rows from uploaded_data table (only for the logged-in user)
function fetchData($conn, $fileId, $userId) {
    $rows = [];
    $stmt = $conn->prepare("
        SELECT ud.*
        FROM uploaded_data ud
        INNER JOIN uploaded_files uf ON ud.file_id = uf.id
        WHERE ud.file_id = ? AND uf.user_id = ?
    ");
    $stmt->bind_param("ii", $fileId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        // Skip completely blank rows
        $allBlank = true;
        foreach ($r as $v) {
            if (trim($v) !== '') {
                $allBlank = false;
                break;
            }
        }
        if (!$allBlank) $rows[] = $r;
    }
    return $rows;
}

// Get Bank Name (only for logged-in user)
function getBankName($conn, $fileId, $userId){
    $stmt = $conn->prepare("SELECT bank_name FROM uploaded_files WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $fileId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $r = $res->fetch_assoc();
    return $r['bank_name'] ?? 'UnknownBank';
}

// Fetch data for both files
$bank1 = getBankName($conn, $fileIds[0], $userId);
$bank2 = getBankName($conn, $fileIds[1], $userId);

$data1 = fetchData($conn, $fileIds[0], $userId);
$data2 = fetchData($conn, $fileIds[1], $userId);

// Only take non-blank values for comparison
$values1 = array_filter(array_column($data1, $col1), fn($v) => trim($v) !== '');
$values2 = array_filter(array_column($data2, $col2), fn($v) => trim($v) !== '');

// Compare and filter unmatched rows
$unmatched1 = array_filter($data1, fn($r) => trim($r[$col1]) !== '' && !in_array($r[$col1], $values2));
$unmatched2 = array_filter($data2, fn($r) => trim($r[$col2]) !== '' && !in_array($r[$col2], $values1));

// Save unmatched rows as new files (with user restriction)
function saveUnmatched($conn, $rows, $prefix, $bankName, $userId){
    if(empty($rows)) return null;

    $cleanBank = preg_replace('/[^A-Za-z0-9]/', '_', $bankName);
    $filename = $prefix . '_' . $cleanBank . '_' . time() . '.xlsx';
    $type = 'unmatched';

    // Insert new unmatched file
    $stmt = $conn->prepare("INSERT INTO uploaded_files (filename, bank_name, type, user_id) VALUES (?,?,?,?)");
    $stmt->bind_param("sssi", $filename, $bankName, $type, $userId);
    $stmt->execute();
    $newFileId = $stmt->insert_id;

    // Insert unmatched data
    $stmt2 = $conn->prepare("
        INSERT INTO uploaded_data 
        (holding_or_tl, txn_id, date, amount, gateway, payment_type, status, file_id, type) 
        VALUES (?,?,?,?,?,?,?,?,?)
    ");

    foreach ($rows as $row){
        // Skip blank rows
        $allBlank = true;
        foreach ($row as $v) {
            if(trim($v) !== '') {
                $allBlank = false;
                break;
            }
        }
        if($allBlank) continue;

        $stmt2->bind_param(
            "sssssssis",
            $row['holding_or_tl'],
            $row['txn_id'],
            $row['date'],
            $row['amount'],
            $row['gateway'],
            $row['payment_type'],
            $row['status'],
            $newFileId,
            $type
        );
        $stmt2->execute();
    }

    return $newFileId;
}

// Save unmatched files for logged-in user only
$_SESSION['unmatched_files'] = [];
if(!empty($unmatched1)) $_SESSION['unmatched_files'][] = saveUnmatched($conn, $unmatched1, 'unmatched', $bank1, $userId);
if(!empty($unmatched2)) $_SESSION['unmatched_files'][] = saveUnmatched($conn, $unmatched2, 'unmatched', $bank2, $userId);

// Redirect to download page
header("Location: download.php");
exit();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Comparison Result</title>
</head>
<body>
<?php if (!empty($noUnmatched)): ?>
    <h2>Comparison Result</h2>
    <p>No unmatched rows found. All rows match between the two files.</p>
    <a href="select_column.php">Go Back to Select Columns</a>
<?php endif; ?>
</body>
</html>
