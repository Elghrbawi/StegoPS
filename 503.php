<?php
http_response_code(503);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 503 Service Unavailable</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#04060a;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:520px;width:100%}
.server-wrap{width:140px;height:120px;margin:0 auto 24px;position:relative}
.server-svg{width:100%;height:100%}
.srv-body{fill:rgba(0,150,220,.06);stroke:rgba(0,150,220,.4);stroke-width:1.5}
.srv-light{animation:blinkLight 1.8s ease-in-out infinite}
.srv-light:nth-child(2){animation-delay:.6s}.srv-light:nth-child(3){animation-delay:1.2s}
@keyframes blinkLight{0%,100%{opacity:.2}50%{opacity:1}}
.zzz{position:absolute;top:-10px;right:10px;display:flex;flex-direction:column;gap:2px;align-items:flex-end}
.z{font-family:'Space Mono',monospace;font-weight:700;color:rgba(0,160,255,.7);animation:zFloat 2s ease-in-out infinite}
.z:nth-child(1){font-size:10px;animation-delay:0s}.z:nth-child(2){font-size:14px;animation-delay:.5s}.z:nth-child(3){font-size:18px;animation-delay:1s}
@keyframes zFloat{0%,100%{opacity:.3;transform:translateY(0)}50%{opacity:.9;transform:translateY(-6px)}}
.progress-bar-wrap{background:rgba(0,150,220,.08);border:1px solid rgba(0,150,220,.2);border-radius:30px;height:6px;margin-bottom:22px;overflow:hidden}
.progress-bar-fill{height:100%;background:linear-gradient(90deg,rgba(0,150,220,.6),rgba(0,229,200,.8));border-radius:30px;animation:progressLoop 4s ease-in-out infinite}
@keyframes progressLoop{0%{width:0%;opacity:1}80%{width:100%;opacity:1}90%{width:100%;opacity:.3}100%{width:0%;opacity:0}}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#0096dc;line-height:1;text-shadow:0 0 50px rgba(0,150,220,.55);animation:codeBreath 3s ease-in-out infinite}
@keyframes codeBreath{0%,100%{text-shadow:0 0 50px rgba(0,150,220,.55)}50%{text-shadow:0 0 80px rgba(0,150,220,.85),0 0 120px rgba(0,150,220,.3)}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(0,150,220,.55);text-transform:uppercase;margin:8px 0 14px}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:18px;line-height:1.8}
.term{background:rgba(4,6,10,.97);border:1px solid rgba(0,150,220,.18);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.2s}.tl:nth-child(2){animation-delay:.7s}.tl:nth-child(3){animation-delay:1.2s}.tl:nth-child(4){animation-delay:1.7s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,80,.9)}.ty{color:rgba(255,200,60,.8)}.tg{color:rgba(0,229,200,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#0096dc;color:#fff;box-shadow:0 0 20px rgba(0,150,220,.3)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(0,150,220,.5)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #0d1a26}.bg:hover{border-color:rgba(0,150,220,.5);color:#0096dc;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <div class="server-wrap">
    <div class="zzz"><span class="z">z</span><span class="z">z</span><span class="z">Z</span></div>
    <svg class="server-svg" viewBox="0 0 140 120" fill="none">
      <rect x="20" y="10" width="100" height="28" rx="5" class="srv-body"/>
      <rect x="20" y="46" width="100" height="28" rx="5" class="srv-body"/>
      <rect x="20" y="82" width="100" height="28" rx="5" class="srv-body"/>
      <circle cx="108" cy="24" r="5" fill="rgba(0,229,200,.15)" stroke="rgba(0,229,200,.6)" stroke-width="1.5" class="srv-light"/>
      <circle cx="108" cy="60" r="5" fill="rgba(0,229,200,.15)" stroke="rgba(0,229,200,.4)" stroke-width="1.5" class="srv-light"/>
      <circle cx="108" cy="96" r="5" fill="rgba(255,150,0,.1)" stroke="rgba(255,150,0,.5)" stroke-width="1.5" class="srv-light"/>
      <line x1="30" y1="24" x2="90" y2="24" stroke="rgba(0,150,220,.3)" stroke-width="1.5" stroke-dasharray="4 3"/>
      <line x1="30" y1="60" x2="70" y2="60" stroke="rgba(0,150,220,.3)" stroke-width="1.5" stroke-dasharray="4 3"/>
    </svg>
  </div>
  <div class="progress-bar-wrap"><div class="progress-bar-fill"></div></div>
  <div class="code">503</div>
  <div class="label">Service Unavailable &#9642;</div>
  <h1>Server Is Taking a Nap</h1>
  <p class="sub">The server is temporarily offline for maintenance<br>or is overloaded. It will return shortly.</p>
  <div class="term">
    <span class="tl tr">> SERVICE_STATUS ........ OFFLINE</span>
    <span class="tl ty">> REASON ................ Maintenance or overload</span>
    <span class="tl ty">> ETA ................... Unknown</span>
    <span class="tl tg">> ACTION ................ Retry in a moment</span>
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
const pts=Array.from({length:40},()=>({px:Math.random()*W,py:Math.random()*H,vx:(Math.random()-.5)*.2,vy:(Math.random()-.5)*.2,r:Math.random()*.7+.2,o:Math.random()*.2+.03}));
function draw(){x.clearRect(0,0,W,H);pts.forEach(p=>{p.px+=p.vx;p.py+=p.vy;if(p.px<0)p.px=W;if(p.px>W)p.px=0;if(p.py<0)p.py=H;if(p.py>H)p.py=0;x.beginPath();x.arc(p.px,p.py,p.r,0,Math.PI*2);x.fillStyle=`rgba(0,150,220,${p.o})`;x.fill();});requestAnimationFrame(draw);}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>