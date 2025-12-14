<?php
session_start();
include '../database/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $judge_id = $_SESSION['judge_id']; // Use the secure session ID
    $criteria_id = $_POST['criteria_id'];
    $competition_id = $_SESSION['competition_id']; // Use the secure session ID
    $scores = $_POST['scores'];

    foreach ($scores as $contestant_id => $score) {
        $check = $conn->prepare("SELECT id FROM scores WHERE judge_id=? AND contestant_id=? AND criteria_id=? AND competition_id=?");
        $check->bind_param("iiii", $judge_id, $contestant_id, $criteria_id, $competition_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $update = $conn->prepare("UPDATE scores SET score=? WHERE judge_id=? AND contestant_id=? AND criteria_id=? AND competition_id=?");
            $update->bind_param("diiii", $score, $judge_id, $contestant_id, $criteria_id, $competition_id);
            $update->execute();
        } else {
            $insert = $conn->prepare("INSERT INTO scores (competition_id, judge_id, contestant_id, criteria_id, score) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param("iiiid", $competition_id, $judge_id, $contestant_id, $criteria_id, $score);
            $insert->execute();
        }
    }

    // --- Create a notification for the admin ---
    $judge_info = $conn->query("SELECT name FROM judges WHERE id = $judge_id")->fetch_assoc();
    $criteria_info = $conn->query("SELECT name FROM criteria WHERE id = $criteria_id")->fetch_assoc();
    
    if ($judge_info && $criteria_info) {
        $judge_name = $judge_info['name'];
        $criteria_name = $criteria_info['name'];
        $message = "<b>" . htmlspecialchars($judge_name) . "</b> has submitted scores for the <b>" . htmlspecialchars($criteria_name) . "</b> criterion.";
        
        $notify_stmt = $conn->prepare("INSERT INTO notifications (message) VALUES (?)");
        $notify_stmt->bind_param("s", $message);
        $notify_stmt->execute();
    }

    header("Location: judge_dashboard.php?success=1&criteria_id=" . urlencode($criteria_id));
    exit();
}
?>