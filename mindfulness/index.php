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

    <style>
        body {
            background-color: #FCE7DF;
            font-family: 'Segoe UI', sans-serif;
        }
        .header {
            background: white;
            padding: 15px 40px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header a {
            text-decoration: none;
            font-weight: 600;
            margin-left: 20px;
            color: #333;
        }
        .btn-login {
            background-color: #e98a6f;
            color: white !important;
            padding: 8px 18px;
            border-radius: 6px;
        }
        .btn-login:hover {
            background-color: #d47860;
        }
        .hero-left {
            padding: 60px;
        }
        .hero-title {
            font-size: 3rem;
            font-weight: 700;
        }
        .hero-text {
            color: #444;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        .btn-dark-purple {
            background-color: #4b1f47;
            padding: 12px 24px;
            color: white;
            border-radius: 8px;
            font-weight: 600;
            border: none;
        }
        .btn-dark-purple:hover {
            background-color: #3d1739;
        }
        .btn-light {
            background-color: white;
            padding: 12px 24px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-weight: 600;
        }
        .preview-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        img.preview-img {
            width: 100%;
            border-radius: 12px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

<!-- HEADER -->
<div class="header">
    <h4 class="m-0">Penis</h4>
    <div>
        <a href="src/login.php" class="btn-login">Login</a>
        <a href="src/register.php" class="ms-3">Register</a>
    </div>
</div>

<!-- MAIN LAYOUT -->
<div class="container-fluid mt-5">
    <div class="row align-items-center">

        <!-- FIXED IMAGE PATH -->
        <img src="assets/Test.png" alt="" style="width:150px;">

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
