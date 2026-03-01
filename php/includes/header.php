<?php
// File: php/includes/header.php
// Usage: include at top of every protected page
// $pageTitle and $activePage must be set before including

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
$auth = new Auth();
$auth->requireLogin();
$user = $auth->currentUser();
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

<!-- NAVBAR -->
<nav class="navbar">
  <a href="<?= SITE_URL ?>/dashboard.php" class="navbar-brand">
    <div class="brand-dot"></div>StegoPS
  </a>
  <div class="navbar-links">
    <a href="<?= SITE_URL ?>/dashboard.php" class="<?= ($activePage??'')==='dashboard'?'active':'' ?>">Dashboard</a>
    <a href="<?= SITE_URL ?>/embed.php"     class="<?= ($activePage??'')==='embed'?'active':'' ?>">Embed</a>
    <a href="<?= SITE_URL ?>/extract.php"   class="<?= ($activePage??'')==='extract'?'active':'' ?>">Extract</a>
    <a href="<?= SITE_URL ?>/chains.php"    class="<?= ($activePage??'')==='chains'?'active':'' ?>">Chains</a>
    <a href="<?= SITE_URL ?>/share.php"     class="<?= ($activePage??'')==='share'?'active':'' ?>">Sharing</a>
  </div>
  <div class="navbar-user">
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost" style="padding:6px 14px;font-size:11px">Logout</a>
    <div class="user-avatar"><?= strtoupper(substr($user['username'],0,2)) ?></div>
  </div>
</nav>

<div class="app-layout">
<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-label">Main</div>
  <a href="<?= SITE_URL ?>/dashboard.php" class="sidebar-item <?= ($activePage??'')==='dashboard'?'active':'' ?>"><span class="sidebar-icon">⬡</span>Dashboard</a>
  <a href="<?= SITE_URL ?>/embed.php"     class="sidebar-item <?= ($activePage??'')==='embed'?'active':'' ?>"><span class="sidebar-icon">⬇</span>Embed</a>
  <a href="<?= SITE_URL ?>/extract.php"   class="sidebar-item <?= ($activePage??'')==='extract'?'active':'' ?>"><span class="sidebar-icon">⬆</span>Extract</a>

  <div class="sidebar-label">Security</div>
  <a href="<?= SITE_URL ?>/chains.php"    class="sidebar-item <?= ($activePage??'')==='chains'?'active':'' ?>"><span class="sidebar-icon">⛓</span>Chain Builder<span class="sidebar-badge">34+</span></a>
  <a href="<?= SITE_URL ?>/share.php"     class="sidebar-item <?= ($activePage??'')==='share'?'active':'' ?>"><span class="sidebar-icon">⬡</span>Secure Share</a>

  <div class="sidebar-label">Account</div>
  <a href="<?= SITE_URL ?>/files.php"     class="sidebar-item <?= ($activePage??'')==='files'?'active':'' ?>"><span class="sidebar-icon">◈</span>My Files</a>
  <?php if ($user['role'] === 'admin'): ?>
  <a href="<?= SITE_URL ?>/admin/"        class="sidebar-item <?= ($activePage??'')==='admin'?'active':'' ?>"><span class="sidebar-icon">◉</span>Admin Panel</a>
  <?php endif; ?>
  <a href="<?= SITE_URL ?>/logout.php"    class="sidebar-item" style="margin-top:auto"><span class="sidebar-icon">→</span>Logout</a>
</aside>

<main class="main-content">
