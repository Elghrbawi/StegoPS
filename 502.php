<?php
http_response_code(502);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 502 Bad Gateway</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#050408;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:520px;width:100%}
.pipeline{display:flex;align-items:center;justify-content:center;gap:0;margin:0 auto 24px;width:260px;height:60px;position:relative}
.pipe-node{width:44px;height:44px;border-radius:50%;border:2px solid;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.pipe-ok{border-color:rgba(0,229,200,.6);background:rgba(0,229,200,.08);color:rgba(0,229,200,.8);animation:nodePulse 2s ease-in-out infinite}
.pipe-broken{border-color:rgba(255,50,80,.7);background:rgba(255,50,80,.1);color:rgba(255,50,80,.9);animation:nodeCrash 1.5s ease-in-out infinite}
@keyframes nodePulse{0%,100%{box-shadow:none}50%{box-shadow:0 0 12px rgba(0,229,200,.3)}}
@keyframes nodeCrash{0%,100%{transform:scale(1)}25%{transform:scale(1.1) rotate(5deg)}75%{transform:scale(.9) rotate(-5deg)}}
.pipe-line{flex:1;height:3px;position:relative;overflow:visible}
.pipe-line-ok{background:rgba(0,229,200,.3)}
.pipe-line-broken{background:rgba(255,50,80,.15);position:relative}
.pipe-line-broken::after{content:'✕';position:absolute;top:-9px;left:50%;transform:translateX(-50%);font-size:16px;color:rgba(255,50,80,.8);animation:xPulse 1s ease-in-out infinite}
@keyframes xPulse{0%,100%{opacity:.5;transform:translateX(-50%) scale(1)}50%{opacity:1;transform:translateX(-50%) scale(1.3)}}
.packet{position:absolute;width:8px;height:8px;border-radius:50%;background:#00e5c8;top:-2.5px;left:0;animation:packetFlow 2s linear infinite}
@keyframes packetFlow{0%{left:0;opacity:1}49%{left:calc(50% - 4px);opacity:1}50%{left:calc(50% - 4px);opacity:0}100%{left:calc(50% - 4px);opacity:0}}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#a855f7;line-height:1;text-shadow:0 0 50px rgba(168,85,247,.5);animation:codeGlitch 4s infinite}
@keyframes codeGlitch{0%,88%,100%{opacity:1;transform:none}89%{text-shadow:-3px 0 #ff3250,3px 0 #00e5c8;transform:skewX(-2deg)}91%{transform:none}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(168,85,247,.55);text-transform:uppercase;margin:8px 0 18px}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:22px;line-height:1.8}
.term{background:rgba(5,4,8,.97);border:1px solid rgba(168,85,247,.2);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.2s}.tl:nth-child(2){animation-delay:.7s}.tl:nth-child(3){animation-delay:1.2s}.tl:nth-child(4){animation-delay:1.7s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,80,.9)}.ty{color:rgba(255,200,60,.8)}.tg{color:rgba(0,229,200,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#a855f7;color:#fff;box-shadow:0 0 20px rgba(168,85,247,.3)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(168,85,247,.5)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #1a152a}.bg:hover{border-color:rgba(168,85,247,.5);color:#a855f7;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <div class="pipeline">
    <div class="pipe-node pipe-ok">&#9673;</div>
    <div class="pipe-line pipe-line-ok" style="position:relative"><div class="packet"></div></div>
    <div class="pipe-node pipe-broken">&#10005;</div>
    <div class="pipe-line pipe-line-broken"></div>
    <div class="pipe-node" style="border-color:rgba(120,160,155,.3);color:rgba(120,160,155,.4);font-size:14px">?</div>
  </div>
  <div class="code">502</div>
  <div class="label">Bad Gateway &#9642;</div>
  <h1>Upstream Pipeline Broken</h1>
  <p class="sub">A gateway or proxy received an invalid response<br>from an upstream server in the chain.</p>
  <div class="term">
    <span class="tl tg">> CLIENT ................ Connected</span>
    <span class="tl tr">> UPSTREAM_SERVER ....... Invalid response</span>
    <span class="tl ty">> GATEWAY ............... Cannot relay</span>
    <span class="tl tg">> ACTION ................ Retry shortly</span>
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
const pts=Array.from({length:50},()=>({px:Math.random()*W,py:Math.random()*H,vx:(Math.random()-.5)*.4,vy:(Math.random()-.5)*.4,r:Math.random()*.8+.2,o:Math.random()*.25+.04}));
function draw(){x.clearRect(0,0,W,H);pts.forEach(p=>{p.px+=p.vx;p.py+=p.vy;if(p.px<0)p.px=W;if(p.px>W)p.px=0;if(p.py<0)p.py=H;if(p.py>H)p.py=0;x.beginPath();x.arc(p.px,p.py,p.r,0,Math.PI*2);x.fillStyle=`rgba(168,85,247,${p.o})`;x.fill();});requestAnimationFrame(draw);}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>