<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../src/config.php';

if (isset($host) && $host !== '') {
    define('DB_HOST', $host);
} else {
    define('DB_HOST', 'localhost');
}

if (isset($user) && $user !== '') {
    define('DB_USER', $user);
} else {

    define('DB_USER', 'root');
}

if (isset($pass)) {
    define('DB_PASS', $pass);
} else {
    define('DB_PASS', '');
}

if (isset($db) && $db !== '') {
    define('DB_NAME', $db);
} else {
    define('DB_NAME', 'mindfulness');
}


class Database {
    private $mysqli;

    public function __construct($host, $user, $pass, $dbName) {
        $this->mysqli = new mysqli($host, $user, $pass, $dbName);
        
        if ($this->mysqli->connect_error) {
            throw new Exception("Database connection failed: " . $this->mysqli->connect_error);
        }
    }

    public function getConnection() {
        return $this->mysqli;
    }
}

class AuthManager {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db->getConnection();
    }

    public function requireLogin($redirectPath = '../src/index.php') {
        if (!isset($_SESSION['user_id'])) {
            header("Location: $redirectPath");
            exit;
        }
        return $_SESSION['user_id'];
    }

    public function getUserDetails(int $user_id) {
        $stmt = $this->db->prepare("SELECT full_name, username, email FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user;
    }

    public function changePassword(int $user_id, string $current, string $new, string $confirm) : string {
        
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            return 'User not found.';
        }

        if (!password_verify($current, $result['password_hash'])) {
            return 'Current password is incorrect.';
        }

        if ($new !== $confirm) {
            return 'New password and confirm password do not match.';
        }
        
        $hashed_password = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        $stmt->execute();
        $stmt->close();
        
        return 'Password updated successfully.';
    }
}

class SettingsView {
    private $user;
    private $message;

    public function __construct(array $user, string $message = '') {
        $this->user = $user;
        $this->message = $message;
    }

    public function render() {
        $fullName = htmlspecialchars($this->user['full_name']);
        $email = htmlspecialchars($this->user['email']);
        $messageHtml = $this->message ? "<div class=\"alert alert-info\">" . htmlspecialchars($this->message) . "</div>" : '';
        
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - Mindfulness Wellness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #FFE9D6; font-family: 'Segoe UI', sans-serif; }
        .header { background: white; padding: 15px 30px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .navbar-nav .nav-link { font-weight: 600; color: #333 !important; margin-left: 15px; }
        .card { background-color: #FFF4EC; border: 1px solid #FFCCB0; }
        h3, h5 { color: #D47456; }
        .text-muted { color: #B67356 !important; }
        .form-label { font-weight: 500; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light header">
    <a class="navbar-brand fw-bold" href="admindashboard.php">Mindfulness Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" href="editusers.php">Edit Users</a></li>
            <li class="nav-item"><a class="nav-link" href="setting.php">Settings</a></li>
            <li class="nav-item"><a class="nav-link" href="../src/logout.php">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container py-4">
    <div class="card p-4 shadow-sm mx-auto" style="max-width: 500px;">
        <h3 class="mb-4">Settings</h3>

        <p><strong>Name:</strong> {$fullName}</p>
        <p><strong>Email:</strong> {$email}</p>

        {$messageHtml}

        <form method="POST">
            <div class="mb-3">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary">Change Password</button>
        </form>

        <div class="mt-3">
            <a href="forgot_password.php" class="text-decoration-none">Forgot Password?</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
    }
}

try {
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    $auth = new AuthManager($db);
    $message = '';
    
    $user_id = $auth->requireLogin();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $message = $auth->changePassword($user_id, $current_password, $new_password, $confirm_password);
    }

    $user = $auth->getUserDetails($user_id);

    $view = new SettingsView($user, $message);
    $view->render();

} catch (Exception $e) {
    die("A critical error occurred: " . $e->getMessage());
}
?>
