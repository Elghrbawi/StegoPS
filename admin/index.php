<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../php/config/database.php';
$adminAuth = new AdminAuth();
$adminAuth->requireAdmin();
$admin = $adminAuth->currentAdmin();
$db    = Database::getInstance();
$stats = [
'users'  => $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE role!='admin'")['c'] ?? 0,
'ops'    => $db->fetchOne("SELECT COUNT(*) as c FROM stego_operations")['c'] ?? 0,
'algos'  => $db->fetchOne("SELECT COUNT(*) as c FROM encryption_algorithms")['c'] ?? 0,
'shares' => $db->fetchOne("SELECT COUNT(*) as c FROM shared_files WHERE is_active=1")['c'] ?? 0,
];
$users     = $db->fetchAll("SELECT user_id,username,email,role,is_active,storage_used,created_at,last_login FROM users ORDER BY created_at DESC LIMIT 30");
$recentOps = $db->fetchAll("SELECT o.op_id,u.username,f.original_filename,o.operation_type,o.status,o.created_at FROM stego_operations o JOIN users u ON o.user_id=u.user_id JOIN media_files f ON o.carrier_file_id=f.file_id ORDER BY o.created_at DESC LIMIT 15");
$allAlgos  = $db->fetchAll("SELECT * FROM encryption_algorithms ORDER BY category,algo_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StegoPS — Admin Panel</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/frontend/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
.admin-navbar{position:fixed;top:0;left:0;right:0;z-index:200;height:60px;background:rgba(5,12,14,.96);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,77,109,.25);display:flex;align-items:center;justify-content:space-between;padding:0 24px}
.admin-brand{font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:#ff4d6d;display:flex;align-items:center;gap:8px}
.admin-brand-dot{width:7px;height:7px;background:#ff4d6d;border-radius:50%;animation:pulse 2s infinite;box-shadow:0 0 6px #ff4d6d}
.admin-user{display:flex;align-items:center;gap:12px;font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)}
.admin-badge{background:rgba(255,77,109,.15);border:1px solid rgba(255,77,109,.3);color:#ff4d6d;font-family:'Space Mono',monospace;font-size:9px;font-weight:700;padding:3px 8px;border-radius:4px;letter-spacing:1px}
.admin-layout{padding-top:60px;min-height:100vh}
.admin-content{padding:28px;max-width:1400px;margin:0 auto}
.danger-stat::before{background:#ff4d6d !important}
.danger-val{color:#ff4d6d !important}
</style>
</head>
<body>
<nav class="admin-navbar">
<div class="admin-brand">
<div class="admin-brand-dot"></div>
StegoPS <span style="opacity:.4;font-size:12px;margin:0 6px">/</span> Admin Panel
</div>
<div class="admin-user">
<span class="admin-badge">ADMIN</span>
<span><?= htmlspecialchars($admin['username']) ?></span>
<a href="<?= SITE_URL ?>/admin/logout.php" class="btn btn-danger" style="padding:5px 14px;font-size:10px">Logout</a>
<a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-ghost" style="padding:5px 14px;font-size:10px">← Main Site</a>
</div>
</nav>
<div class="admin-layout">
<div class="admin-content">
<div class="page-header">
<div>
<div class="page-title" style="color:#ff4d6d">Admin Panel</div>
<div class="page-sub">// SYSTEM ADMINISTRATION — STEGOPS v1.0 · Logged in as <?= htmlspecialchars($admin['username']) ?></div>
</div>
</div>
<div class="grid-4" style="margin-bottom:20px">
<div class="stat-card danger-stat"><div class="stat-value danger-val"><?= $stats['users'] ?></div><div class="stat-label">Registered Users</div></div>
<div class="stat-card blue"><div class="stat-value blue"><?= $stats['ops'] ?></div><div class="stat-label">Total Operations</div></div>
<div class="stat-card purple"><div class="stat-value purple"><?= $stats['algos'] ?></div><div class="stat-label">Algorithms</div></div>
<div class="stat-card orange"><div class="stat-value orange"><?= $stats['shares'] ?></div><div class="stat-label">Active Share Links</div></div>
</div>
<div class="grid-2">
<div class="card">
<div class="card-title">Registered Users</div>
<div style="overflow-x:auto">
<table class="data-table">
<thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Storage</th><th>Joined</th></tr></thead>
<tbody>
<?php foreach($users as $u): ?>
<tr>
<td style="font-weight:600;color:var(--text)"><?= htmlspecialchars($u['username']) ?></td>
<td style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($u['email']??'—') ?></td>
<td><span class="badge <?= $u['role']==='admin'?'badge-video':'badge-success' ?>"><?= strtoupper($u['role']) ?></span></td>
<td><span class="badge <?= $u['is_active']?'badge-success':'badge-failed' ?>"><?= $u['is_active']?'ACTIVE':'SUSPENDED' ?></span></td>
<td style="font-family:'Space Mono',monospace;font-size:10px"><?= round($u['storage_used']/1024/1024,1) ?>MB</td>
<td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<div class="card">
<div class="card-title">Recent Operations (All Users)</div>
<div style="overflow-x:auto">
<table class="data-table">
<thead><tr><th>User</th><th>File</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
<tbody>
<?php foreach($recentOps as $op): ?>
<tr>
<td style="color:var(--accent);font-size:12px"><?= htmlspecialchars($op['username']) ?></td>
<td style="font-size:11px"><?= htmlspecialchars(substr($op['original_filename'],0,16)) ?></td>
<td><span class="badge badge-image"><?= strtoupper($op['operation_type']) ?></span></td>
<td><span class="badge badge-<?= $op['status']==='completed'?'success':($op['status']==='failed'?'failed':'pending') ?>"><?= strtoupper($op['status']) ?></span></td>
<td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)"><?= date('d M',strtotime($op['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
<div class="divider"></div>
<div class="card">
<div class="card-title">Algorithm Registry — <?= $stats['algos'] ?> algorithms</div>
<div style="overflow-x:auto">
<table class="data-table">
<thead><tr><th>Name</th><th>Slug</th><th>Category</th><th>Module</th><th>Status</th></tr></thead>
<tbody>
<?php foreach($allAlgos as $a): ?>
<tr>
<td style="font-weight:600;color:var(--text)"><?= htmlspecialchars($a['algo_name']) ?></td>
<td style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--accent)"><?= htmlspecialchars($a['algo_slug']) ?></td>
<td><span class="badge badge-<?= $a['category']==='symmetric'?'image':($a['category']==='classical'?'video':($a['category']==='custom'?'audio':'success')) ?>"><?= strtoupper($a['category']) ?></span></td>
<td style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-muted)"><?= htmlspecialchars($a['python_module']??'—') ?></td>
<td><span class="badge <?= $a['is_active']?'badge-success':'badge-failed' ?>"><?= $a['is_active']?'ACTIVE':'DISABLED' ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</body>
</html>
