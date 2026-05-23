<?php
$pageTitle='Embed';$activePage='embed';
require_once 'php/includes/header.php';
require_once 'php/includes/chain_manager.php';
$manager=new ChainManager();$algos=$manager->getAllAlgorithms();
$isGuest=$auth->isGuest();
$chainsWithSteps=$isGuest?[]:$manager->getUserChainsWithSteps($user['user_id']);
$userSk=htmlspecialchars($user['steg_key']??'');
$chainKeysMap=[];
foreach($chainsWithSteps as $c){$stepsArr=array_map(fn($s)=>[$s['algo_slug'],$s['algo_name'],$s['category'],'',''],$c['steps']);$chainKeysMap[$c['chain_id']]=base64_encode(json_encode($stepsArr));}
$preCarrierId=(int)($_GET['carrier']??0);$preCarrierFile=null;
if($preCarrierId>0)$preCarrierFile=$db->fetchOne("SELECT f.* FROM media_files f WHERE f.file_id=? LIMIT 1",[$preCarrierId]);
function fmtSz(int $b):string{if($b<1024)return $b.' B';if($b<1048576)return round($b/1024,1).' KB';return round($b/1048576,2).' MB';}
?>
<style>
.steg-method-section{margin-top:10px}
.steg-method-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.sml-dot{width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block;animation:pulse-dot 1.8s ease-in-out infinite}
@keyframes pulse-dot{0%,100%{opacity:.4;transform:scale(1)}50%{opacity:1;transform:scale(1.3)}}
.steg-method-cards{display:flex;flex-direction:column;gap:6px}
.steg-method-card{position:relative;display:flex;align-items:flex-start;gap:10px;padding:9px 12px;border-radius:8px;border:1px solid var(--border);cursor:pointer;transition:border-color .18s,background .18s;background:var(--surface)}
.steg-method-card:hover{border-color:var(--accent);background:rgba(0,229,200,.05)}
.steg-method-card.selected{border-color:var(--accent);background:rgba(0,229,200,.08)}
.steg-method-card.special-method{border-color:rgba(167,139,250,.3)}
.steg-method-card.special-method:hover,.steg-method-card.special-method.selected{border-color:#a78bfa;background:rgba(167,139,250,.08)}
.steg-method-card.special-method .smc-indicator{border-color:rgba(167,139,250,.5)}
.steg-method-card.special-method.selected .smc-indicator{border-color:#a78bfa}
.steg-method-card.special-method.selected .smc-indicator::after{background:#a78bfa}
.steg-method-card input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.smc-indicator{width:14px;height:14px;border-radius:50%;border:2px solid var(--border);flex-shrink:0;margin-top:2px;transition:border-color .18s;position:relative}
.steg-method-card.selected .smc-indicator{border-color:var(--accent)}
.steg-method-card.selected .smc-indicator::after{content:'';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:6px;height:6px;border-radius:50%;background:var(--accent)}
.smc-body{flex:1;min-width:0}
.smc-name{font-family:'Space Mono',monospace;font-size:11px;color:var(--text);font-weight:600;margin-bottom:2px}
.smc-desc{font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--text-muted);line-height:1.45}
.smc-badge{display:inline-block;font-family:'Space Mono',monospace;font-size:8px;padding:1px 5px;border-radius:3px;margin-left:5px;vertical-align:middle}
.smc-badge.dct{background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.3)}
.smc-badge.special{background:rgba(167,139,250,.15);color:#a78bfa;border:1px solid rgba(167,139,250,.3)}
.steg-method-loading{font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);padding:10px 0;display:flex;align-items:center;gap:8px}
.sml-spinner{width:12px;height:12px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0}
.engine-status-bar{display:flex;align-items:center;gap:8px;padding:7px 12px;background:rgba(0,0,0,.2);border:1px solid var(--border);border-radius:7px;font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);margin-bottom:14px}
.engine-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;background:var(--warning);transition:background .4s}
.engine-dot.online{background:#00e5c8;box-shadow:0 0 6px rgba(0,229,200,.6);animation:pulse-dot 2s infinite}
.engine-dot.offline{background:var(--danger);box-shadow:0 0 6px rgba(255,45,85,.4)}
.engine-status-txt{flex:1}
.engine-ping-btn{font-size:9px;color:var(--accent);cursor:pointer;text-decoration:none;border:none;background:none;font-family:inherit;padding:0}
.engine-ping-btn:hover{text-decoration:underline}
.t-line.server{color:#60a5fa}.t-line.success{color:#00e5c8}.t-line.warn{color:#fbbf24}
.t-line.metric{color:#a78bfa;font-size:10px}.t-line.scan{color:#34d399;font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.04em}
.t-line.divider{color:var(--text-dim);opacity:.4}
.t-analysis-bar{display:flex;align-items:center;gap:8px;margin:3px 0}
.t-abar-label{font-size:9px;color:var(--text-muted);width:90px;flex-shrink:0}
.t-abar-track{flex:1;height:4px;background:rgba(255,255,255,.07);border-radius:2px;overflow:hidden}
.t-abar-fill{height:100%;border-radius:2px;transition:width .6s ease}
.t-abar-val{font-size:9px;color:var(--text-dim);width:40px;text-align:right;flex-shrink:0}
.pre-carrier-banner{display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(0,229,200,.06);border:1px solid rgba(0,229,200,.2);border-radius:8px;margin-bottom:10px;font-family:'Space Mono',monospace;font-size:10px;color:var(--accent)}
.pre-carrier-banner .pcb-icon{font-size:16px;flex-shrink:0}
.pre-carrier-banner .pcb-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text)}
.pre-carrier-banner .pcb-size{color:var(--text-muted);white-space:nowrap}
.pre-carrier-banner .pcb-clear{background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:14px;padding:2px 4px;transition:color .2s}
.pre-carrier-banner .pcb-clear:hover{color:var(--danger)}
.img-payload-preview{max-width:100%;max-height:160px;object-fit:contain;border:1px solid var(--border);border-radius:8px;margin-top:8px;display:block}
.payload-img-info{font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-top:5px}
.payload-tabs .media-tab{cursor:pointer}
.method-note{font-family:'Space Mono',monospace;font-size:9px;color:var(--accent);padding:6px 10px;background:rgba(0,229,200,.06);border:1px solid rgba(0,229,200,.15);border-radius:6px;margin-bottom:8px}
</style>
<div class="page-header" style="margin-bottom:16px">
  <div><div class="page-title">Embed Payload</div><div class="page-sub">// ENCRYPT &rarr; HIDE INSIDE MEDIA</div></div>
</div>
<div class="engine-status-bar" id="engine-status-bar">
  <div class="engine-dot" id="engine-dot"></div>
  <span class="engine-status-txt" id="engine-status-txt">Checking Python engine&hellip;</span>
  <button class="engine-ping-btn" onclick="pingEngine()">&#8635; Ping</button>
</div>
<div class="embed-layout" id="embed-layout">
  <div class="embed-left">
    <div class="card" id="card-payload">
      <div class="card-title">01 &mdash; Payload</div>
      <div class="media-tabs payload-tabs" style="margin-bottom:14px">
        <div class="media-tab active" id="pt-text" onclick="switchPayloadType('text')">
          <div class="media-tab-icon">&#9998;</div><div class="media-tab-label">Text</div>
        </div>
        <div class="media-tab" id="pt-file" onclick="switchPayloadType('file')">
          <div class="media-tab-icon">&#10792;</div><div class="media-tab-label">File</div>
        </div>
        <div class="media-tab" id="pt-image" onclick="switchPayloadType('image')">
          <div class="media-tab-icon">&#128444;</div><div class="media-tab-label">Image</div>
        </div>
      </div>
      <div id="payload-text">
        <textarea name="payload_text" id="embed-payload" class="textarea" placeholder="Type your secret message here..." rows="6" oninput="persistEmbed()" style="resize:vertical"></textarea>
      </div>
      <div id="payload-file" style="display:none">
        <div class="carrier-upload-row">
          <div class="upload-zone carrier-drop-zone" onclick="document.getElementById('payload-file-input').click()">
            <div class="upload-icon">&#128206;</div>
            <div class="upload-text">Click or drag file to hide</div>
            <div class="upload-sub">Any file type</div>
          </div>
          <div id="pf-preview" class="carrier-preview-panel" style="display:none"></div>
        </div>
        <input type="file" id="payload-file-input" style="display:none" onchange="handleCarrierSelect(this,'pf-preview');refreshStegMethods()">
      </div>
      <div id="payload-image" style="display:none">
        <div class="upload-zone carrier-drop-zone" id="pi-drop" onclick="document.getElementById('payload-image-input').click()">
          <div class="upload-icon">&#128444;</div>
          <div class="upload-text">Click or drag image to hide inside carrier</div>
          <div class="upload-sub">PNG, JPG, BMP &mdash; unlocks Image-in-Image steg</div>
        </div>
        <div id="pi-preview-wrap" style="display:none;margin-top:10px">
          <img id="pi-img-preview" class="img-payload-preview" src="" alt="Payload image">
          <div class="payload-img-info" id="pi-img-info"></div>
        </div>
        <input type="file" id="payload-image-input" accept="image/*" style="display:none" onchange="handleImagePayloadSelect(this);refreshStegMethods()">
      </div>
    </div>
    <div class="card" id="card-carrier">
      <div class="card-title">02 &mdash; Carrier Media &amp; Steg Method</div>
      <div class="media-tabs">
        <div class="media-tab active" id="mt-image" onclick="switchMedia('image')">
          <div class="media-tab-icon">&#128444;</div><div class="media-tab-label">Image</div>
        </div>
        <div class="media-tab" id="mt-audio" onclick="switchMedia('audio')">
          <div class="media-tab-icon">&#127925;</div><div class="media-tab-label">Audio</div>
        </div>
        <div class="media-tab" id="mt-video" onclick="switchMedia('video')">
          <div class="media-tab-icon">&#127916;</div><div class="media-tab-label">Video</div>
        </div>
      </div>
      <?php if($preCarrierFile):?>
        <div class="pre-carrier-banner" id="pre-carrier-banner">
          <span class="pcb-icon">&#128444;</span>
          <span class="pcb-name"><?=htmlspecialchars($preCarrierFile['original_filename'])?></span>
          <span class="pcb-size"><?=fmtSz((int)$preCarrierFile['file_size_bytes'])?></span>
          <button class="pcb-clear" onclick="clearPreCarrier()" title="Remove">&#10005;</button>
        </div>
        <input type="hidden" id="pre-carrier-file-id" value="<?=$preCarrierId?>">
        <div style="display:none" id="carrier-upload-row">
      <?php else:?>
        <div class="carrier-upload-row" id="carrier-upload-row">
      <?php endif;?>
          <div class="upload-zone carrier-drop-zone" onclick="document.getElementById('carrier-input').click()">
            <div class="upload-icon" id="carrier-icon">&#128444;</div>
            <div class="upload-text">Click or drag carrier file</div>
            <div class="upload-sub" id="carrier-sub">PNG, JPG, BMP supported</div>
            <div class="carrier-technique-label" id="carrier-technique">LSB Steganography</div>
          </div>
          <div id="carrier-preview" class="carrier-preview-panel" style="display:none"></div>
        </div>
        <input type="file" id="carrier-input" style="display:none" onchange="handleCarrierSelect(this,'carrier-preview');refreshStegMethods()" accept="image/*,audio/*,video/*">
        <input type="hidden" id="media-type-input" value="<?=$preCarrierFile?htmlspecialchars($preCarrierFile['file_type']):'image'?>">
        <input type="hidden" id="payload-type-input" value="text">
        <input type="hidden" id="steg-method-input" value="lsb">
        <div class="steg-method-section">
          <div class="steg-method-label"><span class="sml-dot"></span>Steganography Method</div>
          <div id="steg-method-note" class="method-note" style="display:none"></div>
          <div class="steg-method-cards" id="steg-method-cards">
            <div class="steg-method-loading"><div class="sml-spinner"></div>Loading methods&hellip;</div>
          </div>
        </div>
    </div>
    <div class="card" id="card-stegokey">
      <div class="card-title">04 &mdash; Stego Key</div>
      <div class="input-group" style="margin-bottom:6px">
        <label class="input-label">Your Stego Key</label>
        <div class="input-wrap">
          <span class="input-icon">&#11041;</span>
          <input type="password" id="embed-stego-key" class="input has-icon" placeholder="Enter your stego key" value="<?=$userSk?>" oninput="persistEmbed()" style="padding-right:76px">
          <button type="button" class="input-eye" onclick="togglePass('embed-stego-key',this)" style="right:38px">&#9673;</button>
          <button type="button" class="input-eye" onclick="useMyStegKey('embed')" style="right:8px;font-size:11px;font-family:'Space Mono',monospace;opacity:1;color:var(--accent)" title="Use saved steg key">&#11041;</button>
        </div>
      </div>
      <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);line-height:1.5">
        Required to extract the payload. Share with recipient.<br>
        <?php if($user['steg_key']??null):?><a href="<?=SITE_URL?>/profile.php" style="color:var(--accent)">Your profile steg key &rarr;</a><?php endif;?>
      </div>
    </div>
  </div>
  <div class="embed-right" id="embed-right">
    <div class="card" id="card-chain">
      <div class="card-title">03 &mdash; Encryption Chain</div>
      <?php if(!empty($chainsWithSteps)):?>
        <div class="input-group" style="margin-bottom:10px">
          <label class="input-label">Load Saved Chain</label>
          <div class="input-wrap">
            <span class="input-icon" style="font-size:12px">&#9939;</span>
            <select class="input has-icon select-input" id="saved-chain-select" onchange="loadSavedChainEmbed(this.value)" style="font-family:'Space Mono',monospace;font-size:11px">
              <option value="">-- Select a saved chain --</option>
              <?php foreach($chainsWithSteps as $c):?>
                <option value="<?=addslashes($chainKeysMap[$c['chain_id']]??'')?>"><?=htmlspecialchars($c['chain_name'])?> (<?=$c['layer_count']?> layers)</option>
              <?php endforeach;?>
            </select>
          </div>
        </div>
      <?php endif;?>
      <div class="input-group" style="margin-bottom:10px">
        <label class="input-label">Chain Key <span class="optional-tag">(paste to auto-load)</span></label>
        <div class="input-wrap">
          <span class="input-icon" style="font-family:'Space Mono',monospace;font-size:10px">&#11041;</span>
          <input type="text" class="input has-icon" id="chain-key-input" placeholder="Paste chain key to decode..." style="font-family:'JetBrains Mono',monospace;font-size:11px" oninput="autoDecodeChainKey(this.value)" onpaste="setTimeout(()=>autoDecodeChainKey(this.value),50)">
        </div>
      </div>
      <div class="algo-chips-wrap">
        <?php foreach($algos as $cat=>$list):foreach(array_slice($list,0,2) as $a):?>
          <div class="algo-chip <?=$cat?><?=$a['algo_slug']==='netrunner'?' netrunner-chip':''?>" onclick="addToChain('<?=addslashes($a['algo_name'])?>','<?=$cat?>','<?=$a['algo_slug']?>')">
            <?=htmlspecialchars($a['algo_name'])?> <span class="chip-plus">+</span>
          </div>
        <?php endforeach;endforeach;?>
        <a href="<?=SITE_URL?>/chains.php" class="algo-chip-more">All &rarr;</a>
      </div>
      <div class="pipeline-status-bar">
        <span class="pipeline-label">PIPELINE &mdash; <span id="chain-count">0</span> LAYERS</span>
        <div class="pipeline-controls">
          <div class="strength-bar"><div class="strength-fill" id="strength-fill" style="width:0%"></div></div>
          <span class="strength-val" id="strength-val">0%</span>
          <button type="button" class="btn btn-ghost" onclick="clearChain()" style="padding:3px 8px;font-size:10px">&#10005;</button>
          <button type="button" class="btn btn-ghost" onclick="copyChainKey()" style="padding:3px 8px;font-size:10px" title="Copy chain key">&#11041;</button>
        </div>
      </div>
      <div id="chain-steps" class="embed-chain-steps"></div>
      <input type="hidden" id="chain-data" value="[]">
    </div>
    <button class="hide-secure-btn embed-action-btn" id="embed-btn" onclick="runEmbed()">
      <canvas class="btn-hiero-canvas" id="embed-hiero" aria-hidden="true"></canvas>
      <span class="btn-text">&#9939; HIDE &amp; SECURE</span>
      <span class="btn-loader" style="display:none">&#9676;</span>
    </button>
    <div class="process-terminal" id="embed-terminal" style="display:none">
      <div class="terminal-header">
        <div class="term-ctrl-left">
          <span class="terminal-dot red" onclick="termClose('embed-terminal')" title="Close"></span>
          <span class="terminal-dot yellow" onclick="termMinimize('embed-terminal')" title="Minimize"></span>
        </div>
        <span class="terminal-title">StegoPS Encryption Engine</span>
        <div class="term-ctrl-right">
          <span class="terminal-dot green term-expand-btn" onclick="termMaximize('embed-terminal')" title="Maximize">
            <svg viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg" class="term-max-svg">
              <path d="M1 4V1H4M6 1H9V4M9 6V9H6M4 9H1V6" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
          </span>
        </div>
      </div>
      <div class="terminal-body" id="embed-term-body"></div>
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
const EMBED_URL='<?=SITE_URL?>/php/api/process_ajax.php';
const USER_STEG_KEY='<?=$userSk?>';
const IS_GUEST=<?=$isGuest?'true':'false'?>;
let _engineOnline=false,_currentFileType='<?=$preCarrierFile?htmlspecialchars($preCarrierFile['file_type']):'image'?>',_selectedMethod='lsb';
const STEG_METHODS={
  image:[
    {slug:'lsb',name:'LSB Injection',desc:'1-bit per RGB channel — highest capacity (PNG output)',special:false},
    {slug:'xor_pixel',name:'XOR Pixel',desc:'Key-stream XOR on LSBs — keyed steganography',special:true,badge:'special'},
    {slug:'alpha',name:'Alpha Channel',desc:'Hidden in transparency layer — PNG only, zero visual change',special:false},
    {slug:'dct',name:'DCT Embed',desc:'Mid-frequency DCT coefficients — JPEG output, WhatsApp-resistant',special:true,badge:'dct'},
  ],
  image_in_image:[
    {slug:'img_in_img',name:'Image-in-Image',desc:'4-bit nibble merge — embeds a full image inside the carrier image',special:true,badge:'special'},
  ],
  audio:[
    {slug:'wav_lsb',name:'WAV LSB',desc:'Least-significant bit in audio PCM samples — inaudible'},
    {slug:'phase',name:'Phase Coding',desc:'Encode data in DFT phase segments — spectrally covert'},
  ],
  video:[
    {slug:'frame_lsb',name:'Frame LSB',desc:'LSB steganography across all video frames — high capacity'},
  ]
};
function _getMethods(fileType,payloadType){
  if(fileType==='image'&&payloadType==='image')return STEG_METHODS.image_in_image;
  return STEG_METHODS[fileType]||STEG_METHODS.image;
}
function renderStegMethods(methods){
  const c=document.getElementById('steg-method-cards');
  const note=document.getElementById('steg-method-note');
  const payloadType=document.getElementById('payload-type-input')?.value||'text';
  if(!c)return;
  if(payloadType==='image'&&_currentFileType==='image'){
    note.style.display='block';note.textContent='Image payload detected — only Image-in-Image method available';
  } else {note.style.display='none';}
  if(!methods||!methods.length){c.innerHTML='<div class="steg-method-loading" style="color:var(--warning)">No methods available.</div>';return;}
  c.innerHTML='';
  methods.forEach((m,i)=>{
    const isSpec=m.special||false;
    const badge=m.badge?`<span class="smc-badge ${m.badge}">${m.badge==='dct'?'DCT':'NEW'}</span>`:'';
    const card=document.createElement('label');
    card.className='steg-method-card'+(i===0?' selected':'')+(isSpec?' special-method':'');
    card.innerHTML=`<input type="radio" name="steg_method_radio" value="${m.slug}" ${i===0?'checked':''}><div class="smc-indicator"></div><div class="smc-body"><div class="smc-name">${m.name}${badge}</div><div class="smc-desc">${m.desc||''}</div></div>`;
    card.addEventListener('click',()=>{
      document.querySelectorAll('.steg-method-card').forEach(x=>x.classList.remove('selected'));
      card.classList.add('selected');_selectedMethod=m.slug;
      const inp=document.getElementById('steg-method-input');if(inp)inp.value=m.slug;
    });
    if(i===0){_selectedMethod=m.slug;const inp=document.getElementById('steg-method-input');if(inp)inp.value=m.slug;}
    c.appendChild(card);
  });
}
function refreshStegMethods(){
  const pt=document.getElementById('payload-type-input')?.value||'text';
  renderStegMethods(_getMethods(_currentFileType,pt));
}
async function pingEngine(){
  const dot=document.getElementById('engine-dot'),txt=document.getElementById('engine-status-txt');
  if(dot)dot.className='engine-dot';if(txt)txt.textContent='Pinging engine\u2026';
  try{
    const fd=new FormData();fd.append('action','ping');
    const r=await fetch(EMBED_URL,{method:'POST',body:fd});
    const ct=r.headers.get('content-type')||'';
    if(!ct.includes('json'))throw new Error('Non-JSON');
    const d=await r.json();
    if(d.status==='ok'||d.version){_engineOnline=true;if(dot)dot.className='engine-dot online';if(txt)txt.textContent='Engine online — v'+(d.version||'?');}
    else throw new Error(d.message||'Ping failed');
  }catch(e){_engineOnline=false;if(dot)dot.className='engine-dot offline';if(txt)txt.textContent='Engine offline — static methods active';}
}
function handleImagePayloadSelect(input){
  const file=input?.files?.[0];if(!file)return;
  const wrap=document.getElementById('pi-preview-wrap'),img=document.getElementById('pi-img-preview'),info=document.getElementById('pi-img-info');
  if(wrap&&img&&info){const url=URL.createObjectURL(file);img.src=url;img.onload=()=>{info.textContent=file.name+' \u2014 '+img.naturalWidth+'\xd7'+img.naturalHeight+'px \u2014 '+(file.size>1048576?(file.size/1048576).toFixed(2)+' MB':(file.size/1024).toFixed(1)+' KB');};wrap.style.display='block';}
}
function switchPayloadType(type){
  ['text','file','image'].forEach(t=>{
    document.getElementById('pt-'+t)?.classList.toggle('active',t===type);
    const el=document.getElementById('payload-'+t);if(el)el.style.display=t===type?'block':'none';
  });
  const inp=document.getElementById('payload-type-input');if(inp)inp.value=type;
  refreshStegMethods();
}
function switchMedia(type){
  ['image','audio','video'].forEach(t=>document.getElementById('mt-'+t)?.classList.toggle('active',t===type));
  const info={image:{icon:'\uD83D\uDDBC',sub:'PNG, JPG, BMP supported',tech:'LSB / DCT / Alpha Steganography'},audio:{icon:'\uD83C\uDFB5',sub:'WAV supported',tech:'LSB / Phase Coding'},video:{icon:'\uD83C\uDFA6',sub:'MP4, AVI supported',tech:'Frame LSB Steganography'}};
  const m=info[type]||info.image;
  const ic=document.getElementById('carrier-icon');if(ic)ic.textContent=m.icon;
  const sb=document.getElementById('carrier-sub');if(sb)sb.textContent=m.sub;
  const tc=document.getElementById('carrier-technique');if(tc)tc.textContent=m.tech;
  const mi=document.getElementById('media-type-input');if(mi)mi.value=type;
  _currentFileType=type;refreshStegMethods();
}
function clearPreCarrier(){
  document.getElementById('pre-carrier-banner')?.remove();
  const inp=document.getElementById('pre-carrier-file-id');if(inp)inp.value='';
  const row=document.getElementById('carrier-upload-row');if(row)row.style.display='flex';
}
function _termDivider(b){addTermLine(b,'divider','\u2500'.repeat(41));}
function _termMetric(b,label,value,pct,color){
  const bar=document.createElement('div');bar.className='t-analysis-bar';
  bar.innerHTML=`<span class="t-abar-label">${label}</span><div class="t-abar-track"><div class="t-abar-fill" style="width:0%;background:${color||'var(--accent)'}"></div></div><span class="t-abar-val">${value}</span>`;
  b.appendChild(bar);b.scrollTop=b.scrollHeight;
  setTimeout(()=>{const f=bar.querySelector('.t-abar-fill');if(f)f.style.width=Math.min(100,pct)+'%';},80);
}
function _termAnimateScan(b,label,count,delay,cb){
  let i=0;const chars='\u2593\u2592\u2591\u2588\u2593\u2592\u2591\u2593'.split('');
  const iv=setInterval(()=>{
    const bar=chars[i%chars.length].repeat(Math.floor(Math.random()*8+4));
    const el=document.createElement('div');el.className='t-line scan';
    el.textContent=label+'  '+bar+'  ['+Math.round((i+1)/count*100)+'%]';
    b.appendChild(el);b.scrollTop=b.scrollHeight;i++;
    if(i>=count){clearInterval(iv);if(cb)setTimeout(cb,delay);}
  },delay);
}
function loadSavedChainEmbed(key){
  if(!key)return;
  try{const dec=JSON.parse(atob(key));chain=dec.map(s=>({slug:s[0],name:s[1],category:s[2],key:s[3]||'',key2:s[4]||''}));renderChain();showToast('Chain loaded \u2014 '+chain.length+' layers');}
  catch(e){showToast('Failed to load chain');}
}
function persistEmbed(){try{localStorage.setItem('embed_payload',document.getElementById('embed-payload')?.value||'');localStorage.setItem('embed_stego_key',document.getElementById('embed-stego-key')?.value||'');}catch(e){}}
function restoreEmbed(){try{const p=localStorage.getItem('embed_payload');const k=localStorage.getItem('embed_stego_key');if(p&&document.getElementById('embed-payload'))document.getElementById('embed-payload').value=p;if(k&&document.getElementById('embed-stego-key'))document.getElementById('embed-stego-key').value=k;}catch(e){}}
function _getPayloadFile(){const pt=document.getElementById('payload-type-input')?.value||'text';if(pt==='image')return document.getElementById('payload-image-input')?.files?.[0]||null;if(pt==='file')return document.getElementById('payload-file-input')?.files?.[0]||null;return null;}
function runEmbed(){
  const payloadText=document.getElementById('embed-payload')?.value||'';
  const payloadType=document.getElementById('payload-type-input')?.value||'text';
  const payloadFile=_getPayloadFile();
  const stegoKey=document.getElementById('embed-stego-key')?.value||'';
  const carrierFile=document.getElementById('carrier-input')?.files[0];
  const preCarrierId=document.getElementById('pre-carrier-file-id')?.value||'';
  const stegMethod=document.getElementById('steg-method-input')?.value||_selectedMethod||'lsb';
  const btn=document.getElementById('embed-btn');
  if(!carrierFile&&!preCarrierId){showStegError('Upload a carrier file first');return;}
  if(payloadType==='text'&&!payloadText){showStegError('Enter a payload message');return;}
  if((payloadType==='file'||payloadType==='image')&&!payloadFile){showStegError('Select a payload '+(payloadType==='image'?'image':'file')+' first');return;}
  if(!stegoKey){showStegError('Enter your stego key');return;}
  setLoading(btn,true);if(btn._setProc)btn._setProc(true);
  showTerminal('embed-terminal');
  const tb=document.getElementById('embed-term-body');
  const hasCrypto=chain.some(s=>['symmetric','asymmetric','classical'].includes(s.category));
  const payloadBytes=payloadType!=='text'?(payloadFile?.size||0):new TextEncoder().encode(payloadText).length;
  const carrierSizeKB=preCarrierId?'?':((carrierFile?.size||0)/1024).toFixed(1);
  animateTerminal(tb,['Initializing StegoPS secure session\u2026','Authenticating operator credentials\u2026','Connecting to Python engine\u2026'],()=>{
    addTermLine(tb,_engineOnline?'success':'warn',(_engineOnline?'\u2713 Engine online':'\u26a0 Engine status unknown')+' \u2014 proceeding\u2026');
    _termDivider(tb);
    addTermLine(tb,'server','> Carrier: '+(preCarrierId?'Pre-loaded #'+preCarrierId:(carrierFile?.name||'?')));
    addTermLine(tb,'','> Payload type: '+payloadType.toUpperCase());
    addTermLine(tb,'','> Steg method: '+stegMethod.toUpperCase().replace(/_/g,' '));
    if(!preCarrierId)_termMetric(tb,'Carrier size',carrierSizeKB+' KB',Math.min(100,(carrierFile?.size||0)/1024/10),'#60a5fa');
    setTimeout(()=>{
      addTermLine(tb,'','> Calculating embedding capacity\u2026');
      _termAnimateScan(tb,'PIXEL SCAN',4,80,()=>{
        const estCapKB=preCarrierId?'?':Math.floor((carrierFile?.size||0)*0.04/1024);
        if(!preCarrierId)_termMetric(tb,'Est. capacity',estCapKB+' KB',60,'#34d399');
        _termMetric(tb,'Payload',payloadBytes+' B',Math.min(100,payloadBytes/(estCapKB>0?estCapKB*1024:1024)*100),'#fbbf24');
        _termDivider(tb);
        if(chain.length>0){
          addTermLine(tb,'server','> Building pipeline \u2014 '+chain.length+' layer'+(chain.length>1?'s':''));
          chain.forEach((s,i)=>addTermLine(tb,'','  Layer '+(i+1)+': '+s.name+(s.key?' \u2014 [keyed]':' \u2014 [keyless]')));
          if(hasCrypto)_termAnimateScan(tb,'ENCRYPT',3,60,null);
        } else {addTermLine(tb,'warn','> No encryption chain \u2014 embedding raw payload');}
        setTimeout(()=>{
          _termDivider(tb);
          addTermLine(tb,'server','> Applying '+stegMethod.toUpperCase().replace(/_/g,' ')+' steganography\u2026');
          addTermLine(tb,'','> Embedding '+payloadBytes+' bytes\u2026');
          _termAnimateScan(tb,'EMBED',5,70,()=>{
            addTermLine(tb,'','> POST /api/embed \u2192 engine\u2026');
            const fd=new FormData();
            fd.append('action','embed');
            if(payloadType==='image')fd.append('payload_image',payloadFile);
            else if(payloadType==='file')fd.append('payload_file',payloadFile);
            else fd.append('payload_text',payloadText);
            fd.append('stego_key',stegoKey);
            fd.append('steg_method',stegMethod);
            fd.append('chain_data',JSON.stringify(chain));
            fd.append('media_type',document.getElementById('media-type-input').value);
            fd.append('payload_type',payloadType);
            if(preCarrierId)fd.append('carrier_file_id',preCarrierId);
            else fd.append('carrier',carrierFile);
            fetch(EMBED_URL,{method:'POST',body:fd})
              .then(r=>{const ct=r.headers.get('content-type')||'';if(!ct.includes('json'))return r.text().then(t=>{throw new Error('Server error ('+r.status+'): '+t.substring(0,200));});return r.json();})
              .then(d=>{
                setLoading(btn,false);_termDivider(tb);
                if(d.success){
                  if(btn._flashOk)btn._flashOk();
                  addTermLine(tb,'success','\u2713 OPERATION COMPLETE \u2014 #'+d.op_id);
                  addTermLine(tb,'','');addTermLine(tb,'metric','  ANALYSIS:');
                  _termMetric(tb,'Carrier in',((d.carrier_size||0)/1024).toFixed(1)+' KB',70,'#60a5fa');
                  _termMetric(tb,'Output',((d.output_size||0)/1024).toFixed(1)+' KB',72,'#00e5c8');
                  _termMetric(tb,'Payload',(d.payload_size||payloadBytes)+' B',20,'#fbbf24');
                  addTermLine(tb,'','');
                  addTermLine(tb,'server','  Method: '+(d.steg_method||stegMethod).toUpperCase().replace(/_/g,' '));
                  if(d.fallback_note)addTermLine(tb,'warn','\u26a0 '+d.fallback_note);
                  addTermLine(tb,'server','  Layers: '+(d.layers||chain.length));
                  addTermLine(tb,'','');
                  if(IS_GUEST){
                    addTermLine(tb,'','<a href="<?=SITE_URL?>/php/api/download.php?id='+d.file_id+'" style="color:var(--accent)">\u2B07 Download output</a> &nbsp; <span style="color:var(--text-muted);font-size:10px">&#9888; Register to create share links</span>');
                  } else {
                    addTermLine(tb,'','<a href="<?=SITE_URL?>/php/api/download.php?id='+d.file_id+'" style="color:var(--accent)">\u2B07 Download output</a> &nbsp; <a href="<?=SITE_URL?>/share.php?op='+d.op_id+'" style="color:#a78bfa">&#11041; Create Share Link</a>');
                  }
                  localStorage.removeItem('embed_payload');
                } else {
                  if(btn._flashErr)btn._flashErr();else setLoading(btn,false);
                  addTermLine(tb,'err','\u2717 FAILED: '+d.message);
                  showStegError(d.message);
                }
              })
              .catch(e=>{setLoading(btn,false);if(btn._flashErr)btn._flashErr();_termDivider(tb);addTermLine(tb,'err','\u2717 CONNECTION ERROR');addTermLine(tb,'err','  '+e.message);showStegError('Network error: '+e.message);});
          });
        },350);
      });
    },200);
  });
}
window.addEventListener('DOMContentLoaded',()=>{
  restoreEmbed();renderChain();pingEngine();
  const initType=document.getElementById('media-type-input')?.value||'image';
  refreshStegMethods();
});
</script>
<?php require_once 'php/includes/footer.php';?>