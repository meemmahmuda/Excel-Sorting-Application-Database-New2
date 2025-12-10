<?php
session_start();
include 'db.php';
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

// Fetch rows from uploaded_data table
function fetchData($conn, $fileId) {
    $rows = [];
    $res = $conn->query("SELECT * FROM uploaded_data WHERE file_id = $fileId");
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

// Get Bank Name
function getBankName($conn, $fileId){
    $q = $conn->query("SELECT bank_name FROM uploaded_files WHERE id = $fileId");
    $r = $q->fetch_assoc();
    return $r['bank_name'] ?? 'UnknownBank';
}

$bank1 = getBankName($conn, $fileIds[0]);
$bank2 = getBankName($conn, $fileIds[1]);

$data1 = fetchData($conn, $fileIds[0]);
$data2 = fetchData($conn, $fileIds[1]);

$values1 = array_column($data1, $col1);
$values2 = array_column($data2, $col2);

$unmatched1 = array_filter($data1, fn($r) => !in_array($r[$col1], $values2));
$unmatched2 = array_filter($data2, fn($r) => !in_array($r[$col2], $values1));

// Save unmatched rows as new files
function saveUnmatched($conn, $rows, $prefix, $bankName){
    if(empty($rows)) return null;
    $cleanBank = preg_replace('/[^A-Za-z0-9]/', '_', $bankName);
    $filename = $prefix . '_' . $cleanBank . '_' . time() . '.xlsx';
    $type = 'unmatched';

    $stmt = $conn->prepare("INSERT INTO uploaded_files (filename, bank_name, type) VALUES (?,?,?)");
    $stmt->bind_param("sss", $filename, $bankName, $type);
    $stmt->execute();
    $newFileId = $stmt->insert_id;

    $stmt2 = $conn->prepare(
        "INSERT INTO uploaded_data 
        (holding_or_tl, txn_id, date, amount, gateway, payment_type, status, file_id, type) 
        VALUES (?,?,?,?,?,?,?,?,?)"
    );

    foreach ($rows as $row){
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

$_SESSION['unmatched_files'] = [];

if(!empty($unmatched1)) $_SESSION['unmatched_files'][] = saveUnmatched($conn, $unmatched1, 'unmatched', $bank1);
if(!empty($unmatched2)) $_SESSION['unmatched_files'][] = saveUnmatched($conn, $unmatched2, 'unmatched', $bank2);

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
