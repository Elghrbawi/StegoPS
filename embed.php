<?php
$pageTitle='Embed';
$activePage='embed';
require_once 'php/includes/header.php';
require_once 'php/includes/stego_handler.php';
require_once 'php/includes/chain_manager.php';
$manager=new ChainManager();
$handler=new StegoHandler();
$chains=$manager->getUserChains($user['user_id']);
$algos=$manager->getAllAlgorithms();
$error=$success='';
$result=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
$stegoKey=$_POST['stego_key']??'';
$payload=$_POST['payload_text']??'';
$pType=$_POST['payload_type']??'text';
$chainData=json_decode($_POST['chain_data']??'[]',true)?:[];
$chainId=!empty($_POST['saved_chain'])?(int)$_POST['saved_chain']:null;
if(empty($stegoKey)){$error='Stego key is required.';}
elseif(empty($payload)&&$pType==='text'){$error='Payload text cannot be empty.';}
elseif(empty($_FILES['carrier']['name'])||$_FILES['carrier']['error']!==UPLOAD_ERR_OK){$error='Please upload a valid carrier file.';}
else{
$uploaded=$handler->uploadCarrier($_FILES['carrier'],$user['user_id']);
if(!$uploaded['success']){$error=$uploaded['message'];}
else{
$steps=[];
if($chainId){
$chain=$manager->getChainWithSteps($chainId,$user['user_id']);
if($chain){foreach($chain['steps'] as $s){$steps[]=['slug'=>$s['algo_slug'],'key'=>$_POST['chain_key_'.$s['step_id']]??''];}}
}else{$steps=$chainData;}
$res=$handler->embed($user['user_id'],$uploaded['file_id'],$chainId,$pType,$payload,$stegoKey,$steps);
$res['success']?($success=$res['message'])&&($result=$res):($error=$res['message']);
}
}
}
?>
<div class="page-header">
<div>
<div class="page-title">Embed Payload</div>
<div class="page-sub">// ENCRYPT → HIDE INSIDE MEDIA → SHARE</div>
</div>
</div>
<?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" id="embed-form">
<input type="hidden" name="chain_data" id="chain-data" value="[]">
<input type="hidden" name="payload_type" id="payload-type-input" value="text">
<input type="hidden" name="media_type" id="media-type-input" value="image">
<div class="grid-2">
<div style="display:flex;flex-direction:column;gap:16px">
<div class="card">
<div class="card-title">Step 1 — Payload</div>
<div class="media-tabs" style="margin-bottom:14px">
<div class="media-tab active" id="pt-text" onclick="switchPayloadType('text')"><div class="media-tab-icon">✎</div><div class="media-tab-label">Text</div></div>
<div class="media-tab" id="pt-file" onclick="switchPayloadType('file')"><div class="media-tab-icon">◈</div><div class="media-tab-label">File</div></div>
</div>
<div id="payload-text"><textarea name="payload_text" class="textarea" placeholder="Type your secret message here..." rows="5"></textarea></div>
<div id="payload-file" style="display:none">
<div class="upload-zone" onclick="document.getElementById('payload-file-input').click()">
<div class="upload-icon">📎</div>
<div class="upload-text">Click or drag to select file to hide</div>
<div class="upload-sub">Any file type — max 50MB</div>
</div>
<input type="file" id="payload-file-input" name="payload_file" style="display:none" onchange="handleFileSelect(this,'pf-preview')">
<div id="pf-preview"></div>
</div>
</div>
<div class="card">
<div class="card-title">Step 2 — Carrier Media</div>
<div class="media-tabs">
<div class="media-tab active" id="mt-image" onclick="switchMedia('image')"><div class="media-tab-icon">🖼</div><div class="media-tab-label">Image</div></div>
<div class="media-tab" id="mt-audio" onclick="switchMedia('audio')"><div class="media-tab-icon">🎵</div><div class="media-tab-label">Audio</div></div>
<div class="media-tab" id="mt-video" onclick="switchMedia('video')"><div class="media-tab-icon">🎬</div><div class="media-tab-label">Video</div></div>
</div>
<div class="upload-zone" id="carrier-drop" onclick="document.getElementById('carrier-input').click()">
<div class="upload-icon" id="carrier-icon">🖼</div>
<div class="upload-text">Click or drag to upload carrier file</div>
<div class="upload-sub" id="carrier-sub">PNG, JPG, BMP supported</div>
<div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-top:5px" id="carrier-technique">LSB Steganography</div>
</div>
<input type="file" id="carrier-input" name="carrier" style="display:none" onchange="handleFileSelect(this,'carrier-preview')" accept="image/*,audio/*,video/*">
<div id="carrier-preview"></div>
</div>
</div>
<div style="display:flex;flex-direction:column;gap:16px">
<div class="card">
<div class="card-title">Step 3 — Encryption Chain</div>
<div class="input-group">
<label class="input-label">Use Saved Chain (optional)</label>
<select name="saved_chain" class="select-input">
<option value="">— Build chain below instead —</option>
<?php foreach($chains as $c): ?>
<option value="<?= $c['chain_id'] ?>"><?= htmlspecialchars($c['chain_name']) ?> (<?= $c['layer_count'] ?> layers)</option>
<?php endforeach; ?>
</select>
</div>
<div style="display:flex;align-items:center;gap:10px;margin:10px 0">
<div style="flex:1;height:1px;background:var(--border)"></div>
<span style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted)">OR BUILD</span>
<div style="flex:1;height:1px;background:var(--border)"></div>
</div>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
<?php foreach($algos as $category=>$algoList): ?>
<?php foreach(array_slice($algoList,0,3) as $a): ?>
<div class="algo-chip <?= $category ?>" style="font-size:10px;padding:4px 8px" onclick="addToChain('<?= addslashes($a['algo_name']) ?>','<?= $category ?>','<?= $a['algo_slug'] ?>')">
<?= htmlspecialchars($a['algo_name']) ?> <span style="opacity:.5">+</span>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
<a href="<?= SITE_URL ?>/chains.php" style="font-family:'Space Mono',monospace;font-size:10px;color:var(--accent);padding:4px 8px;align-self:center">Full Builder →</a>
</div>
<div>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px">
<span style="font-family:'Space Mono',monospace;font-size:9px;letter-spacing:2px;color:var(--text-muted)">PIPELINE — <span id="chain-count">0</span> LAYERS</span>
<div style="display:flex;align-items:center;gap:7px">
<div class="strength-bar"><div class="strength-fill" id="strength-fill" style="width:0%"></div></div>
<span style="font-family:'Space Mono',monospace;font-size:11px;color:var(--accent)" id="strength-val">0%</span>
<button type="button" class="btn btn-ghost" onclick="clearChain()" style="padding:3px 8px;font-size:10px">✕</button>
</div>
</div>
<div id="chain-steps" style="max-height:220px;overflow-y:auto"><div class="chain-empty" style="padding:20px;font-size:10px">⛓ Add algorithms above</div></div>
</div>
</div>
<div class="card">
<div class="card-title">Step 4 — Stego Key</div>
<div class="input-group" style="margin-bottom:6px">
<label class="input-label">Steganography Key</label>
<div class="input-wrap">
<span class="input-icon">⬡</span>
<input type="password" name="stego_key" class="input has-icon" placeholder="Controls pixel traversal pattern" required>
<button type="button" class="input-eye" onclick="togglePass('stego_key_input',this)">◉</button>
</div>
</div>
<div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)">⚠ Keep secret — required to extract the hidden payload.</div>
</div>
<button type="submit" class="btn btn-primary btn-full btn-lg">⬇ HIDE &amp; SECURE</button>
<?php if($result): ?>
<div class="card card-accent">
<div class="card-title" style="color:var(--accent)">✓ Embed Successful</div>
<div style="font-family:'Space Mono',monospace;font-size:12px;color:var(--text-dim);margin-bottom:14px">Operation #<?= $result['op_id'] ?> completed.</div>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<a href="<?= SITE_URL ?>/share.php?op=<?= $result['op_id'] ?>" class="btn btn-primary">⬡ Create Share Link</a>
<a href="<?= SITE_URL ?>/download.php?fid=<?= $result['file_id'] ?>" class="btn btn-ghost">⬇ Download File</a>
</div>
</div>
<?php endif; ?>
</div>
</div>
</form>
<?php require_once 'php/includes/footer.php'; ?>
