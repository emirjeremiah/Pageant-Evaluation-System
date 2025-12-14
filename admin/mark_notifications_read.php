<?php
session_start();
require_once '../database/db.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");

header('Content-Type: application/json');
echo json_encode(['success' => true]);