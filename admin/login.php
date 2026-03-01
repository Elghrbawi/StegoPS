<?php
require_once __DIR__ . '/auth.php';
$adminAuth = new AdminAuth();
if ($adminAuth->isLoggedIn()) { header('Location: '.SITE_URL.'/admin/'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $adminAuth->login(trim($_POST['username']??''), $_POST['password']??'', $_SERVER['REMOTE_ADDR']);
    if ($result['success']) { header('Location: '.SITE_URL.'/admin/'); exit; }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StegoPS — Admin Access</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#050c0e;--surface:#0a1a1f;--surface2:#0f2530;--border:#1a3d4a;--accent:#ff4d6d;--accent2:#e03558;--accent-dim:rgba(255,77,109,.1);--text:#e8f4f1;--text-dim:#7aa8a0;--text-muted:#3d7068;--danger:#ff4d6d}
html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(255,77,109,.015) 1px,transparent 1px),linear-gradient(90deg,rgba(255,77,109,.015) 1px,transparent 1px);background-size:44px 44px;pointer-events:none}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.wrap{position:relative;z-index:1;width:100%;max-width:380px;padding:20px}
.card{background:var(--surface);border:1px solid rgba(255,77,109,.3);border-radius:16px;padding:36px;animation:fadeUp .5s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
.logo{text-align:center;margin-bottom:26px}
.logo-text{font-family:'Space Mono',monospace;font-size:24px;font-weight:700;color:var(--accent);text-shadow:0 0 20px rgba(255,77,109,.4)}
.logo-sub{font-family:'Space Mono',monospace;font-size:9px;letter-spacing:3px;color:var(--text-muted);margin-top:6px;text-transform:uppercase}
.warn-badge{display:flex;align-items:center;gap:8px;background:rgba(255,77,109,.08);border:1px solid rgba(255,77,109,.2);border-radius:8px;padding:10px 14px;margin-bottom:18px;font-family:'Space Mono',monospace;font-size:10px;color:rgba(255,77,109,.8);letter-spacing:.5px}
.err{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.4);border-radius:8px;padding:11px 14px;font-family:'Space Mono',monospace;font-size:11px;color:var(--danger);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.err::before{content:'✗';font-weight:700}
.lbl{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:1.5px;color:var(--text-muted);text-transform:uppercase;margin-bottom:7px;display:block}
.grp{margin-bottom:14px}
.input-wrap{position:relative}
.input-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;pointer-events:none}
input[type=text],input[type=password]{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:11px 14px 11px 38px;color:var(--text);font-family:'JetBrains Mono',monospace;font-size:13px;outline:none;transition:border-color .2s,box-shadow .2s}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(255,77,109,.08)}
.eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:4px;font-size:13px;opacity:.6}
.eye:hover{opacity:1;color:var(--accent)}
.btn{width:100%;font-family:'Space Mono',monospace;font-size:12px;font-weight:700;letter-spacing:.5px;padding:13px;border-radius:8px;cursor:pointer;border:none;background:var(--accent);color:#fff;margin-top:6px;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn:hover{background:var(--accent2);box-shadow:0 8px 24px rgba(255,77,109,.3);transform:translateY(-1px)}
.btn:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none}
.spin{animation:spin .7s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}
.back{text-align:center;margin-top:16px;font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)}
.back a{color:rgba(255,77,109,.7);text-decoration:none}
.back a:hover{color:var(--accent)}
.note{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:14px;font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);opacity:.7}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="wrap">
<div class="card">
<div class="logo">
<div class="logo-text">Steg<span style="color:#fff">👁</span>PS</div>
<div class="logo-sub">Restricted Admin Access</div>
</div>
<div class="warn-badge">⬡ ADMIN ONLY — Unauthorized access is logged</div>
<?php if($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" id="admin-form" novalidate>
<div class="grp">
<label class="lbl">Admin Username</label>
<div class="input-wrap">
<span class="input-icon">◎</span>
<input type="text" name="username" id="u" placeholder="Admin username or email" required autocomplete="username" value="<?= htmlspecialchars($_POST['username']??'') ?>">
</div>
</div>
<div class="grp" style="margin-bottom:20px">
<label class="lbl">Admin Password</label>
<div class="input-wrap">
<span class="input-icon">⬡</span>
<input type="password" name="password" id="p" placeholder="Admin password" required autocomplete="current-password">
<button type="button" class="eye" onclick="this.previousElementSibling.type=this.previousElementSibling.type==='password'?'text':'password'">◉</button>
</div>
</div>
<button type="submit" class="btn" id="btn">
<span id="btn-txt">→ Access Admin Panel</span>
<span id="btn-ldr" class="spin" style="display:none">◌</span>
</button>
</form>
<div class="back"><a href="<?= SITE_URL ?>/index.php">← Back to main site</a></div>
<div class="note"><span>🔒</span><span>Session expires after 2 hours of inactivity</span></div>
</div>
</div>
<script>
document.getElementById('admin-form').addEventListener('submit',function(){
document.getElementById('btn-txt').style.display='none';
document.getElementById('btn-ldr').style.display='inline';
document.getElementById('btn').disabled=true;
});
(function(){
const c=document.getElementById('c'),ctx=c.getContext('2d');
let W=c.width=innerWidth,H=c.height=innerHeight;
const pts=Array.from({length:40},()=>({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,r:Math.random()+.4,o:Math.random()*.4+.1}));
function draw(){ctx.clearRect(0,0,W,H);pts.forEach(p=>{p.x+=p.vx;p.y+=p.vy;if(p.x<0)p.x=W;if(p.x>W)p.x=0;if(p.y<0)p.y=H;if(p.y>H)p.y=0;ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fillStyle=`rgba(255,77,109,${p.o})`;ctx.fill();});pts.forEach((a,i)=>pts.slice(i+1).forEach(b=>{const d=Math.hypot(a.x-b.x,a.y-b.y);if(d<90){ctx.beginPath();ctx.moveTo(a.x,a.y);ctx.lineTo(b.x,b.y);ctx.strokeStyle=`rgba(255,77,109,${.06*(1-d/90)})`;ctx.lineWidth=.5;ctx.stroke();}}));requestAnimationFrame(draw);}
draw();window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>
