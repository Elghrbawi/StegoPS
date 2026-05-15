<?php
$pageTitle = 'Extract';
$activePage = 'extract';
require_once 'php/includes/header.php';
require_once 'php/includes/chain_manager.php';
$manager = new ChainManager();
$algos = $manager->getAllAlgorithms();
$chainsWithSteps = $isGuest ?? false ? [] : $manager->getUserChainsWithSteps($user['user_id']);
$userSk = htmlspecialchars($user['steg_key'] ?? '');
$chainKeysMap = [];
foreach ($chainsWithSteps as $c) {
  $stepsArr = array_map(fn($s) => [$s['algo_slug'], $s['algo_name'], $s['category'], '', ''], $c['steps']);
  $chainKeysMap[$c['chain_id']] = base64_encode(json_encode($stepsArr));
}
$preFileId = (int)($_GET['file'] ?? 0);
$preOpId = (int)($_GET['op'] ?? 0);
$preFile = null;
$preOp = null;
if ($preFileId > 0) {
  $preFile = $db->fetchOne("SELECT * FROM media_files WHERE file_id=? LIMIT 1", [$preFileId]);
}
if ($preOpId > 0) {
  $preOp = $db->fetchOne("SELECT op_id,stego_technique,payload_type FROM stego_operations WHERE op_id=? LIMIT 1", [$preOpId]);
}
function fmtSzEx(int $b): string
{
  if ($b < 1024) return $b . ' B';
  if ($b < 1048576) return round($b / 1024, 1) . ' KB';
  return round($b / 1048576, 2) . ' MB';
}
?>
<style>
  .steg-method-section {
    margin-top: 10px
  }

  .steg-method-label {
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    color: var(--text-muted);
    letter-spacing: .06em;
    text-transform: uppercase;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px
  }

  .steg-method-label .sml-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #c084fc;
    display: inline-block;
    animation: pulse-dot 1.8s ease-in-out infinite
  }

  @keyframes pulse-dot {

    0%,
    100% {
      opacity: .4;
      transform: scale(1)
    }

    50% {
      opacity: 1;
      transform: scale(1.3)
    }
  }

  .steg-method-cards {
    display: flex;
    flex-direction: column;
    gap: 6px
  }

  .steg-method-card {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 9px 12px;
    border-radius: 8px;
    border: 1px solid var(--border);
    cursor: pointer;
    transition: border-color .18s, background .18s;
    background: var(--surface)
  }

  .steg-method-card:hover {
    border-color: #c084fc;
    background: rgba(192, 132, 252, .05)
  }

  .steg-method-card.selected {
    border-color: #c084fc;
    background: rgba(192, 132, 252, .08)
  }

  .steg-method-card.special-method {
    border-color: rgba(251, 191, 36, .25)
  }

  .steg-method-card.special-method:hover,
  .steg-method-card.special-method.selected {
    border-color: #fbbf24;
    background: rgba(251, 191, 36, .06)
  }

  .steg-method-card.special-method .smc-indicator {
    border-color: rgba(251, 191, 36, .4)
  }

  .steg-method-card.special-method.selected .smc-indicator {
    border-color: #fbbf24
  }

  .steg-method-card.special-method.selected .smc-indicator::after {
    background: #fbbf24
  }

  .steg-method-card input[type=radio] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0
  }

  .smc-indicator {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid var(--border);
    flex-shrink: 0;
    margin-top: 2px;
    transition: border-color .18s;
    position: relative
  }

  .steg-method-card.selected .smc-indicator {
    border-color: #c084fc
  }

  .steg-method-card.selected .smc-indicator::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #c084fc
  }

  .smc-body {
    flex: 1;
    min-width: 0
  }

  .smc-name {
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    color: var(--text);
    font-weight: 600;
    margin-bottom: 2px
  }

  .smc-desc {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: var(--text-muted);
    line-height: 1.45
  }

  .smc-badge {
    display: inline-block;
    font-family: 'Space Mono', monospace;
    font-size: 8px;
    padding: 1px 5px;
    border-radius: 3px;
    margin-left: 5px;
    vertical-align: middle
  }

  .smc-badge.dct {
    background: rgba(251, 191, 36, .15);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, .3)
  }

  .smc-badge.special {
    background: rgba(192, 132, 252, .15);
    color: #c084fc;
    border: 1px solid rgba(192, 132, 252, .3)
  }

  .steg-method-loading {
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    color: var(--text-muted);
    padding: 10px 0;
    display: flex;
    align-items: center;
    gap: 8px
  }

  .steg-method-placeholder {
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    color: var(--text-dim);
    padding: 12px 0;
    text-align: center;
    border: 1px dashed var(--border);
    border-radius: 7px;
    opacity: .7
  }

  .sml-spinner {
    width: 12px;
    height: 12px;
    border: 2px solid var(--border);
    border-top-color: #c084fc;
    border-radius: 50%;
    animation: spin .7s linear infinite;
    flex-shrink: 0
  }

  .pre-file-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: rgba(192, 132, 252, .06);
    border: 1px solid rgba(192, 132, 252, .2);
    border-radius: 8px;
    margin-bottom: 10px;
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    color: #c084fc
  }

  .pre-file-banner .pfb-icon {
    font-size: 16px;
    flex-shrink: 0
  }

  .pre-file-banner .pfb-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text)
  }

  .pre-file-banner .pfb-size {
    color: var(--text-muted);
    white-space: nowrap
  }

  .pre-file-banner .pfb-op {
    font-size: 9px;
    color: var(--text-muted);
    white-space: nowrap
  }

  .pre-file-banner .pfb-clear {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 14px;
    padding: 2px 4px;
    transition: color .2s
  }

  .pre-file-banner .pfb-clear:hover {
    color: var(--danger)
  }

  .payload-img-result {
    max-width: 100%;
    max-height: 200px;
    object-fit: contain;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 8px;
    display: block
  }
</style>
<div class="page-header" style="margin-bottom:16px">
  <div>
    <div class="page-title">Extract Payload</div>
    <div class="page-sub">// UPLOAD STEGO FILE &rarr; DECODE &rarr; REVEAL</div>
  </div>
</div>
<div class="embed-layout extract-layout" id="extract-layout">
  <div class="embed-left">
    <div class="card" id="card-stego-upload">
      <div class="card-title">01 &mdash; Stego File</div>
      <?php if ($preFile): ?>
        <div class="pre-file-banner" id="pre-file-banner">
          <span class="pfb-icon">&#9673;</span>
          <span class="pfb-name"><?= htmlspecialchars($preFile['original_filename']) ?></span>
          <span class="pfb-size"><?= fmtSzEx((int)$preFile['file_size_bytes']) ?></span>
          <?php if ($preOp): ?><span class="pfb-op"><?= strtoupper((string)$preOp['stego_technique']) ?></span><?php endif; ?>
          <button class="pfb-clear" onclick="clearPreFile()" title="Clear">&#10005;</button>
        </div>
        <input type="hidden" id="pre-stego-file-id" value="<?= $preFileId ?>">
        <input type="hidden" id="pre-stego-type" value="<?= htmlspecialchars($preFile['file_type'] ?? 'image') ?>">
        <div style="display:none" id="stego-upload-row">
        <?php else: ?>
          <div class="carrier-upload-row" id="stego-upload-row">
          <?php endif; ?>
          <div class="upload-zone carrier-drop-zone" id="stego-drop" onclick="document.getElementById('stego-input').click()">
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
              <div class="steg-method-placeholder">&#11025; Upload a stego file above to see available methods</div>
            </div>
          </div>
          <input type="hidden" id="extract-steg-method-input" value="<?= htmlspecialchars($preOp['stego_technique'] ?? 'lsb') ?>">
        </div>
        <div class="card" id="card-stego-key">
          <div class="card-title">03 &mdash; Stego Key</div>
          <div class="input-group" style="margin-bottom:6px">
            <label class="input-label">Steganography Key</label>
            <div class="input-wrap">
              <span class="input-icon">&#11041;</span>
              <input type="password" id="extract-stego-key" class="input has-icon" placeholder="Key used during embedding" value="<?= $userSk ?>" oninput="persistExtract()" style="padding-right:76px">
              <button type="button" class="input-eye" onclick="togglePass('extract-stego-key',this)" style="right:38px">&#9673;</button>
              <button type="button" class="input-eye" onclick="useMyStegKey('extract')" style="right:8px;font-size:11px;font-family:'Space Mono',monospace;opacity:1;color:#c084fc" title="Use your saved steg key">&#11041;</button>
            </div>
          </div>
          <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted)">Must match exactly what was used during embedding.</div>
        </div>
    </div>
    <div class="embed-right" id="extract-right">
      <div class="card" id="card-extract-chain">
        <div class="card-title">04 &mdash; Decryption Chain</div>
        <?php if (!empty($chainsWithSteps)): ?>
          <div class="input-group" style="margin-bottom:10px">
            <label class="input-label">Load Saved Chain</label>
            <div class="input-wrap">
              <span class="input-icon" style="font-size:12px">&#9939;</span>
              <select class="input has-icon select-input" id="saved-chain-select" onchange="loadSavedChainExtract(this.value)" style="font-family:'Space Mono',monospace;font-size:11px">
                <option value="">-- Select a saved chain --</option>
                <?php foreach ($chainsWithSteps as $c): ?>
                  <option value="<?= addslashes($chainKeysMap[$c['chain_id']] ?? '') ?>"><?= htmlspecialchars($c['chain_name']) ?> (<?= $c['layer_count'] ?> layers)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        <?php endif; ?>
        <div class="input-group" style="margin-bottom:12px">
          <label class="input-label">Paste Chain Key <span class="optional-tag">(auto-loads chain)</span></label>
          <div class="input-wrap">
            <span class="input-icon" style="font-family:'Space Mono',monospace;font-size:10px">&#11041;</span>
            <input type="text" class="input has-icon" id="chain-key-input" placeholder="Paste the chain key from sender..." style="font-family:'JetBrains Mono',monospace;font-size:11px" oninput="autoDecodeChainKey(this.value)" onpaste="setTimeout(()=>autoDecodeChainKey(this.value),50)">
          </div>
        </div>
        <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-bottom:10px">Or build the chain manually &mdash; must match embedding order (reversed for decryption).</div>
        <div class="algo-chips-wrap">
          <?php foreach ($algos as $cat => $list): foreach (array_slice($list, 0, 2) as $a): ?>
              <div class="algo-chip <?= $cat ?><?= $a['algo_slug'] === 'netrunner' ? ' netrunner-chip' : '' ?>" onclick="addToChain('<?= addslashes($a['algo_name']) ?>','<?= $cat ?>','<?= $a['algo_slug'] ?>')">
                <?= htmlspecialchars($a['algo_name']) ?> <span class="chip-plus">+</span>
              </div>
          <?php endforeach;
          endforeach; ?>
          <a href="<?= SITE_URL ?>/chains.php" class="algo-chip-more">All &rarr;</a>
        </div>
        <div class="pipeline-status-bar">
          <span class="pipeline-label">CHAIN &mdash; <span id="chain-count">0</span> LAYERS</span>
          <div class="pipeline-controls">
            <div class="strength-bar">
              <div class="strength-fill" id="strength-fill" style="width:0%"></div>
            </div>
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
                <path d="M1 4V1H4M6 1H9V4M9 6V9H6M4 9H1V6" stroke="#fff" stroke-width="1.3" stroke-linecap="round" />
              </svg>
            </span>
          </div>
        </div>
        <div class="terminal-body" id="extract-term-body"></div>
        <div class="payload-reveal" id="payload-reveal" style="display:none">
          <div class="payload-reveal-label">DECRYPTED PAYLOAD</div>
          <div class="payload-reveal-content" id="payload-content"></div>
          <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
            <button class="btn btn-primary btn-sm" onclick="copyPayload()" id="copy-payload-btn">&#11041; Copy</button>
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
    const EXTRACT_URL = '<?= SITE_URL ?>/php/api/process_ajax.php';
    const USER_STEG_KEY = '<?= $userSk ?>';
    let _extractFileType = '<?= htmlspecialchars($preFile['file_type'] ?? '') ?>';
    let _extractAllMethods = [];
    let _extractSelectedMethod = '<?= htmlspecialchars($preOp['stego_technique'] ?? 'lsb') ?>';
    const EXTRACT_STATIC_METHODS = {
      image: [{
          slug: 'lsb',
          name: 'LSB — Bit Plane Injection',
          desc: 'Extracts payload from least significant bits of RGB channels.',
          special: false
        },
        {
          slug: 'dct',
          name: 'Compression Bypass Steg — DCT Domain',
          desc: 'Recovers payload from DCT coefficients. For DCT-embedded files.',
          special: true,
          badge: 'dct'
        },
        {
          slug: 'alpha',
          name: 'Alpha Channel Injection',
          desc: 'Extracts payload hidden in the alpha channel of PNG images.',
          special: false
        },
        {
          slug: 'palette',
          name: 'Palette Index Steg',
          desc: 'Recovers data from palette reordering in GIF/PNG files.',
          special: false
        },
        {
          slug: 'xor_pixel',
          name: 'XOR Pixel Masking',
          desc: 'Reverses XOR pixel masking to recover image payload.',
          special: true,
          badge: 'special'
        },
        {
          slug: 'bit_plane_mix',
          name: 'Bit Plane Decomposition',
          desc: 'Recovers payload image from bit plane merge.',
          special: true,
          badge: 'special'
        },
        {
          slug: 'alpha_blend',
          name: 'Alpha Transparency Blend',
          desc: 'Extracts payload fused into alpha layer.',
          special: false
        },
        {
          slug: 'img_in_img',
          name: 'Spatial Domain Overlay',
          desc: 'Recovers payload from spatial domain embedding.',
          special: false
        },
      ],
      audio: [{
          slug: 'wav_lsb',
          name: 'LSB Audio Injection',
          desc: 'Recovers payload from WAV sample LSBs.',
          special: false
        },
        {
          slug: 'echo_hiding',
          name: 'Echo Hiding',
          desc: 'Decodes payload from audio echo delay/decay parameters.',
          special: true,
          badge: 'special'
        },
        {
          slug: 'phase',
          name: 'Phase Coding',
          desc: 'Recovers payload from phase shifts in audio frames.',
          special: false
        },
        {
          slug: 'dct',
          name: 'Compression Bypass Steg — DCT Audio',
          desc: 'Extracts payload from DCT frequency domain of audio.',
          special: true,
          badge: 'dct'
        },
      ],
      video: [{
          slug: 'frame_lsb',
          name: 'LSB Frame Injection',
          desc: 'Extracts payload from per-frame LSB data.',
          special: false
        },
        {
          slug: 'frame_delta',
          name: 'FrameDelta Steg',
          desc: 'Recovers payload from inter-frame difference values.',
          special: true,
          badge: 'special'
        },
        {
          slug: 'motion_vector',
          name: 'Motion Vector Encoding',
          desc: 'Extracts payload from MPEG motion vector data.',
          special: true,
          badge: 'special'
        },
        {
          slug: 'dct',
          name: 'Compression Bypass Steg — DCT Video',
          desc: 'Recovers payload from DCT blocks across video frames.',
          special: true,
          badge: 'dct'
        },
      ]
    };
    async function loadExtractMethods(fileType) {
      const container = document.getElementById('extract-method-cards');
      if (!container) return;
      container.innerHTML = '<div class="steg-method-loading"><div class="sml-spinner"></div>Loading methods\u2026</div>';
      let engineMethods = [];
      try {
        const fd = new FormData();
        fd.append('action', 'get_methods');
        fd.append('file_type', fileType);
        const r = await fetch(EXTRACT_URL, {
          method: 'POST',
          body: fd,
          signal: AbortSignal.timeout(6000)
        });
        const ct = r.headers.get('content-type') || '';
        if (ct.includes('json')) {
          const d = await r.json();
          if (d.success && Array.isArray(d.methods) && d.methods.length) engineMethods = d.methods;
        }
      } catch (e) {}
      const staticList = EXTRACT_STATIC_METHODS[fileType] || EXTRACT_STATIC_METHODS.image;
      if (engineMethods.length) {
        const engineSlugs = new Set(engineMethods.map(m => m.slug));
        _extractAllMethods = [...engineMethods, ...staticList.filter(m => !engineSlugs.has(m.slug))];
      } else {
        _extractAllMethods = staticList;
      }
      renderExtractMethods(_extractAllMethods);
    }

    function renderExtractMethods(methods) {
      const container = document.getElementById('extract-method-cards');
      if (!container || !methods.length) return;
      container.innerHTML = '';
      const preMethod = document.getElementById('extract-steg-method-input')?.value || 'lsb';
      methods.forEach((m, i) => {
        const isSelected = m.slug === preMethod || (i === 0 && !methods.find(x => x.slug === preMethod));
        const isSpecial = m.special || false;
        const badge = m.badge ? `<span class="smc-badge ${m.badge}">${m.badge === 'dct' ? 'DCT' : 'NEW'}</span>` : '';
        const card = document.createElement('label');
        card.className = 'steg-method-card' + (isSelected ? ' selected' : '') + (isSpecial ? ' special-method' : '');
        card.innerHTML = `<input type="radio" name="extract_method_radio" value="${m.slug}" ${isSelected?'checked':''}><div class="smc-indicator"></div><div class="smc-body"><div class="smc-name">${m.name}${badge}</div><div class="smc-desc">${m.desc||''}</div></div>`;
        card.addEventListener('click', () => {
          document.querySelectorAll('#extract-method-cards .steg-method-card').forEach(c => c.classList.remove('selected'));
          card.classList.add('selected');
          _extractSelectedMethod = m.slug;
          const inp = document.getElementById('extract-steg-method-input');
          if (inp) inp.value = m.slug;
        });
        if (isSelected) {
          _extractSelectedMethod = m.slug;
          const inp = document.getElementById('extract-steg-method-input');
          if (inp) inp.value = m.slug;
        }
        container.appendChild(card);
      });
    }

    function onStegoFileSelected(input) {
      const file = input?.files?.[0];
      if (!file) return;
      let type = 'image';
      if (file.type.startsWith('audio/')) type = 'audio';
      else if (file.type.startsWith('video/')) type = 'video';
      _extractFileType = type;
      loadExtractMethods(type);
    }

    function clearPreFile() {
      document.getElementById('pre-file-banner')?.remove();
      const inp = document.getElementById('pre-stego-file-id');
      if (inp) inp.value = '';
      const row = document.getElementById('stego-upload-row');
      if (row) row.style.display = 'flex';
      _extractFileType = '';
      const mc = document.getElementById('extract-method-cards');
      if (mc) mc.innerHTML = '<div class="steg-method-placeholder">&#11025; Upload a stego file above to see available methods</div>';
    }

    function loadSavedChainExtract(key) {
      if (!key) return;
      try {
        const dec = JSON.parse(atob(key));
        chain = dec.map(s => ({
          slug: s[0],
          name: s[1],
          category: s[2],
          key: s[3] || '',
          key2: s[4] || ''
        }));
        renderChain();
        showToast('Chain loaded \u2014 ' + chain.length + ' layers');
      } catch (e) {
        showToast('Failed to load chain');
      }
    }

    function persistExtract() {
      try {
        localStorage.setItem('extract_stego_key', document.getElementById('extract-stego-key')?.value || '');
      } catch (e) {}
    }

    function restoreExtract() {
      try {
        const k = localStorage.getItem('extract_stego_key');
        if (k && document.getElementById('extract-stego-key')) document.getElementById('extract-stego-key').value = k;
      } catch (e) {}
    }
    let extractedPayload = '';
    let extractedBlob = null;

    function copyPayload() {
      if (extractedPayload) navigator.clipboard.writeText(extractedPayload).then(() => showToast('Payload copied!'));
    }

    function _renderBinaryPayload(rawPayload) {
      try {
        let binStr = '';
        try {
          binStr = atob(rawPayload);
        } catch (e1) {
          try {
            binStr = atob(atob(rawPayload));
          } catch (e2) {
            binStr = rawPayload;
          }
        }
        if (binStr.startsWith('STEGFILE1\n')) {
          const sepIdx = binStr.indexOf('\n\n', 9);
          if (sepIdx > 9) {
            const metaRaw = binStr.substring(10, sepIdx);
            let meta = {
              name: 'extracted_payload.bin',
              type: 'application/octet-stream'
            };
            try {
              meta = JSON.parse(metaRaw);
            } catch (e) {}
            const raw = binStr.substring(sepIdx + 2);
            const bytes = new Uint8Array(raw.length);
            for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i) & 0xff;
            extractedBlob = new Blob([bytes], {
              type: meta.type || 'application/octet-stream'
            });
            const url = URL.createObjectURL(extractedBlob);
            const isImg = (meta.type || '').startsWith('image/');
            let html = `<div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-dim);margin-bottom:8px">&#128196; ${meta.name} &bull; ${meta.type} &bull; ${bytes.length.toLocaleString()} B</div>`;
            if (isImg) html += `<img src="${url}" class="payload-img-result" alt="Extracted image">`;
            html += `<a href="${url}" download="${meta.name}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:5px;margin-top:6px">&#11015; Download ${meta.name}</a>`;
            extractedPayload = meta.name + ' (' + bytes.length + ' bytes)';
            return html;
          }
        }
        const bytes = new Uint8Array(binStr.length);
        for (let i = 0; i < binStr.length; i++) bytes[i] = binStr.charCodeAt(i) & 0xff;
        extractedBlob = new Blob([bytes]);
        const url = URL.createObjectURL(extractedBlob);
        extractedPayload = 'binary:' + bytes.length + 'B';
        return `<div style="color:var(--text-muted);font-family:'Space Mono',monospace;font-size:10px;margin-bottom:8px">Binary payload &mdash; ${bytes.length.toLocaleString()} bytes</div><a href="${url}" download="extracted_payload.bin" class="btn btn-primary btn-sm">&#11015; Download binary</a>`;
      } catch (e) {
        return `<div style="color:var(--danger);font-family:'Space Mono',monospace;font-size:10px">Failed to decode binary payload: ${e.message}</div>`;
      }
    }

    function runExtract() {
      const stegoKey = document.getElementById('extract-stego-key')?.value || '';
      const stegoFile = document.getElementById('stego-input')?.files[0];
      const preFileId = document.getElementById('pre-stego-file-id')?.value || '';
      const stegMethod = document.getElementById('extract-steg-method-input')?.value || _extractSelectedMethod || 'lsb';
      const btn = document.getElementById('extract-btn');
      if (!stegoFile && !preFileId) {
        showStegError('Upload a stego file first');
        return;
      }
      if (!stegoKey) {
        showStegError('Enter the stego key');
        return;
      }
      setLoading(btn, true);
      if (btn._setProc) btn._setProc(true);
      showTerminal('extract-terminal');
      const termBody = document.getElementById('extract-term-body');
      const revealEl = document.getElementById('payload-reveal');
      revealEl.style.display = 'none';
      const hasCrypto = chain.some(s => ['symmetric', 'asymmetric', 'classical'].includes(s.category));
      const cryptoLayers = chain.filter(s => ['symmetric', 'asymmetric', 'classical'].includes(s.category)).length;
      const steps = ['Initializing decryption session\u2026', 'Loading stego file: ' + (stegoFile?.name || 'pre-loaded #' + preFileId), 'Detecting steganography: ' + stegMethod.toUpperCase().replace(/_/g, ' '), 'Locating hidden payload region\u2026', chain.length > 0 ? ('Reversing chain \u2014 ' + chain.length + ' layer' + (chain.length > 1 ? 's' : '')) + '\u2026' : ('No chain \u2014 expecting raw payload\u2026'), ...chain.slice().reverse().map((s, i) => '  Layer ' + (i + 1) + ' (rev): ' + s.name), ...(hasCrypto ? ['Reversing ' + cryptoLayers + ' crypto layer' + (cryptoLayers > 1 ? 's' : '') + '\u2026', 'Decrypting data\u2026'] : []), 'Extracting bytes from carrier\u2026', 'Decoding payload data\u2026'];
      animateTerminal(termBody, steps, () => {
        const fd = new FormData();
        fd.append('action', 'extract');
        fd.append('stego_key', stegoKey);
        fd.append('chain_data', JSON.stringify(chain));
        fd.append('steg_method', stegMethod);
        if (preFileId) fd.append('stego_file_id', preFileId);
        else fd.append('stego_file', stegoFile);
        fetch(EXTRACT_URL, {
            method: 'POST',
            body: fd
          })
          .then(r => {
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('json')) return r.text().then(t => {
              throw new Error('Server error (' + r.status + '): ' + t.substring(0, 120));
            });
            return r.json();
          })
          .then(d => {
            setLoading(btn, false);
            if (d.success) {
              if (btn._flashOk) btn._flashOk();
              addTermLine(termBody, '', '<span style="color:var(--accent)">\u2713 EXTRACTION SUCCESSFUL</span>');
              extractedPayload = d.payload || '';
              const copyBtn = document.getElementById('copy-payload-btn');
              if (d.is_binary) {
                document.getElementById('payload-content').innerHTML = _renderBinaryPayload(extractedPayload);
                if (copyBtn) copyBtn.style.display = 'none';
              } else {
                const pcontent = document.getElementById('payload-content');
                pcontent.textContent = extractedPayload;
                if (copyBtn) copyBtn.style.display = '';
              }
              revealEl.style.display = 'block';
            } else {
              if (btn._flashErr) btn._flashErr();
              else setLoading(btn, false);
              addTermLine(termBody, 'err', '\u2717 ' + d.message);
              showStegError(d.message);
            }
          }).catch(e => {
            setLoading(btn, false);
            if (btn._flashErr) btn._flashErr();
            addTermLine(termBody, 'err', '\u2717 Network error: ' + e.message);
            showStegError('Network error: ' + e.message);
          });
      });
    }
    window.addEventListener('DOMContentLoaded', () => {
      restoreExtract();
      renderChain();
      const preType = document.getElementById('pre-stego-type')?.value;
      if (preType) {
        loadExtractMethods(preType);
      }
    });
  </script>
  <?php require_once 'php/includes/footer.php'; ?>