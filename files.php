<?php
$pageTitle='My Files';
$activePage='files';
require_once 'php/includes/header.php';
if (!$auth->isLoggedIn()) { header('Location: '.SITE_URL.'/401.php'); exit; }
$isGuest=$auth->isGuest();
$files=[];
$totalSize=0;
if (!$isGuest) {
$files=$db->fetchAll(
"SELECT f.file_id,f.original_filename,f.stored_filename,f.file_type,f.mime_type,f.file_size_bytes,f.is_processed,f.uploaded_at,o.op_id,o.operation_type,o.status as op_status
FROM media_files f
LEFT JOIN stego_operations o ON (o.carrier_file_id=f.file_id OR o.output_file_id=f.file_id)
WHERE f.user_id=?
ORDER BY f.uploaded_at DESC",
[$user['user_id']]
);
$totalSize=$user['storage_used']??0;
}
function fmtSize(int $b):string{if($b<1024)return $b.' B';if($b<1048576)return round($b/1024,1).' KB';return round($b/1048576,2).' MB';}
function fmtAmPm2(string $dt):string{return date('d M Y g:i A',strtotime($dt));}
$usedMB=round($totalSize/1024/1024,2);
$pct=min(100,round($usedMB/200*100,1));
?>
<div class="page-header">
<div>
<div class="page-title">My Files</div>
<div class="page-sub">// CARRIERS · STEGO OUTPUTS · MANAGEMENT</div>
</div>
<a href="<?= SITE_URL ?>/embed.php" class="btn btn-primary btn-lg">⬇ New Upload</a>
</div>
<?php if($isGuest): ?>
<div class="guest-banner" style="margin-bottom:18px">
<div class="guest-banner-title">👁 GUEST MODE</div>
<div class="guest-banner-sub">Files uploaded in guest mode are temporary. Create a free account to keep files permanently.</div>
<button class="btn btn-primary btn-sm" onclick="document.getElementById('upgrade-modal').style.display='flex'" style="margin-top:6px">⬡ Create Free Account</button>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:16px;padding:16px 20px">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:6px">
<span class="cap-label">STORAGE</span>
<span class="cap-val"><?= $usedMB ?> MB / 200 MB <span style="color:var(--text-muted);"><?= $pct ?>%</span></span>
</div>
<div class="cap-bar"><div class="cap-fill" style="width:<?= $pct ?>%;<?= $pct>85?'background:var(--danger)':($pct>60?'background:var(--warning)':'') ?>"></div></div>
</div>
<?php endif; ?>
<?php if(empty($files)): ?>
<div class="empty-state" style="flex:1;min-height:400px">
<div class="empty-icon" style="font-size:48px">◈</div>
<div class="empty-title">No files yet</div>
<div class="empty-sub">Carrier files and stego outputs will appear here after you embed something</div>
<a href="<?= SITE_URL ?>/embed.php" class="btn btn-primary" style="margin-top:20px">⬇ Start Embedding</a>
</div>
<?php else: ?>
<div class="card full-height-card" style="flex:1">
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px">
<div class="card-title" style="margin:0">All Files <span style="color:var(--text-dim);font-weight:400"><?= count($files) ?> total</span></div>
<div style="display:flex;gap:6px;flex-wrap:wrap">
<button class="btn btn-ghost btn-sm filter-btn active" onclick="filterFiles('all',this)">All</button>
<button class="btn btn-ghost btn-sm filter-btn" onclick="filterFiles('image',this)">🖼 Images</button>
<button class="btn btn-ghost btn-sm filter-btn" onclick="filterFiles('audio',this)">🎵 Audio</button>
<button class="btn btn-ghost btn-sm filter-btn" onclick="filterFiles('video',this)">🎬 Video</button>
<button class="btn btn-ghost btn-sm filter-btn" onclick="filterFiles('stego',this)">⬡ Stego</button>
</div>
</div>
<div style="overflow-x:auto;flex:1">
<table class="data-table" id="files-table">
<thead>
<tr>
<th style="width:34px"></th>
<th>Filename</th>
<th>Type</th>
<th>Size</th>
<th>Uploaded</th>
<th>Operation</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($files as $f):
$isStego=!empty($f['is_processed']);
$icon=match($f['file_type']??''){'image'=>'🖼','audio'=>'🎵','video'=>'🎬',default=>'◈'};
$fc=$isStego?'stego':($f['file_type']??'');
?>
<tr class="file-row" data-type="<?= $fc ?>">
<td style="font-size:18px;text-align:center"><?= $icon ?></td>
<td style="min-width:180px">
<div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text);word-break:break-all;max-width:240px"><?= htmlspecialchars($f['original_filename']) ?></div>
<?php if($isStego): ?><div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--accent);margin-top:2px;letter-spacing:1px">STEGO OUTPUT</div><?php endif; ?>
</td>
<td><span class="badge badge-<?= $f['file_type']??'' ?>"><?= strtoupper($f['file_type']??'') ?></span></td>
<td style="font-family:'Space Mono',monospace;font-size:11px;white-space:nowrap;color:var(--text-dim)"><?= fmtSize((int)($f['file_size_bytes']??0)) ?></td>
<td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);white-space:nowrap"><?= fmtAmPm2($f['uploaded_at']??date('Y-m-d H:i:s')) ?></td>
<td>
<?php if(!empty($f['op_id'])): ?>
<span class="badge badge-<?= ($f['op_status']??'')==='completed'?'success':(($f['op_status']??'')==='failed'?'failed':'pending') ?>"><?= strtoupper($f['operation_type']??'') ?> #<?= $f['op_id'] ?></span>
<?php else: ?><span style="color:var(--text-muted);font-size:11px">—</span><?php endif; ?>
</td>
<td>
<div style="display:flex;gap:5px;flex-wrap:wrap">
<a href="<?= SITE_URL ?>/php/api/download.php?id=<?= $f['file_id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:10px">⬇</a>
<?php if($isStego): ?>
<a href="<?= SITE_URL ?>/share.php?file=<?= $f['file_id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:10px">⬡</a>
<?php else: ?>
<a href="<?= SITE_URL ?>/embed.php?carrier=<?= $f['file_id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:10px">⬇</a>
<?php endif; ?>
<button class="btn btn-danger btn-sm" style="padding:4px 10px;font-size:10px" onclick="confirmDelete(<?= $f['file_id'] ?>,'<?= addslashes(htmlspecialchars($f['original_filename'])) ?>')">✕</button>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<div id="empty-filter" class="empty-state" style="display:none;min-height:200px">
<div class="empty-icon">◎</div>
<div class="empty-title">No files match this filter</div>
</div>
<?php endif; ?>
<div id="delete-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
<div class="modal-card animate-in" style="max-width:360px;text-align:center">
<div style="font-size:32px;margin-bottom:10px">⚠</div>
<div class="modal-title" style="color:var(--danger)">Delete File?</div>
<div class="modal-sub" id="delete-modal-name" style="font-family:'JetBrains Mono',monospace;font-size:12px;word-break:break-all"></div>
<div style="font-size:12px;color:var(--text-muted);margin-bottom:20px">Cannot be undone. Share links will break.</div>
<div style="display:flex;gap:10px;justify-content:center">
<button class="btn btn-danger" id="confirm-delete-btn" onclick="executeDelete()">
<span class="btn-text">✕ Delete</span>
<span class="btn-loader" style="display:none">◌</span>
</button>
<button class="btn btn-ghost" onclick="document.getElementById('delete-modal').style.display='none'">Cancel</button>
</div>
</div>
</div>
<script>
function filterFiles(type,btn){
document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
btn.classList.add('active');
const rows=document.querySelectorAll('.file-row');
let v=0;
rows.forEach(r=>{const show=type==='all'||r.dataset.type===type;r.style.display=show?'':'none';if(show)v++;});
const ef=document.getElementById('empty-filter');
if(ef)ef.style.display=v===0?'flex':'none';
}
let deleteId=0;
function confirmDelete(id,name){
deleteId=id;
document.getElementById('delete-modal-name').textContent=name;
document.getElementById('delete-modal').style.display='flex';
}
function executeDelete(){
const btn=document.getElementById('confirm-delete-btn');
setLoading(btn,true);
fetch('<?= SITE_URL ?>/php/api/delete_file.php',{method:'POST',body:new URLSearchParams({file_id:deleteId})})
.then(r=>r.json()).then(d=>{if(d.success)location.reload();else{setLoading(btn,false);alert(d.message||'Failed.');}})
.catch(()=>setLoading(btn,false));
}
</script>
<?php require_once 'php/includes/footer.php'; ?>
