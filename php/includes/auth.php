<?php
require_once __DIR__ . '/../config/database.php';
class Auth {
private Database $db;
public function __construct() {
    $this->db=Database::getInstance();
    if(session_status()===PHP_SESSION_NONE){
        session_set_cookie_params(['lifetime'=>SESSION_LIFETIME,'secure'=>false,'httponly'=>true,'samesite'=>'Strict']);
        session_start();
    }
}
public function register(string $username,string $email,string $password): array {
    if(strlen($username)<3||strlen($username)>50)return['success'=>false,'message'=>'Username must be 3–50 characters.'];
    if(!preg_match('/^[a-zA-Z0-9_.\-]+$/',$username))return['success'=>false,'message'=>'Username: letters, numbers, _ . - only'];
    if(strlen($password)<1)return['success'=>false,'message'=>'Password cannot be empty.'];
    $existing=$this->db->fetchOne("SELECT user_id FROM users WHERE username=?",[$username]);
    if($existing)return['success'=>false,'message'=>'Username already taken.'];
    if(!empty($email)){$emailTaken=$this->db->fetchOne("SELECT user_id FROM users WHERE email=?",[$email]);if($emailTaken)return['success'=>false,'message'=>'Email already registered.'];}
    $salt=bin2hex(random_bytes(32));
    $hash=password_hash($password.$salt,PASSWORD_BCRYPT,['cost'=>12]);
    $stegKey=bin2hex(random_bytes(16));
    $userId=$this->db->insert("INSERT INTO users (username,email,password_hash,salt,steg_key,is_verified) VALUES (?,?,?,?,?,1)",[$username,$email?:null,$hash,$salt,$stegKey]);
    $this->logAction($userId,'register','New user registered');
    return['success'=>true,'message'=>'Account created!','user_id'=>$userId];
}
public function login(string $username,string $password,string $ip): array {
    $user=$this->db->fetchOne("SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1",[$username,$username]);
    if(!$user){$this->logAction(null,'login_failed',"No user: $username",$ip,'failed');return['success'=>false,'message'=>'No account found with that username or email.'];}
    if(!password_verify($password.$user['salt'],$user['password_hash'])){$this->logAction(null,'login_failed',"Bad pw: $username",$ip,'failed');return['success'=>false,'message'=>'Wrong password. Try again.'];}
    session_regenerate_id(true);
    $_SESSION['user_id']=$user['user_id'];$_SESSION['username']=$user['username'];$_SESSION['role']=$user['role'];$_SESSION['login_at']=time();$_SESSION['guest']=false;
    $this->db->query("UPDATE users SET last_login=NOW() WHERE user_id=?",[$user['user_id']]);
    $this->logAction($user['user_id'],'login','Successful login',$ip);
    return['success'=>true,'message'=>'Welcome, '.$user['username'].'!'];
}
public function startGuestSession(): void {
    session_regenerate_id(true);
    $row=$this->db->fetchOne("SELECT COUNT(*) as n FROM operation_logs WHERE action_type='guest_start'");
    $guestNum=(int)($row['n']??0)+1;
    $_SESSION['user_id']=0;$_SESSION['username']='Guest';$_SESSION['guest_num']=$guestNum;$_SESSION['role']='guest';$_SESSION['login_at']=time();$_SESSION['guest']=true;$_SESSION['guest_file_ids']=[];
    $this->logAction(null,'guest_start',"Guest #$guestNum started");
}
public function logout(): void {
    if(isset($_SESSION['user_id'])&&empty($_SESSION['guest']))$this->logAction($_SESSION['user_id'],'logout','User logged out');
    session_destroy();header('Location: '.SITE_URL.'/index.php');exit;
}
public function isLoggedIn(): bool {
    if(!isset($_SESSION['user_id'],$_SESSION['login_at'])||(time()-$_SESSION['login_at'])>=SESSION_LIFETIME)return false;
    if(!empty($_SESSION['guest']))return true;
    $check=$this->db->fetchOne("SELECT user_id,is_active FROM users WHERE user_id=?",[$_SESSION['user_id']]);
    if(!$check||!$check['is_active']){session_destroy();return false;}
    return true;
}
public function isGuest(): bool{return!empty($_SESSION['guest']);}
public function isAdmin(): bool{return$this->isLoggedIn()&&($_SESSION['role']??'')==='admin';}
public function requireLogin(): void{if(!$this->isLoggedIn()){header('Location: '.SITE_URL.'/index.php?redirect='.urlencode($_SERVER['REQUEST_URI']));exit;}}
public function requireAdmin(): void{$this->requireLogin();if(!$this->isAdmin()){header('Location: '.SITE_URL.'/403.php');exit;}}
public function currentUser(): ?array {
    if(!$this->isLoggedIn())return null;
    if($this->isGuest()){$num=$_SESSION['guest_num']??0;return['user_id'=>0,'username'=>'Guest#'.$num,'display_name'=>'Guest','guest_num'=>$num,'email'=>'','role'=>'guest','storage_used'=>0,'steg_key'=>'','created_at'=>date('Y-m-d'),'last_login'=>date('Y-m-d')];}
    return$this->db->fetchOne("SELECT user_id,username,email,role,storage_used,steg_key,created_at,last_login FROM users WHERE user_id=?",[$_SESSION['user_id']]);
}
public function logAction(?int $userId,string $action,string $detail='',string $ip='',string $status='success'): void {
    if(!$ip)$ip=$_SERVER['REMOTE_ADDR']??'0.0.0.0';
    $ua=$_SERVER['HTTP_USER_AGENT']??'';
    $this->db->insert("INSERT INTO operation_logs (user_id,action_type,action_detail,ip_address,user_agent,status) VALUES (?,?,?,?,?,?)",[$userId,$action,$detail,$ip,$ua,$status]);
}
}