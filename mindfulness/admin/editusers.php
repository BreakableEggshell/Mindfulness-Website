<?php
session_start();
include '../src/config.php';

$message = '';
$default_role_id = 3;

if (!isset($_SESSION['user_id'])) {
    header("Location: ../src/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $mysqli->prepare(
    "SELECT u.full_name, u.username, u.role_id, r.role_name
     FROM users u
     LEFT JOIN user_roles r ON u.role_id = r.role_id
     WHERE u.user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$role = $current_user_data['role_name'] ?? 'User';

if ($role !== 'Admin') {
    header("Location: ../src/dashboard.php");
    exit;
}

if (isset($_POST['create_user'])) {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($full_name) || empty($username) || empty($password)) {
        $message = '<div class="alert alert-danger" role="alert">Full name, username, and password are required.</div>';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $create_stmt = $mysqli->prepare("INSERT INTO users (full_name, username, password_hash, email, role_id) VALUES (?, ?, ?, ?, ?)");
        $create_stmt->bind_param("ssssi", $full_name, $username, $hashed_password, $email, $default_role_id);

        if ($create_stmt->execute()) {
            $message = '<div class="alert alert-success" role="alert">New user <strong>' . htmlspecialchars($username) . '</strong> created successfully!</div>';
        } else {
            if ($mysqli->errno === 1062) {
                $message = '<div class="alert alert-danger" role="alert">Error: Username already exists.</div>';
            } else {
                $message = '<div class="alert alert-danger" role="alert">Database error during creation: ' . $mysqli->error . '</div>';
            }
        }
        $create_stmt->close();
    }
}

if (isset($_POST['update_role_id']) && isset($_POST['new_role_id'])) {
    $target_id = (int)$_POST['update_role_id'];
    $new_role_id = (int)$_POST['new_role_id'];

    $update_stmt = $mysqli->prepare("UPDATE users SET role_id = ? WHERE user_id = ?");
    $update_stmt->bind_param("ii", $new_role_id, $target_id);

    if ($update_stmt->execute()) {
        $message = '<div class="alert alert-success" role="alert">User ID ' . $target_id . ' role has been successfully updated.</div>';
    } else {
        $message = '<div class="alert alert-danger" role="alert">Database error during role update: ' . $mysqli->error . '</div>';
    }
    $update_stmt->close();
}

if (isset($_POST['update_details'])) {
    $target_id = (int)$_POST['user_id_to_update'];
    $full_name = trim($_POST['edit_full_name']);
    $username = trim($_POST['edit_username']);
    $email = trim($_POST['edit_email']);
    $new_password = $_POST['edit_password'];

    $sql = "UPDATE users SET full_name = ?, username = ?, email = ?";
    $types = "sss";
    $params = [$full_name, $username, $email];

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql .= ", password_hash = ?";
        $types .= "s";
        $params[] = $hashed_password;
    }

    $sql .= " WHERE user_id = ?";
    $types .= "i";
    $params[] = $target_id;

    $update_details_stmt = $mysqli->prepare($sql);
    $update_details_stmt->bind_param($types, ...$params);

    if ($update_details_stmt->execute()) {
        $message = '<div class="alert alert-success" role="alert">User ID ' . $target_id . ' details updated successfully.</div>';
    } else {
        if ($mysqli->errno === 1062) {
            $message = '<div class="alert alert-danger" role="alert">Error updating details: Username already exists.</div>';
        } else {
            $message = '<div class="alert alert-danger" role="alert">Database error during update: ' . $mysqli->error . '</div>';
        }
    }
    $update_details_stmt->close();
}

if (isset($_POST['delete_user_id'])) {
    $target_id = (int)$_POST['delete_user_id'];

    if ($target_id === $user_id) {
        $message = '<div class="alert alert-danger" role="alert">Error: You cannot delete your own admin account.</div>';
    } else {
        $mysqli->begin_transaction();
        try {
            $mysqli->query("DELETE FROM user_logs WHERE user_id = $target_id");
            $mysqli->query("DELETE FROM activity_progress WHERE user_id = $target_id");
            $mysqli->query("DELETE FROM activities WHERE user_id = $target_id");

            $delete_stmt = $mysqli->prepare("DELETE FROM users WHERE user_id = ?");
            $delete_stmt->bind_param("i", $target_id);
            $delete_stmt->execute();
            
            if ($delete_stmt->affected_rows > 0) {
                $mysqli->commit();
                $message = '<div class="alert alert-success" role="alert">User ID ' . $target_id . ' and all related data have been successfully deleted.</div>';
            } else {
                $mysqli->rollback();
                $message = '<div class="alert alert-warning" role="alert">User ID ' . $target_id . ' not found.</div>';
            }
            $delete_stmt->close();
        } catch (Exception $e) {
            $mysqli->rollback();
            $message = '<div class="alert alert-danger" role="alert">Transaction error during deletion: ' . $e->getMessage() . '</div>';
        }
    }
}

$all_users_result = $mysqli->query(
    "SELECT u.user_id, u.full_name, u.username, u.email, u.role_id, r.role_name
    FROM users u
    LEFT JOIN user_roles r ON u.role_id = r.role_id
    ORDER BY u.user_id ASC"
);
$all_users = $all_users_result->fetch_all(MYSQLI_ASSOC);

$roles_result = $mysqli->query("SELECT role_id, role_name FROM user_roles ORDER BY role_id");
$roles = $roles_result->fetch_all(MYSQLI_ASSOC);

$user_count = count($all_users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Edit Users - Mindfulness Wellness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
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
        .card {
            background-color: #FFF4EC;
            border: 1px solid #FFCCB0;
            border-radius: 10px;
        }
        h3, h5 {
            color: #D47456;
        }
        .btn-primary-custom {
            background-color: #D47456;
            border-color: #D47456;
            color: white;
        }
        .btn-primary-custom:hover {
            background-color: #B65E47;
            border-color: #B65E47;
        }
        .table thead {
            background-color: #FFD6BD;
            color: #8A3F27;
        }
        .btn-outline-danger {
            border-color: #E67A59;
            color: #E67A59;
        }
        .btn-outline-danger:hover {
            background-color: #E67A59;
            color: white;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #FFF0E6 !important;
        }
        .badge-admin { background-color: #E67A59; }
        .badge-cs { background-color: #4CAF50; }
        .badge-user { background-color: #607D8B; }
        .edit-btn { background-color: #FFCCB0; border-color: #FFCCB0; color: #8A3F27; }
        .edit-btn:hover { background-color: #FFB092; border-color: #FFB092; color: #8A3F27; }
</style>    
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light header">
    <div class="container-fluid">
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
    </div>
</nav>

<div class="container py-4">
    <div class="card p-4 shadow-lg">
        <h3 class="mb-4">User Management Console</h3>
        <p class="text-muted">Logged in as: <strong><?= htmlspecialchars($current_user_data['username']) ?></strong> (<?= htmlspecialchars($role) ?>)</p>

        <?= $message ?>

        <button class="btn btn-primary-custom mb-4" type="button" data-bs-toggle="collapse" data-bs-target="#createUserForm" aria-expanded="false" aria-controls="createUserForm">
            + Create New User (Default Role: User)
        </button>

        <div class="collapse mb-4" id="createUserForm">
            <div class="card card-body">
                <h5>Create Account</h5>
                <form method="POST">
                    <input type="hidden" name="create_user" value="1">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="fullName" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="fullName" name="full_name" required>
                        </div>
                        <div class="col-md-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary-custom">Create Account</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <h5 class="mb-3 mt-4">Manage User Roles & Accounts (<?= $user_count ?> Total)</h5>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Full Name</th>
                        <th scope="col">Username / Email</th>
                        <th scope="col">Current Role</th>
                        <th scope="col" class="text-center">Change Role</th>
                        <th scope="col" class="text-center">Details / Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $user_row): ?>
                    <tr>
                        <th scope="row"><?= htmlspecialchars($user_row['user_id']) ?></th>
                        <td><?= htmlspecialchars($user_row['full_name']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($user_row['username']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($user_row['email'] ?: 'No Email') ?></small>
                        </td>
                        <td>
                            <?php 
                                $badge_class = 'badge-user';
                                if ($user_row['role_name'] === 'Admin') $badge_class = 'badge-admin';
                                else if ($user_row['role_name'] === 'Customer Service') $badge_class = 'badge-cs';
                            ?>
                            <span class="badge rounded-pill <?= $badge_class ?>">
                                <?= htmlspecialchars($user_row['role_name']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <form method="POST" class="d-flex justify-content-center" onsubmit="return confirm('Change role to selected option for <?= htmlspecialchars($user_row['username']) ?>?');">
                                <input type="hidden" name="update_role_id" value="<?= htmlspecialchars($user_row['user_id']) ?>">
                                <select name="new_role_id" class="form-select form-select-sm me-2" style="width: 150px;" required>
                                    <?php foreach ($roles as $role_option): ?>
                                        <?php $selected = ($role_option['role_id'] == $user_row['role_id']) ? 'selected' : ''; ?>
                                        <option value="<?= htmlspecialchars($role_option['role_id']) ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($role_option['role_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary-custom">Update</button>
                            </form>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <button 
                                    type="button" 
                                    class="btn btn-sm edit-btn me-2" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editUserModal"
                                    data-id="<?= htmlspecialchars($user_row['user_id']) ?>"
                                    data-fullname="<?= htmlspecialchars($user_row['full_name']) ?>"
                                    data-username="<?= htmlspecialchars($user_row['username']) ?>"
                                    data-email="<?= htmlspecialchars($user_row['email']) ?>"
                                >
                                    Edit Details
                                </button>
                                
                                <?php if ($user_row['user_id'] !== $user_id): ?>
                                    <form method="POST" onsubmit="return confirm('WARNING: Are you sure you want to permanently DELETE user <?= htmlspecialchars($user_row['username']) ?>? This will delete all their associated activities and progress.');" class="d-inline">
                                        <input type="hidden" name="delete_user_id" value="<?= htmlspecialchars($user_row['user_id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled title="Cannot delete your own account">Self</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel" style="color: #D47456;">Edit User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_details" value="1">
                    <input type="hidden" name="user_id_to_update" id="userIdToUpdate">

                    <div class="mb-3">
                        <label for="editFullName" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="editFullName" name="edit_full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editUsername" class="form-label">Username</label>
                        <input type="text" class="form-control" id="editUsername" name="edit_username" required>
                    </div>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" name="edit_email">
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">New Password (Leave blank to keep current)</label>
                        <input type="password" class="form-control" id="editPassword" name="edit_password">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-custom">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var editUserModal = document.getElementById('editUserModal');
    editUserModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var userId = button.getAttribute('data-id');
        var fullName = button.getAttribute('data-fullname');
        var username = button.getAttribute('data-username');
        var email = button.getAttribute('data-email');

        var modalTitle = editUserModal.querySelector('.modal-title');
        var inputId = editUserModal.querySelector('#userIdToUpdate');
        var inputFullName = editUserModal.querySelector('#editFullName');
        var inputUsername = editUserModal.querySelector('#editUsername');
        var inputEmail = editUserModal.querySelector('#editEmail');

        modalTitle.textContent = 'Edit Details for: ' + username;
        inputId.value = userId;
        inputFullName.value = fullName;
        inputUsername.value = username;
        inputEmail.value = email;

        editUserModal.querySelector('#editPassword').value = '';
    });
});
</script>
</body>
</html>
<?php
$mysqli->close();
?>
