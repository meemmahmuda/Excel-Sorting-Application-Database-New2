<?php
require 'vendor/autoload.php';
include 'db.php';
session_start();

use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

// Column mappings (unchanged)
$columnsToUse = [
    ['docnumber'=>'TL No','tranno'=>'Txn ID','cdate'=>'Date','totalamt'=>'Amount','gateway'=>'Gateway','PaymentType'=>'Payment Type','status'=>'Status'],
    ['e-holding'=>'Holding No','transactio id'=>'Txn ID','date'=>'Date','paid amount'=>'Amount','gateway'=>'Gateway','status'=>'Status'],
    ['bill no'=>'Holding No / TL No','txn id'=>'Txn ID','territory code'=>'Date','amount'=>'Amount','status'=>'Status'],
    ['BILLER_REF_NO'=>'Holding No','TRANSACTION_ID'=>'Txn ID','TXN_DATE'=>'Date','TXN_AMT'=>'Amount','STATUS'=>'Status'],
    ['Account Number'=>'Holding No / TL No','bKash Transaction ID'=>'Txn ID','Pay Date'=>'Date','Total Amount'=>'Amount','status'=>'Status'],
    ['BENEFICIARYNAME'=>'Holding No','BANKTRANID'=>'Txn ID','TRANDATE'=>'Date','REQAMOUNT'=>'Amount','status'=>'Status'],
    ['E-Holding No'=>'Holding No','Transactio ID'=>'Txn ID','Payment Date'=>'Date','Paid Amount'=>'Amount','status'=>'Status'],
    ['Holding no'=>'Holding No','Payment no'=>'Txn ID','Date'=>'Date','Total amount'=>'Amount','status'=>'Status'],
    ['Transaction Id'=>'Txn ID','Transaction Date'=>'Date','Amount'=>'Amount','status'=>'Status'],
    ['E-Holding Number'=>'Holding No','Transaction ID (DNCC)'=>'Txn ID','Date & Time'=>'Date','Amount BDT'=>'Amount','status'=>'Status'],
    ['Holding No'=>'Holding No','TransactionNo'=>'Txn ID','Transaction Date'=>'Date','Amount'=>'Amount','status'=>'Status']
];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $uploadedFileIds = [];

    for($i=1; $i<=2; $i++){
        if(isset($_FILES["file$i"]) && $_FILES["file$i"]['error'] == 0){
            $fileTmp = $_FILES["file$i"]['tmp_name'];
            $fileName = $_FILES["file$i"]['name'];
            $bankName = $_POST["bank_name$i"] ?? null; // get selected bank name

            // Insert file record with bank_name
            $stmt = $conn->prepare("INSERT INTO uploaded_files (filename, bank_name) VALUES (?, ?)");
            $stmt->bind_param("ss", $fileName, $bankName);
            $stmt->execute();
            $fileId = $stmt->insert_id;
            $uploadedFileIds[] = $fileId;

            // Load Excel directly from temp file
            $spreadsheet = IOFactory::load($fileTmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if(count($rows) < 1) continue;

            $header = array_map('strtolower', $rows[0]);
            $mapping = null;

            foreach($columnsToUse as $map){
                $mapKeysLower = array_map('strtolower', array_keys($map));
                if(count(array_intersect($mapKeysLower, $header)) >= 2){
                    $mapping = $map;
                    break;
                }
            }
            if(!$mapping) continue;

            $colIndex = [];
            foreach($mapping as $excelCol => $dbCol){
                $idx = array_search(strtolower($excelCol), $header);
                $colIndex[$dbCol] = $idx !== false ? $idx : null;
            }

            $chunkSize = 500;
            $dataChunk = [];
            for($r=1; $r<count($rows); $r++){
                $row = $rows[$r];
                $dataRow = [
                    'holding_or_tl' => null,
                    'txn_id' => null,
                    'date' => null,
                    'amount' => null,
                    'gateway' => null,
                    'payment_type' => null,
                    'status' => null
                ];

                foreach($colIndex as $dbCol => $idx){
                    if($idx === null) continue;
                    $value = isset($row[$idx]) ? $row[$idx] : null;

                    if($dbCol == "Holding No / TL No" || $dbCol == "Holding No" || $dbCol == "TL No"){
                        $dataRow['holding_or_tl'] = $value;
                    } elseif($dbCol == "Txn ID") {
                        $dataRow['txn_id'] = $value;
                    } elseif($dbCol == "Date") {
                        $dataRow['date'] = $value;
                    } elseif($dbCol == "Amount") {
                        $dataRow['amount'] = is_numeric($value) ? number_format((float)$value, 2, '.', '') : $value;
                    } elseif($dbCol == "Gateway") {
                        $dataRow['gateway'] = $value;
                    } elseif($dbCol == "Payment Type") {
                        $dataRow['payment_type'] = $value;
                    } elseif($dbCol == "Status") {
                        $dataRow['status'] = $value;
                    }
                }

                $dataChunk[] = $dataRow;

                if(count($dataChunk) >= $chunkSize){
                    insertChunk($dataChunk, $fileId, $conn);
                    $dataChunk = [];
                }
            }

            if(!empty($dataChunk)){
                insertChunk($dataChunk, $fileId, $conn);
            }
        }
    }

    $_SESSION['uploaded_file_ids'] = $uploadedFileIds;
    header("Location: select_column.php");
    exit();
}

function insertChunk($dataChunk, $fileId, $conn){
    $stmt = $conn->prepare("INSERT INTO uploaded_data (holding_or_tl, txn_id, date, amount, gateway, payment_type, status, file_id) VALUES (?,?,?,?,?,?,?,?)");
    foreach($dataChunk as $row){
        $stmt->bind_param("sssssssi", 
            $row['holding_or_tl'], 
            $row['txn_id'], 
            $row['date'], 
            $row['amount'], 
            $row['gateway'], 
            $row['payment_type'], 
            $row['status'], 
            $fileId
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
        <label>File 1:</label><br>
        <input type="file" name="file1" required><br><br>
        <label>Bank Portal:</label><br>
        <select name="bank_name1" required style="padding:8px; border:1px solid #ccc; border-radius:4px;">
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

        <label>File 2:</label><br>
        <input type="file" name="file2" required><br><br>
        <label>Bank Portal:</label><br>
        <select name="bank_name2" required style="padding:8px; border:1px solid #ccc; border-radius:4px;">
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
