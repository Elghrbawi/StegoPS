<?php
// File: dashboard.php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once 'php/includes/header.php';
require_once 'php/includes/stego_handler.php';

$db      = Database::getInstance();
$handler = new StegoHandler();
$ops     = $handler->getUserOperations($user['user_id'], 5);

$stats = [
    'ops'    => $db->fetchOne("SELECT COUNT(*) as c FROM stego_operations WHERE user_id=?", [$user['user_id']])['c'] ?? 0,
    'chains' => $db->fetchOne("SELECT COUNT(*) as c FROM encryption_chains WHERE user_id=?", [$user['user_id']])['c'] ?? 0,
    'shares' => $db->fetchOne("SELECT COUNT(*) as c FROM shared_files sf JOIN stego_operations o ON sf.op_id=o.op_id WHERE o.user_id=? AND sf.is_active=1", [$user['user_id']])['c'] ?? 0,
];

$logs = $db->fetchAll(
    "SELECT * FROM operation_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 5",
    [$user['user_id']]
);
?>

<div class="page-header">
  <div>
    <div class="page-title">Welcome back, <?= htmlspecialchars($user['username']) ?></div>
    <div class="page-sub">// STEGANOGRAPHIC PROTECTION SYSTEM v1.0</div>
  </div>
  <a href="<?= SITE_URL ?>/embed.php" class="btn btn-primary btn-lg">⬇ New Embed Operation</a>
</div>

<!-- STATS -->
<div class="grid-4" style="margin-bottom:20px">
  <div class="stat-card green">
    <div class="stat-value green"><?= $stats['ops'] ?></div>
    <div class="stat-label">Total Operations</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-value purple"><?= $stats['chains'] ?></div>
    <div class="stat-label">Saved Chains</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-value blue"><?= $stats['shares'] ?></div>
    <div class="stat-label">Active Share Links</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-value orange"><?= round($user['storage_used']/1024/1024,1) ?><span style="font-size:16px">MB</span></div>
    <div class="stat-label">Storage Used</div>
  </div>
</div>

<div class="grid-2">
  <!-- Recent Operations -->
  <div class="card">
    <div class="card-title">Recent Operations</div>
    <?php if (empty($ops)): ?>
      <p style="color:var(--text-muted);font-family:'Space Mono',monospace;font-size:12px;text-align:center;padding:20px">No operations yet. <a href="<?= SITE_URL ?>/embed.php" style="color:var(--accent)">Start your first embed →</a></p>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>File</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($ops as $op): ?>
        <tr>
          <td style="font-family:'JetBrains Mono',monospace;font-size:11px"><?= htmlspecialchars(substr($op['original_filename'],0,20)) ?></td>
          <td><span class="badge badge-<?= $op['file_type'] ?>"><?= strtoupper($op['file_type']) ?></span></td>
          <td><span class="badge badge-<?= $op['status'] === 'completed' ? 'success' : ($op['status'] === 'failed' ? 'failed' : 'pending') ?>"><?= strtoupper($op['status']) ?></span></td>
          <td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)"><?= date('d M', strtotime($op['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Activity Feed -->
  <div class="card">
    <div class="card-title">Recent Activity</div>
    <?php foreach ($logs as $log): ?>
    <div class="activity-item">
      <div class="activity-dot" style="background:<?= $log['status']==='success'?'var(--accent)':'var(--danger)' ?>"></div>
      <div class="activity-text"><strong><?= htmlspecialchars($log['action_type']) ?></strong> — <?= htmlspecialchars(substr($log['action_detail'],0,40)) ?></div>
      <div class="activity-time"><?= date('H:i', strtotime($log['created_at'])) ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($logs)): ?>
      <p style="color:var(--text-muted);font-family:'Space Mono',monospace;font-size:11px;text-align:center;padding:20px">No activity yet</p>
    <?php endif; ?>
  </div>
</div>

<!-- Quick Actions -->
<div class="divider"></div>
<div class="card-title">Quick Actions</div>
<div class="grid-3" style="margin-top:12px">
  <a href="<?= SITE_URL ?>/embed.php" class="card" style="cursor:pointer;text-decoration:none;transition:border-color .2s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
    <div style="font-size:32px;margin-bottom:12px">⬇</div>
    <div style="font-size:15px;font-weight:700;margin-bottom:6px;color:var(--text)">Hide a Message</div>
    <div style="font-size:12px;color:var(--text-dim)">Embed encrypted payload inside image, audio, or video</div>
  </a>
  <a href="<?= SITE_URL ?>/extract.php" class="card" style="cursor:pointer;text-decoration:none;transition:border-color .2s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
    <div style="font-size:32px;margin-bottom:12px">⬆</div>
    <div style="font-size:15px;font-weight:700;margin-bottom:6px;color:var(--text)">Reveal a Message</div>
    <div style="font-size:12px;color:var(--text-dim)">Extract and decrypt a hidden payload from a stego file</div>
  </a>
  <a href="<?= SITE_URL ?>/chains.php" class="card" style="cursor:pointer;text-decoration:none;transition:border-color .2s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
    <div style="font-size:32px;margin-bottom:12px">⛓</div>
    <div style="font-size:15px;font-weight:700;margin-bottom:6px;color:var(--text)">Build a Chain</div>
    <div style="font-size:12px;color:var(--text-dim)">Design a custom encryption pipeline from 34+ algorithms</div>
  </a>
</div>

<?php require_once 'php/includes/footer.php'; ?>
