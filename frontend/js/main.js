/* ── GLITCH TRANSITION ── */
function triggerGlitchTransition(url,isNewUser){const ov=document.getElementById('glitch-overlay');if(!ov){window.location.href=url;return;}ov.innerHTML='<div class="gl-base"></div><div class="gl-scan"></div><div class="gl-r"></div><div class="gl-g"></div><div class="gl-b"></div><div class="gl-line"></div><div class="gl-txt">'+(isNewUser?'IDENTITY CREATED':'ACCESS GRANTED')+'</div>';ov.classList.add('active');setTimeout(()=>{window.location.href=url;},980);}
function navSwitch(e,url,dir){e.preventDefault();const card=document.getElementById('auth-card');if(!card){window.location.href=url;return false;}card.classList.add(dir==='right'?'slide-out-left':'slide-out-right');setTimeout(()=>{window.location.href=url+'?from='+(dir==='right'?'login':'register');},320);return false;}
/* ── ERRORS / TOASTS ── */
function showInlineError(el,msg){if(!el)return;el.textContent=msg;el.style.display='flex';el.classList.remove('show');void el.offsetWidth;el.classList.add('show');}
function clearError(el){if(!el)return;el.style.display='none';el.textContent='';}
function showStegError(msg){let el=document.getElementById('steg-float-error');if(!el){el=document.createElement('div');el.id='steg-float-error';el.className='steg-float-error';document.body.appendChild(el);}el.textContent=msg;el.style.display='flex';clearTimeout(el._t);el._t=setTimeout(()=>{el.style.opacity='0';setTimeout(()=>{el.style.display='none';el.style.opacity='1';},350);},4500);}
function setLoading(btn,loading){if(!btn)return;const txt=btn.querySelector('.btn-text');const ldr=btn.querySelector('.btn-loader');if(txt)txt.style.display=loading?'none':'inline';if(ldr)ldr.style.display=loading?'inline':'none';btn.disabled=loading;}
let _toastT=null;
function showToast(msg,dur){clearTimeout(_toastT);let t=document.querySelector('.toast');if(!t){t=document.createElement('div');t.className='toast';document.body.appendChild(t);}t.textContent=msg;t.style.opacity='1';_toastT=setTimeout(()=>{t.style.transition='opacity .4s';t.style.opacity='0';setTimeout(()=>t.remove(),400);},dur||2500);}
function togglePass(id,btn){const input=document.getElementById(id)||(btn&&btn.closest('.input-wrap')&&btn.closest('.input-wrap').querySelector('input'));if(!input)return;const showing=input.type!=='password';input.type=showing?'password':'text';if(btn)btn.textContent=showing?'\u25C9':'\uD83D\uDC41';}
function initPasswordEyes(){document.querySelectorAll('.input-eye').forEach(btn=>{if(!btn.dataset.init){btn.dataset.init='1';btn.textContent='\u25C9';}});}
function initPasswordStrength(inputId,wrapId,barId,labelId,checkIds){const input=document.getElementById(inputId);const wrap=document.getElementById(wrapId);const bar=document.getElementById(barId);const label=document.getElementById(labelId);if(!input)return;input.addEventListener('input',()=>{const v=input.value;if(!v){if(wrap)wrap.style.display='none';return;}if(wrap)wrap.style.display='block';const c={len:v.length>=8,upper:/[A-Z]/.test(v),num:/[0-9]/.test(v),sym:/[^a-zA-Z0-9]/.test(v)};const score=(c.len?25:0)+(c.upper?25:0)+(c.num?25:0)+(c.sym?25:0);if(bar){bar.style.width=score+'%';const colors=['#ff4d6d','#ff7043','#ffb347','#00e5c8'];const idx=Math.min(3,Math.floor(score/26));bar.style.background=colors[idx];}if(label){const labels=['Weak','Fair','Good','Strong'];label.textContent=score===0?'\u2014':labels[Math.min(3,Math.floor(score/26))];const c2=['#ff4d6d','#ff7043','#ffb347','#00e5c8'];label.style.color=score===0?'var(--text-muted)':c2[Math.min(3,Math.floor(score/26))];}const keys=['len','upper','num','sym'];if(checkIds)checkIds.forEach((id,i)=>{document.getElementById(id)?.classList.toggle('pass',c[keys[i]]);});});}
function initFieldCheck(inputId,statusId,hintId,type,url){const input=document.getElementById(inputId);const status=document.getElementById(statusId);const hint=document.getElementById(hintId);if(!input)return;let timer=null;input.addEventListener('input',()=>{clearTimeout(timer);const v=input.value.trim();if(!v){if(status){status.textContent='';status.className='input-status';}if(hint){hint.textContent='';hint.className='field-hint';}input.style.borderColor='';return;}if(status){status.textContent='\u25CC';status.className='input-status checking';}timer=setTimeout(async()=>{try{const fd=new FormData();fd.append('type',type);fd.append('value',v);const res=await fetch(url,{method:'POST',body:fd});const data=await res.json();if(status){status.textContent=data.available?'\u2713':'\u2717';status.className='input-status '+(data.available?'ok':'err');}if(hint){hint.textContent=data.message||'';hint.className='field-hint '+(data.available?'ok':'err');}input.style.borderColor=data.available?'var(--accent)':'var(--danger)';}catch(e){if(status){status.textContent='';status.className='input-status';}}},500);});input.addEventListener('blur',()=>{if(!input.value.trim()){input.style.borderColor='';if(status){status.textContent='';status.className='input-status';}}});}
function initEmailHint(inputId,statusId,hintId,checkUrl){const input=document.getElementById(inputId);const hint=document.getElementById(hintId);const status=document.getElementById(statusId);if(!input)return;const re=/^[^\s@]+@[^\s@]+\.[^\s@]+$/;let binaryTimer=null;let debounceTimer=null;input.addEventListener('input',()=>{const v=input.value.trim();clearInterval(binaryTimer);clearTimeout(debounceTimer);if(!v){if(hint){hint.textContent='Not required \u2014 useful for account recovery';hint.className='field-hint';hint.style.color='var(--text-muted)';}if(status){status.textContent='';status.className='input-status';}input.style.borderColor='';return;}const valid=re.test(v);if(status){status.textContent=valid?'\u2713':'?';status.className='input-status '+(valid?'ok':'checking');}if(!valid&&hint){let frame=0;binaryTimer=setInterval(()=>{const bits=Array.from({length:14},()=>Math.round(Math.random())).join('');hint.textContent=bits+' INVALID FORMAT';hint.className='field-hint err';frame++;if(frame>12){clearInterval(binaryTimer);hint.textContent='Unusual format \u2014 double check?';}},60);}else if(hint){hint.textContent='Looks good!';hint.className='field-hint ok';}if(valid&&checkUrl){if(status){status.textContent='\u25CC';status.className='input-status checking';}debounceTimer=setTimeout(async()=>{try{const fd=new FormData();fd.append('type','email');fd.append('value',v);const res=await fetch(checkUrl,{method:'POST',body:fd});const data=await res.json();if(status){status.textContent=data.available?'\u2713':'\u2717';status.className='input-status '+(data.available?'ok':'err');}if(hint){hint.textContent=data.message||'Looks good!';hint.className='field-hint '+(data.available?'ok':'err');}input.style.borderColor=data.available?'var(--accent)':'var(--danger)';}catch(e){}},600);}else{input.style.borderColor=valid?'var(--accent)':'var(--danger)';}});}
function initLoginCheck(inputId,statusId,hintId,url){const input=document.getElementById(inputId);const status=document.getElementById(statusId);const hint=document.getElementById(hintId);if(!input)return;let timer=null;input.addEventListener('input',()=>{clearTimeout(timer);const v=input.value.trim();if(!v){if(status){status.textContent='';status.className='input-status';}if(hint){hint.textContent='';hint.className='field-hint';}input.style.borderColor='';return;}if(status){status.textContent='\u25CC';status.className='input-status checking';}timer=setTimeout(async()=>{try{const fd=new FormData();fd.append('type','username');fd.append('value',v);const res=await fetch(url,{method:'POST',body:fd});const data=await res.json();const exists=!data.available;if(status){status.textContent=exists?'\u2713':'??';status.className='input-status '+(exists?'ok':'checking');}if(hint){hint.textContent=exists?'User found':'Unknown user';hint.className='field-hint '+(exists?'ok':'');hint.style.color=exists?'var(--accent)':'var(--warning)';}input.style.borderColor=exists?'var(--accent)':'';}catch(e){if(status){status.textContent='';status.className='input-status';}}},500);});input.addEventListener('blur',()=>{if(!input.value.trim()){input.style.borderColor='';if(status){status.textContent='';status.className='input-status';}}});}
/* ── BG CANVAS ── */
function initBgCanvas(){const canvas=document.getElementById('bg-canvas');if(!canvas)return;const ctx=canvas.getContext('2d');let W=canvas.width=window.innerWidth,H=canvas.height=window.innerHeight;const pts=Array.from({length:60},()=>({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.35,vy:(Math.random()-.5)*.35,r:Math.random()*1.4+.4,o:Math.random()*.5+.1}));function draw(){ctx.clearRect(0,0,W,H);pts.forEach(p=>{p.x+=p.vx;p.y+=p.vy;if(p.x<0)p.x=W;if(p.x>W)p.x=0;if(p.y<0)p.y=H;if(p.y>H)p.y=0;ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fillStyle=`rgba(0,229,200,${p.o})`;ctx.fill();});for(let i=0;i<pts.length;i++)for(let j=i+1;j<pts.length;j++){const dx=pts[i].x-pts[j].x,dy=pts[i].y-pts[j].y,d=Math.sqrt(dx*dx+dy*dy);if(d<110){ctx.beginPath();ctx.moveTo(pts[i].x,pts[i].y);ctx.lineTo(pts[j].x,pts[j].y);ctx.strokeStyle=`rgba(0,229,200,${.07*(1-d/110)})`;ctx.lineWidth=.6;ctx.stroke();}}requestAnimationFrame(draw);}draw();window.addEventListener('resize',()=>{W=canvas.width=window.innerWidth;H=canvas.height=window.innerHeight;});}
/* ── CHAIN ── */
const chainColors={classical:{bg:'rgba(124,58,237,0.15)',color:'#a78bfa',border:'#7c3aed',num:'#7c3aed'},symmetric:{bg:'rgba(37,99,235,0.15)',color:'#60a5fa',border:'#2563eb',num:'#2563eb'},asymmetric:{bg:'rgba(5,150,105,0.15)',color:'#34d399',border:'#059669',num:'#059669'},custom:{bg:'rgba(217,119,6,0.15)',color:'#fbbf24',border:'#d97706',num:'#d97706'},hashing:{bg:'rgba(220,38,38,0.15)',color:'#f87171',border:'#dc2626',num:'#dc2626'}};
const DUAL_KEY_ALGOS=['aes-cbc','aes-gcm','aes-cfb','camellia','blowfish-cbc','des3-cbc'];
const NO_KEY_ALGOS=['md5','sha1','sha256','sha512','sha3','blake2','base64','base58','hex','binary','run-length','lz4','zlib','deflate','huffman','atbash','rot13','polybius'];
let chain=[];
function algoNeedsKey(slug){const s=(slug||'').toLowerCase();if(NO_KEY_ALGOS.some(n=>s.includes(n)))return 0;if(DUAL_KEY_ALGOS.some(n=>s.includes(n)))return 2;return 1;}
function addToChain(name,category,slug){chain.push({name,category,slug,key:'',key2:''});renderChain();saveChainKey();}
function removeFromChain(idx){chain.splice(idx,1);renderChain();saveChainKey();}
function clearChain(){chain=[];renderChain();saveChainKey();}
function calcStrength(){let s=0;const cats=chain.map(c=>c.category);s+=chain.length*15;if(cats.includes('symmetric'))s+=30;if(cats.includes('asymmetric'))s+=25;if(cats.includes('hashing'))s+=15;if(cats.includes('classical'))s+=10;return Math.min(100,s+Math.min(chain.length,5)*3);}
function renderChain(){
const container=document.getElementById('chain-steps');if(!container)return;
const countEl=document.getElementById('chain-count');if(countEl)countEl.textContent=chain.length;
const strength=calcStrength();
const sfill=document.getElementById('strength-fill');if(sfill)sfill.style.width=strength+'%';
const sval=document.getElementById('strength-val');if(sval)sval.textContent=strength+'%';
const cd=document.getElementById('chain-data');if(cd)cd.value=JSON.stringify(chain);
container.innerHTML='';
if(chain.length===0){container.innerHTML='<div class="chain-empty"><span style="font-size:28px;filter:grayscale(1)">&#9939;</span><span>No operations yet</span><span style="font-size:10px;color:var(--text-muted)">Click an algorithm to start &#8594;</span></div>';updateChainShareKey();_refreshHieros();return;}
chain.forEach((step,i)=>{
const c=(step.slug==="netrunner")?chainColors.hashing:(chainColors[step.category]||chainColors.custom);
if(i>0){const a=document.createElement('div');a.className='step-arrow';a.textContent='\u2193';container.appendChild(a);}
const el=document.createElement('div');el.className='chain-step';el.style.cssText=`background:${c.bg};border-color:${c.border}`;
const keyNeeds=algoNeedsKey(step.slug);
let keyHtml='';
if(keyNeeds===0)keyHtml=`<span style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);opacity:.6">no key</span>`;
else if(keyNeeds===2)keyHtml=`<input type="password" class="step-key" placeholder="Key..." value="${(step.key||'').replace(/"/g,'&quot;')}" oninput="chain[${i}].key=this.value;updateChainData()" title="Key"/><input type="password" class="step-key" placeholder="IV..." value="${(step.key2||'').replace(/"/g,'&quot;')}" oninput="chain[${i}].key2=this.value;updateChainData()" style="margin-left:4px"/>`;
else keyHtml=`<input type="password" class="step-key" placeholder="Key..." value="${(step.key||'').replace(/"/g,'&quot;')}" oninput="chain[${i}].key=this.value;updateChainData()"/>`;
el.innerHTML=`<div class="step-num" style="background:${c.num};color:#fff">${i+1}</div><div style="flex:1;min-width:0"><div class="step-name" style="color:${c.color}">${step.name}</div><div class="step-cat">${step.category.toUpperCase()}</div></div>${keyHtml}<button class="step-remove" onclick="removeFromChain(${i})" type="button">&#10005;</button>`;
container.appendChild(el);
});
updateChainShareKey();_refreshHieros();
}
function updateChainData(){const cd=document.getElementById('chain-data');if(cd)cd.value=JSON.stringify(chain);updateChainShareKey();}
function updateChainShareKey(){const el=document.getElementById('chain-share-key-val');if(!el)return;const row=document.getElementById('chain-share-row');if(chain.length===0){if(row)row.style.display='none';el.textContent='\u2014';return;}if(row)row.style.display='flex';el.textContent=generateChainKey();}
function generateChainKey(){return btoa(JSON.stringify(chain.map(s=>[s.slug,s.name,s.category,s.key||'',s.key2||''])));}
function saveChainKey(){try{localStorage.setItem('last_chain',JSON.stringify(chain));}catch(e){}}
function copyChainKey(){navigator.clipboard.writeText(generateChainKey()).then(()=>showToast('Chain key copied!'));}
function autoDecodeChainKey(val){if(!val)return;try{const dec=JSON.parse(atob(val));chain=dec.map(s=>({slug:s[0],name:s[1],category:s[2],key:s[3]||'',key2:s[4]||''}));renderChain();const inp=document.getElementById('chain-key-input');if(inp)inp.style.borderColor='var(--accent)';showToast('Chain decoded \u2014 '+chain.length+' layers loaded');}catch(e){const inp=document.getElementById('chain-key-input');if(inp)inp.style.borderColor=val.length>10?'var(--danger)':'var(--border)';}}
/* ── MEDIA / PAYLOAD SWITCH ── */
const mediaInfo={image:{icon:'\uD83D\uDDBC',sub:'PNG, JPG, BMP supported',technique:'LSB Steganography'},audio:{icon:'\uD83C\uDFB5',sub:'WAV, MP3 supported',technique:'Phase Coding / Echo Hiding'},video:{icon:'\uD83C\uDFAC',sub:'MP4, AVI supported',technique:'Frame-based LSB Embedding'}};
function switchMedia(type){['image','audio','video'].forEach(t=>document.getElementById('mt-'+t)?.classList.toggle('active',t===type));const m=mediaInfo[type];const icon=document.getElementById('carrier-icon');if(icon)icon.textContent=m.icon;const sub=document.getElementById('carrier-sub');if(sub)sub.textContent=m.sub;const tech=document.getElementById('carrier-technique');if(tech)tech.textContent=m.technique;const inp=document.getElementById('media-type-input');if(inp)inp.value=type;}
function switchPayloadType(type){document.getElementById('pt-text')?.classList.toggle('active',type==='text');document.getElementById('pt-file')?.classList.toggle('active',type==='file');const ta=document.getElementById('payload-text');if(ta)ta.style.display=type==='text'?'block':'none';const fa=document.getElementById('payload-file');if(fa)fa.style.display=type==='file'?'block':'none';const inp=document.getElementById('payload-type-input');if(inp)inp.value=type;}
/* ── GENERIC FILE PREVIEW (payload) ── */
function handleFileSelect(input,previewId){const file=input.files[0];if(!file)return;const preview=document.getElementById(previewId);if(!preview)return;const isImg=file.type.startsWith('image');const isAud=file.type.startsWith('audio');const iconCh=isImg?'\uD83D\uDDBC':isAud?'\uD83C\uDFB5':'\uD83C\uDFAC';preview.innerHTML=`<div class="upload-preview"><span style="font-size:20px;flex-shrink:0;filter:grayscale(1)">${iconCh}</span><div style="flex:1;min-width:0"><div class="upload-preview-name">${file.name}</div><div class="upload-preview-size">${(file.size/1024/1024).toFixed(2)} MB</div></div></div>`;}
/* ── CARRIER FILE HANDLER (side-by-side rich preview) ── */
function handleCarrierSelect(input,previewId){
const file=input.files[0];if(!file)return;
const previewEl=document.getElementById(previewId);if(!previewEl)return;
previewEl.style.display='flex';
previewEl.innerHTML='';
if(file.type.startsWith('image/')){
const url=URL.createObjectURL(file);
previewEl.innerHTML=`<div class="preview-img-wrap" onclick="openImgViewer('${url}')"><img src="${url}" class="preview-img" alt="Preview"><div class="preview-img-hover-overlay">&#128269; View</div><div class="preview-meta">${_shortName(file.name)} &bull; ${(file.size/1024/1024).toFixed(2)}MB</div></div>`;
}else if(file.type.startsWith('audio/')){
const url=URL.createObjectURL(file);
previewEl.innerHTML=`<div class="preview-audio-wrap"><div class="preview-audio-top"><div class="preview-audio-icon-big">&#9835;</div><div class="preview-audio-bars">${Array.from({length:18},(_,i)=>`<div class="pab" style="--i:${i}"></div>`).join('')}</div><div class="preview-audio-info"><div class="preview-audio-fname">${_shortName(file.name)}</div><div class="preview-audio-size">${(file.size/1024/1024).toFixed(2)} MB</div></div></div><div class="preview-audio-bottom"><audio controls class="preview-audio-ctrl"><source src="${url}" type="${file.type}"></audio></div></div>`;
}else if(file.type.startsWith('video/')){
const url=URL.createObjectURL(file);
previewEl.setAttribute('data-video-url',url);
previewEl.setAttribute('data-video-name',file.name);
previewEl.innerHTML=`<div class="preview-video-thumb" onclick="openVideoFrameViewer(this.closest('.carrier-preview-panel'))"><div class="preview-vt-icon">&#9654;</div><div class="preview-vt-label">${_shortName(file.name)}</div><div class="preview-vt-hint">&#128249; Click to view frames</div><div class="preview-vt-size">${(file.size/1024/1024).toFixed(2)} MB</div></div>`;
}else{
previewEl.innerHTML=`<div class="preview-audio-wrap"><div class="preview-audio-pill"><span class="preview-audio-icon-sm">&#128196;</span><span class="preview-audio-fname">${_shortName(file.name)}</span></div><div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted)">${(file.size/1024/1024).toFixed(2)} MB</div></div>`;
}
}
function _shortName(name){return name.length>22?name.substring(0,10)+'...'+name.slice(-8):name;}
/* ── VIDEO FRAME MODAL (open on click) ── */
function openVideoFrameViewer(panel){
const url=panel.getAttribute('data-video-url');
const name=panel.getAttribute('data-video-name')||'video';
if(!url)return;
const existing=document.getElementById('vf-modal');if(existing)existing.remove();
const modal=document.createElement('div');modal.id='vf-modal';modal.className='vf-modal-overlay';
modal.innerHTML=`<div class="vf-modal-inner" onclick="event.stopPropagation()"><div class="vf-modal-header"><span class="vf-modal-title">&#127916; Frame Viewer &mdash; ${_shortName(name)}</span><button class="vf-modal-close" onclick="document.getElementById('vf-modal').remove()">&#10005;</button></div><div class="vf-modal-body" id="vf-modal-body"><div class="vf-loading">Extracting frames&#8230;</div></div></div>`;
modal.onclick=()=>modal.remove();
document.body.appendChild(modal);
const body=document.getElementById('vf-modal-body');
const vid=document.createElement('video');
vid.src=url;vid.muted=true;vid.preload='metadata';
vid.addEventListener('loadedmetadata',()=>{
const dur=vid.duration;
const count=Math.min(6,Math.max(3,Math.floor(dur/1.5)));
const times=Array.from({length:count},(_,i)=>Math.round((dur/(count+1))*(i+1)*10)/10);
body.innerHTML=`<div class="vf-strip" id="vf-strip"></div>`;
const strip=document.getElementById('vf-strip');
let done=0;
const items=times.map(t=>{
const wrap=document.createElement('div');wrap.className='vf-frame-item';
const cvs=document.createElement('canvas');cvs.className='vf-frame-canvas';
const ts=document.createElement('div');ts.className='vf-frame-ts';ts.textContent=_fmtTime(t);
wrap.appendChild(cvs);wrap.appendChild(ts);strip.appendChild(wrap);
return{cvs,t};
});
function seekNext(idx){
if(idx>=items.length)return;
const{cvs,t}=items[idx];
const handler=()=>{
try{cvs.width=vid.videoWidth;cvs.height=vid.videoHeight;const ctx=cvs.getContext('2d');ctx.drawImage(vid,0,0);}catch(e){}
vid.removeEventListener('seeked',handler);
done++;
if(done<items.length)seekNext(idx+1);
else URL.revokeObjectURL(url);
};
vid.addEventListener('seeked',handler,{once:true});
vid.currentTime=t;
}
seekNext(0);
});
vid.addEventListener('error',()=>{body.innerHTML='<div class="vf-loading" style="color:var(--danger)">Cannot preview this video</div>';});
}
function _fmtTime(s){const m=Math.floor(s/60);const sec=Math.floor(s%60);return `${m}:${sec.toString().padStart(2,'0')}`;}
/* ── IMAGE LIGHTBOX ── */
function openImgViewer(url){const ov=document.getElementById('img-viewer-overlay');const img=document.getElementById('img-viewer-src');if(!ov||!img)return;img.src=url;ov.style.display='flex';}
function closeImgViewer(){const ov=document.getElementById('img-viewer-overlay');if(!ov)return;ov.style.display='none';}
/* ── TERMINAL ── */
function showTerminal(id){const el=document.getElementById(id);if(!el)return;el.style.display='block';el.classList.remove('terminal-minimized');}
function addTermLine(body,cls,html){if(!body)return;const div=document.createElement('div');div.className='t-line '+(cls||'');div.innerHTML=html||'';body.appendChild(div);body.scrollTop=body.scrollHeight;}
function animateTerminal(body,steps,onDone){if(!body){if(onDone)onDone();return;}body.innerHTML='';const cursor=document.createElement('span');cursor.className='t-cursor';body.appendChild(cursor);let i=0;function next(){if(i<steps.length){cursor.remove();const div=document.createElement('div');div.className='t-line';div.textContent='> '+steps[i];body.appendChild(div);body.scrollTop=body.scrollHeight;i++;body.appendChild(cursor);setTimeout(next,75+Math.random()*45);}else{cursor.remove();if(onDone)setTimeout(onDone,55);}}setTimeout(next,35);}
/* ── TERMINAL WINDOW CONTROLS ── */
let _termMaxActive=null;let _termBgRafs={};
function termClose(id){
const existing=document.getElementById(id+'-cm');
if(existing){existing.remove();return;}
const term=document.getElementById(id);if(!term)return;
const modal=document.createElement('div');modal.id=id+'-cm';modal.className='term-close-modal';
modal.innerHTML='<div class="term-close-title">Terminal Action</div><div class="term-close-btns"><button class="btn btn-ghost" style="font-size:10px;padding:5px 12px;width:100%" onclick="termHide(\''+id+'\')">&#8212; Hide Terminal</button><button class="btn btn-danger" style="font-size:10px;padding:5px 12px;width:100%;margin-top:4px" onclick="termStop(\''+id+'\')">&#9632; Stop Process</button></div>';
term.appendChild(modal);
setTimeout(()=>{const h=function(e){if(!modal.contains(e.target)&&!e.target.closest('.terminal-dot.red')){modal.remove();document.removeEventListener('click',h);}};document.addEventListener('click',h);},200);
}
function termHide(id){document.getElementById(id+'-cm')?.remove();const el=document.getElementById(id);if(el)el.style.display='none';if(_termMaxActive===id)_termRestoreInstant(id);}
function termStop(id){document.getElementById(id+'-cm')?.remove();if(_termMaxActive===id)_termRestoreInstant(id);const el=document.getElementById(id);if(el)el.style.display='none';const isEmbed=id==='embed-terminal';const btn=document.getElementById(isEmbed?'embed-btn':'extract-btn');if(btn){setLoading(btn,false);if(btn._setProc)btn._setProc(false);}}
function termMinimize(id){const term=document.getElementById(id);if(!term)return;if(term.classList.contains('terminal-maximized')){termRestore(id);return;}term.classList.toggle('terminal-minimized');}
function termMaximize(id){
const term=document.getElementById(id);if(!term)return;
if(term.classList.contains('terminal-maximized')){termRestore(id);return;}
if(_termMaxActive&&_termMaxActive!==id)_termRestoreInstant(_termMaxActive);
_termMaxActive=id;
term.classList.remove('terminal-minimized','terminal-restoring');
const bd=document.createElement('div');bd.className='terminal-max-backdrop';bd.id=id+'-maxbd';bd.onclick=()=>termRestore(id);document.body.appendChild(bd);
const isDecrypt=id.includes('extract');
const cvs=document.createElement('canvas');cvs.className='term-max-canvas';cvs.id=id+'-mcvs';term.insertBefore(cvs,term.firstChild);
term.classList.add('terminal-maximized');
requestAnimationFrame(()=>_startTermBg(cvs,isDecrypt,id));
}
function termRestore(id){
const term=document.getElementById(id);if(!term||!term.classList.contains('terminal-maximized'))return;
term.classList.add('terminal-restoring');
const cvs=document.getElementById(id+'-mcvs');
setTimeout(()=>{
document.getElementById(id+'-maxbd')?.remove();
if(cvs){if(_termBgRafs[id])cancelAnimationFrame(_termBgRafs[id]);delete _termBgRafs[id];cvs.remove();}
term.classList.remove('terminal-maximized','terminal-restoring');
if(_termMaxActive===id)_termMaxActive=null;
},240);
}
function _termRestoreInstant(id){
const term=document.getElementById(id);if(!term)return;
document.getElementById(id+'-maxbd')?.remove();
const cvs=document.getElementById(id+'-mcvs');
if(cvs){if(_termBgRafs[id])cancelAnimationFrame(_termBgRafs[id]);delete _termBgRafs[id];cvs.remove();}
term.classList.remove('terminal-maximized','terminal-restoring');
if(_termMaxActive===id)_termMaxActive=null;
}
function _startTermBg(canvas,isDecrypt,termId){
const ctx=canvas.getContext('2d');
const chars=(isDecrypt?'01ABCDEFabcdef\u2B21\u2295\u2297\u25C6':'\u29C1\u2295\u2297\u26D3\u25C6\u2591\u2592\u2593').split('');
const color=isDecrypt?'#c084fc':'#00e5c8';
let W=0,H=0,cols=[];
function resize(){W=canvas.width=canvas.offsetWidth||1000;H=canvas.height=canvas.offsetHeight||600;cols=[];const sp=20;const n=Math.ceil(W/sp)+2;for(let i=0;i<n;i++)cols.push({x:i*sp+sp*.3,y:Math.random()*-H,speed:.38+Math.random()*.75,char:chars[Math.floor(Math.random()*chars.length)],ct:Math.floor(Math.random()*25)});}
resize();
function draw(){ctx.clearRect(0,0,W,H);cols.forEach(c=>{c.y+=c.speed;if(c.y>H+14)c.y=-14;c.ct--;if(c.ct<=0){c.char=chars[Math.floor(Math.random()*chars.length)];c.ct=8+Math.floor(Math.random()*20);}ctx.font='11px "JetBrains Mono",monospace';ctx.globalAlpha=.05;ctx.fillStyle=color;ctx.fillText(c.char,c.x,c.y);});ctx.globalAlpha=1;_termBgRafs[termId]=requestAnimationFrame(draw);}
draw();
}
/* ── PHARAOH HIEROGLYPH BUTTON ANIMATION ── */
const _stegoChars='\u2591\u2592\u2593\u2502\u2524\u251C\u252C\u2534\u25C6\u25C8\u2B21\u2295\u2297\u2584\u2580\u25A0\u25A1\u25AA\u25AB\u29C1'.split('');
const _cryptoChars='\u26D3\u2295\u2297#@$%&\u2020\u2021\u00A7\u2316\u2317\u2318\u229E\u229F\u22A0'.split('');
const _catSolidColors={classical:'#a78bfa',symmetric:'#60a5fa',asymmetric:'#34d399',custom:'#fbbf24',hashing:'#f87171',netrunner:'#f87171'};
function _hasCrypto(){return typeof chain!=='undefined'&&chain.some(s=>['symmetric','asymmetric','classical'].includes(s.category));}
function _getCryptoCt(){return typeof chain!=='undefined'?chain.filter(s=>['symmetric','asymmetric','classical'].includes(s.category)).length:0;}
function _getBtnChars(){return _hasCrypto()?_stegoChars.concat(_cryptoChars):_stegoChars;}
function _getChainSolidColor(fallback){
if(typeof chain!=='undefined'&&chain.some(s=>(s.slug||'').toLowerCase()==='netrunner'))return '#f87171';
if(typeof chain!=='undefined'&&chain.length>0){
const cats=[...new Set(chain.map(s=>s.category))];
if(cats.length>0){const c=_catSolidColors[cats[Math.floor(Math.random()*cats.length)]];if(c)return c;}
}
return fallback;
}
let _hieroBtns={};
function _refreshHieros(){Object.values(_hieroBtns).forEach(b=>b._refresh&&b._refresh());}
function initBtnHiero(canvasId,mode){
const canvas=document.getElementById(canvasId);if(!canvas)return;
const btn=canvas.closest('button');if(!btn)return;
const ctx=canvas.getContext('2d');
let cols=[],W=0,H=0,running=false,raf=null,processing=false;
const isEmbed=mode==='embed';
const defaultFallback=isEmbed?'#00e5c8':'#c084fc';
const cryptoFallback='#f87171';
function _pickColor(isCrypto){return _getChainSolidColor(isCrypto?cryptoFallback:defaultFallback);}
function resize(){
const r=canvas.getBoundingClientRect();
W=canvas.width=Math.round(r.width||btn.offsetWidth||220);
H=canvas.height=Math.round(r.height||btn.offsetHeight||60);
cols=[];
const sp=22;const cnt=Math.ceil(W/sp)+2;
const chars=_getBtnChars();const cct=_getCryptoCt();
for(let i=0;i<cnt;i++){
const goUp=(i%2===0);
cols.push({x:i*sp+Math.random()*4,y:goUp?(H+Math.random()*H):(-Math.random()*H),speed:goUp?(-(0.55+Math.random()*(processing?2.2:1.0))):(0.55+Math.random()*(processing?2.2:1.0)),char:chars[Math.floor(Math.random()*chars.length)],size:11+Math.floor(Math.random()*6),alpha:0.28+Math.random()*0.42,ct:Math.floor(Math.random()*40),isCrypto:cct>0&&Math.random()<(cct/10),color:_pickColor(cct>0&&Math.random()<.3)});
}
}
function draw(){
if(!running)return;
ctx.clearRect(0,0,W,H);
const chars=_getBtnChars();const cct=_getCryptoCt();const sm=processing?3.2:1;const am=processing?1.8:1;
cols.forEach(c=>{
c.y+=c.speed*sm;
if(c.speed<0&&c.y<-H-40)c.y=H+40;
if(c.speed>0&&c.y>H+40)c.y=-H-40;
c.ct--;
if(c.ct<=0){c.char=chars[Math.floor(Math.random()*chars.length)];c.ct=processing?(4+Math.floor(Math.random()*8)):(14+Math.floor(Math.random()*26));c.isCrypto=cct>0&&Math.random()<(cct/10);c.color=_pickColor(c.isCrypto);}
ctx.font=c.size+'px "JetBrains Mono",monospace';
ctx.globalAlpha=Math.min(0.9,c.alpha*am);
ctx.fillStyle=c.color;
ctx.fillText(c.char,c.x,c.y);
});
ctx.globalAlpha=1;
raf=requestAnimationFrame(draw);
}
function start(){if(running)return;resize();running=true;draw();}
function stop(){if(processing)return;running=false;if(raf)cancelAnimationFrame(raf);ctx.clearRect(0,0,W,H);}
btn.addEventListener('mouseenter',start);
btn.addEventListener('mouseleave',stop);
btn.addEventListener('focus',start);
btn.addEventListener('blur',stop);
btn._setProc=(v)=>{processing=v;if(v){start();btn.classList.add('is-processing');}else{btn.classList.remove('is-processing');if(!btn.matches(':hover'))stop();}};
btn._flashOk=()=>{processing=false;btn.classList.remove('is-processing');btn.classList.add('btn-flash-ok');setTimeout(()=>{btn.classList.remove('btn-flash-ok');if(!btn.matches(':hover'))stop();},1100);};
btn._flashErr=()=>{processing=false;btn.classList.remove('is-processing');btn.classList.add('btn-flash-err');setTimeout(()=>{btn.classList.remove('btn-flash-err');if(!btn.matches(':hover'))stop();},1100);};
btn._refresh=()=>{if(running){running=false;if(raf)cancelAnimationFrame(raf);resize();running=true;draw();}};
_hieroBtns[canvasId]=btn;
}
/* ── USE MY STEG KEY ── */
function useMyStegKey(mode){const id=mode==='embed'?'embed-stego-key':'extract-stego-key';const inp=document.getElementById(id);if(!inp)return;const key=typeof USER_STEG_KEY!=='undefined'?USER_STEG_KEY:'';if(!key){showToast('No steg key saved \u2014 set one in Profile');return;}inp.value=key;inp.type='text';inp.style.borderColor='var(--accent)';showToast('Your steg key loaded!');setTimeout(()=>{inp.style.borderColor='';},1600);}
/* ── STEG KEY BLOCKS ── */
function animateKeyBlocks(key){const container=document.getElementById('steg-key-blocks');const progressFill=document.getElementById('steg-key-progress-fill');if(!container)return;container.style.display='block';container.innerHTML='';if(progressFill)progressFill.style.width='0%';const blocks=[];for(let i=0;i<key.length;i++){const span=document.createElement('span');span.className='skb';span.textContent='\u2588';container.appendChild(span);blocks.push(span);if((i+1)%8===0&&i<key.length-1){const sp=document.createElement('span');sp.innerHTML=' ';container.appendChild(sp);}}let lit=0;function lightNext(){if(lit>=blocks.length){if(progressFill)progressFill.style.width='100%';return;}blocks[lit].classList.add('lit');lit++;if(progressFill)progressFill.style.width=Math.round(lit/blocks.length*100)+'%';setTimeout(lightNext,38+Math.random()*25);}setTimeout(lightNext,50);}
/* ── MISC ── */
function toggleSidebar(){const sidebar=document.getElementById('sidebar');const overlay=document.getElementById('sidebar-overlay');const hamburger=document.getElementById('hamburger');if(!sidebar)return;sidebar.classList.toggle('open');if(overlay)overlay.classList.toggle('open');if(hamburger)hamburger.classList.toggle('open');}
function copyText(text,btn){navigator.clipboard.writeText(text).then(()=>{const o=btn.textContent;btn.textContent='\u2713 Copied!';setTimeout(()=>btn.textContent=o,2000);});}
function toggleSwitch(el){el.classList.toggle('on');const t=el.dataset.target;if(t){const el2=document.getElementById(t);if(el2)el2.style.display=el.classList.contains('on')?'block':'none';}}
/* ── DOM READY ── */
document.addEventListener('DOMContentLoaded',()=>{
initPasswordEyes();
document.querySelectorAll('.upload-zone').forEach(zone=>{zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('dragover');});zone.addEventListener('dragleave',()=>zone.classList.remove('dragover'));zone.addEventListener('drop',e=>{e.preventDefault();zone.classList.remove('dragover');const inp=zone.nextElementSibling;if(inp&&inp.tagName==='INPUT'&&e.dataTransfer.files.length){const dt=new DataTransfer();dt.items.add(e.dataTransfer.files[0]);inp.files=dt.files;inp.dispatchEvent(new Event('change'));}});});
document.querySelectorAll('.alert').forEach(el=>{setTimeout(()=>{el.style.transition='opacity .5s';el.style.opacity='0';setTimeout(()=>el.remove(),500);},5000);});
initBtnHiero('embed-hiero','embed');
initBtnHiero('extract-hiero','extract');
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeImgViewer();const vfm=document.getElementById('vf-modal');if(vfm)vfm.remove();if(_termMaxActive)termRestore(_termMaxActive);}});
});
