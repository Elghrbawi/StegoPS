<?php
$pageTitle='Chain Builder';
$activePage='chains';
require_once 'php/includes/header.php';
require_once 'php/includes/chain_manager.php';
$manager=new ChainManager();
$algos=$manager->getAllAlgorithms();
$chains=[];
$chainsWithSteps=[];
$isGuest=$auth->isGuest();
if(!$isGuest){
$chainsWithSteps=$manager->getUserChainsWithSteps($user['user_id']);
$chains=$chainsWithSteps;
}
$error=$success='';
if(!$isGuest && $_SERVER['REQUEST_METHOD']==='POST'){
if(isset($_POST['action'])){
if($_POST['action']==='save'){
$steps=json_decode($_POST['chain_data']??'[]',true)?:[];
$result=$manager->saveChain($user['user_id'],trim($_POST['chain_name']??''),$steps,trim($_POST['chain_desc']??''));
$result['success']?($success=$result['message']):($error=$result['message']);
}elseif($_POST['action']==='delete'){
$manager->deleteChain((int)$_POST['chain_id'],$user['user_id']);
$success='Chain deleted.';
}
}
header('Location: '.SITE_URL.'/chains.php'.($success?'?msg='.urlencode($success):($error?'?err='.urlencode($error):'')));
exit;
}
if(!$isGuest){
$chainsWithSteps=$manager->getUserChainsWithSteps($user['user_id']);
$chains=$chainsWithSteps;
}
if(isset($_GET['msg']))$success=htmlspecialchars($_GET['msg']);
if(isset($_GET['err']))$error=htmlspecialchars($_GET['err']);
$chainKeysMap=[];
foreach($chainsWithSteps as $c){
$stepsArr=array_map(fn($s)=>[$s['algo_slug'],$s['algo_name'],$s['category'],'',''],$c['steps']);
$chainKeysMap[$c['chain_id']]=base64_encode(json_encode($stepsArr));
}
?>
<div class="page-header">
<div>
<div class="page-title">Chain Builder</div>
<div class="page-sub">// CLICK ALGORITHMS TO BUILD YOUR ENCRYPTION PIPELINE</div>
</div>
</div>
<form method="POST" id="chain-form">
<input type="hidden" name="action" value="save">
<input type="hidden" name="chain_data" id="chain-data" value="[]">
<div class="chain-builder">
<div class="algo-palette">
<div class="algo-search-wrap">
<input type="text" class="input" id="algo-search" placeholder="&#128269; Search algorithms..." oninput="filterAlgos(this.value)" style="font-size:11px;padding:8px 10px;margin-bottom:10px;border-radius:6px;font-family:'Space Mono',monospace">
</div>
<?php foreach($algos as $category=>$algoList): ?>
<div class="algo-cat-group" data-cat="<?= $category ?>">
<div class="algo-cat-title"><?= ucfirst($category) ?></div>
<?php foreach($algoList as $algo): ?>
<div class="algo-chip <?= $category ?><?= $algo['algo_slug']==='netrunner' ? ' netrunner-chip' : '' ?>" data-name="<?= strtolower(htmlspecialchars($algo['algo_name'])) ?>" onclick="addToChain('<?= addslashes($algo['algo_name']) ?>','<?= $category ?>','<?= $algo['algo_slug'] ?>')">
<span><?= htmlspecialchars($algo['algo_name']) ?></span>
<span style="opacity:.5;font-size:14px">+</span>
</div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
<div id="algo-no-results" style="display:none;font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);text-align:center;padding:16px">No algorithms found</div>
</div>
<div class="pipeline-wrap">
<div class="pipeline-header">
<div style="font-family:'Space Mono',monospace;font-size:11px;letter-spacing:1px;color:var(--text-dim)">PIPELINE &mdash; <span id="chain-count">0</span> LAYERS</div>
<div class="strength-bar-wrap">
<span style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)">STRENGTH</span>
<div class="strength-bar"><div class="strength-fill" id="strength-fill" style="width:0%"></div></div>
<span style="font-family:'Space Mono',monospace;font-size:12px;color:var(--accent);font-weight:700" id="strength-val">0%</span>
</div>
<div style="display:flex;gap:6px">
<button type="button" class="btn btn-ghost" onclick="clearChain()" style="padding:5px 10px;font-size:11px">&#10005; Clear</button>
<button type="button" class="btn btn-ghost" onclick="copyChainKey()" style="padding:5px 10px;font-size:11px" title="Copy shareable chain key">&#11041; Key</button>
</div>
</div>
<div class="chain-steps-list" id="chain-steps">
<div class="chain-empty chainer"><span style="font-size:32px;filter:grayscale(1);">&#9939;</span><span>No operations yet</span><span style="font-size:10px;color:var(--text-muted)">Start your first embed &#8594;</span></div>
</div>
<div id="chain-share-row" class="chain-share-row" style="display:none">
<span style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);letter-spacing:1px;white-space:nowrap">CHAIN KEY</span>
<span class="chain-share-val" id="chain-share-key-val">&mdash;</span>
<button type="button" class="btn btn-ghost" onclick="copyChainKey()" style="padding:3px 8px;font-size:10px;flex-shrink:0">Copy</button>
</div>
<div class="divider"></div>
<div class="grid-2" style="margin-bottom:14px">
<div class="input-group" style="margin-bottom:0">
<label class="input-label">Chain Name</label>
<input type="text" name="chain_name" class="input" placeholder="e.g. MyChain-v1" id="chain-name-input">
</div>
<div class="input-group" style="margin-bottom:0">
<label class="input-label">Description <span class="optional-tag">(optional)</span></label>
<input type="text" name="chain_desc" class="input" placeholder="What is this chain for?">
</div>
</div>
<button type="button" class="btn btn-primary btn-full" onclick="doSaveChain()" id="save-chain-btn">
<span class="btn-text">&#11041; Save Chain</span>
<span class="btn-loader" style="display:none">&#9676;</span>
</button>
</div>
</div>
</form>
<div class="divider"></div>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
<div class="card-title" style="margin-bottom:0">Saved Chains</div>
</div>
<div id="chains-list-wrap">
<?php if(!$isGuest && !empty($chains)): ?>
<div class="card" style="margin-top:0">
<div class="chains-saved-wrap">
<table class="data-table">
<thead><tr><th>Chain Name</th><th>Layers</th><th>Uses</th><th>Created</th><th>Key</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($chains as $c): ?>
<tr>
<td style="font-weight:600;color:var(--text)"><?= htmlspecialchars($c['chain_name']) ?></td>
<td><span class="badge badge-success"><?= $c['layer_count'] ?> layers</span></td>
<td style="font-family:'Space Mono',monospace;font-size:11px"><?= $c['use_count']??0 ?></td>
<td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)"><?= date('d M Y',strtotime($c['created_at'])) ?></td>
<td style="max-width:100px">
<button class="btn btn-ghost" onclick="copyText('<?= addslashes($chainKeysMap[$c['chain_id']]??'') ?>',this)" style="padding:3px 8px;font-size:9px">&#11041; Copy</button>
</td>
<td>
<button class="btn btn-danger" onclick="openDeleteChainModal(<?= $c['chain_id'] ?>,'<?= addslashes(htmlspecialchars($c['chain_name'])) ?>')" style="padding:4px 10px;font-size:10px">Delete</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php elseif(!$isGuest): ?>
<div class="chain-empty" style="border-radius:12px">
<span style="font-size:32px;filter:grayscale(1)">&#9939;</span>
<span>No saved chains yet</span>
<span style="font-size:10px;color:var(--text-muted)">Build your first pipeline above &#8593;</span>
</div>
<?php else: ?>
<div id="guest-chains-list"></div>
<?php endif; ?>
</div>
<div id="delete-chain-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
<div class="modal-card animate-in" style="max-width:360px;text-align:center">
<div style="font-size:32px;margin-bottom:10px">&#9939;</div>
<div class="modal-title" style="color:var(--danger)">Delete Chain?</div>
<div class="modal-sub" id="delete-chain-name" style="font-family:'JetBrains Mono',monospace;font-size:12px;margin-bottom:6px"></div>
<div style="font-size:12px;color:var(--text-muted);margin-bottom:20px">This chain will be permanently removed.</div>
<div style="display:flex;gap:10px;justify-content:center">
<form method="POST" id="delete-chain-form">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="chain_id" id="delete-chain-id" value="">
<button type="submit" class="btn btn-danger">&#10005; Delete</button>
</form>
<button class="btn btn-ghost" onclick="document.getElementById('delete-chain-modal').style.display='none'">Cancel</button>
</div>
</div>
</div>
<script>
const IS_GUEST = <?= $isGuest ? 'true' : 'false' ?>;
function filterAlgos(q) {
q = q.toLowerCase().trim();
let anyVisible = false;
document.querySelectorAll('.algo-cat-group').forEach(group => {
let groupVisible = false;
group.querySelectorAll('.algo-chip').forEach(chip => {
const show = !q || (chip.dataset.name||'').includes(q);
chip.style.display = show ? '' : 'none';
if (show) groupVisible = true;
});
group.style.display = groupVisible ? '' : 'none';
if (groupVisible) anyVisible = true;
});
document.getElementById('algo-no-results').style.display = anyVisible ? 'none' : 'block';
}
document.addEventListener('DOMContentLoaded', () => {
renderChain();
if(IS_GUEST) loadGuestChains();
const row = document.getElementById('chain-share-row');
if(row) row.style.display = 'none';
});
function openDeleteChainModal(id, name) {
document.getElementById('delete-chain-id').value = id;
document.getElementById('delete-chain-name').textContent = name;
document.getElementById('delete-chain-modal').style.display = 'flex';
}
function doSaveChain() {
if(chain.length === 0) { showToast('Add at least one algorithm first'); return; }
if(IS_GUEST) {
const name = document.getElementById('chain-name-input')?.value.trim();
if(!name) { showToast('Enter a chain name'); return; }
saveGuestChain(name);
} else {
document.getElementById('chain-form').submit();
}
}
function updateChainShareKey() {
const el = document.getElementById('chain-share-key-val');
const row = document.getElementById('chain-share-row');
if(!el) return;
if(chain.length === 0) { if(row) row.style.display = 'none'; el.textContent = '\u2014'; return; }
if(row) row.style.display = 'flex';
el.textContent = generateChainKey();
}
function loadGuestChains() {
let gc = [];
try { gc = JSON.parse(localStorage.getItem('stego_guest_chains') || '[]'); } catch(e) {}
const wrap = document.getElementById('guest-chains-list');
if(!wrap) return;
if(gc.length === 0) {
wrap.innerHTML = '<div class="chain-empty" style="border-radius:12px"><span style="font-size:32px;filter:grayscale(1)">&#9939;</span><span>No saved chains yet</span><span style="font-size:10px;color:var(--text-muted)">Build your first pipeline above &#8593;</span></div>';
return;
}
wrap.innerHTML = '<div class="card" style="margin-top:0"><div class="chains-saved-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Layers</th><th>Key</th><th>Action</th></tr></thead><tbody id="guest-chains-tbody"></tbody></table></div></div>';
const tbody = document.getElementById('guest-chains-tbody');
gc.forEach((c, i) => {
const key = generateKeyFromSteps(c.steps);
const tr = document.createElement('tr');
tr.innerHTML = `<td style="font-weight:600;color:var(--text)">${c.name}</td><td><span class="badge badge-success">${c.steps.length} layers</span></td><td><button class="btn btn-ghost" onclick="copyText('${key}',this)" style="padding:3px 8px;font-size:9px">&#11041; Copy</button></td><td><button class="btn btn-danger" onclick="deleteGuestChain(${i})" style="padding:4px 10px;font-size:10px">Delete</button></td>`;
tbody.appendChild(tr);
});
}
function saveGuestChain(name) {
let gc = [];
try { gc = JSON.parse(localStorage.getItem('stego_guest_chains') || '[]'); } catch(e) {}
gc.unshift({ name, steps: JSON.parse(JSON.stringify(chain)), created: Date.now() });
try { localStorage.setItem('stego_guest_chains', JSON.stringify(gc)); } catch(e) {}
showToast('Chain saved locally!');
clearChain();
document.getElementById('chain-name-input').value = '';
loadGuestChains();
}
function deleteGuestChain(i) {
document.getElementById('delete-chain-modal').style.display = 'none';
let gc = [];
try { gc = JSON.parse(localStorage.getItem('stego_guest_chains') || '[]'); } catch(e) {}
gc.splice(i, 1);
try { localStorage.setItem('stego_guest_chains', JSON.stringify(gc)); } catch(e) {}
loadGuestChains();
}
function generateKeyFromSteps(steps) {
return btoa(JSON.stringify(steps.map(s => [s.slug, s.name, s.category, s.key || '', s.key2 || ''])));
}
</script>
<?php if($error): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($error) ?>'));</script>
<?php endif; ?>
<?php if($success): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($success) ?>'));</script>
<?php endif; ?>
<?php require_once 'php/includes/footer.php'; ?>