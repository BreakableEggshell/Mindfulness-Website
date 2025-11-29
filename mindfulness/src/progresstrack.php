<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $mysqli->prepare("
    SELECT full_name, username 
    FROM users 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exercises - Mindfulness Wellness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="CSS\exerciselist.css">
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

<div class="container py-4">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-3">Your Completed Exercises</h3>

<?php
// Fetch completed activities within last 5 days
$stmt = $mysqli->prepare("
    SELECT 
        ap.activity_id,
        ap.is_done,
        ap.progress_date,
        a.activity_name,
        a.duration_text,
        s.sub_task_name
    FROM activity_progress ap
    INNER JOIN activities a ON ap.activity_id = a.activity_id
    LEFT JOIN sub_tasks s ON a.sub_task_id = s.sub_task_id
    WHERE ap.user_id = ?
    AND ap.is_done = 1
    AND ap.progress_date >= CURDATE() - INTERVAL 5 DAY
    ORDER BY ap.progress_date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$current_date = null;

if ($result->num_rows === 0) {
    echo "<p class='text-muted'>No completed activities in the past 5 days.</p>";
}

while ($row = $result->fetch_assoc()) {
    $date = date("F j, Y", strtotime($row['progress_date']));

    if ($current_date !== $date) {
        if ($current_date !== null) echo "</ul>";
        $current_date = $date;
        echo "<h4 class='mt-3'>$current_date</h4><ul>";
    }

    echo "<li><strong>{$row['activity_name']}</strong> – "
        . htmlspecialchars($row['sub_task_name'])
        . " ({$row['duration_text']})</li>";
}

if ($current_date !== null) echo "</ul>";
?>

    </div>
</div>

</body>
</html>
