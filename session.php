<?php
// Start the session if not started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'db.php';

// Check if the user still exists in the database
$userId = intval($_SESSION['user_id']);
$result = $conn->query("SELECT id FROM users WHERE id = $userId LIMIT 1");
if ($result->num_rows === 0) {
    // User no longer exists, destroy session
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
