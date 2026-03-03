<?php
http_response_code(404);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 404 Not Found</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#030509;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:520px;width:100%}
.void{width:160px;height:160px;margin:0 auto 24px;position:relative;display:flex;align-items:center;justify-content:center}
.void-ring{position:absolute;border-radius:50%;border:1px solid rgba(124,58,237,.3);animation:voidPulse 3s ease-in-out infinite}
.void-ring:nth-child(1){inset:0;animation-delay:0s}
.void-ring:nth-child(2){inset:12px;border-color:rgba(124,58,237,.2);animation-delay:.8s}
.void-ring:nth-child(3){inset:24px;border-color:rgba(124,58,237,.15);animation-delay:1.6s}
@keyframes voidPulse{0%,100%{transform:scale(1) rotate(0deg);opacity:1}50%{transform:scale(.95) rotate(180deg);opacity:.4}}
.void-core{width:60px;height:60px;border-radius:50%;background:radial-gradient(circle at 40% 40%,rgba(124,58,237,.4),rgba(124,58,237,.1) 50%,transparent);border:1px solid rgba(124,58,237,.5);box-shadow:0 0 30px rgba(124,58,237,.4),inset 0 0 20px rgba(124,58,237,.2);animation:coreSwirl 4s ease-in-out infinite;z-index:2}
@keyframes coreSwirl{0%,100%{box-shadow:0 0 30px rgba(124,58,237,.4),inset 0 0 20px rgba(124,58,237,.2)}50%{box-shadow:0 0 60px rgba(124,58,237,.7),inset 0 0 40px rgba(124,58,237,.4)}}
.void-txt{position:absolute;font-family:'Space Mono',monospace;font-size:9px;color:rgba(124,58,237,.6);letter-spacing:1px;animation:orbit 6s linear infinite}
.void-txt:nth-child(5){animation-direction:reverse;font-size:8px;color:rgba(124,58,237,.3)}
@keyframes orbit{from{transform:rotate(0deg) translateX(70px) rotate(0deg)}to{transform:rotate(360deg) translateX(70px) rotate(-360deg)}}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#7c3aed;line-height:1;text-shadow:0 0 50px rgba(124,58,237,.6),0 0 100px rgba(124,58,237,.2);animation:codeGlitch 4s infinite}
@keyframes codeGlitch{0%,86%,100%{text-shadow:0 0 50px rgba(124,58,237,.6)}87%{text-shadow:-5px 0 #00e5c8,5px 0 #ff3250,0 0 50px rgba(124,58,237,.6);transform:skewX(-3deg)}89%{text-shadow:5px 0 #00e5c8,-5px 0 #ff3250;transform:skewX(2deg)}91%{text-shadow:0 0 50px rgba(124,58,237,.6);transform:none}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(124,58,237,.55);text-transform:uppercase;margin:8px 0 14px}
.path-box{font-family:'JetBrains Mono',monospace;font-size:11px;color:rgba(160,120,255,.75);background:rgba(124,58,237,.06);border:1px solid rgba(124,58,237,.2);border-radius:7px;padding:9px 14px;margin-bottom:18px;word-break:break-all;text-align:left}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:22px;line-height:1.8}
.term{background:rgba(3,5,9,.96);border:1px solid rgba(124,58,237,.2);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.2s}.tl:nth-child(2){animation-delay:.7s}.tl:nth-child(3){animation-delay:1.2s}.tl:nth-child(4){animation-delay:1.7s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,80,.9)}.ty{color:rgba(255,200,60,.8)}.tg{color:rgba(0,229,200,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#7c3aed;color:#fff;box-shadow:0 0 20px rgba(124,58,237,.35)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(124,58,237,.6)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #1a2035}.bg:hover{border-color:rgba(124,58,237,.5);color:#a78bfa;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <div class="void">
    <div class="void-ring"></div><div class="void-ring"></div><div class="void-ring"></div>
    <div class="void-core"></div>
    <span class="void-txt">NULL</span>
    <span class="void-txt">VOID</span>
  </div>
  <div class="code">404</div>
  <div class="label">Data Lost &#8901; Payload Not Found</div>
  <h1>The Payload Was Hidden Too Well</h1>
  <p class="sub">This resource doesn't exist or was deleted.<br>The data fell into the void and cannot be recovered.</p>
  <div class="path-box">> REQUEST: <?= htmlspecialchars($_SERVER['REQUEST_URI']??'/') ?></div>
  <div class="term">
    <span class="tl tr">> DEEP_SCAN ............ 0 matches</span>
    <span class="tl tr">> LAYER_SEARCH ......... All layers checked</span>
    <span class="tl ty">> PAYLOAD .............. null</span>
    <span class="tl tg">> SUGGESTION ........... Return to dashboard</span>
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
const COLS=Math.floor(W/18);
const drops=Array.from({length:COLS},()=>({y:Math.random()*-H,speed:Math.random()*1.5+.5,len:Math.floor(Math.random()*12+4),chars:[]}));
const chars='01アイウエオカキABCDEF⬡◉⬢';
function draw(){
x.fillStyle='rgba(3,5,9,.12)';x.fillRect(0,0,W,H);
x.font='12px JetBrains Mono,monospace';
drops.forEach((d,i)=>{
const col=i*18+9;
for(let j=0;j<d.len;j++){
const row=d.y-j*14;
if(row<0||row>H)continue;
const a=j===0?1:Math.max(0,(.9-(j/d.len)*.85));
x.fillStyle=j===0?`rgba(200,160,255,${a})`:`rgba(124,58,237,${a*.7})`;
x.fillText(chars[Math.floor(Math.random()*chars.length)],col,row);
}
d.y+=d.speed;
if(d.y>H+d.len*14){d.y=Math.random()*-200;d.speed=Math.random()*1.5+.5;}
});
requestAnimationFrame(draw);
}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>