<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) exit('Not authorized');

$user_id    = $_SESSION['user_id'];
$activity_id = intval($_POST['activity_id'] ?? 0);   // This is admin template activity_id
$is_done     = intval($_POST['is_done'] ?? 0);

if (!$activity_id) exit("Missing activity_id");

// -------------------------------------------------------------
// UNCHECKED → DELETE USER'S COPIED ACTIVITY
// -------------------------------------------------------------
if ($is_done == 0) {

    // Find user's copy based on template_id
    $stmt = $mysqli->prepare("
        SELECT activity_id, sub_task_id 
        FROM activities 
        WHERE template_id = ? AND created_by = ?
    ");
    $stmt->bind_param("ii", $activity_id, $user_id);
    $stmt->execute();
    $copy = $stmt->get_result()->fetch_assoc();

    if ($copy) {
        $copy_activity_id = $copy['activity_id'];
        $copy_subtask_id  = $copy['sub_task_id'];

        // Delete progress
        $stmt = $mysqli->prepare("DELETE FROM activity_progress WHERE activity_id = ?");
        $stmt->bind_param("i", $copy_activity_id);
        $stmt->execute();

        // Delete activity
        $stmt = $mysqli->prepare("DELETE FROM activities WHERE activity_id = ?");
        $stmt->bind_param("i", $copy_activity_id);
        $stmt->execute();

        // Delete copied subtask
        $stmt = $mysqli->prepare("DELETE FROM sub_tasks WHERE sub_task_id = ?");
        $stmt->bind_param("i", $copy_subtask_id);
        $stmt->execute();
    }

    exit("unchecked-deleted");
}



// -------------------------------------------------------------
// CHECKED → CREATE USER COPY (unless already created)
// -------------------------------------------------------------

// ----------- DUPLICATION CHECK -----------
$stmt = $mysqli->prepare("
    SELECT activity_id, sub_task_id 
    FROM activities 
    WHERE template_id = ? AND created_by = ?
");
$stmt->bind_param("ii", $activity_id, $user_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    $existing_activity_id = $existing['activity_id'];
    $existing_subtask_id  = $existing['sub_task_id'];

    // Ensure progress is marked done
    $stmt = $mysqli->prepare("
        INSERT INTO activity_progress (user_id, sub_task_id, activity_id, is_done, progress_date)
        VALUES (?, ?, ?, 1, CURDATE())
        ON DUPLICATE KEY UPDATE is_done = 1
    ");
    $stmt->bind_param("iii", $user_id, $existing_subtask_id, $existing_activity_id);
    $stmt->execute();

    exit("duplicate-skip");
}


// -------------------------------------------------------------
// CREATE NEW USER COPY
// -------------------------------------------------------------

// Fetch template activity
$stmt = $mysqli->prepare("
    SELECT activity_name, duration_text, sub_task_id 
    FROM activities 
    WHERE activity_id = ?
");
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$template = $stmt->get_result()->fetch_assoc();

if (!$template) exit("Template not found");

// Fetch template subtask
$stmt = $mysqli->prepare("
    SELECT sub_task_name, description
    FROM sub_tasks
    WHERE sub_task_id = ?
");
$stmt->bind_param("i", $template['sub_task_id']);
$stmt->execute();
$subtemp = $stmt->get_result()->fetch_assoc();


// ----------- CREATE USER SUBTASK COPY -----------
$stmt = $mysqli->prepare("
    INSERT INTO sub_tasks (sub_task_name, description)
    VALUES (?, ?)
");
$stmt->bind_param("ss", $subtemp['sub_task_name'], $subtemp['description']);
$stmt->execute();
$new_subtask_id = $stmt->insert_id;


// ----------- CREATE USER ACTIVITY COPY -----------
$stmt = $mysqli->prepare("
    INSERT INTO activities (user_id, sub_task_id, activity_name, schedule_datetime, duration_text, created_by, template_id)
    VALUES (?, ?, ?, CURDATE(), ?, ?, ?)
");
$stmt->bind_param(
    "iissii",
    $user_id,
    $new_subtask_id,
    $template['activity_name'],
    $template['duration_text'],
    $user_id,         // created_by = this user
    $activity_id      // template_id = admin activity_id
);
$stmt->execute();
$new_activity_id = $stmt->insert_id;


// ----------- CREATE PROGRESS ENTRY -----------
$stmt = $mysqli->prepare("
    INSERT INTO activity_progress (user_id, sub_task_id, activity_id, is_done, progress_date)
    VALUES (?, ?, ?, 1, CURDATE())
");
$stmt->bind_param("iii", $user_id, $new_subtask_id, $new_activity_id);
$stmt->execute();


echo "created";
?>
