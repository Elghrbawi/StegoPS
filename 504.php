<?php
http_response_code(504);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 504 Gateway Timeout</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#060506;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:520px;width:100%}
.clock-wrap{width:130px;height:130px;margin:0 auto 24px;position:relative}
.clock-svg{width:100%;height:100%}
.clock-face{fill:rgba(220,50,200,.05);stroke:rgba(220,50,200,.45);stroke-width:2}
.clock-hand-m{transform-origin:65px 65px;animation:frozenTick 3s ease-in-out infinite}
.clock-hand-h{transform-origin:65px 65px;animation:frozenTick 8s ease-in-out infinite;animation-direction:reverse}
@keyframes frozenTick{0%,80%{transform:rotate(0deg)}81%{transform:rotate(20deg)}82%{transform:rotate(-5deg)}83%,100%{transform:rotate(0deg)}}
.clock-glow{position:absolute;inset:-10px;border-radius:50%;background:radial-gradient(circle,rgba(220,50,200,.1) 0%,transparent 70%);animation:glowPulse 2.5s ease-in-out infinite}
@keyframes glowPulse{0%,100%{opacity:.5}50%{opacity:1}}
.timeout-ring{position:absolute;inset:-18px;border-radius:50%;border:1px dashed rgba(220,50,200,.2);animation:ringRotate 8s linear infinite}
@keyframes ringRotate{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#dc32c8;line-height:1;text-shadow:0 0 50px rgba(220,50,200,.55);animation:timeGlitch 3.5s infinite}
@keyframes timeGlitch{0%,88%,100%{text-shadow:0 0 50px rgba(220,50,200,.55);transform:none}89%{text-shadow:-3px 0 #00e5c8,3px 0 #ffb347;transform:translateX(3px)}91%{transform:none}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(220,50,200,.55);text-transform:uppercase;margin:8px 0 18px}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:22px;line-height:1.8}
.term{background:rgba(6,5,6,.97);border:1px solid rgba(220,50,200,.18);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.2s}.tl:nth-child(2){animation-delay:.7s}.tl:nth-child(3){animation-delay:1.2s}.tl:nth-child(4){animation-delay:1.7s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,80,.9)}.ty{color:rgba(255,200,60,.8)}.tg{color:rgba(0,229,200,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#dc32c8;color:#fff;box-shadow:0 0 20px rgba(220,50,200,.3)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(220,50,200,.5)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #2a1228}.bg:hover{border-color:rgba(220,50,200,.5);color:#dc32c8;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <div class="clock-wrap">
    <div class="clock-glow"></div>
    <div class="timeout-ring"></div>
    <svg class="clock-svg" viewBox="0 0 130 130" fill="none">
      <circle cx="65" cy="65" r="55" class="clock-face"/>
      <circle cx="65" cy="65" r="4" fill="rgba(220,50,200,.8)"/>
      <line x1="65" y1="65" x2="65" y2="22" stroke="rgba(220,50,200,.9)" stroke-width="2.5" stroke-linecap="round" class="clock-hand-m"/>
      <line x1="65" y1="65" x2="92" y2="65" stroke="rgba(220,50,200,.6)" stroke-width="2" stroke-linecap="round" class="clock-hand-h"/>
      <circle cx="65" cy="20" r="2" fill="rgba(220,50,200,.5)"/>
      <circle cx="65" cy="110" r="2" fill="rgba(220,50,200,.5)"/>
      <circle cx="20" cy="65" r="2" fill="rgba(220,50,200,.5)"/>
      <circle cx="110" cy="65" r="2" fill="rgba(220,50,200,.5)"/>
    </svg>
  </div>
  <div class="code">504</div>
  <div class="label">Gateway Timeout &#9632;</div>
  <h1>Time Ran Out Waiting</h1>
  <p class="sub">The upstream server didn't respond in time.<br>The connection timed out before data could be received.</p>
  <div class="term">
    <span class="tl tr">> UPSTREAM_RESPONSE ..... TIMEOUT</span>
    <span class="tl ty">> WAIT_TIME ............. Exceeded limit</span>
    <span class="tl ty">> CONNECTION ............ Dropped</span>
    <span class="tl tg">> ACTION ................ Retry the request</span>
  </div>
  <div class="btns">
    <a href="javascript:location.reload()" class="btn bp">&#8635; Retry</a>
    <a href="<?= SITE_URL ?>/index.php" class="btn bg">&#8592; Home</a>
  </div>
</div>
<script>
(function(){
const c=document.getElementById('c'),x=c.getContext('2d');
let W=c.width=innerWidth,H=c.height=innerHeight;
const pts=Array.from({length:50},()=>({px:Math.random()*W,py:Math.random()*H,vx:(Math.random()-.5)*.35,vy:(Math.random()-.5)*.35,r:Math.random()*.8+.2,o:Math.random()*.3+.04}));
function draw(){x.clearRect(0,0,W,H);pts.forEach(p=>{p.px+=p.vx;p.py+=p.vy;if(p.px<0)p.px=W;if(p.px>W)p.px=0;if(p.py<0)p.py=H;if(p.py>H)p.py=0;x.beginPath();x.arc(p.px,p.py,p.r,0,Math.PI*2);x.fillStyle=`rgba(220,50,200,${p.o})`;x.fill();});requestAnimationFrame(draw);}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>