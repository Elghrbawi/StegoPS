<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$auth = new Auth();
if (!$auth->isLoggedIn() || $auth->isGuest()) { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
$db     = Database::getInstance();
$fileId = (int)($_POST['file_id'] ?? 0);
if (!$fileId) { echo json_encode(['success'=>false,'message'=>'Invalid file ID']); exit; }
$file = $db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?", [$fileId, $_SESSION['user_id']]);
if (!$file) { echo json_encode(['success'=>false,'message'=>'File not found or access denied']); exit; }
$filePath = UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $file['storage_path']);
if (file_exists($filePath)) @unlink($filePath);
$db->query("UPDATE users SET storage_used=GREATEST(0,storage_used-?) WHERE user_id=?", [$file['file_size_bytes'], $_SESSION['user_id']]);
$db->query("DELETE FROM media_files WHERE file_id=?", [$fileId]);
echo json_encode(['success'=>true,'message'=>'File deleted']);
