const SERVER='http://127.0.0.1:5001';
const SECRET='stegops_internal_secret_2025';
const HEADERS={'Content-Type':'application/json','Authorization':`Bearer ${SECRET}`};
async function serverCall(endpoint,body){
const res=await fetch(`${SERVER}${endpoint}`,{method:'POST',headers:HEADERS,body:JSON.stringify(body)});
const json=await res.json();
if(!res.ok||json.error)throw new Error(json.error||`Server error ${res.status}`);
return json;
}
async function serverHealth(){
try{
const res=await fetch(`${SERVER}/health`,{method:'GET',headers:HEADERS});
const json=await res.json();
return{ok:res.ok,status:json.status||'ok'};
}catch(e){return{ok:false,status:'unreachable'};}
}
async function handleImageSelection({imageData,mimeType,message}){
const method=mimeType==='image/png'?'lsb':'dct';
const result=await serverCall('/api/embed',{
carrier_b64:imageData,
steg_method:method,
payload:message,
payload_type:'text',
stego_key:'',
file_type:'image',
op_id:Date.now(),
steps:[],
});
return{imageData:result.output_b64,mimeType:method==='dct'?'image/jpeg':'image/png',method:result.steg_method||method,outputSize:result.output_size||0};
}
async function handleChatImageDetection({imageData,mimeType}){
const methods=mimeType==='image/png'?['lsb','dct']:['dct','lsb'];
for(const method of methods){
try{
const result=await serverCall('/api/extract',{stego_b64:imageData,steg_method:method,stego_key:'',file_type:'image',steps:[]});
if(result.success&&result.payload)return{message:result.payload,method:result.steg_method||method};
}catch(_){}
}
return null;
}
browser.runtime.onMessage.addListener((msg,_sender)=>{
if(msg.type==='STEG_EMBED')return handleImageSelection(msg);
if(msg.type==='STEG_EXTRACT')return handleChatImageDetection(msg);
if(msg.type==='GET_HEALTH')return serverHealth();
return false;
});
browser.runtime.onInstalled.addListener(()=>{
browser.contextMenus.create({id:'stegops-inspect',title:'🔍 STEGOPS — Inspect for hidden message',contexts:['image'],documentUrlPatterns:['https://web.whatsapp.com/*']});
});
browser.contextMenus.onClicked.addListener((info,tab)=>{
if(info.menuItemId==='stegops-inspect'&&tab)browser.tabs.sendMessage(tab.id,{type:'CONTEXT_INSPECT',srcUrl:info.srcUrl});
});
