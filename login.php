<?php
// File: login.php
require_once 'php/config/database.php';
require_once 'php/includes/auth.php';
$auth = new Auth();
if ($auth->isLoggedIn()) { header('Location: ' . SITE_URL . '/dashboard.php'); exit; }

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->login(
        trim($_POST['username'] ?? ''),
        $_POST['password'] ?? '',
        $_SERVER['REMOTE_ADDR']
    );
    if ($result['success']) {
        $redirect = $_GET['redirect'] ?? SITE_URL . '/dashboard.php';
        header('Location: ' . $redirect); exit;
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StegoPS — Login</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/frontend/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="auth-logo-text">Steg<span style="color:#fff;position:relative">👁</span>PS</div>
      <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);margin-top:6px;letter-spacing:2px">STEGANOGRAPHIC PROTECTION SYSTEM</div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="input-group">
        <label class="input-label">Username or Email</label>
        <input type="text" name="username" class="input" placeholder="Enter your username" required autocomplete="username">
      </div>
      <div class="input-group">
        <label class="input-label">Password</label>
        <input type="password" name="password" class="input" placeholder="Enter your password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px">→ Login to StegoPS</button>
    </form>

    <div class="auth-footer">
      Don't have an account? <a href="<?= SITE_URL ?>/register.php">Create one</a>
    </div>
  </div>
</div>
<script src="<?= SITE_URL ?>/frontend/js/main.js"></script>
</body>
</html>
