<?php
$pageTitle='Secure Sharing';$activePage='share';
require_once 'php/includes/header.php';
require_once 'php/includes/stego_handler.php';
$handler=new StegoHandler();$db=Database::getInstance();
$auth2=new Auth();$isGuest=$auth2->isGuest();

$shareToken=trim($_GET['t']??'');
$error=$success=$shareUrl='';$sharedView=null;$pwRequired=$pwError=false;

// ── STREAM / DOWNLOAD HANDLER ──────────────────────────────────────────────
// ?dl=1 → download with Content-Disposition: attachment
// ?stream=1 → serve inline (audio/image browser preview)
$wantDl     = ($_GET['dl']     ?? '') === '1';
$wantStream = ($_GET['stream'] ?? '') === '1';

if ($shareToken && ($wantDl || $wantStream)) {
  $sv = $db->fetchOne(
    "SELECT sf.*,o.output_file_id,sf.password_hash AS pw_hash
     FROM shared_files sf JOIN stego_operations o ON sf.op_id=o.op_id
     WHERE sf.share_token=? AND sf.is_active=1", [$shareToken]);
  if ($sv) {
    // Check expiry & download cap without full page render
    $expired = $sv['expires_at'] && strtotime($sv['expires_at']) < time();
    $capped  = $sv['max_downloads'] > 0 && $sv['download_count'] >= $sv['max_downloads'];
    $locked  = $sv['pw_hash'] && !isset($_SESSION['share_unlocked_'.$shareToken]);
    if (!$expired && !$capped && !$locked) {
      $frow = $db->fetchOne(
        "SELECT storage_path,original_filename,mime_type FROM media_files WHERE file_id=?",
        [$sv['output_file_id'] ?? 0]);
      if ($frow) {
        $rp = UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $frow['storage_path']);
        if (file_exists($rp)) {
          if ($wantDl) $db->query("UPDATE shared_files SET download_count=download_count+1 WHERE share_token=?", [$shareToken]);
          $mime = $frow['mime_type'] ?? 'application/octet-stream';
          header('Content-Type: ' . $mime);
          header('Content-Length: ' . filesize($rp));
          header('Cache-Control: no-store');
          if ($wantDl) header('Content-Disposition: attachment; filename="'.addslashes($frow['original_filename']??'stego_output').'"');
          else          header('Content-Disposition: inline; filename="'.addslashes($frow['original_filename']??'stego_output').'"');
          ob_clean(); flush(); readfile($rp); exit;
        }
      }
    }
  }
  header('HTTP/1.1 404 Not Found'); exit('File not available.');
}

// ── SHARE VIEW LOOKUP ────────────────────────────────────────────────────────
if ($shareToken) {
  $sharedView = $db->fetchOne(
    "SELECT sf.*,o.stego_technique,o.payload_type,o.payload_size,o.created_at AS op_created,
     cf.original_filename AS carrier_name,cf.file_size_bytes AS carrier_size,cf.file_type AS media_type,
     of2.original_filename AS output_name,of2.file_size_bytes AS output_size,of2.file_id AS output_file_id,
     of2.mime_type AS output_mime,u.username AS shared_by_name
     FROM shared_files sf
     JOIN stego_operations o  ON sf.op_id=o.op_id
     JOIN media_files cf      ON cf.file_id=o.carrier_file_id
     LEFT JOIN media_files of2 ON of2.file_id=o.output_file_id
     JOIN users u             ON sf.shared_by=u.user_id
     WHERE sf.share_token=? AND sf.is_active=1", [$shareToken]);

  if ($sharedView) {
    if ($sharedView['expires_at'] && strtotime($sharedView['expires_at']) < time()) {
      $sharedView=null; $error='This share link has expired.';
    } elseif ($sharedView['max_downloads'] > 0 && $sharedView['download_count'] >= $sharedView['max_downloads']) {
      $sharedView=null; $error='Download limit reached.';
    } elseif ($sharedView['password_hash']) {
      if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['share_password'])) {
        if (password_verify($_POST['share_password'], $sharedView['password_hash']))
          $_SESSION['share_unlocked_'.$shareToken] = true;
        else { $pwError=true; $sharedView=null; }
      }
      if (!isset($_SESSION['share_unlocked_'.$shareToken]) && !$pwError) {
        $pwRequired=true; $sharedView=null;
      }
    }
  } elseif (!$error) {
    $error='Share link not found or no longer active.';
  }
}

// ── CREATE SHARE (logged-in users) ──────────────────────────────────────────
if (!$shareToken && !$isGuest && $_SERVER['REQUEST_METHOD']==='POST') {
  $res = $handler->createShareLink(
    (int)($_POST['op_id']??0), $auth2->currentUser()['user_id'],
    !empty($_POST['password']) ? $_POST['password'] : null,
    (int)($_POST['max_downloads']??0),
    !empty($_POST['expires_at']) ? $_POST['expires_at'] : null,
    $_POST['chain_hint']??''
  );
  $res['success']
    ? [$success,$shareUrl] = ['Share link created!', $res['url']]
    : ($error = $res['message']);
}

$ops=$shares=[];$preOp=0;$opDetails=null;
if (!$isGuest) {
  $uid=$auth2->currentUser()['user_id']; $preOp=(int)($_GET['op']??0);
  $ops=$db->fetchAll("SELECT o.op_id,f.original_filename,o.created_at,o.stego_technique FROM stego_operations o JOIN media_files f ON o.carrier_file_id=f.file_id WHERE o.user_id=? AND o.status='completed' ORDER BY o.created_at DESC LIMIT 50",[$uid]);
  $shares=$db->fetchAll("SELECT sf.*,f.original_filename FROM shared_files sf JOIN stego_operations o ON sf.op_id=o.op_id JOIN media_files f ON o.carrier_file_id=f.file_id WHERE o.user_id=? AND sf.is_active=1 ORDER BY sf.created_at DESC",[$uid]);
  if($preOp>0)$opDetails=$db->fetchOne("SELECT o.*,cf.original_filename AS before_name,cf.file_size_bytes AS before_size,cf.file_type AS media_type,cf.mime_type AS carrier_mime,of2.original_filename AS after_name,of2.file_size_bytes AS after_size,of2.file_id AS output_file_id,of2.mime_type AS output_mime FROM stego_operations o JOIN media_files cf ON cf.file_id=o.carrier_file_id LEFT JOIN media_files of2 ON of2.file_id=o.output_file_id WHERE o.op_id=? AND o.user_id=?",[$preOp,$uid]);
}

function ss(int $b):string{if($b<1024)return $b.' B';if($b<1048576)return round($b/1024,1).' KB';return round($b/1048576,2).' MB';}
function sd(string $dt):string{return date('d M Y H:i',strtotime($dt));}
function mtype(string $mt):string{return match($mt){'image'=>'&#128444;','audio'=>'&#127925;','video'=>'&#127916;',default=>'&#128196;'};}
// Build short display URL — shows /s.php?t=TOKEN which resolves same as share.php
function shortUrl(string $token):string{return SITE_URL.'/s.php?t='.urlencode($token);}
?>
<style>
.share-view{max-width:560px;margin:0 auto}
.share-hero{background:linear-gradient(135deg,rgba(0,229,200,.08),rgba(167,139,250,.08));border:1px solid rgba(0,229,200,.2);border-radius:12px;padding:24px;margin-bottom:16px;text-align:center}
.share-hero-icon{font-size:36px;margin-bottom:10px}
.share-hero-title{font-family:'Space Mono',monospace;font-size:14px;color:var(--text);font-weight:700;margin-bottom:4px}
.share-hero-by{font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)}
.share-preview-box{background:var(--surface);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:16px}
.share-preview-img{width:100%;max-height:220px;object-fit:contain;background:rgba(0,0,0,.3);display:block;cursor:zoom-in}
.share-preview-audio{width:100%;padding:12px;background:rgba(0,0,0,.2)}
.share-meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--border)}
.share-meta-cell{background:var(--surface);padding:10px 14px}
.share-meta-label{font-family:'Space Mono',monospace;font-size:8px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px}
.share-meta-value{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text)}
.share-badge{display:inline-block;font-family:'Space Mono',monospace;font-size:9px;padding:2px 7px;border-radius:4px;background:rgba(0,229,200,.12);color:var(--accent);border:1px solid rgba(0,229,200,.25)}
.share-dl-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px;background:var(--accent);color:#000;font-family:'Space Mono',monospace;font-size:12px;font-weight:700;border-radius:8px;text-decoration:none;margin-bottom:12px;transition:opacity .2s}
.share-dl-btn:hover{opacity:.88}
.copy-row{display:flex;gap:8px}
.copy-input{flex:1;font-family:'JetBrains Mono',monospace;font-size:10px;background:var(--input-bg);border:1px solid var(--border);border-radius:6px;padding:8px 10px;color:var(--text);min-width:0}
.copy-btn{font-family:'Space Mono',monospace;font-size:10px;padding:8px 12px;background:rgba(0,229,200,.1);border:1px solid rgba(0,229,200,.3);border-radius:6px;color:var(--accent);cursor:pointer;white-space:nowrap;transition:background .2s}
.copy-btn:hover,.copy-btn.copied{background:rgba(0,229,200,.2)}
.guest-strip{font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);text-align:center;padding:10px;margin-top:12px;border:1px dashed var(--border);border-radius:8px}
.guest-strip a{color:var(--accent);margin-left:8px}
.pw-form{max-width:360px;margin:40px auto;text-align:center}
.pw-form-title{font-family:'Space Mono',monospace;font-size:16px;color:var(--text);margin-bottom:6px}
.pw-form-sub{font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);margin-bottom:16px}
.ops-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:0}
@media(max-width:640px){.ops-grid{grid-template-columns:1fr}.share-meta-grid{grid-template-columns:1fr}}
.share-row{display:flex;align-items:center;gap:8px;padding:8px 10px;border-bottom:1px solid var(--border);font-size:11px;flex-wrap:wrap}
.share-row:last-child{border-bottom:none}
.share-row-token{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--accent);cursor:pointer;flex:0 0 auto;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.share-row-file{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-dim);font-family:'Space Mono',monospace;font-size:9px}
.share-row-dl,.share-row-exp{color:var(--text-muted);font-family:'Space Mono',monospace;font-size:9px;white-space:nowrap}
.share-row-btns{display:flex;gap:4px;flex-shrink:0}
.share-mini-btn{font-family:'Space Mono',monospace;font-size:9px;padding:3px 7px;border-radius:4px;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--text-dim);transition:all .15s}
.share-mini-btn:hover{border-color:var(--accent);color:var(--accent)}
.share-mini-btn.danger:hover{border-color:var(--danger);color:var(--danger)}
.share-opts-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px}
@media(max-width:500px){.share-opts-grid{grid-template-columns:1fr}}
.op-preview-card{background:var(--surface);border:1px solid rgba(0,229,200,.15);border-radius:10px;overflow:hidden;margin-bottom:16px}
.op-preview-header{padding:10px 14px;border-bottom:1px solid var(--border);font-family:'Space Mono',monospace;font-size:10px;color:var(--accent);display:flex;align-items:center;justify-content:space-between}
.op-preview-body{display:flex;gap:0}
.op-preview-col{flex:1;padding:12px 14px;border-right:1px solid var(--border)}
.op-preview-col:last-child{border-right:none}
.op-preview-label{font-family:'Space Mono',monospace;font-size:8px;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px}
.op-preview-val{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text)}
.op-preview-sub{font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-top:2px}
.chain-hint-display{font-family:'JetBrains Mono',monospace;font-size:10px;background:rgba(0,0,0,.2);border:1px solid rgba(0,229,200,.15);border-radius:6px;padding:8px 10px;color:var(--accent);margin-top:8px;display:flex;align-items:center;gap:6px}
.short-url-badge{display:inline-flex;align-items:center;gap:6px;font-family:'JetBrains Mono',monospace;font-size:10px;background:rgba(167,139,250,.08);border:1px solid rgba(167,139,250,.2);border-radius:6px;padding:4px 8px;color:#a78bfa;margin-top:4px}
</style>

<?php if($error):?><div class="alert alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?>

<?php if($pwRequired||$pwError):?>
<div class="pw-form">
  <div class="pw-form-title">&#128274; Password Protected</div>
  <div class="pw-form-sub">This share link requires a password to access.</div>
  <?php if($pwError):?><div class="alert alert-error" style="margin-bottom:10px">Incorrect password.</div><?php endif;?>
  <form method="POST">
    <div class="input-group" style="margin-bottom:10px">
      <div class="input-wrap">
        <span class="input-icon">&#128273;</span>
        <input type="password" name="share_password" class="input has-icon" placeholder="Enter share password" autofocus required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-full">Unlock &rarr;</button>
  </form>
</div>

<?php elseif($sharedView):?>
<?php
$outputMime = (string)($sharedView['output_mime'] ?? '');
$isImg      = str_starts_with($outputMime, 'image/');
$isAudio    = str_starts_with($outputMime, 'audio/');
$streamUrl  = SITE_URL.'/share.php?t='.urlencode($shareToken).'&stream=1';
$dlUrl      = SITE_URL.'/share.php?t='.urlencode($shareToken).'&dl=1';
$displayUrl = shortUrl($shareToken);
?>
<div class="share-view">
  <div class="share-hero">
    <div class="share-hero-icon"><?=mtype((string)$sharedView['media_type'])?></div>
    <div class="share-hero-title">Shared Stego File</div>
    <div class="share-hero-by">shared by <?=htmlspecialchars($sharedView['shared_by_name'])?> &bull; <?=sd($sharedView['op_created'])?></div>
  </div>

  <?php if($isImg||$isAudio):?>
  <div class="share-preview-box">
    <?php if($isImg):?>
    <img src="<?=$streamUrl?>" class="share-preview-img" alt="Stego output preview" id="preview-img" loading="lazy">
    <?php elseif($isAudio):?>
    <div class="share-preview-audio">
      <audio controls style="width:100%" preload="metadata">
        <source src="<?=$streamUrl?>" type="<?=htmlspecialchars($outputMime)?>">
        Your browser does not support audio.
      </audio>
    </div>
    <?php endif;?>
  </div>
  <?php endif;?>

  <div class="share-preview-box" style="margin-bottom:16px">
    <div class="share-meta-grid">
      <div class="share-meta-cell"><div class="share-meta-label">Output File</div><div class="share-meta-value"><?=htmlspecialchars($sharedView['output_name']??'stego_output')?></div></div>
      <div class="share-meta-cell"><div class="share-meta-label">Carrier</div><div class="share-meta-value"><?=htmlspecialchars($sharedView['carrier_name'])?></div></div>
      <div class="share-meta-cell"><div class="share-meta-label">Media Type</div><div class="share-meta-value"><span class="share-badge"><?=strtoupper((string)$sharedView['media_type'])?></span></div></div>
      <div class="share-meta-cell"><div class="share-meta-label">Technique</div><div class="share-meta-value"><span class="share-badge"><?=strtoupper(str_replace('_',' ',(string)$sharedView['stego_technique']))?></span></div></div>
      <div class="share-meta-cell"><div class="share-meta-label">Payload</div><div class="share-meta-value"><?=ss((int)$sharedView['payload_size'])?> &bull; <?=strtoupper((string)$sharedView['payload_type'])?></div></div>
      <div class="share-meta-cell"><div class="share-meta-label">Output Size</div><div class="share-meta-value"><?=ss((int)$sharedView['output_size'])?></div></div>
      <div class="share-meta-cell"><div class="share-meta-label">Downloads</div><div class="share-meta-value"><?=(int)$sharedView['download_count']?><?=$sharedView['max_downloads']?' / '.(int)$sharedView['max_downloads']:' / &infin;'?></div></div>
      <?php if($sharedView['expires_at']):?><div class="share-meta-cell"><div class="share-meta-label">Expires</div><div class="share-meta-value"><?=sd($sharedView['expires_at'])?></div></div><?php endif;?>
    </div>
  </div>

  <?php if($sharedView['chain_hint']):?>
  <div class="chain-hint-display">&#9939; Chain hint: <strong><?=htmlspecialchars($sharedView['chain_hint'])?></strong></div>
  <?php endif;?>

  <a href="<?=$dlUrl?>" class="share-dl-btn" id="dl-btn">&#11015; Download Stego File</a>

  <div style="margin-bottom:16px">
    <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Share This Link</div>
    <div class="copy-row">
      <input type="text" class="copy-input" id="surl" value="<?=htmlspecialchars($displayUrl)?>" readonly onclick="this.select()">
      <button class="copy-btn" id="scb" onclick="cpUrl('<?=htmlspecialchars($displayUrl,ENT_QUOTES)?>','scb')">&#11014; Copy</button>
    </div>
  </div>

  <div style="text-align:center">
    <a href="<?=SITE_URL?>/extract.php" style="font-family:'Space Mono',monospace;font-size:10px;color:var(--accent)">&#128274; Extract payload from this file &rarr;</a>
  </div>

  <?php if($isGuest):?>
  <div class="guest-strip">&#9888; Viewing as Guest &mdash; register to create and manage your own links.<a href="<?=SITE_URL?>/register.php">Create Account &rarr;</a></div>
  <?php endif;?>
</div>

<?php elseif(!$shareToken):?>
<?php if($isGuest):?>
<div class="card" style="max-width:520px;margin:0 auto;text-align:center;padding:30px">
  <div style="font-size:32px;margin-bottom:12px">&#128279;</div>
  <div style="font-family:'Space Mono',monospace;font-size:13px;color:var(--text);margin-bottom:8px">Share Links Require an Account</div>
  <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted);line-height:1.7;margin-bottom:16px">Guest sessions cannot create share links. Register a free account to generate shareable links with expiry, password protection, and download limits.</div>
  <a href="<?=SITE_URL?>/register.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px">&#128274; Create Free Account</a>
</div>
<?php else:?>

<?php if($opDetails):?>
<div class="op-preview-card">
  <div class="op-preview-header">
    <span>Operation #<?=(int)$opDetails['op_id']?> — Ready to Share</span>
    <span style="color:var(--text-muted)"><?=strtoupper((string)$opDetails['stego_technique'])?> &bull; <?=strtoupper((string)$opDetails['media_type'])?></span>
  </div>
  <div class="op-preview-body">
    <div class="op-preview-col">
      <div class="op-preview-label">Carrier Input</div>
      <div class="op-preview-val"><?=htmlspecialchars(substr($opDetails['before_name'],0,26))?></div>
      <div class="op-preview-sub"><?=ss((int)$opDetails['before_size'])?></div>
    </div>
    <div class="op-preview-col" style="display:flex;align-items:center;justify-content:center;font-size:18px;flex:0 0 40px;color:var(--accent)">&#8594;</div>
    <div class="op-preview-col">
      <div class="op-preview-label">Stego Output</div>
      <div class="op-preview-val" style="color:var(--accent)"><?=htmlspecialchars(substr($opDetails['after_name']??'pending',0,26))?></div>
      <div class="op-preview-sub"><?=ss((int)($opDetails['after_size']??0))?></div>
    </div>
  </div>
</div>
<?php endif;?>

<?php if($shareUrl):?>
<div class="card" style="margin-bottom:16px;border-color:rgba(0,229,200,.3)">
  <div class="card-title" style="color:var(--accent)">&#10003; Share Link Created</div>
  <?php $shortDisplay=shortUrl(basename(parse_url($shareUrl,PHP_URL_QUERY)?:''))?:$shareUrl;?>
  <div class="copy-row" style="margin-bottom:6px">
    <input type="text" class="copy-input" id="new-share-url" value="<?=htmlspecialchars($shareUrl)?>" readonly onclick="this.select()">
    <button class="copy-btn" id="nsub" onclick="cpUrl('<?=htmlspecialchars($shareUrl,ENT_QUOTES)?>','nsub')">&#11014; Copy</button>
  </div>
  <a href="<?=htmlspecialchars($shareUrl)?>" target="_blank" style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)">Open link &rarr;</a>
</div>
<?php endif;?>

<div class="ops-grid">
<form method="POST">
<div class="card" style="margin-bottom:0">
  <div class="card-title">Create Share Link</div>
  <div class="input-group" style="margin-bottom:10px">
    <label class="input-label">Select Completed Operation</label>
    <select name="op_id" class="select-input" required>
      <option value="">— Choose an operation —</option>
      <?php foreach($ops as $op):?>
      <option value="<?=$op['op_id']?>" <?=$preOp===$op['op_id']?'selected':''?>>#<?=$op['op_id']?> — <?=htmlspecialchars(substr($op['original_filename'],0,24))?> &bull; <?=strtoupper(str_replace('_',' ',(string)$op['stego_technique']))?></option>
      <?php endforeach;?>
    </select>
  </div>
  <div class="share-opts-grid">
    <div>
      <div class="input-label">Password <span class="optional-tag">(optional)</span></div>
      <input type="password" name="password" class="input" placeholder="Link password">
    </div>
    <div>
      <div class="input-label">Expiration</div>
      <select name="expires_at" class="select-input">
        <option value="">Never</option>
        <option value="<?=date('Y-m-d H:i:s',strtotime('+1 hour'))?>">1 hour</option>
        <option value="<?=date('Y-m-d H:i:s',strtotime('+24 hours'))?>" selected>24 hours</option>
        <option value="<?=date('Y-m-d H:i:s',strtotime('+7 days'))?>">7 days</option>
        <option value="<?=date('Y-m-d H:i:s',strtotime('+30 days'))?>">30 days</option>
      </select>
    </div>
    <div>
      <div class="input-label">Max Downloads <span class="optional-tag">(0=&infin;)</span></div>
      <input type="number" name="max_downloads" class="input" value="5" min="0">
    </div>
    <div>
      <div class="input-label">Chain Hint <span class="optional-tag">(optional)</span></div>
      <input type="text" name="chain_hint" class="input" placeholder="Hint for recipient">
    </div>
  </div>
  <button type="submit" class="btn btn-primary btn-full" style="margin-top:4px">&#128279; Generate Share Link</button>
</div>
</form>
<div class="card">
  <div class="card-title">Active Share Links</div>
  <?php if(empty($shares)):?>
  <p style="color:var(--text-muted);font-family:'Space Mono',monospace;font-size:11px;text-align:center;padding:20px 0">No active links</p>
  <?php else:?>
  <div id="shares-list">
    <?php foreach($shares as $s):$surl=shortUrl($s['share_token']);?>
    <div class="share-row" id="srow-<?=$s['share_id']?>">
      <span class="share-row-token" title="<?=htmlspecialchars($surl)?>" onclick="cpUrl('<?=htmlspecialchars($surl,ENT_QUOTES)?>','stok-<?=$s['share_id']?>')" id="stok-<?=$s['share_id']?>"><?=htmlspecialchars($s['share_token'])?></span>
      <span class="share-row-file" title="<?=htmlspecialchars($s['original_filename'])?>"><?=htmlspecialchars(substr($s['original_filename'],0,16))?></span>
      <span class="share-row-dl"><?=$s['download_count']?>/<?=$s['max_downloads']?:'&infin;'?></span>
      <span class="share-row-exp"><?=$s['expires_at']?date('d M',strtotime($s['expires_at'])):'Never'?></span>
      <div class="share-row-btns">
        <button class="share-mini-btn" id="scb-<?=$s['share_id']?>" onclick="cpUrl('<?=htmlspecialchars($surl,ENT_QUOTES)?>','scb-<?=$s['share_id']?>')">&#11014; Copy</button>
        <a href="<?=htmlspecialchars($surl)?>" target="_blank" class="share-mini-btn">&#8594;</a>
        <button class="share-mini-btn danger" onclick="revokeShare(<?=$s['share_id']?>,'srow-<?=$s['share_id']?>')">Revoke</button>
      </div>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>
</div>
<?php endif;?>
<?php endif;?>

<script>
const SITE_URL='<?=SITE_URL?>';
function cpUrl(url,btnId){
  navigator.clipboard.writeText(url).then(()=>{
    const btn=document.getElementById(btnId);if(!btn)return;
    const orig=btn.textContent;btn.textContent='\u2713 Copied!';btn.classList.add('copied');
    setTimeout(()=>{btn.textContent=orig;btn.classList.remove('copied');},2200);
  }).catch(()=>{const i=document.createElement('input');i.value=url;document.body.appendChild(i);i.select();document.execCommand('copy');document.body.removeChild(i);typeof showToast==='function'&&showToast('Copied!');});
}
function revokeShare(id,rowId){
  if(!confirm('Revoke this share link? Recipients will lose access.'))return;
  const fd=new FormData();fd.append('share_id',id);fd.append('action','revoke_share');
  fetch(`${SITE_URL}/php/api/process_ajax.php`,{method:'POST',body:fd}).then(r=>r.json())
    .then(d=>{if(d.success){document.getElementById(rowId)?.remove();typeof showToast==='function'&&showToast('Share link revoked.');}else alert(d.message||'Failed to revoke.')})
    .catch(()=>window.location.reload());
}
const previewImg=document.getElementById('preview-img');
if(previewImg){previewImg.addEventListener('click',()=>{const o=document.getElementById('img-viewer-overlay');const s=document.getElementById('img-viewer-src');if(o&&s){s.src=previewImg.src;o.style.display='flex';}});}
</script>
<?php require_once 'php/includes/footer.php';?>