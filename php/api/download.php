<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();
$auth->requireLogin();
$db = Database::getInstance();
$fileId = (int)($_GET['id'] ?? 0);
if (!$fileId) { http_response_code(400); die('Invalid file ID.'); }
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid === 0) {
    $guestFiles = $_SESSION['guest_file_ids'] ?? [];
    if (!in_array($fileId, $guestFiles)) { http_response_code(403); die('Access denied.'); }
    $storageUser = $db->fetchOne("SELECT user_id FROM users WHERE username='guest_storage' LIMIT 1");
    if (!$storageUser) { http_response_code(404); die('Storage unavailable.'); }
    $file = $db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?", [$fileId, $storageUser['user_id']]);
} else {
    $file = $db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?", [$fileId, $uid]);
}
if (!$file) { http_response_code(404); die('File not found or access denied.'); }
$filePath = UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $file['storage_path']);
if (!file_exists($filePath)) { http_response_code(404); die('File not found on disk.'); }
$mimeMap = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','bmp'=>'image/bmp','wav'=>'audio/wav','mp3'=>'audio/mpeg'];
$ext  = strtolower(pathinfo($file['original_filename'], PATHINFO_EXTENSION));
$mime = $mimeMap[$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . addslashes($file['original_filename']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-store');
readfile($filePath);
exit;