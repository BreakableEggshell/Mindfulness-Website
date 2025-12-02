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
            <div class="start">
                <a href="src/register.php">
                    <img class="start_img" src="assets/Board.png" alt="assets/Board.png">
                </a>
            </div>
            <div class="title_wrapper">
                    <img class="logo" src="assets/logo_mindfulness.png" alt="mindfulness/assets/logo_mindfulness.png">
                    <div class="flavour_text">
                    <h1 class="hero-title">Wellness starts with you</h1>
                    </div>
            </div>
            <div class="col-md-5">
            <div class="preview-card">
                <h5 class="fw-bold">What we are</h5>
                <p class="text-muted">A customer first, service. That seeks to better the users day to day life.</p>
            </div>

            <div class="preview-card">
                <h5 class="fw-bold">What we offer</h5>
                <p class="text-muted">Track your progress and build meaningful habits.</p>
                <p class="text-muted">Small and non intensive exercises help keep your body</p>
                <p class="text-muted">Tasks that help keep your hands busy to keep your mind off things. </p>
            </div>

        </div>
            </div>

        <!-- RIGHT SIDE -->
        

    </div>
</div>

</body>
</html>
