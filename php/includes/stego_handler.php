<?php
require_once __DIR__ . '/../config/database.php';
class StegoHandler {
private Database $db;
public function __construct() { $this->db = Database::getInstance(); }
/* ── ENSURE UPLOAD DIRS EXIST ── */
private function ensureDirs(): void {
    $dirs = ['carriers', 'stego', 'payloads'];
    foreach ($dirs as $dir) {
        $path = UPLOAD_PATH . $dir;
        if (!is_dir($path)) mkdir($path, 0755, true);
    }
}
/* ── UPLOAD CARRIER FILE ── */
public function uploadCarrier(array $file, int $userId): array {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
        return ['success'=>false,'message'=>'No file received or invalid upload.'];
    $allowed = [
        'image'=>['image/png','image/jpeg','image/jpg','image/bmp','image/x-bmp','image/x-ms-bmp'],
        'audio'=>['audio/wav','audio/x-wav','audio/mpeg','audio/mp3','audio/x-mp3'],
        'video'=>['video/mp4','video/avi','video/x-msvideo','video/quicktime','video/x-ms-wmv'],
    ];
    $mime = @mime_content_type($file['tmp_name']) ?: $file['type'];
    $type = null;
    foreach ($allowed as $t=>$mimes) { if (in_array($mime,$mimes)){$type=$t;break;} }
    if (!$type) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extMap = ['png'=>'image','jpg'=>'image','jpeg'=>'image','bmp'=>'image','gif'=>'image','wav'=>'audio','mp3'=>'audio','mp4'=>'video','avi'=>'video','mov'=>'video'];
        if (isset($extMap[$ext])) $type = $extMap[$ext];
    }
    if (!$type) return ['success'=>false,'message'=>'Unsupported file type. Use PNG/JPG/BMP (image), WAV/MP3 (audio), or MP4/AVI (video).'];
    if ($file['size'] > MAX_FILE_SIZE) return ['success'=>false,'message'=>'File too large. Maximum is 200MB.'];
    $this->ensureDirs();
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $stored  = bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    $destDir = UPLOAD_PATH . 'carriers' . DIRECTORY_SEPARATOR;
    $dest    = $destDir . $stored;
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest))
        return ['success'=>false,'message'=>'Failed to save uploaded file. Check folder permissions for: ' . $destDir];
    $hash   = hash_file('sha256', $dest);
    $fileId = $this->db->insert(
        "INSERT INTO media_files (user_id,original_filename,stored_filename,file_type,mime_type,file_size_bytes,file_hash,storage_path) VALUES (?,?,?,?,?,?,?,?)",
        [$userId,$file['name'],$stored,$type,$mime,$file['size'],$hash,'carriers/'.$stored]
    );
    if ($userId > 0) $this->db->query("UPDATE users SET storage_used=storage_used+? WHERE user_id=?",[$file['size'],$userId]);
    return ['success'=>true,'file_id'=>$fileId,'file_type'=>$type,'stored'=>$stored];
}
/* ── CALL PYTHON ENGINE ── */
private function callPythonEngine(string $endpoint, array $data, array $files=[]): array {
    $url  = PYTHON_API_URL . $endpoint;
    $curl = curl_init($url);
    $post = $data;
    foreach ($files as $k=>$p) { $post[$k] = new CURLFile($p); }
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>300,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.PYTHON_API_SECRET]]);
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($httpCode !== 200) { $err=json_decode($response,true); return ['success'=>false,'message'=>$err['error']??'Python engine error (HTTP '.$httpCode.')']; }
    return ['success'=>true,'data'=>$response];
}
/* ── EMBED ── */
public function embed(int $userId, int $fileId, ?int $chainId, string $payloadType, string $payload, string $stegoKey, array $chainSteps): array {
    $carrier = $this->db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?",[$fileId,$userId]);
    if (!$carrier) return ['success'=>false,'message'=>'Carrier file not found.'];
    $technique = match($carrier['file_type']){'image'=>'lsb','audio'=>'phase_coding','video'=>'frame_embed',default=>'lsb'};
    $opId = $this->db->insert("INSERT INTO stego_operations (user_id,chain_id,carrier_file_id,operation_type,payload_type,payload_size,stego_technique,status) VALUES (?,?,?,?,?,?,?,'processing')",[$userId,$chainId,$fileId,'embed',$payloadType,strlen($payload),$technique]);
    $carrierPath = UPLOAD_PATH . str_replace('/',DIRECTORY_SEPARATOR,$carrier['storage_path']);
    $result = $this->callPythonEngine('/api/embed/'.$carrier['file_type'],['payload'=>$payload,'stego_key'=>$stegoKey,'steps'=>json_encode($chainSteps),'op_id'=>$opId],['carrier'=>$carrierPath]);
    if (!$result['success']) { $this->db->query("UPDATE stego_operations SET status='failed',error_message=? WHERE op_id=?",[$result['message'],$opId]); return ['success'=>false,'message'=>$result['message']]; }
    $this->ensureDirs();
    $ext = $carrier['file_type']==='audio'?'wav':($carrier['file_type']==='video'?'mp4':'png');
    $outName = 'stego_'.bin2hex(random_bytes(8)).'.'.$ext;
    $outPath = UPLOAD_PATH.'stego'.DIRECTORY_SEPARATOR.$outName;
    file_put_contents($outPath, $result['data']);
    $outFileId = $this->db->insert("INSERT INTO media_files (user_id,original_filename,stored_filename,file_type,mime_type,file_size_bytes,file_hash,storage_path,is_processed) VALUES (?,?,?,?,?,?,?,?,1)",[$userId,'stego_output.'.$ext,$outName,$carrier['file_type'],$carrier['mime_type'],strlen($result['data']),hash('sha256',$result['data']),'stego/'.$outName]);
    $this->db->query("UPDATE stego_operations SET status='completed',output_file_id=?,completed_at=NOW() WHERE op_id=?",[$outFileId,$opId]);
    return ['success'=>true,'op_id'=>$opId,'file_id'=>$outFileId,'message'=>'Embed successful!'];
}
/* ── EXTRACT ── */
public function extract(int $userId, int $fileId, string $stegoKey, array $chainSteps): array {
    $file = $this->db->fetchOne("SELECT * FROM media_files WHERE file_id=? AND user_id=?",[$fileId,$userId]);
    if (!$file) return ['success'=>false,'message'=>'File not found.'];
    $filePath = UPLOAD_PATH . str_replace('/',DIRECTORY_SEPARATOR,$file['storage_path']);
    $result = $this->callPythonEngine('/api/extract/'.$file['file_type'],['stego_key'=>$stegoKey,'steps'=>json_encode($chainSteps)],['stego_file'=>$filePath]);
    if (!$result['success']) return ['success'=>false,'message'=>$result['message']];
    return ['success'=>true,'payload'=>$result['data'],'message'=>'Extraction successful!'];
}
/* ── GET USER OPERATIONS ── */
public function getUserOperations(int $userId, int $limit=20): array {
    return $this->db->fetchAll("SELECT o.*,f.original_filename,f.file_type FROM stego_operations o JOIN media_files f ON o.carrier_file_id=f.file_id WHERE o.user_id=? ORDER BY o.created_at DESC LIMIT ?",[$userId,$limit]);
}
/* ── CREATE SHARE LINK ── */
public function createShareLink(int $opId, int $userId, ?string $password, int $maxDownloads, ?string $expiresAt, string $chainHint=''): array {
    $op = $this->db->fetchOne("SELECT * FROM stego_operations WHERE op_id=? AND user_id=? AND status='completed'",[$opId,$userId]);
    if (!$op) return ['success'=>false,'message'=>'Operation not found or not completed.'];
    $token    = bin2hex(random_bytes(64));
    $pwHash   = $password ? password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]) : null;
    $shareUrl = SITE_URL.'/share.php?t='.$token;
    $shareId  = $this->db->insert("INSERT INTO shared_files (op_id,shared_by,share_token,password_hash,max_downloads,chain_hint,expires_at) VALUES (?,?,?,?,?,?,?)",[$opId,$userId,$token,$pwHash,$maxDownloads,$chainHint,$expiresAt]);
    return ['success'=>true,'share_id'=>$shareId,'url'=>$shareUrl,'token'=>$token];
}
}
