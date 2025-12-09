<?php
session_start();
include 'db.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Delete file record
if(isset($_GET['delete_file'])){
    $fileId = intval($_GET['delete_file']);
    $conn->query("DELETE FROM uploaded_files WHERE id=$fileId");
    $conn->query("DELETE FROM uploaded_data WHERE file_id=$fileId");
    header("Location: download.php");
    exit();
}

// Fetch all files
$allFiles = [];
$res = $conn->query("SELECT * FROM uploaded_files ORDER BY id ASC");
while($row = $res->fetch_assoc()){
    $allFiles[] = $row;
}

// Clean rows for Excel export
function cleanRows($rows){
    if(empty($rows)) return [];
    foreach($rows as &$row){
        unset($row['id'], $row['file_id']);
        if(!isset($row['status'])) $row['status'] = '';
    }
    $columns = array_keys(reset($rows));
    foreach($columns as $col){
        if($col === 'status') continue;
        $allEmpty = true;
        foreach($rows as $r){
            if(isset($r[$col]) && trim($r[$col]) !== ''){
                $allEmpty = false;
                break;
            }
        }
        if($allEmpty){
            foreach($rows as &$r) unset($r[$col]);
        }
    }
    return $rows;
}

// Generate Excel and download
function downloadExcel($rows, $filename){
    if(empty($rows)) return;
    $rows = cleanRows($rows);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(array_keys(reset($rows)), null, 'A1'); // header
    $sheet->fromArray(array_map('array_values', $rows), null, 'A2'); // data

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

// Download requested file from DB
if(isset($_GET['download_file'])){
    $fileId = intval($_GET['download_file']);
    $res = $conn->query("SELECT * FROM uploaded_files WHERE id=$fileId");
    if($row = $res->fetch_assoc()){
        $dataRes = $conn->query("SELECT * FROM uploaded_data WHERE file_id=".$fileId);
        $rows = [];
        while($r = $dataRes->fetch_assoc()) $rows[] = $r;
        downloadExcel($rows, $row['filename']);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Download Files</title>
    <style>
        table { border-collapse: collapse; width: 80%; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        th { background-color: #f0f0f0; }
        button { margin-left: 5px; }
    </style>
</head>
<body>
<h2>All Uploaded and Unmatched Files</h2>
<table>
    <tr>
        <!-- <th>#</th> -->
        <th>Filename</th>
        <th>Actions</th>
    </tr>
    <?php foreach($allFiles as $index => $file): ?>
    <tr>
    
        <td><?= htmlspecialchars($file['filename']) ?></td>
        <td>
            <a href="download.php?download_file=<?= $file['id'] ?>"><button>Download</button></a>
            <a href="download.php?delete_file=<?= $file['id'] ?>" onclick="return confirm('Are you sure to delete this file?')"><button>Delete</button></a>
            <a href="view_file.php?file_id=<?= $file['id'] ?>"><button>View</button></a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<br>
<a href="upload.php"><button>Upload More Files</button></a>
</body>
</html>
