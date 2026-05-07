<?php
ob_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    $auth = new Auth();
    if (!$auth->isLoggedIn() || $auth->isGuest()) {
        echo json_encode(['success'=>false,'message'=>'Authentication required.']); exit;
    }
    $user = $auth->currentUser();
    $uid  = (int)($user['user_id'] ?? 0);
    $db   = Database::getInstance();

    $shareId = (int)($_POST['share_id'] ?? 0);
    if (!$shareId) { echo json_encode(['success'=>false,'message'=>'Invalid share ID.']); exit; }

    // Verify ownership — share must belong to the requesting user's operation
    $share = $db->fetchOne(
        "SELECT sf.share_id, sf.share_token FROM shared_files sf
         JOIN stego_operations o ON sf.op_id = o.op_id
         WHERE sf.share_id = ? AND o.user_id = ? AND sf.is_active = 1",
        [$shareId, $uid]
    );

    if (!$share) {
        echo json_encode(['success'=>false,'message'=>'Share link not found or already revoked.']); exit;
    }

    $db->query("UPDATE shared_files SET is_active = 0 WHERE share_id = ?", [$shareId]);
    echo json_encode(['success'=>true,'message'=>'Share link revoked.','share_id'=>$shareId]);

} catch (Throwable $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]);
}