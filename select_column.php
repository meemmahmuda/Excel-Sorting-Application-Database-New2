<?php
session_start();
include 'db.php';

if(!isset($_SESSION['uploaded_file_ids'])){
    die("No uploaded files found.");
}

$fileIds = $_SESSION['uploaded_file_ids'];
$columns = [];

foreach($fileIds as $id){
    $result = $conn->query("SELECT * FROM uploaded_data WHERE file_id = $id LIMIT 1");
    $row = $result->fetch_assoc();
    if($row){
        $cols = array_keys($row);
        $cols = array_filter($cols, fn($c) => !in_array($c,['id','file_id'])); // exclude id and file_id
        $columns[$id] = $cols;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Select Columns</title>
</head>
<body>
<h2>Select Columns to Compare</h2>
<form action="compare.php" method="post">
    <label for="col1">Select column from File 1:</label>
    <select name="col1" id="col1" required>
        <?php foreach($columns[$fileIds[0]] as $col): ?>
            <option value="<?= $col ?>"><?= $col ?></option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <label for="col2">Select column from File 2:</label>
    <select name="col2" id="col2" required>
        <?php foreach($columns[$fileIds[1]] as $col): ?>
            <option value="<?= $col ?>"><?= $col ?></option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <button type="submit">Compare</button>
</form>
</body>
</html>
