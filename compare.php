<?php
session_start();
include 'db.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if(!isset($_SESSION['uploaded_file_ids']) || count($_SESSION['uploaded_file_ids']) < 2){
    die("No uploaded files found.");
}

$fileIds = $_SESSION['uploaded_file_ids'];
$col1 = $_POST['col1'] ?? null;
$col2 = $_POST['col2'] ?? null;

if(!$col1 || !$col2){
    die("Please select columns to compare.");
}

// Fetch data
function fetchData($conn, $fileId){
    $rows = [];
    $res = $conn->query("SELECT * FROM uploaded_data WHERE file_id = $fileId");
    while($r = $res->fetch_assoc()){
        $rows[] = $r;
    }
    return $rows;
}

$data1 = fetchData($conn, $fileIds[0]);
$data2 = fetchData($conn, $fileIds[1]);

$values1 = array_column($data1, $col1);
$values2 = array_column($data2, $col2);

$unmatched1 = array_filter($data1, fn($r) => !in_array($r[$col1], $values2));
$unmatched2 = array_filter($data2, fn($r) => !in_array($r[$col2], $values1));

// Save unmatched data to DB (no uploads folder)
function saveUnmatched($conn, $rows, $prefix){
    if(empty($rows)) return null;

    // Generate a filename for DB
    $filename = $prefix . '_' . time() . '.xlsx';

    // Insert into uploaded_files
    $stmt = $conn->prepare("INSERT INTO uploaded_files (filename) VALUES (?)");
    $stmt->bind_param("s", $filename);
    $stmt->execute();
    $newFileId = $stmt->insert_id;

    // Insert rows into uploaded_data
    $stmt2 = $conn->prepare("INSERT INTO uploaded_data (holding_or_tl, txn_id, date, amount, gateway, payment_type, status, file_id) VALUES (?,?,?,?,?,?,?,?)");

    foreach($rows as $row){
        $holding_or_tl = $row['holding_or_tl'] ?? null;
        $txn_id        = $row['txn_id'] ?? null;
        $date          = $row['date'] ?? null;
        $amount        = $row['amount'] ?? null;
        $gateway       = $row['gateway'] ?? null;
        $payment_type  = $row['payment_type'] ?? null;
        $status        = $row['status'] ?? null;
        $file_id       = $newFileId;

        $stmt2->bind_param("sssssssi",
            $holding_or_tl,
            $txn_id,
            $date,
            $amount,
            $gateway,
            $payment_type,
            $status,
            $file_id
        );
        $stmt2->execute();
    }

    return $newFileId;
}

// Store unmatched files IDs in session
$_SESSION['unmatched_files'] = [];

if(!empty($unmatched1)){
    $_SESSION['unmatched_files'][] = saveUnmatched($conn, $unmatched1, 'unmatched1');
}
if(!empty($unmatched2)){
    $_SESSION['unmatched_files'][] = saveUnmatched($conn, $unmatched2, 'unmatched2');
}

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
