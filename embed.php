<?php
$pageTitle='Embed';
$activePage='embed';
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
<div class="page-title">Embed Payload</div>
<div class="page-sub">// ENCRYPT &rarr; HIDE INSIDE MEDIA</div>
</div>
</div>
<div class="embed-layout" id="embed-layout">
<div class="embed-left">
<div class="card" id="card-payload">
<div class="card-title">01 &mdash; Payload</div>
<div class="media-tabs payload-tabs" style="margin-bottom:14px">
<div class="media-tab active" id="pt-text" onclick="switchPayloadType('text')"><div class="media-tab-icon">&#9998;</div><div class="media-tab-label">Text</div></div>
<div class="media-tab" id="pt-file" onclick="switchPayloadType('file')"><div class="media-tab-icon">&#10792;</div><div class="media-tab-label">File</div></div>
</div>
<div id="payload-text">
<textarea name="payload_text" id="embed-payload" class="textarea" placeholder="Type your secret message here..." rows="6" oninput="persistEmbed()" style="resize:vertical"></textarea>
</div>
<div id="payload-file" style="display:none">
<div class="carrier-upload-row" id="pf-upload-row">
<div class="upload-zone carrier-drop-zone" id="pf-drop" onclick="document.getElementById('payload-file-input').click()">
<div class="upload-icon">&#128206;</div>
<div class="upload-text">Click or drag file to hide</div>
<div class="upload-sub">Any file type &mdash; max 50MB</div>
</div>
<div id="pf-preview" class="carrier-preview-panel" style="display:none"></div>
</div>
<input type="file" id="payload-file-input" name="payload_file" style="display:none" onchange="handleCarrierSelect(this,'pf-preview')">
</div>
</div>
<div class="card" id="card-carrier">
<div class="card-title">02 &mdash; Carrier Media</div>
<div class="media-tabs">
<div class="media-tab active" id="mt-image" onclick="switchMedia('image')"><div class="media-tab-icon">&#128444;</div><div class="media-tab-label">Image</div></div>
<div class="media-tab" id="mt-audio" onclick="switchMedia('audio')"><div class="media-tab-icon">&#127925;</div><div class="media-tab-label">Audio</div></div>
<div class="media-tab" id="mt-video" onclick="switchMedia('video')"><div class="media-tab-icon">&#127916;</div><div class="media-tab-label">Video</div></div>
</div>
<div class="carrier-upload-row" id="carrier-upload-row">
<div class="upload-zone carrier-drop-zone" id="carrier-drop" onclick="document.getElementById('carrier-input').click()">
<div class="upload-icon" id="carrier-icon">&#128444;</div>
<div class="upload-text">Click or drag carrier file</div>
<div class="upload-sub" id="carrier-sub">PNG, JPG, BMP supported</div>
<div class="carrier-technique-label" id="carrier-technique">LSB Steganography</div>
</div>
<div id="carrier-preview" class="carrier-preview-panel" style="display:none"></div>
</div>
<input type="file" id="carrier-input" name="carrier" style="display:none" onchange="handleCarrierSelect(this,'carrier-preview')" accept="image/*,audio/*,video/*">
<input type="hidden" id="media-type-input" value="image">
<input type="hidden" id="payload-type-input" value="text">
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
<?php if($user['steg_key']??null): ?><a href="<?= SITE_URL ?>/profile.php" style="color:var(--accent)">Your profile steg key &rarr;</a><?php endif; ?>
</div>
</div>
</div>
<div class="embed-right" id="embed-right">
<div class="card" id="card-chain">
<div class="card-title">03 &mdash; Encryption Chain</div>
<?php if(!empty($chainsWithSteps)): ?>
<div class="input-group" style="margin-bottom:10px">
<label class="input-label">Load Saved Chain</label>
<div class="input-wrap">
<span class="input-icon" style="font-size:12px">&#9939;</span>
<select class="input has-icon select-input" id="saved-chain-select" onchange="loadSavedChainEmbed(this.value)" style="font-family:'Space Mono',monospace;font-size:11px">
<option value="">-- Select a saved chain --</option>
<?php foreach($chainsWithSteps as $c): ?>
<option value="<?= addslashes($chainKeysMap[$c['chain_id']]??'') ?>"><?= htmlspecialchars($c['chain_name']) ?> (<?= $c['layer_count'] ?> layers)</option>
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
<?php foreach($algos as $cat=>$list): foreach(array_slice($list,0,2) as $a): ?>
<div class="algo-chip <?= $cat ?>" onclick="addToChain('<?= addslashes($a['algo_name']) ?>','<?= $cat ?>','<?= $a['algo_slug'] ?>')">
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
const EMBED_URL='<?= SITE_URL ?>/php/api/process_ajax.php';
const USER_STEG_KEY='<?= $userSk ?>';
function loadSavedChainEmbed(key){
if(!key)return;
try{const dec=JSON.parse(atob(key));chain=dec.map(s=>({slug:s[0],name:s[1],category:s[2],key:s[3]||'',key2:s[4]||''}));renderChain();showToast('Chain loaded \u2014 '+chain.length+' layers');}
catch(e){showToast('Failed to load chain');}
}
function persistEmbed(){
try{localStorage.setItem('embed_payload',document.getElementById('embed-payload')?.value||'');localStorage.setItem('embed_stego_key',document.getElementById('embed-stego-key')?.value||'');}catch(e){}
}
function restoreEmbed(){
try{const p=localStorage.getItem('embed_payload');const k=localStorage.getItem('embed_stego_key');if(p&&document.getElementById('embed-payload'))document.getElementById('embed-payload').value=p;if(k&&document.getElementById('embed-stego-key'))document.getElementById('embed-stego-key').value=k;}catch(e){}
}
function runEmbed(){
const payload=document.getElementById('embed-payload')?.value||'';
const stegoKey=document.getElementById('embed-stego-key')?.value||'';
const carrierFile=document.getElementById('carrier-input')?.files[0];
const btn=document.getElementById('embed-btn');
if(!carrierFile){showStegError('Upload a carrier file first');return;}
if(!payload&&document.getElementById('payload-type-input').value==='text'){showStegError('Enter a payload message');return;}
if(!stegoKey){showStegError('Enter your stego key');return;}
setLoading(btn,true);
if(btn._setProc)btn._setProc(true);
showTerminal('embed-terminal');
const termBody=document.getElementById('embed-term-body');
const hasCrypto=chain.some(s=>['symmetric','asymmetric','classical'].includes(s.category));
const cryptoLayers=chain.filter(s=>['symmetric','asymmetric','classical'].includes(s.category)).length;
const steps=['Initializing secure session...','Validating carrier: '+carrierFile.name,'Reading file metadata...',chain.length>0?('Building pipeline \u2014 '+chain.length+' layer'+(chain.length>1?'s:':':')):('No encryption \u2014 embedding raw payload...'),...chain.map((s,i)=>'  Layer '+(i+1)+': '+s.name+(s.key?' [keyed]':' [keyless]')),...(hasCrypto?['Deriving '+cryptoLayers+' crypto key'+(cryptoLayers>1?'s':'')+'...','Encrypting payload...']:[]),'Calculating carrier capacity...','Applying LSB steganography...','Embedding '+payload.length+' bytes...','Finalizing output...'];
animateTerminal(termBody,steps,()=>{
const fd=new FormData();
fd.append('action','embed');
fd.append('payload_text',payload);
fd.append('stego_key',stegoKey);
fd.append('chain_data',JSON.stringify(chain));
fd.append('media_type',document.getElementById('media-type-input').value);
fd.append('payload_type',document.getElementById('payload-type-input').value);
fd.append('carrier',carrierFile);
fetch(EMBED_URL,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
setLoading(btn,false);
if(d.success){
if(btn._flashOk)btn._flashOk();
addTermLine(termBody,'','<span style="color:var(--accent)">\u2713 COMPLETE \u2014 #'+d.op_id+'</span>');
addTermLine(termBody,'','<a href="<?= SITE_URL ?>/php/api/download.php?id='+d.file_id+'" style="color:var(--accent)">\u2B07 Download</a> &nbsp; <a href="<?= SITE_URL ?>/share.php?op='+d.op_id+'" style="color:var(--text-dim)">&#11041; Share</a>');
localStorage.removeItem('embed_payload');
}else{
if(btn._flashErr)btn._flashErr();else setLoading(btn,false);
addTermLine(termBody,'err','\u2717 '+d.message);
showStegError(d.message);
}
}).catch(e=>{
setLoading(btn,false);if(btn._flashErr)btn._flashErr();
addTermLine(termBody,'err','\u2717 Network error: '+e.message);
showStegError('Network error');
});
});
}
window.addEventListener('DOMContentLoaded',()=>{restoreEmbed();renderChain();});
</script>
<?php require_once 'php/includes/footer.php'; ?>
