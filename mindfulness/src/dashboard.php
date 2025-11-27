<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $mysqli->prepare("
    SELECT u.full_name, u.username, r.role_name
    FROM users u
    LEFT JOIN user_roles r ON u.role_id = r.role_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$role = $user['role_name'] ?? "User";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Mindfulness Wellness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #FFE9D6;
            font-family: 'Segoe UI', sans-serif;
        }
        .header {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .navbar-nav .nav-link {
            font-weight: 600;
            color: #333 !important;
            margin-left: 15px;
        }
        .card {
            background-color: #FFF4EC;
            border: 1px solid #FFCCB0;
        }
        h3, h5 {
            color: #D47456;
        }
        .table thead {
            background-color: #FFD6BD;
            color: #8A3F27;
        }
        .btn-outline-danger {
            border-color: #E67A59;
            color: #E67A59;
        }
        .btn-outline-danger:hover {
            background-color: #E67A59;
            color: white;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #FFF0E6 !important;
        }
        .text-muted {
            color: #B67356 !important;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light header">
    <a class="navbar-brand fw-bold" href="dashboard.php">Mindfulness</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" href="./dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="./exerciselist.php">Exercises</a></li>
            <li class="nav-item"><a class="nav-link" href="./progresstrack.php">Progress Tracker</a></li>
            <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container py-3">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-3">Welcome back, <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?> </h3>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
