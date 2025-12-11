<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "excel_practice";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure UTF-8 support
$conn->set_charset("utf8mb4");
?>
