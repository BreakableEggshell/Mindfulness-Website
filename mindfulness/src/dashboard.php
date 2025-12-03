<?php
session_start();
include 'config.php';

/* ============================================================
   ABSTRACT CLASS: UserBase
   ============================================================ */
abstract class UserBase {
    protected mysqli $mysqli;
    protected int $user_id;
    protected string $full_name = "";
    protected string $username = "";

    public function __construct(mysqli $db, int $user_id) {
        $this->mysqli = $db;
        $this->user_id = $user_id;
        $this->loadUserData();
    }

    abstract protected function loadUserData(): void;
    abstract public function getGreeting(): string;

    public function getDisplayName(): string {
        return $this->full_name ?: $this->username;
    }
}

/* ============================================================
   CHILD CLASS: Dashboard
   ============================================================ */
class Dashboard extends UserBase {

    protected function loadUserData(): void {
        $stmt = $this->mysqli->prepare("SELECT full_name, username FROM users WHERE user_id=?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $stmt->bind_result($full_name, $username);
        $stmt->fetch();
        $this->full_name = $full_name ?? "";
        $this->username = $username ?? "";
        $stmt->close();
    }

    public function getGreeting(): string {
        return "Hope you have a mindful and relaxing day!";
    }

    // Hardcoded recommended exercises
    public function getRecommendedExercises(): array {
        return [
            "Journaling",
            "Walking",
            "Dancing",
            "Meditation",
            "Stretching",
            "Deep Breathing",
            "Reading a Book",
            "Yoga",
            "Listening to Music",
            "Mindful Cooking"
        ];
    }
}

/* ============================================================
   EXECUTION
   ============================================================ */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$dashboard = new Dashboard($mysqli, $_SESSION['user_id']);

// Pick 5 random exercises from the list
$allExercises = $dashboard->getRecommendedExercises();
shuffle($allExercises);
$recommended = array_slice($allExercises, 0, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Mindfulness Wellness App</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="CSS/dashboard.css">
<style>
    body {
        background-color: #FCE7DF;
        background-image: url('CSS/assets/BG_1.png');
        background-repeat: no-repeat;
        background-size: cover;
        font-family: 'Segoe UI', sans-serif;
    }
    .card-peach {
        background-color: #F7A98F;
        color: #fff;
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .card-peach h3, .card-peach h5 {
        font-weight: bold;
    }
    /* Header text in black */
    .navbar .nav-link {
        color: #000 !important;
        font-weight: 500;
    }
    .navbar-brand {
        color: #000 !important;
        font-weight: bold;
    }
    ul.exercise-list {
        text-align: left;
        padding-left: 20px;
        margin-top: 10px;
        list-style: none;
    }
    ul.exercise-list li {
        margin-bottom: 12px;
        padding: 8px 10px;
        background-color: rgba(255,255,255,0.2);
        border-radius: 6px;
        transition: background-color 0.3s;
        cursor: pointer;
    }
    ul.exercise-list li:hover {
        background-color: rgba(255,255,255,0.35);
    }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light header mb-4">
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

<div class="container">
    <div class="row g-4">
        <!-- Left column: Welcome card -->
        <div class="col-md-6">
            <div class="card-peach">
                <h3>Welcome back, <?= htmlspecialchars($dashboard->getDisplayName()); ?>!</h3>
                <p><?= htmlspecialchars($dashboard->getGreeting()); ?></p>
            </div>
        </div>

        <!-- Right column: Recommended Exercises card -->
        <div class="col-md-6">
            <div class="card-peach">
                <h5>Want to do more today? Try these exercises:</h5>
                <ul class="exercise-list">
                    <?php foreach ($recommended as $exercise) { ?>
                        <li><?= htmlspecialchars($exercise); ?></li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
