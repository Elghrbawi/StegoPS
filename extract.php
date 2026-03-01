<?php
$pageTitle='Extract';
$activePage='extract';
require_once 'php/includes/header.php';
require_once 'php/includes/stego_handler.php';
require_once 'php/includes/chain_manager.php';
$manager=new ChainManager();
$handler=new StegoHandler();
$chains=$manager->getUserChains($user['user_id']);
$algos=$manager->getAllAlgorithms();
$error=$success=$payload='';
if($_SERVER['REQUEST_METHOD']==='POST'){
$stegoKey=$_POST['stego_key']??'';
$chainData=json_decode($_POST['chain_data']??'[]',true)?:[];
if(empty($stegoKey)){$error='Stego key is required.';}
elseif(empty($_FILES['stego_file']['name'])||$_FILES['stego_file']['error']!==UPLOAD_ERR_OK){$error='Please upload a valid stego file.';}
else{
$uploaded=$handler->uploadCarrier($_FILES['stego_file'],$user['user_id']);
if(!$uploaded['success']){$error=$uploaded['message'];}
else{
$res=$handler->extract($user['user_id'],$uploaded['file_id'],$stegoKey,$chainData);
$res['success']?($success=$res['message'])&&($payload=$res['payload']):($error=$res['message']);
}
}
}
?>
<div class="page-header">
<div>
<div class="page-title">Extract Payload</div>
<div class="page-sub">// UPLOAD STEGO FILE → APPLY CHAIN → REVEAL</div>
</div>
</div>
<?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="chain_data" id="chain-data" value="[]">
<div class="grid-2">
<div style="display:flex;flex-direction:column;gap:16px">
<div class="card">
<div class="card-title">Step 1 — Upload Stego File</div>
<div class="upload-zone" onclick="document.getElementById('stego-input').click()">
<div class="upload-icon">🔍</div>
<div class="upload-text">Click or drag to upload stego file</div>
<div class="upload-sub">PNG / WAV / MP4 supported</div>
</div>
<input type="file" id="stego-input" name="stego_file" style="display:none" onchange="handleFileSelect(this,'stego-preview')" accept="image/*,audio/*,video/*">
<div id="stego-preview"></div>
</div>
<div class="card">
<div class="card-title">Step 2 — Stego Key</div>
<div class="input-group" style="margin-bottom:0">
<label class="input-label">Steganography Key</label>
<div class="input-wrap">
<span class="input-icon">⬡</span>
<input type="password" name="stego_key" class="input has-icon" placeholder="Enter the stego key used during embed" required>
<button type="button" class="input-eye" onclick="togglePass(this.previousElementSibling.id||'sk',this)">◉</button>
</div>
</div>
</div>
</div>
<div style="display:flex;flex-direction:column;gap:16px">
<div class="card">
<div class="card-title">Step 3 — Decryption Chain</div>
<div class="input-group">
<label class="input-label">Chain Used for Embedding</label>
<select name="saved_chain" class="select-input">
<option value="">— Use chain builder below —</option>
<?php foreach($chains as $c): ?>
<option value="<?= $c['chain_id'] ?>"><?= htmlspecialchars($c['chain_name']) ?> (<?= $c['layer_count'] ?> layers)</option>
<?php endforeach; ?>
</select>
</div>
<div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);margin-bottom:10px">⚠ Must use exact same chain as embedding — decryption applies in reverse.</div>
<div>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px">
<span style="font-family:'Space Mono',monospace;font-size:9px;letter-spacing:2px;color:var(--text-muted)">PIPELINE — <span id="chain-count">0</span> LAYERS</span>
<div style="display:flex;align-items:center;gap:7px">
<div class="strength-bar"><div class="strength-fill" id="strength-fill" style="width:0%"></div></div>
<span style="font-family:'Space Mono',monospace;font-size:11px;color:var(--accent)" id="strength-val">0%</span>
<button type="button" class="btn btn-ghost" onclick="clearChain()" style="padding:3px 8px;font-size:10px">✕</button>
</div>
</div>
<div id="chain-steps" style="max-height:180px;overflow-y:auto"><div class="chain-empty" style="padding:16px;font-size:10px">⛓ Add algorithms</div></div>
</div>
<div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:10px">
<?php foreach($algos as $cat=>$list): ?>
<?php foreach(array_slice($list,0,2) as $a): ?>
<div class="algo-chip <?= $cat ?>" style="font-size:10px;padding:4px 8px" onclick="addToChain('<?= addslashes($a['algo_name']) ?>','<?= $cat ?>','<?= $a['algo_slug'] ?>')">
<?= htmlspecialchars($a['algo_name']) ?> <span style="opacity:.5">+</span>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
</div>
</div>
<button type="submit" class="btn btn-primary btn-full btn-lg">⬆ EXTRACT &amp; DECRYPT</button>
<?php if($payload): ?>
<div class="card card-accent">
<div class="card-title" style="color:var(--accent)">✓ Extraction Successful — Integrity Verified</div>
<div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;font-family:'JetBrains Mono',monospace;font-size:13px;color:var(--text-dim);margin-bottom:14px;max-height:180px;overflow-y:auto;white-space:pre-wrap;word-break:break-all">
<?= htmlspecialchars($payload) ?>
</div>
<button type="button" class="btn btn-ghost" onclick="copyText(<?= json_encode($payload) ?>,this)">📋 Copy Message</button>
</div>
<?php endif; ?>
</div>
</div>
</form>
<?php require_once 'php/includes/footer.php'; ?>
