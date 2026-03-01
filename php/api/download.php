<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();
$auth->requireLogin();
$db     = Database::getInstance();
$fileId = (int)($_GET['id'] ?? 0);
if (!$fileId) { http_response_code(400); die('Invalid file ID.'); }
$file = $db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?", [$fileId, $_SESSION['user_id']]);
if (!$file) { http_response_code(404); die('File not found or access denied.'); }
$filePath = UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $file['storage_path']);
if (!file_exists($filePath)) { http_response_code(404); die('File not found on disk.'); }
$mimeMap = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','bmp'=>'image/bmp','wav'=>'audio/wav','mp3'=>'audio/mpeg','mp4'=>'video/mp4','avi'=>'video/avi'];
$ext  = strtolower(pathinfo($file['original_filename'], PATHINFO_EXTENSION));
$mime = $mimeMap[$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . addslashes($file['original_filename']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-store');
readfile($filePath);
exit;
