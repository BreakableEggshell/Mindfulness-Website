<?php
session_start();
include 'config.php'; // Include your database connection

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// 1. Get POST data
$template_id = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);
$is_done = filter_input(INPUT_POST, 'is_done', FILTER_VALIDATE_INT); // 1 for done, 0 for undone
$is_template_flag = filter_input(INPUT_POST, 'is_template', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];
$today_date = date('Y-m-d');

if (!$template_id || ($is_done === null)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required data.']);
    exit;
}

$mysqli->begin_transaction();
$current_activity_id = $template_id;
$is_new_user_activity = false;

try {
    // --- STEP 2: Handle Admin Templates (if necessary) ---
    if ($is_template_flag == 1 && $is_done == 1) {
        // Check if a user-specific copy exists
        $stmt_check = $mysqli->prepare("
            SELECT activity_id FROM activities 
            WHERE template_id = ? AND user_id = ?
        ");
        $stmt_check->bind_param("ii", $template_id, $user_id);
        $stmt_check->execute();
        $user_activity = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($user_activity) {
            $current_activity_id = $user_activity['activity_id'];
        } else {
            // Create a user copy of the template
            $stmt_get_template = $mysqli->prepare("
                SELECT activity_name, duration_text FROM activities WHERE activity_id = ?
            ");
            $stmt_get_template->bind_param("i", $template_id);
            $stmt_get_template->execute();
            $template_data = $stmt_get_template->get_result()->fetch_assoc();
            $stmt_get_template->close();

            if ($template_data) {
                // 1. Insert the new activity row for the user
                $stmt_insert = $mysqli->prepare("
                    INSERT INTO activities (user_id, activity_name, duration_text, template_id)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt_insert->bind_param(
                    "issi", 
                    $user_id, 
                    $template_data['activity_name'], 
                    $template_data['duration_text'], 
                    $template_id
                );
                $stmt_insert->execute();
                $current_activity_id = $mysqli->insert_id;
                $stmt_insert->close();
                $is_new_user_activity = true; 
                
                // 2. *** CRITICAL FIX: CLONE SUB-TASKS, INCLUDING sub_task_name ***
                $stmt_copy_subtasks = $mysqli->prepare("
                    INSERT INTO sub_tasks (activity_id, sub_task_name, description)
                    SELECT ?, sub_task_name, description
                    FROM sub_tasks
                    WHERE activity_id = ?
                ");
                $stmt_copy_subtasks->bind_param("ii", $current_activity_id, $template_id);
                $stmt_copy_subtasks->execute();
                $stmt_copy_subtasks->close();
                // *** END CRITICAL FIX ***
                
            } else {
                throw new Exception("Template not found.");
            }
        }
    }
    
    // --- STEP 3: Update/Insert Progress (Assumes sub_task_id is dropped) ---
    
    // Check if a progress entry exists for today for this activity
    $stmt_check_progress = $mysqli->prepare("
        SELECT progress_id FROM activity_progress
        WHERE user_id = ? AND activity_id = ? AND DATE(progress_date) = ?
    ");
    $stmt_check_progress->bind_param("iis", $user_id, $current_activity_id, $today_date);
    $stmt_check_progress->execute();
    $progress_row = $stmt_check_progress->get_result()->fetch_assoc();
    $stmt_check_progress->close();

    if ($progress_row) {
        // Update existing progress for today
        $stmt_update = $mysqli->prepare("
            UPDATE activity_progress SET is_done = ?, progress_date = NOW()
            WHERE progress_id = ?
        ");
        $stmt_update->bind_param("ii", $is_done, $progress_row['progress_id']);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        // Insert new progress entry
        if ($is_done == 1) { // Only insert if marking as done today
            $stmt_insert_progress = $mysqli->prepare("
                INSERT INTO activity_progress (user_id, activity_id, is_done, progress_date)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt_insert_progress->bind_param("iii", $user_id, $current_activity_id, $is_done);
            $stmt_insert_progress->execute();
            $stmt_insert_progress->close();
        }
    }

    $mysqli->commit();
    echo json_encode([
        'status' => 'success', 
        'message' => 'Progress updated successfully.', 
        'activity_id' => $current_activity_id,
        'new_activity_created' => $is_new_user_activity
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Progress update error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
?>