<?php
require_once 'php/config/database.php';
require_once 'php/includes/auth.php';
$auth = new Auth();
if ($auth->isLoggedIn()) { header('Location: '.SITE_URL.'/dashboard.php'); exit; }
$fromLogin = isset($_GET['from']) && $_GET['from']==='login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StegoPS — Create Account</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/frontend/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="auth-body">
<canvas id="bg-canvas"></canvas>
<div class="glitch-overlay" id="glitch-overlay"></div>
<div class="auth-scene">
<div class="auth-brand-panel">
<div class="brand-panel-inner">
<div class="brand-logo-big">Steg<span class="logo-eye">👁</span>PS</div>
<div class="brand-tagline">JOIN THE NETWORK</div>
<div class="brand-steps">
<div class="brand-step active"><div class="step-num-circle">1</div><span>Create Identity</span></div>
<div class="brand-step"><div class="step-num-circle">2</div><span>Build Your Chain</span></div>
<div class="brand-step"><div class="step-num-circle">3</div><span>Hide &amp; Share Secrets</span></div>
</div>
<div class="brand-stats">
<div class="bstat"><div class="bstat-val">Free</div><div class="bstat-lbl">Forever</div></div>
<div class="bstat"><div class="bstat-val">200MB</div><div class="bstat-lbl">Storage</div></div>
<div class="bstat"><div class="bstat-val">∞</div><div class="bstat-lbl">Operations</div></div>
</div>
</div>
</div>
<div class="auth-form-panel">
<div class="auth-card <?= $fromLogin ? 'slide-from-left' : 'fade-up' ?>" id="auth-card">
<div class="auth-logo">
<div class="auth-logo-text">Steg<span class="logo-eye">👁</span>PS</div>
<div class="auth-logo-sub">Create your account</div>
</div>
<div class="field-error-msg" id="reg-error" style="display:none"></div>
<div class="input-group">
<label class="input-label">Username <span class="required-star">*</span></label>
<div class="input-wrap">
<span class="input-icon">◎</span>
<input type="text" class="input has-icon" id="reg-username" placeholder="Choose a username..." minlength="3" maxlength="50" autocomplete="username">
<span class="input-status" id="username-status"></span>
</div>
<div class="field-hint" id="username-hint"></div>
</div>
<div class="input-group">
<label class="input-label">Email <span class="optional-tag">(optional)</span></label>
<div class="input-wrap">
<span class="input-icon" style="font-size:12px;font-family:'Space Mono',monospace">@</span>
<input type="email" class="input has-icon" id="reg-email" placeholder="your@email.com">
<span class="input-status" id="email-status"></span>
</div>
<div class="field-hint" id="email-hint" style="color:var(--text-muted);font-family:'Space Mono',monospace;font-size:9px;margin-top:4px">Not required — but useful for account recovery</div>
</div>
<div class="input-group" style="margin-bottom:20px">
<label class="input-label">Password <span class="required-star">*</span></label>
<div class="input-wrap">
<span class="input-icon">⬡</span>
<input type="password" class="input has-icon" id="reg-password" placeholder="Any password — no rules enforced" autocomplete="new-password">
<button type="button" class="input-eye" onclick="togglePass('reg-password',this)">◉</button>
</div>
<div class="pw-strength-wrap" id="pw-wrap" style="display:none">
<div class="pw-bar-track"><div class="pw-bar-fill" id="pw-bar"></div></div>
<div class="pw-meta">
<span class="pw-label" id="pw-label">—</span>
<div class="pw-checks">
<span class="pw-check" id="pc-len">8+</span>
<span class="pw-check" id="pc-upper">A-Z</span>
<span class="pw-check" id="pc-num">0-9</span>
<span class="pw-check" id="pc-sym">!@#</span>
</div>
</div>
</div>
</div>
<button class="btn btn-primary btn-full btn-lg btn-glow" id="reg-btn" onclick="submitRegister()">
<span class="btn-text">⬡ Create Account</span>
<span class="btn-loader" style="display:none">◌</span>
</button>
<div class="auth-divider"><span>or</span></div>
<div class="auth-switch">
Already have an account? <a href="<?= SITE_URL ?>/login.php" class="auth-switch-link" onclick="return navSwitch(event,'<?= SITE_URL ?>/login.php','left')">Sign in →</a>
</div>
<div class="auth-switch" style="margin-top:8px">
<a href="<?= SITE_URL ?>/index.php" class="auth-switch-link" style="opacity:.6;font-size:11px">← Back · Try as Guest</a>
</div>
<div class="auth-security-note"><span>🔒</span><span>Passwords stored with bcrypt cost-12</span></div>
</div>
</div>
</div>
<script src="<?= SITE_URL ?>/frontend/js/main.js"></script>
<script>
initBgCanvas();
const CHECK_URL = '<?= SITE_URL ?>/php/api/check_availability.php';
const errEl     = document.getElementById('reg-error');
const regUser   = document.getElementById('reg-username');
const regEmail  = document.getElementById('reg-email');
const regPass   = document.getElementById('reg-password');
const regBtn    = document.getElementById('reg-btn');
initPasswordStrength('reg-password','pw-wrap','pw-bar','pw-label',['pc-len','pc-upper','pc-num','pc-sym']);
initFieldCheck('reg-username','username-status','username-hint','username',CHECK_URL);
initEmailHint('reg-email','email-status','email-hint');
[regUser,regEmail,regPass].forEach(el=>el.addEventListener('keydown',e=>{ if(e.key==='Enter') submitRegister(); }));
[regUser,regPass].forEach(el=>el.addEventListener('input',()=>{ el.classList.remove('input-error'); clearError(errEl); }));
function submitRegister() {
const username=regUser.value.trim();
const email=regEmail.value.trim();
const password=regPass.value;
if(!username){regUser.focus();showInputError(regUser,errEl,'Choose a username');return;}
if(username.length<3){showInputError(regUser,errEl,'Username needs at least 3 characters');return;}
if(!password){regPass.focus();showInputError(regPass,errEl,'Set a password');return;}
setLoading(regBtn,true);
fetch('<?= SITE_URL ?>/php/api/auth_ajax.php',{method:'POST',body:new URLSearchParams({action:'register',username,email,password})})
.then(r=>r.json()).then(d=>{
if(d.success){
triggerGlitchTransition(d.redirect,true);
}else{
setLoading(regBtn,false);
showInlineError(errEl,d.message);
if(d.field==='username')regUser.classList.add('input-error');
else if(d.field==='email')regEmail.classList.add('input-error');
else if(d.field==='password')regPass.classList.add('input-error');
setTimeout(()=>[regUser,regEmail,regPass].forEach(el=>el.classList.remove('input-error')),3000);
}
}).catch(()=>setLoading(regBtn,false));
}
</script>
</body>
</html>
