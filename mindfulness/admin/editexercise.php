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
        $stmt->bind_param("i", $user_id); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
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

    public function getActivityDetails(int $activityId) {
        $stmt = $this->db->prepare("SELECT activity_name, duration_text FROM activities WHERE activity_id = ?");
        $stmt->bind_param("i", $activityId);
        $stmt->execute();
        $details = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $details;
    }

    public function getStepsForActivity(int $activityId) {
        $stmt = $this->db->prepare("SELECT sub_task_id, sub_task_name, description FROM sub_tasks WHERE activity_id = ? ORDER BY sub_task_id");
        $stmt->bind_param("i", $activityId);
        $stmt->execute();
        $steps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $steps;
    }
    
    public function addStep(int $activityId, string $name, string $description) {
        if (empty($name) || empty($description)) return "Error: Name and Description are required.";
        
        $sql = "INSERT INTO sub_tasks (activity_id, sub_task_name, description) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) return "Error preparing insert statement: " . $this->db->error;
        $stmt->bind_param("iss", $activityId, $name, $description);
        
        if ($stmt->execute()) { return "Step '$name' added successfully!"; }
        return "Database Error: " . $stmt->error;
    }

    public function updateStep(int $stepId, string $name, string $description) {
        if (empty($name) || empty($description)) return "Error: Name and Description are required.";
        
        $sql = "UPDATE sub_tasks SET sub_task_name = ?, description = ? WHERE sub_task_id = ?";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) return "Error preparing statement: " . $this->db->error;
        $stmt->bind_param("ssi", $name, $description, $stepId);
        
        if ($stmt->execute()) { return "$name updated successfully!"; }
        return "Database Error: " . $stmt->error;
    }

    public function deleteStep(int $stepId) {
        $sql = "DELETE FROM sub_tasks WHERE sub_task_id = ?";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) return "Error preparing statement: " . $this->db->error;
        $stmt->bind_param("i", $stepId);
        
        if ($stmt->execute()) { return "Step ID $stepId deleted."; }
        return "Database Error: " . $stmt->error;
    }
}

class EditStepsView {
    private $activityId;
    private $activityName;
    private $duration;
    private $steps;
    private $message;
    private $editingStep;

    public function __construct(int $id, array $details, array $steps, string $message, $editingStep = null) {
        $this->activityId = $id;
        $this->activityName = htmlspecialchars($details['activity_name'] ?? 'Unknown Activity');
        $this->duration = htmlspecialchars($details['duration_text'] ?? 'N/A');
        $this->steps = $steps;
        $this->message = $message;
        $this->editingStep = $editingStep;
    }
    
    private function renderStepsList() {
        $html = '';
        foreach ($this->steps as $step) {
            $stepId = $step['sub_task_id'];
            $stepName = htmlspecialchars($step['sub_task_name']);
            
            $isEditing = $this->editingStep && $this->editingStep['sub_task_id'] == $stepId;
            
            $html .= '
            <li class="list-group-item d-flex justify-content-between align-items-center">
                ' . $stepName . '
                <div>
                    <form method="GET" class="d-inline me-2">
                        <input type="hidden" name="activity_id" value="' . $this->activityId . '">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="step_id" value="' . $stepId . '">
                        <button type="submit" class="btn btn-sm ' . ($isEditing ? 'btn-warning' : 'btn-outline-primary') . '">Edit</button>
                    </form>
                    <form method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to delete this step?\');">
                        <input type="hidden" name="activity_id" value="' . $this->activityId . '">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="step_id" value="' . $stepId . '">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </div>
            </li>';
        }
        if (empty($this->steps)) {
            $html = '<li class="list-group-item text-muted">No steps defined yet.</li>';
        }
        return '<ul class="list-group mb-4">' . $html . '</ul>';
    }

    private function renderAddEditForm() {
        $isEditing = $this->editingStep !== null;
        $stepId = $isEditing ? $this->editingStep['sub_task_id'] : '';
        $stepName = $isEditing ? htmlspecialchars($this->editingStep['sub_task_name']) : '';
        $description = $isEditing ? htmlspecialchars($this->editingStep['description']) : '';
        $action = $isEditing ? 'update' : 'add';
        $buttonText = $isEditing ? 'Save Changes' : 'Add New Sub-Task';

        return '
        <div class="card p-4 bg-light mb-4">
            <form method="POST">
                <input type="hidden" name="activity_id" value="' . $this->activityId . '">
                <input type="hidden" name="action" value="' . $action . '">
                ' . ($isEditing ? '<input type="hidden" name="step_id" value="' . $stepId . '">' : '') . '

                <div class="mb-3">
                    <label for="step_name" class="form-label">Sub-Task Name</label>
                    <input type="text" class="form-control" id="step_name" name="sub_task_name" value="' . $stepName . '" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" required>' . $description . '</textarea>
                </div>

                <button type="submit" class="btn ' . ($isEditing ? 'btn-warning' : 'btn-success') . '">' . $buttonText . '</button>
                ' . ($isEditing ? '<a href="editexercise.php?activity_id=' . $this->activityId . '" class="btn btn-secondary ms-2">Cancel</a>' : '') . '
            </form>
        </div>';
    }

    public function render() {
        $stepsListHtml = $this->renderStepsList();
        $addEditFormHtml = $this->renderAddEditForm();
        $messageHtml = $this->message ? "<div class=\"alert alert-info\">" . htmlspecialchars($this->message) . "</div>" : '';

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Steps - {$this->activityName}</title>
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
    <div class="card p-4 shadow-sm mx-auto" style="max-width: 800px;">
        <h3 class="mb-2">Edit Activity: {$this->activityName}</h3>
        <p class="text-muted">Duration: {$this->duration}</p>

        <a href="listofexercise.php" class="btn btn-sm btn-outline-secondary mb-4">← Back to Exercise List</a>

        <h4 class="mb-3">Sub-Tasks</h4>

        {$messageHtml}
        {$stepsListHtml}
        {$addEditFormHtml}

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
    }
}

$message = '';
$editingStep = null;
$activityId = (int)($_REQUEST['activity_id'] ?? 0);

try {
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $auth = new AuthManager($db);
    $activityManager = new ActivityManager($db);

    $userId = $auth->requireLogin();
    $currentUser = $auth->getCurrentUser($userId);
    $auth->requireRole($currentUser, 'Admin'); 

    if ($activityId <= 0) {
        header("Location: dashboard.php");
        exit;
    }
    
    $action = $_REQUEST['action'] ?? '';
    $stepId = (int)($_REQUEST['step_id'] ?? 0);

    // --- 1. HANDLE CRUD ACTIONS (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['sub_task_name'] ?? '';
        $description = $_POST['description'] ?? '';

        if ($action === 'add') {
            $message = $activityManager->addStep($activityId, $name, $description);
        } elseif ($action === 'update' && $stepId > 0) {
            $message = $activityManager->updateStep($stepId, $name, $description);
        } elseif ($action === 'delete' && $stepId > 0) {
            $message = $activityManager->deleteStep($stepId);
        }
        if ($action !== '') {
            header("Location: editexercise.php?activity_id={$activityId}&message=" . urlencode($message));
            exit;
        }
    }
    
    // --- 2. HANDLE EDIT REQUEST (GET) ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'edit' && $stepId > 0) {
        $stmt = $db->getConnection()->prepare("SELECT sub_task_id, sub_task_name, description FROM sub_tasks WHERE sub_task_id = ?");
        $stmt->bind_param("i", $stepId);
        $stmt->execute();
        $editingStep = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    
    // --- 3. FETCH DATA ---
    $activityDetails = $activityManager->getActivityDetails($activityId);
    if (!$activityDetails) { throw new Exception("Activity not found."); }
    
    $existingSteps = $activityManager->getStepsForActivity($activityId);
    
    $message = $_GET['message'] ?? $message;

    // --- 4. RENDER VIEW ---
    $view = new EditStepsView($activityId, $activityDetails, $existingSteps, $message, $editingStep);
    $view->render();

} catch (Exception $e) {
    die("A critical error occurred: " . $e->getMessage());
}
?>