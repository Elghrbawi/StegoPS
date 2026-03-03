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
<div class="profile-avatar-big"><?= strtoupper(substr($user['username'],0,2)) ?></div>
<div style="text-align:center;margin-bottom:20px">
<div style="font-size:22px;font-weight:800;color:var(--text);margin-bottom:4px"><?= htmlspecialchars($user['username']) ?></div>
<div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)"><?= htmlspecialchars($user['email']??'No email set') ?></div>
<span class="badge badge-<?= $user['role']==='admin'?'video':'success' ?>" style="margin-top:8px;display:inline-block"><?= strtoupper($user['role']) ?></span>
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
<div class="steg-key-badge" onclick="toggleStegKey()" style="cursor:pointer" title="Click to reveal/hide key">
<div class="steg-key-icon">&#11041;</div>
<div class="steg-key-info" style="flex:1;min-width:0">
<div class="steg-key-label" id="steg-key-label"><?= $hasKey ? 'Click to reveal' : 'No key yet &mdash; generate one below' ?></div>
<div class="steg-key-progress"><div class="steg-key-progress-fill" id="steg-key-progress-fill"></div></div>
<div id="steg-key-blocks" data-len="<?= $keyLen ?>" class="steg-key-blocks"<?= !$hasKey?' style="display:none"':''?>></div>
<div id="steg-key-raw" class="steg-key-reveal-text" style="display:none" data-key="<?= htmlspecialchars($user['steg_key']??'') ?>"></div>
</div>
</div>
<div class="input-group" style="margin-bottom:10px;margin-top:8px">
<label class="input-label">Custom Key <span class="optional-tag">(paste or type your own)</span></label>
<div class="input-wrap">
<span class="input-icon">&#11041;</span>
<input type="password" class="input has-icon" id="custom-steg-key-input" placeholder="Paste or type a key..." style="font-family:'JetBrains Mono',monospace;font-size:11px" value="<?= htmlspecialchars($user['steg_key']??'') ?>">
<button type="button" class="input-eye" onclick="togglePass('custom-steg-key-input',this)">&#9673;</button>
</div>
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
(function initProfileChecks(){
const usernameInput=document.getElementById('new-username');
const usernameStatus=document.getElementById('uname-status');
const usernameHint=document.getElementById('uname-hint');
const emailInput=document.getElementById('new-email');
const emailStatus=document.getElementById('email-status');
const emailHint=document.getElementById('email-hint');
const re=/^[^\s@]+@[^\s@]+\.[^\s@]+$/;
let uTimer=null,eTimer=null,eBinaryTimer=null;
if(usernameInput){
usernameInput.addEventListener('input',function(){
clearTimeout(uTimer);
const v=this.value.trim();
usernameStatus.textContent='';usernameStatus.className='input-status';
usernameHint.textContent='';usernameHint.className='field-hint';
this.style.borderColor='';
if(!v)return;
if(v.length<3){usernameStatus.textContent='\u2717';usernameStatus.className='input-status err';usernameHint.textContent='Too short (min 3)';usernameHint.className='field-hint err';this.style.borderColor='var(--danger)';return;}
if(!/^[a-zA-Z0-9_.\\-]+$/.test(v)){usernameStatus.textContent='\u2717';usernameStatus.className='input-status err';usernameHint.textContent='Only letters, numbers, _ . -';usernameHint.className='field-hint err';this.style.borderColor='var(--danger)';return;}
usernameStatus.textContent='\u25CC';usernameStatus.className='input-status checking';
uTimer=setTimeout(async()=>{
try{
const fd=new FormData();fd.append('type','username');fd.append('value',v);
const r=await fetch(CHECK_URL,{method:'POST',body:fd});
const d=await r.json();
usernameStatus.textContent=d.available?'\u2713':'\u2717';
usernameStatus.className='input-status '+(d.available?'ok':'err');
usernameHint.textContent=d.message||'';
usernameHint.className='field-hint '+(d.available?'ok':'err');
usernameInput.style.borderColor=d.available?'var(--accent)':'var(--danger)';
}catch(e){usernameStatus.textContent='';usernameStatus.className='input-status';}
},500);
});
usernameInput.addEventListener('blur',function(){if(!this.value.trim()){this.style.borderColor='';usernameStatus.textContent='';usernameStatus.className='input-status';}});
}
if(emailInput){
emailInput.addEventListener('input',function(){
clearTimeout(eTimer);clearInterval(eBinaryTimer);
const v=this.value.trim();
emailStatus.textContent='';emailStatus.className='input-status';
emailHint.textContent='Not required \u2014 useful for account recovery';emailHint.className='field-hint';emailHint.style.color='';
this.style.borderColor='';
if(!v)return;
const valid=re.test(v);
emailStatus.textContent=valid?'\u2713':'?';
emailStatus.className='input-status '+(valid?'ok':'checking');
if(!valid){
let f=0;
eBinaryTimer=setInterval(()=>{
const bits=Array.from({length:14},()=>Math.round(Math.random())).join('');
emailHint.textContent=bits+' INVALID FORMAT';
emailHint.className='field-hint err';emailHint.style.color='';
f++;
if(f>12){clearInterval(eBinaryTimer);emailHint.textContent='Unusual format \u2014 double check?';}
},60);
this.style.borderColor='var(--danger)';
return;
}
emailHint.textContent='Looks good!';emailHint.className='field-hint ok';
emailStatus.textContent='\u25CC';emailStatus.className='input-status checking';
eTimer=setTimeout(async()=>{
try{
const fd=new FormData();fd.append('type','email');fd.append('value',v);
const r=await fetch(CHECK_URL,{method:'POST',body:fd});
const d=await r.json();
emailStatus.textContent=d.available?'\u2713':'\u2717';
emailStatus.className='input-status '+(d.available?'ok':'err');
emailHint.textContent=d.message||'Looks good!';
emailHint.className='field-hint '+(d.available?'ok':'err');emailHint.style.color='';
emailInput.style.borderColor=d.available?'var(--accent)':'var(--danger)';
}catch(e){emailStatus.textContent='\u2713';emailStatus.className='input-status ok';}
},600);
});
}
})();
initPasswordStrength('new-pw','pw-wrap','pw-bar','pw-label',['pc-len','pc-upper','pc-num','pc-sym']);
function updateKeyLenLabel(v){document.getElementById('key-len-val').textContent=v;}
let keyVisible=false;
function toggleStegKey(){
const rawEl=document.getElementById('steg-key-raw');
const blocksEl=document.getElementById('steg-key-blocks');
const btn=document.getElementById('reveal-key-btn');
const lbl=document.getElementById('steg-key-label');
const key=rawEl?rawEl.dataset.key||'':'';
if(!key){showToast('No key set yet \u2014 generate or save one first');return;}
keyVisible=!keyVisible;
if(keyVisible){
if(blocksEl)blocksEl.style.display='none';
rawEl.style.display='block';rawEl.textContent='';
if(btn)btn.innerHTML='&#128584; Hide';
if(lbl)lbl.textContent='Your Key (visible)';
revealKeyTypewriter(rawEl,key);
}else{
rawEl.style.display='none';rawEl.textContent='';
if(blocksEl)blocksEl.style.display='block';
if(btn)btn.innerHTML='&#128065; Reveal';
if(lbl)lbl.textContent='Click to reveal';
}
}
function revealKeyTypewriter(el,key){
el.textContent='';
let i=0;
const chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
function scramble(t,p){let s='';for(let j=0;j<t.length;j++)s+=j<p?t[j]:chars[Math.floor(Math.random()*chars.length)];return s;}
function step(){if(i>key.length)return;el.textContent=scramble(key,i);if(i<key.length){i++;setTimeout(step,18+Math.random()*12);}else el.textContent=key;}
step();
}
function copyStegKey(){
const rawEl=document.getElementById('steg-key-raw');
const customInp=document.getElementById('custom-steg-key-input');
const key=(rawEl&&rawEl.dataset.key)||(customInp&&customInp.value)||'';
if(!key){showToast('No key to copy');return;}
navigator.clipboard.writeText(key).then(()=>showToast('Steg key copied!'));
}
function saveCustomKey(){
const inp=document.getElementById('custom-steg-key-input');
const key=inp?inp.value.trim():'';
if(!key){showInlineError(document.getElementById('profile-alert'),'Enter a key to save');return;}
const btn=document.getElementById('save-key-btn');
setLoading(btn,true);
fetch(PROFILE_URL,{method:'POST',body:new URLSearchParams({action:'save_custom_steg_key',steg_key:key})})
.then(r=>r.json()).then(d=>{
setLoading(btn,false);
if(d.success){
const rawEl=document.getElementById('steg-key-raw');
const lbl=document.getElementById('steg-key-label');
const blocksEl=document.getElementById('steg-key-blocks');
if(rawEl){rawEl.dataset.key=key;rawEl.style.display='none';rawEl.textContent='';}
if(lbl)lbl.textContent='Click to reveal';
keyVisible=false;
if(blocksEl){blocksEl.style.display='block';blocksEl.dataset.len=key.length;}
if(btn)btn.querySelector('.btn-text').textContent='\u2713 Save Key';
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
const rawEl=document.getElementById('steg-key-raw');
const blocksEl=document.getElementById('steg-key-blocks');
const lbl=document.getElementById('steg-key-label');
const revBtn=document.getElementById('reveal-key-btn');
const customInp=document.getElementById('custom-steg-key-input');
const newKey=d.steg_key||'';
if(rawEl){rawEl.dataset.key=newKey;rawEl.style.display='none';rawEl.textContent='';}
if(customInp)customInp.value=newKey;
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
function animateKeyBlocks(key){
const container=document.getElementById('steg-key-blocks');
const progressFill=document.getElementById('steg-key-progress-fill');
if(!container)return;
container.style.display='block';
container.innerHTML='';
if(progressFill)progressFill.style.width='0%';
const blocks=[];
for(let i=0;i<key.length;i++){
const span=document.createElement('span');
span.className='skb';span.textContent='\u2588';
container.appendChild(span);blocks.push(span);
if((i+1)%8===0&&i<key.length-1){const sp=document.createElement('span');sp.innerHTML=' ';container.appendChild(sp);}
}
let lit=0;
function lightNext(){
if(lit>=blocks.length){if(progressFill)progressFill.style.width='100%';return;}
blocks[lit].classList.add('lit');lit++;
if(progressFill)progressFill.style.width=Math.round(lit/blocks.length*100)+'%';
setTimeout(lightNext,35+Math.random()*25);
}
setTimeout(lightNext,50);
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
function showSuccessMsg(msg){
const el=document.getElementById('profile-success');
el.textContent=msg;el.style.display='block';
document.getElementById('profile-alert').style.display='none';
setTimeout(()=>el.style.display='none',4000);
el.scrollIntoView({behavior:'smooth',block:'nearest'});
}
function showErrorMsg(msg){
showInlineError(document.getElementById('profile-alert'),msg);
document.getElementById('profile-alert').scrollIntoView({behavior:'smooth',block:'nearest'});
}
function executeDeleteAccount(){
const btn=document.getElementById('delete-account-btn');
setLoading(btn,true);
fetch(PROFILE_URL,{method:'POST',body:new URLSearchParams({action:'delete_account'})})
.then(r=>r.json()).then(d=>{
if(d.success)window.location.href='<?= SITE_URL ?>/logout.php';
else{setLoading(btn,false);showErrorMsg(d.message);}
}).catch(()=>setLoading(btn,false));
}
document.addEventListener('DOMContentLoaded',function(){
const blocksEl=document.getElementById('steg-key-blocks');
if(blocksEl&&blocksEl.dataset.len&&parseInt(blocksEl.dataset.len)>0){
animateKeyBlocks('<?= addslashes($user['steg_key']??'') ?>');
}
});
<?php endif; ?>
</script>
<?php require_once 'php/includes/footer.php'; ?>