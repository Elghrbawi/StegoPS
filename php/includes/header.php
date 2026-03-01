<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
$auth = new Auth();
$auth->requireLogin();
$user = $auth->currentUser();
if (!isset($db)) $db = Database::getInstance();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StegoPS — <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/frontend/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="glitch-overlay" id="glitch-overlay"></div>
<nav class="navbar">
<button class="hamburger" id="hamburger" onclick="toggleSidebar()" aria-label="Menu">
<span></span><span></span><span></span>
</button>
<a href="<?= SITE_URL ?>/dashboard.php" class="navbar-brand">
<div class="brand-dot"></div>StegoPS
</a>
<div class="navbar-links">
<a href="<?= SITE_URL ?>/dashboard.php" class="<?= ($activePage??'')==='dashboard'?'active':'' ?>">Dashboard</a>
<a href="<?= SITE_URL ?>/embed.php" class="<?= ($activePage??'')==='embed'?'active':'' ?>">Embed</a>
<a href="<?= SITE_URL ?>/extract.php" class="<?= ($activePage??'')==='extract'?'active':'' ?>">Extract</a>
<a href="<?= SITE_URL ?>/chains.php" class="<?= ($activePage??'')==='chains'?'active':'' ?>">Chains</a>
<a href="<?= SITE_URL ?>/share.php" class="<?= ($activePage??'')==='share'?'active':'' ?>">Sharing</a>
</div>
<div class="navbar-user">
<?php if($auth->isGuest()): ?>
<button class="btn btn-primary btn-sm" onclick="document.getElementById('upgrade-modal')&&(document.getElementById('upgrade-modal').style.display='flex')" style="font-size:10px">⬡ Create Account</button>
<?php else: ?>
<a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
<?php endif; ?>
<div class="user-avatar" title="<?= htmlspecialchars($user['username']) ?>"><?= strtoupper(substr($user['username'],0,2)) ?></div>
</div>
</nav>
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>
<div class="app-layout">
<aside class="sidebar" id="sidebar">
<div class="sidebar-close-btn" onclick="toggleSidebar()">✕</div>
<div class="sidebar-label">Main</div>
<a href="<?= SITE_URL ?>/dashboard.php" class="sidebar-item <?= ($activePage??'')==='dashboard'?'active':'' ?>"><span class="sidebar-icon">⬡</span>Dashboard</a>
<a href="<?= SITE_URL ?>/embed.php" class="sidebar-item <?= ($activePage??'')==='embed'?'active':'' ?>"><span class="sidebar-icon">⬇</span>Embed</a>
<a href="<?= SITE_URL ?>/extract.php" class="sidebar-item <?= ($activePage??'')==='extract'?'active':'' ?>"><span class="sidebar-icon">⬆</span>Extract</a>
<div class="sidebar-label">Security</div>
<a href="<?= SITE_URL ?>/chains.php" class="sidebar-item <?= ($activePage??'')==='chains'?'active':'' ?>"><span class="sidebar-icon">⛓</span>Chain Builder<span class="sidebar-badge">34+</span></a>
<a href="<?= SITE_URL ?>/share.php" class="sidebar-item <?= ($activePage??'')==='share'?'active':'' ?>"><span class="sidebar-icon">⬡</span>Secure Share</a>
<div class="sidebar-label">Account</div>
<a href="<?= SITE_URL ?>/files.php" class="sidebar-item <?= ($activePage??'')==='files'?'active':'' ?>"><span class="sidebar-icon">◈</span>My Files</a>
<?php if($user['role']==='admin'): ?>
<a href="<?= SITE_URL ?>/admin/" class="sidebar-item <?= ($activePage??'')==='admin'?'active':'' ?>"><span class="sidebar-icon">◉</span>Admin Panel</a>
<?php endif; ?>
<a href="<?= SITE_URL ?>/logout.php" class="sidebar-item sidebar-logout"><span class="sidebar-icon">→</span>Logout</a>
</aside>
<main class="main-content">
