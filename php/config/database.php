<?php
define('DB_HOST',    'localhost');
define('DB_NAME',    'stegops_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
define('SITE_URL',   'http://localhost/StegoPS');
define('UPLOAD_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('MAX_FILE_SIZE', 200 * 1024 * 1024);
define('PYTHON_API_URL',    'http://127.0.0.1:5001');
define('PYTHON_API_SECRET', 'stegops_internal_secret_2025');
define('SESSION_LIFETIME', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);
class Database {
private static $instance = null;
private $pdo;
private function __construct() {
    $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
    $opts = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
    try { $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $opts); }
    catch (PDOException $e) { die(json_encode(['error'=>'Database connection failed.'])); }
}
public static function getInstance(): Database { if (self::$instance===null) self::$instance=new Database(); return self::$instance; }
public function getConnection(): PDO { return $this->pdo; }
public function query(string $sql, array $params=[]): PDOStatement { $s=$this->pdo->prepare($sql); $s->execute($params); return $s; }
public function fetchOne(string $sql, array $params=[]): ?array { return $this->query($sql,$params)->fetch()?:null; }
public function fetchAll(string $sql, array $params=[]): array { return $this->query($sql,$params)->fetchAll(); }
public function insert(string $sql, array $params=[]): int { $this->query($sql,$params); return (int)$this->pdo->lastInsertId(); }
}
