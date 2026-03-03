<?php
http_response_code(403);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 403 Forbidden</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#060304;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:500px;width:100%}
.shield-wrap{width:140px;height:160px;margin:0 auto 28px;position:relative}
.shield-svg{width:100%;height:100%}
.bolt-wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center}
.bolt{font-size:52px;filter:drop-shadow(0 0 16px #ffb020) drop-shadow(0 0 40px rgba(255,160,20,.5));animation:boltZap 2s ease-in-out infinite}
@keyframes boltZap{0%,100%{transform:scale(1);filter:drop-shadow(0 0 16px #ffb020) drop-shadow(0 0 40px rgba(255,160,20,.5))}50%{transform:scale(1.15);filter:drop-shadow(0 0 28px #ffb020) drop-shadow(0 0 60px rgba(255,160,20,.7))}}
.shield-arc{fill:none;stroke:rgba(255,150,20,.55);stroke-width:2.5;stroke-dasharray:8 4;animation:arcSpin 3s linear infinite}
@keyframes arcSpin{from{stroke-dashoffset:0}to{stroke-dashoffset:-48}}
.shield-glow{fill:rgba(255,130,10,.06);stroke:rgba(255,140,15,.4);stroke-width:2;filter:drop-shadow(0 0 12px rgba(255,140,15,.5));animation:glowPulse 2s ease-in-out infinite}
@keyframes glowPulse{0%,100%{opacity:.7}50%{opacity:1}}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#ff9010;line-height:1;text-shadow:0 0 50px rgba(255,140,16,.6),0 0 100px rgba(255,140,16,.2);animation:zapGlitch 3s infinite}
@keyframes zapGlitch{0%,80%,100%{text-shadow:0 0 50px rgba(255,140,16,.6)}81%{text-shadow:-4px 0 #ff3250,4px 0 #ffee20,0 0 50px rgba(255,140,16,.6)}83%{text-shadow:4px 0 #ff3250,-4px 0 #00e5c8}85%{text-shadow:0 0 50px rgba(255,140,16,.6)}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(255,140,16,.6);text-transform:uppercase;margin:8px 0 18px}
.warning-tape{background:repeating-linear-gradient(90deg,rgba(255,140,16,.12) 0px,rgba(255,140,16,.12) 20px,rgba(0,0,0,0) 20px,rgba(0,0,0,0) 40px);height:3px;margin-bottom:18px;animation:tapeSlide 1.5s linear infinite}
@keyframes tapeSlide{from{background-position:0 0}to{background-position:40px 0}}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:22px;line-height:1.8}
.term{background:rgba(6,3,4,.96);border:1px solid rgba(255,140,16,.2);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.2s}.tl:nth-child(2){animation-delay:.7s}.tl:nth-child(3){animation-delay:1.2s}.tl:nth-child(4){animation-delay:1.7s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,80,.9)}.ty{color:rgba(255,200,20,.85)}.tg{color:rgba(0,229,200,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#ff9010;color:#0a0502;box-shadow:0 0 20px rgba(255,140,16,.35)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(255,140,16,.6)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #1a2d35}.bg:hover{border-color:rgba(255,140,16,.5);color:#ff9010;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <div class="shield-wrap">
    <svg class="shield-svg" viewBox="0 0 140 160" fill="none">
      <path class="shield-glow" d="M70 8 L130 30 L130 80 C130 118 70 152 70 152 C70 152 10 118 10 80 L10 30 Z"/>
      <ellipse cx="70" cy="80" rx="52" ry="52" class="shield-arc"/>
      <path d="M70 8 L130 30 L130 80 C130 118 70 152 70 152 C70 152 10 118 10 80 L10 30 Z" fill="none" stroke="rgba(255,150,20,.35)" stroke-width="1.5" stroke-dasharray="3 5"/>
    </svg>
    <div class="bolt-wrap"><span class="bolt">&#9889;</span></div>
  </div>
  <div class="code">403</div>
  <div class="label">Forbidden &#9889; Clearance Denied</div>
  <div class="warning-tape"></div>
  <h1>Insufficient Clearance Level</h1>
  <p class="sub">Your account lacks the permissions for this area.<br>Restricted to authorized personnel only.</p>
  <div class="term">
    <span class="tl tr">> CLEARANCE_CHECK ...... DENIED</span>
    <span class="tl ty">> REQUIRED_ROLE ........ ADMIN</span>
    <span class="tl ty">> YOUR_ROLE ............ USER</span>
    <span class="tl tg">> ACTION ............... Return to safe zone</span>
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
const bolts=[];
function newBolt(){
const sx=Math.random()*W,sy=0;
const pts=[{x:sx,y:sy}];
let cx=sx,cy=sy;
while(cy<H+20){cy+=Math.random()*60+30;cx+=(Math.random()-.5)*120;pts.push({x:cx,y:cy});}
return{pts,life:1,decay:Math.random()*.08+.04,w:Math.random()*1.5+.5};
}
if(Math.random()>.5)bolts.push(newBolt());
let boltTimer=0;
const particles=Array.from({length:40},()=>({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.5,vy:(Math.random()-.5)*.5,r:Math.random()*.7+.2,o:Math.random()*.3+.04}));
function draw(){
x.clearRect(0,0,W,H);
boltTimer++;
if(boltTimer>80+Math.random()*120){boltTimer=0;bolts.push(newBolt());if(bolts.length>5)bolts.shift();}
bolts.forEach((b,i)=>{
b.life-=b.decay;
if(b.life<=0){bolts.splice(i,1);return;}
x.strokeStyle=`rgba(255,200,40,${b.life*.8})`;
x.shadowColor='rgba(255,180,20,.6)';
x.shadowBlur=8;
x.lineWidth=b.w*b.life;
x.beginPath();
b.pts.forEach((p,j)=>j===0?x.moveTo(p.x,p.y):x.lineTo(p.x,p.y));
x.stroke();
x.shadowBlur=0;
});
particles.forEach(p=>{p.x+=p.vx;p.y+=p.vy;if(p.x<0)p.x=W;if(p.x>W)p.x=0;if(p.y<0)p.y=H;if(p.y>H)p.y=0;x.beginPath();x.arc(p.x,p.y,p.r,0,Math.PI*2);x.fillStyle=`rgba(255,140,16,${p.o})`;x.fill();});
requestAnimationFrame(draw);
}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>