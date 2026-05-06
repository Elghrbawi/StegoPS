<?php
// Always output JSON — catch everything before any output
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stego_handler.php';

// Discard any accidental whitespace/BOM from includes
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success'=>false,'message'=>'Not authenticated']);
        exit;
    }
    $user = $auth->currentUser();
    $uid  = (int)($user['user_id'] ?? 0);

    $action  = $_POST['action'] ?? '';
    $handler = new StegoHandler();

    /* ── PING: check if Python engine is reachable ── */
    if ($action === 'ping') {
        $r = $handler->pingEngine();
        echo json_encode($r);
        exit;
    }

    /* ── GET METHODS: fetch available steg methods for a file type ── */
    if ($action === 'get_methods') {
        $fileType = $_POST['file_type'] ?? 'image';
        $r        = $handler->getMethodsRaw($fileType);
        echo json_encode($r);
        exit;
    }

    /* ── EMBED ── */
    if ($action === 'embed') {
        if (empty($_FILES['carrier']['name']) || $_FILES['carrier']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success'=>false,'message'=>'No carrier file or upload error.']);
            exit;
        }

        $stegoKey  = trim($_POST['stego_key']  ?? '');
        $payload   = $_POST['payload_text']    ?? '';
        $pType     = $_POST['payload_type']    ?? 'text';
        $stegMeth  = $_POST['steg_method']     ?? 'lsb';
        $chainRaw  = json_decode($_POST['chain_data'] ?? '[]', true) ?: [];

        if ($pType === 'file') {
            if (empty($_FILES['payload_file']['name']) || $_FILES['payload_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success'=>false,'message'=>'No payload file or upload error.']);
                exit;
            }
            $bin = file_get_contents($_FILES['payload_file']['tmp_name']);
            if ($bin === false) {
                echo json_encode(['success'=>false,'message'=>'Failed to read payload file.']);
                exit;
            }
            $meta = json_encode([
                'name' => $_FILES['payload_file']['name'] ?? 'payload.bin',
                'type' => $_FILES['payload_file']['type'] ?? 'application/octet-stream',
            ], JSON_UNESCAPED_SLASHES);
            $payload = base64_encode("STEGFILE1\n" . $meta . "\n\n" . $bin);
        }

        if (empty($stegoKey)) { echo json_encode(['success'=>false,'message'=>'Stego key is required.']); exit; }
        if (empty($payload) && $pType === 'text') { echo json_encode(['success'=>false,'message'=>'Payload cannot be empty.']); exit; }

        $uploaded = $handler->uploadCarrier($_FILES['carrier'], $uid);
        if (!$uploaded['success']) { echo json_encode($uploaded); exit; }

        $result = $handler->embed($uid, $uploaded['file_id'], null, $pType, $payload, $stegoKey, $stegMeth, $chainRaw);
        echo json_encode($result);
        exit;
    }

    /* ── EXTRACT ── */
    if ($action === 'extract') {
        if (empty($_FILES['stego_file']['name']) || $_FILES['stego_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success'=>false,'message'=>'No stego file or upload error.']);
            exit;
        }

        $stegoKey = trim($_POST['stego_key']  ?? '');
        $stegMeth = $_POST['steg_method']     ?? 'lsb';
        $chainRaw = json_decode($_POST['chain_data'] ?? '[]', true) ?: [];

        if (empty($stegoKey)) { echo json_encode(['success'=>false,'message'=>'Stego key is required.']); exit; }

        $uploaded = $handler->uploadCarrier($_FILES['stego_file'], $uid);
        if (!$uploaded['success']) { echo json_encode($uploaded); exit; }

        $result = $handler->extract($uid, $uploaded['file_id'], $stegoKey, $stegMeth, $chainRaw);
        echo json_encode($result);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Invalid action: '.$action]);

} catch (Throwable $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]);
}
