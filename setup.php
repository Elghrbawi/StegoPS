<?php
define('DB_HOST','localhost');
define('DB_NAME','stegops_db');
define('DB_USER','root');
define('DB_PASS','');
define('DB_CHARSET','utf8mb4');
define('SITE_URL','http://localhost/StegoPS');
define('UPLOAD_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('SESSION_LIFETIME',3600);
define('MAX_FILE_SIZE',200*1024*1024);
define('PYTHON_API_URL','http://127.0.0.1:5001');
define('PYTHON_API_SECRET','stegops_internal_secret_2025');
define('MAX_LOGIN_ATTEMPTS',5);
define('LOCKOUT_TIME',900);
$steps = [];
$dirs = [
UPLOAD_PATH,
UPLOAD_PATH . 'carriers',
UPLOAD_PATH . 'stego',
UPLOAD_PATH . 'payloads',
UPLOAD_PATH . 'temp',
];
foreach ($dirs as $dir) {
if (!is_dir($dir)) {
if (mkdir($dir, 0755, true)) $steps[] = ['ok','Created: '.$dir];
else $steps[] = ['err','Failed to create: '.$dir . ' — check permissions'];
} else {
$steps[] = ['ok','Exists: '.$dir];
}
}
$htaccess = UPLOAD_PATH . '.htaccess';
if (!file_exists($htaccess)) {
file_put_contents($htaccess, "Options -Indexes\nphp_flag engine off\n<FilesMatch \"\\.php$\">\nDeny from all\n</FilesMatch>");
$steps[] = ['ok','Created .htaccess in uploads/'];
} else {
$steps[] = ['ok','.htaccess already exists in uploads/'];
}
try {
$pdo = new PDO("mysql:host=".DB_HOST.";charset=".DB_CHARSET, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."`");
$steps[] = ['ok','Database `'.DB_NAME.'` ready'];
$sql = file_get_contents(__DIR__.'/database/stegops.sql');
if ($sql) {
$pdo->exec("USE `".DB_NAME."`");
$pdo->exec($sql);
$steps[] = ['ok','Database schema imported from stegops.sql'];
} else {
$steps[] = ['warn','stegops.sql not found — import manually'];
}
} catch (PDOException $e) {
$steps[] = ['err','DB Error: '.$e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StegoPS Setup</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#050c0e;color:#e8f4f1;font-family:'Syne',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#0a1a1f;border:1px solid #1a3d4a;border-radius:16px;padding:36px;max-width:560px;width:100%}
h1{font-family:'Space Mono',monospace;font-size:20px;color:#00e5c8;margin-bottom:6px}
.sub{font-family:'Space Mono',monospace;font-size:10px;color:#3d7068;letter-spacing:2px;margin-bottom:24px;text-transform:uppercase}
.step{display:flex;align-items:flex-start;gap:12px;padding:9px 0;border-bottom:1px solid rgba(26,61,74,.4);font-family:'Space Mono',monospace;font-size:11px;word-break:break-all}
.step:last-child{border-bottom:none}
.ok{color:#00e5c8;flex-shrink:0;font-weight:700}
.err{color:#ff4d6d;flex-shrink:0;font-weight:700}
.warn{color:#ffb347;flex-shrink:0;font-weight:700}
.note{margin-top:20px;background:rgba(255,77,109,.08);border:1px solid rgba(255,77,109,.25);border-radius:8px;padding:14px;font-family:'Space Mono',monospace;font-size:10px;color:rgba(255,77,109,.8);line-height:1.7}
.btn{display:inline-block;margin-top:18px;font-family:'Space Mono',monospace;font-size:12px;font-weight:700;padding:12px 24px;background:#00e5c8;color:#050c0e;border-radius:8px;text-decoration:none}
.btn:hover{background:#00b89f}
</style>
</head>
<body>
<div class="card">
<h1>StegoPS Setup</h1>
<div class="sub">One-time initialization</div>
<?php foreach($steps as $s): ?>
<div class="step">
<span class="<?= $s[0] ?>"><?= $s[0]==='ok'?'✓':($s[0]==='err'?'✗':'!') ?></span>
<span><?= htmlspecialchars($s[1]) ?></span>
</div>
<?php endforeach; ?>
<div class="note">⚠ Delete or rename this file (setup.php) after setup is complete to prevent re-running.</div>
<a href="<?= SITE_URL ?>/index.php" class="btn">→ Go to StegoPS</a>
</div>
</body>
</html>
