<?php
$pageTitle='Extract';
$activePage='extract';
require_once 'php/includes/header.php';
require_once 'php/includes/chain_manager.php';
$manager=new ChainManager();
$algos=$manager->getAllAlgorithms();
$chainsWithSteps=$isGuest??false?[]:$manager->getUserChainsWithSteps($user['user_id']);
$userSk=htmlspecialchars($user['steg_key']??'');
$chainKeysMap=[];
foreach($chainsWithSteps as $c){
$stepsArr=array_map(fn($s)=>[$s['algo_slug'],$s['algo_name'],$s['category'],'',''],$c['steps']);
$chainKeysMap[$c['chain_id']]=base64_encode(json_encode($stepsArr));
}
?>
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
<div class="carrier-upload-row" id="stego-upload-row">
<div class="upload-zone carrier-drop-zone" id="stego-drop" onclick="document.getElementById('stego-input').click()">
<div class="upload-icon">&#9673;</div>
<div class="upload-text">Click or drag stego file</div>
<div class="upload-sub">PNG / WAV / MP4 &mdash; file with hidden payload</div>
</div>
<div id="stego-preview" class="carrier-preview-panel" style="display:none"></div>
</div>
<input type="file" id="stego-input" style="display:none" onchange="handleCarrierSelect(this,'stego-preview')" accept="image/*,audio/*,video/*">
</div>
<div class="card" id="card-stego-key">
<div class="card-title">02 &mdash; Stego Key</div>
<div class="input-group" style="margin-bottom:6px">
<label class="input-label">Steganography Key</label>
<div class="input-wrap">
<span class="input-icon">&#11041;</span>
<input type="password" id="extract-stego-key" class="input has-icon" placeholder="Key used during embedding" value="<?= $userSk ?>" oninput="persistExtract()" style="padding-right:76px">
<button type="button" class="input-eye" onclick="togglePass('extract-stego-key',this)" style="right:38px">&#9673;</button>
<button type="button" class="input-eye" onclick="useMyStegKey('extract')" style="right:8px;font-size:11px;font-family:'Space Mono',monospace;opacity:1;color:var(--accent)" title="Use your saved steg key">&#11041;</button>
</div>
</div>
<div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted)">Must match exactly what was used during embedding.</div>
</div>
</div>
<div class="embed-right" id="extract-right">
<div class="card" id="card-extract-chain">
<div class="card-title">03 &mdash; Decryption Chain</div>
<?php if(!empty($chainsWithSteps)): ?>
<div class="input-group" style="margin-bottom:10px">
<label class="input-label">Load Saved Chain</label>
<div class="input-wrap">
<span class="input-icon" style="font-size:12px">&#9939;</span>
<select class="input has-icon select-input" id="saved-chain-select" onchange="loadSavedChainExtract(this.value)" style="font-family:'Space Mono',monospace;font-size:11px">
<option value="">-- Select a saved chain --</option>
<?php foreach($chainsWithSteps as $c): ?>
<option value="<?= addslashes($chainKeysMap[$c['chain_id']]??'') ?>"><?= htmlspecialchars($c['chain_name']) ?> (<?= $c['layer_count'] ?> layers)</option>
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
<div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-bottom:10px">Or build the chain manually &mdash; must match embedding order.</div>
<div class="algo-chips-wrap">
<?php foreach($algos as $cat=>$list): foreach(array_slice($list,0,2) as $a): ?>
<div class="algo-chip <?= $cat ?>" onclick="addToChain('<?= addslashes($a['algo_name']) ?>','<?= $cat ?>','<?= $a['algo_slug'] ?>')">
<?= htmlspecialchars($a['algo_name']) ?> <span class="chip-plus">+</span>
</div>
<?php endforeach; endforeach; ?>
<a href="<?= SITE_URL ?>/chains.php" class="algo-chip-more">All &rarr;</a>
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
<svg viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg" class="term-max-svg"><path d="M1 4V1H4M6 1H9V4M9 6V9H6M4 9H1V6" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/></svg>
</span>
</div>
</div>
<div class="terminal-body" id="extract-term-body"></div>
<div class="payload-reveal" id="payload-reveal" style="display:none">
<div class="payload-reveal-label">DECRYPTED PAYLOAD</div>
<div class="payload-reveal-content" id="payload-content"></div>
<div style="display:flex;gap:8px;margin-top:10px">
<button class="btn btn-primary btn-sm" onclick="copyPayload()">&#11041; Copy</button>
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
const EXTRACT_URL='<?= SITE_URL ?>/php/api/process_ajax.php';
const USER_STEG_KEY='<?= $userSk ?>';
function loadSavedChainExtract(key){
if(!key)return;
try{const dec=JSON.parse(atob(key));chain=dec.map(s=>({slug:s[0],name:s[1],category:s[2],key:s[3]||'',key2:s[4]||''}));renderChain();showToast('Chain loaded \u2014 '+chain.length+' layers');}
catch(e){showToast('Failed to load chain');}
}
function persistExtract(){try{localStorage.setItem('extract_stego_key',document.getElementById('extract-stego-key')?.value||'');}catch(e){}}
function restoreExtract(){try{const k=localStorage.getItem('extract_stego_key');if(k&&document.getElementById('extract-stego-key'))document.getElementById('extract-stego-key').value=k;}catch(e){}}
let extractedPayload='';
function copyPayload(){navigator.clipboard.writeText(extractedPayload).then(()=>showToast('Payload copied!'));}
function runExtract(){
const stegoKey=document.getElementById('extract-stego-key')?.value||'';
const stegoFile=document.getElementById('stego-input')?.files[0];
const btn=document.getElementById('extract-btn');
if(!stegoFile){showStegError('Upload a stego file first');return;}
if(!stegoKey){showStegError('Enter the stego key');return;}
setLoading(btn,true);
if(btn._setProc)btn._setProc(true);
showTerminal('extract-terminal');
const termBody=document.getElementById('extract-term-body');
const revealEl=document.getElementById('payload-reveal');
revealEl.style.display='none';
const hasCrypto=chain.some(s=>['symmetric','asymmetric','classical'].includes(s.category));
const cryptoLayers=chain.filter(s=>['symmetric','asymmetric','classical'].includes(s.category)).length;
const steps=['Initializing decryption session...','Loading stego file: '+stegoFile.name,'Detecting steganography technique...','Locating hidden payload region...',chain.length>0?('Reversing chain \u2014 '+chain.length+' layer'+(chain.length>1?'s reverse order:':':')):('No chain \u2014 expecting raw payload...'),...chain.slice().reverse().map((s,i)=>'  Layer '+(i+1)+' (rev): '+s.name),...(hasCrypto?['Reversing '+cryptoLayers+' crypto layer'+(cryptoLayers>1?'s':'')+'...','Decrypting data...']:[]),'Extracting bytes from carrier...','Verifying payload integrity...','Decoding payload data...'];
animateTerminal(termBody,steps,()=>{
const fd=new FormData();
fd.append('action','extract');
fd.append('stego_key',stegoKey);
fd.append('chain_data',JSON.stringify(chain));
fd.append('stego_file',stegoFile);
fetch(EXTRACT_URL,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
setLoading(btn,false);
if(d.success){
if(btn._flashOk)btn._flashOk();
addTermLine(termBody,'','<span style="color:var(--accent)">\u2713 EXTRACTION SUCCESSFUL</span>');
extractedPayload=d.payload||'';
document.getElementById('payload-content').textContent=extractedPayload;
revealEl.style.display='block';
}else{
if(btn._flashErr)btn._flashErr();else setLoading(btn,false);
addTermLine(termBody,'err','\u2717 '+d.message);
showStegError(d.message);
}
}).catch(e=>{setLoading(btn,false);if(btn._flashErr)btn._flashErr();addTermLine(termBody,'err','\u2717 Network error');showStegError('Network error');});
});
}
window.addEventListener('DOMContentLoaded',()=>{restoreExtract();renderChain();});
</script>
<?php require_once 'php/includes/footer.php'; ?>
