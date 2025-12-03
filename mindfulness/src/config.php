<?php
// Show all PHP errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Local database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'mindfulness';

// Create MySQLi connection
$mysqli = new mysqli($host, $user, $pass, $db);

// Check connection
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Set charset
if (!$mysqli->set_charset("utf8mb4")) {
    die("Error loading character set utf8mb4: " . $mysqli->error);
}

?>
