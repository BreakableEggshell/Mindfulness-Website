<?php
session_start();
include('config.php');

/* ============================================================
   PARENT CLASS: UserBase (Encapsulation)
   ============================================================ */
class UserBase {
    protected $mysqli;
    protected string $username = "";
    protected string $password = "";

    public function __construct(mysqli $db) {
        $this->mysqli = $db;
    }

    // Encapsulated setter for credentials
    public function setCredentials(string $username, string $password) {
        $this->username = trim($username);
        $this->password = trim($password);
    }

    // Input validation
    protected function validateInput(): bool {
        return !(empty($this->username) || empty($this->password));
    }
}

/* ============================================================
   CHILD CLASS: LoginUser (Inheritance)
   ============================================================ */
class LoginUser extends UserBase {

    // Authenticate user and set sessions
    public function authenticate(): string|null {

        if (!$this->validateInput()) {
            return "Please enter both username and password.";
        }

        $stmt = $this->mysqli->prepare(
            "SELECT u.user_id, u.username, u.password_hash, u.role_id, r.role_name
             FROM users u
             LEFT JOIN user_roles r ON u.role_id = r.role_id
             WHERE u.username = ? LIMIT 1"
        );

        if (!$stmt) return "Database error: " . $this->mysqli->error;

        $stmt->bind_param("s", $this->username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            return "User not found.";
        }

        $user = $result->fetch_assoc();

        if (!password_verify($this->password, $user['password_hash'])) {
            return "Incorrect password.";
        }

        // Set sessions
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = isset($user['role_id']) ? (int)$user['role_id'] : 0;
        $_SESSION['role_name'] = isset($user['role_name']) ? $user['role_name'] : '';

        error_log(sprintf("[login] user_id=%d username=%s role_id=%d role_name=%s", 
            $user['user_id'], $user['username'], $_SESSION['role_id'], $_SESSION['role_name']));

        // Redirect based on role
        if (strcasecmp($_SESSION['role_name'], 'Admin') === 0) {
            header("Location: ../admin/admindashboard.php");
            exit;
        }

        header("Location: dashboard.php");
        exit;
    }
}

/* ============================================================
   EXECUTION
   ============================================================ */
$error = "";
$login = new LoginUser($mysqli);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login->setCredentials($_POST['username'], $_POST['password']);
    $error = $login->authenticate();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Mindfulness App</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="CSS/login.css">
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

                        <button type="submit" class="btn-peach w-100">Login</button>
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
