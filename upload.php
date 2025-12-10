<?php
require 'vendor/autoload.php';
include 'db.php';
session_start();

use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

// Define possible column names for mapping
$holdingCols = ['holding no', 'tl no', 'holding no / tl no', 'e-holding', 's/l', 'e-holding no', 'e-holding number', 'bill no', 'account number', 'docnumber'];
$txnCols     = ['txn id', 'tranno', 'transaction id', 'transactio id', 'bkash transaction id', 'transactionno', 'payment no', 'transaction id (dncc)'];
$dateCols    = ['date', 'pay date', 'txn_date', 'trandate', 'date & time', 'territory code', 'payment date', 'transaction date', 'cdate'];
$amountCols  = ['amount', 'paid amount', 'txn_amt', 'total amount', 'reqamount', 'amount bdt', 'totalamt'];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $uploadedFileIds = [];

    for($i=1; $i<=2; $i++){
        if(isset($_FILES["file$i"]) && $_FILES["file$i"]['error'] == 0){
            $fileTmp = $_FILES["file$i"]['tmp_name'];
            $fileName = $_FILES["file$i"]['name'];
            $bankName = $_POST["bank_name$i"] ?? null;

            // Insert file with type 'uploaded'
            $stmt = $conn->prepare("INSERT INTO uploaded_files (filename, bank_name, type) VALUES (?,?,?)");
            $type = 'uploaded';
            $stmt->bind_param("sss", $fileName, $bankName, $type);
            $stmt->execute();
            $fileId = $stmt->insert_id;
            $uploadedFileIds[] = $fileId;

            // Load Excel
            $spreadsheet = IOFactory::load($fileTmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            if(count($rows) < 1) continue;

            // Clean and normalize headers
            $header = array_map(function($h){
                return strtolower(trim(str_replace("\xc2\xa0", ' ', $h))); // remove non-breaking spaces
            }, $rows[0]);

            // Map headers to DB columns
            $colIndex = [
                'holding_or_tl' => null,
                'txn_id'        => null,
                'date'          => null,
                'amount'        => null,
                'gateway'       => null,
                'payment_type'  => null,
                'status'        => null
            ];

            foreach($header as $idx => $col){
                $col = trim(strtolower($col));
                if(in_array($col, $holdingCols)) $colIndex['holding_or_tl'] = $idx;
                elseif(in_array($col, $txnCols)) $colIndex['txn_id'] = $idx;
                elseif(in_array($col, $dateCols)) $colIndex['date'] = $idx;
                elseif(in_array($col, $amountCols)) $colIndex['amount'] = $idx;
                elseif(strpos($col, 'gateway') !== false) $colIndex['gateway'] = $idx;
                elseif($col === 'payment type' || $col === 'payment method') $colIndex['payment_type'] = $idx;
                elseif(strpos($col, 'status') !== false) $colIndex['status'] = $idx;
            }

            // Insert rows in chunks
            $chunkSize = 500;
            $dataChunk = [];
            for($r=1; $r<count($rows); $r++){
                $row = $rows[$r];

                // Skip completely blank rows
                $isBlank = true;
                foreach($row as $cell){
                    if(trim($cell) !== '') {
                        $isBlank = false;
                        break;
                    }
                }
                if($isBlank) continue;

                // Skip total/summary rows like "Total ৳ ..."
                $rowString = implode(' ', $row);
                if(stripos($rowString, 'total') !== false) continue;

                $dataRow = [
                    'holding_or_tl' => $colIndex['holding_or_tl'] !== null ? $row[$colIndex['holding_or_tl']] : null,
                    'txn_id'        => $colIndex['txn_id'] !== null ? $row[$colIndex['txn_id']] : null,
                    'date'          => $colIndex['date'] !== null ? $row[$colIndex['date']] : null,
                    'amount'        => $colIndex['amount'] !== null ? (is_numeric($row[$colIndex['amount']]) ? number_format((float)$row[$colIndex['amount']], 2, '.', '') : $row[$colIndex['amount']]) : null,
                    'gateway'       => $colIndex['gateway'] !== null ? $row[$colIndex['gateway']] : null,
                    'payment_type'  => $colIndex['payment_type'] !== null ? $row[$colIndex['payment_type']] : null,
                    'status'        => $colIndex['status'] !== null ? $row[$colIndex['status']] : null
                ];

                $dataChunk[] = $dataRow;

                if(count($dataChunk) >= $chunkSize){
                    insertChunk($dataChunk, $fileId, $conn, $type);
                    $dataChunk = [];
                }
            }

            if(!empty($dataChunk)){
                insertChunk($dataChunk, $fileId, $conn, $type);
            }
        }
    }

    $_SESSION['uploaded_file_ids'] = $uploadedFileIds;
    header("Location: select_column.php");
    exit();
}

function insertChunk($dataChunk, $fileId, $conn, $type){
    $stmt = $conn->prepare("INSERT INTO uploaded_data (holding_or_tl, txn_id, date, amount, gateway, payment_type, status, file_id, type) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach($dataChunk as $row){
        $stmt->bind_param("sssssssis", 
            $row['holding_or_tl'], 
            $row['txn_id'], 
            $row['date'], 
            $row['amount'], 
            $row['gateway'], 
            $row['payment_type'], 
            $row['status'], 
            $fileId,
            $type
        );
        $stmt->execute();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Excel Files</title>
</head>
<body>
<h2>Upload 2 Excel Files</h2>
<form method="post" enctype="multipart/form-data">
    <!-- File 1 -->
    <label>File 1:</label><br>
    <input type="file" name="file1" required><br><br>
    <label>Bank Portal:</label><br>
    <select name="bank_name1" required>
        <option value="">-- Select Bank --</option>
        <option value="DNCC Bank Portal">DNCC Bank Portal</option>
        <option value="DBBL Holding">DBBL Holding</option>
        <option value="DBBL Holding Due">DBBL Holding Due</option>
        <option value="DBBL MFS">DBBL MFS</option>
        <option value="DBBL MFS Due">DBBL MFS Due</option>
        <option value="Bkash Holding">Bkash Holding</option>
        <option value="Sonali Bank">Sonali Bank</option>
        <option value="Standard Bank">Standard Bank</option>
        <option value="Modhumoti Bank">Modhumoti Bank</option>
        <option value="Trust TAP Holding">Trust TAP Holding</option>
        <option value="Trust TAP TL">Trust TAP TL</option>
        <option value="Upay MFS">Upay MFS</option>
        <option value="OK Wallet">OK Wallet</option>
        <option value="DBBL TL Collection">DBBL TL Collection</option>
        <option value="DBBL TL Correction">DBBL TL Correction</option>
        <option value="Bkash TL">Bkash TL</option>
    </select><br><br>

    <!-- File 2 -->
    <label>File 2:</label><br>
    <input type="file" name="file2" required><br><br>
    <label>Bank Portal:</label><br>
    <select name="bank_name2" required>
        <option value="">-- Select Bank --</option>
        <option value="DNCC Bank Portal">DNCC Bank Portal</option>
        <option value="DBBL Holding">DBBL Holding</option>
        <option value="DBBL Holding Due">DBBL Holding Due</option>
        <option value="DBBL MFS">DBBL MFS</option>
        <option value="DBBL MFS Due">DBBL MFS Due</option>
        <option value="Bkash Holding">Bkash Holding</option>
        <option value="Sonali Bank">Sonali Bank</option>
        <option value="Standard Bank">Standard Bank</option>
        <option value="Modhumoti Bank">Modhumoti Bank</option>
        <option value="Trust TAP Holding">Trust TAP Holding</option>
        <option value="Trust TAP TL">Trust TAP TL</option>
        <option value="Upay MFS">Upay MFS</option>
        <option value="OK Wallet">OK Wallet</option>
        <option value="DBBL TL Collection">DBBL TL Collection</option>
        <option value="DBBL TL Correction">DBBL TL Correction</option>
        <option value="Bkash TL">Bkash TL</option>
    </select><br><br>

    <button type="submit">Upload</button>
</form>
</body>
</html>
