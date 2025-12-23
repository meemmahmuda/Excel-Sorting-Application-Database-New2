<?php
session_start();
require 'vendor/autoload.php';
include 'db.php';
include 'session.php';  
include 'header.php';  

use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

$message = "";


$bankPortals = [
    "DNCC Bank Portal","CCC Bank Portal","DBBL Holding DNCC", "DBBL Holding CCC","DBBL Holding Due DNCC", "DBBL Holding Due CCC", "DBBL MFS DNCC", "DBBL MFS CCC","DBBL MFS Due DNCC", "DBBL MFS Due CCC",
    "Bkash Holding","Sonali Bank DNCC", "Sonali Bank CCC","Standard Bank","Modhumoti Bank",
    "Trust TAP Holding","Trust TAP TL","Upay MFS","OK Wallet",
    "DBBL TL Collection DNCC", "DBBL TL Collection CCC","DBBL TL Correction DNCC", "DBBL TL Correction CCC","Bkash TL"
];


$holdingCols = ['holding no', 'tl no', 'holding no / tl no', 'e-holding', 's/l', 'e-holding no', 'e-holding number', 'bill no', 'account number', 'docnumber', 'document number', 'biller_ref_no', 'beneficiaryname'];
$txnCols     = ['txn id', 'tranno', 'transaction id', 'transactio id', 'bkash transaction id', 'transactionno', 'payment no', 'transaction id (dncc)', 'transaction_id', 'banktranid','transaction number'];
$dateCols    = ['date', 'pay date', 'txn_date', 'trandate', 'date & time', 'territory code', 'payment date', 'transaction date', 'cdate'];
$amountCols  = ['amount', 'paid amount', 'txn_amt', 'total amount', 'reqamount', 'amount bdt', 'totalamt'];
$paymentTypeCols = ['paymenttype', 'payment type'];

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $uploadedFileIds = [];
    $userId = $_SESSION['user_id'];

    for($i=1; $i<=2; $i++){
        if(isset($_FILES["file$i"]) && $_FILES["file$i"]['error'] === 0){

            $fileTmp = $_FILES["file$i"]['tmp_name'];
            $fileName = $_FILES["file$i"]['name'];
            $bankName = $_POST["bank_name$i"] ?? null;

           
            if(!in_array($bankName, $bankPortals)){
                $message = "Invalid bank selected for file $i!";
                break;
            }

           
            $stmt = $conn->prepare("INSERT INTO uploaded_files (filename, bank_name, type, user_id) VALUES (?,?,?,?)");
            $type = 'uploaded';
            $stmt->bind_param("sssi", $fileName, $bankName, $type, $userId);
            $stmt->execute();
            $fileId = $stmt->insert_id;
            $uploadedFileIds[] = $fileId;

        
            $spreadsheet = IOFactory::load($fileTmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            if(count($rows) < 1) continue;

           
            $header = array_map(fn($h) => strtolower(trim(str_replace("\xc2\xa0", ' ', $h))), $rows[0]);

         
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
                elseif(in_array($col, $paymentTypeCols)) $colIndex['payment_type'] = $idx;
                elseif(strpos($col, 'gateway') !== false) $colIndex['gateway'] = $idx;
                elseif(strpos($col, 'status') !== false) $colIndex['status'] = $idx;
                elseif($col === 'payment type' || $col === 'payment method') $colIndex['payment_type'] = $idx;
            }

           
            insertRows($rows, $fileId, $conn, $colIndex, $type);
        }
    }

    if(!empty($uploadedFileIds)){
        $_SESSION['uploaded_file_ids'] = $uploadedFileIds;
        header("Location: select_column.php");
        exit();
    }
}

function insertRows($rows, $fileId, $conn, $colIndex, $type){
    $chunkSize = 500;
    $dataChunk = [];

    for($r=1; $r<count($rows); $r++){
        $row = $rows[$r];


        $isBlank = true;
        foreach($row as $cell){
            if(trim($cell) !== '') { $isBlank = false; break; }
        }
        if($isBlank) continue;

        
        if(stripos(implode(' ', $row), 'total') !== false) continue;

        $dataChunk[] = [
            'holding_or_tl' => $colIndex['holding_or_tl'] !== null ? $row[$colIndex['holding_or_tl']] : null,
            'txn_id'        => $colIndex['txn_id'] !== null ? $row[$colIndex['txn_id']] : null,
            'date'          => $colIndex['date'] !== null ? $row[$colIndex['date']] : null,
            'amount'        => $colIndex['amount'] !== null ? (is_numeric($row[$colIndex['amount']]) ? number_format((float)$row[$colIndex['amount']],2,'.','') : $row[$colIndex['amount']]) : null,
            'gateway'       => $colIndex['gateway'] !== null ? $row[$colIndex['gateway']] : null,
            'payment_type'  => $colIndex['payment_type'] !== null ? $row[$colIndex['payment_type']] : null,
            'status'        => $colIndex['status'] !== null ? $row[$colIndex['status']] : null
        ];

        if(count($dataChunk) >= $chunkSize){
            saveChunk($dataChunk, $fileId, $conn, $type);
            $dataChunk = [];
        }
    }

    if(!empty($dataChunk)) saveChunk($dataChunk, $fileId, $conn, $type);
}

function saveChunk($dataChunk, $fileId, $conn, $type){
    $stmt = $conn->prepare("INSERT INTO uploaded_data (holding_or_tl, txn_id, date, amount, gateway, payment_type, status, file_id, type) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach($dataChunk as $row){
        $stmt->bind_param("sssssssis", 
            $row['holding_or_tl'], $row['txn_id'], $row['date'], $row['amount'], 
            $row['gateway'], $row['payment_type'], $row['status'], $fileId, $type
        );
        $stmt->execute();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Excel Files</title>
    <style>
       
        * { box-sizing: border-box; margin:0; padding:0; font-family: Arial,sans-serif; }
        body { background:#f2f2f2; display:flex; flex-direction:column; min-height:100vh; }
        .main-container { flex:1; display:flex; justify-content:center; align-items:center; padding:20px; margin-top:80px; }
        .upload-container { background:#fff; padding:40px 30px; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.1); width:400px; }
        h2 { text-align:center; margin-bottom:30px; color:#1a73e8; }
        label { font-weight:600; display:block; margin-bottom:6px; }
        input[type="file"], select { width:100%; padding:10px 12px; margin-bottom:20px; border:1px solid #ccc; border-radius:6px; font-size:14px; }
        button { width:100%; padding:12px; background:#1a73e8; color:#fff; border:none; border-radius:6px; font-size:16px; cursor:pointer; font-weight:bold; transition:0.3s; }
        button:hover { background:#1666c1; }
        .message { text-align:center; margin-bottom:15px; color:red; font-weight:bold; }
    </style>
</head>
<body>
<div class="main-container">
    <div class="upload-container">
        <h2>Upload 2 Excel Files</h2>

        <?php if(!empty($message)): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <label>File 1:</label>
            <input type="file" name="file1" required>
            <label>Bank Portal:</label>
            <select name="bank_name1" required>
                <option value="">-- Select Bank --</option>
                <?php foreach($bankPortals as $bank): ?>
                    <option value="<?= htmlspecialchars($bank) ?>"><?= htmlspecialchars($bank) ?></option>
                <?php endforeach; ?>
            </select>

            <label>File 2:</label>
            <input type="file" name="file2" required>
            <label>Bank Portal:</label>
            <select name="bank_name2" required>
                <option value="">-- Select Bank --</option>
                <?php foreach($bankPortals as $bank): ?>
                    <option value="<?= htmlspecialchars($bank) ?>"><?= htmlspecialchars($bank) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Upload</button>
        </form>
    </div>
</div>
</body>
</html>
