<?php
session_start();
include 'db.php';
require 'vendor/autoload.php';

if(!isset($_GET['file_id'])) die("No file selected.");
$fileId = intval($_GET['file_id']);

// Fetch file info
$res = $conn->query("SELECT filename, type FROM uploaded_files WHERE id=$fileId");
if(!$file = $res->fetch_assoc()) die("File not found.");

$fileType = $file['type'];

// Handle status update only for unmatched files
if($fileType === 'unmatched' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']) && isset($_POST['row_ids'])){
    $statuses = $_POST['status'];
    $rowIds = $_POST['row_ids'];
    foreach($rowIds as $i => $rowId){
        $stmt = $conn->prepare("UPDATE uploaded_data SET status=? WHERE id=?");
        $stmt->bind_param("si", $statuses[$i], $rowId);
        $stmt->execute();
    }
    $success = "Status updated successfully.";
}

// Fetch rows
$dataRes = $conn->query("SELECT * FROM uploaded_data WHERE file_id=$fileId");
$rows = [];
while($r = $dataRes->fetch_assoc()) $rows[] = $r;

// Remove unwanted columns
foreach($rows as &$r){
    unset($r['file_id']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View File</title>
</head>
<body>
<h2>Viewing: <?= htmlspecialchars($file['filename']) ?> (<?= htmlspecialchars($file['type']) ?>)</h2>

<?php if(!empty($success)) echo "<p style='color:green;'>$success</p>"; ?>

<?php if(empty($rows)): ?>
<p>No data available.</p>
<?php else: ?>
<form method="post">
<table border="1" cellpadding="5" cellspacing="0">
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
                <td><input type="text" name="status[]" value="<?= htmlspecialchars($val) ?>"></td>
            <?php else: ?>
                <td><?= htmlspecialchars($val) ?></td>
            <?php endif; ?>
        <?php else: ?>
            <td><?= htmlspecialchars($val) ?></td>
        <?php endif; ?>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>
</table>
<?php if($fileType==='unmatched'): ?>
    <br><button type="submit">Save Status</button>
<?php endif; ?>
</form>
<?php endif; ?>

<br><a href="download.php">Back to Downloads</a>
</body>
</html>
