<?php
// File: admin/index.php
$pageTitle  = 'Admin Panel';
$activePage = 'admin';
require_once '../php/includes/header.php';
$auth->requireAdmin();
$db = Database::getInstance();

$stats = [
    'users'  => $db->fetchOne("SELECT COUNT(*) as c FROM users")['c'],
    'ops'    => $db->fetchOne("SELECT COUNT(*) as c FROM stego_operations")['c'],
    'algos'  => $db->fetchOne("SELECT COUNT(*) as c FROM encryption_algorithms")['c'],
    'shares' => $db->fetchOne("SELECT COUNT(*) as c FROM shared_files WHERE is_active=1")['c'],
];

$users    = $db->fetchAll("SELECT user_id,username,email,role,is_active,created_at,last_login FROM users ORDER BY created_at DESC LIMIT 20");
$recentOps = $db->fetchAll(
    "SELECT o.op_id, u.username, f.original_filename, o.operation_type, o.status, o.created_at
     FROM stego_operations o
     JOIN users u ON o.user_id = u.user_id
     JOIN media_files f ON o.carrier_file_id = f.file_id
     ORDER BY o.created_at DESC LIMIT 10"
);
?>

<div class="page-header">
  <div>
    <div class="page-title">Admin Panel</div>
    <div class="page-sub">// SYSTEM ADMINISTRATION — STEGOPS v1.0</div>
  </div>
</div>

<div class="grid-4" style="margin-bottom:24px">
  <div class="stat-card green"><div class="stat-value green"><?= $stats['users'] ?></div><div class="stat-label">Total Users</div></div>
  <div class="stat-card blue"><div class="stat-value blue"><?= $stats['ops'] ?></div><div class="stat-label">Total Operations</div></div>
  <div class="stat-card purple"><div class="stat-value purple"><?= $stats['algos'] ?></div><div class="stat-label">Algorithms</div></div>
  <div class="stat-card orange"><div class="stat-value orange"><?= $stats['shares'] ?></div><div class="stat-label">Active Links</div></div>
</div>

<div class="grid-2">
  <!-- Users Table -->
  <div class="card">
    <div class="card-title">Registered Users</div>
    <table class="data-table">
      <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td style="font-weight:600;color:var(--text)"><?= htmlspecialchars($u['username']) ?></td>
          <td style="font-size:11px"><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge <?= $u['role']==='admin'?'badge-video':'badge-success' ?>"><?= strtoupper($u['role']) ?></span></td>
          <td><span class="badge <?= $u['is_active']?'badge-success':'badge-failed' ?>"><?= $u['is_active']?'ACTIVE':'SUSPENDED' ?></span></td>
          <td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Recent Operations -->
  <div class="card">
    <div class="card-title">Recent Operations (All Users)</div>
    <table class="data-table">
      <thead><tr><th>User</th><th>File</th><th>Type</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recentOps as $op): ?>
        <tr>
          <td style="color:var(--accent);font-size:12px"><?= htmlspecialchars($op['username']) ?></td>
          <td style="font-size:11px"><?= htmlspecialchars(substr($op['original_filename'],0,18)) ?></td>
          <td><span class="badge badge-image"><?= strtoupper($op['operation_type']) ?></span></td>
          <td><span class="badge badge-<?= $op['status']==='completed'?'success':($op['status']==='failed'?'failed':'pending') ?>"><?= strtoupper($op['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Algorithm Management -->
<div class="divider"></div>
<div class="card">
  <div class="card-title">Algorithm Registry (<?= $stats['algos'] ?> algorithms)</div>
  <?php $allAlgos = $db->fetchAll("SELECT * FROM encryption_algorithms ORDER BY category, algo_name"); ?>
  <table class="data-table">
    <thead><tr><th>Name</th><th>Slug</th><th>Category</th><th>Module</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($allAlgos as $a): ?>
      <tr>
        <td style="font-weight:600;color:var(--text)"><?= htmlspecialchars($a['algo_name']) ?></td>
        <td style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--accent)"><?= htmlspecialchars($a['algo_slug']) ?></td>
        <td><span class="badge badge-<?= $a['category']==='symmetric'?'image':($a['category']==='classical'?'video':($a['category']==='custom'?'audio':'success')) ?>"><?= strtoupper($a['category']) ?></span></td>
        <td style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-muted)"><?= htmlspecialchars($a['python_module']) ?></td>
        <td><span class="badge <?= $a['is_active']?'badge-success':'badge-failed' ?>"><?= $a['is_active']?'ACTIVE':'DISABLED' ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once '../php/includes/footer.php'; ?>
