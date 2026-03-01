<?php
require_once 'php/config/database.php';
require_once 'php/includes/auth.php';
$auth = new Auth();
if ($auth->isLoggedIn()) { header('Location: '.SITE_URL.'/dashboard.php'); exit; }
$redirectParam = $_GET['redirect'] ?? '';
$fromRegister  = isset($_GET['from']) && $_GET['from']==='register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StegoPS — Sign In</title>
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
<div class="brand-tagline">STEGANOGRAPHIC<br>PROTECTION SYSTEM</div>
<div class="brand-feats">
<div class="brand-feat"><span class="feat-dot"></span>34+ Encryption Algorithms</div>
<div class="brand-feat"><span class="feat-dot"></span>LSB / Phase / Frame Steganography</div>
<div class="brand-feat"><span class="feat-dot"></span>Multi-layer Chain Builder</div>
<div class="brand-feat"><span class="feat-dot"></span>Secure Encrypted Share Links</div>
</div>
<div class="brand-stats">
<div class="bstat"><div class="bstat-val">34+</div><div class="bstat-lbl">Algorithms</div></div>
<div class="bstat"><div class="bstat-val">3</div><div class="bstat-lbl">Media Types</div></div>
<div class="bstat"><div class="bstat-val">∞</div><div class="bstat-lbl">Chain Depth</div></div>
</div>
</div>
</div>
<div class="auth-form-panel">
<div class="auth-card <?= $fromRegister ? 'slide-from-right' : 'fade-up' ?>" id="auth-card">
<div class="auth-logo">
<div class="auth-logo-text">Steg<span class="logo-eye">👁</span>PS</div>
<div class="auth-logo-sub">Welcome back</div>
</div>
<div class="field-error-msg" id="login-error" style="display:none"></div>
<div class="input-group">
<label class="input-label">Username or Email</label>
<div class="input-wrap">
<span class="input-icon">◎</span>
<input type="text" class="input has-icon" id="login-user" placeholder="Who are you?" autocomplete="username">
</div>
</div>
<div class="input-group" style="margin-bottom:20px">
<label class="input-label">Password</label>
<div class="input-wrap">
<span class="input-icon">⬡</span>
<input type="password" class="input has-icon" id="login-pass" placeholder="Your secret key" autocomplete="current-password">
<button type="button" class="input-eye" onclick="togglePass('login-pass',this)">◉</button>
</div>
</div>
<button class="btn btn-primary btn-full btn-lg btn-glow" id="login-btn" onclick="submitLogin()">
<span class="btn-text">→ Enter StegoPS</span>
<span class="btn-loader" style="display:none">◌</span>
</button>
<div class="auth-divider"><span>or</span></div>
<div class="auth-switch">
No account? <a href="<?= SITE_URL ?>/register.php" class="auth-switch-link" onclick="return navSwitch(event,'<?= SITE_URL ?>/register.php','right')">Create one →</a>
</div>
<div class="auth-switch" style="margin-top:8px">
<a href="<?= SITE_URL ?>/index.php" class="auth-switch-link" style="opacity:.6;font-size:11px">← Back · Try as Guest</a>
</div>
<div class="auth-security-note"><span>🔒</span><span>bcrypt · salt · session protection</span></div>
</div>
</div>
</div>
<script src="<?= SITE_URL ?>/frontend/js/main.js"></script>
<script>
initBgCanvas();
const REDIRECT = '<?= addslashes($redirectParam ?: SITE_URL.'/dashboard.php') ?>';
const loginUser = document.getElementById('login-user');
const loginPass = document.getElementById('login-pass');
const loginBtn  = document.getElementById('login-btn');
const errEl     = document.getElementById('login-error');
[loginUser, loginPass].forEach(el=>el.addEventListener('keydown',e=>{ if(e.key==='Enter') submitLogin(); }));
loginUser.addEventListener('input',()=>{ loginUser.classList.remove('input-error'); clearError(errEl); });
loginPass.addEventListener('input',()=>{ loginPass.classList.remove('input-error'); clearError(errEl); });
function submitLogin() {
const username=loginUser.value.trim();
const password=loginPass.value;
if(!username){loginUser.focus();showInputError(loginUser,errEl,'Enter your username or email');return;}
if(!password){loginPass.focus();showInputError(loginPass,errEl,'Enter your password');return;}
setLoading(loginBtn,true);
fetch('<?= SITE_URL ?>/php/api/auth_ajax.php',{method:'POST',body:new URLSearchParams({action:'login',username,password})})
.then(r=>r.json()).then(d=>{
if(d.success){
triggerGlitchTransition(REDIRECT);
}else{
setLoading(loginBtn,false);
showInlineError(errEl,d.message);
if(d.field==='username')loginUser.classList.add('input-error');
else loginPass.classList.add('input-error');
loginPass.value='';
setTimeout(()=>{ loginPass.classList.remove('input-error');loginUser.classList.remove('input-error'); },3000);
}
}).catch(()=>setLoading(loginBtn,false));
}
</script>
</body>
</html>
