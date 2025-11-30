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
    public function getConnection() { return $this->mysqli; }
}

class AuthManager {
    private $db;
    public function __construct(Database $db) { $this->db = $db->getConnection(); }

    public function requireLogin($redirectPath = 'index.php') {
        if (!isset($_SESSION['user_id'])) { header("Location: $redirectPath"); exit; }
        return $_SESSION['user_id'];
    }

    public function getCurrentUser(int $user_id) {
        $stmt = $this->db->prepare("SELECT u.full_name, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user;
    }
    
    public function requireRole($user, $requiredRole = 'Admin', $redirectPath = 'dashboard.php') {
        $currentRole = $user['role_name'] ?? 'User';
        if ($currentRole !== $requiredRole) { header("Location: $redirectPath"); exit; }
    }
}

class ActivityManager {
    private $db;
    public function __construct(Database $db) { $this->db = $db->getConnection(); }

    public function addActivity(int $adminId, string $name, string $duration) {
        if (empty($name) || empty($duration)) {
            return "Error: Exercise Name and Duration are required.";
        }
        
        $sql = "INSERT INTO activities (activity_name, duration_text, user_id) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt === false) { return "Error preparing statement: " . $this->db->error; }
        
        $stmt->bind_param("ssi", $name, $duration, $adminId);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        } else {
            return "Database Error: " . $stmt->error;
        }
    }
}

class AdminAddExerciseView {
    private $user;
    private $message;

    public function __construct($user, $message) {
        $this->user = $user;
        $this->message = $message;
    }

    public function render() {
        $fullName = htmlspecialchars($this->user['full_name'] ?? $this->user['username'] ?? 'Admin');
        $messageHtml = $this->message ? "<div class=\"alert alert-info\">" . htmlspecialchars($this->message) . "</div>" : '';
        
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Exercise - Mindfulness Wellness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #FFE9D6; font-family: 'Segoe UI', sans-serif; }
        .header { background: white; padding: 15px 30px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .navbar-nav .nav-link { font-weight: 600; color: #333 !important; margin-left: 15px; }
        .card { background-color: #FFF4EC; border: 1px solid #FFCCB0; }
        h3 { color: #D47456; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light header">
    <a class="navbar-brand fw-bold" href="admindashboard.php">Mindfulness Admin</a>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" href="editusers.php">Edit Users</a></li>
            <li class="nav-item"><a class="nav-link" href="addexercise.php">Add New Exercise</a></li>
            <li class="nav-item"><a class="nav-link" href="listofexercise.php">List New Exercise</a></li>
            <li class="nav-item"><a class="nav-link" href="setting.php">Settings</a></li>
            <li class="nav-item"><a class="nav-link" href="../src/logout.php">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container py-3">
    <div class="card p-4 shadow-sm mx-auto" style="max-width: 600px;">
        <h3 class="mb-3">STEP 1: Create New Activity</h3>
        <p class="text-muted">Logged in as: {$fullName}</p>

        {$messageHtml}

        <form method="POST">
            <div class="mb-3">
                <label for="activity_name" class="form-label">Exercise Name</label>
                <input type="text" class="form-control" id="activity_name" name="activity_name" required>
            </div>
            
            <div class="mb-3">
                <label for="duration_text" class="form-label">Duration Text (e.g., 10 minutes)</label>
                <input type="text" class="form-control" id="duration_text" name="duration_text" required>
            </div>

            <button type="submit" class="btn btn-primary">Add Sub-Tasks</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
    }
}

$message = '';

try {
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $auth = new AuthManager($db);
    $activityManager = new ActivityManager($db);

    $userId = $auth->requireLogin();
    $currentUser = $auth->getCurrentUser($userId);
    $auth->requireRole($currentUser, 'Admin'); 

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['activity_name'] ?? '';
        $duration = $_POST['duration_text'] ?? '';
        
        $result = $activityManager->addActivity($userId, $name, $duration);
        
        if (is_int($result) && $result > 0) {
            $newActivityId = $result;
            header("Location: sub-task.php?activity_id={$newActivityId}");
            exit;
        } else {
            $message = $result;
        }
    }
    
    
    $view = new AdminAddExerciseView($currentUser, $message);
    $view->render();

} catch (Exception $e) {
    die("A critical error occurred: " . $e->getMessage());
}
?>