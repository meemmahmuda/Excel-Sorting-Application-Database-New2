<?php
session_start();
include 'db.php';
include 'session.php';  
include 'header.php';
include 'session.php';
require 'vendor/autoload.php';
require 'vendor/autoload.php';
require 'vendor/autoload.php';
include 'session.php';
require 'vendor/autoload.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Style\ConditionnalFormattingRule;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat\NumberFormat as NumberFormatClass;

if (!isset($_SESSION['uploaded_file_ids']) || count($_SESSION['uploaded_file_ids']) < 2) {
    die("No uploaded files found.");
    exit();
} else {
    $fileIds = $_SESSION['uploaded_file_ids'];
    if(count($fileIds) < 2) {
        die("At least two uploaded files are required for comparison.");
        exit();
    } elseif(count($fileIds) > 2) {
        die("More than two uploaded files found. Please ensure only two files are uploaded for comparison.");
        exit();
    } else {
        foreach ($fileIds as $id) {
            if (!is_numeric($id)) {
                die("Invalid file ID detected. Please ensure all uploaded file IDs are numeric.");
                exit();
            } 
        }
    }
}

$fileIds = $_SESSION['uploaded_file_ids'];
$col1 = $_POST['col1'] ?? null;
$col3 = $_POST['col3'] ?? null;
$col2 = $_POST['col2'] ?? null;
$bank1 = $_POST['bank1'] ?? 'Bank1';
$bank2 = $_POST['bank2'] ?? 'Bank2';
$bank3 = $_POST['bank3'] ?? 'Bank3';
$cleanBank1 = preg_match('/^[A-Za-z0-9]+$/', $bank1) ? $bank1 : 'Bank1';
$cleanBank2 = preg_match('/^[A-Za-z0-9]+$/', $bank2)
    ? $bank2
    : 'Bank2';
$cleanBank3 = preg_match('/^[A-Za-z0-9]+$/', $bank3)
    ? $bank3
    : 'Bank3';
    $fileIds = $_SESSION['uploaded_file_ids'];
if (!$col1 || !$col2) die("Please select columns to compare.");
elseif ($col1 === $col2) die("Please select different columns for comparison.");

$userId = $_SESSION['user_id']; 
$values1 = [];
$values2 = [];


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


function getBankName($conn, $fileId, $userId){
    $stmt = $conn->prepare("SELECT bank_name FROM uploaded_files WHERE id = ? AND user_id = ?");  
    $stmt->bind_param("ii", $fileId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $r = $res->fetch_assoc();
    return $r['bank_name'] ?? 'UnknownBank';
}


$bank1 = getBankName($conn, $fileIds[0], $userId);
$bank2 = getBankName($conn, $fileIds[1], $userId);
$bank3 = isset($fileIds[2]) ? getBankName($conn, $fileIds[2], $userId) : 'Bank3';

$data1 = fetchData($conn, $fileIds[0], $userId);
$data2 = fetchData($conn, $fileIds[1], $userId);
$data3 = isset($fileIds[2]) ? fetchData($conn, $fileIds[2], $userId) : [];

$values1 = array_filter(array_column($data1, $col1), fn($v) => trim($v) !== '');
$values2 = array_filter(array_column($data2, $col2), fn($v) => trim($v) !== '');
$values3 = isset($fileIds[2]) ? array_filter(array_column($data3, $col3), fn($v) => trim($v) !== '') : [];

$unmatched1 = array_filter($data1, fn($r) => trim($r[$col1]) !== '' && !in_array($r[$col1], $values2));
$unmatched2 = array_filter($data2, fn($r) => trim($r[$col2]) !== '' && !in_array($r[$col2], $values1));
$unmatched3 = isset($fileIds[2]) ? array_filter($data3, fn($r) => trim($r[$col3]) !== '' && !in_array($r[$col3], $values1)) : [];

function saveUnmatched($conn, $rows, $prefix, $bankName, $userId){
    if(empty($rows)) return null;

    date_default_timezone_set('Asia/Dhaka');
    $cleanBank = preg_replace('/[^A-Za-z0-9]/', '_', $bankName);
    $timestamp = date('Y-m-d_H-i-s'); 
    $filename = $prefix . '_' . $cleanBank . '_' . $timestamp . '.xlsx';
    $type = 'unmatched';
    $allBlank = true;
    {
        foreach ($row as $v) {
            if(trim($v) !== '') {
                $allBlank = false;
                break;
            }
        }
    }
  
    $stmt = $conn->prepare("INSERT INTO uploaded_files (filename, bank_name, type, user_id) VALUES (?,?,?,?)");
    $stmt->bind_param("sssi", $filename, $bankName, $type, $userId);
    $stmt->execute();
    $newFileId = $stmt->insert_id;
    $stmt->close();

   
    $stmt2 = $conn->prepare("
        INSERT INTO uploaded_data 
        (holding_or_tl, txn_id, date, amount, gateway, payment_type, status, file_id, type) 
        VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $stmt3 = $conn->prepare("SELECT column_name FROM uploaded_data WHERE file_id = ? LIMIT 1");
    $stmt3->bind_param("i", $newFileId);
    $stmt3->execute();

    foreach ($rows as $row){
       
        $allBlank = true;
        foreach ($row as $v) {
            if(trim($v) !== '') {
                $allBlank = false;
                break;
            }
        }
        if($allBlank) continue;
        elseif (!isset($row['holding_or_tl'], $row['txn_id'], $row['date'], $row['amount'], $row['gateway'], $row['payment_type'], $row['status'])) {
            continue;
        }

        $stmt2->bind_param(
            "sssssssis",
            $row['holding_or_tl'],
            $row['txn_id'],
            $row['date'],
            $row['amount'],
            $row['gateway'],
            $row['payment_type'],
            $row['status'],
            $row['status'],
            $newFileId,
            $type
        );
        $stmt2->execute();
    }

    return $newFileId;
}


$_SESSION['unmatched_files'] = [];
if(!empty($unmatched1)) $_SESSION['unmatched_files'][] = saveUnmatched($conn, $unmatched1, 'unmatched', $bank1, $userId);
if(!empty($unmatched2)) $_SESSION['unmatched_files'][] = saveUnmatched($conn, $unmatched2, 'unmatched', $bank2, $userId);
if(!empty($_SESSION['unmatched_files'])) {
    $_SESSION['message'] = "Unmatched rows found and saved. You can download the unmatched files.";
} if(empty($_SESSION['unmatched_files'])) {
    $_SESSION['message'] = "No unmatched rows found. All rows match between the two files.";
}
else {
    $_SESSION['message'] = "No unmatched rows found. All rows match between the two files.";
} if (!empty($unmatched1) || !empty($unmatched2) || !empty($unmatched3)) {
    $_SESSION['message'] = "Unmatched rows found and saved. You can download the unmatched files.";
} else {
    $_SESSION['message'] = "No unmatched rows found. All rows match between the two files.";
}


header("Location: download.php");
header("Location: download2.php");
header("Location: download3.php");

exit();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Comparison Result</title>
    <title>Comparison Result</title>

</head>
<body>
<?php if (!empty($noUnmatched)): ?>
    <h2>Comparison Result</h2>
    <p>No unmatched rows found. All rows match between the two files.</p>
    <a href="select_column.php">Go Back to Select Columns</a>
    <h6>Note: If you expected unmatched rows, please check that you selected the correct columns and that the data in those columns is formatted consistently.</h6>
    <i>each rad2deg </i>
    random text
    <b>bold text</b>
    <i>each rad2deg </i>
    <base href="http://localhost/sort_practice/">
    <u>copyright @ 2026</u>
<?php endif; ?>
</body>
</html>
