<?php
session_start();
include('config.php');

/* ============================================================
   PARENT CLASS: UserBase (Encapsulation)
   ============================================================ */
class UserBase {
    protected $mysqli;
    protected string $full_name = "";
    protected string $email = "";
    protected string $username = "";
    protected string $password = "";

    public function __construct(mysqli $db) {
        $this->mysqli = $db;
    }

    public function setCredentials(string $full_name, string $email, string $username, string $password) {
        $this->full_name = trim($full_name);
        $this->email = trim($email);
        $this->username = trim($username);
        $this->password = trim($password);
    }

    protected function validateInput(): bool {
        return !empty($this->full_name) && !empty($this->email) && !empty($this->username) && !empty($this->password);
    }
}

/* ============================================================
   CHILD CLASS: RegisterUser (Inheritance)
   ============================================================ */
class RegisterUser extends UserBase {

    public function register(): string|null {
        if (!$this->validateInput()) {
            return "Please fill out all fields.";
        }

        $check = $this->mysqli->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        if (!$check) return "Database error: " . $this->mysqli->error;

        $check->bind_param("ss", $this->username, $this->email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->close();
            return "Username or Email is already taken.";
        }
        $check->close();

        $hashed = password_hash($this->password, PASSWORD_DEFAULT);

        $stmt = $this->mysqli->prepare("
            INSERT INTO users (full_name, email, username, password_hash)
            VALUES (?, ?, ?, ?)
        ");
        if (!$stmt) return "Database error: " . $this->mysqli->error;

        $stmt->bind_param("ssss", $this->full_name, $this->email, $this->username, $hashed);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: login.php?registered=1");
            exit;
        } else {
            $stmt->close();
            return "Registration failed. Try again.";
        }
    }
}

/* ============================================================
   EXECUTION
   ============================================================ */
$error = "";
$register = new RegisterUser($mysqli);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $register->setCredentials($_POST['full_name'], $_POST['email'], $_POST['username'], $_POST['password']);
    $error = $register->register();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Mindfulness App</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="CSS/login.css">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="text-center mb-4">Create Account</h3>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required autocomplete="off">
                        </div>

                        <button type="submit" class="btn-peach">Register</button>
                    </form>

                    <div class="text-center mt-3">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
