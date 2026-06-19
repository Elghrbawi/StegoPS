<?php
ob_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stego_handler.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* ── Helpers ─────────────────────────────────────────── */
function readUpload(string $key, string &$err): ?string {
    if (empty($_FILES[$key]['name']) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        $err = 'No file uploaded or upload error.'; return null;
    }
    $d = file_get_contents($_FILES[$key]['tmp_name']);
    if ($d === false) { $err = 'Failed to read uploaded file.'; return null; }
    return $d;
}

/**
 * mediaPayload — handles ANY binary payload: images, audio, video, documents.
 * Previously split into filePayload/imagePayload — now unified as 'media'.
 * The stored format is base64("STEGMEDIA1\n{json_meta}\n\n{binary}") so the
 * extractor can reconstruct the original file with correct name & MIME type.
 */
function mediaPayload(string $key, string &$err): ?string {
    if (empty($_FILES[$key]['name']) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        $err = 'No media file uploaded or upload error.'; return null;
    }
    $maxBytes = 50 * 1024 * 1024; // 50 MB payload limit
    if ($_FILES[$key]['size'] > $maxBytes) {
        $err = 'Payload media file too large. Max 50 MB.'; return null;
    }
    $bin = file_get_contents($_FILES[$key]['tmp_name']);
    if ($bin === false) { $err = 'Failed to read payload file.'; return null; }
    $mime = @mime_content_type($_FILES[$key]['tmp_name']) ?: ($_FILES[$key]['type'] ?? 'application/octet-stream');
    $meta = json_encode([
        'name' => $_FILES[$key]['name'] ?? 'payload.bin',
        'type' => $mime,
        'size' => strlen($bin),
    ], JSON_UNESCAPED_SLASHES);
    return base64_encode("STEGMEDIA1\n{$meta}\n\n{$bin}");
}

function resolveCarrier(StegoHandler $h, int $uid, string &$err): ?array {
    $preId = (int)($_POST['carrier_file_id'] ?? 0);
    if ($preId > 0) {
        $f = $h->getFileById($preId, $uid);
        if (!$f) { $err = 'Pre-selected carrier not found.'; return null; }
        return ['success'=>true,'file_id'=>$preId,'file_type'=>$f['file_type'],'file_size'=>$f['file_size_bytes']];
    }
    if (empty($_FILES['carrier']['name']) || $_FILES['carrier']['error'] !== UPLOAD_ERR_OK) {
        $err = 'No carrier file or upload error.'; return null;
    }
    $r = $h->uploadCarrier($_FILES['carrier'], $uid);
    if (!$r['success']) { $err = $r['message']; return null; }
    return $r;
}

function resolveStego(StegoHandler $h, int $uid, string &$err): ?array {
    $preId = (int)($_POST['stego_file_id'] ?? 0);
    if ($preId > 0) {
        $f = $h->getFileById($preId, $uid);
        if (!$f) { $err = 'Pre-selected stego file not found.'; return null; }
        return ['success'=>true,'file_id'=>$preId,'file_type'=>$f['file_type']];
    }
    if (empty($_FILES['stego_file']['name']) || $_FILES['stego_file']['error'] !== UPLOAD_ERR_OK) {
        $err = 'No stego file or upload error.'; return null;
    }
    $r = $h->uploadCarrier($_FILES['stego_file'], $uid);
    if (!$r['success']) { $err = $r['message']; return null; }
    return $r;
}

/* ── Main ─────────────────────────────────────────────── */
try {
    $auth = new Auth();
    if (!$auth->isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
    $user    = $auth->currentUser();
    $uid     = (int)($user['user_id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    $handler = new StegoHandler();

    if ($action === 'ping') { echo json_encode($handler->pingEngine()); exit; }

    /* get_methods */
    if ($action === 'get_methods') {
        echo json_encode($handler->getMethodsRaw(
            $_POST['file_type']    ?? 'image',
            $_POST['payload_type'] ?? 'text'
        ));
        exit;
    }

    /* capacity — returns how many bytes can be hidden */
    if ($action === 'capacity') {
        echo json_encode($handler->getCapacity(
            (int)($_POST['carrier_file_id'] ?? 0), $uid,
            $_POST['steg_method'] ?? 'lsb'
        ));
        exit;
    }

    /* embed */
    if ($action === 'embed') {
        $stegoKey = trim($_POST['stego_key']   ?? '');
        $pType    = $_POST['payload_type']      ?? 'text';
        $stegMeth = $_POST['steg_method']       ?? 'lsb';
        $chainRaw = json_decode($_POST['chain_data'] ?? '[]', true) ?: [];
        $errMsg   = '';

        if (empty($stegoKey)) { echo json_encode(['success'=>false,'message'=>'Stego key is required.']); exit; }

        // ── Payload type resolution ─────────────────────────────────────────
        // Supported: 'text', 'media' (any binary file — images, audio, video, docs)
        // 'image' and 'file' are accepted as aliases for 'media' for back-compat.
        switch ($pType) {
            case 'media':
            case 'file':
            case 'image':   // legacy alias — images ARE media
                $payload = mediaPayload('payload_media', $errMsg);
                if ($payload === null) {
                    // Try legacy key names
                    if (!empty($_FILES['payload_file']['name']))   $payload = mediaPayload('payload_file', $errMsg);
                    elseif (!empty($_FILES['payload_image']['name'])) $payload = mediaPayload('payload_image', $errMsg);
                }
                $pType = 'media'; // normalise
                break;

            default: // 'text'
                $payload = $_POST['payload_text'] ?? '';
                if (trim($payload) === '') $errMsg = 'Payload text cannot be empty.';
        }

        if (!empty($errMsg) || $payload === null) {
            echo json_encode(['success'=>false,'message'=>$errMsg ?: 'Payload error.']); exit;
        }

        $carrier = resolveCarrier($handler, $uid, $errMsg);
        if (!$carrier) { echo json_encode(['success'=>false,'message'=>$errMsg]); exit; }

        $result = $handler->embed($uid, $carrier['file_id'], null, $pType, $payload, $stegoKey, $stegMeth, $chainRaw);

        if ($result['success'] && $uid === 0) {
            $_SESSION['guest_file_ids'] = array_unique(array_merge(
                $_SESSION['guest_file_ids'] ?? [],
                [$result['file_id'], $carrier['file_id']]
            ));
        }

        echo json_encode($result);
        exit;
    }

    /* extract */
    if ($action === 'extract') {
        $stegoKey = trim($_POST['stego_key'] ?? '');
        $stegMeth = $_POST['steg_method']    ?? 'lsb';
        $chainRaw = json_decode($_POST['chain_data'] ?? '[]', true) ?: [];
        $errMsg   = '';

        if (empty($stegoKey)) { echo json_encode(['success'=>false,'message'=>'Stego key is required.']); exit; }

        $stego = resolveStego($handler, $uid, $errMsg);
        if (!$stego) { echo json_encode(['success'=>false,'message'=>$errMsg]); exit; }

        echo json_encode($handler->extract($uid, $stego['file_id'], $stegoKey, $stegMeth, $chainRaw));
        exit;
    }

    /* create_share */
    if ($action === 'create_share') {
        if ($auth->isGuest()) { echo json_encode(['success'=>false,'message'=>'Guests cannot create share links. Please register.']); exit; }
        echo json_encode($handler->createShareLink(
            (int)($_POST['op_id']         ?? 0), $uid,
            !empty($_POST['password'])    ? $_POST['password']   : null,
            (int)($_POST['max_downloads'] ?? 0),
            !empty($_POST['expires_at'])  ? $_POST['expires_at'] : null,
            $_POST['chain_hint']          ?? ''
        ));
        exit;
    }

    /* revoke_share */
    if ($action === 'revoke_share') {
        if ($auth->isGuest()) { echo json_encode(['success'=>false,'message'=>'Not allowed.']); exit; }
        $shareId = (int)($_POST['share_id'] ?? 0);
        if (!$shareId) { echo json_encode(['success'=>false,'message'=>'Invalid share ID.']); exit; }
        $db    = Database::getInstance();
        $share = $db->fetchOne(
            "SELECT sf.share_id FROM shared_files sf JOIN stego_operations o ON sf.op_id=o.op_id WHERE sf.share_id=? AND o.user_id=? AND sf.is_active=1",
            [$shareId, $uid]
        );
        if (!$share) { echo json_encode(['success'=>false,'message'=>'Not found or already revoked.']); exit; }
        $db->query("UPDATE shared_files SET is_active=0 WHERE share_id=?", [$shareId]);
        echo json_encode(['success'=>true]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Invalid action: '.$action]);

} catch (Throwable $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]);
}