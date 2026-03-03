<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$auth = new Auth();
if (!$auth->isLoggedIn() || $auth->isGuest()) { echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
$db     = Database::getInstance();
$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';
/* ── UPDATE USERNAME ── */
if ($action === 'username') {
$username = trim($_POST['username'] ?? '');
if (strlen($username) < 3 || strlen($username) > 50) { echo json_encode(['success'=>false,'message'=>'Username must be 3–50 characters']); exit; }
if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $username)) { echo json_encode(['success'=>false,'message'=>'Username: letters, numbers, _ . - only']); exit; }
$exists = $db->fetchOne("SELECT user_id FROM users WHERE username=? AND user_id!=?",[$username,$userId]);
if ($exists) { echo json_encode(['success'=>false,'message'=>'Username already taken']); exit; }
$db->query("UPDATE users SET username=? WHERE user_id=?",[$username,$userId]);
$_SESSION['username'] = $username;
echo json_encode(['success'=>true,'message'=>'Username updated to '.$username]);
exit;
}
/* ── UPDATE EMAIL ── */
if ($action === 'email') {
$email = trim($_POST['email'] ?? '');
if (!empty($email)) {
$exists = $db->fetchOne("SELECT user_id FROM users WHERE email=? AND user_id!=?",[$email,$userId]);
if ($exists) { echo json_encode(['success'=>false,'message'=>'Email already in use']); exit; }
}
$db->query("UPDATE users SET email=? WHERE user_id=?",[$email?:null,$userId]);
echo json_encode(['success'=>true,'message'=>'Email updated']);
exit;
}
/* ── CHANGE PASSWORD ── */
if ($action === 'password') {
$currentPw  = $_POST['current_password'] ?? '';
$newPw      = $_POST['new_password'] ?? '';
$confirmPw  = $_POST['confirm_password'] ?? '';
if (empty($currentPw) || empty($newPw)) { echo json_encode(['success'=>false,'message'=>'Fill in all password fields']); exit; }
if ($newPw !== $confirmPw) { echo json_encode(['success'=>false,'message'=>'Passwords do not match']); exit; }
$user = $db->fetchOne("SELECT * FROM users WHERE user_id=?",[$userId]);
if (!$user || !password_verify($currentPw.$user['salt'],$user['password_hash'])) { echo json_encode(['success'=>false,'message'=>'Current password is wrong']); exit; }
$newHash = password_hash($newPw.$user['salt'], PASSWORD_BCRYPT, ['cost'=>12]);
$db->query("UPDATE users SET password_hash=? WHERE user_id=?",[$newHash,$userId]);
echo json_encode(['success'=>true,'message'=>'Password changed successfully']);
exit;
}
/* ── REGEN STEG KEY ── */
if ($action === 'regen_steg_key') {
$keyLen = max(16, min(64, (int)($_POST['key_length'] ?? 32)));
$newKey = bin2hex(random_bytes((int)ceil($keyLen/2)));
$newKey = substr($newKey, 0, $keyLen);
$db->query("UPDATE users SET steg_key=? WHERE user_id=?",[$newKey,$userId]);
echo json_encode(['success'=>true,'steg_key'=>$newKey,'message'=>'New steg key generated']);
exit;
}
/* ── SAVE CUSTOM STEG KEY ── */
if ($action === 'save_custom_steg_key') {
$key = trim($_POST['steg_key'] ?? '');
if (strlen($key) < 1) { echo json_encode(['success'=>false,'message'=>'Key cannot be empty']); exit; }
if (strlen($key) > 512) { echo json_encode(['success'=>false,'message'=>'Key too long (max 512)']); exit; }
$db->query("UPDATE users SET steg_key=? WHERE user_id=?",[$key,$userId]);
echo json_encode(['success'=>true,'steg_key'=>$key,'message'=>'Custom key saved']);
exit;
}
/* ── DELETE ACCOUNT ── */
if ($action === 'delete_account') {
$db->query("DELETE FROM users WHERE user_id=?",[$userId]);
session_destroy();
echo json_encode(['success'=>true]);
exit;
}
echo json_encode(['success'=>false,'message'=>'Invalid action']);