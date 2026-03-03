<?php
http_response_code(413);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 413 Payload Too Large</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#06050a;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:520px;width:100%}
.bucket-wrap{width:120px;height:100px;margin:0 auto 16px;position:relative}
.bucket-svg{width:100%;height:100%}
.overflow-drop{position:absolute;bottom:-8px;left:50%;transform:translateX(-50%);width:12px;height:12px;border-radius:50%;background:rgba(255,90,200,.8);box-shadow:0 0 10px rgba(255,90,200,.6);animation:drip 1.5s ease-in infinite}
@keyframes drip{0%{height:4px;border-radius:50%;opacity:1;transform:translateX(-50%) translateY(0)}60%{height:16px;border-radius:2px;opacity:1}100%{height:4px;border-radius:50%;opacity:0;transform:translateX(-50%) translateY(20px)}}
.fill-bar{background:rgba(255,90,200,.1);border:1px solid rgba(255,90,200,.25);border-radius:8px;height:10px;overflow:hidden;margin-bottom:18px;position:relative}
.fill-inner{height:100%;background:linear-gradient(90deg,rgba(0,229,200,.5),rgba(255,90,200,1));border-radius:8px;animation:fillOverflow 2s ease-in-out infinite}
@keyframes fillOverflow{0%{width:85%}50%{width:105%}51%{width:0%}100%{width:85%}}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#ff5ac8;line-height:1;text-shadow:0 0 50px rgba(255,90,200,.55);animation:payloadGlitch 3.5s infinite}
@keyframes payloadGlitch{0%,88%,100%{transform:none;text-shadow:0 0 50px rgba(255,90,200,.55)}89%{text-shadow:-4px 0 #00e5c8,4px 0 #ff3250;transform:scaleX(1.05)}91%{transform:none}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(255,90,200,.55);text-transform:uppercase;margin:8px 0 14px}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:22px;line-height:1.8}
.term{background:rgba(6,5,10,.97);border:1px solid rgba(255,90,200,.18);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.2s}.tl:nth-child(2){animation-delay:.7s}.tl:nth-child(3){animation-delay:1.2s}.tl:nth-child(4){animation-delay:1.7s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,80,.9)}.ty{color:rgba(255,200,60,.8)}.tg{color:rgba(0,229,200,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#ff5ac8;color:#fff;box-shadow:0 0 20px rgba(255,90,200,.3)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(255,90,200,.5)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #281020}.bg:hover{border-color:rgba(255,90,200,.5);color:#ff5ac8;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <div class="bucket-wrap">
    <svg class="bucket-svg" viewBox="0 0 120 100" fill="none">
      <path d="M20 20 L100 20 L88 90 L32 90 Z" fill="rgba(255,90,200,.06)" stroke="rgba(255,90,200,.4)" stroke-width="2"/>
      <path d="M20 20 L100 20" stroke="rgba(255,90,200,.3)" stroke-width="3" stroke-linecap="round"/>
      <path d="M32 90 L88 90" stroke="rgba(255,90,200,.3)" stroke-width="2.5" stroke-linecap="round"/>
      <rect x="22" y="30" width="76" height="48" rx="2" fill="rgba(255,90,200,.25)" clip-path="url(#bucket-clip)"/>
      <clipPath id="bucket-clip"><path d="M22 20 L98 20 L86 90 L34 90 Z"/></clipPath>
      <text x="60" y="60" text-anchor="middle" fill="rgba(255,90,200,.9)" font-family="Space Mono,monospace" font-size="22" font-weight="700">!!</text>
    </svg>
    <div class="overflow-drop"></div>
  </div>
  <div class="fill-bar"><div class="fill-inner"></div></div>
  <div class="code">413</div>
  <div class="label">Payload Too Large &#9642;</div>
  <h1>Your Payload Overflowed the Container</h1>
  <p class="sub">The file or data you uploaded exceeds the<br>maximum allowed size for this server.</p>
  <div class="term">
    <span class="tl tr">> PAYLOAD_SIZE .......... EXCEEDS LIMIT</span>
    <span class="tl ty">> MAX_ALLOWED ........... 50 MB</span>
    <span class="tl ty">> RECEIVED .............. Too large</span>
    <span class="tl tg">> ACTION ................ Reduce file size and retry</span>
  </div>
  <div class="btns">
    <a href="javascript:history.back()" class="btn bp">&#8592; Go Back</a>
    <a href="<?= SITE_URL ?>/embed.php" class="btn bg">Try Again</a>
  </div>
</div>
<script>
(function(){
const c=document.getElementById('c'),x=c.getContext('2d');
let W=c.width=innerWidth,H=c.height=innerHeight;
const pts=Array.from({length:50},()=>({px:Math.random()*W,py:Math.random()*H,vx:(Math.random()-.5)*.4,vy:(Math.random()-.5)*.4,r:Math.random()*.8+.2,o:Math.random()*.3+.04}));
function draw(){x.clearRect(0,0,W,H);pts.forEach(p=>{p.px+=p.vx;p.py+=p.vy;if(p.px<0)p.px=W;if(p.px>W)p.px=0;if(p.py<0)p.py=H;if(p.py>H)p.py=0;x.beginPath();x.arc(p.px,p.py,p.r,0,Math.PI*2);x.fillStyle=`rgba(255,90,200,${p.o})`;x.fill();});requestAnimationFrame(draw);}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>