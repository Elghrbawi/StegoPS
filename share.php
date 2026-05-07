<?php
$pageTitle  = 'Secure Sharing';
$activePage = 'share';
require_once 'php/includes/header.php';
require_once 'php/includes/stego_handler.php';
$handler  = new StegoHandler();
$db       = Database::getInstance();
$isGuest  = $auth->isGuest();
$shareToken  = trim($_GET['t'] ?? '');
$error = $success = $shareUrl = '';
$sharedView = null;
$pwRequired = $pwError = false;

/* ── Resolve share token ────────────────────────── */
if ($shareToken) {
    $sharedView = $db->fetchOne(
        "SELECT sf.*,
            o.stego_technique,o.payload_type,o.payload_size,o.created_at AS op_created,
            cf.original_filename AS carrier_name,cf.file_size_bytes AS carrier_size,cf.file_type AS media_type,
            of2.original_filename AS output_name,of2.file_size_bytes AS output_size,of2.file_id AS output_file_id,
            u.username AS shared_by_name
         FROM shared_files sf
         JOIN stego_operations o  ON sf.op_id = o.op_id
         JOIN media_files cf      ON cf.file_id = o.carrier_file_id
         LEFT JOIN media_files of2 ON of2.file_id = o.output_file_id
         JOIN users u             ON sf.shared_by = u.user_id
         WHERE sf.share_token = ? AND sf.is_active = 1",
        [$shareToken]
    );
    if ($sharedView) {
        if ($sharedView['expires_at'] && strtotime($sharedView['expires_at']) < time()) {
            $sharedView = null; $error = 'This share link has expired.';
        } elseif ($sharedView['max_downloads'] > 0 && $sharedView['download_count'] >= $sharedView['max_downloads']) {
            $sharedView = null; $error = 'This share link has reached its download limit.';
        } elseif ($sharedView['password_hash']) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_password'])) {
                if (password_verify($_POST['share_password'], $sharedView['password_hash'])) {
                    $_SESSION['share_unlocked_'.$shareToken] = true;
                } else { $pwError = true; $sharedView = null; }
            }
            if (!isset($_SESSION['share_unlocked_'.$shareToken]) && !$pwError) {
                $pwRequired = true; $sharedView = null;
            }
        }
    } elseif (!$error) { $error = 'Share link not found or no longer active.'; }

    // Direct download
    if ($sharedView && ($_GET['dl'] ?? '') === '1') {
        $frow = $db->fetchOne("SELECT storage_path,original_filename,mime_type FROM media_files WHERE file_id=?", [$sharedView['output_file_id'] ?? 0]);
        if ($frow) {
            $rp = UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $frow['storage_path']);
            if (file_exists($rp)) {
                $db->query("UPDATE shared_files SET download_count=download_count+1 WHERE share_token=?", [$shareToken]);
                header('Content-Type: '.($frow['mime_type'] ?? 'application/octet-stream'));
                header('Content-Disposition: attachment; filename="'.addslashes($frow['original_filename'] ?? 'stego_output').'"');
                header('Content-Length: '.filesize($rp));
                header('Cache-Control: no-store');
                ob_clean(); readfile($rp); exit;
            }
        }
        $error = 'Output file not found on server.';
    }
}

/* ── Create share (logged-in POST) ─────────────── */
if (!$shareToken && !$isGuest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = $handler->createShareLink(
        (int)($_POST['op_id']         ?? 0), $user['user_id'],
        !empty($_POST['password'])    ? $_POST['password']   : null,
        (int)($_POST['max_downloads'] ?? 0),
        !empty($_POST['expires_at'])  ? $_POST['expires_at'] : null,
        $_POST['chain_hint']          ?? ''
    );
    $res['success'] ? [$success, $shareUrl] = ['Share link created!', $res['url']] : ($error = $res['message']);
}

/* ── Data for list view ─────────────────────────── */
$ops = $shares = []; $preOp = 0; $opDetails = null;
if (!$isGuest) {
    $uid    = $user['user_id'];
    $preOp  = (int)($_GET['op'] ?? 0);
    $ops    = $db->fetchAll("SELECT o.op_id,f.original_filename,o.created_at,o.stego_technique FROM stego_operations o JOIN media_files f ON o.carrier_file_id=f.file_id WHERE o.user_id=? AND o.status='completed' ORDER BY o.created_at DESC LIMIT 50", [$uid]);
    $shares = $db->fetchAll("SELECT sf.*,f.original_filename FROM shared_files sf JOIN stego_operations o ON sf.op_id=o.op_id JOIN media_files f ON o.carrier_file_id=f.file_id WHERE o.user_id=? AND sf.is_active=1 ORDER BY sf.created_at DESC", [$uid]);
    if ($preOp > 0) $opDetails = $db->fetchOne("SELECT o.*,cf.original_filename AS before_name,cf.file_size_bytes AS before_size,cf.file_type AS media_type,of2.original_filename AS after_name,of2.file_size_bytes AS after_size FROM stego_operations o JOIN media_files cf ON cf.file_id=o.carrier_file_id LEFT JOIN media_files of2 ON of2.file_id=o.output_file_id WHERE o.op_id=? AND o.user_id=?", [$preOp, $uid]);
}

function ss(int $b): string { if($b<1024)return $b.' B'; if($b<1048576)return round($b/1024,1).' KB'; return round($b/1048576,2).' MB'; }
function sd(string $dt): string { return date('d M Y H:i', strtotime($dt)); }
?>
<link rel="stylesheet" href="<?= SITE_URL ?>/frontend/css/stego-pages.css">

<div class="page-header"><div><div class="page-title">Secure Sharing</div><div class="page-sub">// GENERATE ENCRYPTED SHARE LINKS</div></div></div>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- ── Password gate ──────────────────────────── -->
<?php if ($pwRequired || $pwError): ?>
<div class="pw-form">
  <div class="pw-form-title">🔒 Password Protected</div>
  <div class="pw-form-sub">This share link requires a password.</div>
  <?php if ($pwError): ?><div class="alert alert-error">Wrong password.</div><?php endif; ?>
  <form method="POST">
    <div class="input-group" style="margin-bottom:10px">
      <div class="input-wrap"><span class="input-icon">🔑</span>
        <input type="password" name="share_password" class="input has-icon" placeholder="Enter share password" autofocus required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-full">Unlock</button>
  </form>
</div>

<!-- ── Shared file view ───────────────────────── -->
<?php elseif ($sharedView): ?>
<div class="share-token-view">
  <div class="share-card">
    <div class="share-card-header">
      <span style="font-size:18px">⬤</span>
      <span class="share-card-title">Shared Stego File</span>
      <span class="share-card-meta">by <?= htmlspecialchars($sharedView['shared_by_name']) ?></span>
    </div>
    <div class="share-card-body">
      <?php $rows = [
        ['Carrier',   htmlspecialchars($sharedView['carrier_name'])],
        ['Output',    htmlspecialchars($sharedView['output_name'] ?? 'stego_output')],
        ['Type',      '<span class="stat-badge">'.strtoupper((string)$sharedView['media_type']).'</span>'],
        ['Technique', '<span class="stat-badge">'.strtoupper(str_replace('_',' ',(string)$sharedView['stego_technique'])).'</span>'],
        ['Payload',   ss((int)$sharedView['payload_size']).' · '.strtoupper((string)$sharedView['payload_type'])],
        ['Carrier In',ss((int)$sharedView['carrier_size'])],
        ['Output',    ss((int)$sharedView['output_size'])],
        ['Created',   sd($sharedView['op_created'])],
        $sharedView['chain_hint']  ? ['Chain Hint', '<span style="color:var(--accent)">'.htmlspecialchars($sharedView['chain_hint']).'</span>'] : null,
        $sharedView['expires_at']  ? ['Expires',    sd($sharedView['expires_at'])] : null,
        ['Downloads', (int)$sharedView['download_count'].($sharedView['max_downloads'] ? ' / '.(int)$sharedView['max_downloads'] : '')],
      ]; foreach(array_filter($rows) as [$l,$v]): ?>
      <div class="stat-row"><span class="stat-label"><?=$l?></span><span class="stat-value"><?=$v?></span></div>
      <?php endforeach; ?>
    </div>
  </div>

  <a href="<?= SITE_URL ?>/share.php?t=<?= urlencode($shareToken) ?>&amp;dl=1" class="share-dl-btn">⬇ Download Stego File</a>

  <div>
    <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Share This Link</div>
    <div class="copy-row">
      <input type="text" class="copy-input" id="surl" value="<?= htmlspecialchars(SITE_URL.'/share.php?t='.$shareToken) ?>" readonly onclick="this.select()">
      <button class="copy-btn" id="scb" onclick="cpUrl('<?= htmlspecialchars(SITE_URL.'/share.php?t='.$shareToken,ENT_QUOTES) ?>','scb')">⬆ Copy</button>
    </div>
  </div>

  <?php if ($isGuest): ?>
  <div class="guest-strip">⚠ Viewing as Guest — register to create and manage your own links.<a href="<?= SITE_URL ?>/register.php">Create Account →</a></div>
  <?php endif; ?>
</div>

<!-- ── No token — main share manager ────────────── -->
<?php elseif (!$shareToken): ?>
<?php if ($isGuest): ?>
<div class="card" style="max-width:520px;margin:0 auto;text-align:center;padding:30px">
  <div style="font-size:28px;margin-bottom:12px">🔄</div>
  <div style="font-family:'Space Mono',monospace;font-size:12px;color:var(--text);margin-bottom:8px">Share Links require an Account</div>
  <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);line-height:1.6;margin-bottom:16px">Guest sessions cannot create share links. Register a free account to generate shareable links with expiry, passwords, and download limits.</div>
  <a href="<?= SITE_URL ?>/register.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px">⬆ Create Free Account</a>
</div>
<?php else: ?>

<!-- Operation details (when redirected from embed) -->
<?php if ($opDetails): ?>
<div class="card" style="margin-bottom:16px;border-color:rgba(0,229,200,.2)">
  <div class="card-title" style="color:var(--accent)">Operation #<?= (int)$opDetails['op_id'] ?> — Analysis</div>
  <div class="grid-2">
    <div>
      <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase">Before / After</div>
      <div style="font-size:12px;color:var(--text)"><?= htmlspecialchars($opDetails['before_name']) ?></div>
      <div style="font-size:10px;color:var(--text-muted);margin:2px 0"><?= ss((int)$opDetails['before_size']) ?> → <?= ss((int)($opDetails['after_size']??0)) ?></div>
      <div style="font-size:12px;color:var(--accent);margin-top:8px"><?= htmlspecialchars($opDetails['after_name']??'pending output') ?></div>
    </div>
    <div>
      <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase">Details</div>
      <?php foreach([['Media',strtoupper((string)$opDetails['media_type'])],['Steg',strtoupper(str_replace('_',' ',(string)$opDetails['stego_technique']))],['Payload',ss((int)$opDetails['payload_size'])],['Status',strtoupper((string)$opDetails['status'])]] as [$l,$v]): ?>
      <div style="font-size:11px;color:var(--text-dim)"><?=$l?>: <?=$v?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- New share URL display -->
<?php if ($shareUrl): ?>
<div class="card" style="margin-bottom:16px;border-color:rgba(0,229,200,.3)">
  <div class="card-title" style="color:var(--accent)">✔ Share Link Created</div>
  <div class="copy-row" style="margin-bottom:10px">
    <input type="text" class="copy-input" id="new-share-url" value="<?= htmlspecialchars($shareUrl) ?>" readonly onclick="this.select()">
    <button class="copy-btn" id="nsub" onclick="cpUrl('<?= htmlspecialchars($shareUrl,ENT_QUOTES) ?>','nsub')">⬆ Copy</button>
  </div>
  <a href="<?= htmlspecialchars($shareUrl) ?>" target="_blank" style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)">Open link →</a>
</div>
<?php endif; ?>

<div class="grid-2">
<!-- Create form -->
<form method="POST">
<div class="card" style="margin-bottom:0">
  <div class="card-title">Create Share Link</div>
  <div class="input-group">
    <label class="input-label">Select Completed Operation</label>
    <select name="op_id" class="select-input" required>
      <option value="">— Choose an operation —</option>
      <?php foreach ($ops as $op): ?>
      <option value="<?= $op['op_id'] ?>" <?= $preOp===$op['op_id']?'selected':'' ?>>#<?= $op['op_id'] ?> — <?= htmlspecialchars(substr($op['original_filename'],0,28)) ?> · <?= strtoupper(str_replace('_',' ',(string)$op['stego_technique'])) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="share-opts-grid">
    <div>
      <div class="input-label">Password (optional)</div>
      <input type="password" name="password" class="input" placeholder="Link password">
    </div>
    <div>
      <div class="input-label">Expiration</div>
      <select name="expires_at" class="select-input">
        <option value="">Never expires</option>
        <option value="<?= date('Y-m-d H:i:s',strtotime('+1 hour')) ?>">1 hour</option>
        <option value="<?= date('Y-m-d H:i:s',strtotime('+24 hours')) ?>" selected>24 hours</option>
        <option value="<?= date('Y-m-d H:i:s',strtotime('+7 days')) ?>">7 days</option>
        <option value="<?= date('Y-m-d H:i:s',strtotime('+30 days')) ?>">30 days</option>
      </select>
    </div>
    <div>
      <div class="input-label">Max Downloads (0=∞)</div>
      <input type="number" name="max_downloads" class="input" value="5" min="0">
    </div>
    <div>
      <div class="input-label">Chain Hint</div>
      <input type="text" name="chain_hint" class="input" placeholder="Hint for recipient">
    </div>
  </div>
  <button type="submit" class="btn btn-primary btn-full" style="margin-top:14px">⬆ Generate Share Link</button>
</div>
</form>

<!-- Active links -->
<div class="card">
  <div class="card-title">Active Share Links</div>
  <?php if (empty($shares)): ?>
  <p style="color:var(--text-muted);font-family:'Space Mono',monospace;font-size:11px;text-align:center;padding:20px">No active links</p>
  <?php else: ?>
  <div id="shares-list">
    <?php foreach ($shares as $s):
      $surl = SITE_URL.'/share.php?t='.$s['share_token']; ?>
    <div class="share-row" id="srow-<?= $s['share_id'] ?>">
      <span class="share-row-token" title="<?= htmlspecialchars($surl) ?>" onclick="cpUrl('<?= htmlspecialchars($surl,ENT_QUOTES) ?>','stok-<?= $s['share_id'] ?>')" id="stok-<?= $s['share_id'] ?>"><?= substr($s['share_token'],0,14) ?>…</span>
      <span class="share-row-file" title="<?= htmlspecialchars($s['original_filename']) ?>"><?= htmlspecialchars(substr($s['original_filename'],0,16)) ?></span>
      <span class="share-row-dl"><?= $s['download_count'] ?>/<?= $s['max_downloads'] ?: '∞' ?></span>
      <span class="share-row-exp"><?= $s['expires_at'] ? date('d M', strtotime($s['expires_at'])) : 'Never' ?></span>
      <div class="share-row-btns">
        <button class="share-mini-btn" id="scb-<?= $s['share_id'] ?>" onclick="cpUrl('<?= htmlspecialchars($surl,ENT_QUOTES) ?>','scb-<?= $s['share_id'] ?>')">⬆ Copy</button>
        <a href="<?= htmlspecialchars($surl) ?>" target="_blank" class="share-mini-btn">→</a>
        <button class="share-mini-btn danger" onclick="revokeShare(<?= $s['share_id'] ?>,'srow-<?= $s['share_id'] ?>')">Revoke</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
const SITE_URL = '<?= SITE_URL ?>';
function cpUrl(url, btnId) {
  navigator.clipboard.writeText(url).then(() => {
    const btn = document.getElementById(btnId); if (!btn) return;
    const orig = btn.textContent;
    btn.textContent = '✓ Copied!'; btn.classList.add('copied');
    setTimeout(() => { btn.textContent = orig; btn.classList.remove('copied'); }, 2200);
  }).catch(() => {
    const i = document.createElement('input'); i.value = url;
    document.body.appendChild(i); i.select(); document.execCommand('copy'); document.body.removeChild(i);
    typeof showToast === 'function' && showToast('Copied!');
  });
}
function revokeShare(id, rowId) {
  if (!confirm('Revoke this share link? Recipients will lose access.')) return;
  const fd = new FormData(); fd.append('share_id', id);
  fetch(`${SITE_URL}/php/api/revoke.php`, {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        document.getElementById(rowId)?.remove();
        typeof showToast === 'function' && showToast('Share link revoked.');
      } else { alert(d.message || 'Failed to revoke.'); }
    })
    .catch(() => window.location.reload());
}
</script>
<?php require_once 'php/includes/footer.php'; ?>