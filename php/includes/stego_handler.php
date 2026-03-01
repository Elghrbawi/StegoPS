<?php
// ============================================================
// StegoPS — Stego Operations Handler
// File: php/includes/stego_handler.php
// ============================================================

require_once __DIR__ . '/../config/database.php';

class StegoHandler {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ── UPLOAD CARRIER FILE ──
    public function uploadCarrier(array $file, int $userId): array {
        $allowed = [
            'image' => ['image/png', 'image/jpeg', 'image/bmp'],
            'audio' => ['audio/wav', 'audio/mpeg', 'audio/mp3'],
            'video' => ['video/mp4', 'video/avi', 'video/quicktime'],
        ];

        $mime = mime_content_type($file['tmp_name']);
        $type = null;
        foreach ($allowed as $t => $mimes) {
            if (in_array($mime, $mimes)) { $type = $t; break; }
        }
        if (!$type)
            return ['success' => false, 'message' => 'Unsupported file type. Use PNG/JPG/WAV/MP4.'];
        if ($file['size'] > MAX_FILE_SIZE)
            return ['success' => false, 'message' => 'File too large. Max 200MB.'];

        $stored  = bin2hex(random_bytes(16)) . '_' . time();
        $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
        $stored .= '.' . strtolower($ext);
        $path    = UPLOAD_PATH . 'carriers/' . $stored;

        if (!move_uploaded_file($file['tmp_name'], $path))
            return ['success' => false, 'message' => 'Failed to save file.'];

        $hash   = hash_file('sha256', $path);
        $fileId = $this->db->insert(
            "INSERT INTO media_files (user_id, original_filename, stored_filename, file_type, mime_type, file_size_bytes, file_hash, storage_path)
             VALUES (?,?,?,?,?,?,?,?)",
            [$userId, $file['name'], $stored, $type, $mime, $file['size'], $hash, 'carriers/' . $stored]
        );

        // Update user storage
        $this->db->query(
            "UPDATE users SET storage_used = storage_used + ? WHERE user_id = ?",
            [$file['size'], $userId]
        );

        return ['success' => true, 'file_id' => $fileId, 'file_type' => $type, 'stored' => $stored];
    }

    // ── CALL PYTHON ENGINE ──
    private function callPythonEngine(string $endpoint, array $data, array $files = []): array {
        $url = PYTHON_API_URL . $endpoint;
        $curl = curl_init($url);

        $postData = $data;
        foreach ($files as $key => $path) {
            $postData[$key] = new CURLFile($path);
        }

        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . PYTHON_API_SECRET],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            $err = json_decode($response, true);
            return ['success' => false, 'message' => $err['error'] ?? 'Python engine error.'];
        }

        return ['success' => true, 'data' => $response];
    }

    // ── EMBED OPERATION ──
    public function embed(
        int $userId,
        int $fileId,
        ?int $chainId,
        string $payloadType,
        string $payload,
        string $stegoKey,
        array $chainSteps
    ): array {
        $carrier = $this->db->fetchOne(
            "SELECT * FROM media_files WHERE file_id = ? AND user_id = ?",
            [$fileId, $userId]
        );
        if (!$carrier) return ['success' => false, 'message' => 'Carrier file not found.'];

        // Create operation record
        $technique = match($carrier['file_type']) {
            'image' => 'lsb',
            'audio' => 'phase_coding',
            'video' => 'frame_embed',
            default => 'lsb'
        };

        $opId = $this->db->insert(
            "INSERT INTO stego_operations (user_id, chain_id, carrier_file_id, operation_type, payload_type, payload_size, stego_technique, status)
             VALUES (?,?,?,?,?,?,?,'processing')",
            [$userId, $chainId, $fileId, 'embed', $payloadType, strlen($payload), $technique]
        );

        // Call Python engine
        $carrierPath = UPLOAD_PATH . $carrier['storage_path'];
        $result = $this->callPythonEngine('/api/embed/' . $carrier['file_type'], [
            'payload'    => $payload,
            'stego_key'  => $stegoKey,
            'steps'      => json_encode($chainSteps),
            'op_id'      => $opId,
        ], ['carrier' => $carrierPath]);

        if (!$result['success']) {
            $this->db->query(
                "UPDATE stego_operations SET status='failed', error_message=? WHERE op_id=?",
                [$result['message'], $opId]
            );
            return ['success' => false, 'message' => $result['message']];
        }

        // Save output file
        $outName = 'stego_' . bin2hex(random_bytes(8)) . '_' . time() . '.png';
        $outPath = UPLOAD_PATH . 'stego/' . $outName;
        file_put_contents($outPath, $result['data']);

        $ext = $carrier['file_type'] === 'audio' ? 'wav' : ($carrier['file_type'] === 'video' ? 'mp4' : 'png');
        $outName = 'stego_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $outPath = UPLOAD_PATH . 'stego/' . $outName;
        file_put_contents($outPath, $result['data']);

        $outFileId = $this->db->insert(
            "INSERT INTO media_files (user_id, original_filename, stored_filename, file_type, mime_type, file_size_bytes, file_hash, storage_path, is_processed)
             VALUES (?,?,?,?,?,?,?,?,1)",
            [$userId, 'stego_output.' . $ext, $outName, $carrier['file_type'],
             $carrier['mime_type'], strlen($result['data']),
             hash('sha256', $result['data']), 'stego/' . $outName]
        );

        $this->db->query(
            "UPDATE stego_operations SET status='completed', output_file_id=?, completed_at=NOW() WHERE op_id=?",
            [$outFileId, $opId]
        );

        return ['success' => true, 'op_id' => $opId, 'file_id' => $outFileId, 'message' => 'Embed successful!'];
    }

    // ── EXTRACT OPERATION ──
    public function extract(int $userId, int $fileId, string $stegoKey, array $chainSteps): array {
        $file = $this->db->fetchOne(
            "SELECT * FROM media_files WHERE file_id = ? AND user_id = ?",
            [$fileId, $userId]
        );
        if (!$file) return ['success' => false, 'message' => 'File not found.'];

        $filePath = UPLOAD_PATH . $file['storage_path'];
        $result   = $this->callPythonEngine('/api/extract/' . $file['file_type'], [
            'stego_key' => $stegoKey,
            'steps'     => json_encode($chainSteps),
        ], ['stego_file' => $filePath]);

        if (!$result['success'])
            return ['success' => false, 'message' => $result['message']];

        return ['success' => true, 'payload' => $result['data'], 'message' => 'Extraction successful!'];
    }

    // ── GET USER OPERATIONS ──
    public function getUserOperations(int $userId, int $limit = 20): array {
        return $this->db->fetchAll(
            "SELECT o.*, f.original_filename, f.file_type
             FROM stego_operations o
             JOIN media_files f ON o.carrier_file_id = f.file_id
             WHERE o.user_id = ?
             ORDER BY o.created_at DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    // ── CREATE SHARE LINK ──
    public function createShareLink(
        int $opId,
        int $userId,
        ?string $password,
        int $maxDownloads,
        ?string $expiresAt,
        string $chainHint = ''
    ): array {
        $op = $this->db->fetchOne(
            "SELECT * FROM stego_operations WHERE op_id = ? AND user_id = ? AND status = 'completed'",
            [$opId, $userId]
        );
        if (!$op) return ['success' => false, 'message' => 'Operation not found or not completed.'];

        $token     = bin2hex(random_bytes(64));
        $pwHash    = $password ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]) : null;
        $shareUrl  = SITE_URL . '/share.php?t=' . $token;

        $shareId = $this->db->insert(
            "INSERT INTO shared_files (op_id, shared_by, share_token, password_hash, max_downloads, chain_hint, expires_at)
             VALUES (?,?,?,?,?,?,?)",
            [$opId, $userId, $token, $pwHash, $maxDownloads, $chainHint, $expiresAt]
        );

        return ['success' => true, 'share_id' => $shareId, 'url' => $shareUrl, 'token' => $token];
    }
}
