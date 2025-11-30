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

    // R: Get Activity Name/Duration
    public function getActivityDetails(int $activityId) {
        $stmt = $this->db->prepare("SELECT activity_name, duration_text FROM activities WHERE activity_id = ?");
        $stmt->bind_param("i", $activityId);
        $stmt->execute();
        $details = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $details;
    }

    // R: Get all steps for a given activity (Fixed Query)
    public function getExistingSteps(int $activityId) {
        $sql = "SELECT sub_task_id, sub_task_name FROM sub_tasks WHERE activity_id = ?";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) { return []; }
        $stmt->bind_param("i", $activityId);
        $stmt->execute();
        $steps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $steps;
    }
    
    // C: Insert a new step (Fixed Logic - No UPDATE on activities table)
    public function addActivityStep(int $activityId, string $subTaskName, string $description) {
        if (empty($subTaskName) || empty($description)) {
            return "Error: Sub-Task Name and Description are required.";
        }
        
        // Use activity_id in the INSERT query to create the foreign key link directly.
        $insertSql = "INSERT INTO sub_tasks (activity_id, sub_task_name, description) VALUES (?, ?, ?)";
        $insertStmt = $this->db->prepare($insertSql);

        if ($insertStmt === false) { return "Error preparing insert statement: " . $this->db->error; }

        $insertStmt->bind_param("iss", $activityId, $subTaskName, $description);

        if ($insertStmt->execute()) {
            return "Step '$subTaskName' added successfully!";
        } else {
            // This is where the FK error occurs if $activityId is invalid.
            return "Database Error: Cannot link step. Check if Activity ID {$activityId} exists."; 
        }
    }
}

// 3. AdminAddStepsView Class (Encapsulates presentation logic for Step 2)
class AdminAddStepsView {
    private $activityId;
    private $activityName;
    private $steps;
    private $message;

    public function __construct(int $activityId, string $activityName, array $steps, string $message) {
        $this->activityId = $activityId;
        $this->activityName = $activityName;
        $this->steps = $steps;
        $this->message = $message;
    }

    public function render() {
        $messageHtml = $this->message ? "<div class=\"alert alert-info\">" . htmlspecialchars($this->message) . "</div>" : '';
        $stepsListHtml = '';
        
        // Display previously added steps
        if (!empty($this->steps)) {
            $stepsListHtml .= '<h5 class="mt-4 mb-2 text-muted">Current Steps:</h5><ul>';
            foreach ($this->steps as $step) {
                $stepsListHtml .= '<li>' . htmlspecialchars($step['sub_task_name']) . '</li>';
            }
            $stepsListHtml .= '</ul>';
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Steps - {$this->activityName}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #FFE9D6; font-family: 'Segoe UI', sans-serif; }
        .header { background: white; padding: 15px 30px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .card { background-color: #FFF4EC; border: 1px solid #FFCCB0; }
        h3 { color: #D47456; }
    </style>
</head>
<body>
<div class="container py-3">
    <div class="card p-4 shadow-sm mx-auto" style="max-width: 600px;">
        <h3 class="mb-3">STEP 2: Add Sub-Tasks for '{$this->activityName}' (ID: {$this->activityId})</h3>
        
        {$messageHtml}
        
        {$stepsListHtml}

        <hr>
        
        <form method="POST">
            <input type="hidden" name="activity_id" value="{$this->activityId}">
            
            <div class="mb-3">
                <label for="sub_task_name" class="form-label">Sub-Task Name (Step Title)</label>
                <input type="text" class="form-control" id="sub_task_name" name="sub_task_name" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description (What the user does)</label>
                <textarea class="form-control" id="description" name="description" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Add this Step</button>
            <a href="editexercise.php?activity_id={$this->activityId}" class="btn btn-secondary ms-2">Finish & View Exercises</a>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
    }
}

// ==========================================================
// EXECUTION / CONTROLLER LOGIC (Step 2)
// ==========================================================
$message = '';
$activityId = (int)($_REQUEST['activity_id'] ?? 0);
$activityName = 'Activity'; // Placeholder until lookup is added

try {
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $auth = new AuthManager($db);
    $activityManager = new ActivityManager($db);

    $userId = $auth->requireLogin();
    $currentUser = $auth->getCurrentUser($userId);
    $auth->requireRole($currentUser, 'Admin'); 

    if ($activityId <= 0) {
         throw new Exception("Invalid Activity ID provided.");
    }
    
    // Fetch activity details to display the correct name
    $details = $activityManager->getActivityDetails($activityId);
    if (!$details) {
        throw new Exception("Activity ID {$activityId} not found in the database.");
    }
    $activityName = htmlspecialchars($details['activity_name']);
    
    // Process form submission to add a new step
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subTaskName = $_POST['sub_task_name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        $message = $activityManager->addActivityStep($activityId, $subTaskName, $description);
    }
    
    // Fetch currently added steps
    $existingSteps = $activityManager->getExistingSteps($activityId);

    // Render the view for Step 2
    $view = new AdminAddStepsView($activityId, $activityName, $existingSteps, $message);
    $view->render();

} catch (Exception $e) {
    die("A critical error occurred: " . $e->getMessage());
}
?>