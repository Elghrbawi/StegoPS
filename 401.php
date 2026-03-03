<?php http_response_code(401); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>StegoPS — 401 Unauthorized</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}body{font-family:'Syne',sans-serif;background:#050c0e;color:#e8f4f1;display:flex;align-items:center;justify-content:center}body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,229,200,.012) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,200,.012) 1px,transparent 1px);background-size:44px 44px;pointer-events:none}canvas{position:fixed;inset:0;z-index:0;pointer-events:none}.wrap{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:500px}.code{font-family:'Space Mono',monospace;font-size:clamp(80px,15vw,130px);font-weight:700;color:#00e5c8;line-height:1;text-shadow:0 0 40px rgba(0,229,200,.4);animation:glitch 3s infinite}.code-label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(0,229,200,.5);text-transform:uppercase;margin-bottom:16px;animation:glitch 3s infinite .3s}@keyframes glitch{0%,90%,100%{text-shadow:0 0 40px rgba(0,229,200,.4)}91%{text-shadow:-3px 0 #ff4d6d,3px 0 #7c3aed,0 0 40px rgba(0,229,200,.4)}93%{text-shadow:3px 0 #ff4d6d,-3px 0 #7c3aed,0 0 40px rgba(0,229,200,.4)}95%{text-shadow:0 0 40px rgba(0,229,200,.4)}}.title{font-size:clamp(18px,4vw,28px);font-weight:800;margin-bottom:10px;letter-spacing:-.3px}.sub{font-family:'JetBrains Mono',monospace;font-size:12px;color:#7aa8a0;margin-bottom:32px;line-height:1.7}.terminal{background:rgba(5,12,14,.9);border:1px solid rgba(0,229,200,.2);border-radius:10px;padding:14px;margin-bottom:28px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;color:rgba(0,229,200,.6);line-height:1.7}.t-red{color:rgba(255,77,109,.8)}.t-dim{color:rgba(122,168,160,.3)}.t-ok{color:rgba(0,229,200,.9)}.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:11px 22px;border-radius:8px;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s}.btn-p{background:#00e5c8;color:#050c0e}.btn-p:hover{background:#00b89f}.btn-g{background:transparent;color:#7aa8a0;border:1px solid #1a3d4a}.btn-g:hover{border-color:#00e5c8;color:#00e5c8}</style></head>
<body><canvas id="c"></canvas><div class="wrap">
<div class="code">401</div>
<div class="code-label">Access Denied · Unauthorized</div>
<h1 class="title">Identity Not Verified</h1>
<p class="sub">Your session has expired or was never established.<br>You need to authenticate before accessing this area.</p>
<div class="terminal">
<div>> AUTH_CHECK: <span class="t-red">FAILED — no valid session token</span></div>
<div>> IDENTITY: <span class="t-red">UNVERIFIED</span></div>
<div>> ACTION: <span class="t-ok">Redirect to authentication portal</span></div>
<div class="t-dim">> Session tokens are valid for 1 hour after last activity</div>
</div>
<div class="btns">
<a href="<?php define('SITE_URL','http://localhost/StegoPS'); echo SITE_URL; ?>/login.php" class="btn btn-p">→ Sign In</a>
<a href="<?= SITE_URL ?>/index.php" class="btn btn-g">← Home</a>
</div>
</div>
<script>(function(){const c=document.getElementById('c'),ctx=c.getContext('2d');let W=c.width=innerWidth,H=c.height=innerHeight;const pts=Array.from({length:40},()=>({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,r:Math.random()+.4,o:Math.random()*.3+.05}));function draw(){ctx.clearRect(0,0,W,H);pts.forEach(p=>{p.x+=p.vx;p.y+=p.vy;if(p.x<0)p.x=W;if(p.x>W)p.x=0;if(p.y<0)p.y=H;if(p.y>H)p.y=0;ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fillStyle=`rgba(0,229,200,${p.o})`;ctx.fill();});requestAnimationFrame(draw);}draw();window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});})();</script>
</body></html>
