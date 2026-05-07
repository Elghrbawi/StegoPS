<?php
$pageTitle = 'Embed';
$activePage = 'embed';
require_once 'php/includes/header.php';
require_once 'php/includes/chain_manager.php';
$manager = new ChainManager();
$algos = $manager->getAllAlgorithms();
$chainsWithSteps = $isGuest ?? false ? [] : $manager->getUserChainsWithSteps($user['user_id']);
$userSk = htmlspecialchars($user['steg_key'] ?? '');
$chainKeysMap = [];
foreach ($chainsWithSteps as $c) {
    $stepsArr = array_map(fn($s) => [$s['algo_slug'],$s['algo_name'],$s['category'],'',''], $c['steps']);
    $chainKeysMap[$c['chain_id']] = base64_encode(json_encode($stepsArr));
}
$preCarrierId = (int)($_GET['carrier'] ?? 0);
$preCarrierFile = null;
if ($preCarrierId > 0) {
    $preCarrierFile = $db->fetchOne("SELECT f.* FROM media_files f WHERE f.file_id=? LIMIT 1", [$preCarrierId]);
}
function fmtSz(int $b):string{if($b<1024)return $b.' B';if($b<1048576)return round($b/1024,1).' KB';return round($b/1048576,2).' MB';}
$isGuest = $auth->isGuest();
?>
<style>
.steg-method-section{margin-top:10px}
.steg-method-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.steg-method-label .sml-dot{width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block;animation:pulse-dot 1.8s ease-in-out infinite}
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
.t-line.server{color:#60a5fa}.t-line.success{color:#00e5c8}.t-line.warn{color:#fbbf24}.t-line.metric{color:#a78bfa;font-size:10px}.t-line.scan{color:#34d399;font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.04em}.t-line.divider{color:var(--text-dim);opacity:.4}
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
.img-payload-preview{max-width:100%;max-height:180px;object-fit:contain;border:1px solid var(--border);border-radius:8px;margin-top:8px;display:block}
.payload-img-info{font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-top:5px}
</style>
<div class="page-header" style="margin-bottom:16px">
  <div>
    <div class="page-title">Embed Payload</div>
    <div class="page-sub">// ENCRYPT &rarr; HIDE INSIDE MEDIA</div>
  </div>
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
      <div class="media-tab active" id="pt-text" onclick="switchPayloadType('text')"><div class="media-tab-icon">&#9998;</div><div class="media-tab-label">Text</div></div>
      <div class="media-tab" id="pt-file" onclick="switchPayloadType('file')"><div class="media-tab-icon">&#10792;</div><div class="media-tab-label">File</div></div>
      <div class="media-tab" id="pt-image" onclick="switchPayloadType('image')"><div class="media-tab-icon">&#128444;</div><div class="media-tab-label">Image</div></div>
    </div>
    <div id="payload-text">
      <textarea name="payload_text" id="embed-payload" class="textarea" placeholder="Type your secret message here..." rows="6" oninput="persistEmbed()" style="resize:vertical"></textarea>
    </div>
    <div id="payload-file" style="display:none">
      <div class="carrier-upload-row" id="pf-upload-row">
        <div class="upload-zone carrier-drop-zone" id="pf-drop" onclick="document.getElementById('payload-file-input').click()">
          <div class="upload-icon">&#128206;</div>
          <div class="upload-text">Click or drag file to hide</div>
          <div class="upload-sub">Any file type &mdash; images get special steg treatment</div>
        </div>
        <div id="pf-preview" class="carrier-preview-panel" style="display:none"></div>
      </div>
      <input type="file" id="payload-file-input" name="payload_file" style="display:none" onchange="handleCarrierSelect(this,'pf-preview');refreshStegMethodsBySelection()">
    </div>
    <div id="payload-image" style="display:none">
      <div class="carrier-upload-row" id="pi-upload-row">
        <div class="upload-zone carrier-drop-zone" id="pi-drop" onclick="document.getElementById('payload-image-input').click()">
          <div class="upload-icon">&#128444;</div>
          <div class="upload-text">Click or drag image to hide inside carrier</div>
          <div class="upload-sub">PNG, JPG, BMP &mdash; unlocks image-in-image steg methods</div>
        </div>
        <div id="pi-preview-wrap" style="display:none;margin-top:10px">
          <img id="pi-img-preview" class="img-payload-preview" src="" alt="Payload image preview">
          <div class="payload-img-info" id="pi-img-info"></div>
        </div>
      </div>
      <input type="file" id="payload-image-input" name="payload_file" accept="image/*" style="display:none" onchange="handleImagePayloadSelect(this);refreshStegMethodsBySelection()">
    </div>
  </div>
  <div class="card" id="card-carrier">
    <div class="card-title">02 &mdash; Carrier Media &amp; Steg Method</div>
    <div class="media-tabs">
      <div class="media-tab active" id="mt-image" onclick="switchMedia('image')"><div class="media-tab-icon">&#128444;</div><div class="media-tab-label">Image</div></div>
      <div class="media-tab" id="mt-audio" onclick="switchMedia('audio')"><div class="media-tab-icon">&#127925;</div><div class="media-tab-label">Audio</div></div>
      <div class="media-tab" id="mt-video" onclick="switchMedia('video')"><div class="media-tab-icon">&#127916;</div><div class="media-tab-label">Video</div></div>
    </div>
    <?php if ($preCarrierFile): ?>
    <div class="pre-carrier-banner" id="pre-carrier-banner">
      <span class="pcb-icon">&#128444;</span>
      <span class="pcb-name"><?= htmlspecialchars($preCarrierFile['original_filename']) ?></span>
      <span class="pcb-size"><?= fmtSz((int)$preCarrierFile['file_size_bytes']) ?></span>
      <button class="pcb-clear" onclick="clearPreCarrier()" title="Remove pre-selected">&#10005;</button>
    </div>
    <input type="hidden" id="pre-carrier-file-id" value="<?= $preCarrierId ?>">
    <div style="display:none" id="carrier-upload-row">
    <?php else: ?>
    <div class="carrier-upload-row" id="carrier-upload-row">
    <?php endif; ?>
      <div class="upload-zone carrier-drop-zone" id="carrier-drop" onclick="document.getElementById('carrier-input').click()">
        <div class="upload-icon" id="carrier-icon">&#128444;</div>
        <div class="upload-text">Click or drag carrier file</div>
        <div class="upload-sub" id="carrier-sub">PNG, JPG, BMP supported</div>
        <div class="carrier-technique-label" id="carrier-technique">LSB Steganography</div>
      </div>
      <div id="carrier-preview" class="carrier-preview-panel" style="display:none"></div>
    </div>
    <input type="file" id="carrier-input" name="carrier" style="display:none" onchange="handleCarrierSelect(this,'carrier-preview');refreshStegMethodsBySelection()" accept="image/*,audio/*,video/*">
    <input type="hidden" id="media-type-input" value="<?= $preCarrierFile ? htmlspecialchars($preCarrierFile['file_type']) : 'image' ?>">
    <input type="hidden" id="payload-type-input" value="text">
    <input type="hidden" id="steg-method-input" value="lsb">
    <div class="steg-method-section">
      <div class="steg-method-label"><span class="sml-dot"></span>Steganography Method</div>
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
        <input type="password" id="embed-stego-key" class="input has-icon" placeholder="Enter your stego key" value="<?= $userSk ?>" oninput="persistEmbed()" style="padding-right:76px">
        <button type="button" class="input-eye" onclick="togglePass('embed-stego-key',this)" style="right:38px">&#9673;</button>
        <button type="button" class="input-eye" onclick="useMyStegKey('embed')" style="right:8px;font-size:11px;font-family:'Space Mono',monospace;opacity:1;color:var(--accent)" title="Use your saved steg key">&#11041;</button>
      </div>
    </div>
    <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);line-height:1.5">
      Required to extract the payload. Share with the recipient.<br>
      <?php if ($user['steg_key'] ?? null): ?><a href="<?= SITE_URL ?>/profile.php" style="color:var(--accent)">Your profile steg key &rarr;</a><?php endif; ?>
    </div>
  </div>
</div>
<div class="embed-right" id="embed-right">
  <div class="card" id="card-chain">
    <div class="card-title">03 &mdash; Encryption Chain</div>
    <?php if (!empty($chainsWithSteps)): ?>
    <div class="input-group" style="margin-bottom:10px">
      <label class="input-label">Load Saved Chain</label>
      <div class="input-wrap">
        <span class="input-icon" style="font-size:12px">&#9939;</span>
        <select class="input has-icon select-input" id="saved-chain-select" onchange="loadSavedChainEmbed(this.value)" style="font-family:'Space Mono',monospace;font-size:11px">
          <option value="">-- Select a saved chain --</option>
          <?php foreach ($chainsWithSteps as $c): ?>
          <option value="<?= addslashes($chainKeysMap[$c['chain_id']] ?? '') ?>"><?= htmlspecialchars($c['chain_name']) ?> (<?= $c['layer_count'] ?> layers)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php endif; ?>
    <div class="input-group" style="margin-bottom:10px">
      <label class="input-label">Chain Key <span class="optional-tag">(paste to auto-load)</span></label>
      <div class="input-wrap">
        <span class="input-icon" style="font-family:'Space Mono',monospace;font-size:10px">&#11041;</span>
        <input type="text" class="input has-icon" id="chain-key-input" placeholder="Paste chain key to decode..." style="font-family:'JetBrains Mono',monospace;font-size:11px" oninput="autoDecodeChainKey(this.value)" onpaste="setTimeout(()=>autoDecodeChainKey(this.value),50)">
      </div>
    </div>
    <div class="algo-chips-wrap">
      <?php foreach ($algos as $cat => $list): foreach (array_slice($list, 0, 2) as $a): ?>
      <div class="algo-chip <?= $cat ?><?= $a['algo_slug']==='netrunner' ? ' netrunner-chip' : '' ?>"
           onclick="addToChain('<?= addslashes($a['algo_name']) ?>','<?= $cat ?>','<?= $a['algo_slug'] ?>')">
        <?= htmlspecialchars($a['algo_name']) ?> <span class="chip-plus">+</span>
      </div>
      <?php endforeach; endforeach; ?>
      <a href="<?= SITE_URL ?>/chains.php" class="algo-chip-more">All &rarr;</a>
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
          <svg viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg" class="term-max-svg"><path d="M1 4V1H4M6 1H9V4M9 6V9H6M4 9H1V6" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/></svg>
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
const EMBED_URL = '<?= SITE_URL ?>/php/api/process_ajax.php';
const USER_STEG_KEY = '<?= $userSk ?>';
const IS_GUEST = <?= $isGuest ? 'true' : 'false' ?>;
let _engineOnline = false;
let _currentFileType = '<?= $preCarrierFile ? htmlspecialchars($preCarrierFile['file_type']) : 'image' ?>';
let _selectedMethod = 'lsb';
let _allMethods = [];
const STATIC_STEG_METHODS = {
  image: [
    {slug:'lsb',name:'LSB — Bit Plane Injection',desc:'Least significant bit injection into RGB channels. High capacity, imperceptible distortion.',special:false},
    {slug:'dct',name:'Compression Bypass Steg — DCT Domain',desc:'Embeds payload in discrete cosine transform coefficients. Survives JPEG re-compression cycles.',special:true,badge:'dct'},
    {slug:'alpha_channel',name:'Alpha Channel Injection',desc:'Hides payload data exclusively in the PNG alpha channel. Zero visual distortion on opaque images.',special:false},
    {slug:'palette',name:'Palette Index Steg',desc:'Reorders PNG/GIF palette entries to encode bit sequences. Output visually identical to source.',special:false},
  ],
  image_in_image: [
    {slug:'lsb',name:'LSB — Carrier Bit Injection',desc:'Embeds payload image bits into host image LSBs. Highest capacity, undetectable to naked eye.',special:false},
    {slug:'dct',name:'Compression Bypass Steg — DCT Fusion',desc:'Merges payload image into DCT domain of carrier. Survives JPEG compression with minimal loss.',special:true,badge:'dct'},
    {slug:'xor_pixel',name:'XOR Pixel Masking',desc:'XORs payload image pixel values with carrier. Produces statistically random noise pattern, reversible.',special:true,badge:'special'},
    {slug:'bit_plane_mix',name:'Bit Plane Decomposition',desc:'Decomposes both images into bit planes. Splices payload MSBs into carrier LSBs. Imperceptible merge.',special:true,badge:'special'},
    {slug:'alpha_blend',name:'Alpha Transparency Blend',desc:'Fuses payload at near-zero opacity into alpha layer. No structural change to carrier pixels.',special:false},
    {slug:'img_in_img',name:'Spatial Domain Overlay',desc:'Direct spatial domain embedding with perceptual masking. Exploits HVS sensitivity thresholds.',special:false},
  ],
  audio: [
    {slug:'lsb_audio',name:'LSB Audio Injection',desc:'Modifies least significant bits of WAV PCM samples. Inaudible at standard listening levels.',special:false},
    {slug:'echo_hiding',name:'Echo Hiding',desc:'Encodes data via deliberate echo with controlled delay and decay rate. Acoustically indistinguishable.',special:true,badge:'special'},
    {slug:'phase_coding',name:'Phase Coding',desc:'Shifts signal phase components to encode bits. Spectrally covert — survives audio analysis.',special:false},
    {slug:'dct',name:'Compression Bypass Steg — DCT Audio',desc:'Embeds in DCT frequency bins of audio frames. Robust against MP3/AAC transcoding.',special:true,badge:'dct'},
  ],
  video: [
    {slug:'lsb_video',name:'LSB Frame Injection',desc:'Per-frame LSB embedding into video. High capacity across all frames, imperceptible at playback speed.',special:false},
    {slug:'frame_delta',name:'FrameDelta Steg',desc:'Hides payload in inter-frame difference values. Invisible to single-frame or still analysis.',special:true,badge:'special'},
    {slug:'motion_vector',name:'Motion Vector Encoding',desc:'Encodes payload bits in MPEG motion vector magnitudes. Bypasses frame-level steganalysis tools.',special:true,badge:'special'},
    {slug:'dct',name:'Compression Bypass Steg — DCT Video',desc:'Embeds payload across DCT blocks in video frames. Robust against H.264/H.265 re-encoding.',special:true,badge:'dct'},
  ]
};
function _getStaticMethods(fileType, payloadType) {
  if (fileType === 'image' && (payloadType === 'image' || payloadType === 'file')) {
    const f = document.getElementById('payload-image-input')?.files?.[0] || document.getElementById('payload-file-input')?.files?.[0];
    if (f && f.type.startsWith('image/')) return STATIC_STEG_METHODS.image_in_image;
  }
  return STATIC_STEG_METHODS[fileType] || STATIC_STEG_METHODS.image;
}
function _mergeWithStatic(engineMethods, fileType, payloadType) {
  const staticList = _getStaticMethods(fileType, payloadType);
  if (!engineMethods || !engineMethods.length) return staticList;
  const engineSlugs = new Set(engineMethods.map(m => m.slug));
  const extras = staticList.filter(m => !engineSlugs.has(m.slug));
  return [...engineMethods, ...extras];
}
async function pingEngine() {
  const dot = document.getElementById('engine-dot'), txt = document.getElementById('engine-status-txt');
  if (dot) dot.className = 'engine-dot';
  if (txt) txt.textContent = 'Pinging engine\u2026';
  try {
    const fd = new FormData(); fd.append('action','ping');
    const r = await fetch(EMBED_URL, {method:'POST',body:fd});
    const ct = r.headers.get('content-type') || '';
    if (!ct.includes('json')) throw new Error('Non-JSON (HTTP '+r.status+')');
    const d = await r.json();
    if (d.status === 'ok' || d.version) { _engineOnline = true; if (dot) dot.className = 'engine-dot online'; if (txt) txt.textContent = 'Engine online'; }
    else throw new Error(d.message || 'Ping failed');
  } catch(e) { _engineOnline = false; if (dot) dot.className = 'engine-dot offline'; if (txt) txt.textContent = 'Engine offline — using static methods'; }
}
async function loadStegMethods(fileType) {
  const container = document.getElementById('steg-method-cards');
  if (!container) return;
  const payloadType = document.getElementById('payload-type-input')?.value || 'text';
  container.innerHTML = '<div class="steg-method-loading"><div class="sml-spinner"></div>Loading methods\u2026</div>';
  let engineMethods = [];
  try {
    const fd = new FormData(); fd.append('action','get_methods'); fd.append('file_type',fileType);
    const r = await fetch(EMBED_URL, {method:'POST',body:fd, signal: AbortSignal.timeout(6000)});
    const ct = r.headers.get('content-type') || '';
    if (ct.includes('json')) {
      const d = await r.json();
      if (d.success && Array.isArray(d.methods) && d.methods.length) engineMethods = d.methods;
    }
  } catch(e) {}
  _allMethods = _mergeWithStatic(engineMethods, fileType, payloadType);
  renderStegMethods(_allMethods);
}
function renderStegMethods(allMethods) {
  const container = document.getElementById('steg-method-cards');
  if (!container) return;
  const payloadType = document.getElementById('payload-type-input')?.value || 'text';
  const methods = allMethods.filter(m => m && m.slug);
  if (!methods.length) { container.innerHTML = '<div class="steg-method-loading" style="color:var(--warning)">No methods available.</div>'; return; }
  container.innerHTML = '';
  methods.forEach((m, i) => {
    const isSpecial = m.special || false;
    const badge = m.badge ? `<span class="smc-badge ${m.badge}">${m.badge === 'dct' ? 'DCT' : 'NEW'}</span>` : '';
    const card = document.createElement('label');
    card.className = 'steg-method-card' + (i===0?' selected':'') + (isSpecial?' special-method':'');
    card.innerHTML = `<input type="radio" name="steg_method_radio" value="${m.slug}" ${i===0?'checked':''}><div class="smc-indicator"></div><div class="smc-body"><div class="smc-name">${m.name}${badge}</div><div class="smc-desc">${m.desc||''}</div></div>`;
    card.addEventListener('click', () => {
      document.querySelectorAll('.steg-method-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      _selectedMethod = m.slug;
      const inp = document.getElementById('steg-method-input'); if (inp) inp.value = m.slug;
    });
    if (i===0) { _selectedMethod = m.slug; const inp = document.getElementById('steg-method-input'); if (inp) inp.value = m.slug; }
    container.appendChild(card);
  });
}
function refreshStegMethodsBySelection() {
  const payloadType = document.getElementById('payload-type-input')?.value || 'text';
  _allMethods = _mergeWithStatic(_allMethods.filter(m => m && m.slug && !STATIC_STEG_METHODS.image_in_image.find(s => s.slug === m.slug) && !STATIC_STEG_METHODS[_currentFileType]?.find(s => s.slug === m.slug)), _currentFileType, payloadType);
  _allMethods = _getStaticMethods(_currentFileType, payloadType);
  renderStegMethods(_allMethods);
}
function handleImagePayloadSelect(input) {
  const file = input?.files?.[0];
  if (!file) return;
  const wrap = document.getElementById('pi-preview-wrap');
  const img = document.getElementById('pi-img-preview');
  const info = document.getElementById('pi-img-info');
  if (wrap && img && info) {
    const url = URL.createObjectURL(file);
    img.src = url;
    img.onload = () => { info.textContent = file.name + ' \u2014 ' + img.naturalWidth + '\xd7' + img.naturalHeight + 'px \u2014 ' + (file.size > 1048576 ? (file.size/1048576).toFixed(2)+' MB' : (file.size/1024).toFixed(1)+' KB'); };
    wrap.style.display = 'block';
  }
}
function switchPayloadType(type) {
  ['text','file','image'].forEach(t => {
    document.getElementById('pt-'+t)?.classList.toggle('active', t===type);
    const el = document.getElementById('payload-'+t); if (el) el.style.display = t===type ? 'block' : 'none';
  });
  const inp = document.getElementById('payload-type-input'); if (inp) inp.value = type;
  refreshStegMethodsBySelection();
}
function switchMedia(type) {
  ['image','audio','video'].forEach(t => document.getElementById('mt-'+t)?.classList.toggle('active',t===type));
  const info = {image:{icon:'🖼',sub:'PNG, JPG, BMP supported',tech:'LSB / DCT Steganography'},audio:{icon:'🎵',sub:'WAV supported',tech:'Echo Hiding / Phase Coding'},video:{icon:'🎬',sub:'MP4, AVI supported',tech:'FrameDelta / Motion Vector Steg'}};
  const m = info[type]||info.image;
  const icon = document.getElementById('carrier-icon'); if (icon) icon.textContent = m.icon;
  const sub = document.getElementById('carrier-sub'); if (sub) sub.textContent = m.sub;
  const tech = document.getElementById('carrier-technique'); if (tech) tech.textContent = m.tech;
  const inp = document.getElementById('media-type-input'); if (inp) inp.value = type;
  _currentFileType = type;
  _allMethods = _getStaticMethods(type, document.getElementById('payload-type-input')?.value || 'text');
  renderStegMethods(_allMethods);
  loadStegMethods(type);
}
function clearPreCarrier() {
  document.getElementById('pre-carrier-banner')?.remove();
  const inp = document.getElementById('pre-carrier-file-id'); if (inp) inp.value = '';
  const row = document.getElementById('carrier-upload-row'); if (row) row.style.display = 'flex';
}
function _termDivider(body) { addTermLine(body,'divider','─────────────────────────────────────────'); }
function _termMetric(body,label,value,pct,color) {
  const bar = document.createElement('div'); bar.className = 't-analysis-bar';
  bar.innerHTML = `<span class="t-abar-label">${label}</span><div class="t-abar-track"><div class="t-abar-fill" style="width:0%;background:${color||'var(--accent)'}"></div></div><span class="t-abar-val">${value}</span>`;
  body.appendChild(bar); body.scrollTop = body.scrollHeight;
  setTimeout(() => { const f = bar.querySelector('.t-abar-fill'); if (f) f.style.width = Math.min(100,pct)+'%'; }, 80);
}
function _termAnimateScan(body,label,count,delay,cb) {
  let i=0; const chars='▓▒░█▓▒░▓'.split('');
  const interval = setInterval(() => {
    const bar = chars[i%chars.length].repeat(Math.floor(Math.random()*8+4));
    const el = document.createElement('div'); el.className='t-line scan'; el.textContent=label+'  '+bar+'  ['+Math.round((i+1)/count*100)+'%]';
    body.appendChild(el); body.scrollTop=body.scrollHeight;
    i++; if (i>=count) { clearInterval(interval); if (cb) setTimeout(cb,delay); }
  }, delay);
}
function loadSavedChainEmbed(key) {
  if (!key) return;
  try { const dec = JSON.parse(atob(key)); chain=dec.map(s=>({slug:s[0],name:s[1],category:s[2],key:s[3]||'',key2:s[4]||''})); renderChain(); showToast('Chain loaded \u2014 '+chain.length+' layers'); }
  catch(e) { showToast('Failed to load chain'); }
}
function persistEmbed() {
  try { localStorage.setItem('embed_payload',document.getElementById('embed-payload')?.value||''); localStorage.setItem('embed_stego_key',document.getElementById('embed-stego-key')?.value||''); } catch(e) {}
}
function restoreEmbed() {
  try { const p=localStorage.getItem('embed_payload'); const k=localStorage.getItem('embed_stego_key'); if(p&&document.getElementById('embed-payload'))document.getElementById('embed-payload').value=p; if(k&&document.getElementById('embed-stego-key'))document.getElementById('embed-stego-key').value=k; } catch(e) {}
}
function _getPayloadFile() {
  const ptype = document.getElementById('payload-type-input')?.value || 'text';
  if (ptype === 'image') return document.getElementById('payload-image-input')?.files?.[0] || null;
  if (ptype === 'file') return document.getElementById('payload-file-input')?.files?.[0] || null;
  return null;
}
function runEmbed() {
  const payloadText = document.getElementById('embed-payload')?.value || '';
  const payloadType = document.getElementById('payload-type-input')?.value || 'text';
  const payloadFile = _getPayloadFile();
  const stegoKey = document.getElementById('embed-stego-key')?.value || '';
  const carrierFile = document.getElementById('carrier-input')?.files[0];
  const preCarrierId = document.getElementById('pre-carrier-file-id')?.value || '';
  const stegMethod = document.getElementById('steg-method-input')?.value || _selectedMethod || 'lsb';
  const btn = document.getElementById('embed-btn');
  if (!carrierFile && !preCarrierId) { showStegError('Upload a carrier file first'); return; }
  if (payloadType === 'text' && !payloadText) { showStegError('Enter a payload message'); return; }
  if ((payloadType === 'file' || payloadType === 'image') && !payloadFile) { showStegError('Select a payload '+(payloadType === 'image' ? 'image' : 'file')+' first'); return; }
  if (!stegoKey) { showStegError('Enter your stego key'); return; }
  if (IS_GUEST && payloadType === 'image' && !payloadFile) { showStegError('Select payload image'); return; }
  setLoading(btn, true);
  if (btn._setProc) btn._setProc(true);
  showTerminal('embed-terminal');
  const termBody = document.getElementById('embed-term-body');
  const hasCrypto = chain.some(s=>['symmetric','asymmetric','classical'].includes(s.category));
  const cryptoLayers = chain.filter(s=>['symmetric','asymmetric','classical'].includes(s.category)).length;
  const payloadBytes = payloadType !== 'text' ? (payloadFile?.size||0) : new TextEncoder().encode(payloadText).length;
  const carrierSizeKB = preCarrierId ? '?' : ((carrierFile?.size||0)/1024).toFixed(1);
  animateTerminal(termBody, ['Initializing StegoPS secure session\u2026','Authenticating operator credentials\u2026','Connecting to Python engine\u2026'], () => {
    addTermLine(termBody, _engineOnline?'success':'warn', (_engineOnline?'\u2713 Engine online':'\u26a0 Engine status unknown')+' \u2014 proceeding\u2026');
    _termDivider(termBody);
    addTermLine(termBody,'server','> Carrier: '+(preCarrierId?'Pre-loaded #'+preCarrierId:(carrierFile?.name||'?')));
    addTermLine(termBody,'','> Payload type: '+payloadType.toUpperCase());
    addTermLine(termBody,'','> Steg method: '+stegMethod.toUpperCase().replace(/_/g,' '));
    if (!preCarrierId) _termMetric(termBody,'Carrier size',carrierSizeKB+' KB',Math.min(100,(carrierFile?.size||0)/1024/10),'#60a5fa');
    setTimeout(() => {
      addTermLine(termBody,'','> Calculating embedding capacity\u2026');
      _termAnimateScan(termBody,'PIXEL SCAN',4,80,() => {
        const estCapKB = preCarrierId ? '?' : Math.floor((carrierFile?.size||0)*0.04/1024);
        if (!preCarrierId) { _termMetric(termBody,'Est. capacity',estCapKB+' KB',60,'#34d399'); }
        _termMetric(termBody,'Payload',payloadBytes+' B',Math.min(100,payloadBytes/(estCapKB>0?estCapKB*1024:1024)*100),'#fbbf24');
        _termDivider(termBody);
        if (chain.length > 0) {
          addTermLine(termBody,'server','> Building pipeline \u2014 '+chain.length+' layer'+(chain.length>1?'s':''));
          chain.forEach((s,i) => addTermLine(termBody,'','  Layer '+(i+1)+': '+s.name+(s.key?' \u2014 [keyed]':' \u2014 [keyless]')));
          if (hasCrypto) { addTermLine(termBody,'','> Deriving '+cryptoLayers+' crypto key'+(cryptoLayers>1?'s':'')+'\u2026'); _termAnimateScan(termBody,'ENCRYPT',3,60,null); }
        } else { addTermLine(termBody,'warn','> No encryption chain \u2014 embedding raw payload'); }
        setTimeout(() => {
          _termDivider(termBody);
          addTermLine(termBody,'server','> Applying '+stegMethod.toUpperCase().replace(/_/g,' ')+' steganography\u2026');
          addTermLine(termBody,'','> Embedding '+payloadBytes+' bytes\u2026');
          _termAnimateScan(termBody,'EMBED',5,70,() => {
            addTermLine(termBody,'','> POST /api/embed \u2192 engine\u2026');
            const fd = new FormData();
            fd.append('action','embed');
            if (payloadType === 'file' || payloadType === 'image') fd.append('payload_file', payloadFile);
            else fd.append('payload_text', payloadText);
            fd.append('stego_key',stegoKey);
            fd.append('steg_method',stegMethod);
            fd.append('chain_data',JSON.stringify(chain));
            fd.append('media_type',document.getElementById('media-type-input').value);
            fd.append('payload_type',payloadType === 'image' ? 'file' : payloadType);
            if (preCarrierId) fd.append('carrier_file_id',preCarrierId);
            else fd.append('carrier',carrierFile);
            fetch(EMBED_URL,{method:'POST',body:fd})
              .then(r => { const ct=r.headers.get('content-type')||''; if(!ct.includes('json'))return r.text().then(t=>{throw new Error('Server non-JSON ('+r.status+'): '+t.substring(0,200));}); return r.json(); })
              .then(d => {
                setLoading(btn,false); _termDivider(termBody);
                if (d.success) {
                  if (btn._flashOk) btn._flashOk();
                  addTermLine(termBody,'success','\u2713 OPERATION COMPLETE \u2014 #'+d.op_id);
                  addTermLine(termBody,'','');
                  addTermLine(termBody,'metric','  ANALYSIS:');
                  _termMetric(termBody,'Carrier in',((d.carrier_size||0)/1024).toFixed(1)+' KB',70,'#60a5fa');
                  _termMetric(termBody,'Output',((d.output_size||0)/1024).toFixed(1)+' KB',72,'#00e5c8');
                  _termMetric(termBody,'Payload',(d.payload_size||payloadBytes)+' B',20,'#fbbf24');
                  addTermLine(termBody,'','');
                  addTermLine(termBody,'server','  Method: '+(d.steg_method||stegMethod).toUpperCase().replace(/_/g,' '));
                  addTermLine(termBody,'server','  Layers: '+(d.layers||chain.length));
                  addTermLine(termBody,'','');
                  if (IS_GUEST) {
                    addTermLine(termBody,'','<a href="<?= SITE_URL ?>/php/api/download.php?id='+d.file_id+'" style="color:var(--accent)">\u2B07 Download output</a> &nbsp; <span style="color:var(--text-muted);font-size:10px">&#9888; Register to share links</span>');
                  } else {
                    addTermLine(termBody,'','<a href="<?= SITE_URL ?>/php/api/download.php?id='+d.file_id+'" style="color:var(--accent)">\u2B07 Download output</a> &nbsp; <a href="<?= SITE_URL ?>/share.php?op='+d.op_id+'" style="color:#a78bfa">&#11041; Create Share Link</a>');
                  }
                  localStorage.removeItem('embed_payload');
                } else {
                  if (btn._flashErr) btn._flashErr(); else setLoading(btn,false);
                  addTermLine(termBody,'err','\u2717 FAILED: '+d.message);
                  showStegError(d.message);
                }
              })
              .catch(e => {
                setLoading(btn,false); if (btn._flashErr) btn._flashErr();
                _termDivider(termBody);
                addTermLine(termBody,'err','\u2717 CONNECTION ERROR');
                addTermLine(termBody,'err','  '+e.message);
                showStegError('Network error: '+e.message);
              });
          });
        }, 350);
      });
    }, 200);
  });
}
window.addEventListener('DOMContentLoaded', () => {
  restoreEmbed(); renderChain(); pingEngine();
  const initType = document.getElementById('media-type-input')?.value || 'image';
  _allMethods = _getStaticMethods(initType, 'text');
  renderStegMethods(_allMethods);
  loadStegMethods(initType);
  document.getElementById('payload-file-input')?.addEventListener('change', refreshStegMethodsBySelection);
  document.getElementById('payload-image-input')?.addEventListener('change', refreshStegMethodsBySelection);
});
</script>
<?php require_once 'php/includes/footer.php'; ?>