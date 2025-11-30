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
        if ($this->mysqli->connect_error) { throw new Exception("Database connection failed: " . $this->mysqli->connect_error); }
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
        $stmt = $this->db->prepare("SELECT u.full_name, u.username, r.role_name FROM users u LEFT JOIN user_roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
        $stmt->bind_param("i", $user_id); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $user;
    }
}

class ActivityManager {
    private $db;
    public function __construct(Database $db) { $this->db = $db->getConnection(); }

    // --- FIXED: Only fetch master exercises (where template_id is NULL) ---
    public function getAllActivities() {
        $sql = "SELECT a.activity_id, a.activity_name, a.duration_text
                FROM activities a
                WHERE a.template_id IS NULL OR a.template_id = 0
                ORDER BY a.activity_name";
        $result = $this->db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    // NEW METHOD: Deletes the activity and all associated steps/progress
    public function deleteActivityAndSteps(int $activityId) {
        $db = $this->db;
        $db->autocommit(false);
        $activityName = '';

        try {
            // Get activity name before deletion for the message
            $stmtName = $db->prepare("SELECT activity_name FROM activities WHERE activity_id = ?");
            if (!$stmtName) throw new Exception("Error preparing name statement: " . $db->error);
            $stmtName->bind_param("i", $activityId);
            $stmtName->execute();
            $result = $stmtName->get_result()->fetch_assoc();
            $stmtName->close();
            if ($result) {
                $activityName = $result['activity_name'];
            } else {
                throw new Exception("Activity not found.");
            }

            // --- IMPORTANT: Delete all USER COPIES linked to this template ID ---
            // If the admin deletes the master copy, all user copies must also be deleted
            $db->query("DELETE FROM activities WHERE template_id = {$activityId}");
            
            // 1. Delete associated activity progress (including progress for user copies just deleted)
            $db->query("DELETE FROM activity_progress WHERE activity_id = {$activityId} OR activity_id IN (SELECT activity_id FROM activities WHERE template_id = {$activityId})");
            
            // 2. Delete associated sub-tasks (steps) (including steps for user copies just deleted)
            $db->query("DELETE FROM sub_tasks WHERE activity_id = {$activityId} OR activity_id IN (SELECT activity_id FROM activities WHERE template_id = {$activityId})");

            // 3. Delete the main activity record (must be last)
            $stmtActivity = $db->prepare("DELETE FROM activities WHERE activity_id = ?");
            if (!$stmtActivity) throw new Exception("Error preparing activity statement: " . $db->error);
            $stmtActivity->bind_param("i", $activityId);
            $stmtActivity->execute();
            $deletedCount = $db->affected_rows;
            $stmtActivity->close();

            if ($deletedCount === 0) {
                // If it wasn't the master copy, this is okay, but we already covered that case.
                // Keep the commit logic simple here.
            }

            $db->commit();
            return "Activity '{$activityName}' (ID: {$activityId}) and all associated data deleted successfully.";

        } catch (Exception $e) {
            $db->rollback();
            return "Deletion failed: " . $e->getMessage();
        } finally {
            $db->autocommit(true);
        }
    }
}

class ExerciseListView {
    private $user;
    private $activities;
    private $message;

    public function __construct($user, $activities, $message = '') {
        $this->user = $user;
        $this->activities = $activities;
        $this->message = $message;
    }

    public function render() {
        $fullName = htmlspecialchars($this->user['full_name'] ?: $this->user['username']);
        $role = $this->user['role_name'] ?? 'User';
        $isAdmin = ($role === 'Admin');

        $rowsHtml = '';
        if (empty($this->activities)) {
            $rowsHtml = '<tr><td colspan="3" class="text-center text-muted">No exercises available</td></tr>';
        } else {
            foreach ($this->activities as $row) {
                $activityId = $row['activity_id'];
                $activityName = htmlspecialchars($row['activity_name']);
                
                $activityLink = $activityName;
                $deleteCell = '';
                
                if ($isAdmin) {
                    // Link to the edit page
                    $activityLink = '<a href="editexercise.php?activity_id=' . $activityId . '">' . $activityName . '</a>';
                    
                    // DELETE BUTTON FORM
                    $deleteCell = '<td>
                        <form method="POST" onsubmit="return confirm(\'WARNING: This will delete the entire \\\''. $activityName .'\\\' exercise and ALL user copies and progress. Are you sure?\');">
                            <input type="hidden" name="action" value="delete_activity">
                            <input type="hidden" name="activity_id" value="' . $activityId . '">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>';
                }
                
                $rowsHtml .= '
                    <tr>
                        <td>' . $activityLink . '</td>
                        <td>' . htmlspecialchars($row['duration_text']) . '</td>
                        ' . $deleteCell . '
                    </tr>';
            }
        }
        
        $adminButton = '';
        if ($isAdmin) {
            $adminButton = '
                <div class="d-flex justify-content-end mb-3">
                    <a href="addexercise.php" class="btn btn-success">
                        + Add New Exercise
                    </a>
                </div>';
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exercises - Mindfulness Wellness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #FFE9D6; font-family: 'Segoe UI', sans-serif; }
        .header { background: white; padding: 15px 30px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .navbar-nav .nav-link { font-weight: 600; color: #333 !important; margin-left: 15px; }
        .card { background-color: #FFF4EC; border: 1px solid #FFCCB0; }
        h3, h5 { color: #D47456; }
        .table thead { background-color: #FFD6BD; color: #8A3F27; }
        .btn-outline-danger { border-color: #E67A59; color: #E67A59; }
        .btn-outline-danger:hover { background-color: #E67A59; color: white; }
        .table-striped > tbody > tr:nth-of-type(odd) { background-color: #FFF0E6 !important; }
        .text-muted { color: #B67356 !important; }
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
            <li class="nav-item"><a class="nav-link" href="addexercise.php">Add New Exercise</a></li>
            <li class="nav-item"><a class="nav-link" href="listofexercise.php">List New Exercise</a></li>
            <li class="nav-item"><a class="nav-link" href="setting.php">Settings</a></li>
            <li class="nav-item"><a class="nav-link" href="../src/logout.php">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container py-3">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-3">Welcome Admin, {$fullName} </h3>
        <h5 class="mb-3">List of Exercises</h5>
        
        {$adminButton}
        
        <hr>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Exercise Name (Click to Edit)</th>
                    <th>Duration</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                {$rowsHtml}
            </tbody>
        </table>
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
    $user = $auth->getCurrentUser($userId);
    $role = $user['role_name'] ?? 'User';

    // --- HANDLE DELETE ACTION ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'Admin' && ($_POST['action'] ?? '') === 'delete_activity') {
        $activityIdToDelete = (int)($_POST['activity_id'] ?? 0);
        
        if ($activityIdToDelete > 0) {
            $message = $activityManager->deleteActivityAndSteps($activityIdToDelete);
        } else {
            $message = "Error: Invalid activity ID for deletion.";
        }
        // Redirect to clear POST data and prevent re-submission warning
        header("Location: listofexercise.php?message=" . urlencode($message));
        exit;
    }
    
    // --- HANDLE GET REQUEST ---
    $message = $_GET['message'] ?? $message; 

    $activities = $activityManager->getAllActivities();
    $view = new ExerciseListView($user, $activities, $message);
    $view->render();

} catch (Exception $e) {
    die("A critical error occurred: " . $e->getMessage());
}
?>