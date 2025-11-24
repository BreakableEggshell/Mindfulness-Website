<?php
session_start();
include('config.php');

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {

        $stmt = $mysqli->prepare("SELECT u.user_id, u.username, u.password_hash, u.role_id, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.role_id WHERE u.username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password_hash'])) {

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = isset($user['role_id']) ? (int)$user['role_id'] : 0;
                $_SESSION['role_name'] = isset($user['role_name']) ? $user['role_name'] : '';

                error_log(sprintf("[login] user_id=%d username=%s role_id=%d role_name=%s", $user['user_id'], $user['username'], $_SESSION['role_id'], $_SESSION['role_name']));

                if (strcasecmp($_SESSION['role_name'], 'Admin') === 0) {
                    header("Location: ../admin/admindashboard.php");
                    exit;
                }

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "User not found.";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mindfulness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <style>
        .btn-peach {
            background-color: #F7A98F !important;
            border: none !important;
            color: white !important;
            padding: 12px;
            font-size: 1rem;
            border-radius: 6px;
            width: 100%;
            display: block;
            margin: 0 auto;
        }
        .btn-peach:hover {
            background-color: #e98a6f !important;
        }
        body {
            background: #f6f8fa;
        }
    </style>
</head>

<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="text-center mb-4">Mindfulness</h3>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required autocomplete="off">
                        </div>

                        <button type="submit" class="btn-peach">Login</button>
                    </form>

                    <div class="text-center mt-3">
                        <p>Don't have an account? <a href="register.php">Register</a></p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
