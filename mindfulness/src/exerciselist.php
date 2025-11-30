<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $mysqli->prepare("
    SELECT u.full_name, u.username, r.role_name
    FROM users u
    LEFT JOIN user_roles r ON u.role_id = r.role_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$role = $user['role_name'] ?? "User";
$stmt->close();

// Placeholder for display message
$message = $_GET['message'] ?? ''; 

// --- FIXED SQL QUERY FOR USER'S EXERCISES ---
// This query prevents duplication by only showing Admin templates 
// if the user has NOT created a copy yet.
$sql = "
    SELECT 
        a.activity_id,
        a.activity_name,
        a.duration_text,
        a.template_id, 
        u_creator_role.role_name AS creator_role
    FROM activities a
    LEFT JOIN users u_creator ON a.user_id = u_creator.user_id
    LEFT JOIN user_roles u_creator_role ON u_creator.role_id = u_creator_role.role_id 
    WHERE 
        -- CONDITION A: Show activities created by the user (non-templates or user-copied templates)
        a.user_id = {$user_id}
    OR
        -- CONDITION B: Show Admin templates ONLY IF the user has NOT created a copy yet
        (
            u_creator_role.role_name = 'Admin'
            AND NOT EXISTS (
                SELECT 1 
                FROM activities user_a 
                WHERE user_a.template_id = a.activity_id AND user_a.user_id = {$user_id}
            )
        )
";

$result = $mysqli->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exercises - Mindfulness Wellness App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="CSS/exerciselist.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light header">
    <a class="navbar-brand fw-bold" href="dashboard.php">Mindfulness</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" href="./dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link active" aria-current="page" href="./exerciselist.php">Exercises</a></li>
            <li class="nav-item"><a class="nav-link" href="./progresstrack.php">Progress Tracker</a></li>
            <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container py-3">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-3">Welcome back, <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?> </h3>
        <h5 class="mb-3">Available Exercises</h5>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($role === 'Admin'): ?>
        <div class="d-flex justify-content-end mb-3">
            <a href="addexercise.php" class="btn btn-success">
                + Add New Exercise (Admin)
            </a>
        </div>
        <?php endif; ?>
        
        <hr>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Exercise Name</th>
                    <th>Duration</th>
                    <th>Done</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $activity_to_check_id = $row['activity_id'];
                        $is_template = ($row['creator_role'] === 'Admin');
                        $is_done = 0;
                        
                        // If it's a template, we need to know the User's copy ID to check progress later.
                        // This logic is necessary because the main query now only shows the TEMPLATE 
                        // if the copy doesn't exist, OR it shows the USER COPY. We need to be able 
                        // to check the progress of the item currently being displayed.

                        $activity_to_check_progress_id = $row['activity_id'];
                        if ($is_template) {
                            // If it's an Admin template currently visible, we check if the user 
                            // has a copy just for progress checking (though progress is likely 0).
                            // More importantly, we use the $row['activity_id'] as the TEMPLATE_ID 
                            // for the update_progress.php script.
                        } else {
                            // If it's a user's activity (either original or copy), check progress directly.
                            $activity_to_check_progress_id = $row['activity_id'];
                        }


                        // Check progress for the determined activity ID
                        // This logic should check progress against the user's specific copy if it exists,
                        // or against the current activity ID if it's the user's own creation.
                        if (!$is_template) {
                            $stmt_progress = $mysqli->prepare("
                                SELECT is_done
                                FROM activity_progress
                                WHERE user_id = ? AND activity_id = ?
                                ORDER BY progress_date DESC LIMIT 1
                            ");
                            $stmt_progress->bind_param("ii", $user_id, $activity_to_check_progress_id);
                            $stmt_progress->execute();
                            $res = $stmt_progress->get_result()->fetch_assoc();
                            $stmt_progress->close();

                            if ($res && $res['is_done'] == 1) {
                                $is_done = 1;
                            }
                        }

                        $activityName = htmlspecialchars($row['activity_name']);
                        $detailLink = 'viewexercises.php?activity_id=' . $activity_to_check_progress_id;

                        $linkedActivityName = '<a href="' . $detailLink . '">' . $activityName . '</a>';
                    ?>
                        <tr>
                            <td><?= $linkedActivityName ?></td>
                            <td><?= htmlspecialchars($row['duration_text']) ?></td>
                            <td>
                                <input type="checkbox" 
                                    class="mark-done" 
                                    data-activity="<?= $row['activity_id'] ?>" 
                                    data-is-template="<?= $is_template ? 1 : 0 ?>"
                                    <?= $is_done ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted">No exercises available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>


    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.mark-done').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            // Note: templateId here refers to the activity_id shown in the table (which is the Admin ID if it's a template)
            const activityId = this.dataset.activity; 
            const isTemplate = this.dataset.isTemplate; 
            const isDone = this.checked ? 1 : 0; 

            fetch('update_progress.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `activity_id=${activityId}&is_done=${isDone}&is_template=${isTemplate}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Progress updated:', data);
                if (data.status === 'success') {
                    // If a template was marked done, we created a new user activity. 
                    // Refresh the list to show the new user copy instead of the template.
                    if (isTemplate === '1' && isDone === 1) {
                        window.location.href = 'exerciselist.php?message=Exercise%20completed!%20Refreshing%20list.';
                    } else if (isDone === 1) {
                        // Redirect to the progress tracker upon successful completion
                        window.location.href = 'progresstrack.php?status=completed';
                    }
                } else {
                    // Revert checkbox if update failed
                    this.checked = !this.checked;
                    alert(`Update failed: ${data.message}`);
                }
            })
            .catch(err => {
                // Revert checkbox if update failed due to network error
                this.checked = !this.checked;
                alert('Error updating progress. See console for details.');
                console.error(err);
            });
        });
    });
});
</script>
</body>
</html>