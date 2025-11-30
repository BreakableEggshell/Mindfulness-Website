<?php
session_start();
include 'config.php'; // Include your database connection

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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

// Fetch completed activities from the activity_progress table
$sql_progress = "
    SELECT 
        DATE(ap.progress_date) AS completion_date,
        a.activity_name,
        a.duration_text
    FROM activity_progress ap
    JOIN activities a ON ap.activity_id = a.activity_id
    WHERE ap.user_id = ? AND ap.is_done = 1
    ORDER BY completion_date DESC, ap.progress_date DESC
";

$stmt_progress = $mysqli->prepare($sql_progress);
$stmt_progress->bind_param("i", $user_id);
$stmt_progress->execute();
$result_progress = $stmt_progress->get_result();

$completed_activities_by_date = [];
if ($result_progress->num_rows > 0) {
    while ($row = $result_progress->fetch_assoc()) {
        $date = $row['completion_date'];
        $activity_display = htmlspecialchars($row['activity_name']) . ' (' . htmlspecialchars($row['duration_text']) . ')';
        
        $completed_activities_by_date[$date][] = $activity_display;
    }
}

$stmt_progress->close();
$mysqli->close();

$message = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Progress Tracker - Mindfulness Wellness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="CSS/exerciselist.css"> 
    <style>
        .completed-exercises-box {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            background-color: #f8f8f8;
        }
        .completed-exercises-box h4 {
            color: #d9534f;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .date-group {
            margin-bottom: 20px;
        }
        .date-group h5 {
            font-weight: bold;
            color: #333;
        }
        .date-group ul {
            list-style-type: none;
            padding-left: 0;
        }
        .date-group ul li::before {
            content: "\2022";
            color: #d9534f;
            font-weight: bold;
            display: inline-block;
            width: 1em;
            margin-left: -1em;
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
            <li class="nav-item"><a class="nav-link active" aria-current="page" href="./progresstrack.php">Progress Tracker</a></li>
            <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container py-5">
    <div class="card p-4 shadow-lg">
        <h2 class="mb-4">Progress Tracker 📊</h2>
        
        <?php if ($message === 'completed'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Great job!</strong> Your progress has been recorded. Keep up the good work!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="completed-exercises-box">
            <h4>Your Completed Exercises</h4>
            
            <?php if (empty($completed_activities_by_date)): ?>
                <p class="text-muted text-center">You haven't completed any exercises yet. Go to <a href="exerciselist.php">Exercises</a> to start!</p>
            <?php else: ?>
                <?php 
                foreach ($completed_activities_by_date as $date => $activities):
                    $display_date = date('F j, Y', strtotime($date));
                ?>
                    <div class="date-group">
                        <h5><?= $display_date ?></h5>
                        <ul>
                            <?php foreach ($activities as $activity): ?>
                                <li><?= $activity ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>