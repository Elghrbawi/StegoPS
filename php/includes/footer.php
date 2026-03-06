<?php ?>
</main>
</div>
<?php if($auth->isGuest()): ?>
<div id="upgrade-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
<div class="modal-card animate-in">
<div class="modal-title">Create Your Free Account</div>
<div class="modal-sub">Just a username and password. Email is optional.<br>Your local guest data will carry over.</div>
<div id="upgrade-error" class="field-error-msg" style="display:none"></div>
<div class="input-group">
<label class="input-label">Username <span class="required-star">*</span></label>
<div class="input-wrap">
<span class="input-icon">&#9678;</span>
<input type="text" class="input has-icon" id="up-username" placeholder="Choose a username..." autocomplete="username">
<span class="input-status" id="up-uname-status"></span>
</div>
<div class="field-hint" id="up-uname-hint"></div>
</div>
<div class="input-group">
<label class="input-label">Password <span class="required-star">*</span></label>
<div class="input-wrap">
<span class="input-icon">&#11041;</span>
<input type="password" class="input has-icon" id="up-password" placeholder="Your password" autocomplete="new-password">
<button type="button" class="input-eye" onclick="togglePass('up-password',this)">&#9673;</button>
</div>
</div>
<div class="input-group" style="margin-bottom:0">
<label class="input-label">Email <span class="optional-tag">(optional)</span></label>
<div class="input-wrap">
<span class="input-icon" style="font-size:12px;font-family:'Space Mono',monospace">@</span>
<input type="email" class="input has-icon" id="up-email" placeholder="your@email.com (optional)" autocomplete="email">
</div>
</div>
<div class="modal-actions">
<button class="btn btn-primary btn-full" id="upgrade-btn" onclick="doGuestUpgrade()">
<span class="btn-text">&#11041; Create Account</span>
<span class="btn-loader" style="display:none">&#9676;</span>
</button>
<button class="btn btn-ghost" onclick="document.getElementById('upgrade-modal').style.display='none'">Later</button>
</div>
</div>
</div>
<?php endif; ?>
<script src="<?= SITE_URL ?>/frontend/js/main.js"></script>
<?php if($auth->isGuest()): ?>
<script>
initFieldCheck('up-username','up-uname-status','up-uname-hint','username','<?= SITE_URL ?>/php/api/check_availability.php');
function _gcBgAnim(canvas,inner){
const ctx=canvas.getContext('2d');
let W=canvas.width=inner.offsetWidth,H=canvas.height=inner.offsetHeight;
const chars='01ABCDEFabcdef⬡⊕⊗⛓◆░▒';
const cols=[];const sp=22;const n=Math.ceil(W/sp)+2;
for(let i=0;i<n;i++)cols.push({x:i*sp,y:Math.random()*-H,speed:.45+Math.random()*.8,char:chars[Math.floor(Math.random()*chars.length)],ct:Math.floor(Math.random()*28)});
(function draw(){
ctx.clearRect(0,0,W,H);
cols.forEach(c=>{c.y+=c.speed;if(c.y>H+14)c.y=-14;c.ct--;if(c.ct<=0){c.char=chars[Math.floor(Math.random()*chars.length)];c.ct=9+Math.floor(Math.random()*24);}
ctx.font='10px "JetBrains Mono",monospace';ctx.globalAlpha=.045;ctx.fillStyle='#00e5c8';ctx.fillText(c.char,c.x,c.y);});
ctx.globalAlpha=1;requestAnimationFrame(draw);
})();
}
function showConvertOverlay(){
if(document.getElementById('gc-overlay'))return null;
const ov=document.createElement('div');ov.className='guest-convert-overlay';ov.id='gc-overlay';
const inner=document.createElement('div');inner.className='guest-convert-inner';
const canvas=document.createElement('canvas');canvas.className='gc-canvas';canvas.id='gc-bg-canvas';
inner.appendChild(canvas);
const hdrTxt=document.createElement('div');hdrTxt.className='gc-header-txt';hdrTxt.textContent='⬡ IDENTITY PROTOCOL';inner.appendChild(hdrTxt);
const term=document.createElement('div');term.className='gc-terminal';
const tHdr=document.createElement('div');tHdr.className='gc-terminal-header';
tHdr.innerHTML='<span class="terminal-dot red" style="cursor:default"></span><span class="terminal-dot yellow" style="cursor:default"></span><span class="terminal-dot green" style="cursor:default"></span><span class="terminal-title" style="margin-left:4px">Account Creation Engine v2</span>';
const body=document.createElement('div');body.className='gc-terminal-body';body.id='gc-term-body';
term.appendChild(tHdr);term.appendChild(body);inner.appendChild(term);
const barWrap=document.createElement('div');barWrap.className='gc-bar-wrap';
barWrap.innerHTML='<div class="gc-bar-track"><div class="gc-bar-fill" id="gc-bar-fill"></div></div><div class="gc-status" id="gc-status">INITIALIZING...</div>';
inner.appendChild(barWrap);
ov.appendChild(inner);document.body.appendChild(ov);
setTimeout(()=>{const c=document.getElementById('gc-bg-canvas');if(c)_gcBgAnim(c,inner);},60);
return body;
}
function removeConvertOverlay(){const el=document.getElementById('gc-overlay');if(el){el.style.animation='backdropIn .2s ease reverse';setTimeout(()=>el.remove(),200);}}
function setConvertBar(pct,status){
const bar=document.getElementById('gc-bar-fill');const st=document.getElementById('gc-status');
if(bar)bar.style.width=Math.min(100,pct)+'%';if(st&&status)st.textContent=status;
}
function doGuestUpgrade(){
const btn=document.getElementById('upgrade-btn');
const errEl=document.getElementById('upgrade-error');
const uname=document.getElementById('up-username').value.trim();
const pw=document.getElementById('up-password').value;
const email=document.getElementById('up-email').value.trim();
if(!uname||!pw){showInlineError(errEl,'Fill in username and password');return;}
document.getElementById('upgrade-modal').style.display='none';
const termBody=showConvertOverlay();
if(!termBody)return;
const guestData=localStorage.getItem('stego_guest_ops')||'[]';
const guestChains=localStorage.getItem('stego_guest_chains')||'[]';
let chainCount=0;try{chainCount=JSON.parse(guestChains).length;}catch(e){}
const steps=[
'> Validating username identifier: '+uname+'...',
'> Generating cryptographic salt [256-bit CSPRNG]...',
'> Hashing password with bcrypt [cost:12]...',
'> Allocating user record in database...',
'> Generating personal steg key [256-bit]...',
chainCount>0?('> Migrating '+chainCount+' guest chain'+(chainCount>1?'s':'')+' to account...'):'> No guest chains to migrate.',
'> Transferring session data...',
'> Establishing authenticated session...',
'> Finalizing account setup...'
];
const apiPromise=fetch('<?= SITE_URL ?>/php/api/auth_ajax.php',{method:'POST',body:new URLSearchParams({action:'guest_upgrade',username:uname,password:pw,email:email,guest_data:guestData,guest_chains:guestChains})}).then(r=>r.json());
const cursor=document.createElement('span');cursor.className='t-cursor';termBody.appendChild(cursor);
let idx=0;
function nextStep(){
if(!document.getElementById('gc-overlay'))return;
if(idx<steps.length){
cursor.remove();
const div=document.createElement('div');div.className='t-line';div.textContent=steps[idx];termBody.appendChild(div);termBody.scrollTop=termBody.scrollHeight;
idx++;termBody.appendChild(cursor);
setConvertBar(Math.round(idx/steps.length*88),'PROCESSING \u2014 '+Math.round(idx/steps.length*88)+'%');
setTimeout(nextStep,150+Math.random()*110);
}else{
cursor.remove();
setConvertBar(95,'AWAITING SERVER RESPONSE...');
apiPromise.then(d=>{
if(d.success){
const ok=document.createElement('div');ok.className='t-line ok';ok.textContent='\u2713 ACCOUNT CREATED \u2014 WELCOME, '+uname.toUpperCase()+'!';termBody.appendChild(ok);termBody.scrollTop=termBody.scrollHeight;
setConvertBar(100,'COMPLETE \u2014 REDIRECTING...');
localStorage.removeItem('stego_guest_ops');
localStorage.removeItem('stego_guest_chains');
setTimeout(()=>triggerGlitchTransition(d.redirect,true),800);
}else{
removeConvertOverlay();
document.getElementById('upgrade-modal').style.display='flex';
setLoading(btn,false);
showInlineError(errEl,d.message);
if(d.field)document.getElementById('up-'+d.field)?.classList.add('input-error');
}
}).catch(()=>{
removeConvertOverlay();
document.getElementById('upgrade-modal').style.display='flex';
setLoading(btn,false);
showInlineError(errEl,'Network error \u2014 please try again');
});
}
}
setTimeout(nextStep,80);
}
</script>
<?php endif; ?>
</body>
</html>