<?php http_response_code(403); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>StegoPS — 403 Forbidden</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}body{font-family:'Syne',sans-serif;background:#050c0e;color:#e8f4f1;display:flex;align-items:center;justify-content:center}body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(255,77,109,.01) 1px,transparent 1px),linear-gradient(90deg,rgba(255,77,109,.01) 1px,transparent 1px);background-size:44px 44px;pointer-events:none}canvas{position:fixed;inset:0;z-index:0;pointer-events:none}.wrap{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:500px}.code{font-family:'Space Mono',monospace;font-size:clamp(80px,15vw,130px);font-weight:700;color:#ff4d6d;line-height:1;text-shadow:0 0 40px rgba(255,77,109,.4);animation:glitch 2.5s infinite}@keyframes glitch{0%,88%,100%{text-shadow:0 0 40px rgba(255,77,109,.4)}89%{text-shadow:-3px 0 #00e5c8,3px 0 #7c3aed,0 0 40px rgba(255,77,109,.6)}91%{text-shadow:3px 0 #00e5c8,-2px 0 #7c3aed}93%{text-shadow:0 0 40px rgba(255,77,109,.4)}}.code-label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(255,77,109,.5);text-transform:uppercase;margin-bottom:16px}.title{font-size:clamp(18px,4vw,28px);font-weight:800;margin-bottom:10px}.sub{font-family:'JetBrains Mono',monospace;font-size:12px;color:#7aa8a0;margin-bottom:32px;line-height:1.7}.terminal{background:rgba(5,12,14,.9);border:1px solid rgba(255,77,109,.2);border-radius:10px;padding:14px;margin-bottom:28px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;color:rgba(255,77,109,.7);line-height:1.7}.t-dim{color:rgba(122,168,160,.3)}.t-ok{color:rgba(0,229,200,.9)}.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:11px 22px;border-radius:8px;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s}.btn-p{background:#00e5c8;color:#050c0e}.btn-p:hover{background:#00b89f}.btn-g{background:transparent;color:#7aa8a0;border:1px solid #1a3d4a}.btn-g:hover{border-color:#ff4d6d;color:#ff4d6d}</style></head>
<body><canvas id="c"></canvas><div class="wrap">
<div class="code">403</div>
<div class="code-label">Forbidden · Clearance Required</div>
<h1 class="title">Insufficient Permissions</h1>
<p class="sub">Your account does not have clearance for this operation.<br>This area is restricted to authorized personnel only.</p>
<div class="terminal">
<div>> ACCESS_LEVEL: <span style="color:rgba(255,77,109,.9)">DENIED — role clearance insufficient</span></div>
<div>> REQUIRED: <span style="color:rgba(255,179,71,.8)">ADMIN</span></div>
<div>> ACTION: <span class="t-ok">Return to authorized zone</span></div>
<div class="t-dim">> This access attempt has been logged</div>
</div>
<div class="btns">
<a href="<?php define('SITE_URL','http://localhost/StegoPS'); echo SITE_URL; ?>/dashboard.php" class="btn btn-p">← Dashboard</a>
<a href="<?= SITE_URL ?>/index.php" class="btn btn-g">Home</a>
</div>
</div>
<script>(function(){const c=document.getElementById('c'),ctx=c.getContext('2d');let W=c.width=innerWidth,H=c.height=innerHeight;const pts=Array.from({length:40},()=>({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.35,vy:(Math.random()-.5)*.35,r:Math.random()+.4,o:Math.random()*.35+.05}));function draw(){ctx.clearRect(0,0,W,H);pts.forEach(p=>{p.x+=p.vx;p.y+=p.vy;if(p.x<0)p.x=W;if(p.x>W)p.x=0;if(p.y<0)p.y=H;if(p.y>H)p.y=0;ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fillStyle=`rgba(255,77,109,${p.o})`;ctx.fill();});requestAnimationFrame(draw);}draw();window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});})();</script>
</body></html>
