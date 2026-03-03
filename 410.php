<?php
http_response_code(410);
require_once 'php/config/database.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>StegoPS — 410 Gone</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;overflow:hidden}
body{font-family:'Syne',sans-serif;background:#04050a;color:#e8f4f1;display:flex;align-items:center;justify-content:center}
canvas{position:fixed;inset:0;z-index:0;pointer-events:none}
.scene{position:relative;z-index:1;text-align:center;padding:40px 24px;max-width:520px;width:100%}
.erased{width:180px;height:50px;margin:0 auto 24px;position:relative;display:flex;align-items:center;gap:0}
.erased-text{font-family:'JetBrains Mono',monospace;font-size:14px;color:rgba(100,120,160,.5);text-decoration:line-through;text-decoration-color:rgba(100,120,255,.8);text-decoration-thickness:2px;letter-spacing:2px;animation:eraseFade 3s ease-in-out infinite}
@keyframes eraseFade{0%{opacity:.6}50%{opacity:.15}100%{opacity:.6}}
.shred{display:flex;justify-content:center;gap:2px;margin-bottom:20px}
.shred-strip{width:6px;background:linear-gradient(to bottom,rgba(80,100,200,.3),rgba(80,100,200,.1));border-radius:1px;animation:shredFall infinite linear}
.shred-strip:nth-child(1){height:30px;animation-duration:2.1s;animation-delay:0s}
.shred-strip:nth-child(2){height:22px;animation-duration:1.8s;animation-delay:.3s}
.shred-strip:nth-child(3){height:35px;animation-duration:2.4s;animation-delay:.6s}
.shred-strip:nth-child(4){height:18px;animation-duration:1.6s;animation-delay:.9s}
.shred-strip:nth-child(5){height:28px;animation-duration:2.2s;animation-delay:.2s}
.shred-strip:nth-child(6){height:24px;animation-duration:1.9s;animation-delay:.7s}
@keyframes shredFall{0%{opacity:0;transform:translateY(-10px)}20%{opacity:.8}80%{opacity:.5}100%{opacity:0;transform:translateY(20px)}}
.code{font-family:'Space Mono',monospace;font-size:clamp(70px,13vw,108px);font-weight:700;color:#5060c8;line-height:1;text-shadow:0 0 50px rgba(80,100,200,.5);animation:fadeGlitch 4s infinite}
@keyframes fadeGlitch{0%,86%,100%{opacity:1}87%{opacity:.2;text-shadow:-3px 0 #ff3250,3px 0 #00e5c8}89%{opacity:1}}
.label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:4px;color:rgba(80,100,200,.55);text-transform:uppercase;margin:8px 0 18px}
h1{font-size:clamp(16px,3.2vw,24px);font-weight:800;margin-bottom:8px}
.sub{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7aa8a0;margin-bottom:22px;line-height:1.8}
.term{background:rgba(4,5,10,.97);border:1px solid rgba(80,100,200,.2);border-radius:10px;padding:14px 16px;margin-bottom:26px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9}
.tl{display:block;opacity:0;transform:translateX(-8px);animation:ti .3s ease forwards}
.tl:nth-child(1){animation-delay:.2s}.tl:nth-child(2){animation-delay:.7s}.tl:nth-child(3){animation-delay:1.2s}.tl:nth-child(4){animation-delay:1.7s}
@keyframes ti{to{opacity:1;transform:none}}
.tr{color:rgba(255,50,80,.9)}.ty{color:rgba(255,200,60,.8)}.tg{color:rgba(0,229,200,.85)}.td{color:rgba(120,160,155,.25)}
.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .22s;letter-spacing:.4px;border:none;cursor:pointer}
.bp{background:#5060c8;color:#fff;box-shadow:0 0 20px rgba(80,100,200,.3)}.bp:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(80,100,200,.5)}
.bg{background:transparent;color:#7aa8a0;border:1px solid #101428}.bg:hover{border-color:rgba(80,100,200,.5);color:#8090e8;transform:translateY(-2px)}
</style>
</head>
<body>
<canvas id="c"></canvas>
<div class="scene">
  <div class="erased"><span class="erased-text">/resource/permanently/deleted</span></div>
  <div class="shred"><div class="shred-strip"></div><div class="shred-strip"></div><div class="shred-strip"></div><div class="shred-strip"></div><div class="shred-strip"></div><div class="shred-strip"></div></div>
  <div class="code">410</div>
  <div class="label">Permanently Gone &#9632;</div>
  <h1>This Resource Was Erased</h1>
  <p class="sub">The resource was deliberately removed and<br>will not be available again at this location.</p>
  <div class="term">
    <span class="tl tr">> RESOURCE_STATUS ....... PERMANENTLY_DELETED</span>
    <span class="tl tr">> RECOVERY .............. NOT POSSIBLE</span>
    <span class="tl ty">> DIFFERS FROM 404 ...... This will never return</span>
    <span class="tl tg">> ACTION ................ Go back to safety</span>
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
const pts=Array.from({length:45},()=>({px:Math.random()*W,py:Math.random()*H,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,r:Math.random()*.8+.2,o:Math.random()*.25+.04}));
function draw(){x.clearRect(0,0,W,H);pts.forEach(p=>{p.px+=p.vx;p.py+=p.vy;if(p.px<0)p.px=W;if(p.px>W)p.px=0;if(p.py<0)p.py=H;if(p.py>H)p.py=0;x.beginPath();x.arc(p.px,p.py,p.r,0,Math.PI*2);x.fillStyle=`rgba(80,100,200,${p.o})`;x.fill();});requestAnimationFrame(draw);}draw();
window.addEventListener('resize',()=>{W=c.width=innerWidth;H=c.height=innerHeight;});
})();
</script>
</body>
</html>