<?php
http_response_code(429);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 429 Too Many Requests</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#050a05;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:520px;width:100%}
.firewall{width:180px;height:60px;margin:0 auto 24px;position:relative;display:flex;align-items:center;justify-content:center;gap:4px}
.brick{width:18px;height:14px;background:rgba(255,100,0,.15);border:1px solid rgba(255,120,20,.35);border-radius:2px;animation:brickFlash infinite}
.brick:nth-child(odd){animation-duration:1.4s}
.brick:nth-child(even){animation-duration:1.8s;animation-delay:.4s}
@keyframes brickFlash{0%,100%{background:rgba(255,100,0,.1)}50%{background:rgba(255,100,0,.3);box-shadow:0 0 6px rgba(255,100,0,.4)}}
.rate-bar{background:rgba(255,100,0,.08);border:1px solid rgba(255,100,0,.2);border-radius:20px;height:8px;margin-bottom:20px;overflow:hidden}
.rate-fill{height:100%;background:linear-gradient(90deg,rgba(0,229,200,.6),rgba(255,100,0,.8) 80%,rgba(255,50,50,1));border-radius:20px;width:100%;animation:ratePulse 1s ease-in-out infinite}
@keyframes ratePulse{0%,100%{opacity:1;filter:brightness(1)}50%{opacity:.7;filter:brightness(1.3)}}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#ff6414;line-height:1;text-shadow:0 0 50px rgba(255,100,20,.55);animation:rateGlitch 2.5s infinite}
@keyframes rateGlitch{0%,85%,100%{text-shadow:0 0 50px rgba(255,100,20,.55)}86%{text-shadow:-3px 0 #00e5c8,3px 0 #ff3250;transform:translateX(-2px)}88%{transform:none}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(255,100,20,.6);text-transform:uppercase;margin:8px 0 14px;animation:blink 1s step-end infinite}
@keyframes blink{50%{opacity:0}}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:22px;line-height:1.8}
.term{background:rgba(5,10,5,.97);border:1px solid rgba(255,100,20,.2);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.1s}.tl:nth-child(2){animation-delay:.5s}.tl:nth-child(3){animation-delay:.9s}.tl:nth-child(4){animation-delay:1.3s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,80,.9)}.ty{color:rgba(255,200,60,.8)}.tg{color:rgba(0,229,200,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#ff6414;color:#fff;box-shadow:0 0 20px rgba(255,100,20,.3)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(255,100,20,.5)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #1a2010}.bg:hover{border-color:rgba(255,100,20,.5);color:#ff6414;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <div class="firewall">
    <?php for($i=0;$i<8;$i++): ?><div class="brick"></div><?php endfor; ?>
  </div>
  <div class="rate-bar"><div class="rate-fill"></div></div>
  <div class="code">429</div>
  <div class="label">Rate Limited &#9646;</div>
  <h1>Slow Down, You're Flooding the System</h1>
  <p class="sub">Too many requests in too short a time.<br>The firewall has throttled your connection.</p>
  <div class="term">
    <span class="tl tr">> RATE_LIMIT ............ EXCEEDED</span>
    <span class="tl ty">> REQUEST_COUNT ......... Too many</span>
    <span class="tl ty">> COOLDOWN .............. Active</span>
    <span class="tl tg">> ACTION ................ Wait before retrying</span>
  </div>
  <div class="btns">
    <a href="javascript:setTimeout(()=>location.reload(),3000)" class="btn bp">&#9203; Wait &amp; Retry</a>
    <a href="<?= SITE_URL ?>/index.php" class="btn bg">&#8592; Home</a>
  </div>
</div>
<script>
(function(){
const c=document.getElementById('c'),x=c.getContext('2d');
let W=c.width=innerWidth,H=c.height=innerHeight;
const pts=Array.from({length:60},()=>({px:Math.random()*W,py:Math.random()*H,vx:(Math.random()-.5)*.6,vy:(Math.random()-.5)*.6,r:Math.random()*.6+.2,o:Math.random()*.3+.05}));
function draw(){x.clearRect(0,0,W,H);pts.forEach(p=>{p.px+=p.vx;p.py+=p.vy;if(p.px<0)p.px=W;if(p.px>W)p.px=0;if(p.py<0)p.py=H;if(p.py>H)p.py=0;x.beginPath();x.arc(p.px,p.py,p.r,0,Math.PI*2);x.fillStyle=`rgba(255,100,20,${p.o})`;x.fill();});requestAnimationFrame(draw);}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>