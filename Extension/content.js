(function(){
'use strict';
const BR=globalThis.browser??globalThis.chrome;
const scanPromises=new Map();
const scannedSrcs=new Set();
const foundSrcs=new Set();
const imgState=new WeakMap();
const overlays=new Map();
let embedPanel=null;
let cfg={enabled:true,highlight:true};
let cfgReady=false;
let rafRunning=false;
BR.storage.local.get('cfg').then(r=>{
if(r.cfg)cfg={...cfg,...r.cfg};
cfgReady=true;
if(cfg.enabled!==false)scanAll();
}).catch(()=>{cfgReady=true;});
BR.runtime.onMessage.addListener(msg=>{
if(msg.type==='CFG_UPDATE'){cfg={...cfg,...msg.cfg};return true;}
if(msg.type==='CONTEXT_INSPECT')doContextInspect(msg.srcUrl);
});
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
async function toB64(blob){
return new Promise(res=>{const fr=new FileReader();fr.onload=()=>res(fr.result.split(',')[1]);fr.readAsDataURL(blob);});
}
async function fetchBlob(url){
if(url.startsWith('blob:')){
try{const r=await fetch(url);if(r.ok){const b=await r.blob();if(b.size>200)return b;}}catch(_){}
return null;
}
try{
const r=await fetch(url,{credentials:'include',mode:'cors'});
if(r.ok){const b=await r.blob();if(b.size>200)return b;}
}catch(_){}
return new Promise(res=>{
const i=new Image();i.crossOrigin='anonymous';
i.onload=()=>{const c=document.createElement('canvas');c.width=i.naturalWidth;c.height=i.naturalHeight;c.getContext('2d').drawImage(i,0,0);c.toBlob(b=>res(b&&b.size>200?b:null),'image/jpeg',0.92);};
i.onerror=()=>res(null);i.src=url;
});
}
async function extractBlob(blob){
const b64=await toB64(blob);
const r=await BR.runtime.sendMessage({type:'STEG_EXTRACT',imageData:b64,mimeType:blob.type||'image/jpeg'});
if(!r||!r.message)throw new Error('none');
return r;
}
function validSrc(src){
if(!src||src.startsWith('data:'))return false;
if(src.startsWith('blob:'))return true;
if(src.includes('mmg.whatsapp.net')||src.includes('pps.whatsapp.net'))return true;
if(src.match(/\.(jpg|jpeg|png|webp)(\?|$)/i))return true;
return false;
}
function bigEnough(img){return(img.naturalWidth||0)>=40&&(img.naturalHeight||0)>=40;}
function scanSrc(src){
if(scanPromises.has(src))return scanPromises.get(src);
const p=(async()=>{try{const b=await fetchBlob(src);if(!b)return null;return await extractBlob(b);}catch(_){return null;}})();
scanPromises.set(src,p);
return p;
}
function posRect(img,el){
const r=img.getBoundingClientRect();
if(r.width<1||r.height<1){el.style.display='none';return;}
el.style.cssText+=`;display:block;left:${r.left}px;top:${r.top}px;width:${r.width}px;height:${r.height}px;`;
}
function ensureRaf(){
if(rafRunning)return;rafRunning=true;
const tick=()=>{overlays.forEach((o,img)=>{if(!img.isConnected){removeOverlay(img);return;}posRect(img,o.ov);});if(overlays.size)requestAnimationFrame(tick);else rafRunning=false;};
requestAnimationFrame(tick);
}
function removeOverlay(img){const o=overlays.get(img);if(o){o.ov.remove();overlays.delete(img);}}
function makeOverlay(img){
const ov=document.createElement('div');ov.className='so-overlay';
const btn=document.createElement('button');btn.className='so-reveal-btn';btn.innerHTML='◈ reveal';
ov.appendChild(btn);document.body.appendChild(ov);
let t=null;
const show=()=>{clearTimeout(t);btn.classList.add('so-show');};
const hide=()=>{t=setTimeout(()=>btn.classList.remove('so-show'),150);};
img.addEventListener('mouseenter',show);img.addEventListener('mouseleave',hide);
btn.addEventListener('mouseenter',show);btn.addEventListener('mouseleave',hide);
return{ov,btn};
}
function attachUI(img,rec,result){
if(rec.uiAttached)return;rec.uiAttached=true;rec.hasResult=true;
if(cfg.highlight!==false)img.classList.add('so-has-msg');
const o=makeOverlay(img);
o.btn.onclick=e=>{e.stopPropagation();showPanel(result.message,result.method,img.currentSrc||img.src);};
overlays.set(img,o);posRect(img,o.ov);ensureRaf();
}
function showPanel(msg,method,thumb){
document.querySelector('.so-panel')?.remove();
const p=document.createElement('div');p.className='so-panel';
p.innerHTML=`<div class="so-pi"><div class="so-ph"><span class="so-pname">◈ STEGOPS <span class="so-tag">${esc(method)}</span></span><button class="so-px">×</button></div>${thumb?`<img class="so-pt" src="${esc(thumb)}" onerror="this.style.display='none'" alt="">`:''}<div class="so-plabel">hidden message</div><div class="so-pb">${esc(msg)}</div><div style="display:flex;gap:6px;margin-top:9px"><button class="so-copy">copy</button><button class="so-close2">close</button></div></div>`;
document.body.appendChild(p);
const close=()=>p.remove();
p.querySelector('.so-px').onclick=close;p.querySelector('.so-close2').onclick=close;
p.querySelector('.so-copy').onclick=function(){navigator.clipboard.writeText(msg);this.textContent='✓';setTimeout(()=>{this.textContent='copy';},1500);};
}
async function scanImg(img,rec){
if(rec.scanStarted||cfg.enabled===false)return;rec.scanStarted=true;
const src=rec.src;const isNew=!scanPromises.has(src);
if(isNew&&!scannedSrcs.has(src))scannedSrcs.add(src);
const result=await scanSrc(src);
if(imgState.get(img)!==rec)return;
if(result){
if(!foundSrcs.has(src)){foundSrcs.add(src);BR.runtime.sendMessage({type:'INCR_STATS',keys:['scanned','found']}).catch(()=>{});}
else if(isNew)BR.runtime.sendMessage({type:'INCR_STATS',keys:['scanned']}).catch(()=>{});
attachUI(img,rec,result);
}else if(isNew){BR.runtime.sendMessage({type:'INCR_STATS',keys:['scanned']}).catch(()=>{});}
}
function considerImg(img){
if(cfg.enabled===false)return;
const src=img.currentSrc||img.src||'';if(!validSrc(src))return;
const prev=imgState.get(img);if(prev&&prev.src===src)return;
if(prev){removeOverlay(img);img.classList.remove('so-has-msg');}
const rec={src,uiAttached:false,hasResult:false,scanStarted:false};
imgState.set(img,rec);
if(!bigEnough(img)){if(!img.complete)img.addEventListener('load',()=>{if(imgState.get(img)===rec&&bigEnough(img)&&cfg.enabled!==false)scanImg(img,rec);},{once:true});return;}
scanImg(img,rec);
}
function scanAll(){document.querySelectorAll('img').forEach(considerImg);}
new MutationObserver(muts=>{
for(const m of muts){
for(const n of m.addedNodes){if(n.nodeType!==1)continue;if(n.tagName==='IMG')considerImg(n);n.querySelectorAll?.('img').forEach(considerImg);}
if(m.type==='attributes'&&m.target.tagName==='IMG')considerImg(m.target);
}
}).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['src','currentSrc']});
async function doContextInspect(srcUrl){
try{const b=await fetchBlob(srcUrl);if(!b)throw 0;const r=await extractBlob(b);showPanel(r.message,r.method,srcUrl);}
catch(_){showPanel('no hidden message found.','—',null);}
}
let sendingSticker=false;
document.addEventListener('click',e=>{
if(cfg.enabled===false||sendingSticker)return;
const item=e.target.closest('[data-id="sticker-item"]');
if(!item)return;
const img=item.querySelector('img[src^="blob:"]');
if(!img)return;
e.preventDefault();e.stopImmediatePropagation();
showStickerPanel(img.src,item);
},true);
async function sendSticker(item){
sendingSticker=true;
try{
const btn=item.closest('button')||item.closest('[role="button"]')||item;
for(const ev of['pointerdown','pointerup','mousedown','mouseup','click']){
btn.dispatchEvent(new PointerEvent(ev,{bubbles:true,cancelable:true,composed:true,isPrimary:true}));
await new Promise(r=>setTimeout(r,20));
}
}finally{setTimeout(()=>{sendingSticker=false;},500);}
}
async function showStickerPanel(blobSrc,item){
if(embedPanel)embedPanel.remove();
const p=document.createElement('div');p.className='so-embed';
p.innerHTML=`<div class="so-ebox"><div class="so-eh"><b class="so-etitle">◈ STEGOPS</b><div style="display:flex;gap:6px;align-items:center"><span class="so-tag">DCT</span><span class="so-size-info">sticker</span><button class="so-skip" id="so-skip">skip</button></div></div><img class="so-prev" src="${esc(blobSrc)}" alt=""><label class="so-elabel">secret message</label><textarea class="so-ta" id="so-ta" placeholder="type your secret…" maxlength="2000"></textarea><div class="so-emeta"><span class="so-size-note">DCT · local</span><span id="so-chars">0/2000</span></div><div class="so-efoot"><button class="so-btn-g" id="so-cancel">cancel</button><button class="so-btn-p" id="so-go">embed + send</button></div><div class="so-estat" id="so-estat" style="display:none"></div></div>`;
document.body.appendChild(p);embedPanel=p;
const ta=p.querySelector('#so-ta'),chars=p.querySelector('#so-chars');
ta.addEventListener('input',()=>{chars.textContent=ta.value.length+'/2000';});
setTimeout(()=>ta.focus(),80);
const close=()=>{p.remove();embedPanel=null;};
p.querySelector('#so-skip').onclick=()=>{close();setTimeout(()=>sendSticker(item),80);};
p.querySelector('#so-cancel').onclick=close;
p.querySelector('#so-go').onclick=async()=>{
const msg=ta.value.trim();
if(!msg){ta.classList.add('so-shake');setTimeout(()=>ta.classList.remove('so-shake'),400);return;}
const st=p.querySelector('#so-estat'),btn=p.querySelector('#so-go');
btn.disabled=true;st.style.display='block';st.textContent='embedding…';
try{
const blob=await fetchBlob(blobSrc);
if(!blob)throw new Error('cannot read sticker');
const b64=await toB64(blob);
const res=await BR.runtime.sendMessage({type:'STEG_EMBED',imageData:b64,mimeType:'image/jpeg',message:msg,forceDct:true});
const arr=Uint8Array.from(atob(res.imageData),c=>c.charCodeAt(0));
st.textContent=`✓ DCT · ${Math.round(blob.size/1024)}→${Math.round(arr.length/1024)}KB`;
BR.runtime.sendMessage({type:'INCR_STATS',keys:['embedded']}).catch(()=>{});
const newBlob=new Blob([arr],{type:'image/jpeg'});
const newUrl=URL.createObjectURL(newBlob);
const imgEl=item.querySelector('img');
const origSrc=imgEl.src;
imgEl.src=newUrl;
await new Promise(r=>setTimeout(r,300));
close();
await new Promise(r=>setTimeout(r,150));
await sendSticker(item);
setTimeout(()=>{try{imgEl.src=origSrc;URL.revokeObjectURL(newUrl);}catch(_){}},4000);
}catch(err){st.textContent='✗ '+err.message;btn.disabled=false;}
};
requestAnimationFrame(()=>p.classList.add('so-embed--in'));
}
document.addEventListener('change',async e=>{
if(cfg.enabled===false)return;
const inp=e.target;if(inp.type!=='file')return;
const file=inp.files?.[0];
if(!file||!file.type.startsWith('image/'))return;
if(inp.dataset.soProcessed){delete inp.dataset.soProcessed;return;}
e.stopImmediatePropagation();
showFilePanel(file,inp);
},true);
function showFilePanel(file,inp){
if(embedPanel)embedPanel.remove();
const isLsb=file.type==='image/png'||file.type==='image/webp';
const method=isLsb?'LSB':'DCT';
const p=document.createElement('div');p.className='so-embed';
p.innerHTML=`<div class="so-ebox"><div class="so-eh"><b class="so-etitle">◈ STEGOPS</b><div style="display:flex;gap:6px;align-items:center"><span class="so-tag">${method}</span><span class="so-size-info">${Math.round(file.size/1024)}KB</span><button class="so-skip" id="so-skip">skip</button></div></div><img class="so-prev" id="so-prev" alt=""><label class="so-elabel">secret message</label><textarea class="so-ta" id="so-ta" placeholder="type your secret…" maxlength="2000"></textarea><div class="so-emeta"><span class="so-size-note">${method} · local</span><span id="so-chars">0/2000</span></div><div class="so-efoot"><button class="so-btn-g" id="so-cancel">cancel</button><button class="so-btn-p" id="so-go">embed + send</button></div><div class="so-estat" id="so-estat" style="display:none"></div></div>`;
document.body.appendChild(p);embedPanel=p;
const pv=p.querySelector('#so-prev'),ou=URL.createObjectURL(file);
pv.src=ou;pv.onload=()=>URL.revokeObjectURL(ou);
const ta=p.querySelector('#so-ta'),chars=p.querySelector('#so-chars');
ta.addEventListener('input',()=>{chars.textContent=ta.value.length+'/2000';});
setTimeout(()=>ta.focus(),80);
const close=()=>{p.remove();embedPanel=null;};
p.querySelector('#so-skip').onclick=()=>{inp.dataset.soProcessed='1';close();inp.dispatchEvent(new Event('change',{bubbles:true}));};
p.querySelector('#so-cancel').onclick=close;
p.querySelector('#so-go').onclick=async()=>{
const msg=ta.value.trim();
if(!msg){ta.classList.add('so-shake');setTimeout(()=>ta.classList.remove('so-shake'),400);return;}
const st=p.querySelector('#so-estat'),btn=p.querySelector('#so-go');
btn.disabled=true;st.style.display='block';st.textContent='embedding…';
try{
const b64=await toB64(new Blob([await file.arrayBuffer()],{type:file.type}));
const res=await BR.runtime.sendMessage({type:'STEG_EMBED',imageData:b64,mimeType:file.type,message:msg});
const arr=Uint8Array.from(atob(res.imageData),c=>c.charCodeAt(0));
st.textContent=`✓ ${res.method} · ${Math.round(file.size/1024)}→${Math.round(arr.length/1024)}KB`;
BR.runtime.sendMessage({type:'INCR_STATS',keys:['embedded']}).catch(()=>{});
const ext=res.mimeType==='image/png'?'png':'jpg';
const newFile=new File([arr],file.name.replace(/\.\w+$/,`.${ext}`),{type:res.mimeType});
const dt=new DataTransfer();dt.items.add(newFile);
inp.dataset.soProcessed='1';inp.files=dt.files;
setTimeout(()=>{
close();
inp.dispatchEvent(new Event('change',{bubbles:true}));
setTimeout(()=>{
(document.querySelector('[data-testid="wds-ic-send-filled"]')?.closest('[role="button"]')
||document.querySelector('[data-testid="send"]')
||document.querySelector('[aria-label="Send"]'))?.click();
},700);
},800);
}catch(err){st.textContent='✗ '+err.message;btn.disabled=false;}
};
requestAnimationFrame(()=>p.classList.add('so-embed--in'));
}
})();