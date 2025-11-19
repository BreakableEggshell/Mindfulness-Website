<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'mindfulness';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

if (!$mysqli->set_charset("utf8mb4")) {
    die("Error loading character set utf8mb4: " . $mysqli->error);
}

// $host = 'localhost';
// $user = 's24103884_mindfulness';
// $pass = 'mind00P25';
// $db   = 's24103884_mindfulness';

// $mysqli = new mysqli($host, $user, $pass, $db);

// if ($mysqli->connect_error) {
//     die("❌ Database connection failed: " . $mysqli->connect_error);
// }

// if (!$mysqli->set_charset("utf8mb4")) {
//     die("❌ Error loading character set utf8mb4: " . $mysqli->error);
// }
?>




