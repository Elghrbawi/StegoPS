<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stego_handler.php';
require_once __DIR__ . '/../includes/chain_manager.php';
header('Content-Type: application/json');
$auth = new Auth();
if (!$auth->isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
$user = $auth->currentUser();
$uid  = (int)($user['user_id'] ?? 0);
$action = $_POST['action'] ?? '';
$handler = new StegoHandler();
/* ── EMBED ── */
if ($action === 'embed') {
if (empty($_FILES['carrier']['name']) || $_FILES['carrier']['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'message'=>'No carrier file received or upload error.']); exit; }
$stegoKey = trim($_POST['stego_key'] ?? '');
$payload  = $_POST['payload_text'] ?? '';
$pType    = $_POST['payload_type'] ?? 'text';
$chainRaw = json_decode($_POST['chain_data'] ?? '[]', true) ?: [];
if (empty($stegoKey)) { echo json_encode(['success'=>false,'message'=>'Stego key is required.']); exit; }
if (empty($payload) && $pType === 'text') { echo json_encode(['success'=>false,'message'=>'Payload text cannot be empty.']); exit; }
$uploaded = $handler->uploadCarrier($_FILES['carrier'], $uid);
if (!$uploaded['success']) { echo json_encode($uploaded); exit; }
$result = $handler->embed($uid, $uploaded['file_id'], null, $pType, $payload, $stegoKey, $chainRaw);
echo json_encode($result);
exit;
}
/* ── EXTRACT ── */
if ($action === 'extract') {
if (empty($_FILES['stego_file']['name']) || $_FILES['stego_file']['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'message'=>'No stego file received or upload error.']); exit; }
$stegoKey = trim($_POST['stego_key'] ?? '');
$chainRaw = json_decode($_POST['chain_data'] ?? '[]', true) ?: [];
if (empty($stegoKey)) { echo json_encode(['success'=>false,'message'=>'Stego key is required.']); exit; }
$uploaded = $handler->uploadCarrier($_FILES['stego_file'], $uid);
if (!$uploaded['success']) { echo json_encode($uploaded); exit; }
$result = $handler->extract($uid, $uploaded['file_id'], $stegoKey, $chainRaw);
echo json_encode($result);
exit;
}
echo json_encode(['success'=>false,'message'=>'Invalid action']);
