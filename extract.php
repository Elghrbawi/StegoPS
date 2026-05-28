<?php
$pageTitle='Extract';$activePage='extract';
require_once 'php/includes/header.php';
require_once 'php/includes/chain_manager.php';
$manager=new ChainManager();$algos=$manager->getAllAlgorithms();
$isGuest=$auth->isGuest();
$chainsWithSteps=$isGuest?[]:$manager->getUserChainsWithSteps($user['user_id']);
$userSk=htmlspecialchars($user['steg_key']??'');
$chainKeysMap=[];
foreach($chainsWithSteps as $c){$stepsArr=array_map(fn($s)=>[$s['algo_slug'],$s['algo_name'],$s['category'],'',''],$c['steps']);$chainKeysMap[$c['chain_id']]=base64_encode(json_encode($stepsArr));}
$preFileId=(int)($_GET['file']??0);$preOpId=(int)($_GET['op']??0);$preFile=null;$preOp=null;
if($preFileId>0)$preFile=$db->fetchOne("SELECT * FROM media_files WHERE file_id=? LIMIT 1",[$preFileId]);
if($preOpId>0)$preOp=$db->fetchOne("SELECT op_id,stego_technique,payload_type FROM stego_operations WHERE op_id=? LIMIT 1",[$preOpId]);
function fmtSzEx(int $b):string{if($b<1024)return $b.' B';if($b<1048576)return round($b/1024,1).' KB';return round($b/1048576,2).' MB';}
?>
<style>
.steg-method-section{margin-top:10px}
.steg-method-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.sml-dot{width:6px;height:6px;border-radius:50%;background:#c084fc;display:inline-block;animation:pulse-dot 1.8s ease-in-out infinite}
@keyframes pulse-dot{0%,100%{opacity:.4;transform:scale(1)}50%{opacity:1;transform:scale(1.3)}}
.steg-method-cards{display:flex;flex-direction:column;gap:6px}
.steg-method-card{position:relative;display:flex;align-items:flex-start;gap:10px;padding:9px 12px;border-radius:8px;border:1px solid var(--border);cursor:pointer;transition:border-color .18s,background .18s;background:var(--surface)}
.steg-method-card:hover{border-color:#c084fc;background:rgba(192,132,252,.05)}
.steg-method-card.selected{border-color:#c084fc;background:rgba(192,132,252,.08)}
.steg-method-card.special-method{border-color:rgba(251,191,36,.25)}
.steg-method-card.special-method:hover,.steg-method-card.special-method.selected{border-color:#fbbf24;background:rgba(251,191,36,.06)}
.steg-method-card.special-method .smc-indicator{border-color:rgba(251,191,36,.4)}
.steg-method-card.special-method.selected .smc-indicator{border-color:#fbbf24}
.steg-method-card.special-method.selected .smc-indicator::after{background:#fbbf24}
.steg-method-card input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.smc-indicator{width:14px;height:14px;border-radius:50%;border:2px solid var(--border);flex-shrink:0;margin-top:2px;transition:border-color .18s;position:relative}
.steg-method-card.selected .smc-indicator{border-color:#c084fc}
.steg-method-card.selected .smc-indicator::after{content:'';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:6px;height:6px;border-radius:50%;background:#c084fc}
.smc-body{flex:1;min-width:0}
.smc-name{font-family:'Space Mono',monospace;font-size:11px;color:var(--text);font-weight:600;margin-bottom:2px}
.smc-desc{font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--text-muted);line-height:1.45}
.smc-badge{display:inline-block;font-family:'Space Mono',monospace;font-size:8px;padding:1px 5px;border-radius:3px;margin-left:5px;vertical-align:middle}
.smc-badge.dct{background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.3)}
.smc-badge.special{background:rgba(192,132,252,.15);color:#c084fc;border:1px solid rgba(192,132,252,.3)}
.steg-method-loading,.steg-method-placeholder{font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);padding:10px 0;display:flex;align-items:center;gap:8px}
.steg-method-placeholder{justify-content:center;border:1px dashed var(--border);border-radius:7px;color:var(--text-dim);opacity:.7}
.sml-spinner{width:12px;height:12px;border:2px solid var(--border);border-top-color:#c084fc;border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0}
.upload-zone{transition:border-color .15s,background .15s,transform .15s}
.upload-zone.drag-over{border-color:#c084fc!important;background:rgba(192,132,252,.13)!important;transform:scale(1.015)}
.pre-file-banner{display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(192,132,252,.06);border:1px solid rgba(192,132,252,.2);border-radius:8px;margin-bottom:10px;font-family:'Space Mono',monospace;font-size:10px;color:#c084fc}
.pre-file-banner .pfb-icon{font-size:16px;flex-shrink:0}
.pre-file-banner .pfb-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text)}
.pre-file-banner .pfb-size,.pre-file-banner .pfb-op{color:var(--text-muted);white-space:nowrap;font-size:9px}
.pre-file-banner .pfb-clear{background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:14px;padding:2px 4px;transition:color .2s}
.pre-file-banner .pfb-clear:hover{color:var(--danger)}
.payload-img-result{max-width:100%;max-height:220px;object-fit:contain;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;display:block;cursor:zoom-in}
.payload-reveal{padding:12px;background:rgba(0,0,0,.2);border-top:1px solid var(--border)}
.payload-reveal-label{font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px}
.payload-reveal-content{font-family:'JetBrains Mono',monospace;font-size:11px;color:#34d399;background:rgba(0,0,0,.3);padding:10px;border-radius:6px;border:1px solid rgba(52,211,153,.2);max-height:200px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;line-height:1.5}
.payload-actions{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.payload-meta{font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-bottom:8px}
</style>
<div class="page-header" style="margin-bottom:16px">
  <div><div class="page-title">Extract Payload</div><div class="page-sub">// UPLOAD STEGO FILE &rarr; DECODE &rarr; REVEAL</div></div>
</div>
<div class="embed-layout extract-layout" id="extract-layout">
  <div class="embed-left">
    <div class="card" id="card-stego-upload">
      <div class="card-title">01 &mdash; Stego File</div>
      <?php if($preFile):?>
        <div class="pre-file-banner" id="pre-file-banner">
          <span class="pfb-icon">&#9673;</span>
          <span class="pfb-name"><?=htmlspecialchars($preFile['original_filename'])?></span>
          <span class="pfb-size"><?=fmtSzEx((int)$preFile['file_size_bytes'])?></span>
          <?php if($preOp):?><span class="pfb-op"><?=strtoupper((string)$preOp['stego_technique'])?></span><?php endif?>
          <button class="pfb-clear" onclick="clearPreFile()" title="Clear">&#10005;</button>
        </div>
        <input type="hidden" id="pre-stego-file-id" value="<?=$preFileId?>">
        <input type="hidden" id="pre-stego-type" value="<?=htmlspecialchars($preFile['file_type']??'image')?>">
        <div style="display:none" id="stego-upload-row">
      <?php else:?>
        <div class="carrier-upload-row" id="stego-upload-row">
      <?php endif?>
          <div class="upload-zone carrier-drop-zone" onclick="document.getElementById('stego-input').click()">
            <div class="upload-icon">&#9673;</div>
            <div class="upload-text">Click or drag stego file</div>
            <div class="upload-sub">PNG / JPG / WAV / MP4 &mdash; file with hidden payload</div>
          </div>
          <div id="stego-preview" class="carrier-preview-panel" style="display:none"></div>
        </div>
        <input type="file" id="stego-input" style="display:none" onchange="handleCarrierSelect(this,'stego-preview');onStegoFileSelected(this)" accept="image/*,audio/*,video/*">
    </div>
    <div class="card" id="card-stego-method">
      <div class="card-title">02 &mdash; Steganography Method</div>
      <div class="steg-method-section">
        <div class="steg-method-label"><span class="sml-dot"></span>Method used during embedding</div>
        <div class="steg-method-cards" id="extract-method-cards">
          <div class="steg-method-placeholder">&#11025; Upload a stego file to see methods</div>
        </div>
      </div>
      <input type="hidden" id="extract-steg-method-input" value="<?=htmlspecialchars($preOp['stego_technique']??'lsb')?>">
    </div>
    <div class="card" id="card-stego-key">
      <div class="card-title">03 &mdash; Stego Key</div>
      <div class="input-group" style="margin-bottom:6px">
        <label class="input-label">Steganography Key</label>
        <div class="input-wrap">
          <span class="input-icon">&#11041;</span>
          <input type="password" id="extract-stego-key" class="input has-icon" placeholder="Key used during embedding" value="<?=$userSk?>" oninput="persistExtract()" style="padding-right:76px">
          <button type="button" class="input-eye" onclick="togglePass('extract-stego-key',this)" style="right:38px">&#9673;</button>
          <button type="button" class="input-eye" onclick="useMyStegKey('extract')" style="right:8px;font-size:11px;font-family:'Space Mono',monospace;opacity:1;color:#c084fc" title="Use saved steg key">&#11041;</button>
        </div>
      </div>
      <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted)">Must match exactly what was used during embedding.</div>
    </div>
  </div>
  <div class="embed-right" id="extract-right">
    <div class="card" id="card-extract-chain">
      <div class="card-title">04 &mdash; Decryption Chain</div>
      <?php if(!empty($chainsWithSteps)):?>
        <div class="input-group" style="margin-bottom:10px">
          <label class="input-label">Load Saved Chain</label>
          <div class="input-wrap">
            <span class="input-icon" style="font-size:12px">&#9939;</span>
            <select class="input has-icon select-input" id="saved-chain-select" onchange="loadSavedChainExtract(this.value)" style="font-family:'Space Mono',monospace;font-size:11px">
              <option value="">-- Select a saved chain --</option>
              <?php foreach($chainsWithSteps as $c):?>
                <option value="<?=addslashes($chainKeysMap[$c['chain_id']]??'')?>"><?=htmlspecialchars($c['chain_name'])?> (<?=$c['layer_count']?> layers)</option>
              <?php endforeach?>
            </select>
          </div>
        </div>
      <?php endif?>
      <div class="input-group" style="margin-bottom:12px">
        <label class="input-label">Paste Chain Key <span class="optional-tag">(auto-loads chain)</span></label>
        <div class="input-wrap">
          <span class="input-icon" style="font-family:'Space Mono',monospace;font-size:10px">&#11041;</span>
          <input type="text" class="input has-icon" id="chain-key-input" placeholder="Paste the chain key from sender..." style="font-family:'JetBrains Mono',monospace;font-size:11px" oninput="autoDecodeChainKey(this.value)" onpaste="setTimeout(()=>autoDecodeChainKey(this.value),50)">
        </div>
      </div>
      <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-bottom:10px">Or build chain manually &mdash; must match embedding order (reversed for decryption).</div>
      <div class="algo-chips-wrap">
        <?php foreach($algos as $cat=>$list):foreach(array_slice($list,0,2) as $a):?>
          <div class="algo-chip <?=$cat?><?=$a['algo_slug']==='netrunner'?' netrunner-chip':''?>" onclick="addToChain('<?=addslashes($a['algo_name'])?>','<?=$cat?>','<?=$a['algo_slug']?>')">
            <?=htmlspecialchars($a['algo_name'])?> <span class="chip-plus">+</span>
          </div>
        <?php endforeach;endforeach?>
        <a href="<?=SITE_URL?>/chains.php" class="algo-chip-more">All &rarr;</a>
      </div>
      <div class="pipeline-status-bar">
        <span class="pipeline-label">CHAIN &mdash; <span id="chain-count">0</span> LAYERS</span>
        <div class="pipeline-controls">
          <div class="strength-bar"><div class="strength-fill" id="strength-fill" style="width:0%"></div></div>
          <span class="strength-val" id="strength-val">0%</span>
          <button type="button" class="btn btn-ghost" onclick="clearChain()" style="padding:3px 8px;font-size:10px">&#10005;</button>
        </div>
      </div>
      <div id="chain-steps" class="embed-chain-steps"></div>
      <input type="hidden" id="chain-data" value="[]">
    </div>
    <button class="extract-decrypt-btn embed-action-btn" id="extract-btn" onclick="runExtract()">
      <canvas class="btn-hiero-canvas" id="extract-hiero" aria-hidden="true"></canvas>
      <span class="btn-text">&#128274; EXTRACT &amp; DECRYPT</span>
      <span class="btn-loader" style="display:none">&#9676;</span>
    </button>
    <div class="process-terminal extract-terminal" id="extract-terminal" style="display:none">
      <div class="terminal-header">
        <div class="term-ctrl-left">
          <span class="terminal-dot red" onclick="termClose('extract-terminal')" title="Close"></span>
          <span class="terminal-dot yellow" onclick="termMinimize('extract-terminal')" title="Minimize"></span>
        </div>
        <span class="terminal-title">StegoPS Decryption Engine</span>
        <div class="term-ctrl-right">
          <span class="terminal-dot green term-expand-btn" onclick="termMaximize('extract-terminal')" title="Maximize">
            <svg viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg" class="term-max-svg">
              <path d="M1 4V1H4M6 1H9V4M9 6V9H6M4 9H1V6" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
          </span>
        </div>
      </div>
      <div class="terminal-body" id="extract-term-body"></div>
      <div id="payload-reveal" style="display:none">
        <div class="payload-reveal">
          <div class="payload-reveal-label">&#9632; Decrypted Payload</div>
          <div id="payload-type-badge" style="font-family:'Space Mono',monospace;font-size:9px;margin-bottom:8px"></div>
          <div id="payload-content" class="payload-reveal-content"></div>
          <div class="payload-actions">
            <button class="btn btn-primary btn-sm" onclick="copyPayload()" id="copy-payload-btn">&#11041; Copy</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div id="img-viewer-overlay" class="img-viewer-overlay" onclick="closeImgViewer()" style="display:none">
  <div class="img-viewer-inner" onclick="event.stopPropagation()">
    <button class="img-viewer-close" onclick="closeImgViewer()">&#10005;</button>
    <img id="img-viewer-src" src="" alt="Preview" class="img-viewer-img">
  </div>
</div>
<script>
const EXTRACT_URL='<?=SITE_URL?>/php/api/process_ajax.php';
const USER_STEG_KEY='<?=$userSk?>';
let _extractFileType='<?=htmlspecialchars($preFile['file_type']??'')?>';
let _extractSelectedMethod='<?=htmlspecialchars($preOp['stego_technique']??'lsb')?>';
let extractedPayload='',extractedBlob=null;
const EXTRACT_METHODS={
  image:[
    {slug:'lsb',name:'LSB Injection',desc:'Extract from least significant bits of RGB channels'},
    {slug:'xor_pixel',name:'XOR Pixel',desc:'Reverse XOR key-stream to recover payload',special:true,badge:'special'},
    {slug:'alpha',name:'Alpha Channel',desc:'Extract from PNG alpha transparency layer'},
    {slug:'dct',name:'DCT Embed',desc:'Recover from DCT coefficients — for DCT-embedded files',special:true,badge:'dct'},
    {slug:'img_in_img',name:'Image-in-Image',desc:'Recover image payload from 4-bit nibble merge'},
  ],
  audio:[
    {slug:'wav_lsb',name:'WAV LSB',desc:'Recover payload from audio PCM sample LSBs'},
    {slug:'phase',name:'Phase Coding',desc:'Recover payload from DFT phase segments'},
    {slug:'aahe',name:'AAHE',desc:'Adaptive audio hiding engine — keyed XOR + psychoacoustic masking',special:true,badge:'special'},
  ],
  video:[
    {slug:'frame_lsb',name:'Frame LSB',desc:'Recover payload from per-frame LSB data'},
  ]
};
function renderExtractMethods(methods,preMethod){
  const c=document.getElementById('extract-method-cards');if(!c||!methods.length)return;
  c.innerHTML='';
  methods.forEach((m,i)=>{
    const sel=preMethod?m.slug===preMethod:(i===0);
    const badge=m.badge?`<span class="smc-badge ${m.badge}">${m.badge==='dct'?'DCT':'NEW'}</span>`:'';
    const card=document.createElement('label');
    card.className='steg-method-card'+(sel?' selected':'')+(m.special?' special-method':'');
    card.innerHTML=`<input type="radio" name="extract_method_radio" value="${m.slug}" ${sel?'checked':''}><div class="smc-indicator"></div><div class="smc-body"><div class="smc-name">${m.name}${badge}</div><div class="smc-desc">${m.desc||''}</div></div>`;
    card.addEventListener('click',()=>{
      document.querySelectorAll('#extract-method-cards .steg-method-card').forEach(x=>x.classList.remove('selected'));
      card.classList.add('selected');_extractSelectedMethod=m.slug;
      const inp=document.getElementById('extract-steg-method-input');if(inp)inp.value=m.slug;
    });
    if(sel){_extractSelectedMethod=m.slug;const inp=document.getElementById('extract-steg-method-input');if(inp)inp.value=m.slug;}
    c.appendChild(card);
  });
}
function onStegoFileSelected(input){
  const file=input?.files?.[0];if(!file)return;
  _extractFileType=file.type.startsWith('audio/')?'audio':file.type.startsWith('video/')?'video':'image';
  renderExtractMethods(EXTRACT_METHODS[_extractFileType]||EXTRACT_METHODS.image,null);
}
function clearPreFile(){
  document.getElementById('pre-file-banner')?.remove();
  const inp=document.getElementById('pre-stego-file-id');if(inp)inp.value='';
  const row=document.getElementById('stego-upload-row');if(row)row.style.display='flex';
  _extractFileType='';
  const mc=document.getElementById('extract-method-cards');
  if(mc)mc.innerHTML='<div class="steg-method-placeholder">&#11025; Upload a stego file to see methods</div>';
}
function loadSavedChainExtract(key){
  if(!key)return;
  try{const dec=JSON.parse(atob(key));chain=dec.map(s=>({slug:s[0],name:s[1],category:s[2],key:s[3]||'',key2:s[4]||''}));renderChain();showToast('Chain loaded \u2014 '+chain.length+' layers');}
  catch(e){showToast('Failed to load chain');}
}
function persistExtract(){try{localStorage.setItem('extract_stego_key',document.getElementById('extract-stego-key')?.value||'');}catch(e){}}
function restoreExtract(){try{const k=localStorage.getItem('extract_stego_key');if(k&&document.getElementById('extract-stego-key'))document.getElementById('extract-stego-key').value=k;}catch(e){}}
function copyPayload(){if(extractedPayload)navigator.clipboard.writeText(extractedPayload).then(()=>showToast('Payload copied!'));}
function _renderPayloadOutput(d){
  const reveal=document.getElementById('payload-reveal');
  const content=document.getElementById('payload-content');
  const badge=document.getElementById('payload-type-badge');
  const copyBtn=document.getElementById('copy-payload-btn');
  reveal.style.display='block';
  if(d.is_binary){
    copyBtn.style.display='none';
    try{
      let binStr='';
      try{binStr=atob(d.payload);}catch(e1){try{binStr=atob(atob(d.payload));}catch(e2){binStr=d.payload;}}
      if(binStr.startsWith('STEGIMAGE1\n')||binStr.startsWith('STEGFILE1\n')){
        const isImage=binStr.startsWith('STEGIMAGE1\n');
        const prefix=isImage?'STEGIMAGE1\n':'STEGFILE1\n';
        const sepIdx=binStr.indexOf('\n\n',prefix.length-1);
        if(sepIdx>prefix.length){
          const metaRaw=binStr.substring(prefix.length,sepIdx);
          let meta={name:isImage?'extracted_image.png':'extracted_payload.bin',type:isImage?'image/png':'application/octet-stream'};
          try{meta=JSON.parse(metaRaw);}catch(e){}
          const raw=binStr.substring(sepIdx+2);
          const bytes=new Uint8Array(raw.length);
          for(let i=0;i<raw.length;i++)bytes[i]=raw.charCodeAt(i)&0xff;
          extractedBlob=new Blob([bytes],{type:meta.type||'application/octet-stream'});
          const url=URL.createObjectURL(extractedBlob);
          const isImg=(meta.type||'').startsWith('image/');
          badge.innerHTML=`<span style="color:#a78bfa">${isImage?'IMAGE PAYLOAD':'FILE PAYLOAD'}</span> &mdash; ${meta.name} &mdash; ${bytes.length.toLocaleString()} bytes`;
          let html='';
          if(isImg)html+=`<img src="${url}" class="payload-img-result" alt="Extracted image" onclick="openImgViewer('${url}')">`;
          html+=`<a href="${url}" download="${meta.name}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:5px">\u2B07 Download ${meta.name}</a>`;
          content.innerHTML=html;extractedPayload=meta.name+' ('+bytes.length+' bytes)';
          return;
        }
      }
      const bytes=new Uint8Array(binStr.length);
      for(let i=0;i<binStr.length;i++)bytes[i]=binStr.charCodeAt(i)&0xff;
      extractedBlob=new Blob([bytes]);
      const url=URL.createObjectURL(extractedBlob);
      badge.innerHTML=`<span style="color:var(--text-muted)">BINARY PAYLOAD</span> &mdash; ${bytes.length.toLocaleString()} bytes`;
      content.innerHTML=`<a href="${url}" download="extracted_payload.bin" class="btn btn-primary btn-sm">\u2B07 Download binary (${bytes.length.toLocaleString()} B)</a>`;
      extractedPayload='binary:'+bytes.length+'B';
    }catch(e){content.textContent='Failed to decode binary: '+e.message;badge.innerHTML='';}
  } else {
    extractedPayload=d.payload||'';
    badge.innerHTML='<span style="color:#34d399">TEXT PAYLOAD</span> &mdash; '+extractedPayload.length+' characters';
    content.textContent=extractedPayload;
    copyBtn.style.display='';
  }
}
function runExtract(){
  const stegoKey=document.getElementById('extract-stego-key')?.value||'';
  const stegoFile=document.getElementById('stego-input')?.files[0];
  const preFileId=document.getElementById('pre-stego-file-id')?.value||'';
  const stegMethod=document.getElementById('extract-steg-method-input')?.value||_extractSelectedMethod||'lsb';
  const btn=document.getElementById('extract-btn');
  if(!stegoFile&&!preFileId){showStegError('Upload a stego file first');return;}
  if(!stegoKey){showStegError('Enter the stego key');return;}
  setLoading(btn,true);if(btn._setProc)btn._setProc(true);
  showTerminal('extract-terminal');
  const tb=document.getElementById('extract-term-body');
  document.getElementById('payload-reveal').style.display='none';
  const steps=[
    'Initializing decryption session\u2026',
    'Loading stego file: '+(stegoFile?.name||'pre-loaded #'+preFileId),
    'Detecting steganography: '+stegMethod.toUpperCase().replace(/_/g,' '),
    'Locating hidden payload region\u2026',
    chain.length>0?('Reversing chain \u2014 '+chain.length+' layer'+(chain.length>1?'s':'')+'\u2026'):('No chain \u2014 expecting raw payload\u2026'),
    ...chain.slice().reverse().map((s,i)=>'  Layer '+(i+1)+' (rev): '+s.name),
    ...(chain.some(s=>['symmetric','asymmetric','classical'].includes(s.category))?['Decrypting data\u2026']:['Extracting bytes from carrier\u2026']),
  ];
  animateTerminal(tb,steps,()=>{
    const fd=new FormData();
    fd.append('action','extract');fd.append('stego_key',stegoKey);
    fd.append('chain_data',JSON.stringify(chain));fd.append('steg_method',stegMethod);
    if(preFileId)fd.append('stego_file_id',preFileId);else fd.append('stego_file',stegoFile);
    fetch(EXTRACT_URL,{method:'POST',body:fd})
      .then(r=>{if(!r.headers.get('content-type')?.includes('json'))return r.text().then(t=>{throw new Error('Server error ('+r.status+'): '+t.substring(0,120));});return r.json();})
      .then(d=>{
        setLoading(btn,false);
        if(d.success){
          if(btn._flashOk)btn._flashOk();
          addTermLine(tb,'','<span style="color:var(--accent)">\u2713 EXTRACTION SUCCESSFUL</span>');
          _renderPayloadOutput(d);
        } else {
          if(btn._flashErr)btn._flashErr();else setLoading(btn,false);
          addTermLine(tb,'err','\u2717 '+d.message);showStegError(d.message);
        }
      })
      .catch(e=>{setLoading(btn,false);if(btn._flashErr)btn._flashErr();addTermLine(tb,'err','\u2717 Network error: '+e.message);showStegError('Network error: '+e.message);});
  });
}
function initDrop(zone,input,cb){
  if(!zone||!input)return;
  ['dragenter','dragover'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();e.stopPropagation();e.dataTransfer&&(e.dataTransfer.dropEffect='copy');zone.classList.add('drag-over');}));
  ['dragleave','dragend'].forEach(ev=>zone.addEventListener(ev,e=>{if(ev==='dragleave'&&zone.contains(e.relatedTarget))return;zone.classList.remove('drag-over');}));
  zone.addEventListener('drop',e=>{
    e.preventDefault();e.stopPropagation();zone.classList.remove('drag-over');
    const f=e.dataTransfer.files[0];if(!f)return;
    try{const dt=new DataTransfer();dt.items.add(f);input.files=dt.files;}catch(ex){}
    input.dispatchEvent(new Event('change',{bubbles:true}));
    if(cb)cb(f);
  });
}
window.addEventListener('DOMContentLoaded',()=>{
  restoreExtract();renderChain();
  const preType=document.getElementById('pre-stego-type')?.value;
  if(preType&&EXTRACT_METHODS[preType])renderExtractMethods(EXTRACT_METHODS[preType],document.getElementById('extract-steg-method-input')?.value||null);
  initDrop(document.querySelector('#stego-upload-row .upload-zone'),document.getElementById('stego-input'),null);
});
</script>
<?php require_once 'php/includes/footer.php';?>
