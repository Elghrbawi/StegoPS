<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'method']); exit; }
$type  = $_POST['type']  ?? '';
$value = trim($_POST['value'] ?? '');
if (!in_array($type, ['username','email']) || strlen($value) < 1) { echo json_encode(['available' => false, 'message' => 'Invalid input']); exit; }
$db = Database::getInstance();
if ($type === 'username') {
    if (strlen($value) < 3)  { echo json_encode(['available' => false, 'message' => 'Too short (min 3)']); exit; }
    if (strlen($value) > 50) { echo json_encode(['available' => false, 'message' => 'Too long (max 50)']); exit; }
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $value)) { echo json_encode(['available' => false, 'message' => 'Only letters, numbers, _ . -']); exit; }
    $row = $db->fetchOne("SELECT user_id FROM users WHERE username = ?", [$value]);
    echo json_encode(['available' => !$row, 'message' => $row ? 'Username taken' : 'Available!']);
} else {
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) { echo json_encode(['available' => false, 'message' => 'Invalid email format']); exit; }
    $row = $db->fetchOne("SELECT user_id FROM users WHERE email = ?", [$value]);
    echo json_encode(['available' => !$row, 'message' => $row ? 'Email already registered' : 'Email available!']);
}
