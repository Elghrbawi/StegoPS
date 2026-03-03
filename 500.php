<?php
http_response_code(500);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 500 Server Error</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#060402;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:520px;width:100%}
.core-wrap{width:150px;height:150px;margin:0 auto 26px;position:relative;display:flex;align-items:center;justify-content:center}
.core-ring{position:absolute;border-radius:50%;animation:coreRing 1.5s ease-in-out infinite}
.core-ring:nth-child(1){inset:0;border:2px solid rgba(255,120,0,.4);animation-delay:0s}
.core-ring:nth-child(2){inset:12px;border:1.5px solid rgba(255,80,0,.3);animation-delay:.25s}
.core-ring:nth-child(3){inset:24px;border:1px solid rgba(255,50,0,.2);animation-delay:.5s}
@keyframes coreRing{0%,100%{transform:scale(1);opacity:.8}50%{transform:scale(1.08);opacity:1}}
.core-inner{width:58px;height:58px;border-radius:50%;background:radial-gradient(circle at 35% 35%,#fff8e0,#ffa020 30%,#ff4000 60%,rgba(180,20,0,.8));box-shadow:0 0 40px #ff6010,0 0 80px rgba(255,80,0,.4),0 0 120px rgba(255,40,0,.15);animation:corePulse 1.2s ease-in-out infinite;z-index:2}
@keyframes corePulse{0%,100%{transform:scale(1);box-shadow:0 0 40px #ff6010,0 0 80px rgba(255,80,0,.4)}50%{transform:scale(1.12);box-shadow:0 0 60px #ff8020,0 0 120px rgba(255,80,0,.6),0 0 180px rgba(255,40,0,.25)}}
.spikes{position:absolute;inset:0;animation:spikeRot 3s linear infinite}
@keyframes spikeRot{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.spike{position:absolute;left:50%;top:0;width:2px;height:24px;background:linear-gradient(to bottom,rgba(255,180,0,.8),transparent);transform-origin:1px 75px;border-radius:1px}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#ff5010;line-height:1;text-shadow:0 0 50px rgba(255,80,16,.65),0 0 100px rgba(255,60,0,.25);animation:meltGlitch 2s infinite}
@keyframes meltGlitch{0%,78%,100%{text-shadow:0 0 50px rgba(255,80,16,.65);transform:none}79%{text-shadow:-5px 2px #ffee00,5px -2px #ff0040;transform:skewX(-4deg) translateX(3px)}81%{text-shadow:5px 2px #ffee00,-5px -2px #7c3aed;transform:skewX(3deg) translateX(-2px)}83%{text-shadow:0 0 50px rgba(255,80,16,.65);transform:none}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(255,100,16,.6);text-transform:uppercase;margin:8px 0 14px}
.alert-bar{background:repeating-linear-gradient(-45deg,rgba(255,100,0,.1),rgba(255,100,0,.1) 10px,rgba(255,180,0,.05) 10px,rgba(255,180,0,.05) 20px);border:1px solid rgba(255,100,0,.25);border-radius:6px;padding:8px 14px;margin-bottom:18px;font-family:'JetBrains Mono',monospace;font-size:10px;color:rgba(255,160,20,.8);letter-spacing:1px;animation:alertBlink 1.8s step-end infinite}
@keyframes alertBlink{50%{border-color:rgba(255,100,0,.5);background:repeating-linear-gradient(-45deg,rgba(255,100,0,.15),rgba(255,100,0,.15) 10px,rgba(255,180,0,.08) 10px,rgba(255,180,0,.08) 20px)}}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:22px;line-height:1.8}
.term{background:rgba(6,4,2,.97);border:1px solid rgba(255,100,16,.2);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.1s}.tl:nth-child(2){animation-delay:.5s}.tl:nth-child(3){animation-delay:.9s}.tl:nth-child(4){animation-delay:1.3s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,60,.9)}.ty{color:rgba(255,200,20,.85)}.tg{color:rgba(0,229,200,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#ff5010;color:#fff;box-shadow:0 0 20px rgba(255,80,16,.35)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(255,80,16,.6)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #2a1a10}.bg:hover{border-color:rgba(255,100,16,.5);color:#ff8040;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <div class="core-wrap">
    <div class="core-ring"></div><div class="core-ring"></div><div class="core-ring"></div>
    <div class="spikes" id="spikes"></div>
    <div class="core-inner"></div>
  </div>
  <div class="code">500</div>
  <div class="label">&#9888; Critical Failure &#9888;</div>
  <div class="alert-bar">&#9632; SYSTEM ALERT: Core process terminated unexpectedly &#9632;</div>
  <h1>The Encryption Engine Melted Down</h1>
  <p class="sub">An unhandled exception caused the server to crash.<br>The error has been logged. Restart in progress.</p>
  <div class="term">
    <span class="tl tr">> CRITICAL: Unhandled exception in core engine</span>
    <span class="tl tr">> PROCESS: Terminated with code SIGSEGV</span>
    <span class="tl ty">> RECOVERY: Auto-restart sequence initiated</span>
    <span class="tl tg">> ETA: Services restoring shortly</span>
  </div>
  <div class="btns">
    <a href="<?= SITE_URL ?>/dashboard.php" class="btn bp">&#8592; Dashboard</a>
    <a href="javascript:location.reload()" class="btn bg">&#8635; Retry</a>
  </div>
</div>
<script>
(function(){
const spikes=document.getElementById('spikes');
for(let i=0;i<12;i++){const d=document.createElement('div');d.className='spike';d.style.transform=`rotate(${i*30}deg) translateX(-1px)`;spikes.appendChild(d);}
const c=document.getElementById('c'),x=c.getContext('2d');
let W=c.width=innerWidth,H=c.height=innerHeight;
const CX=W/2,CY=H/2;
const embers=Array.from({length:120},()=>newEmber());
function newEmber(){
const angle=Math.random()*Math.PI*2;
const speed=Math.random()*2.5+.5;
return{x:W*.5+(Math.random()-.5)*W*.6,y:H*.5+(Math.random()-.5)*H*.5,vx:Math.cos(angle)*speed*(Math.random()>.7?3:1),vy:Math.sin(angle)*speed*(Math.random()>.7?3:1)-Math.random()*.5,r:Math.random()*2.5+.4,o:Math.random()*.8+.15,decay:Math.random()*.008+.002,hot:Math.random()>.4};
}
const cracks=[];
for(let i=0;i<8;i++){
const angle=Math.random()*Math.PI*2;
const pts=[{x:W/2,y:H/2}];
let cx=W/2,cy=H/2;
for(let j=0;j<6;j++){cx+=Math.cos(angle+(Math.random()-.5)*.8)*(Math.random()*80+40);cy+=Math.sin(angle+(Math.random()-.5)*.8)*(Math.random()*80+40);pts.push({x:cx,y:cy});}
cracks.push({pts,o:Math.random()*.3+.05});
}
function draw(){
x.clearRect(0,0,W,H);
cracks.forEach(cr=>{
x.strokeStyle=`rgba(255,100,0,${cr.o*(.7+Math.random()*.3)})`;
x.lineWidth=1;
x.beginPath();
cr.pts.forEach((p,i)=>i===0?x.moveTo(p.x,p.y):x.lineTo(p.x,p.y));
x.stroke();
});
const grd=x.createRadialGradient(W/2,H/2,0,W/2,H/2,Math.min(W,H)*.45);
grd.addColorStop(0,'rgba(255,120,0,.04)');
grd.addColorStop(.6,'rgba(255,60,0,.015)');
grd.addColorStop(1,'transparent');
x.fillStyle=grd;x.fillRect(0,0,W,H);
embers.forEach((e,i)=>{
e.x+=e.vx;e.y+=e.vy;e.vy+=.04;e.o-=e.decay;
if(e.o<=0||e.y>H+10){embers[i]=newEmber();}
const col=e.hot?`rgba(255,${160+Math.random()*80},20,${e.o})`:`rgba(255,${60+Math.random()*60},0,${e.o})`;
x.beginPath();x.arc(e.x,e.y,e.r,0,Math.PI*2);x.fillStyle=col;x.fill();
});
requestAnimationFrame(draw);
}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>