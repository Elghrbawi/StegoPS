(function(){
'use strict';
const scanning=new Set();
const scanned=new Map();
let embedPanel=null;
let stats={detected:0,hidden:0,detectedList:[]};
let cfg={autoExtract:true,highlight:true,showHoverBtn:true};
browser.storage.local.get('cfg').then(r=>{if(r.cfg)cfg={...cfg,...r.cfg};}).catch(()=>{});
browser.runtime.onMessage.addListener(msg=>{
if(msg.type==='CFG_UPDATE'){
cfg={...cfg,...msg.cfg};
if(!cfg.highlight){
document.querySelectorAll('.so-has-msg').forEach(el=>el.classList.remove('so-has-msg'));
}else{
scanned.forEach((result,src)=>{
if(!result)return;
const img=findImgBySrc(src);
if(img){const w=findWrap(img);if(w)w.classList.add('so-has-msg');}
});
}
}
if(msg.type==='CONTEXT_INSPECT')doContextInspect(msg.srcUrl);
if(msg.type==='GET_STATS')return Promise.resolve(stats);
});
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function findImgBySrc(src){for(const img of document.querySelectorAll('img')){if(img.src===src||img.currentSrc===src)return img;}return null;}
async function toB64(blob){return new Promise(resolve=>{const fr=new FileReader();fr.onload=()=>resolve(fr.result.split(',')[1]);fr.readAsDataURL(blob);});}
async function fetchImageBlob(url){
try{
const r=await fetch(url,{credentials:'include',mode:'same-origin'});
if(r.ok){const b=await r.blob();if(b.size>500)return b;}
}catch(_){}
return new Promise((resolve,reject)=>{
const img=new Image();
img.crossOrigin='anonymous';
img.onload=()=>{
const c=document.createElement('canvas');
c.width=img.naturalWidth;c.height=img.naturalHeight;
c.getContext('2d').drawImage(img,0,0);
c.toBlob(b=>(b&&b.size>500)?resolve(b):reject(new Error('empty')),'image/png');
};
img.onerror=()=>reject(new Error('load failed'));
img.src=url;
});
}
async function extractFromBlob(blob){
const b64=await toB64(blob);
const result=await browser.runtime.sendMessage({type:'STEG_EXTRACT',imageData:b64,mimeType:blob.type||'image/jpeg'});
if(!result||!result.message)throw new Error('no payload');
return result;
}
function findWrap(img){
let el=img.parentElement;
for(let i=0;i<7;i++){
if(!el)break;
if(el.tagName==='FIGURE'||el.getAttribute('data-testid')==='image-thumb')return makeRelative(el);
const s=getComputedStyle(el);
if((s.position==='relative'||s.position==='absolute')&&el.offsetWidth>60)return el;
el=el.parentElement;
}
return img.parentElement?makeRelative(img.parentElement):null;
}
function makeRelative(el){if(getComputedStyle(el).position==='static')el.style.position='relative';return el;}
function attachHoverUI(img,result){
const wrap=findWrap(img);
if(!wrap||wrap.dataset.soAttached)return;
wrap.dataset.soAttached='1';
if(cfg.highlight)wrap.classList.add('so-has-msg');
const btn=document.createElement('button');
btn.className='so-reveal-btn';
btn.innerHTML='<span class="so-rbico">◈</span><span class="so-rbtxt">Show text</span>';
btn.onclick=e=>{e.stopPropagation();showMsgPanel(result.message,result.method,img.src);};
wrap.appendChild(btn);
}
function showMsgPanel(msg,method,thumbSrc){
document.querySelector('.so-panel')?.remove();
const p=document.createElement('div');
p.className='so-panel';
p.innerHTML=`<div class="so-pi"><div class="so-ph"><span class="so-pname">⬡ STEGOPS <span class="so-tag">${esc(method)}</span></span><button class="so-px">×</button></div>${thumbSrc?`<img class="so-pt" src="${esc(thumbSrc)}" onerror="this.style.display='none'" alt="">`:''}<div class="so-plabel">Hidden message</div><div class="so-pb">${esc(msg)}</div><div style="display:flex;gap:6px;margin-top:9px"><button class="so-copy">Copy</button><button class="so-close2">Close</button></div></div>`;
document.body.appendChild(p);
const close=()=>p.remove();
p.querySelector('.so-px').onclick=close;
p.querySelector('.so-close2').onclick=close;
p.querySelector('.so-copy').onclick=function(){navigator.clipboard.writeText(msg);this.textContent='✓ Copied';setTimeout(()=>{this.textContent='Copy';},1500);};
}
async function scanChatImage(img){
const src=img.src||img.currentSrc||'';
if(!src.startsWith('blob:'))return;
if(scanning.has(src)||scanned.has(src))return;
if((img.naturalWidth||0)<40||(img.naturalHeight||0)<40)return;
scanning.add(src);
stats.detected++;
notifyPopup();
const wrap=findWrap(img);
let badge=null;
if(wrap&&!wrap.dataset.soScanning){
wrap.dataset.soScanning='1';
badge=document.createElement('div');
badge.className='so-scanning';
badge.textContent='…';
wrap.appendChild(badge);
}
try{
const blob=await fetchImageBlob(src);
let result=null;
try{result=await extractFromBlob(blob);}catch(_){}
scanned.set(src,result);
scanning.delete(src);
badge?.remove();
if(wrap)delete wrap.dataset.soScanning;
if(result){
stats.hidden++;
stats.detectedList.push({src,msg:result.message,method:result.method,thumbSrc:src});
notifyPopup();
attachHoverUI(img,result);
}
}catch(err){
scanning.delete(src);
badge?.remove();
if(wrap)delete wrap.dataset.soScanning;
scanned.set(src,null);
}
}
function notifyPopup(){browser.runtime.sendMessage({type:'STATS_UPDATE',stats}).catch(()=>{});}
function watchImg(img){
const src=img.src||img.currentSrc||'';
if(!src.startsWith('blob:'))return;
if(scanned.has(src)||scanning.has(src))return;
if(img.complete&&img.naturalWidth>0)scanChatImage(img);
else img.addEventListener('load',()=>{if(cfg.autoExtract!==false)scanChatImage(img);},{once:true});
}
new MutationObserver(muts=>{
for(const m of muts)for(const n of m.addedNodes){
if(n.nodeType!==1)continue;
if(n.tagName==='IMG')watchImg(n);
n.querySelectorAll?.('img').forEach(watchImg);
}
}).observe(document.body,{childList:true,subtree:true});
setTimeout(()=>document.querySelectorAll('img').forEach(watchImg),1500);
setTimeout(()=>document.querySelectorAll('img').forEach(watchImg),4000);
async function doContextInspect(srcUrl){
try{const blob=await fetchImageBlob(srcUrl);const result=await extractFromBlob(blob);showMsgPanel(result.message,result.method,srcUrl);}
catch(_){showMsgPanel('No hidden message found in this image.','—',null);}
}
document.addEventListener('change',async e=>{
const inp=e.target;
if(inp.type!=='file')return;
const file=inp.files?.[0];
if(!file||!file.type.startsWith('image/'))return;
if(inp.dataset.soProcessed){delete inp.dataset.soProcessed;return;}
e.stopImmediatePropagation();
showEmbedPanel(file,inp);
},true);
function showEmbedPanel(file,inp){
if(embedPanel)embedPanel.remove();
const isPNG=file.type==='image/png';
const method=isPNG?'PNG-LSB':'DCT';
const kbSize=Math.round(file.size/1024);
const p=document.createElement('div');
p.className='so-embed';
p.innerHTML=`<div class="so-ebox"><div class="so-eh"><b class="so-etitle">⬡ STEGOPS</b><div style="display:flex;gap:6px;align-items:center"><span class="so-tag">${method}</span><span class="so-size-info">${kbSize} KB</span><button class="so-skip" id="so-skip">Skip →</button></div></div><img class="so-prev" id="so-prev" alt=""><label class="so-elabel">Secret message</label><textarea class="so-ta" id="so-ta" placeholder="Type your secret message…" maxlength="2000"></textarea><div class="so-emeta"><span class="so-size-note">${method} · local server</span><span id="so-chars">0 / 2000</span></div><div class="so-efoot"><button class="so-btn-g" id="so-cancel">Cancel</button><button class="so-btn-p" id="so-go">⬡ Embed &amp; Send</button></div><div class="so-estat" id="so-estat" style="display:none"></div></div>`;
document.body.appendChild(p);
embedPanel=p;
const pv=p.querySelector('#so-prev');
const ou=URL.createObjectURL(file);
pv.src=ou;pv.onload=()=>URL.revokeObjectURL(ou);
const ta=p.querySelector('#so-ta');
const chars=p.querySelector('#so-chars');
ta.addEventListener('input',()=>{ta.style.height='auto';ta.style.height=Math.min(ta.scrollHeight,140)+'px';chars.textContent=ta.value.length+' / 2000';});
setTimeout(()=>ta.focus(),80);
const close=()=>{p.remove();embedPanel=null;};
p.querySelector('#so-skip').onclick=()=>{inp.dataset.soProcessed='1';close();inp.dispatchEvent(new Event('change',{bubbles:true}));};
p.querySelector('#so-cancel').onclick=close;
p.querySelector('#so-go').onclick=async()=>{
const msg=ta.value.trim();
if(!msg){ta.classList.add('so-shake');setTimeout(()=>ta.classList.remove('so-shake'),400);return;}
const st=p.querySelector('#so-estat');
const btn=p.querySelector('#so-go');
btn.disabled=true;st.style.display='block';st.textContent='Embedding…';
try{
const b64=await toB64(new Blob([await file.arrayBuffer()],{type:file.type}));
const res=await browser.runtime.sendMessage({type:'STEG_EMBED',imageData:b64,mimeType:file.type,message:msg});
const bin=atob(res.imageData);
const arr=new Uint8Array(bin.length);
for(let i=0;i<bin.length;i++)arr[i]=bin.charCodeAt(i);
const origKb=Math.round(file.size/1024);
const newKb=Math.round((res.outputSize||arr.length)/1024);
st.textContent=`✓ ${res.method} · ${origKb}→${newKb} KB`;
const ext=res.mimeType==='image/png'?'png':'jpg';
const newFile=new File([arr],file.name.replace(/\.(png|jpg|jpeg|webp)$/i,`.${ext}`),{type:res.mimeType});
const dt=new DataTransfer();dt.items.add(newFile);
inp.dataset.soProcessed='1';inp.files=dt.files;
setTimeout(()=>{close();inp.dispatchEvent(new Event('change',{bubbles:true}));setTimeout(()=>{const sb=document.querySelector('[aria-label="Send 1 selected"]')||document.querySelector('[data-testid="wds-ic-send-filled"]')?.closest('[role="button"]');sb?.click();},700);},900);
}catch(err){st.textContent='✗ '+err.message;btn.disabled=false;}
};
requestAnimationFrame(()=>p.classList.add('so-embed--in'));
}
})();
