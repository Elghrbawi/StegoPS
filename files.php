<?php
$pageTitle  = 'My Files';
$activePage = 'files';
require_once 'php/includes/header.php';
$isGuest = $auth->isGuest();
$files = [];
$totalSize = 0;
if (!$isGuest) {
$files = $db->fetchAll(
"SELECT f.*, o.op_id, o.operation_type, o.status as op_status
FROM media_files f
LEFT JOIN stego_operations o ON (o.carrier_file_id = f.file_id OR o.output_file_id = f.file_id)
WHERE f.user_id = ?
ORDER BY f.created_at DESC",
[$user['user_id']]
);
$totalSize = $user['storage_used'] ?? 0;
}
$usedMB  = round($totalSize / 1024 / 1024, 2);
$limitMB = 200;
$pct     = min(100, round($usedMB / $limitMB * 100, 1));
function fmtSize(int $bytes): string {
if ($bytes < 1024) return $bytes . ' B';
if ($bytes < 1048576) return round($bytes/1024,1) . ' KB';
return round($bytes/1048576,2) . ' MB';
}
function fmtDate(string $dt): string { return date('d M Y, H:i', strtotime($dt)); }
?>
<div class="page-header">
<div>
<div class="page-title">My Files</div>
<div class="page-sub">// UPLOADED CARRIERS &amp; STEGO OUTPUTS</div>
</div>
<a href="<?= SITE_URL ?>/embed.php" class="btn btn-primary btn-lg">⬇ New Upload</a>
</div>
<?php if($isGuest): ?>
<div class="guest-banner" style="margin-bottom:18px">
<div class="guest-banner-title">👁 GUEST MODE</div>
<div class="guest-banner-sub">Files uploaded in guest mode are stored temporarily. Create a free account to keep your files permanently.</div>
<button class="btn btn-primary btn-sm" onclick="document.getElementById('upgrade-modal').style.display='flex'" style="margin-top:6px">⬡ Create Free Account</button>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:16px">
<div class="cap-header">
<span class="cap-label">Storage Used</span>
<span class="cap-val"><?= $usedMB ?> MB / <?= $limitMB ?> MB (<?= $pct ?>%)</span>
</div>
<div class="cap-bar"><div class="cap-fill" style="width:<?= $pct ?>%;background:<?= $pct>85?'var(--danger)':($pct>60?'var(--warning)':'') ?>"></div></div>
</div>
<?php endif; ?>
<?php if(empty($files)): ?>
<div class="card" style="text-align:center;padding:50px 20px">
<div style="font-size:40px;margin-bottom:14px">◈</div>
<div style="font-family:'Space Mono',monospace;font-size:14px;color:var(--text-dim);margin-bottom:8px">No files yet</div>
<div style="font-size:12px;color:var(--text-muted);margin-bottom:20px">Uploaded carriers and stego outputs will appear here</div>
<a href="<?= SITE_URL ?>/embed.php" class="btn btn-primary">⬇ Upload Your First File</a>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:14px">
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px">
<div class="card-title" style="margin-bottom:0">All Files — <?= count($files) ?> total</div>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<button class="btn btn-ghost btn-sm filter-btn active" data-filter="all" onclick="filterFiles('all',this)">All</button>
<button class="btn btn-ghost btn-sm filter-btn" data-filter="image" onclick="filterFiles('image',this)">🖼 Images</button>
<button class="btn btn-ghost btn-sm filter-btn" data-filter="audio" onclick="filterFiles('audio',this)">🎵 Audio</button>
<button class="btn btn-ghost btn-sm filter-btn" data-filter="video" onclick="filterFiles('video',this)">🎬 Video</button>
<button class="btn btn-ghost btn-sm filter-btn" data-filter="stego" onclick="filterFiles('stego',this)">⬡ Stego Output</button>
</div>
</div>
<div style="overflow-x:auto">
<table class="data-table" id="files-table">
<thead>
<tr>
<th style="width:32px"></th>
<th>Filename</th>
<th>Type</th>
<th>Size</th>
<th>Uploaded</th>
<th>Linked Op</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($files as $f):
$isStego = !empty($f['is_processed']);
$icon = match($f['file_type']){'image'=>'🖼','audio'=>'🎵','video'=>'🎬',default=>'◈'};
$filterClass = $isStego ? 'stego' : $f['file_type'];
?>
<tr class="file-row" data-type="<?= $filterClass ?>">
<td style="font-size:18px;text-align:center"><?= $icon ?></td>
<td>
<div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text);word-break:break-all;max-width:220px"><?= htmlspecialchars($f['original_filename']) ?></div>
<?php if($isStego): ?><div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--accent);margin-top:2px;letter-spacing:1px">STEGO OUTPUT</div><?php endif; ?>
</td>
<td>
<span class="badge badge-<?= $f['file_type'] ?>"><?= strtoupper($f['file_type']) ?></span>
</td>
<td style="font-family:'Space Mono',monospace;font-size:11px;white-space:nowrap"><?= fmtSize((int)$f['file_size_bytes']) ?></td>
<td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);white-space:nowrap"><?= fmtDate($f['created_at']) ?></td>
<td>
<?php if($f['op_id']): ?>
<span class="badge badge-<?= $f['op_status']==='completed'?'success':($f['op_status']==='failed'?'failed':'pending') ?>" style="font-size:9px"><?= strtoupper($f['operation_type']??'') ?> #<?= $f['op_id'] ?></span>
<?php else: ?>
<span style="color:var(--text-muted);font-size:11px">—</span>
<?php endif; ?>
</td>
<td>
<div style="display:flex;gap:6px;flex-wrap:wrap">
<a href="<?= SITE_URL ?>/php/api/download.php?id=<?= $f['file_id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:10px">⬇ Download</a>
<?php if(!$isStego): ?>
<a href="<?= SITE_URL ?>/embed.php?carrier=<?= $f['file_id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:10px">⬡ Embed</a>
<?php else: ?>
<a href="<?= SITE_URL ?>/share.php?file=<?= $f['file_id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:10px">⬡ Share</a>
<?php endif; ?>
<button class="btn btn-danger btn-sm" style="padding:4px 10px;font-size:10px" onclick="confirmDelete(<?= $f['file_id'] ?>, '<?= addslashes(htmlspecialchars($f['original_filename'])) ?>')">✕</button>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<div id="empty-filter" style="display:none;text-align:center;padding:30px;color:var(--text-muted);font-family:'Space Mono',monospace;font-size:11px">No files match this filter</div>
<?php endif; ?>
<div id="delete-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
<div class="modal-card animate-in" style="max-width:360px;text-align:center">
<div style="font-size:32px;margin-bottom:10px">⚠</div>
<div class="modal-title" style="color:var(--danger)">Delete File?</div>
<div class="modal-sub" id="delete-modal-name" style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text-dim);word-break:break-all"></div>
<div style="font-size:12px;color:var(--text-muted);margin-bottom:20px">This cannot be undone. Any linked share links will break.</div>
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
function filterFiles(type, btn) {
document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
btn.classList.add('active');
const rows = document.querySelectorAll('.file-row');
let visible = 0;
rows.forEach(r=>{
const show = type==='all' || r.dataset.type===type;
r.style.display = show ? '' : 'none';
if(show) visible++;
});
document.getElementById('empty-filter').style.display = visible===0 ? 'block' : 'none';
}
let deleteId = 0;
function confirmDelete(id, name) {
deleteId = id;
document.getElementById('delete-modal-name').textContent = name;
document.getElementById('delete-modal').style.display = 'flex';
}
function executeDelete() {
const btn = document.getElementById('confirm-delete-btn');
setLoading(btn, true);
fetch('<?= SITE_URL ?>/php/api/delete_file.php', {
method:'POST',
body: new URLSearchParams({file_id: deleteId})
}).then(r=>r.json()).then(d=>{
if (d.success) { location.reload(); }
else { setLoading(btn,false); alert(d.message || 'Failed to delete.'); }
}).catch(()=>setLoading(btn,false));
}
</script>
<?php require_once 'php/includes/footer.php'; ?>
