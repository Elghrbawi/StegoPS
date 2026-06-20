<?php
$pageTitle='Dashboard';
$activePage='dashboard';
require_once 'php/includes/header.php';
require_once 'php/includes/stego_handler.php';
$isGuest=$auth->isGuest();
if (!$isGuest) {
$handler=new StegoHandler();
$ops=$handler->getUserOperations($user['user_id'],8);
$stats=[
'ops'=>$db->fetchOne("SELECT COUNT(*) as c FROM stego_operations WHERE user_id=?",[$user['user_id']])['c']??0,
'chains'=>$db->fetchOne("SELECT COUNT(*) as c FROM encryption_chains WHERE user_id=?",[$user['user_id']])['c']??0,
'shares'=>$db->fetchOne("SELECT COUNT(*) as c FROM shared_files sf JOIN stego_operations o ON sf.op_id=o.op_id WHERE o.user_id=? AND sf.is_active=1",[$user['user_id']])['c']??0,
];
$logs=$db->fetchAll("SELECT * FROM operation_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 8",[$user['user_id']]);
} else {
$ops=[];$stats=['ops'=>0,'chains'=>0,'shares'=>0];$logs=[];
}
function fmtAmPm(string $dt):string{return date('d M Y g:i A',strtotime($dt));}
?>
<?php if($isGuest): ?>
<div class="guest-banner">
<div class="guest-banner-title">👁 GUEST MODE</div>
<div class="guest-banner-sub">Exploring without an account. Create one free to save work permanently.</div>
<button class="btn btn-primary btn-sm" onclick="document.getElementById('upgrade-modal').style.display='flex'" style="margin-top:6px">⬡ Create Free Account — takes 10 seconds</button>
</div>
<?php endif; ?>
<div class="page-header">
<div>
<div class="page-title">Welcome<?= $isGuest?' to StegoPS':', '.htmlspecialchars($user['username']) ?></div>
<div class="page-sub">// STEGANOGRAPHIC PROTECTION SYSTEM v1.0</div>
</div>
<?php if(!$isGuest): ?>
<a href="<?= SITE_URL ?>/embed.php" class="btn btn-primary btn-lg">⬇ New Embed</a>
<?php endif; ?>
</div>
<div class="ext-strip">
<span class="ext-strip-dot"></span>
<div class="ext-strip-text"><strong>StegOps extension</strong> is available now <span class="ext-strip-sub">&mdash; embed &amp; extract straight from your browser</span></div>
<div class="ext-strip-actions">
<a href="#" class="ext-strip-btn">⬡ Get the Add-on</a>
<a href="<?= SITE_URL ?>/StegTool.zip" class="ext-strip-btn ext-strip-btn-ghost" download>⬇ Manual .zip</a>
</div>
</div>
<div class="grid-4" style="margin-bottom:20px">
<div class="stat-card green"><div class="stat-value green"><?= $stats['ops'] ?></div><div class="stat-label">Total Operations</div></div>
<div class="stat-card purple"><div class="stat-value purple"><?= $stats['chains'] ?></div><div class="stat-label">Saved Chains</div></div>
<div class="stat-card blue"><div class="stat-value blue"><?= $stats['shares'] ?></div><div class="stat-label">Active Shares</div></div>
<div class="stat-card orange"><div class="stat-value orange"><?= $isGuest?'0':round($user['storage_used']/1024/1024,1) ?><span style="font-size:14px">MB</span></div><div class="stat-label">Storage Used</div></div>
</div>
<div class="grid-2" style="margin-bottom:20px">
<div class="card full-height-card">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
<div class="card-title" style="margin:0">Recent Operations</div>
<a href="<?= SITE_URL ?>/files.php" style="font-family:'Space Mono',monospace;font-size:10px;color:var(--accent);text-decoration:none">View All →</a>
</div>
<?php if(empty($ops)): ?>
<div class="empty-state" style="padding:24px 0">
<div class="empty-icon" style="font-size:24px">⬡</div>
<div class="empty-title" style="font-size:12px">No operations yet</div>
<a href="<?= SITE_URL ?>/embed.php" style="font-size:11px;color:var(--accent);text-decoration:none;margin-top:8px;display:inline-block">Start your first embed →</a>
</div>
<?php else: ?>
<div style="overflow-x:auto">
<table class="data-table">
<thead><tr><th>File</th><th>Type</th><th>Technique</th><th>Status</th><th>Date</th></tr></thead>
<tbody>
<?php foreach($ops as $op): ?>
<tr style="cursor:pointer" onclick="window.location='<?= SITE_URL ?>/files.php'">
<td style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text)"><?= htmlspecialchars(substr($op['original_filename'],0,18)) ?></td>
<td><span class="badge badge-<?= $op['file_type'] ?>"><?= strtoupper($op['file_type']) ?></span></td>
<td style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted)"><?= strtoupper(str_replace('_',' ',$op['stego_technique']??'')) ?></td>
<td><span class="badge badge-<?= $op['status']==='completed'?'success':($op['status']==='failed'?'failed':'pending') ?>"><?= strtoupper($op['status']) ?></span></td>
<td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)"><?= date('d M g:i A',strtotime($op['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
<div class="card full-height-card">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
<div class="card-title" style="margin:0">Recent Activity</div>
<span style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted)"><?= count($logs) ?> entries</span>
</div>
<?php if(empty($logs)): ?>
<div class="empty-state" style="padding:24px 0">
<div class="empty-icon" style="font-size:24px">◎</div>
<div class="empty-title" style="font-size:12px">No activity yet</div>
</div>
<?php else: ?>
<div style="overflow-y:auto;max-height:300px">
<?php foreach($logs as $log): ?>
<div class="activity-item">
<div class="activity-dot" style="background:<?= $log['status']==='success'?'var(--accent)':($log['status']==='blocked'?'var(--warning)':'var(--danger)') ?>"></div>
<div class="activity-text" style="min-width:0;flex:1">
<strong style="display:block;font-size:12px;color:var(--text)"><?= htmlspecialchars($log['action_type']) ?></strong>
<span style="font-size:11px"><?= htmlspecialchars(substr($log['action_detail']??'',0,40)) ?></span>
</div>
<div class="activity-time"><?= fmtAmPm($log['created_at']) ?></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
<div class="divider" style="margin:4px 0 20px"></div>
<div style="margin-bottom:12px">
<div class="card-title">Quick Actions</div>
</div>
<div class="grid-3">
<a href="<?= SITE_URL ?>/embed.php" class="action-card">
<div class="action-card-icon">⬇</div>
<div class="action-card-title">Hide a Message</div>
<div class="action-card-sub">Embed encrypted payload inside image, audio, or video</div>
</a>
<a href="<?= SITE_URL ?>/extract.php" class="action-card">
<div class="action-card-icon">⬆</div>
<div class="action-card-title">Reveal a Message</div>
<div class="action-card-sub">Extract and decrypt a hidden payload from a stego file</div>
</a>
<a href="<?= SITE_URL ?>/chains.php" class="action-card">
<div class="action-card-icon">⛓</div>
<div class="action-card-title">Build a Chain</div>
<div class="action-card-sub">Design a custom encryption pipeline from 34+ algorithms</div>
</a>
</div>
<?php require_once 'php/includes/footer.php'; ?>