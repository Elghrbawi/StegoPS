const SERVER='http://127.0.0.1:5001';
const SECRET='stegops_internal_secret_2025';
const AUTH={'Content-Type':'application/json','Authorization':`Bearer ${SECRET}`};
async function serverCall(endpoint,body){
const res=await fetch(`${SERVER}${endpoint}`,{method:'POST',headers:AUTH,body:JSON.stringify(body)});
const json=await res.json();
if(!res.ok||json.error)throw new Error(json.error||`${res.status}`);
return json;
}
async function serverHealth(){
try{
const res=await fetch(`${SERVER}/health`,{method:'GET',headers:{'Authorization':`Bearer ${SECRET}`}});
const json=await res.json();
return{ok:res.ok,status:json.status||'ok'};
}catch(e){return{ok:false,status:'unreachable'};}
}
async function handleEmbed({imageData,mimeType,message,forceDct}){
const dct=forceDct||(mimeType!=='image/png'&&mimeType!=='image/webp');
const method=dct?'dct':'lsb';
const result=await serverCall('/api/embed',{
carrier_b64:imageData,steg_method:method,payload:message,
payload_type:'text',stego_key:'',file_type:'image',op_id:Date.now(),steps:[],
});
return{imageData:result.output_b64,mimeType:dct?'image/jpeg':'image/png',method:result.steg_method||method,outputSize:result.output_size||0};
}
async function handleExtract({imageData,mimeType}){
const preferDct=mimeType!=='image/png'&&mimeType!=='image/webp';
const methods=preferDct?['dct','lsb']:['lsb','dct'];
for(const method of methods){
try{
const r=await serverCall('/api/extract',{stego_b64:imageData,steg_method:method,stego_key:'',file_type:'image',steps:[]});
if(r.success&&r.payload)return{message:r.payload,method:r.steg_method||method};
}catch(_){}
}
return null;
}
async function incrStats(keys){
const r=await browser.storage.local.get('stegStats');
const s=r.stegStats||{scanned:0,found:0,embedded:0};
for(const k of keys)s[k]=(s[k]||0)+1;
await browser.storage.local.set({stegStats:s});
return s;
}
browser.runtime.onMessage.addListener((msg)=>{
if(msg.type==='STEG_EMBED')return handleEmbed(msg);
if(msg.type==='STEG_EXTRACT')return handleExtract(msg);
if(msg.type==='GET_HEALTH')return serverHealth();
if(msg.type==='INCR_STATS')return incrStats(msg.keys);
return false;
});
browser.runtime.onInstalled.addListener(()=>{
browser.contextMenus.create({
id:'stegops-inspect',
title:'◈ StegOps — inspect for hidden message',
contexts:['image'],
documentUrlPatterns:['https://web.whatsapp.com/*'],
});
});
browser.contextMenus.onClicked.addListener((info,tab)=>{
if(info.menuItemId==='stegops-inspect'&&tab)
browser.tabs.sendMessage(tab.id,{type:'CONTEXT_INSPECT',srcUrl:info.srcUrl});
});
