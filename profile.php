<?php
$pageTitle='Profile';
$activePage='profile';
require_once 'php/includes/header.php';
$isGuest=$auth->isGuest();
$keyLen=strlen($user['steg_key']??'');
$hasKey=!empty($user['steg_key']);
?>
<div class="page-header">
<div>
<div class="page-title">Profile &amp; Settings</div>
<div class="page-sub">// IDENTITY &middot; SECURITY &middot; STEG KEY</div>
</div>
</div>
<?php if($isGuest): ?>
<div class="guest-banner" style="margin-bottom:18px">
<div class="guest-banner-title">&#11041; GUEST MODE</div>
<div class="guest-banner-sub">Create a free account to get your personal steg key and profile settings.</div>
<button class="btn btn-primary btn-sm" onclick="document.getElementById('upgrade-modal').style.display='flex'" style="margin-top:6px">&#11041; Create Account</button>
</div>
<?php else: ?>
<div id="profile-alert" class="field-error-msg" style="display:none"></div>
<div id="profile-success" class="alert alert-success" style="display:none"></div>
<div class="profile-grid">
<div class="profile-left">
<div class="card no-margin">
<div class="card-title">Identity</div>
<div class="profile-identity-row">
<div class="profile-avatar-sm"><?= strtoupper(substr($user['username'],0,2)) ?></div>
<div class="profile-identity-info">
<div class="profile-identity-name"><?= htmlspecialchars($user['username']) ?></div>
<div class="profile-identity-email"><?= htmlspecialchars($user['email']??'No email set') ?></div>
<span class="badge badge-<?= $user['role']==='admin'?'video':'success' ?>" style="margin-top:5px;display:inline-block"><?= strtoupper($user['role']) ?></span>
</div>
</div>
<div class="profile-stat-row">
<div class="profile-stat">
<div class="profile-stat-val"><?= date('d M Y',strtotime($user['created_at'])) ?></div>
<div class="profile-stat-lbl">Joined</div>
</div>
<div class="profile-stat">
<div class="profile-stat-val"><?= $user['last_login']?date('d M',strtotime($user['last_login'])):'Never' ?></div>
<div class="profile-stat-lbl">Last Login</div>
</div>
<div class="profile-stat">
<div class="profile-stat-val"><?= round(($user['storage_used']??0)/1024/1024,1) ?>MB</div>
<div class="profile-stat-lbl">Storage</div>
</div>
</div>
</div>
<div class="card steg-key-card no-margin">
<div class="card-title steg-key-animated-label">&#9670; Your Steg Key</div>
<div class="steg-key-badge" onclick="toggleStegKey()" id="steg-key-badge">
<div class="steg-key-label" id="steg-key-label"><?= $hasKey ? 'Click to reveal &amp; edit' : 'No key yet &mdash; generate one below' ?></div>
<div class="steg-key-progress"><div class="steg-key-progress-fill" id="steg-key-progress-fill"></div></div>
<div id="steg-key-blocks" data-len="<?= $keyLen ?>" class="steg-key-blocks"<?= !$hasKey?' style="display:none"':''?>></div>
<div id="steg-key-edit-area" class="steg-key-edit-area" contenteditable="false" spellcheck="false" data-key="<?= htmlspecialchars($user['steg_key']??'') ?>" onclick="event.stopPropagation()"></div>
</div>
<div class="steg-key-size">
<label>Generated key length</label>
<input type="range" min="8" max="64" value="32" id="key-len-slider" oninput="updateKeyLenLabel(this.value)">
<span class="steg-key-size-val" id="key-len-val">32</span>
</div>
<div style="font-size:12px;color:var(--text-dim);margin-bottom:14px;line-height:1.6">Share with trusted contacts. They use it to embed messages for you.</div>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<button class="btn btn-ghost btn-sm" id="reveal-key-btn" onclick="toggleStegKey()">&#128065; Reveal</button>
<button class="btn btn-ghost btn-sm" onclick="copyStegKey()">&#11041; Copy</button>
<button class="btn btn-primary btn-sm" id="save-key-btn" onclick="saveCustomKey()">
<span class="btn-text">&#10003; Save Key</span>
<span class="btn-loader" style="display:none">&#9676;</span>
</button>
<button class="btn btn-danger btn-sm" id="regen-btn-top" onclick="confirmRegen()">&#8635; Generate New</button>
</div>
<div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">&#9888; Changing the key invalidates existing embeds made with the old key.</div>
</div>
<div class="card no-margin" style="border-color:rgba(255,77,109,.2)">
<div class="card-title" style="color:var(--danger)">Danger Zone</div>
<div style="font-size:12px;color:var(--text-dim);margin-bottom:14px;line-height:1.5">These actions are permanent and cannot be undone.</div>
<button class="btn btn-danger" onclick="document.getElementById('delete-account-modal').style.display='flex'">&#10005; Delete My Account</button>
</div>
</div>
<div class="profile-right">
<div class="card no-margin">
<div class="card-title">Update Username</div>
<div class="input-group" style="margin-bottom:6px">
<label class="input-label">New Username</label>
<div class="input-wrap">
<span class="input-icon">&#9678;</span>
<input type="text" class="input has-icon" id="new-username" placeholder="New username..." value="<?= htmlspecialchars($user['username']) ?>" minlength="3" maxlength="50" autocomplete="off">
<span class="input-status" id="uname-status"></span>
</div>
<div class="field-hint" id="uname-hint"></div>
</div>
<button class="btn btn-primary btn-sm" id="save-username-btn" onclick="saveProfile('username')">
<span class="btn-text">Save Username</span>
<span class="btn-loader" style="display:none">&#9676;</span>
</button>
</div>
<div class="card no-margin">
<div class="card-title">Update Email <span class="optional-tag">(optional)</span></div>
<div class="input-group" style="margin-bottom:6px">
<label class="input-label">Email Address</label>
<div class="input-wrap">
<span class="input-icon" style="font-size:12px;font-family:'Space Mono',monospace">@</span>
<input type="email" class="input has-icon" id="new-email" placeholder="your@email.com" value="<?= htmlspecialchars($user['email']??'') ?>" autocomplete="off">
<span class="input-status" id="email-status"></span>
</div>
<div class="field-hint" id="email-hint">Not required &mdash; useful for account recovery</div>
</div>
<button class="btn btn-primary btn-sm" id="save-email-btn" onclick="saveProfile('email')">
<span class="btn-text">Save Email</span>
<span class="btn-loader" style="display:none">&#9676;</span>
</button>
</div>
<div class="card no-margin">
<div class="card-title">Change Password</div>
<div class="input-group">
<label class="input-label">Current Password</label>
<div class="input-wrap">
<span class="input-icon">&#11041;</span>
<input type="password" class="input has-icon" id="current-pw" placeholder="Your current password">
<button type="button" class="input-eye" onclick="togglePass('current-pw',this)">&#9673;</button>
</div>
</div>
<div class="input-group">
<label class="input-label">New Password</label>
<div class="input-wrap">
<span class="input-icon">&#11041;</span>
<input type="password" class="input has-icon" id="new-pw" placeholder="New password">
<button type="button" class="input-eye" onclick="togglePass('new-pw',this)">&#9673;</button>
</div>
<div class="pw-strength-wrap" id="pw-wrap" style="display:none">
<div class="pw-bar-track"><div class="pw-bar-fill" id="pw-bar"></div></div>
<div class="pw-meta">
<span class="pw-label" id="pw-label">&mdash;</span>
<div class="pw-checks">
<span class="pw-check" id="pc-len">8+</span>
<span class="pw-check" id="pc-upper">A-Z</span>
<span class="pw-check" id="pc-num">0-9</span>
<span class="pw-check" id="pc-sym">!@#</span>
</div>
</div>
</div>
</div>
<div class="input-group" style="margin-bottom:6px">
<label class="input-label">Confirm New Password</label>
<div class="input-wrap">
<span class="input-icon">&#11041;</span>
<input type="password" class="input has-icon" id="confirm-pw" placeholder="Repeat new password">
<button type="button" class="input-eye" onclick="togglePass('confirm-pw',this)">&#9673;</button>
</div>
</div>
<br>
<button class="btn btn-primary btn-sm" id="save-pw-btn" onclick="saveProfile('password')">
<span class="btn-text">Change Password</span>
<span class="btn-loader" style="display:none">&#9676;</span>
</button>
</div>
</div>
</div>
<div id="regen-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
<div class="modal-card animate-in" style="max-width:380px;text-align:center">
<div style="font-size:30px;margin-bottom:10px">&#9888;</div>
<div class="modal-title" style="color:var(--warning)">Regenerate Steg Key?</div>
<div class="modal-sub">Any embeds made with the current key will become inaccessible. This cannot be undone.</div>
<div style="display:flex;gap:10px;justify-content:center;margin-top:16px">
<button class="btn btn-danger" id="regen-confirm-btn" onclick="regenStegKey()">
<span class="btn-text">&#8635; Generate</span>
<span class="btn-loader" style="display:none">&#9676;</span>
</button>
<button class="btn btn-ghost" onclick="document.getElementById('regen-modal').style.display='none'">Cancel</button>
</div>
</div>
</div>
<div id="delete-account-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
<div class="modal-card animate-in" style="max-width:380px;text-align:center">
<div style="font-size:30px;margin-bottom:10px">&#9888;</div>
<div class="modal-title" style="color:var(--danger)">Delete Account?</div>
<div class="modal-sub">All your files, chains, and data will be permanently deleted. This cannot be undone.</div>
<div style="display:flex;gap:10px;justify-content:center;margin-top:16px">
<button class="btn btn-danger" id="delete-account-btn" onclick="executeDeleteAccount()">
<span class="btn-text">&#10005; Delete Forever</span>
<span class="btn-loader" style="display:none">&#9676;</span>
</button>
<button class="btn btn-ghost" onclick="document.getElementById('delete-account-modal').style.display='none'">Cancel</button>
</div>
</div>
</div>
<?php endif; ?>
<script>
const PROFILE_URL='<?= SITE_URL ?>/php/api/profile_update.php';
const CHECK_URL='<?= SITE_URL ?>/php/api/check_availability.php';
<?php if(!$isGuest): ?>
document.addEventListener('DOMContentLoaded',function(){
initFieldCheck('new-username','uname-status','uname-hint','username',CHECK_URL);
initEmailHint('new-email','email-status','email-hint',CHECK_URL);
initPasswordStrength('new-pw','pw-wrap','pw-bar','pw-label',['pc-len','pc-upper','pc-num','pc-sym']);
const blocksEl=document.getElementById('steg-key-blocks');
if(blocksEl&&blocksEl.dataset.len&&parseInt(blocksEl.dataset.len)>0){
animateKeyBlocks('<?= addslashes($user['steg_key']??'') ?>');
}
});
function updateKeyLenLabel(v){document.getElementById('key-len-val').textContent=v;}
let keyVisible=false;
function toggleStegKey(){
const editArea=document.getElementById('steg-key-edit-area');
const blocksEl=document.getElementById('steg-key-blocks');
const btn=document.getElementById('reveal-key-btn');
const lbl=document.getElementById('steg-key-label');
const key=editArea?editArea.dataset.key||'':'';
if(!key){showToast('No key set yet \u2014 generate or save one first');return;}
keyVisible=!keyVisible;
if(keyVisible){
if(blocksEl)blocksEl.style.display='none';
editArea.style.display='block';
editArea.contentEditable='true';
editArea.textContent='';
if(btn)btn.innerHTML='&#128584; Hide';
if(lbl)lbl.textContent='Your Key \u2014 click to edit';
revealKeyTypewriter(editArea,key);
}else{
editArea.contentEditable='false';
editArea.style.display='none';
editArea.textContent='';
if(blocksEl)blocksEl.style.display='block';
if(btn)btn.innerHTML='&#128065; Reveal';
if(lbl)lbl.textContent='Click to reveal &amp; edit';
}
}
function revealKeyTypewriter(el,key){
el.textContent='';
let i=0;
const chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
function scramble(t,p){let s='';for(let j=0;j<t.length;j++)s+=j<p?t[j]:chars[Math.floor(Math.random()*chars.length)];return s;}
function step(){if(i>key.length)return;el.textContent=scramble(key,i);if(i<key.length){i++;setTimeout(step,16+Math.random()*10);}else el.textContent=key;}
step();
}
function copyStegKey(){
const editArea=document.getElementById('steg-key-edit-area');
const key=(editArea&&editArea.dataset.key)||'';
if(!key){showToast('No key to copy');return;}
navigator.clipboard.writeText(key).then(()=>showToast('Steg key copied!'));
}
function saveCustomKey(){
const editArea=document.getElementById('steg-key-edit-area');
const rawKey=(editArea&&keyVisible)?editArea.textContent.trim():(editArea&&editArea.dataset.key)||'';
const key=rawKey;
if(!key){showInlineError(document.getElementById('profile-alert'),'Enter a key to save');return;}
const btn=document.getElementById('save-key-btn');
setLoading(btn,true);
fetch(PROFILE_URL,{method:'POST',body:new URLSearchParams({action:'save_custom_steg_key',steg_key:key})})
.then(r=>r.json()).then(d=>{
setLoading(btn,false);
if(d.success){
const lbl=document.getElementById('steg-key-label');
const blocksEl=document.getElementById('steg-key-blocks');
if(editArea){editArea.dataset.key=key;editArea.textContent=key;}
if(lbl)lbl.textContent='Click to reveal &amp; edit';
keyVisible=false;
if(editArea){editArea.contentEditable='false';editArea.style.display='none';}
if(blocksEl){blocksEl.style.display='block';blocksEl.dataset.len=key.length;}
animateKeyBlocks(key);
showSuccessMsg('Steg key saved!');
showToast('Key saved!');
}else{showErrorMsg(d.message);}
}).catch(()=>{setLoading(btn,false);showErrorMsg('Failed to save key');});
}
function confirmRegen(){document.getElementById('regen-modal').style.display='flex';}
function regenStegKey(){
const btn=document.getElementById('regen-confirm-btn');
const keyLen=document.getElementById('key-len-slider')?.value||32;
setLoading(btn,true);
fetch(PROFILE_URL,{method:'POST',body:new URLSearchParams({action:'regen_steg_key',key_length:keyLen})})
.then(r=>r.json()).then(d=>{
setLoading(btn,false);
if(d.success){
document.getElementById('regen-modal').style.display='none';
const editArea=document.getElementById('steg-key-edit-area');
const blocksEl=document.getElementById('steg-key-blocks');
const lbl=document.getElementById('steg-key-label');
const revBtn=document.getElementById('reveal-key-btn');
const newKey=d.steg_key||'';
if(editArea){editArea.dataset.key=newKey;editArea.contentEditable='false';editArea.style.display='none';editArea.textContent='';}
if(blocksEl){blocksEl.dataset.len=newKey.length;blocksEl.style.display='block';}
if(lbl)lbl.textContent='Key updated \u2014 click to reveal';
if(revBtn)revBtn.innerHTML='&#128065; Reveal';
keyVisible=false;
animateKeyBlocks(newKey);
showSuccessMsg('New steg key generated!');
showToast('New steg key generated');
}else{showErrorMsg(d.message);}
}).catch(()=>{setLoading(btn,false);});
}
function saveProfile(type){
const errEl=document.getElementById('profile-alert');
clearError(errEl);
let btn,body={action:type};
if(type==='username'){
btn=document.getElementById('save-username-btn');
body.username=document.getElementById('new-username').value.trim();
if(!body.username){showInlineError(errEl,'Enter a username');return;}
}else if(type==='email'){
btn=document.getElementById('save-email-btn');
body.email=document.getElementById('new-email').value.trim();
}else if(type==='password'){
btn=document.getElementById('save-pw-btn');
body.current_password=document.getElementById('current-pw').value;
body.new_password=document.getElementById('new-pw').value;
body.confirm_password=document.getElementById('confirm-pw').value;
if(!body.current_password){showInlineError(errEl,'Enter your current password');return;}
if(!body.new_password){showInlineError(errEl,'Enter a new password');return;}
if(body.new_password!==body.confirm_password){showInlineError(errEl,'Passwords do not match');return;}
}
setLoading(btn,true);
fetch(PROFILE_URL,{method:'POST',body:new URLSearchParams(body)})
.then(r=>r.json()).then(d=>{
setLoading(btn,false);
if(d.success){showSuccessMsg(d.message||'Saved!');showToast(d.message||'Saved!');}
else{showErrorMsg(d.message);}
}).catch(()=>setLoading(btn,false));
}
function showSuccessMsg(msg){const el=document.getElementById('profile-success');el.textContent=msg;el.style.display='block';document.getElementById('profile-alert').style.display='none';setTimeout(()=>el.style.display='none',4000);el.scrollIntoView({behavior:'smooth',block:'nearest'});}
function showErrorMsg(msg){showInlineError(document.getElementById('profile-alert'),msg);document.getElementById('profile-alert').scrollIntoView({behavior:'smooth',block:'nearest'});}
function executeDeleteAccount(){
const btn=document.getElementById('delete-account-btn');
setLoading(btn,true);
fetch(PROFILE_URL,{method:'POST',body:new URLSearchParams({action:'delete_account'})})
.then(r=>r.json()).then(d=>{
if(d.success)window.location.href='<?= SITE_URL ?>/logout.php';
else{setLoading(btn,false);showErrorMsg(d.message);}
}).catch(()=>setLoading(btn,false));
}
<?php endif; ?>
</script>
<?php require_once 'php/includes/footer.php'; ?>