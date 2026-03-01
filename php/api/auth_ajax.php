<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }
$auth = new Auth();
$action = $_POST['action'] ?? '';
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username)) { echo json_encode(['success'=>false,'message'=>'Enter your username or email','field'=>'username']); exit; }
    if (empty($password)) { echo json_encode(['success'=>false,'message'=>'Enter your password','field'=>'password']); exit; }
    $result = $auth->login($username, $password, $_SERVER['REMOTE_ADDR']);
    if ($result['success']) { echo json_encode(['success'=>true,'redirect'=>SITE_URL.'/dashboard.php']); }
    else { echo json_encode(['success'=>false,'message'=>$result['message'],'field'=>'password']); }
    exit;
}
if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username)) { echo json_encode(['success'=>false,'message'=>'Choose a username','field'=>'username']); exit; }
    if (strlen($username) < 3) { echo json_encode(['success'=>false,'message'=>'Username too short (min 3 chars)','field'=>'username']); exit; }
    if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $username)) { echo json_encode(['success'=>false,'message'=>'Username: letters, numbers, _ . - only','field'=>'username']); exit; }
    if (empty($password)) { echo json_encode(['success'=>false,'message'=>'Set a password','field'=>'password']); exit; }
    $db = Database::getInstance();
    $existing = $db->fetchOne("SELECT user_id FROM users WHERE username = ?", [$username]);
    if ($existing) { echo json_encode(['success'=>false,'message'=>'Username already taken','field'=>'username']); exit; }
    if (!empty($email)) {
        $emailTaken = $db->fetchOne("SELECT user_id FROM users WHERE email = ?", [$email]);
        if ($emailTaken) { echo json_encode(['success'=>false,'message'=>'Email already registered','field'=>'email']); exit; }
    }
    $result = $auth->register($username, $email, $password);
    if ($result['success']) {
        $login = $auth->login($username, $password, $_SERVER['REMOTE_ADDR']);
        if ($login['success']) { echo json_encode(['success'=>true,'redirect'=>SITE_URL.'/dashboard.php','new_user'=>true]); }
        else { echo json_encode(['success'=>true,'redirect'=>SITE_URL.'/login.php','new_user'=>true]); }
    } else { echo json_encode(['success'=>false,'message'=>$result['message']]); }
    exit;
}
if ($action === 'guest') {
    $auth->startGuestSession();
    echo json_encode(['success'=>true,'redirect'=>SITE_URL.'/dashboard.php','guest'=>true]);
    exit;
}
if ($action === 'guest_upgrade') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email    = trim($_POST['email'] ?? '');
    $guestData = $_POST['guest_data'] ?? '[]';
    if (empty($username) || strlen($username) < 3) { echo json_encode(['success'=>false,'message'=>'Username too short','field'=>'username']); exit; }
    if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $username)) { echo json_encode(['success'=>false,'message'=>'Invalid username characters','field'=>'username']); exit; }
    if (empty($password)) { echo json_encode(['success'=>false,'message'=>'Set a password','field'=>'password']); exit; }
    $db = Database::getInstance();
    $existing = $db->fetchOne("SELECT user_id FROM users WHERE username = ?", [$username]);
    if ($existing) { echo json_encode(['success'=>false,'message'=>'Username taken','field'=>'username']); exit; }
    $result = $auth->register($username, $email, $password);
    if ($result['success']) {
        $auth->login($username, $password, $_SERVER['REMOTE_ADDR']);
        echo json_encode(['success'=>true,'redirect'=>SITE_URL.'/dashboard.php']);
    } else { echo json_encode(['success'=>false,'message'=>$result['message']]); }
    exit;
}
echo json_encode(['success'=>false,'message'=>'Invalid action']);
