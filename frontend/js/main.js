function triggerGlitchTransition(url, isNewUser) {
const ov = document.getElementById('glitch-overlay');
if (!ov) { window.location.href = url; return; }
ov.innerHTML = '<div class="gl-base"></div><div class="gl-scan"></div><div class="gl-r"></div><div class="gl-g"></div><div class="gl-b"></div><div class="gl-line"></div><div class="gl-txt">' + (isNewUser ? 'IDENTITY CREATED' : 'ACCESS GRANTED') + '</div>';
ov.classList.add('active');
setTimeout(() => { window.location.href = url; }, 980);
}
function navSwitch(e, url, dir) {
e.preventDefault();
const card = document.getElementById('auth-card');
if (!card) { window.location.href = url; return false; }
card.classList.add(dir === 'right' ? 'slide-out-left' : 'slide-out-right');
setTimeout(() => { window.location.href = url + '?from=' + (dir === 'right' ? 'login' : 'register'); }, 320);
return false;
}
function showInlineError(el, msg) {
if (!el) return;
el.textContent = msg;
el.style.display = 'flex';
el.classList.remove('show');
void el.offsetWidth;
el.classList.add('show');
}
function clearError(el) {
if (!el) return;
el.style.display = 'none';
el.textContent = '';
}
function showInputError(input, errEl, msg) {
input.classList.add('input-error');
showInlineError(errEl, msg);
setTimeout(() => input.classList.remove('input-error'), 3000);
}
function showStegError(msg) {
let el = document.getElementById('steg-float-error');
if (!el) {
el = document.createElement('div');
el.id = 'steg-float-error';
el.className = 'steg-float-error';
document.body.appendChild(el);
}
el.textContent = msg;
el.style.display = 'flex';
clearTimeout(el._timer);
el._timer = setTimeout(() => { el.style.display = 'none'; }, 4000);
}
function setLoading(btn, loading) {
if (!btn) return;
const txt = btn.querySelector('.btn-text');
const ldr = btn.querySelector('.btn-loader');
if (txt) txt.style.display = loading ? 'none' : 'inline';
if (ldr) ldr.style.display = loading ? 'inline' : 'none';
btn.disabled = loading;
}
let toastTimeout = null;
function showToast(msg, duration) {
clearTimeout(toastTimeout);
let t = document.querySelector('.toast');
if (!t) { t = document.createElement('div'); t.className = 'toast'; document.body.appendChild(t); }
t.textContent = msg;
t.style.opacity = '1';
toastTimeout = setTimeout(() => { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, duration || 2500);
}
function togglePass(id, btn) {
const input = document.getElementById(id) || (btn && btn.closest('.input-wrap') && btn.closest('.input-wrap').querySelector('input'));
if (!input) return;
const showing = input.type !== 'password';
input.type = showing ? 'password' : 'text';
if (btn) btn.textContent = showing ? '\u25C9' : '\uD83D\uDC41';
}
function initPasswordEyes() {
document.querySelectorAll('.input-eye').forEach(btn => {
if (!btn.dataset.init) {
btn.dataset.init = '1';
btn.textContent = '\u25C9';
}
});
}
function initPasswordStrength(inputId, wrapId, barId, labelId, checkIds) {
const input = document.getElementById(inputId);
const wrap  = document.getElementById(wrapId);
const bar   = document.getElementById(barId);
const label = document.getElementById(labelId);
if (!input) return;
input.addEventListener('input', () => {
const v = input.value;
if (!v) { if (wrap) wrap.style.display = 'none'; return; }
if (wrap) wrap.style.display = 'block';
const c = { len: v.length >= 8, upper: /[A-Z]/.test(v), num: /[0-9]/.test(v), sym: /[^a-zA-Z0-9]/.test(v) };
const score = (c.len?25:0)+(c.upper?25:0)+(c.num?25:0)+(c.sym?25:0);
if (bar) { bar.style.width = score+'%'; const colors=['#ff4d6d','#ff7043','#ffb347','#00e5c8']; const idx=Math.min(3,Math.floor(score/26)); bar.style.background=colors[idx]; }
if (label) { const labels=['Weak','Fair','Good','Strong']; label.textContent=score===0?'\u2014':labels[Math.min(3,Math.floor(score/26))]; const c2=['#ff4d6d','#ff7043','#ffb347','#00e5c8']; label.style.color=score===0?'var(--text-muted)':c2[Math.min(3,Math.floor(score/26))]; }
const keys = ['len','upper','num','sym'];
if (checkIds) checkIds.forEach((id, i) => { document.getElementById(id)?.classList.toggle('pass', c[keys[i]]); });
});
}
function initFieldCheck(inputId, statusId, hintId, type, url) {
const input  = document.getElementById(inputId);
const status = document.getElementById(statusId);
const hint   = document.getElementById(hintId);
if (!input) return;
let timer = null;
input.addEventListener('input', () => {
clearTimeout(timer);
const v = input.value.trim();
if (!v) { if(status){status.textContent='';status.className='input-status';} if(hint){hint.textContent='';hint.className='field-hint';} input.style.borderColor=''; return; }
if (status) { status.textContent='\u25CC'; status.className='input-status checking'; }
timer = setTimeout(async () => {
try {
const fd = new FormData(); fd.append('type', type); fd.append('value', v);
const res = await fetch(url, { method: 'POST', body: fd });
const data = await res.json();
if (status) { status.textContent=data.available?'\u2713':'\u2717'; status.className='input-status '+(data.available?'ok':'err'); }
if (hint) { hint.textContent=data.message||''; hint.className='field-hint '+(data.available?'ok':'err'); }
input.style.borderColor = data.available ? 'var(--accent)' : 'var(--danger)';
} catch(e) { if(status){status.textContent='';status.className='input-status';} }
}, 500);
});
input.addEventListener('blur', () => { if(!input.value.trim()){input.style.borderColor='';if(status){status.textContent='';status.className='input-status';}} });
}
function initEmailHint(inputId, statusId, hintId, checkUrl) {
const input  = document.getElementById(inputId);
const hint   = document.getElementById(hintId);
const status = document.getElementById(statusId);
if (!input) return;
const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
let binaryTimer = null;
let debounceTimer = null;
input.addEventListener('input', () => {
const v = input.value.trim();
clearInterval(binaryTimer);
clearTimeout(debounceTimer);
if (!v) {
if(hint){hint.textContent='Not required \u2014 useful for account recovery';hint.className='field-hint';hint.style.color='var(--text-muted)';}
if(status){status.textContent='';status.className='input-status';}
input.style.borderColor='';
return;
}
const valid = re.test(v);
if(status){status.textContent=valid?'\u2713':'?';status.className='input-status '+(valid?'ok':'checking');}
if(!valid&&hint){
let frame=0;
binaryTimer=setInterval(()=>{
const bits=Array.from({length:14},()=>Math.round(Math.random())).join('');
hint.textContent=bits+' INVALID FORMAT';
hint.className='field-hint err';
frame++;
if(frame>12){clearInterval(binaryTimer);hint.textContent='Unusual format \u2014 double check?';}
},60);
}else if(hint){hint.textContent='Looks good!';hint.className='field-hint ok';}
if(valid&&checkUrl){
if(status){status.textContent='\u25CC';status.className='input-status checking';}
debounceTimer=setTimeout(async()=>{
try{
const fd=new FormData();fd.append('type','email');fd.append('value',v);
const res=await fetch(checkUrl,{method:'POST',body:fd});
const data=await res.json();
if(status){status.textContent=data.available?'\u2713':'\u2717';status.className='input-status '+(data.available?'ok':'err');}
if(hint){hint.textContent=data.message||'Looks good!';hint.className='field-hint '+(data.available?'ok':'err');}
input.style.borderColor=data.available?'var(--accent)':'var(--danger)';
}catch(e){}
},600);
}else{input.style.borderColor=valid?'var(--accent)':'var(--danger)';}
});
}
function initLoginCheck(inputId, statusId, hintId, url) {
const input  = document.getElementById(inputId);
const status = document.getElementById(statusId);
const hint   = document.getElementById(hintId);
if (!input) return;
let timer = null;
input.addEventListener('input', () => {
clearTimeout(timer);
const v = input.value.trim();
if(!v){if(status){status.textContent='';status.className='input-status';}if(hint){hint.textContent='';hint.className='field-hint';}input.style.borderColor='';return;}
if(status){status.textContent='\u25CC';status.className='input-status checking';}
timer=setTimeout(async()=>{
try{
const fd=new FormData();fd.append('type','username');fd.append('value',v);
const res=await fetch(url,{method:'POST',body:fd});
const data=await res.json();
const exists=!data.available;
if(status){status.textContent=exists?'\u2713':'??';status.className='input-status '+(exists?'ok':'checking');}
if(hint){hint.textContent=exists?'User found':'Unknown user';hint.className='field-hint '+(exists?'ok':'');hint.style.color=exists?'var(--accent)':'var(--warning)';}
input.style.borderColor=exists?'var(--accent)':'';
}catch(e){if(status){status.textContent='';status.className='input-status';}}
},500);
});
input.addEventListener('blur',()=>{if(!input.value.trim()){input.style.borderColor='';if(status){status.textContent='';status.className='input-status';}}});
}
function initBgCanvas() {
const canvas = document.getElementById('bg-canvas');
if (!canvas) return;
const ctx = canvas.getContext('2d');
let W=canvas.width=window.innerWidth, H=canvas.height=window.innerHeight;
const pts=Array.from({length:60},()=>({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.35,vy:(Math.random()-.5)*.35,r:Math.random()*1.4+.4,o:Math.random()*.5+.1}));
function draw(){
ctx.clearRect(0,0,W,H);
pts.forEach(p=>{p.x+=p.vx;p.y+=p.vy;if(p.x<0)p.x=W;if(p.x>W)p.x=0;if(p.y<0)p.y=H;if(p.y>H)p.y=0;ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fillStyle=`rgba(0,229,200,${p.o})`;ctx.fill();});
for(let i=0;i<pts.length;i++)for(let j=i+1;j<pts.length;j++){const dx=pts[i].x-pts[j].x,dy=pts[i].y-pts[j].y,d=Math.sqrt(dx*dx+dy*dy);if(d<110){ctx.beginPath();ctx.moveTo(pts[i].x,pts[i].y);ctx.lineTo(pts[j].x,pts[j].y);ctx.strokeStyle=`rgba(0,229,200,${.07*(1-d/110)})`;ctx.lineWidth=.6;ctx.stroke();}}
requestAnimationFrame(draw);
}
draw();
window.addEventListener('resize',()=>{W=canvas.width=window.innerWidth;H=canvas.height=window.innerHeight;});
}
const chainColors={
classical:  {bg:'rgba(124,58,237,0.15)',color:'#a78bfa',border:'#7c3aed',num:'#7c3aed'},
symmetric:  {bg:'rgba(37,99,235,0.15)', color:'#60a5fa',border:'#2563eb',num:'#2563eb'},
asymmetric: {bg:'rgba(5,150,105,0.15)', color:'#34d399',border:'#059669',num:'#059669'},
custom:     {bg:'rgba(217,119,6,0.15)', color:'#fbbf24',border:'#d97706',num:'#d97706'},
hashing:    {bg:'rgba(220,38,38,0.15)', color:'#f87171',border:'#dc2626',num:'#dc2626'},
};
const DUAL_KEY_ALGOS=['aes-cbc','aes-gcm','aes-cfb','camellia','blowfish-cbc','des3-cbc'];
const NO_KEY_ALGOS=['md5','sha1','sha256','sha512','sha3','blake2','base64','base58','hex','binary','run-length','lz4','zlib','deflate','huffman'];
let chain=[];
function algoNeedsKey(slug){
const s=(slug||'').toLowerCase();
if(NO_KEY_ALGOS.some(n=>s.includes(n)))return 0;
if(DUAL_KEY_ALGOS.some(n=>s.includes(n)))return 2;
return 1;
}
function addToChain(name,category,slug){chain.push({name,category,slug,key:'',key2:''});renderChain();saveChainKey();}
function removeFromChain(idx){chain.splice(idx,1);renderChain();saveChainKey();}
function clearChain(){chain=[];renderChain();saveChainKey();}
function calcStrength(){
let s=0;
const cats=chain.map(c=>c.category);
s+=chain.length*15;
if(cats.includes('symmetric'))s+=30;
if(cats.includes('asymmetric'))s+=25;
if(cats.includes('hashing'))s+=15;
if(cats.includes('classical'))s+=10;
const layerBonus=Math.min(chain.length,5)*3;
s+=layerBonus;
return Math.min(100,s);
}
function renderChain(){
const container=document.getElementById('chain-steps');
if(!container)return;
const countEl=document.getElementById('chain-count');
if(countEl)countEl.textContent=chain.length;
const strength=calcStrength();
const sfill=document.getElementById('strength-fill');if(sfill)sfill.style.width=strength+'%';
const sval=document.getElementById('strength-val');if(sval)sval.textContent=strength+'%';
const cd=document.getElementById('chain-data');if(cd)cd.value=JSON.stringify(chain);
container.innerHTML='';
if(chain.length===0){
container.innerHTML='<div class="chain-empty"><span style="font-size:28px;filter:grayscale(1)">\u26D3</span><span>No operations yet</span><span style="font-size:10px;color:var(--text-muted)">Click an algorithm to start \u2192</span></div>';
updateChainShareKey();
return;
}
chain.forEach((step,i)=>{
const c=chainColors[step.category]||chainColors.custom;
if(i>0){const a=document.createElement('div');a.className='step-arrow';a.textContent='\u2193';container.appendChild(a);}
const el=document.createElement('div');
el.className='chain-step';
el.style.cssText=`background:${c.bg};border-color:${c.border}`;
const keyNeeds=algoNeedsKey(step.slug);
let keyHtml='';
if(keyNeeds===0){
keyHtml=`<span style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);opacity:.6">no key</span>`;
}else if(keyNeeds===2){
keyHtml=`<input type="password" class="step-key" placeholder="Key..." value="${(step.key||'').replace(/"/g,'&quot;')}" oninput="chain[${i}].key=this.value;updateChainData()" title="Key"/><input type="password" class="step-key" placeholder="IV..." value="${(step.key2||'').replace(/"/g,'&quot;')}" oninput="chain[${i}].key2=this.value;updateChainData()" title="IV/Salt" style="margin-left:4px"/>`;
}else{
keyHtml=`<input type="password" class="step-key" placeholder="Key..." value="${(step.key||'').replace(/"/g,'&quot;')}" oninput="chain[${i}].key=this.value;updateChainData()"/>`;
}
el.innerHTML=`<div class="step-num" style="background:${c.num};color:#fff">${i+1}</div><div style="flex:1;min-width:0"><div class="step-name" style="color:${c.color}">${step.name}</div><div class="step-cat">${step.category.toUpperCase()}</div></div>${keyHtml}<button class="step-remove" onclick="removeFromChain(${i})" type="button">\u2715</button>`;
container.appendChild(el);
});
updateChainShareKey();
}
function updateChainData(){
const cd=document.getElementById('chain-data');
if(cd)cd.value=JSON.stringify(chain);
updateChainShareKey();
}
function updateChainShareKey(){
const el=document.getElementById('chain-share-key-val');
if(!el)return;
const row=document.getElementById('chain-share-row');
if(chain.length===0){if(row)row.style.display='none';el.textContent='\u2014';return;}
if(row)row.style.display='flex';
el.textContent=generateChainKey();
}
function generateChainKey(){
return btoa(JSON.stringify(chain.map(s=>[s.slug,s.name,s.category,s.key||'',s.key2||''])));
}
function saveChainKey(){
try{localStorage.setItem('last_chain',JSON.stringify(chain));}catch(e){}
}
function copyChainKey(){
const key=generateChainKey();
navigator.clipboard.writeText(key).then(()=>{showToast('Chain key copied!');});
}
function autoDecodeChainKey(val){
if(!val)return;
try{
const dec=JSON.parse(atob(val));
chain=dec.map(s=>({slug:s[0],name:s[1],category:s[2],key:s[3]||'',key2:s[4]||''}));
renderChain();
const inp=document.getElementById('chain-key-input');
if(inp)inp.style.borderColor='var(--accent)';
showToast('Chain decoded \u2014 '+chain.length+' layers loaded');
}catch(e){
const inp=document.getElementById('chain-key-input');
if(inp)inp.style.borderColor=val.length>10?'var(--danger)':'var(--border)';
}
}
const mediaInfo={
image:{icon:'\uD83D\uDDBC',sub:'PNG, JPG, BMP supported',technique:'LSB Steganography'},
audio:{icon:'\uD83C\uDFB5',sub:'WAV, MP3 supported',technique:'Phase Coding / Echo Hiding'},
video:{icon:'\uD83C\uDFAC',sub:'MP4, AVI supported',technique:'Frame-based LSB Embedding'},
};
function switchMedia(type){
['image','audio','video'].forEach(t=>document.getElementById('mt-'+t)?.classList.toggle('active',t===type));
const m=mediaInfo[type];
const icon=document.getElementById('carrier-icon');if(icon)icon.textContent=m.icon;
const sub=document.getElementById('carrier-sub');if(sub)sub.textContent=m.sub;
const tech=document.getElementById('carrier-technique');if(tech)tech.textContent=m.technique;
const inp=document.getElementById('media-type-input');if(inp)inp.value=type;
}
function switchPayloadType(type){
document.getElementById('pt-text')?.classList.toggle('active',type==='text');
document.getElementById('pt-file')?.classList.toggle('active',type==='file');
const ta=document.getElementById('payload-text');if(ta)ta.style.display=type==='text'?'block':'none';
const fa=document.getElementById('payload-file');if(fa)fa.style.display=type==='file'?'block':'none';
const inp=document.getElementById('payload-type-input');if(inp)inp.value=type;
}
function sanitizeFilename(name){
const ext=name.lastIndexOf('.')>0?name.substring(name.lastIndexOf('.')):'';
const base=name.replace(ext,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').substring(0,24);
return(base||'file')+ext;
}
function handleFileSelect(input,previewId){
const file=input.files[0];
if(!file)return;
const preview=document.getElementById(previewId);
if(!preview)return;
const cleanName=sanitizeFilename(file.name);
const isImg=file.type.startsWith('image');
const isAud=file.type.startsWith('audio');
const iconCh=isImg?'\uD83D\uDDBC':isAud?'\uD83C\uDFB5':'\uD83C\uDFAC';
preview.innerHTML=`<div class="upload-preview"><span style="font-size:20px;flex-shrink:0;filter:grayscale(1)">${iconCh}</span><div style="flex:1;min-width:0"><div class="upload-preview-name">${cleanName}</div><div class="upload-preview-size">${(file.size/1024/1024).toFixed(2)} MB</div></div></div><div class="upload-progress" id="${previewId}-prog"><div class="upload-progress-track"><div class="upload-progress-fill" id="${previewId}-bar"></div></div><div class="upload-progress-text" id="${previewId}-txt">Loading...</div></div>`;
const progWrap=document.getElementById(previewId+'-prog');
const progBar=document.getElementById(previewId+'-bar');
const progTxt=document.getElementById(previewId+'-txt');
if(progWrap)progWrap.style.display='block';
if(isImg){
const reader=new FileReader();
reader.onprogress=e=>{if(e.lengthComputable&&progBar&&progTxt){const pct=Math.round(e.loaded/e.total*100);progBar.style.width=pct+'%';progTxt.textContent=pct+'% loaded';}};
reader.onload=e=>{
if(progBar)progBar.style.width='100%';
if(progTxt)progTxt.textContent='Ready \u2713';
setTimeout(()=>{if(progWrap)progWrap.style.display='none';},800);
const img=document.createElement('img');
img.src=e.target.result;
img.style.cssText='max-width:100%;max-height:110px;border-radius:8px;margin-top:8px;display:block;object-fit:contain';
preview.appendChild(img);
};
reader.readAsDataURL(file);
}else{
let pct=0;
const iv=setInterval(()=>{pct+=20;if(progBar)progBar.style.width=pct+'%';if(progTxt)progTxt.textContent=pct+'% loaded';if(pct>=100){clearInterval(iv);if(progTxt)progTxt.textContent='Ready \u2713';setTimeout(()=>{if(progWrap)progWrap.style.display='none';},800);}},100);
}
}
function showTerminal(id){
const el=document.getElementById(id);
if(el)el.style.display='block';
}
function addTermLine(body,cls,html){
if(!body)return;
const div=document.createElement('div');
div.className='t-line '+(cls||'');
div.innerHTML=html||'';
body.appendChild(div);
body.scrollTop=body.scrollHeight;
}
function animateTerminal(body,steps,onDone){
if(!body){if(onDone)onDone();return;}
body.innerHTML='';
const cursor=document.createElement('span');cursor.className='t-cursor';body.appendChild(cursor);
let i=0;
function next(){
if(i<steps.length){
cursor.remove();
const div=document.createElement('div');div.className='t-line';div.textContent='> '+steps[i];body.appendChild(div);body.scrollTop=body.scrollHeight;i++;
body.appendChild(cursor);
setTimeout(next,100+Math.random()*60);
}else{
cursor.remove();
if(onDone)setTimeout(onDone,80);
}
}
setTimeout(next,50);
}
document.addEventListener('DOMContentLoaded',()=>{
initPasswordEyes();
document.querySelectorAll('.upload-zone').forEach(zone=>{
zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('dragover');});
zone.addEventListener('dragleave',()=>zone.classList.remove('dragover'));
zone.addEventListener('drop',e=>{
e.preventDefault();zone.classList.remove('dragover');
const inp=zone.nextElementSibling;
if(inp&&inp.tagName==='INPUT'&&e.dataTransfer.files.length){const dt=new DataTransfer();dt.items.add(e.dataTransfer.files[0]);inp.files=dt.files;inp.dispatchEvent(new Event('change'));}
});
});
document.querySelectorAll('.alert').forEach(el=>{
setTimeout(()=>{el.style.transition='opacity .5s';el.style.opacity='0';setTimeout(()=>el.remove(),500);},5000);
});
const keyBlocksEl=document.getElementById('steg-key-blocks');
if(keyBlocksEl&&keyBlocksEl.dataset.len){
animateStegKeyBlocks(parseInt(keyBlocksEl.dataset.len));
}
});
function animateStegKeyBlocks(len){
const container=document.getElementById('steg-key-blocks');
const progressFill=document.getElementById('steg-key-progress-fill');
if(!container)return;
container.innerHTML='';
const blocks=[];
for(let i=0;i<len;i++){
const span=document.createElement('span');
span.className='skb';
span.textContent='\u2588';
container.appendChild(span);
blocks.push(span);
if((i+1)%8===0&&i<len-1){const sp=document.createElement('span');sp.innerHTML=' ';container.appendChild(sp);}
}
let lit=0;
function lightNext(){
if(lit>=blocks.length){if(progressFill)progressFill.style.width='100%';return;}
blocks[lit].classList.add('lit');
lit++;
if(progressFill)progressFill.style.width=Math.round(lit/blocks.length*100)+'%';
setTimeout(lightNext,60+Math.random()*40);
}
setTimeout(lightNext,200);
}
function animateStegKeyRegen(key){
const container=document.getElementById('steg-key-blocks');
const progressFill=document.getElementById('steg-key-progress-fill');
if(!container)return;
container.innerHTML='';
if(progressFill)progressFill.style.width='0%';
const blocks=[];
for(let i=0;i<key.length;i++){
const span=document.createElement('span');
span.className='skb';
span.textContent='\u2588';
container.appendChild(span);
blocks.push(span);
if((i+1)%8===0&&i<key.length-1){const sp=document.createElement('span');sp.innerHTML=' ';container.appendChild(sp);}
}
let lit=0;
function lightNext(){
if(lit>=blocks.length){if(progressFill)progressFill.style.width='100%';return;}
blocks[lit].classList.add('lit');
lit++;
if(progressFill)progressFill.style.width=Math.round(lit/blocks.length*100)+'%';
setTimeout(lightNext,40+Math.random()*30);
}
setTimeout(lightNext,100);
}
function toggleSidebar(){
const sidebar=document.getElementById('sidebar');
const overlay=document.getElementById('sidebar-overlay');
const hamburger=document.getElementById('hamburger');
if(!sidebar)return;
sidebar.classList.toggle('open');
if(overlay)overlay.classList.toggle('open');
if(hamburger)hamburger.classList.toggle('open');
}
function copyText(text,btn){
navigator.clipboard.writeText(text).then(()=>{const o=btn.textContent;btn.textContent='\u2713 Copied!';setTimeout(()=>btn.textContent=o,2000);});
}
function toggleSwitch(el){
el.classList.toggle('on');
const t=el.dataset.target;if(t){const el2=document.getElementById(t);if(el2)el2.style.display=el.classList.contains('on')?'block':'none';}
}
function initBtnRain(canvasId,chars,color){
const canvas=document.getElementById(canvasId);
if(!canvas)return;
const btn=canvas.closest('button');
if(!btn)return;
const ctx=canvas.getContext('2d');
let W=0,H=0,cols=[],running=false,raf=null;
function resize(){
const r=canvas.getBoundingClientRect();
W=canvas.width=Math.round(r.width||btn.offsetWidth||200);
H=canvas.height=Math.round(r.height||btn.offsetHeight||60);
cols=[];
const spacing=28;
const count=Math.ceil(W/spacing)+2;
for(let i=0;i<count;i++){
cols.push({
x:i*spacing+Math.random()*spacing,
y:Math.random()*-H*2,
speed:1.4+Math.random()*1.8,
char:chars[Math.floor(Math.random()*chars.length)],
size:14+Math.floor(Math.random()*10),
alpha:0.12+Math.random()*0.22,
changeTimer:Math.floor(Math.random()*40)
});
}
}
function draw(){
if(!running)return;
ctx.clearRect(0,0,W,H);
cols.forEach(c=>{
c.y+=c.speed;
if(c.y>H+30){
c.y=Math.random()*-60-20;
c.x=Math.random()*W;
c.speed=1.4+Math.random()*1.8;
c.char=chars[Math.floor(Math.random()*chars.length)];
}
c.changeTimer--;
if(c.changeTimer<=0){c.char=chars[Math.floor(Math.random()*chars.length)];c.changeTimer=20+Math.floor(Math.random()*40);}
ctx.font=c.size+'px serif';
ctx.globalAlpha=c.alpha;
ctx.fillStyle=color;
ctx.fillText(c.char,c.x,c.y);
});
ctx.globalAlpha=1;
raf=requestAnimationFrame(draw);
}
function start(){if(running)return;W=0;resize();running=true;draw();}
function stop(){running=false;if(raf)cancelAnimationFrame(raf);ctx.clearRect(0,0,W,H);}
btn.addEventListener('mouseenter',start);
btn.addEventListener('mouseleave',stop);
btn.addEventListener('focus',start);
btn.addEventListener('blur',stop);
}
document.addEventListener('DOMContentLoaded',function(){
initBtnRain('embed-rain',['\u26D3','\u26D3\uFE0F','\uD83D\uDD17','\u2022','\u25AA','\u26AC'],'rgba(5,12,14,0.6)');
initBtnRain('extract-rain',['\uD83D\uDD12','\uD83D\uDD13','\u26BF','\u2022','\u25AA','\u26AC'],'rgba(200,140,255,0.55)');
});