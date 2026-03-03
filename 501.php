<?php
http_response_code(501);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 501 Not Implemented</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#030a06;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:520px;width:100%}
.robot{font-size:80px;display:block;margin:0 auto 20px;animation:robotShake 2s ease-in-out infinite;filter:drop-shadow(0 0 20px rgba(80,200,100,.4))}
@keyframes robotShake{0%,100%{transform:translateX(0) rotate(0deg)}20%{transform:translateX(-4px) rotate(-3deg)}40%{transform:translateX(4px) rotate(3deg)}60%{transform:translateX(-3px) rotate(-2deg)}80%{transform:translateX(3px) rotate(2deg)}}
.gear{display:inline-block;font-size:20px;animation:spin 4s linear infinite;opacity:.5}
.gear.rev{animation-direction:reverse;animation-duration:3s}
.gears-row{margin-bottom:16px;display:flex;gap:6px;justify-content:center;align-items:center}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#4ec97a;line-height:1;text-shadow:0 0 50px rgba(78,201,122,.5);animation:codeFlicker 5s infinite}
@keyframes codeFlicker{0%,90%,100%{opacity:1}91%{opacity:.3}92%{opacity:1}93%{opacity:.6}94%{opacity:1}}
@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(78,201,122,.55);text-transform:uppercase;margin:8px 0 18px}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:22px;line-height:1.8}
.term{background:rgba(3,10,6,.97);border:1px solid rgba(78,201,122,.2);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.2s}.tl:nth-child(2){animation-delay:.7s}.tl:nth-child(3){animation-delay:1.2s}.tl:nth-child(4){animation-delay:1.7s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,80,.9)}.ty{color:rgba(255,200,60,.8)}.tg{color:rgba(78,201,122,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#4ec97a;color:#020804;box-shadow:0 0 20px rgba(78,201,122,.3)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(78,201,122,.5)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #1a2d20}.bg:hover{border-color:rgba(78,201,122,.5);color:#4ec97a;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <span class="robot">🤖</span>
  <div class="gears-row"><span class="gear">⚙</span><span class="gear rev">⚙</span><span class="gear">⚙</span><span class="gear rev">⚙</span><span class="gear">⚙</span></div>
  <div class="code">501</div>
  <div class="label">Not Implemented &#9632;</div>
  <h1>Feature Not Built Yet</h1>
  <p class="sub">The server recognized the request but lacks the<br>ability to fulfill it. This feature is under construction.</p>
  <div class="term">
    <span class="tl tr">> METHOD_HANDLER ........ NOT_FOUND</span>
    <span class="tl ty">> STATUS ................ Under construction</span>
    <span class="tl ty">> IMPLEMENTATION ........ Pending</span>
    <span class="tl tg">> ACTION ................ Return to dashboard</span>
  </div>
  <div class="btns">
    <a href="<?= SITE_URL ?>/dashboard.php" class="btn bp">&#8592; Dashboard</a>
    <a href="<?= SITE_URL ?>/index.php" class="btn bg">Home</a>
  </div>
</div>
<script>
(function(){
const c=document.getElementById('c'),x=c.getContext('2d');
let W=c.width=innerWidth,H=c.height=innerHeight;
const pts=Array.from({length:50},()=>({px:Math.random()*W,py:Math.random()*H,vx:(Math.random()-.5)*.4,vy:(Math.random()-.5)*.4,r:Math.random()*.8+.2,o:Math.random()*.3+.04}));
function draw(){x.clearRect(0,0,W,H);pts.forEach(p=>{p.px+=p.vx;p.py+=p.vy;if(p.px<0)p.px=W;if(p.px>W)p.px=0;if(p.py<0)p.py=H;if(p.py>H)p.py=0;x.beginPath();x.arc(p.px,p.py,p.r,0,Math.PI*2);x.fillStyle=`rgba(78,201,122,${p.o})`;x.fill();});requestAnimationFrame(draw);}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>