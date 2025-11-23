<!--
    FOR TESTING PURPOSES REMOVE LATER -Tricia
    This adds an admin user and 3 exercises that are 'created' by that admin user
-->

<?php
session_start();
include 'config.php';

// --- Admin user setup ---
$admin_username = 'admin';
$admin_fullname = 'admin';
$admin_email = 'admin@gmail.com';
$admin_password = password_hash('admin', PASSWORD_DEFAULT);

// Check if admin exists
$stmt = $mysqli->prepare("SELECT user_id FROM users WHERE username=?");
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    $admin_id = $admin['user_id'];
} else {
    // Insert admin
    $stmt = $mysqli->prepare("
        INSERT INTO users (username, full_name, email, password_hash, role_id)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("ssss", $admin_username, $admin_fullname, $admin_email, $admin_password);
    $stmt->execute();
    $admin_id = $mysqli->insert_id;
}

// --- Sub-tasks setup ---
$subtasks = [
    ['name' => 'Journaling', 'description' => 'Put your thoughts to pen'],
    ['name' => 'Meditate', 'description' => 'Clear your mind'],
    ['name' => 'Drink Water', 'description' => 'Stay hydrated']
];

$subtask_ids = [];
foreach ($subtasks as $st) {
    // Check if sub-task exists
    $stmt = $mysqli->prepare("SELECT sub_task_id FROM sub_tasks WHERE sub_task_name=?");
    $stmt->bind_param("s", $st['name']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $subtask_ids[] = $row['sub_task_id'];
    } else {
        // Insert sub-task
        $stmt = $mysqli->prepare("INSERT INTO sub_tasks (sub_task_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $st['name'], $st['description']);
        $stmt->execute();
        $subtask_ids[] = $mysqli->insert_id;
    }
}

// --- Activities setup ---
$activities = [
    ['activity_name' => 'Reflection', 'sub_task_index' => 0],
    ['activity_name' => 'Slowing Down', 'sub_task_index' => 1],
    ['activity_name' => 'Hydration', 'sub_task_index' => 2]
];

foreach ($activities as $act) {
    $sub_id = $subtask_ids[$act['sub_task_index']];

    // Check if activity already exists for admin + subtask
    $stmt = $mysqli->prepare("
        SELECT activity_id FROM activities 
        WHERE activity_name=? AND sub_task_id=? AND created_by=?
    ");
    $stmt->bind_param("sii", $act['activity_name'], $sub_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Insert activity
        $stmt = $mysqli->prepare("
            INSERT INTO activities (user_id, sub_task_id, activity_name, schedule_datetime, duration_text, created_by)
            VALUES (?, ?, ?, NOW(), '15 mins', ?)
        ");
        $stmt->bind_param("iisi", $admin_id, $sub_id, $act['activity_name'], $admin_id);
        $stmt->execute();
    }
}

// Redirect back to exerciselist.php
header("Location: exerciselist.php");
exit;
