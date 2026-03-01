<?php
// File: register.php
require_once 'php/config/database.php';
require_once 'php/includes/auth.php';
$auth = new Auth();
if ($auth->isLoggedIn()) { header('Location: ' . SITE_URL . '/dashboard.php'); exit; }

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->register(
        trim($_POST['username'] ?? ''),
        trim($_POST['email'] ?? ''),
        $_POST['password'] ?? ''
    );
    if ($result['success']) {
        $success = $result['message'] . ' You can now login.';
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StegoPS — Register</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/frontend/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="auth-logo-text">Steg<span style="color:#fff">👁</span>PS</div>
      <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);margin-top:6px;letter-spacing:2px">CREATE YOUR ACCOUNT</div>
    </div>

    <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST">
      <div class="input-group">
        <label class="input-label">Username</label>
        <input type="text" name="username" class="input" placeholder="Choose a username" required minlength="3" maxlength="50">
      </div>
      <div class="input-group">
        <label class="input-label">Email Address</label>
        <input type="email" name="email" class="input" placeholder="your@email.com" required>
      </div>
      <div class="input-group">
        <label class="input-label">Password</label>
        <input type="password" name="password" class="input" placeholder="Minimum 8 characters" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px">⬡ Create Account</button>
    </form>
    <div class="auth-footer">Already have an account? <a href="<?= SITE_URL ?>/login.php">Login</a></div>
  </div>
</div>
</body>
</html>
