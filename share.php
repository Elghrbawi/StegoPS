<?php
// File: share.php
$pageTitle  = 'Secure Sharing';
$activePage = 'share';
require_once 'php/includes/header.php';
require_once 'php/includes/stego_handler.php';

$handler = new StegoHandler();
$db      = Database::getInstance();
$error = $success = $shareUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opId         = (int)($_POST['op_id'] ?? 0);
    $password     = !empty($_POST['password']) ? $_POST['password'] : null;
    $maxDownloads = (int)($_POST['max_downloads'] ?? 0);
    $expiresAt    = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $chainHint    = $_POST['chain_hint'] ?? '';

    $res = $handler->createShareLink($opId, $user['user_id'], $password, $maxDownloads, $expiresAt, $chainHint);
    $res['success'] ? ($success = 'Share link created!') && ($shareUrl = $res['url']) : ($error = $res['message']);
}

// Get user's completed operations for dropdown
$ops = $db->fetchAll(
    "SELECT o.op_id, f.original_filename, o.created_at
     FROM stego_operations o
     JOIN media_files f ON o.carrier_file_id = f.file_id
     WHERE o.user_id = ? AND o.status = 'completed'
     ORDER BY o.created_at DESC LIMIT 50",
    [$user['user_id']]
);

// Get active share links
$shares = $db->fetchAll(
    "SELECT sf.*, f.original_filename
     FROM shared_files sf
     JOIN stego_operations o ON sf.op_id = o.op_id
     JOIN media_files f ON o.carrier_file_id = f.file_id
     WHERE o.user_id = ? AND sf.is_active = 1
     ORDER BY sf.created_at DESC",
    [$user['user_id']]
);

// Pre-select op from embed redirect
$preOp = (int)($_GET['op'] ?? 0);
?>

<div class="page-header">
  <div>
    <div class="page-title">Secure Sharing</div>
    <div class="page-sub">// GENERATE ENCRYPTED SHARE LINKS</div>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="grid-2">
  <!-- Create Link -->
  <div style="display:flex;flex-direction:column;gap:20px">
    <form method="POST">
      <div class="card">
        <div class="card-title">Create Share Link</div>
        <div class="input-group">
          <label class="input-label">Select Completed Operation</label>
          <select name="op_id" class="select-input" required>
            <option value="">— Choose an operation —</option>
            <?php foreach ($ops as $op): ?>
            <option value="<?= $op['op_id'] ?>" <?= $preOp===$op['op_id']?'selected':'' ?>>
              #<?= $op['op_id'] ?> — <?= htmlspecialchars(substr($op['original_filename'],0,30)) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="share-grid">
          <div class="share-option">
            <div class="share-option-label">Password (optional)</div>
            <input type="password" name="password" class="input" placeholder="Link password">
          </div>
          <div class="share-option">
            <div class="share-option-label">Expiration</div>
            <select name="expires_at" class="select-input">
              <option value="">Never expires</option>
              <option value="<?= date('Y-m-d H:i:s', strtotime('+1 hour')) ?>">1 hour</option>
              <option value="<?= date('Y-m-d H:i:s', strtotime('+24 hours')) ?>" selected>24 hours</option>
              <option value="<?= date('Y-m-d H:i:s', strtotime('+7 days')) ?>">7 days</option>
              <option value="<?= date('Y-m-d H:i:s', strtotime('+30 days')) ?>">30 days</option>
            </select>
          </div>
          <div class="share-option">
            <div class="share-option-label">Max Downloads (0=unlimited)</div>
            <input type="number" name="max_downloads" class="input" value="5" min="0">
          </div>
          <div class="share-option">
            <div class="share-option-label">Chain Hint</div>
            <input type="text" name="chain_hint" class="input" placeholder="Optional hint for recipient">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:16px">⬡ Generate Share Link</button>
      </div>
    </form>

    <?php if ($shareUrl): ?>
    <div class="card card-accent">
      <div class="card-title" style="color:var(--accent)">✓ Link Generated</div>
      <div class="share-link-box">
        <div class="share-link-url"><?= htmlspecialchars($shareUrl) ?></div>
        <button class="btn btn-ghost" onclick="copyText('<?= htmlspecialchars($shareUrl) ?>',this)" style="padding:8px 14px;white-space:nowrap">📋 Copy</button>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Active Links -->
  <div class="card">
    <div class="card-title">Active Share Links</div>
    <?php if (empty($shares)): ?>
      <p style="color:var(--text-muted);font-family:'Space Mono',monospace;font-size:11px;text-align:center;padding:20px">No active links</p>
    <?php else: ?>
    <table class="data-table">
      <thead><tr><th>Token</th><th>File</th><th>Downloads</th><th>Expires</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($shares as $s): ?>
        <tr>
          <td style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--accent)"><?= substr($s['share_token'],0,12) ?>...</td>
          <td style="font-size:11px"><?= htmlspecialchars(substr($s['original_filename'],0,20)) ?></td>
          <td style="font-family:'Space Mono',monospace;font-size:11px"><?= $s['download_count'] ?>/<?= $s['max_downloads']?:'∞' ?></td>
          <td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)"><?= $s['expires_at'] ? date('d M', strtotime($s['expires_at'])) : 'Never' ?></td>
          <td>
            <form method="POST" action="<?= SITE_URL ?>/php/api/revoke_share.php" style="display:inline">
              <input type="hidden" name="share_id" value="<?= $s['share_id'] ?>">
              <button type="submit" class="btn btn-danger" style="padding:4px 8px;font-size:10px">Revoke</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'php/includes/footer.php'; ?>
