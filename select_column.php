<?php
session_start();
include 'db.php';
include 'session.php'; // protect page
include 'header.php';

// Check user logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

// Check uploaded files
if(empty($_SESSION['uploaded_file_ids']) || count($_SESSION['uploaded_file_ids']) < 2){
    header("Location: upload.php");
    exit();
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
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }

        body {
            background: #f2f2f2;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Main container for form */
        .main-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            margin-top: 80px; /* leave space for header */
        }

        .form-container {
            background: #fff;
            padding: 40px 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            width: 400px;
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #1a73e8;
        }

        label {
            font-weight: 600;
            display: block;
            margin-bottom: 6px;
        }

        select {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #1a73e8;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
        }

        button:hover { background: #1666c1; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="form-container">
        <h2>Select Columns to Compare</h2>
        <form action="compare.php" method="post">
            <label for="col1">Select column from File 1:</label>
            <select name="col1" id="col1" required>
                <?php foreach($columns[$fileIds[0]] as $col): ?>
                    <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="col2">Select column from File 2:</label>
            <select name="col2" id="col2" required>
                <?php foreach($columns[$fileIds[1]] as $col): ?>
                    <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Compare</button>
        </form>
    </div>
</div>

</body>
</html>

