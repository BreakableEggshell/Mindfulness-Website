<!--
    Code is for exerciselist.php to save is_done.
-->

<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) exit('Not authorized');

$user_id = $_SESSION['user_id'];
$activity_id = $_POST['activity_id'] ?? 0;
$is_done = $_POST['is_done'] ?? 0;

if (!$activity_id) exit('Missing activity_id');

// Check if progress exists
$stmt = $mysqli->prepare("
    SELECT progress_id FROM activity_progress 
    WHERE user_id = ? AND activity_id = ?
");
$stmt->bind_param("ii", $user_id, $activity_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    // Update
    $stmt = $mysqli->prepare("
        UPDATE activity_progress 
        SET is_done = ? 
        WHERE user_id = ? AND activity_id = ?
    ");
    $stmt->bind_param("iii", $is_done, $user_id, $activity_id);
    $stmt->execute();
} else {
    // Insert
    $stmt = $mysqli->prepare("
        INSERT INTO activity_progress (user_id, activity_id, sub_task_id, is_done, progress_date)
        SELECT ?, a.activity_id, a.sub_task_id, ?, CURDATE()
        FROM activities a
        WHERE a.activity_id = ?
    ");
    $stmt->bind_param("iii", $user_id, $is_done, $activity_id);
    $stmt->execute();
}

echo 'success';
?>
