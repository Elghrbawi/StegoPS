<?php
require_once __DIR__ . '/../php/config/database.php';
class AdminAuth {
private Database $db;
private const SESSION_KEY  = 'admin_logged_in';
private const USER_KEY     = 'admin_user_id';
private const USER_NAME    = 'admin_username';
private const LOGIN_TIME   = 'admin_login_at';
private const SESSION_LIFE = 7200;
public function __construct() {
    $this->db = Database::getInstance();
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime'=>self::SESSION_LIFE,'secure'=>false,'httponly'=>true,'samesite'=>'Strict']);
        session_start();
    }
}
public function login(string $username, string $password, string $ip): array {
    $user = $this->db->fetchOne("SELECT * FROM users WHERE (username=? OR email=?) AND role='admin' AND is_active=1",[$username,$username]);
    if (!$user) return ['success'=>false,'message'=>'Admin account not found.'];
    if (!password_verify($password.$user['salt'],$user['password_hash'])) {
        $this->logAction($user['user_id'],'admin_login_failed','Bad password',$ip,'failed');
        return ['success'=>false,'message'=>'Wrong password.'];
    }
    session_regenerate_id(true);
    $_SESSION[self::SESSION_KEY] = true;
    $_SESSION[self::USER_KEY]    = $user['user_id'];
    $_SESSION[self::USER_NAME]   = $user['username'];
    $_SESSION[self::LOGIN_TIME]  = time();
    $this->db->query("UPDATE users SET last_login=NOW() WHERE user_id=?",[$user['user_id']]);
    $this->logAction($user['user_id'],'admin_login','Admin login',$ip);
    return ['success'=>true];
}
public function logout(): void {
    if ($this->isLoggedIn()) $this->logAction($_SESSION[self::USER_KEY],'admin_logout','Admin logged out');
    unset($_SESSION[self::SESSION_KEY],$_SESSION[self::USER_KEY],$_SESSION[self::USER_NAME],$_SESSION[self::LOGIN_TIME]);
    header('Location: '.SITE_URL.'/admin/login.php');
    exit;
}
public function isLoggedIn(): bool {
    return !empty($_SESSION[self::SESSION_KEY]) && isset($_SESSION[self::LOGIN_TIME]) && (time()-$_SESSION[self::LOGIN_TIME]) < self::SESSION_LIFE;
}
public function requireAdmin(): void {
    if (!$this->isLoggedIn()) { header('Location: '.SITE_URL.'/admin/login.php'); exit; }
}
public function currentAdmin(): array {
    return ['user_id'=>$_SESSION[self::USER_KEY]??0,'username'=>$_SESSION[self::USER_NAME]??'Admin'];
}
private function logAction(int $userId, string $action, string $detail='', string $ip='', string $status='success'): void {
    if (!$ip) $ip=$_SERVER['REMOTE_ADDR']??'0.0.0.0';
    $ua=$_SERVER['HTTP_USER_AGENT']??'';
    $this->db->insert("INSERT INTO operation_logs (user_id,action_type,action_detail,ip_address,user_agent,status) VALUES (?,?,?,?,?,?)",[$userId,$action,$detail,$ip,$ua,$status]);
}
}
