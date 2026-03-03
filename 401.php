<?php
http_response_code(401);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 401 Unauthorized</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#030609;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:500px;width:100%}
.scanner-wrap{position:relative;width:160px;height:160px;margin:0 auto 28px}
.ring{position:absolute;border-radius:50%;border:1.5px solid rgba(255,50,80,.2);animation:rp 2.2s ease-in-out infinite}
.ring:nth-child(1){inset:0;animation-delay:0s}
.ring:nth-child(2){inset:-14px;border-color:rgba(255,50,80,.1);animation-delay:.5s}
.ring:nth-child(3){inset:-28px;border-color:rgba(255,50,80,.05);animation-delay:1s}
@keyframes rp{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.05);opacity:.4}}
.fp{width:100%;height:100%;position:relative;z-index:2}
.scan-line{position:absolute;left:14px;right:14px;height:2px;background:linear-gradient(90deg,transparent,#ff3250,transparent);top:50%;animation:scan 2.8s ease-in-out infinite;box-shadow:0 0 14px #ff3250,0 0 30px rgba(255,50,80,.4)}
@keyframes scan{0%{top:12%;opacity:0}8%{opacity:1}85%{opacity:1}100%{top:88%;opacity:0}}
.denied{position:absolute;inset:-4px;border-radius:50%;background:rgba(255,50,80,.0);animation:denied 2.8s ease-in-out infinite}
@keyframes denied{0%,88%,100%{background:rgba(255,50,80,.0);box-shadow:none}89%{background:rgba(255,50,80,.18);box-shadow:0 0 40px rgba(255,50,80,.6)}94%{background:rgba(255,50,80,.0)}}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#ff3250;line-height:1;text-shadow:0 0 50px rgba(255,50,80,.5),0 0 100px rgba(255,50,80,.15);animation:flicker 5s infinite}
@keyframes flicker{0%,92%,100%{opacity:1}93%{opacity:.2}94%{opacity:1}95%{opacity:.4}96%{opacity:1}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(255,50,80,.55);text-transform:uppercase;margin:8px 0 18px;animation:blink 1.2s step-end infinite}
@keyframes blink{50%{opacity:0}}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:22px;line-height:1.8}
.term{background:rgba(3,6,9,.96);border:1px solid rgba(255,50,80,.18);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.2s}.tl:nth-child(2){animation-delay:.7s}.tl:nth-child(3){animation-delay:1.2s}.tl:nth-child(4){animation-delay:1.7s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,80,.9)}.ty{color:rgba(255,200,60,.8)}.tg{color:rgba(0,229,200,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#ff3250;color:#fff;box-shadow:0 0 20px rgba(255,50,80,.35)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 35px rgba(255,50,80,.55)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #1a2d35}.bg:hover{border-color:rgba(255,50,80,.45);color:#ff3250;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <div class="scanner-wrap">
    <div class="ring"></div><div class="ring"></div><div class="ring"></div>
    <div class="scan-line"></div>
    <div class="denied"></div>
    <svg class="fp" viewBox="0 0 100 100" fill="none">
      <circle cx="50" cy="50" r="38" stroke="rgba(255,50,80,.5)" stroke-width="1.5" stroke-dasharray="4 3"/>
      <circle cx="50" cy="50" r="28" stroke="rgba(255,50,80,.4)" stroke-width="1.5" stroke-dasharray="6 2"/>
      <circle cx="50" cy="50" r="18" stroke="rgba(255,50,80,.3)" stroke-width="1.5"/>
      <circle cx="50" cy="50" r="8" stroke="rgba(255,50,80,.6)" stroke-width="1.5"/>
      <circle cx="50" cy="50" r="2.5" fill="rgba(255,50,80,.9)"/>
      <line x1="12" y1="50" x2="88" y2="50" stroke="rgba(255,50,80,.2)" stroke-width="1" stroke-dasharray="2 4"/>
      <line x1="50" y1="12" x2="50" y2="88" stroke="rgba(255,50,80,.2)" stroke-width="1" stroke-dasharray="2 4"/>
      <line x1="22" y1="22" x2="78" y2="78" stroke="rgba(255,50,80,.1)" stroke-width="1"/>
      <line x1="78" y1="22" x2="22" y2="78" stroke="rgba(255,50,80,.1)" stroke-width="1"/>
    </svg>
  </div>
  <div class="code">401</div>
  <div class="label">Access Denied &#9646;</div>
  <h1>Identity Not Verified</h1>
  <p class="sub">No valid session token was found.<br>You must authenticate before entering this zone.</p>
  <div class="term">
    <span class="tl tr">> BIOMETRIC_SCAN ........ FAILED</span>
    <span class="tl tr">> SESSION_TOKEN ......... NOT FOUND</span>
    <span class="tl ty">> IDENTITY .............. UNVERIFIED</span>
    <span class="tl tg">> ACTION ................ Redirect to auth portal</span>
  </div>
  <div class="btns">
    <a href="<?= SITE_URL ?>/login.php" class="btn bp">&#8594; Sign In</a>
    <a href="<?= SITE_URL ?>/index.php" class="btn bg">&#8592; Home</a>
  </div>
</div>
<script>
(function(){
const c=document.getElementById('c'),x=c.getContext('2d');
let W=c.width=innerWidth,H=c.height=innerHeight;
const pts=Array.from({length:55},()=>({px:Math.random()*W,py:Math.random()*H,vx:(Math.random()-.5)*.45,vy:(Math.random()-.5)*.45,r:Math.random()*.9+.25,o:Math.random()*.35+.04}));
const bars=Array.from({length:10},()=>({x:Math.random()*W,y:Math.random()*H,w:Math.random()*180+60,speed:(Math.random()-.5)*1.2,o:Math.random()*.05+.015}));
function draw(){
x.clearRect(0,0,W,H);
bars.forEach(b=>{b.y+=b.speed;if(b.y>H)b.y=0;if(b.y<0)b.y=H;x.fillStyle=`rgba(255,50,80,${b.o})`;x.fillRect(b.x,b.y,b.w,1);});
pts.forEach(p=>{p.px+=p.vx;p.py+=p.vy;if(p.px<0)p.px=W;if(p.px>W)p.px=0;if(p.py<0)p.py=H;if(p.py>H)p.py=0;x.beginPath();x.arc(p.px,p.py,p.r,0,Math.PI*2);x.fillStyle=`rgba(255,50,80,${p.o})`;x.fill();});
requestAnimationFrame(draw);
}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>