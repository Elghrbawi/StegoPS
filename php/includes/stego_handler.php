<?php
require_once __DIR__ . '/../config/database.php';

class StegoHandler {
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }
    private ?int $guestStorageUserId = null;

    public function getGuestStorageUserId(): int {
        return $this->resolveStorageUserId(0);
    }

    private function resolveStorageUserId(int $userId): int {
        if ($userId > 0) return $userId;
        if ($this->guestStorageUserId !== null) return $this->guestStorageUserId;
        $guest = $this->db->fetchOne("SELECT user_id FROM users WHERE username='guest_storage' LIMIT 1");
        if ($guest && !empty($guest['user_id'])) { $this->guestStorageUserId = (int)$guest['user_id']; return $this->guestStorageUserId; }
        $salt = bin2hex(random_bytes(8));
        $rawPass = 'guest_storage_' . bin2hex(random_bytes(12));
        $passHash = password_hash($rawPass . $salt, PASSWORD_BCRYPT, ['cost'=>12]);
        $stegKey = substr(bin2hex(random_bytes(32)), 0, 32);
        $id = $this->db->insert("INSERT INTO users (username,email,password_hash,salt,steg_key,role,is_verified,is_active) VALUES (?,?,?,?,?,'user',1,1)", ['guest_storage', null, $passHash, $salt, $stegKey]);
        $this->guestStorageUserId = (int)$id;
        return $this->guestStorageUserId;
    }

    private function ensureDirs(): void {
        foreach (['carriers','stego','payloads'] as $d) { $p = UPLOAD_PATH . $d; if (!is_dir($p)) mkdir($p, 0755, true); }
    }

    private function makeFilename(string $prefix, int $userId, string $ext): string {
        $uname = 'guest';
        if ($userId > 0) { $row = $this->db->fetchOne("SELECT username FROM users WHERE user_id=?", [$userId]); if ($row) $uname = preg_replace('/[^a-z0-9]/', '', strtolower($row['username'])); }
        return $uname . '_' . $prefix . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
    }

    public function uploadCarrier(array $file, int $userId): array {
        $storageUserId = $this->resolveStorageUserId($userId);
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return ['success'=>false,'message'=>'No file received or invalid upload.'];
        $allowed = [
            'image' => ['image/png','image/jpeg','image/jpg','image/bmp','image/x-bmp','image/x-ms-bmp'],
            'audio' => [
                'audio/wav','audio/x-wav','audio/wave','audio/vnd.wave',
                'audio/mpeg','audio/mp3','audio/mpeg3','audio/x-mpeg-3',
                'audio/ogg','application/ogg','audio/flac','audio/x-flac',
                'audio/aac','audio/x-aac','audio/aacp',
                'audio/mp4','audio/x-m4a','audio/m4a',
                'audio/opus','audio/x-ms-wma',
                'audio/aiff','audio/x-aiff','audio/basic','audio/webm',
            ],
        ];
        $mime = @mime_content_type($file['tmp_name']) ?: $file['type'];
        $type = null;
        foreach ($allowed as $t => $mimes) { if (in_array($mime, $mimes)) { $type = $t; break; } }
        if (!$type) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $extMap = [
                'png'=>'image','jpg'=>'image','jpeg'=>'image','bmp'=>'image',
                'wav'=>'audio','mp3'=>'audio','ogg'=>'audio','flac'=>'audio',
                'aac'=>'audio','m4a'=>'audio','mp4'=>'audio','opus'=>'audio',
                'wma'=>'audio','aiff'=>'audio','aif'=>'audio','au'=>'audio','webm'=>'audio',
            ];
            if (isset($extMap[$ext])) $type = $extMap[$ext];
        }
        if (!$type) return ['success'=>false,'message'=>'Unsupported file. Use PNG/JPG/BMP (image) or WAV/MP3/OGG/FLAC/AAC/M4A/OPUS/WMA/AIFF/AU (audio).'];
        if ($file['size'] > MAX_FILE_SIZE) return ['success'=>false,'message'=>'File too large. Max 200MB.'];
        $this->ensureDirs();
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $stored = $this->makeFilename('carrier', $storageUserId, $ext);
        $destDir = UPLOAD_PATH . 'carriers' . DIRECTORY_SEPARATOR;
        $dest = $destDir . $stored;
        if (!move_uploaded_file($file['tmp_name'], $dest)) return ['success'=>false,'message'=>'Failed to save file. Check permissions: '.$destDir];
        $hash = hash_file('sha256', $dest);
        $fileId = $this->db->insert("INSERT INTO media_files (user_id,original_filename,stored_filename,file_type,mime_type,file_size_bytes,file_hash,storage_path) VALUES (?,?,?,?,?,?,?,?)", [$storageUserId, $file['name'], $stored, $type, $mime, $file['size'], $hash, 'carriers/'.$stored]);
        if ($userId > 0) $this->db->query("UPDATE users SET storage_used=storage_used+? WHERE user_id=?", [$file['size'], $storageUserId]);
        return ['success'=>true,'file_id'=>$fileId,'file_type'=>$type,'stored'=>$stored,'abs_path'=>$dest,'file_size'=>$file['size']];
    }

    private function callPython(string $endpoint, array $data, string $method = 'POST'): array {
        $url = PYTHON_API_URL . $endpoint;
        $curl = curl_init($url);
        $headers = ['Authorization: Bearer ' . PYTHON_API_SECRET, 'Content-Type: application/json'];
        if ($method === 'GET') { curl_setopt($curl, CURLOPT_HTTPGET, true); } else { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data)); }
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>300, CURLOPT_HTTPHEADER=>$headers, CURLOPT_CONNECTTIMEOUT=>8]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($curl);
        curl_close($curl);
        if ($curlErr || $httpCode === 0) return ['success'=>false,'message'=>'Cannot reach Python engine at '.PYTHON_API_URL.' — is server.py running? ('.$curlErr.')'];
        if ($httpCode !== 200) { $err = json_decode($response, true); return ['success'=>false,'message'=>$err['error'] ?? 'Python engine error (HTTP '.$httpCode.')']; }
        $decoded = json_decode($response, true);
        if (!$decoded) return ['success'=>false,'message'=>'Invalid JSON from Python engine'];
        return array_merge(['success'=>true], $decoded);
    }

    public function pingEngine(): array { return $this->callPython('/health', [], 'GET'); }

    public function getMethodsRaw(string $fileType, string $payloadType = 'text'): array {
        $url  = PYTHON_API_URL . '/api/methods/' . urlencode($fileType) . '?payload=' . urlencode($payloadType);
        $opts = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_CONNECTTIMEOUT=>8,
                 CURLOPT_HTTPHEADER=>['Authorization: Bearer '.PYTHON_API_SECRET,'Content-Type: application/json']];

        // Try GET first, fall back to POST
        foreach (['GET','POST'] as $m) {
            $curl = curl_init($url);
            if ($m === 'POST') { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([])); }
            else { curl_setopt($curl, CURLOPT_HTTPGET, true); }
            curl_setopt_array($curl, $opts);
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($curl);
            curl_close($curl);
            if ($curlErr || $httpCode === 0) continue;
            if ($httpCode === 405) continue;
            if ($httpCode !== 200) return ['success'=>false,'methods'=>[],'message'=>'Engine error HTTP '.$httpCode];
            break;
        }

        if ($curlErr || ($httpCode ?? 0) === 0) return ['success'=>false,'methods'=>[],'message'=>'Engine offline: '.$curlErr];
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) return ['success'=>false,'methods'=>[],'message'=>'Bad response from engine'];

        return ['success'=>true,'methods'=>$decoded];
    }

    public function getFileById(int $fileId, int $userId): ?array {
        $storageUserId = $this->resolveStorageUserId($userId);
        return $this->db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?", [$fileId, $storageUserId]);
    }

    public function embed(int $userId, int $fileId, ?int $chainId, string $payloadType, string $payload, string $stegoKey, string $stegMethod, array $chainSteps): array {
        $storageUserId = $this->resolveStorageUserId($userId);
        $carrier = $this->db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?", [$fileId, $storageUserId]);
        if (!$carrier) return ['success'=>false,'message'=>'Carrier file not found.'];
        $opId = $this->db->insert("INSERT INTO stego_operations (user_id,chain_id,carrier_file_id,operation_type,payload_type,payload_size,stego_technique,status) VALUES (?,?,?,?,?,?,?,'processing')", [$storageUserId, $chainId, $fileId, 'embed', $payloadType, strlen($payload), $stegMethod]);
        $carrierPath = UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $carrier['storage_path']);
        $outputDir = UPLOAD_PATH . 'stego' . DIRECTORY_SEPARATOR;
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
        // Decode STEGMEDIA1 envelope (set by mediaPayload()) to extract filename
        // and send raw base64 binary + filename separately to Python engine
        $pyPayload     = $payload;
        $pyPayloadType = $payloadType === 'file' ? 'file_b64' : $payloadType;
        $pyFilename    = 'hidden_file';
        if ($payloadType === 'file' || $payloadType === 'media') {
            $decoded = base64_decode($payload, true);
            if ($decoded !== false && str_starts_with($decoded, "STEGMEDIA1\n")) {
                $nl2 = strpos($decoded, "\n\n", 11);
                if ($nl2 !== false) {
                    $meta = @json_decode(substr($decoded, 11, $nl2 - 11), true);
                    if (is_array($meta) && !empty($meta['name'])) {
                        $pyFilename = $meta['name'];
                    }
                    // Re-encode just the binary part for Python
                    $pyPayload     = base64_encode(substr($decoded, $nl2 + 2));
                    $pyPayloadType = 'file_b64';
                }
            }
        }
        $result = $this->callPython('/api/embed', ['carrier_path'=>$carrierPath,'output_dir'=>$outputDir,'payload'=>$pyPayload,'payload_type'=>$pyPayloadType,'payload_filename'=>$pyFilename,'stego_key'=>$stegoKey,'steg_method'=>$stegMethod,'file_type'=>$carrier['file_type'],'steps'=>$chainSteps,'op_id'=>$opId]);
        if (!$result['success']) { $this->db->query("UPDATE stego_operations SET status='failed',error_message=? WHERE op_id=?", [$result['message'], $opId]); return ['success'=>false,'message'=>$result['message']]; }
        $outFilename = $result['output_filename'];
        $outPath = $outputDir . $outFilename;
        $outSize = file_exists($outPath) ? filesize($outPath) : 0;
        $outHash = file_exists($outPath) ? hash_file('sha256', $outPath) : '';
        $ext = strtolower(pathinfo($outFilename, PATHINFO_EXTENSION));
        $mimeMap = ['png'=>'image/png','wav'=>'audio/wav'];
        $outFileId = $this->db->insert("INSERT INTO media_files (user_id,original_filename,stored_filename,file_type,mime_type,file_size_bytes,file_hash,storage_path,is_processed) VALUES (?,?,?,?,?,?,?,?,1)", [$storageUserId, 'stego_output.'.$ext, $outFilename, $carrier['file_type'], $mimeMap[$ext]??'application/octet-stream', $outSize, $outHash, 'stego/'.$outFilename]);
        $this->db->query("UPDATE stego_operations SET status='completed',output_file_id=?,completed_at=NOW() WHERE op_id=?", [$outFileId, $opId]);
        if ($userId > 0) $this->db->query("UPDATE users SET storage_used=storage_used+? WHERE user_id=?", [$outSize, $storageUserId]);
        return ['success'=>true,'op_id'=>$opId,'file_id'=>$outFileId,'carrier_size'=>$result['carrier_size']??$carrier['file_size_bytes'],'output_size'=>$outSize,'payload_size'=>$result['payload_size']??strlen($payload),'steg_method'=>$stegMethod,'layers'=>count($chainSteps),'message'=>'Embed successful!'];
    }

    public function extract(int $userId, int $fileId, string $stegoKey, string $stegMethod, array $chainSteps): array {
        $storageUserId = $this->resolveStorageUserId($userId);
        $file = $this->db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?", [$fileId, $storageUserId]);
        if (!$file) return ['success'=>false,'message'=>'File not found.'];
        $filePath = UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $file['storage_path']);
        $result = $this->callPython('/api/extract', ['stego_path'=>$filePath,'file_type'=>$file['file_type'],'stego_key'=>$stegoKey,'steg_method'=>$stegMethod,'steps'=>$chainSteps]);
        if (!$result['success']) return ['success'=>false,'message'=>$result['message']??'Extraction failed'];
        $isBinary  = $result['is_binary']  ?? false;
        $isFile    = $result['is_file']    ?? false;
        $filename  = $result['filename']   ?? '';
        $rawPayload = $result['payload']   ?? '';

        // If Python returned a wrapped file, re-encode as STEGMEDIA1 so the
        // frontend (extract.php) can offer a proper named download
        if ($isFile && $filename !== '' && $isBinary) {
            $bin     = base64_decode($rawPayload, true) ?: '';
            $ext     = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $mimeMap = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
                        'gif'=>'image/gif','bmp'=>'image/bmp','webp'=>'image/webp',
                        'pdf'=>'application/pdf','txt'=>'text/plain',
                        'mp3'=>'audio/mpeg','mp4'=>'video/mp4','zip'=>'application/zip'];
            $mime    = $mimeMap[$ext] ?? 'application/octet-stream';
            $meta    = json_encode(['name'=>$filename,'type'=>$mime,'size'=>strlen($bin)], JSON_UNESCAPED_SLASHES);
            $wrapped = base64_encode("STEGMEDIA1\n{$meta}\n\n{$bin}");
            return ['success'=>true,'payload'=>$wrapped,'is_binary'=>true,'is_file'=>true,'filename'=>$filename,'message'=>'Extraction successful!'];
        }
        return ['success'=>true,'payload'=>$rawPayload,'is_binary'=>$isBinary,'is_file'=>false,'filename'=>'','message'=>'Extraction successful!'];
    }

    public function getUserOperations(int $userId, int $limit = 20): array {
        return $this->db->fetchAll("SELECT o.*,f.original_filename,f.file_type FROM stego_operations o JOIN media_files f ON o.carrier_file_id=f.file_id WHERE o.user_id=? ORDER BY o.created_at DESC LIMIT ?", [$userId, $limit]);
    }

    public function createShareLink(int $opId, int $userId, ?string $password, int $maxDownloads, ?string $expiresAt, string $chainHint = ''): array {
        $op = $this->db->fetchOne("SELECT * FROM stego_operations WHERE op_id=? AND user_id=? AND status='completed'", [$opId, $userId]);
        if (!$op) return ['success'=>false,'message'=>'Operation not found or not completed.'];
        $urow = $this->db->fetchOne("SELECT username FROM users WHERE user_id=?", [$userId]);
        $uslug = $urow ? preg_replace('/[^a-z0-9]/', '', strtolower($urow['username'])) : 'u';
        $token = $uslug . '_' . bin2hex(random_bytes(6));
        $pwHash = $password ? password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]) : null;
        $shareId = $this->db->insert("INSERT INTO shared_files (op_id,shared_by,share_token,password_hash,max_downloads,chain_hint,expires_at) VALUES (?,?,?,?,?,?,?)", [$opId, $userId, $token, $pwHash, $maxDownloads, $chainHint, $expiresAt]);
        return ['success'=>true,'share_id'=>$shareId,'url'=>SITE_URL.'/share.php?t='.$token,'token'=>$token];
    }
}