<?php
$pageTitle='Dashboard';
$activePage='dashboard';
require_once 'php/includes/header.php';
require_once 'php/includes/stego_handler.php';
$isGuest = $auth->isGuest();
if (!$isGuest) {
$db=$db??Database::getInstance();
$handler=new StegoHandler();
$ops=$handler->getUserOperations($user['user_id'],5);
$stats=[
'ops'=>$db->fetchOne("SELECT COUNT(*) as c FROM stego_operations WHERE user_id=?",[$user['user_id']])['c']??0,
'chains'=>$db->fetchOne("SELECT COUNT(*) as c FROM encryption_chains WHERE user_id=?",[$user['user_id']])['c']??0,
'shares'=>$db->fetchOne("SELECT COUNT(*) as c FROM shared_files sf JOIN stego_operations o ON sf.op_id=o.op_id WHERE o.user_id=? AND sf.is_active=1",[$user['user_id']])['c']??0,
];
$logs=$db->fetchAll("SELECT * FROM operation_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 5",[$user['user_id']]);
} else {
$ops=[]; $stats=['ops'=>0,'chains'=>0,'shares'=>0]; $logs=[];
}
?>
<?php if($isGuest): ?>
<div class="guest-banner">
<div class="guest-banner-title">👁 GUEST MODE</div>
<div class="guest-banner-sub">Your work is stored locally. Create a free account to save everything permanently.</div>
<button class="btn btn-primary btn-sm" onclick="document.getElementById('upgrade-modal').style.display='flex'" style="margin-top:4px">⬡ Create Free Account</button>
</div>
<?php endif; ?>
<div class="page-header">
<div>
<div class="page-title">Welcome<?= $isGuest ? '' : ', '.htmlspecialchars($user['username']) ?></div>
<div class="page-sub">// STEGANOGRAPHIC PROTECTION SYSTEM v1.0</div>
</div>
<?php if(!$isGuest): ?>
<a href="<?= SITE_URL ?>/embed.php" class="btn btn-primary btn-lg">⬇ New Embed</a>
<?php endif; ?>
</div>
<div class="grid-4" style="margin-bottom:16px">
<div class="stat-card green"><div class="stat-value green"><?= $stats['ops'] ?></div><div class="stat-label">Total Operations</div></div>
<div class="stat-card purple"><div class="stat-value purple"><?= $stats['chains'] ?></div><div class="stat-label">Saved Chains</div></div>
<div class="stat-card blue"><div class="stat-value blue"><?= $stats['shares'] ?></div><div class="stat-label">Active Shares</div></div>
<div class="stat-card orange"><div class="stat-value orange"><?= $isGuest ? '0' : round($user['storage_used']/1024/1024,1) ?><span style="font-size:14px">MB</span></div><div class="stat-label">Storage Used</div></div>
</div>
<div class="grid-2">
<div class="card">
<div class="card-title">Recent Operations</div>
<?php if(empty($ops)): ?>
<p style="color:var(--text-muted);font-family:'Space Mono',monospace;font-size:11px;text-align:center;padding:20px">No operations yet. <a href="<?= SITE_URL ?>/embed.php" style="color:var(--accent)">Start your first embed →</a></p>
<?php else: ?>
<div style="overflow-x:auto"><table class="data-table">
<thead><tr><th>File</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
<tbody>
<?php foreach($ops as $op): ?>
<tr>
<td style="font-family:'JetBrains Mono',monospace;font-size:11px"><?= htmlspecialchars(substr($op['original_filename'],0,18)) ?></td>
<td><span class="badge badge-<?= $op['file_type'] ?>"><?= strtoupper($op['file_type']) ?></span></td>
<td><span class="badge badge-<?= $op['status']==='completed'?'success':($op['status']==='failed'?'failed':'pending') ?>"><?= strtoupper($op['status']) ?></span></td>
<td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)"><?= date('d M',strtotime($op['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
</div>
<div class="card">
<div class="card-title">Recent Activity</div>
<?php if(!empty($logs)): foreach($logs as $log): ?>
<div class="activity-item">
<div class="activity-dot" style="background:<?= $log['status']==='success'?'var(--accent)':'var(--danger)' ?>"></div>
<div class="activity-text"><strong><?= htmlspecialchars($log['action_type']) ?></strong> — <?= htmlspecialchars(substr($log['action_detail'],0,38)) ?></div>
<div class="activity-time"><?= date('H:i',strtotime($log['created_at'])) ?></div>
</div>
<?php endforeach; else: ?>
<p style="color:var(--text-muted);font-family:'Space Mono',monospace;font-size:11px;text-align:center;padding:20px">No activity yet</p>
<?php endif; ?>
</div>
</div>
<div class="divider"></div>
<div class="card-title">Quick Actions</div>
<div class="grid-3" style="margin-top:10px">
<a href="<?= SITE_URL ?>/embed.php" class="card" style="cursor:pointer;text-decoration:none;transition:border-color .2s;margin-bottom:0" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
<div style="font-size:26px;margin-bottom:10px">⬇</div>
<div style="font-size:14px;font-weight:700;margin-bottom:5px;color:var(--text)">Hide a Message</div>
<div style="font-size:12px;color:var(--text-dim)">Embed encrypted payload inside image, audio, or video</div>
</a>
<a href="<?= SITE_URL ?>/extract.php" class="card" style="cursor:pointer;text-decoration:none;transition:border-color .2s;margin-bottom:0" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
<div style="font-size:26px;margin-bottom:10px">⬆</div>
<div style="font-size:14px;font-weight:700;margin-bottom:5px;color:var(--text)">Reveal a Message</div>
<div style="font-size:12px;color:var(--text-dim)">Extract and decrypt a hidden payload from a stego file</div>
</a>
<a href="<?= SITE_URL ?>/chains.php" class="card" style="cursor:pointer;text-decoration:none;transition:border-color .2s;margin-bottom:0" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
<div style="font-size:26px;margin-bottom:10px">⛓</div>
<div style="font-size:14px;font-weight:700;margin-bottom:5px;color:var(--text)">Build a Chain</div>
<div style="font-size:12px;color:var(--text-dim)">Design a custom encryption pipeline from 34+ algorithms</div>
</a>
</div>
<?php if($isGuest): ?>
<div id="upgrade-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
<div class="modal-card animate-in">
<div class="modal-title">Create Your Account</div>
<div class="modal-sub">Just a username and password. Email is optional. Your local data will carry over.</div>
<div id="ug-error" class="field-error-msg" style="display:none"></div>
<div class="input-group">
<label class="input-label">Username</label>
<div class="input-wrap">
<span class="input-icon">◎</span>
<input type="text" class="input has-icon" id="ug-username" placeholder="Choose a username...">
<span class="input-status" id="ug-status"></span>
</div>
<div class="field-hint" id="ug-hint"></div>
</div>
<div class="input-group">
<label class="input-label">Password</label>
<div class="input-wrap">
<span class="input-icon">⬡</span>
<input type="password" class="input has-icon" id="ug-password" placeholder="Your password">
<button type="button" class="input-eye" onclick="togglePass('ug-password',this)">◉</button>
</div>
</div>
<div class="input-group" style="margin-bottom:0">
<label class="input-label">Email <span class="optional-tag">(optional)</span></label>
<div class="input-wrap">
<span class="input-icon" style="font-size:12px;font-family:'Space Mono',monospace">@</span>
<input type="email" class="input has-icon" id="ug-email" placeholder="your@email.com (optional)">
</div>
</div>
<div class="modal-actions">
<button class="btn btn-primary btn-full" id="ug-btn" onclick="doUpgrade()">
<span class="btn-text">⬡ Create Account</span>
<span class="btn-loader" style="display:none">◌</span>
</button>
<button class="btn btn-ghost" onclick="document.getElementById('upgrade-modal').style.display='none'">Later</button>
</div>
</div>
</div>
<div class="glitch-overlay" id="glitch-overlay"></div>
<script>
initFieldCheck('ug-username','ug-status','ug-hint','username','<?= SITE_URL ?>/php/api/check_availability.php');
function doUpgrade() {
const btn=document.getElementById('ug-btn');
const errEl=document.getElementById('ug-error');
const username=document.getElementById('ug-username').value.trim();
const password=document.getElementById('ug-password').value;
const email=document.getElementById('ug-email').value.trim();
if(!username||!password){showInlineError(errEl,'Fill in username and password');return;}
setLoading(btn,true);
const guestData=localStorage.getItem('stego_guest_ops')||'[]';
fetch('<?= SITE_URL ?>/php/api/auth_ajax.php',{method:'POST',body:new URLSearchParams({action:'guest_upgrade',username,password,email,guest_data:guestData})})
.then(r=>r.json()).then(d=>{
if(d.success){localStorage.removeItem('stego_guest_ops');triggerGlitchTransition(d.redirect,true);}
else{setLoading(btn,false);showInlineError(errEl,d.message);if(d.field)document.getElementById('ug-'+d.field)?.classList.add('input-error');}
}).catch(()=>setLoading(btn,false));
}
</script>
<?php endif; ?>
<?php require_once 'php/includes/footer.php'; ?>
