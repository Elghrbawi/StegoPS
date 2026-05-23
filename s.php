<?php
/**
 * s.php — Short share URL handler.
 *
 * All share links now use /s.php?t=TOKEN instead of the long share.php URL.
 * Tokens are format: {username}{6-char base62} e.g. "alice-K9m3xP"
 * This redirects to share.php with the same token.
 * Can also be used as an API: ?t=TOKEN&json=1 returns share metadata.
 */
require_once __DIR__ . '/php/config/database.php';

$token = trim($_GET['t'] ?? '');

if (!$token || !preg_match('/^[a-z0-9_\-]{4,40}$/i', $token)) {
    http_response_code(400);
    die('<title>Invalid Link</title><p>Invalid share token.</p>');
}

$db = Database::getInstance();

// JSON API mode (for link preview / AJAX)
if (($_GET['json'] ?? '') === '1') {
    header('Content-Type: application/json');
    $row = $db->fetchOne(
        "SELECT sf.share_token, sf.expires_at, sf.max_downloads, sf.download_count,
                sf.is_active, sf.chain_hint, o.stego_technique, o.payload_type,
                o.payload_size, cf.file_type AS media_type,
                of2.original_filename AS output_name, of2.file_size_bytes AS output_size,
                of2.mime_type AS output_mime, u.username AS shared_by
         FROM shared_files sf
         JOIN stego_operations o  ON sf.op_id = o.op_id
         JOIN media_files cf      ON cf.file_id = o.carrier_file_id
         LEFT JOIN media_files of2 ON of2.file_id = o.output_file_id
         JOIN users u             ON sf.shared_by = u.user_id
         WHERE sf.share_token = ? AND sf.is_active = 1", [$token]
    );
    if (!$row) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }
    if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
        echo json_encode(['error'=>'Expired']); exit;
    }
    echo json_encode([
        'token'      => $row['share_token'],
        'shared_by'  => $row['shared_by'],
        'technique'  => $row['stego_technique'],
        'media_type' => $row['media_type'],
        'mime'       => $row['output_mime'],
        'output'     => $row['output_name'],
        'size'       => $row['output_size'],
        'downloads'  => (int)$row['download_count'],
        'max_dl'     => (int)$row['max_downloads'],
        'expires_at' => $row['expires_at'],
        'chain_hint' => $row['chain_hint'],
        'view_url'   => SITE_URL . '/s.php?t=' . urlencode($token),
        'dl_url'     => SITE_URL . '/share.php?t=' . urlencode($token) . '&dl=1',
    ]);
    exit;
}

// Validate token exists before redirecting (shows 404 instead of empty page)
$exists = $db->fetchOne(
    "SELECT is_active, expires_at, max_downloads, download_count
     FROM shared_files WHERE share_token = ?", [$token]
);

if (!$exists || !$exists['is_active']) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Link Not Found</title>
<style>body{font-family:monospace;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#0d1117;color:#8b949e;}
.box{text-align:center;padding:40px;border:1px solid #30363d;border-radius:12px;}
.icon{font-size:48px;margin-bottom:16px;}h2{color:#f0f6fc;margin-bottom:8px;}</style>
</head><body><div class="box"><div class="icon">&#128279;</div>
<h2>Link Not Found</h2><p>This share link does not exist or has been revoked.</p>
<a href="'.htmlspecialchars(SITE_URL).'" style="color:#00e5c8">← Back to StegoPS</a></div></body></html>';
    exit;
}

if ($exists['expires_at'] && strtotime($exists['expires_at']) < time()) {
    http_response_code(410);
    echo '<!DOCTYPE html><html><head><title>Link Expired</title>
<style>body{font-family:monospace;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#0d1117;color:#8b949e;}
.box{text-align:center;padding:40px;border:1px solid #30363d;border-radius:12px;}
.icon{font-size:48px;margin-bottom:16px;}h2{color:#f0f6fc;margin-bottom:8px;}</style>
</head><body><div class="box"><div class="icon">&#9203;</div>
<h2>Link Expired</h2><p>This share link has expired.</p>
<a href="'.htmlspecialchars(SITE_URL).'" style="color:#00e5c8">← Back to StegoPS</a></div></body></html>';
    exit;
}

if ($exists['max_downloads'] > 0 && $exists['download_count'] >= $exists['max_downloads']) {
    http_response_code(410);
    echo '<!DOCTYPE html><html><head><title>Download Limit Reached</title>
<style>body{font-family:monospace;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#0d1117;color:#8b949e;}
.box{text-align:center;padding:40px;border:1px solid #30363d;border-radius:12px;}
.icon{font-size:48px;margin-bottom:16px;}h2{color:#f0f6fc;margin-bottom:8px;}</style>
</head><body><div class="box"><div class="icon">&#128683;</div>
<h2>Download Limit Reached</h2><p>This share link has hit its download cap.</p>
<a href="'.htmlspecialchars(SITE_URL).'" style="color:#00e5c8">← Back to StegoPS</a></div></body></html>';
    exit;
}

// Redirect to full share page
header('Location: ' . SITE_URL . '/share.php?t=' . urlencode($token), true, 302);
exit;