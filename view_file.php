<?php
session_start();
include 'db.php';
include 'session.php';   // protect the page
include 'header.php';  
require 'vendor/autoload.php';

// Validate file_id
if(!isset($_GET['file_id'])) die("No file selected.");
$fileId = intval($_GET['file_id']);
$userId = $_SESSION['user_id'];

// Fetch file info, ensuring it belongs to logged-in user
$stmt = $conn->prepare("SELECT filename, type FROM uploaded_files WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $fileId, $userId);
$stmt->execute();
$result = $stmt->get_result();
if(!$file = $result->fetch_assoc()) die("File not found or access denied.");

$fileType = $file['type'];

// Handle status update only for unmatched files
if($fileType === 'unmatched' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']) && isset($_POST['row_ids'])){
    $statuses = $_POST['status'];
    $rowIds = $_POST['row_ids'];
    $updateStmt = $conn->prepare("UPDATE uploaded_data SET status=? WHERE id=? AND file_id=?");
    foreach($rowIds as $i => $rowId){
        $updateStmt->bind_param("sii", $statuses[$i], $rowId, $fileId);
        $updateStmt->execute();
    }
    $success = "Status updated successfully.";
}

// Fetch rows for this file
$stmt = $conn->prepare("SELECT * FROM uploaded_data WHERE file_id=?");
$stmt->bind_param("i", $fileId);
$stmt->execute();
$dataRes = $stmt->get_result();
$rows = $dataRes->fetch_all(MYSQLI_ASSOC);

// Remove unwanted columns
foreach($rows as &$r){
    unset($r['file_id']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View File</title>
    <style>
        body {
            background: #f2f2f2;
            font-family: Arial, sans-serif;
            margin: 0; padding: 0;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; color: #1a73e8; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 14px; }
        th { background-color: #1a73e8; color: #fff; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        input[type="text"] { width: 100%; padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { margin-top: 15px; padding: 10px 20px; background-color: #1a73e8; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        button:hover { background-color: #1666c1; }
        .message { text-align: center; margin-bottom: 15px; color: green; font-weight: bold; }
        a.back-link { display: inline-block; margin-top: 20px; text-decoration: none; color: #1a73e8; font-weight: bold; transition: 0.3s; }
        a.back-link:hover { text-decoration: underline; }
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            th { position: sticky; top: 0; }
            td { padding-left: 50%; position: relative; }
            td::before { position: absolute; left: 10px; width: 45%; white-space: nowrap; font-weight: bold; content: attr(data-label); }
        }
    </style>
</head>
<body>

<div class="container">
    
    <a href="download.php" class="back-link">&laquo; Back to Downloads</a>

    <h2>Viewing: <?= htmlspecialchars($file['filename']) ?> (<?= htmlspecialchars($file['type']) ?>)</h2>

    <?php if(!empty($success)): ?>
        <div class="message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if(empty($rows)): ?>
        <p>No data available.</p>
    <?php else: ?>
        <form method="post">
            <table>
                <tr>
                    <?php foreach(array_keys($rows[0]) as $col): ?>
                        <?php if($col !== 'id'): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>

                <?php foreach($rows as $i => $rowData): ?>
                <tr>
                    <?php foreach($rowData as $col => $val): ?>
                        <?php if($col==='id'): ?>
                            <input type="hidden" name="row_ids[]" value="<?= $val ?>">
                        <?php elseif($col==='status'): ?>
                            <?php if($fileType==='unmatched'): ?>
                                <td><input type="text" name="status[]" value="<?= htmlspecialchars($val) ?>" data-label="Status"></td>
                            <?php else: ?>
                                <td data-label="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($val) ?></td>
                            <?php endif; ?>
                        <?php else: ?>
                            <td data-label="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($val) ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </table>

            <?php if($fileType==='unmatched'): ?>
                <button type="submit">Save Status</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>

</div>

</body>
</html>
