<?php http_response_code(404); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>StegoPS — 404 Not Found</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}body{font-family:'Syne',sans-serif;background:#050c0e;color:#e8f4f1;display:flex;align-items:center;justify-content:center}body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,229,200,.012) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,200,.012) 1px,transparent 1px);background-size:44px 44px;pointer-events:none}canvas{position:fixed;inset:0;z-index:0;pointer-events:none}.wrap{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:520px}.code{font-family:'Space Mono',monospace;font-size:clamp(80px,15vw,130px);font-weight:700;color:#7c3aed;line-height:1;text-shadow:0 0 40px rgba(124,58,237,.5);animation:glitch 3.5s infinite}@keyframes glitch{0%,85%,100%{text-shadow:0 0 40px rgba(124,58,237,.5)}86%{text-shadow:-4px 0 #00e5c8,4px 0 #ff4d6d,0 0 40px rgba(124,58,237,.6)}88%{text-shadow:4px 0 #00e5c8,-4px 0 #ff4d6d}90%{text-shadow:0 0 40px rgba(124,58,237,.5)}}.code-label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(124,58,237,.6);text-transform:uppercase;margin-bottom:16px}.title{font-size:clamp(18px,4vw,28px);font-weight:800;margin-bottom:10px}.sub{font-family:'JetBrains Mono',monospace;font-size:12px;color:#7aa8a0;margin-bottom:28px;line-height:1.7}.path{font-family:'JetBrains Mono',monospace;font-size:12px;color:rgba(124,58,237,.7);background:rgba(124,58,237,.08);border:1px solid rgba(124,58,237,.2);border-radius:6px;padding:10px 14px;margin-bottom:28px;word-break:break-all}.terminal{background:rgba(5,12,14,.9);border:1px solid rgba(124,58,237,.2);border-radius:10px;padding:14px;margin-bottom:28px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;color:rgba(124,58,237,.7);line-height:1.7}.t-ok{color:rgba(0,229,200,.9)}.t-dim{color:rgba(122,168,160,.3)}.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:11px 22px;border-radius:8px;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s}.btn-p{background:#00e5c8;color:#050c0e}.btn-p:hover{background:#00b89f}.btn-g{background:transparent;color:#7aa8a0;border:1px solid #1a3d4a}.btn-g:hover{border-color:#7c3aed;color:#a78bfa}</style></head>
<body><canvas id="c"></canvas><div class="wrap">
<div class="code">404</div>
<div class="code-label">Not Found · Data Lost</div>
<h1 class="title">The Payload Was Hidden Too Well</h1>
<p class="sub">This page doesn't exist or was moved.<br>The data you're looking for is not in our registers.</p>
<div class="path">> REQUEST: <?= htmlspecialchars($_SERVER['REQUEST_URI']??'/') ?></div>
<div class="terminal">
<div>> FILE_SCAN: <span style="color:rgba(255,77,109,.9)">0 matches found</span></div>
<div>> DEPTH: <span style="color:rgba(255,179,71,.8)">All layers searched</span></div>
<div>> RESULT: <span style="color:rgba(255,77,109,.8)">null</span></div>
<div class="t-dim">> Payload extraction failed — resource does not exist</div>
</div>
<div class="btns">
<a href="<?php define('SITE_URL','http://localhost/StegoPS'); echo SITE_URL; ?>/dashboard.php" class="btn btn-p">← Dashboard</a>
<a href="<?= SITE_URL ?>/index.php" class="btn btn-g">Home</a>
</div>
</div>
<script>(function(){const c=document.getElementById('c'),ctx=c.getContext('2d');let W=c.width=innerWidth,H=c.height=innerHeight;const pts=Array.from({length:45},()=>({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,r:Math.random()+.4,o:Math.random()*.25+.05}));function draw(){ctx.clearRect(0,0,W,H);pts.forEach(p=>{p.x+=p.vx;p.y+=p.vy;if(p.x<0)p.x=W;if(p.x>W)p.x=0;if(p.y<0)p.y=H;if(p.y>H)p.y=0;ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fillStyle=`rgba(124,58,237,${p.o})`;ctx.fill();});requestAnimationFrame(draw);}draw();window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});})();</script>
</body></html>
