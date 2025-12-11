<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


include 'db.php';


$userId = intval($_SESSION['user_id']);
$result = $conn->query("SELECT id FROM users WHERE id = $userId LIMIT 1");
if ($result->num_rows === 0) {
    
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
