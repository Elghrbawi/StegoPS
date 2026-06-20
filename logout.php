<?php
require_once 'php/config/database.php';
require_once 'php/includes/auth.php';
$auth = new Auth();
if (!$auth->isLoggedIn()) { header('Location: '.SITE_URL.'/index.php'); exit; }
if (isset($_GET['confirm'])) { $auth->logout(); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StegoPS &mdash; Logging Out</title>
<link rel="icon" href="logo.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;overflow:hidden;background:#050c0e;font-family:'Space Mono',monospace}
.gl-wrap{position:fixed;inset:0;z-index:9999;overflow:hidden;background:#050c0e}
.gl-scan{position:absolute;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(255,77,109,.025) 2px,rgba(255,77,109,.025) 4px);animation:scanScroll .35s linear infinite;pointer-events:none}
@keyframes scanScroll{from{background-position:0 0}to{background-position:0 4px}}
.gl-r{position:absolute;inset:0;background:#050c0e;mix-blend-mode:screen;opacity:.7;animation:glitchR .75s ease forwards}
.gl-g{position:absolute;inset:0;background:rgba(255,77,109,.12);mix-blend-mode:screen;animation:glitchG .75s ease forwards}
.gl-b{position:absolute;inset:0;background:rgba(124,58,237,.12);mix-blend-mode:screen;animation:glitchB .75s ease forwards}
.gl-line{position:absolute;left:0;right:0;height:2px;background:#ff4d6d;box-shadow:0 0 12px #ff4d6d,0 0 24px #ff4d6d;animation:glitchLine .75s ease forwards}
.gl-line2{position:absolute;left:0;right:0;height:1px;background:rgba(255,77,109,.4);animation:glitchLine .75s .08s ease forwards;opacity:.6}
.gl-txt{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:13px;letter-spacing:4px;color:#ff4d6d;text-transform:uppercase;animation:glitchTxt .75s ease forwards;text-shadow:0 0 20px rgba(255,77,109,.8)}
.gl-logo{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#ff4d6d;animation:logoFade .75s ease forwards;text-shadow:0 0 30px rgba(255,77,109,.5)}
.gl-logo span{color:#fff}
.gl-sub{position:absolute;bottom:30%;left:0;right:0;display:flex;align-items:center;justify-content:center;font-size:10px;letter-spacing:6px;color:rgba(255,77,109,.4);animation:subFade .75s .1s ease forwards;text-transform:uppercase}
@keyframes glitchR{0%{clip-path:inset(0 0 100% 0);transform:translate(-3px,0)}15%{clip-path:inset(10% 0 60% 0);transform:translate(3px,0)}35%{clip-path:inset(60% 0 10% 0);transform:translate(4px,0)}55%,100%{clip-path:inset(0 0 0 0);transform:translate(0,0)}}
@keyframes glitchG{0%{clip-path:inset(100% 0 0 0);transform:translate(3px,0)}30%{clip-path:inset(10% 0 60% 0);transform:translate(-4px,0)}55%,100%{clip-path:inset(0 0 0 0);transform:translate(0,0)}}
@keyframes glitchB{0%{clip-path:inset(50% 0 50% 0)}40%{clip-path:inset(70% 0 0 0)}60%,100%{clip-path:inset(0 0 0 0)}}
@keyframes glitchLine{0%{top:-10px;opacity:1}40%{top:40%;opacity:1}70%{top:90%;opacity:.5}100%{top:110%;opacity:0}}
@keyframes glitchTxt{0%,30%{opacity:0}40%{opacity:1;letter-spacing:8px}55%{opacity:1;letter-spacing:4px}80%,100%{opacity:0}}
@keyframes logoFade{0%,45%{opacity:0}55%{opacity:1;transform:scale(1.05)}70%,100%{opacity:0;transform:scale(1)}}
@keyframes subFade{0%,50%{opacity:0}60%{opacity:1}80%,100%{opacity:0}}
</style>
</head>
<body>
<div class="gl-wrap">
<div class="gl-scan"></div>
<div class="gl-r"></div>
<div class="gl-g"></div>
<div class="gl-b"></div>
<div class="gl-line"></div>
<div class="gl-line2"></div>
<div class="gl-txt">SESSION TERMINATED</div>
<div class="gl-logo">Steg<span>&#128065;</span>PS</div>
<div class="gl-sub">ACCESS REVOKED</div>
</div>
<script>
setTimeout(function(){window.location.href='<?= SITE_URL ?>/logout.php?confirm=1';},790);
</script>
</body>
</html>
