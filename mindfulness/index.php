<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: src/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mindfulness Wellness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="index.css">
    
</head>

<body>

<!-- HEADER -->
<div class="header">
    <h4 class="m-0">Mindfulness</h4>
    <div>
        <a href="src/login.php" class="btn-login">Login</a>
        <a href="src/register.php" class="ms-3">Register</a>
    </div>
</div>

<!-- MAIN LAYOUT -->
<div class="container-fluid mt-5">
    <div class="row align-items-center">

        <!-- FIXED IMAGE PATH -->
       

        <!-- LEFT SIDE -->
        <div class="col-md-6 hero-left">
            <h1 class="hero-title">Fuel wellness through mindful habits</h1>
            <p class="hero-text">
                Build better routines, improve your emotional health, and track your daily mindfulness progress.
            </p>

            <!-- FIXED PATH -->
            <a href="src/register.php" class="btn-dark-purple me-3">Get Started</a>

        </div>

        <!-- RIGHT SIDE -->
        <div class="col-md-5">

            <div class="preview-card">
                <h5 class="fw-bold">Daily Mindfulness Tips</h5>
                <p class="text-muted">Small steps to improve your well-being.</p>
                <ul>
                    <li>Take 5 slow breaths before starting work.</li>
                    <li>Write down one thing you're grateful for.</li>
                    <li>Spend 3 minutes observing your surroundings.</li>
                </ul>
            </div>

            <div class="preview-card">
                <h5 class="fw-bold">Journeys</h5>
                <p class="text-muted">Track your progress and build meaningful habits.</p>
            </div>

        </div>

    </div>
</div>

</body>
</html>
