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
.sml-dot{width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block;animation:pulse-dot 1.8s ease-in-out infinite}
@keyframes pulse-dot{0%,100%{opacity:.4;transform:scale(1)}50%{opacity:1;transform:scale(1.3)}}
.steg-method-section{margin-top:10px}
.steg-method-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px;display:flex;align-items:center;gap:6px}
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
.upload-zone{transition:border-color .15s,background .15s,transform .15s}
.upload-zone.drag-over{border-color:var(--accent)!important;background:rgba(0,229,200,.13)!important;transform:scale(1.015)}
.steg-method-group{margin-bottom:10px}
.steg-method-group:last-child{margin-bottom:0}
.steg-method-group-label{font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:6px;padding:0 2px;display:flex;align-items:center;gap:6px}
.steg-method-group-label::before{content:'';display:inline-block;width:3px;height:10px;border-radius:2px;background:var(--accent);flex-shrink:0}
.steg-method-group-label.audio-group::before{background:#a78bfa}
.steg-method-group-label.img-in-img-group::before{background:#f472b6}
.steg-method-group-label.image-group::before{background:var(--accent)}
.steg-method-group-label.video-group::before{background:#fbbf24}
.steg-method-group-label.text-group::before{background:#34d399}
.steg-method-group+.steg-method-group{border-top:1px solid rgba(255,255,255,.05);padding-top:10px}
.stego-text-result{background:rgba(0,0,0,.25);border:1px solid rgba(52,211,153,.2);border-radius:8px;padding:12px;margin-top:12px}
.stego-text-result-label{font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px}
.stego-text-result-content{font-family:'JetBrains Mono',monospace;font-size:11px;color:#34d399;max-height:160px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;line-height:1.5;padding:8px;background:rgba(0,0,0,.2);border-radius:6px}
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
    <!-- CARD 01: PAYLOAD -->
    <div class="card" id="card-payload">
      <div class="card-title">01 &mdash; Payload</div>
      <div class="media-tabs payload-tabs" style="margin-bottom:14px">
        <div class="media-tab active" id="pt-text" onclick="switchPayloadType('text')">
          <div class="media-tab-icon">&#9998;</div><div class="media-tab-label">Text</div>
        </div>
        <div class="media-tab" id="pt-file" onclick="switchPayloadType('file')">
          <div class="media-tab-icon">&#128206;</div><div class="media-tab-label">File / Image</div>
        </div>
      </div>
      <!-- Text payload -->
      <div id="payload-text">
        <textarea name="payload_text" id="embed-payload" class="textarea" placeholder="Type your secret message here..." rows="6" oninput="persistEmbed()" style="resize:vertical"></textarea>
      </div>
      <!-- File/Image payload — same design as carrier upload -->
      <div id="payload-file" style="display:none">
        <div class="carrier-upload-row" id="payload-upload-row">
          <div class="upload-zone carrier-drop-zone" id="payload-drop-zone" onclick="document.getElementById('payload-media-input').click()">
            <div class="upload-icon" id="payload-icon">&#128206;</div>
            <div class="upload-text" id="payload-upload-text">Click or drag file / image to hide</div>
            <div class="upload-sub">Any file type &mdash; images unlock Image-in-Image steg</div>
          </div>
          <div id="payload-preview" class="carrier-preview-panel" style="display:none"></div>
        </div>
        <input type="file" id="payload-media-input" accept="*" style="display:none" onchange="handlePayloadSelect(this)">
      </div>
    </div>
    <!-- CARD 02: CARRIER + STEG METHOD -->
    <div class="card" id="card-carrier">
      <div class="card-title">02 &mdash; Carrier Media &amp; Steg Method</div>
      <div class="media-tabs" id="carrier-tabs">
        <div class="media-tab active" id="mt-image" onclick="switchMedia('image')">
          <div class="media-tab-icon">&#128444;</div><div class="media-tab-label">Image</div>
        </div>
        <div class="media-tab" id="mt-audio" onclick="switchMedia('audio')">
          <div class="media-tab-icon">&#127925;</div><div class="media-tab-label">Audio</div>
        </div>
        <div class="media-tab" id="mt-video" onclick="switchMedia('video')">
          <div class="media-tab-icon">&#127916;</div><div class="media-tab-label">Video</div>
        </div>
        <div class="media-tab" id="mt-text" onclick="switchMedia('text')">
          <div class="media-tab-icon">&#128084;</div><div class="media-tab-label">Text</div>
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
      <?php endif?>
          <div class="upload-zone carrier-drop-zone" onclick="document.getElementById('carrier-input').click()">
            <div class="upload-icon" id="carrier-icon">&#128444;</div>
            <div class="upload-text">Click or drag carrier file</div>
            <div class="upload-sub" id="carrier-sub">PNG, JPG, BMP supported</div>
            <div class="carrier-technique-label" id="carrier-technique">LSB Steganography</div>
          </div>
          <div id="carrier-preview" class="carrier-preview-panel" style="display:none"></div>
        </div>
        <!-- Text carrier cover text (hidden unless text tab selected) -->
        <div id="cover-text-section" style="display:none;margin-top:12px">
          <label class="input-label" style="margin-bottom:6px">Cover Text <span class="optional-tag">(innocent-looking text to hide payload inside)</span></label>
          <textarea id="cover-text" class="textarea" placeholder="Paste or type your cover text here&#8230; The longer, the higher capacity." rows="5" style="resize:vertical"></textarea>
          <div id="cover-text-cap" style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-top:4px"></div>
        </div>
        <input type="file" id="carrier-input" style="display:none" onchange="onCarrierChange(this)" accept="image/*,audio/*,video/*">
        <input type="hidden" id="media-type-input" value="<?=$preCarrierFile?htmlspecialchars($preCarrierFile['file_type']):'image'?>">
        <input type="hidden" id="payload-type-input" value="text">
        <input type="hidden" id="steg-method-input" value="lsb">
        <div class="steg-method-section">
          <div class="steg-method-label"><span class="sml-dot"></span>Steganography Method</div>
          <div class="steg-method-cards" id="steg-method-cards">
            <div class="steg-method-loading"><div class="sml-spinner"></div>Loading methods&hellip;</div>
          </div>
        </div>
    </div>
    <!-- CARD 04: STEGO KEY -->
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
        <?php if($user['steg_key']??null):?><a href="<?=SITE_URL?>/profile.php" style="color:var(--accent)">Your profile steg key &rarr;</a><?php endif?>
      </div>
    </div>
  </div>
  <div class="embed-right" id="embed-right">
    <!-- CARD 03: ENCRYPTION CHAIN -->
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
              <?php endforeach?>
            </select>
          </div>
        </div>
      <?php endif?>
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
        <?php endforeach;endforeach?>
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
      <!-- Stego text output (text carrier only) -->
      <div id="stego-text-reveal" style="display:none">
        <div class="stego-text-result">
          <div class="stego-text-result-label">&#9632; Stego Text &mdash; <span id="stego-text-overhead"></span></div>
          <div class="stego-text-result-content" id="stego-text-content"></div>
          <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
            <button class="btn btn-primary btn-sm" onclick="copyStegoText()">&#11041; Copy Text</button>
            <button class="btn btn-ghost btn-sm" onclick="downloadStegoText()">&#8595; Download .txt</button>
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
const EMBED_URL='<?=SITE_URL?>/php/api/process_ajax.php';
const USER_STEG_KEY='<?=$userSk?>';
const IS_GUEST=<?=$isGuest?'true':'false'?>;
let _engineOnline=false,_currentFileType='<?=$preCarrierFile?htmlspecialchars($preCarrierFile['file_type']):'image'?>',_selectedMethod='lsb';
let _stegoTextOutput='';

/* ── Steg method definitions (all algorithms from Python server) ─────── */
const STEG_METHODS={
  image:[
    {slug:'lsb',      name:'LSB Injection',   desc:'1-bit per RGB channel — highest capacity, lossless PNG output'},
    {slug:'xor_pixel',name:'XOR Pixel',        desc:'Key-stream XOR on LSBs — keyed, key-dependent extraction',special:true,badge:'special'},
    {slug:'alpha',    name:'Alpha Channel',    desc:'Hidden in transparency layer — PNG only, zero visual change'},
    {slug:'dct',      name:'DCT JPEG',         desc:'Mid-frequency DCT coefficients — JPEG output, WhatsApp-resistant',special:true,badge:'dct'},
  ],
  img_in_img:[
    {slug:'img_in_img',name:'Image-in-Image',  desc:'4-bit nibble merge — embeds any file (image/doc/zip) inside carrier, PNG output',special:true,badge:'special'},
  ],
  audio:[
    {slug:'wav_lsb',name:'WAV LSB',            desc:'Least-significant bit in PCM audio samples — inaudible, highest capacity'},
    {slug:'phase',  name:'Phase Coding',       desc:'Encode payload in DFT phase segments — spectrally covert'},
    {slug:'aahe',   name:'AAHE Adaptive',      desc:'Psychoacoustic masking + keyed scatter + 2nd-LSB QIM — most imperceptible',special:true,badge:'special'},
  ],
  video:[
    {slug:'frame_lsb',name:'Frame LSB',        desc:'LSB steganography across all video frames — very high capacity'},
  ],
  text:[
    {slug:'zwsp',       name:'Zero-Width Space',   desc:'Invisible U+200B/C chars between words — survives copy-paste'},
    {slug:'homoglyph',  name:'Homoglyph Swap',     desc:'Cyrillic/Greek lookalike substitution — visually identical letters'},
    {slug:'unicode_tag',name:'Unicode Tag Block',  desc:'Invisible tag-block chars (U+E0000) — zero render footprint',special:true,badge:'special'},
    {slug:'whitespace', name:'Whitespace Variant', desc:'SPACE vs NO-BREAK SPACE — pixel-perfect identical in every font'},
    {slug:'combining',  name:'Combining Diacritics',desc:'CGJ + soft-hyphen after letters — invisible in all renderers'},
  ]
};

function _getMethods(fileType,payloadType){
  if(fileType==='audio') return [{label:'Audio Steganography',cls:'audio-group',methods:STEG_METHODS.audio}];
  if(fileType==='video') return [{label:'Video Steganography',cls:'video-group',methods:STEG_METHODS.video}];
  if(fileType==='text')  return [{label:'Text Steganography',cls:'text-group',methods:STEG_METHODS.text}];
  const isFilePay=(payloadType==='file'||payloadType==='media'||payloadType==='image');
  if(isFilePay) return [
    {label:'Image-in-Image (File Carrier)',cls:'img-in-img-group',methods:STEG_METHODS.img_in_img},
    {label:'Standard Image Methods',cls:'image-group',methods:STEG_METHODS.image},
  ];
  return [{label:'Image Steganography',cls:'image-group',methods:STEG_METHODS.image}];
}

function renderStegMethods(groups){
  const c=document.getElementById('steg-method-cards');if(!c)return;
  const allMethods=groups.flatMap(g=>g.methods||[]);
  if(!allMethods.length){c.innerHTML='<div class="steg-method-loading" style="color:var(--warning)">No methods available.</div>';return;}
  const keepSlug=allMethods.some(m=>m.slug===_selectedMethod)?_selectedMethod:allMethods[0].slug;
  c.innerHTML='';
  const multi=groups.filter(g=>(g.methods||[]).length>0).length>1;
  groups.forEach(group=>{
    const methods=group.methods||[];if(!methods.length)return;
    const gDiv=document.createElement('div');gDiv.className='steg-method-group';
    if(multi){const lbl=document.createElement('div');lbl.className='steg-method-group-label '+(group.cls||'');lbl.textContent=group.label||'';gDiv.appendChild(lbl);}
    const wrap=document.createElement('div');wrap.className='steg-method-cards';
    methods.forEach(m=>{
      const sel=m.slug===keepSlug;
      const badge=m.badge==='dct'?'<span class="smc-badge dct">DCT</span>':m.badge==='special'?'<span class="smc-badge special">\u2605</span>':'';
      const card=document.createElement('label');
      card.className='steg-method-card'+(sel?' selected':'')+(m.special?' special-method':'');
      card.dataset.slug=m.slug;
      card.innerHTML=`<input type="radio" name="steg_method_radio" value="${m.slug}" ${sel?'checked':''}><div class="smc-indicator"></div><div class="smc-body"><div class="smc-name">${m.name}${badge}</div><div class="smc-desc">${m.desc||''}</div></div>`;
      card.addEventListener('click',()=>{
        document.querySelectorAll('.steg-method-card').forEach(x=>x.classList.remove('selected'));
        card.classList.add('selected');_selectedMethod=m.slug;
        const inp=document.getElementById('steg-method-input');if(inp)inp.value=m.slug;
      });
      if(sel){_selectedMethod=m.slug;const inp=document.getElementById('steg-method-input');if(inp)inp.value=m.slug;}
      wrap.appendChild(card);
    });
    gDiv.appendChild(wrap);c.appendChild(gDiv);
  });
}

function refreshStegMethods(){
  renderStegMethods(_getMethods(_currentFileType,document.getElementById('payload-type-input')?.value||'text'));
}

async function pingEngine(){
  const dot=document.getElementById('engine-dot'),txt=document.getElementById('engine-status-txt');
  dot.className='engine-dot';txt.textContent='Pinging engine\u2026';
  try{
    const fd=new FormData();fd.append('action','ping');
    const r=await fetch(EMBED_URL,{method:'POST',body:fd});
    if(!r.headers.get('content-type')?.includes('json'))throw new Error('Non-JSON');
    const d=await r.json();
    if(d.status==='ok'||d.version){_engineOnline=true;dot.className='engine-dot online';txt.textContent='Engine online \u2014 v'+(d.version||'?');}
    else throw new Error(d.message||'fail');
  }catch(e){_engineOnline=false;dot.className='engine-dot offline';txt.textContent='Engine offline \u2014 static methods active';}
}

/* ── Payload file upload — copies carrier panel design ─────────────── */
function handlePayloadSelect(input){
  const file=input?.files?.[0];if(!file)return;
  handleCarrierSelect(input,'payload-preview');
  document.getElementById('payload-icon').textContent=file.type.startsWith('image/')?'\uD83D\uDDBC':'\uD83D\uDCC4';
  document.getElementById('payload-upload-text').textContent='File ready \u2014 drag to replace';
  refreshStegMethods();
}

function clearPayloadMedia(){
  document.getElementById('payload-media-input').value='';
  const pp=document.getElementById('payload-preview');if(pp){pp.style.display='none';pp.innerHTML='';}
  document.getElementById('payload-icon').textContent='\uD83D\uDCC4';
  document.getElementById('payload-upload-text').textContent='Click or drag file / image to hide';
  refreshStegMethods();
}

function onCarrierChange(input){
  const f=input?.files?.[0];
  if(f){
    const type=f.type.startsWith('audio/')?'audio':f.type.startsWith('video/')?'video':'image';
    _currentFileType=type;
    document.getElementById('media-type-input').value=type;
    ['image','audio','video','text'].forEach(t=>document.getElementById('mt-'+t)?.classList.toggle('active',t===type));
  }
  handleCarrierSelect(input,'carrier-preview');
  refreshStegMethods();
}

function clearPreCarrier(){
  document.getElementById('pre-carrier-banner')?.remove();
  const inp=document.getElementById('pre-carrier-file-id');if(inp)inp.value='';
  const row=document.getElementById('carrier-upload-row');if(row)row.style.display='flex';
}

/* ── Terminal helpers ────────────────────────────────────────────────── */
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
    const el=document.createElement('div');el.className='t-line scan';
    el.textContent=label+'  '+chars[i%8].repeat(Math.floor(Math.random()*8+4))+'  ['+Math.round((i+1)/count*100)+'%]';
    b.appendChild(el);b.scrollTop=b.scrollHeight;i++;
    if(i>=count){clearInterval(iv);if(cb)setTimeout(cb,delay);}
  },delay);
}

function loadSavedChainEmbed(key){
  if(!key)return;
  try{const dec=JSON.parse(atob(key));chain=dec.map(s=>({slug:s[0],name:s[1],category:s[2],key:s[3]||'',key2:s[4]||''}));renderChain();showToast('Chain loaded \u2014 '+chain.length+' layers');}
  catch(e){showToast('Failed to load chain');}
}

function persistEmbed(){
  try{localStorage.setItem('embed_payload',document.getElementById('embed-payload')?.value||'');localStorage.setItem('embed_stego_key',document.getElementById('embed-stego-key')?.value||'');}catch(e){}
}
function restoreEmbed(){
  try{const p=localStorage.getItem('embed_payload');const k=localStorage.getItem('embed_stego_key');
  if(p)document.getElementById('embed-payload').value=p;if(k)document.getElementById('embed-stego-key').value=k;}catch(e){}
}

function copyStegoText(){if(_stegoTextOutput)navigator.clipboard.writeText(_stegoTextOutput).then(()=>showToast('Stego text copied!'));}
function downloadStegoText(){
  if(!_stegoTextOutput)return;
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([_stegoTextOutput],{type:'text/plain'}));a.download='stego_output.txt';a.click();
}

/* ── Main embed function ─────────────────────────────────────────────── */
function runEmbed(){
  const payloadText=document.getElementById('embed-payload')?.value||'';
  const payloadType=document.getElementById('payload-type-input')?.value||'text';
  const payloadFile=payloadType!=='text'?document.getElementById('payload-media-input')?.files?.[0]||null:null;
  const stegoKey=document.getElementById('embed-stego-key')?.value||'';
  const carrierFile=document.getElementById('carrier-input')?.files[0];
  const preCarrierId=document.getElementById('pre-carrier-file-id')?.value||'';
  const stegMethod=document.getElementById('steg-method-input')?.value||_selectedMethod||'lsb';
  const isTextCarrier=(_currentFileType==='text');
  const coverText=document.getElementById('cover-text')?.value||'';
  const btn=document.getElementById('embed-btn');

  if(!isTextCarrier&&!carrierFile&&!preCarrierId){showStegError('Upload a carrier file first');return;}
  if(isTextCarrier&&!coverText.trim()){showStegError('Enter cover text for text steganography');return;}
  if(payloadType==='text'&&!payloadText){showStegError('Enter a payload message');return;}
  if(payloadType!=='text'&&!payloadFile){showStegError('Select a payload file first');return;}
  if(!stegoKey){showStegError('Enter your stego key');return;}

  setLoading(btn,true);if(btn._setProc)btn._setProc(true);
  showTerminal('embed-terminal');
  const tb=document.getElementById('embed-term-body');
  document.getElementById('stego-text-reveal').style.display='none';
  const hasCrypto=chain.some(s=>['symmetric','asymmetric','classical'].includes(s.category));
  const payloadBytes=payloadType!=='text'?(payloadFile?.size||0):new TextEncoder().encode(payloadText).length;
  const carrierSizeKB=isTextCarrier?Math.round(coverText.length/10):preCarrierId?'?':((carrierFile?.size||0)/1024).toFixed(1);

  animateTerminal(tb,['Initializing StegoPS secure session\u2026','Authenticating credentials\u2026','Connecting to Python engine\u2026'],()=>{
    addTermLine(tb,_engineOnline?'success':'warn',(_engineOnline?'\u2713 Engine online':'\u26a0 Engine unknown')+' \u2014 proceeding\u2026');
    _termDivider(tb);
    addTermLine(tb,'server','> Carrier: '+(isTextCarrier?'Text cover ('+coverText.length+' chars)':preCarrierId?'Pre-loaded #'+preCarrierId:(carrierFile?.name||'?')));
    addTermLine(tb,'','> Payload: '+payloadType.toUpperCase()+' / > Method: '+stegMethod.toUpperCase().replace(/_/g,' '));
    if(!isTextCarrier&&!preCarrierId)_termMetric(tb,'Carrier',carrierSizeKB+' KB',Math.min(100,(carrierFile?.size||0)/1024/10),'#60a5fa');
    setTimeout(()=>{
      _termAnimateScan(tb,'SCAN',4,80,()=>{
        const estCapKB=isTextCarrier?Math.round(coverText.length/80):preCarrierId?'?':Math.floor((carrierFile?.size||0)*0.04/1024);
        if(!isTextCarrier&&!preCarrierId)_termMetric(tb,'Capacity',estCapKB+' KB',60,'#34d399');
        _termMetric(tb,'Payload',payloadBytes+' B',Math.min(100,payloadBytes/((typeof estCapKB==='number'&&estCapKB>0)?estCapKB*1024:1024)*100),'#fbbf24');
        _termDivider(tb);
        if(chain.length>0){
          addTermLine(tb,'server','> Pipeline \u2014 '+chain.length+' layer'+(chain.length>1?'s':''));
          chain.forEach((s,i)=>addTermLine(tb,'','  '+(i+1)+': '+s.name+(s.key?' [keyed]':' [keyless]')));
          if(hasCrypto)_termAnimateScan(tb,'ENCRYPT',3,60,null);
        } else addTermLine(tb,'warn','> No chain \u2014 embedding raw payload');
        setTimeout(()=>{
          _termDivider(tb);
          addTermLine(tb,'server','> Applying '+stegMethod.toUpperCase().replace(/_/g,' ')+'\u2026');
          _termAnimateScan(tb,'EMBED',5,70,()=>{
            const fd=new FormData();
            if(isTextCarrier){
              fd.append('action','embed');
              fd.append('payload_type','text_steg');
              fd.append('cover_text',coverText);
              fd.append('payload_text',payloadType==='text'?payloadText:'');
              if(payloadType!=='text'&&payloadFile)fd.append('payload_media',payloadFile);
            } else {
              fd.append('action','embed');
              if(payloadType!=='text')fd.append('payload_media',payloadFile);
              else fd.append('payload_text',payloadText);
              fd.append('media_type',_currentFileType);
              if(preCarrierId)fd.append('carrier_file_id',preCarrierId);
              else fd.append('carrier',carrierFile);
            }
            fd.append('stego_key',stegoKey);
            fd.append('steg_method',stegMethod);
            fd.append('chain_data',JSON.stringify(chain));
            fd.append('payload_type',isTextCarrier?'text_steg':payloadType!=='text'?'media':payloadType);

            fetch(EMBED_URL,{method:'POST',body:fd})
              .then(r=>{if(!r.headers.get('content-type')?.includes('json'))return r.text().then(t=>{throw new Error('Server error: '+t.substring(0,200));});return r.json();})
              .then(d=>{
                setLoading(btn,false);_termDivider(tb);
                if(d.success){
                  if(btn._flashOk)btn._flashOk();
                  if(d.stego_text){
                    // Text carrier output
                    _stegoTextOutput=d.stego_text;
                    addTermLine(tb,'success','\u2713 TEXT EMBED COMPLETE \u2014 '+(d.overhead_chars||0)+' invisible chars added');
                    document.getElementById('stego-text-reveal').style.display='block';
                    document.getElementById('stego-text-content').textContent=d.stego_text;
                    document.getElementById('stego-text-overhead').textContent=(d.overhead_chars||0)+' invisible chars added';
                  } else {
                    // File carrier output
                    addTermLine(tb,'success','\u2713 COMPLETE \u2014 #'+d.op_id);
                    _termMetric(tb,'In',((d.carrier_size||0)/1024).toFixed(1)+' KB',70,'#60a5fa');
                    _termMetric(tb,'Out',((d.output_size||0)/1024).toFixed(1)+' KB',72,'#00e5c8');
                    if(d.fallback_note)addTermLine(tb,'warn','\u26a0 '+d.fallback_note);
                    if(IS_GUEST){
                      addTermLine(tb,'','<a href="<?=SITE_URL?>/php/api/download.php?id='+d.file_id+'" style="color:var(--accent)">\u2B07 Download</a>');
                    } else {
                      addTermLine(tb,'','<a href="<?=SITE_URL?>/php/api/download.php?id='+d.file_id+'" style="color:var(--accent)">\u2B07 Download</a> &nbsp; <a href="<?=SITE_URL?>/share.php?op='+d.op_id+'" style="color:#a78bfa">&#11041; Share</a>');
                    }
                    localStorage.removeItem('embed_payload');
                  }
                } else {
                  if(btn._flashErr)btn._flashErr();else setLoading(btn,false);
                  addTermLine(tb,'err','\u2717 '+d.message);showStegError(d.message);
                }
              })
              .catch(e=>{setLoading(btn,false);if(btn._flashErr)btn._flashErr();addTermLine(tb,'err','\u2717 '+e.message);showStegError(e.message);});
          });
        },350);
      });
    },200);
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

const _mediaInfo={
  image:{icon:'\uD83D\uDDBC',sub:'PNG, JPG, BMP supported',technique:'LSB / DCT / Alpha / Image-in-Image',accept:'image/*'},
  audio:{icon:'\uD83C\uDFB5',sub:'WAV required for all audio methods',technique:'WAV LSB / Phase Coding / AAHE',accept:'audio/wav,audio/*'},
  video:{icon:'\uD83C\uDFA6',sub:'MP4, AVI supported',technique:'Frame LSB Steganography',accept:'video/*'},
  text: {icon:'\uD83D\uDCC4',sub:'Paste cover text below',technique:'Zero-Width / Homoglyph / Unicode Tag',accept:''}
};

window.addEventListener('DOMContentLoaded',()=>{
  restoreEmbed();renderChain();pingEngine();refreshStegMethods();
  initDrop(document.querySelector('#carrier-upload-row .upload-zone'),document.getElementById('carrier-input'));
  initDrop(document.getElementById('payload-drop-zone'),document.getElementById('payload-media-input'));

  const _origSPT=window.switchPayloadType;
  window.switchPayloadType=function(type){
    _origSPT&&_origSPT(type);
    refreshStegMethods();
  };

  window.switchMedia=function(type){
    ['image','audio','video','text'].forEach(t=>document.getElementById('mt-'+t)?.classList.toggle('active',t===type));
    const m=_mediaInfo[type]||_mediaInfo.image;
    document.getElementById('carrier-icon').textContent=m.icon;
    document.getElementById('carrier-sub').textContent=m.sub;
    document.getElementById('carrier-technique').textContent=m.technique;
    document.getElementById('media-type-input').value=type;
    const ci=document.getElementById('carrier-input');if(ci)ci.accept=m.accept||'image/*';
    const cp=document.getElementById('carrier-preview');if(cp){cp.style.display='none';cp.innerHTML='';}
    // Show/hide file upload vs cover text
    const isText=(type==='text');
    const row=document.getElementById('carrier-upload-row');if(row)row.style.display=isText?'none':'flex';
    document.getElementById('cover-text-section').style.display=isText?'block':'none';
    _currentFileType=type;
    refreshStegMethods();
  };
});
</script>
<?php require_once 'php/includes/footer.php';?>