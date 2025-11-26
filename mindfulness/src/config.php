<?php
$host = 'localhost';
$user = 's24101113_mindfulness';
$pass = 'Mindfulness4$';
$db   = 's24101113_mindfulness';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

if (!$mysqli->set_charset("utf8mb4")) {
    die("Error loading character set utf8mb4: " . $mysqli->error);
}

?>




