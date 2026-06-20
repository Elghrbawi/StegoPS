<?php
require_once 'php/config/database.php';
require_once 'php/includes/auth.php';
$auth = new Auth();
if ($auth->isLoggedIn()) { header('Location: '.SITE_URL.'/dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StegoPS — Steganographic Protection System</title>
<link rel="icon" href="logo.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= SITE_URL ?>/frontend/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="auth-body landing-body">
<canvas id="bg-canvas"></canvas>
<div class="glitch-overlay" id="glitch-overlay"></div>
<div class="landing-wrap">
<nav class="landing-nav">
<div class="landing-logo">Steg<span class="logo-eye">👁</span>PS</div>
<div style="display:flex;gap:10px">
<a href="<?= SITE_URL ?>/login.php" class="btn btn-ghost btn-sm">Sign In</a>
<a href="<?= SITE_URL ?>/register.php" class="btn btn-primary btn-sm">Create Account</a>
</div>
</nav>
<div class="landing-hero">
<div class="landing-badge"><span class="badge-dot"></span>v1.0 · 34+ Encryption Algorithms</div>
<h1 class="landing-title">
<span class="title-glitch" data-text="HIDE YOUR SECRETS">HIDE YOUR SECRETS</span><br>
<span class="title-accent">INSIDE PLAIN SIGHT</span>
</h1>
<p class="landing-sub">Multi-layer steganography with chained encryption. Conceal messages inside images, audio, and video files — invisible to the naked eye.</p>
<div class="landing-actions">
<button class="btn btn-primary btn-xl" id="guest-btn" onclick="startGuest()">
<span>👁</span>Try Without Account
</button>
<a href="<?= SITE_URL ?>/register.php" class="btn btn-outline-accent btn-xl">
<span>⬡</span>Create Free Account
</a>
</div>
<div class="landing-note">Guest mode · No signup · Upgrade anytime to save your work</div>
</div>
<div class="landing-features">
<div class="feat-card" style="--i:0"><div class="feat-icon">🖼</div><div class="feat-title">Image Stego</div><div class="feat-desc">LSB pixel manipulation inside PNG, JPG, BMP</div></div>
<div class="feat-card" style="--i:1"><div class="feat-icon">🎵</div><div class="feat-title">Audio Stego</div><div class="feat-desc">Phase coding &amp; echo hiding in WAV, MP3</div></div>
<div class="feat-card" style="--i:2"><div class="feat-icon">⛓</div><div class="feat-title">Chain Builder</div><div class="feat-desc">Stack 34+ ciphers — AES, RSA, Blowfish, XOR</div></div>
<div class="feat-card" style="--i:3"><div class="feat-icon">⬡</div><div class="feat-title">Secure Share</div><div class="feat-desc">Encrypted links with password &amp; expiry</div></div>
</div>
</div>
<div id="upgrade-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
<div class="modal-card animate-in">
<div class="modal-title">Save Your Guest Work</div>
<div class="modal-sub">Set a username and password. Your local data will carry over.</div>
<div id="upgrade-error" class="field-error-msg" style="display:none"></div>
<div class="input-group">
<label class="input-label">Username</label>
<div class="input-wrap">
<span class="input-icon">◎</span>
<input type="text" class="input has-icon" id="up-username" placeholder="Choose a username...">
<span class="input-status" id="up-uname-status"></span>
</div>
<div class="field-hint" id="up-uname-hint"></div>
</div>
<div class="input-group">
<label class="input-label">Password</label>
<div class="input-wrap">
<span class="input-icon">⬡</span>
<input type="password" class="input has-icon" id="up-password" placeholder="Your password">
<button type="button" class="input-eye" onclick="togglePass('up-password',this)">◉</button>
</div>
</div>
<div class="input-group" style="margin-bottom:0">
<label class="input-label">Email <span class="optional-tag">(optional)</span></label>
<div class="input-wrap">
<span class="input-icon" style="font-size:12px;font-family:'Space Mono',monospace">@</span>
<input type="email" class="input has-icon" id="up-email" placeholder="your@email.com (optional)">
</div>
</div>
<div class="modal-actions">
<button class="btn btn-primary btn-full" id="upgrade-btn" onclick="submitUpgrade()">
<span class="btn-text">⬡ Create Account</span>
<span class="btn-loader" style="display:none">◌</span>
</button>
<button class="btn btn-ghost" onclick="document.getElementById('upgrade-modal').style.display='none'">Cancel</button>
</div>
</div>
</div>
<script src="<?= SITE_URL ?>/frontend/js/main.js"></script>
<script>
initBgCanvas();
initFieldCheck('up-username','up-uname-status','up-uname-hint','username','<?= SITE_URL ?>/php/api/check_availability.php');
function startGuest() {
const btn=document.getElementById('guest-btn');
btn.innerHTML='<span class="btn-loader">◌</span> Entering...';
btn.disabled=true;
fetch('<?= SITE_URL ?>/php/api/auth_ajax.php',{method:'POST',body:new URLSearchParams({action:'guest'})})
.then(r=>r.json()).then(d=>{
if(d.success){triggerGlitchTransition(d.redirect);}
else{btn.innerHTML='<span>👁</span>Try Without Account';btn.disabled=false;}
}).catch(()=>{btn.innerHTML='<span>👁</span>Try Without Account';btn.disabled=false;});
}
function submitUpgrade() {
const btn=document.getElementById('upgrade-btn');
const errEl=document.getElementById('upgrade-error');
const username=document.getElementById('up-username').value.trim();
const password=document.getElementById('up-password').value;
const email=document.getElementById('up-email').value.trim();
if(!username||!password){showInlineError(errEl,'Fill in username and password');return;}
btn.querySelector('.btn-text').style.display='none';
btn.querySelector('.btn-loader').style.display='inline';
btn.disabled=true;
const guestData=localStorage.getItem('stego_guest_ops')||'[]';
fetch('<?= SITE_URL ?>/php/api/auth_ajax.php',{method:'POST',body:new URLSearchParams({action:'guest_upgrade',username,password,email,guest_data:guestData})})
.then(r=>r.json()).then(d=>{
if(d.success){localStorage.removeItem('stego_guest_ops');triggerGlitchTransition(d.redirect);}
else{
showInlineError(errEl,d.message);
btn.querySelector('.btn-text').style.display='inline';
btn.querySelector('.btn-loader').style.display='none';
btn.disabled=false;
if(d.field)document.getElementById('up-'+(d.field==='username'?'username':d.field))?.classList.add('input-error');
}
}).catch(()=>{btn.querySelector('.btn-text').style.display='inline';btn.querySelector('.btn-loader').style.display='none';btn.disabled=false;});
}
</script>
</body>
</html>
