<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$activity_id = (int)($_GET['activity_id'] ?? 0);

if ($activity_id <= 0) {
    die("Error: Invalid activity ID provided.");
}

// Fetch user info
$stmt = $mysqli->prepare("
    SELECT u.full_name, u.username
    FROM users u
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- 1. Fetch Activity Details (Name, Duration) ---
$stmt = $mysqli->prepare("
    SELECT activity_name, duration_text
    FROM activities
    WHERE activity_id = ?
");
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$activity_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$activity_details) {
    die("Error: Activity not found.");
}

$activity_name = htmlspecialchars($activity_details['activity_name']);
$duration_text = htmlspecialchars($activity_details['duration_text']);

// --- 2. Fetch ALL Sub-Tasks (Steps) for this Activity ---
// This assumes the sub_tasks table uses activity_id as a foreign key to link steps to the template.
$stmt = $mysqli->prepare("
    SELECT sub_task_name, description
    FROM sub_tasks
    WHERE activity_id = ?
    ORDER BY sub_task_id ASC
");
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$sub_tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Steps: <?= $activity_name ?> - Mindfulness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="CSS/viewexercises.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light header">
    <a class="navbar-brand fw-bold" href="dashboard.php">Mindfulness</a>
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
        <a href="exerciselist.php" class="btn btn-sm btn-outline-secondary mb-4" style="width: auto;">← Back to Exercises</a>

        <h3 class="mb-2"><?= $activity_name ?></h3>
        <h5 class="text-muted mb-4">Duration: <?= $duration_text ?></h5>
        
        <hr>

        <h4 class="mb-3">Activity Steps (Sub-Tasks)</h4>

        <?php if (!empty($sub_tasks)): ?>
            <div class="step-card">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Sub-Task</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sub_tasks as $task): ?>
                            <tr class="step-row">
                                <td><?= htmlspecialchars($task['sub_task_name']) ?></td>
                                <td><?= htmlspecialchars($task['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">No steps have been defined for this activity yet.</div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>