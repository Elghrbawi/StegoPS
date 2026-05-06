<?php
require_once __DIR__ . '/../config/database.php';

class StegoHandler {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    private function ensureDirs(): void {
        foreach (['carriers','stego','payloads'] as $d) {
            $p = UPLOAD_PATH . $d;
            if (!is_dir($p)) mkdir($p, 0755, true);
        }
    }

    private function makeFilename(string $prefix, int $userId, string $ext): string {
        $uname = 'guest';
        if ($userId > 0) {
            $row = $this->db->fetchOne("SELECT username FROM users WHERE user_id=?", [$userId]);
            if ($row) $uname = preg_replace('/[^a-z0-9]/', '', strtolower($row['username']));
        }
        return $uname . '_' . $prefix . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
    }

    public function uploadCarrier(array $file, int $userId): array {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
            return ['success'=>false,'message'=>'No file received or invalid upload.'];

        $allowed = [
            'image' => ['image/png','image/jpeg','image/jpg','image/bmp','image/x-bmp','image/x-ms-bmp'],
            'audio' => ['audio/wav','audio/x-wav'],
            'video' => ['video/mp4','video/avi','video/x-msvideo','video/quicktime'],
        ];
        $mime = @mime_content_type($file['tmp_name']) ?: $file['type'];
        $type = null;
        foreach ($allowed as $t => $mimes) { if (in_array($mime, $mimes)) { $type = $t; break; } }
        if (!$type) {
            $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $extMap = ['png'=>'image','jpg'=>'image','jpeg'=>'image','bmp'=>'image',
                       'wav'=>'audio','mp4'=>'video','avi'=>'video','mov'=>'video'];
            if (isset($extMap[$ext])) $type = $extMap[$ext];
        }
        if (!$type) return ['success'=>false,'message'=>'Unsupported file. Use PNG/JPG/BMP (image), WAV (audio), MP4/AVI (video).'];
        if ($file['size'] > MAX_FILE_SIZE) return ['success'=>false,'message'=>'File too large. Max 200MB.'];

        $this->ensureDirs();
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $stored  = $this->makeFilename('carrier', $userId, $ext);
        $destDir = UPLOAD_PATH . 'carriers' . DIRECTORY_SEPARATOR;
        $dest    = $destDir . $stored;
        if (!move_uploaded_file($file['tmp_name'], $dest))
            return ['success'=>false,'message'=>'Failed to save file. Check permissions: '.$destDir];

        $hash   = hash_file('sha256', $dest);
        $fileId = $this->db->insert(
            "INSERT INTO media_files (user_id,original_filename,stored_filename,file_type,mime_type,file_size_bytes,file_hash,storage_path) VALUES (?,?,?,?,?,?,?,?)",
            [$userId, $file['name'], $stored, $type, $mime, $file['size'], $hash, 'carriers/'.$stored]
        );
        if ($userId > 0) $this->db->query("UPDATE users SET storage_used=storage_used+? WHERE user_id=?", [$file['size'], $userId]);
        return ['success'=>true,'file_id'=>$fileId,'file_type'=>$type,'stored'=>$stored,'abs_path'=>$dest,'file_size'=>$file['size']];
    }

    /* ── Python bridge ── */
    private function callPython(string $endpoint, array $data, string $method = 'POST'): array {
        $url  = PYTHON_API_URL . $endpoint;
        $curl = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . PYTHON_API_SECRET,
            'Content-Type: application/json',
        ];

        if ($method === 'GET') {
            // GET with no body — just send auth header
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($curl);
        curl_close($curl);

        if ($curlErr || $httpCode === 0) {
            return ['success'=>false,'message'=>'Cannot reach Python engine at '.PYTHON_API_URL.' — is server.py running? ('.$curlErr.')'];
        }
        if ($httpCode !== 200) {
            $err = json_decode($response, true);
            return ['success'=>false,'message'=>$err['error'] ?? 'Python engine error (HTTP '.$httpCode.')'];
        }
        $decoded = json_decode($response, true);
        if (!$decoded) return ['success'=>false,'message'=>'Invalid JSON from Python engine'];
        return array_merge(['success'=>true], $decoded);
    }

    /* ── Public API ── */
    public function pingEngine(): array {
        return $this->callPython('/health', [], 'GET');
    }

    public function getMethods(string $fileType): array {
        // Use POST — Python route now accepts both GET & POST
        $result = $this->callPython('/api/methods/' . $fileType, []);
        if (!$result['success']) return ['success'=>false,'methods'=>[],'message'=>$result['message']??''];
        // Python returns a plain array (list of method objects) — not wrapped
        // Strip our injected 'success' key and return the rest as-is
        unset($result['success']);
        $methods = array_values($result); // numeric array
        // When Flask returns a list, json_decode gives a numeric array;
        // but after array_merge it gets mangled — fetch raw instead:
        return ['success'=>true,'methods'=>[]]; // fallback, see note below
    }

    public function getMethodsRaw(string $fileType): array {
        $url  = PYTHON_API_URL . '/api/methods/' . $fileType;
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . PYTHON_API_SECRET,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($curl);
        curl_close($curl);

        if ($curlErr || $httpCode === 0) {
            return ['success'=>false,'methods'=>[],'message'=>'Engine offline: '.$curlErr];
        }
        if ($httpCode !== 200) {
            return ['success'=>false,'methods'=>[],'message'=>'Engine error HTTP '.$httpCode];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['success'=>false,'methods'=>[],'message'=>'Bad response from engine'];
        }
        return ['success'=>true,'methods'=>$decoded];
    }

    public function embed(int $userId, int $fileId, ?int $chainId,
                          string $payloadType, string $payload,
                          string $stegoKey, string $stegMethod, array $chainSteps): array {
        $carrier = $this->db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?", [$fileId, $userId]);
        if (!$carrier) return ['success'=>false,'message'=>'Carrier file not found.'];

        $opId = $this->db->insert(
            "INSERT INTO stego_operations (user_id,chain_id,carrier_file_id,operation_type,payload_type,payload_size,stego_technique,status) VALUES (?,?,?,?,?,?,?,'processing')",
            [$userId, $chainId, $fileId, 'embed', $payloadType, strlen($payload), $stegMethod]
        );

        $carrierPath = UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $carrier['storage_path']);
        $outputDir   = UPLOAD_PATH . 'stego' . DIRECTORY_SEPARATOR;
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);

        $result = $this->callPython('/api/embed', [
            'carrier_path' => $carrierPath,
            'output_dir'   => $outputDir,
            'payload'      => $payload,
            'payload_type' => $payloadType,
            'stego_key'    => $stegoKey,
            'steg_method'  => $stegMethod,
            'file_type'    => $carrier['file_type'],
            'steps'        => $chainSteps,
            'op_id'        => $opId,
        ]);

        if (!$result['success']) {
            $this->db->query("UPDATE stego_operations SET status='failed',error_message=? WHERE op_id=?", [$result['message'], $opId]);
            return ['success'=>false,'message'=>$result['message']];
        }

        $outFilename = $result['output_filename'];
        $outPath     = $outputDir . $outFilename;
        $outSize     = file_exists($outPath) ? filesize($outPath) : 0;
        $outHash     = file_exists($outPath) ? hash_file('sha256', $outPath) : '';
        $ext         = strtolower(pathinfo($outFilename, PATHINFO_EXTENSION));
        $mimeMap     = ['png'=>'image/png','wav'=>'audio/wav','mp4'=>'video/mp4','avi'=>'video/x-msvideo'];

        $outFileId = $this->db->insert(
            "INSERT INTO media_files (user_id,original_filename,stored_filename,file_type,mime_type,file_size_bytes,file_hash,storage_path,is_processed) VALUES (?,?,?,?,?,?,?,?,1)",
            [$userId, 'stego_output.'.$ext, $outFilename, $carrier['file_type'], $mimeMap[$ext] ?? 'application/octet-stream', $outSize, $outHash, 'stego/'.$outFilename]
        );
        $this->db->query("UPDATE stego_operations SET status='completed',output_file_id=?,completed_at=NOW() WHERE op_id=?", [$outFileId, $opId]);
        if ($userId > 0) $this->db->query("UPDATE users SET storage_used=storage_used+? WHERE user_id=?", [$outSize, $userId]);

        return [
            'success'       => true,
            'op_id'         => $opId,
            'file_id'       => $outFileId,
            'carrier_size'  => $result['carrier_size'] ?? $carrier['file_size_bytes'],
            'output_size'   => $outSize,
            'payload_size'  => $result['payload_size'] ?? strlen($payload),
            'steg_method'   => $stegMethod,
            'layers'        => count($chainSteps),
            'message'       => 'Embed successful!',
        ];
    }

    public function extract(int $userId, int $fileId, string $stegoKey, string $stegMethod, array $chainSteps): array {
        $file = $this->db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?", [$fileId, $userId]);
        if (!$file) return ['success'=>false,'message'=>'File not found.'];

        $filePath = UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $file['storage_path']);
        $result   = $this->callPython('/api/extract', [
            'stego_path'  => $filePath,
            'file_type'   => $file['file_type'],
            'stego_key'   => $stegoKey,
            'steg_method' => $stegMethod,
            'steps'       => $chainSteps,
        ]);

        if (!$result['success']) return ['success'=>false,'message'=>$result['message'] ?? 'Extraction failed'];
        return ['success'=>true,'payload'=>$result['payload'] ?? '','is_binary'=>$result['is_binary'] ?? false,'message'=>'Extraction successful!'];
    }

    public function getUserOperations(int $userId, int $limit = 20): array {
        return $this->db->fetchAll(
            "SELECT o.*,f.original_filename,f.file_type FROM stego_operations o
             JOIN media_files f ON o.carrier_file_id=f.file_id
             WHERE o.user_id=? ORDER BY o.created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public function createShareLink(int $opId, int $userId, ?string $password,
                                    int $maxDownloads, ?string $expiresAt, string $chainHint = ''): array {
        $op = $this->db->fetchOne(
            "SELECT * FROM stego_operations WHERE op_id=? AND user_id=? AND status='completed'",
            [$opId, $userId]
        );
        if (!$op) return ['success'=>false,'message'=>'Operation not found or not completed.'];

        $token   = bin2hex(random_bytes(64));
        $pwHash  = $password ? password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]) : null;
        $shareId = $this->db->insert(
            "INSERT INTO shared_files (op_id,shared_by,share_token,password_hash,max_downloads,chain_hint,expires_at) VALUES (?,?,?,?,?,?,?)",
            [$opId, $userId, $token, $pwHash, $maxDownloads, $chainHint, $expiresAt]
        );
        return ['success'=>true,'share_id'=>$shareId,'url'=>SITE_URL.'/share.php?t='.$token,'token'=>$token];
    }
}
