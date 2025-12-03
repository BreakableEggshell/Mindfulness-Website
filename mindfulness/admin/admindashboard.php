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
            die("Database connection failed: " . $this->mysqli->connect_error);
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
    }

    public function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $user_id = $_SESSION['user_id'];
        
        $sql = "SELECT u.full_name, u.username, r.role_name
                FROM users u
                LEFT JOIN user_roles r ON u.role_id = r.role_id
                WHERE u.user_id = ?";
        
        $stmt = $this->db->prepare($sql);
        
        if ($stmt === false) {
             return null;
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $user;
    }

    public function requireRole($requiredRole = 'Admin', $redirectPath = '../src/dashboard.php') {
        $user = $this->getCurrentUser();
        
        $currentRole = $user['role_name'] ?? 'User';

        if ($currentRole !== $requiredRole) {
            header("Location: $redirectPath");
            exit;
        }
        return $user;
    }
}

// --- AdminView Class (Modified for data fetching and rendering) ---
class AdminView {
    private $user;
    private $db; 

    // Constructor now requires the database connection object
    public function __construct($user, $db) { 
        $this->user = $user;
        $this->db = $db;
    }

    // public function render() {
    //     $fullName = htmlspecialchars($this->user['full_name'] ?: $this->user['username']);
    // }
    /**
     * Fetches the total number of users from the users table.
     */
    private function getTotalUsers() {
        $sql = "SELECT COUNT(*) AS total FROM users";
        $result = $this->db->query($sql);
        return $result ? $result->fetch_assoc()['total'] : 0;
    }

    /**
     * Fetches the total number of listed exercises (using the correct 'activities' table).
     */
    private function getTotalExercises() {
        $sql = "SELECT COUNT(*) AS total FROM activities";
        $result = $this->db->query($sql);
        
        if ($result === false) {
             error_log("SQL Error: Could not query 'activities' table. " . $this->db->error);
             return 0; 
        }
        
        return $result->fetch_assoc()['total'] ?? 0;
    }

    /**
     * Fetches the 5 most recently registered users to act as "Recent Activity".
     */
    private function getRecentActivity() {
        $sql = "SELECT u.username, u.full_name, r.role_name
                FROM users u
                LEFT JOIN user_roles r ON u.role_id = r.role_id
                ORDER BY u.user_id DESC 
                LIMIT 5";
        
        $result = $this->db->query($sql);
        
        $activity = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $activity[] = $row;
            }
        }
        return $activity;
    }

    /**
     * Fetches placeholder data for user exercise progress graph.
     */
    private function getProgressGraphData() {
        // Placeholder data for Chart.js
        return json_encode([
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'data' => [12, 19, 3, 5, 2, 3, 15] 
        ]);
    }

    public function render() {
        $fullName = htmlspecialchars($this->user['full_name'] ?: $this->user['username']);        
        // Fetch data
        $totalUsers = $this->getTotalUsers();
        $totalExercises = $this->getTotalExercises();
        $graphDataJson = $this->getProgressGraphData();
        $recentActivity = $this->getRecentActivity(); 

        // Generate HTML rows for the Recent User Activity table
        $activityRows = '';
        foreach ($recentActivity as $user) {
            $username = htmlspecialchars($user['username']);
            $fullName = htmlspecialchars($user['full_name'] ?? 'N/A');
            $role = htmlspecialchars($user['role_name'] ?? 'User');
            
            // Icon for the table row
            $icon = '<i class="fa-solid fa-user me-2 text-muted"></i>'; 
            
            $activityRows .= "
                <tr>
                    <td>{$icon}{$username}</td>
                    <td>{$fullName}</td>
                    <td>{$role}</td>
                </tr>";
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Mindfulness Wellness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <style>
        body {
            background-color: #FFE9D6;
            font-family: 'Segoe UI', sans-serif;
        }
        .header {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .navbar-nav .nav-link {
            font-weight: 600;
            color: #333 !important;
            margin-left: 15px;
        }
        /* Custom dashboard styles */
        .card {
            background-color: #FFF4EC;
            border: 1px solid #FFCCB0;
        }
        .card-stats {
            background-color: #FFFAEF;
        }
        h3, h5 {
            color: #D47456;
        }
        .text-stats {
            color: #8A3F27;
        }
        .table thead {
            background-color: #FFD6BD;
            color: #8A3F27;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #FFF0E6 !important;
        }
        .text-muted {
            color: #B67356 !important;
        }
        .main-content {
             max-width: 900px; /* Controls the 50% screen width look */
             margin: 0 auto;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light header">
    <a class="navbar-brand fw-bold" href="#">Mindfulness Admin</a>
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

<div class="container py-4 main-content">
    <div class="card p-4 shadow-sm mb-4">
        <h3 class="mb-3">Welcome Admin John</h3>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card card-stats p-3 text-center shadow-sm">
                <h5 class="card-title text-muted mb-0">Total App Users</h5>
                <p class="fs-1 fw-bold text-stats mb-0"><i class="fa-solid fa-user me-2"> </i>{$totalUsers}</p>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card card-stats p-3 text-center shadow-sm">
                <h5 class="card-title text-muted mb-0">Total Listed Exercises</h5>
                <p class="fs-1 fw-bold text-stats mb-0"><i class="fa-solid fa-list"></i> {$totalExercises}</p>
            </div>
        </div>
    </div>


    <div class="card p-4 shadow-sm">
        <h5 class="mb-3">🆕 Recently Registered Users</h5>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th scope="col">Username</th>
                        <th scope="col">Full Name</th>
                        <th scope="col">Role</th>
                    </tr>
                </thead>
                <tbody>
                    {$activityRows}
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
HTML;
    }
}

// --- Application Flow ---
try {
    // 1. Initialize Database
    $dbConnection = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $mysqli = $dbConnection->getConnection();
    
    // 2. Initialize Authentication Manager
    $auth = new AuthManager($dbConnection);
    
    // 3. Enforce Login and Admin Role
    $auth->requireLogin();
    // Note: The AdminView constructor must now receive the $mysqli connection.
    $admin_user = $auth->requireRole('Admin'); 

    // 4. Render Admin View, passing the database connection
    $view = new AdminView($admin_user, $mysqli); 
    $view->render();

} catch (Exception $e) {
    // Catch any critical errors
    die("A critical error occurred: " . $e->getMessage());
}
?>
    
