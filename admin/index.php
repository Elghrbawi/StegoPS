<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../php/config/database.php';
$adminAuth = new AdminAuth();
$adminAuth->requireAdmin();
$admin = $adminAuth->currentAdmin();
$db = Database::getInstance();
function db_escape($str) {
    global $db;
    if (method_exists($db, 'escape')) return $db->escape($str);
    if (method_exists($db, 'getConnection')) {
        $conn = $db->getConnection();
        if ($conn instanceof PDO) return substr($conn->quote($str), 1, -1);
    }
    return addslashes($str);
}
$page = isset($_GET['page']) ? preg_replace('/[^a-z_]/', '', $_GET['page']) : 'dashboard';
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$message = '';
$error = '';
function updateChainEngineMap($slug, $module, $remove = false) {
    $file = __DIR__ . '/../Server/chain_engine.py';
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    $pattern = '/ALGO_MAP\s*=\s*\{([^}]*)\}/s';
    if (preg_match($pattern, $content, $matches)) {
        $mapStr = $matches[1];
        $lines = explode("\n", $mapStr);
        $newLines = [];
        $found = false;
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            if (strpos($line, "'$slug'") !== false) {
                if ($remove) continue;
                $found = true;
                $newLines[] = "    '$slug': '$module',";
            } else {
                $newLines[] = $line;
            }
        }
        if (!$remove && !$found) $newLines[] = "    '$slug': '$module',";
        $newMap = "ALGO_MAP = {\n" . implode("\n", $newLines) . "\n}";
        $newContent = preg_replace($pattern, $newMap, $content);
        file_put_contents($file, $newContent);
        return true;
    }
    return false;
}
function backupDeletedAlgo($algo_id) {
    global $db;
    $algo = $db->fetchOne("SELECT * FROM encryption_algorithms WHERE algo_id = $algo_id");
    if (!$algo) return false;
    $pyFile = __DIR__ . "/../Server/{$algo['python_module']}.py";
    $code = file_exists($pyFile) ? file_get_contents($pyFile) : '';
    $backupDir = __DIR__ . '/../Server/backups';
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    $backup = [
        'algo_id' => $algo['algo_id'],
        'algo_name' => $algo['algo_name'],
        'algo_slug' => $algo['algo_slug'],
        'category' => $algo['category'],
        'requires_key' => $algo['requires_key'],
        'description' => $algo['description'],
        'python_module' => $algo['python_module'],
        'python_code' => $code,
        'deleted_at' => date('Y-m-d H:i:s')
    ];
    $filename = $backupDir . '/algo_backup_' . $algo['algo_slug'] . '_' . time() . '.json';
    file_put_contents($filename, json_encode($backup, JSON_PRETTY_PRINT));
    return true;
}
function getBackups() {
    $backupDir = __DIR__ . '/../Server/backups';
    if (!is_dir($backupDir)) return [];
    $files = glob($backupDir . '/algo_backup_*.json');
    $backups = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $data['backup_file'] = basename($file);
            $backups[] = $data;
        }
    }
    usort($backups, function($a, $b) {
        return strtotime($b['deleted_at']) - strtotime($a['deleted_at']);
    });
    return $backups;
}
function deleteMediaFile($file_id) {
    global $db;
    $media = $db->fetchOne("SELECT storage_path FROM media_files WHERE file_id = $file_id");
    if ($media) {
        $fullPath = UPLOAD_PATH . ltrim($media['storage_path'], '/');
        if (file_exists($fullPath)) {
            if (!unlink($fullPath)) {
                error_log("Failed to delete file: $fullPath");
            }
        } else {
            error_log("File not found: $fullPath");
        }
        $db->query("DELETE FROM media_files WHERE file_id = $file_id");
        return true;
    }
    return false;
}
function deleteOperation($op_id) {
    global $db;
    $op = $db->fetchOne("SELECT carrier_file_id, output_file_id FROM stego_operations WHERE op_id = $op_id");
    if ($op) {
        $db->query("DELETE FROM shared_files WHERE op_id = $op_id");
        $db->query("DELETE FROM stego_operations WHERE op_id = $op_id");
        if ($op['carrier_file_id']) deleteMediaFile($op['carrier_file_id']);
        if ($op['output_file_id']) deleteMediaFile($op['output_file_id']);
        return true;
    }
    return false;
}
function deleteAllOperations() {
    global $db;
    $ops = $db->fetchAll("SELECT op_id FROM stego_operations");
    foreach ($ops as $o) {
        deleteOperation($o['op_id']);
    }
}
function deleteAllMediaFiles() {
    global $db;
    deleteAllOperations();
    $db->query("SET FOREIGN_KEY_CHECKS=0");
    $db->query("TRUNCATE TABLE media_files");
    $db->query("SET FOREIGN_KEY_CHECKS=1");
}
if ($action === 'delete_user' && $id) {
    if ($id != $admin['user_id']) {
        $db->query("DELETE FROM users WHERE user_id = $id");
        $message = 'User deleted.';
    } else {
        $error = 'Cannot delete your own admin account.';
    }
}
if ($action === 'delete_algo' && $id) {
    $algo = $db->fetchOne("SELECT python_module, algo_slug FROM encryption_algorithms WHERE algo_id = $id");
    if ($algo) {
        backupDeletedAlgo($id);
        $pyFile = __DIR__ . "/../Server/{$algo['python_module']}.py";
        if (file_exists($pyFile)) unlink($pyFile);
        updateChainEngineMap($algo['algo_slug'], '', true);
        $db->query("DELETE FROM encryption_algorithms WHERE algo_id = $id");
        $message = 'Algorithm deleted and backed up.';
    }
}
if ($action === 'restore_algo') {
    $backupFile = isset($_POST['backup_file']) ? $_POST['backup_file'] : '';
    if ($backupFile) {
        $backupDir = __DIR__ . '/../Server/backups';
        $filePath = $backupDir . '/' . $backupFile;
        if (file_exists($filePath)) {
            $data = json_decode(file_get_contents($filePath), true);
            if ($data) {
                $name = db_escape($data['algo_name']);
                $slug = db_escape($data['algo_slug']);
                $category = $data['category'];
                $requires_key = $data['requires_key'];
                $desc = db_escape($data['description']);
                $module = db_escape($data['python_module']);
                $code = $data['python_code'];
                $db->query("INSERT INTO encryption_algorithms (algo_name, algo_slug, category, requires_key, description, python_module) VALUES ('$name', '$slug', '$category', $requires_key, '$desc', '$module')");
                $newId = $db->getConnection()->lastInsertId();
                $pyFile = __DIR__ . "/../Server/{$module}.py";
                file_put_contents($pyFile, $code);
                updateChainEngineMap($slug, $module);
                unlink($filePath);
                $message = 'Algorithm restored successfully.';
            } else {
                $error = 'Invalid backup file.';
            }
        } else {
            $error = 'Backup file not found.';
        }
    }
}
if ($action === 'delete_chain' && $id) {
    $db->query("DELETE FROM encryption_chains WHERE chain_id = $id");
    $message = 'Chain deleted.';
}
if ($action === 'delete_op' && $id) {
    if (deleteOperation($id)) $message = 'Operation and associated files deleted.';
    else $error = 'Operation not found.';
}
if ($action === 'delete_media' && $id) {
    if (deleteMediaFile($id)) $message = 'Media file deleted.';
    else $error = 'Media file not found.';
}
if ($action === 'delete_share' && $id) {
    $db->query("DELETE FROM shared_files WHERE share_id = $id");
    $message = 'Share link deleted.';
}
if ($action === 'clear_logs') {
    $db->query("TRUNCATE TABLE operation_logs");
    $message = 'Logs cleared.';
}
if ($action === 'delete_all_media') {
    deleteAllMediaFiles();
    $message = 'All media files deleted.';
}
if ($action === 'delete_all_ops') {
    deleteAllOperations();
    $message = 'All operations and associated files deleted.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add_user') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        if ($username && $password) {
            $salt = bin2hex(random_bytes(8));
            $hash = password_hash($password . $salt, PASSWORD_BCRYPT);
            $steg_key = bin2hex(random_bytes(16));
            $db->query("INSERT INTO users (username, email, password_hash, salt, steg_key, role) VALUES ('" . db_escape($username) . "', '" . db_escape($email) . "', '$hash', '$salt', '$steg_key', '$role')");
            $message = 'User added.';
        } else {
            $error = 'Username and password required.';
        }
    } elseif ($action === 'edit_user' && $id) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $active = isset($_POST['is_active']) ? 1 : 0;
        $db->query("UPDATE users SET username = '" . db_escape($username) . "', email = '" . db_escape($email) . "', role = '$role', is_active = $active WHERE user_id = $id");
        if (!empty($_POST['new_password'])) {
            $salt = bin2hex(random_bytes(8));
            $hash = password_hash($_POST['new_password'] . $salt, PASSWORD_BCRYPT);
            $db->query("UPDATE users SET password_hash = '$hash', salt = '$salt' WHERE user_id = $id");
        }
        $message = 'User updated.';
    } elseif ($action === 'add_algo') {
        $name = trim($_POST['algo_name']);
        $slug = trim($_POST['algo_slug']);
        $category = $_POST['category'];
        $module = trim($_POST['python_module']);
        $requires_key = isset($_POST['requires_key']) ? 1 : 0;
        $desc = trim($_POST['description']);
        $code = trim($_POST['python_code']);
        if ($name && $slug && $module && $code) {
            $pyFile = __DIR__ . "/../Server/{$module}.py";
            if (file_exists($pyFile)) {
                $error = 'Module file already exists.';
            } else {
                file_put_contents($pyFile, $code);
                $db->query("INSERT INTO encryption_algorithms (algo_name, algo_slug, category, requires_key, description, python_module) VALUES ('" . db_escape($name) . "', '" . db_escape($slug) . "', '$category', $requires_key, '" . db_escape($desc) . "', '" . db_escape($module) . "')");
                updateChainEngineMap($slug, $module);
                $message = 'Algorithm added.';
            }
        } else {
            $error = 'All fields required.';
        }
    } elseif ($action === 'edit_algo' && $id) {
        $name = trim($_POST['algo_name']);
        $slug = trim($_POST['algo_slug']);
        $category = $_POST['category'];
        $module = trim($_POST['python_module']);
        $requires_key = isset($_POST['requires_key']) ? 1 : 0;
        $desc = trim($_POST['description']);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $code = trim($_POST['python_code']);
        $old = $db->fetchOne("SELECT * FROM encryption_algorithms WHERE algo_id = $id");
        if (!$old) { $error = 'Algorithm not found.'; }
        else {
            if ($code) {
                if ($old['python_module'] != $module) {
                    $oldFile = __DIR__ . "/../Server/{$old['python_module']}.py";
                    if (file_exists($oldFile)) unlink($oldFile);
                }
                $pyFile = __DIR__ . "/../Server/{$module}.py";
                file_put_contents($pyFile, $code);
            }
            $db->query("UPDATE encryption_algorithms SET algo_name = '" . db_escape($name) . "', algo_slug = '" . db_escape($slug) . "', category = '$category', requires_key = $requires_key, description = '" . db_escape($desc) . "', python_module = '" . db_escape($module) . "', is_active = $active WHERE algo_id = $id");
            updateChainEngineMap($slug, $module);
            if ($old['algo_slug'] != $slug) updateChainEngineMap($old['algo_slug'], '', true);
            $message = 'Algorithm updated.';
        }
    }
}
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $ajax = $_GET['ajax'];
    if ($ajax === 'get_user' && $id) {
        $u = $db->fetchOne("SELECT user_id, username, email, role, is_active FROM users WHERE user_id = $id");
        echo json_encode($u);
        exit;
    }
    if ($ajax === 'get_algo' && $id) {
        $a = $db->fetchOne("SELECT * FROM encryption_algorithms WHERE algo_id = $id");
        if ($a) {
            $pyFile = __DIR__ . "/../Server/{$a['python_module']}.py";
            $a['python_code'] = file_exists($pyFile) ? file_get_contents($pyFile) : '';
        }
        echo json_encode($a);
        exit;
    }
    if ($ajax === 'get_chain' && $id) {
        $chain = $db->fetchOne("SELECT c.*, u.username FROM encryption_chains c JOIN users u ON c.user_id = u.user_id WHERE c.chain_id = $id");
        if ($chain) {
            $steps = $db->fetchAll("SELECT s.*, a.algo_name FROM chain_steps s JOIN encryption_algorithms a ON s.algo_id = a.algo_id WHERE s.chain_id = $id ORDER BY s.step_order");
            $chain['steps'] = $steps;
        }
        echo json_encode($chain);
        exit;
    }
    if ($ajax === 'test_algo') {
        $algo_id = isset($_POST['algo_id']) ? (int)$_POST['algo_id'] : 0;
        $plaintext = isset($_POST['plaintext']) ? $_POST['plaintext'] : 'Hello';
        $key = isset($_POST['key']) ? $_POST['key'] : 'testkey';
        $algo = $db->fetchOne("SELECT algo_slug FROM encryption_algorithms WHERE algo_id = $algo_id");
        if (!$algo) { echo json_encode(['error' => 'Algorithm not found']); exit; }
        $url = PYTHON_API_URL . '/api/test_algo';
        $payload = json_encode([
            'algo_slug' => $algo['algo_slug'],
            'plaintext' => $plaintext,
            'key' => $key
        ]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . PYTHON_API_SECRET
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            echo json_encode(['error' => 'cURL error: ' . $err]);
        } elseif ($httpCode != 200) {
            echo json_encode(['error' => 'API error (HTTP ' . $httpCode . '): ' . $response]);
        } else {
            echo $response;
        }
        exit;
    }
}
$stats = [
    'users' => $db->fetchOne("SELECT COUNT(*) as c FROM users")['c'] ?? 0,
    'ops' => $db->fetchOne("SELECT COUNT(*) as c FROM stego_operations")['c'] ?? 0,
    'algos' => $db->fetchOne("SELECT COUNT(*) as c FROM encryption_algorithms")['c'] ?? 0,
    'shares' => $db->fetchOne("SELECT COUNT(*) as c FROM shared_files WHERE is_active=1")['c'] ?? 0,
    'chains' => $db->fetchOne("SELECT COUNT(*) as c FROM encryption_chains")['c'] ?? 0,
    'media' => $db->fetchOne("SELECT COUNT(*) as c FROM media_files")['c'] ?? 0,
    'logs' => $db->fetchOne("SELECT COUNT(*) as c FROM operation_logs")['c'] ?? 0,
];
$users = $db->fetchAll("SELECT user_id, username, email, role, is_active, storage_used, created_at, last_login FROM users ORDER BY created_at DESC LIMIT 50");
$algos = $db->fetchAll("SELECT * FROM encryption_algorithms ORDER BY category, algo_name");
$chains = $db->fetchAll("SELECT c.*, u.username FROM encryption_chains c JOIN users u ON c.user_id = u.user_id ORDER BY c.created_at DESC LIMIT 30");
$ops = $db->fetchAll("SELECT o.*, u.username, f.original_filename FROM stego_operations o JOIN users u ON o.user_id = u.user_id LEFT JOIN media_files f ON o.carrier_file_id = f.file_id ORDER BY o.created_at DESC LIMIT 30");
$media = $db->fetchAll("SELECT m.*, u.username FROM media_files m JOIN users u ON m.user_id = u.user_id ORDER BY m.uploaded_at DESC LIMIT 30");
$shares = $db->fetchAll("SELECT s.*, u.username, f.original_filename FROM shared_files s JOIN users u ON s.shared_by = u.user_id JOIN stego_operations o ON s.op_id = o.op_id JOIN media_files f ON o.carrier_file_id = f.file_id ORDER BY s.created_at DESC LIMIT 30");
$logs = $db->fetchAll("SELECT * FROM operation_logs ORDER BY created_at DESC LIMIT 50");
$recentOps = $db->fetchAll("SELECT o.op_id,u.username,f.original_filename,o.operation_type,o.status,o.created_at FROM stego_operations o JOIN users u ON o.user_id = u.user_id JOIN media_files f ON o.carrier_file_id=f.file_id ORDER BY o.created_at DESC LIMIT 15");
$backups = getBackups();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StegoPS — Admin Panel</title>
<link rel="icon" href="logo.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= SITE_URL ?>/frontend/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#050c0e;--surface:#0a1a1f;--surface2:#0f2530;--border:#1a3d4a;--accent:#00e5c8;--accent2:#00b89f;--accent-dim:rgba(0,229,200,0.08);--accent-glow:rgba(0,229,200,0.25);--text:#e8f4f1;--text-dim:#7aa8a0;--text-muted:#3d7068;--danger:#ff4d6d;--danger-dim:rgba(255,77,109,0.1);--warning:#ffb347;--chain1:#7c3aed;--chain2:#2563eb;--chain3:#059669;--chain4:#d97706}
body{font-family:'Syne',sans-serif;background:var(--bg);color:var(--text);padding-top:60px}
.admin-navbar{position:fixed;top:0;left:0;right:0;z-index:200;height:60px;background:rgba(5,12,14,.96);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,77,109,.25);display:flex;align-items:center;justify-content:space-between;padding:0 24px}
.admin-brand{font-family:'Space Mono',monospace;font-size:16px;font-weight:700;color:#ff4d6d;display:flex;align-items:center;gap:8px}
.admin-brand-dot{width:7px;height:7px;background:#ff4d6d;border-radius:50%;animation:pulse 2s infinite;box-shadow:0 0 6px #ff4d6d}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(.7)}}
.admin-user{display:flex;align-items:center;gap:12px;font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)}
.admin-badge{background:rgba(255,77,109,.15);border:1px solid rgba(255,77,109,.3);color:#ff4d6d;font-family:'Space Mono',monospace;font-size:9px;font-weight:700;padding:3px 8px;border-radius:4px;letter-spacing:1px}
.admin-layout{display:flex}
.sidebar{width:220px;background:var(--surface);border-right:1px solid var(--border);padding:16px 12px;position:fixed;top:60px;left:0;bottom:0;height:calc(100vh - 60px);overflow-y:auto;z-index:100;flex-shrink:0}
.sidebar-label{font-family:'Space Mono',monospace;font-size:9px;letter-spacing:2px;color:var(--text-muted);padding:12px 10px 6px;text-transform:uppercase}
.sidebar-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;color:var(--text-dim);text-decoration:none;border:1px solid transparent;font-size:13px;transition:all .2s}
.sidebar-item:hover{background:var(--accent-dim);color:var(--text)}
.sidebar-item.active{background:var(--accent-dim);border-color:var(--border);color:var(--accent)}
.sidebar-icon{font-size:14px;width:18px;text-align:center}
.main-content{margin-left:220px;padding:32px;flex:1;min-height:calc(100vh - 60px)}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:32px;flex-wrap:wrap;gap:16px}
.page-title{font-size:28px;font-weight:800;letter-spacing:-.4px}
.page-sub{font-size:13px;color:var(--text-dim);margin-top:4px;font-family:'Space Mono',monospace}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:24px}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:22px 18px;position:relative;overflow:hidden;text-align:center}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat-card.green::before{background:var(--accent)}.stat-card.purple::before{background:var(--chain1)}.stat-card.blue::before{background:var(--chain2)}.stat-card.orange::before{background:var(--warning)}.stat-card.red::before{background:#ff4d6d}
.stat-value{font-family:'Space Mono',monospace;font-size:32px;font-weight:700;line-height:1.2}
.stat-value.green{color:var(--accent)}.stat-value.purple{color:var(--chain1)}.stat-value.blue{color:var(--chain2)}.stat-value.orange{color:var(--warning)}.stat-value.red{color:#ff4d6d}
.stat-label{font-size:12px;color:var(--text-dim);margin-top:6px;font-family:'Space Mono',monospace}
.card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:20px}
.card-title{font-family:'Space Mono',monospace;font-size:11px;letter-spacing:2px;color:var(--text-muted);text-transform:uppercase;margin-bottom:16px}
.data-table{width:100%;border-collapse:collapse}
.data-table th{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:2px;color:var(--text-muted);text-transform:uppercase;padding:12px 16px;text-align:left;border-bottom:1px solid var(--border)}
.data-table td{padding:12px 16px;border-bottom:1px solid rgba(26,61,74,.4);font-size:13px;color:var(--text-dim);word-break:break-word}
.data-table tr:hover td{background:var(--accent-dim);color:var(--text)}
.data-table .actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
.badge{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;padding:3px 10px;border-radius:4px;display:inline-block}
.badge-image{background:rgba(37,99,235,.2);color:#60a5fa}.badge-audio{background:rgba(217,119,6,.2);color:#fbbf24}.badge-video{background:rgba(124,58,237,.2);color:#a78bfa}.badge-success{background:rgba(0,229,200,.15);color:var(--accent)}.badge-failed{background:rgba(255,77,109,.15);color:var(--danger)}.badge-pending{background:rgba(255,179,71,.15);color:var(--warning)}
.btn{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;letter-spacing:.4px;padding:10px 20px;border-radius:8px;cursor:pointer;border:none;transition:all .2s;display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none}
.btn-primary{background:var(--accent);color:var(--bg)}
.btn-primary:hover{background:var(--accent2);transform:translateY(-2px);box-shadow:0 8px 24px var(--accent-glow)}
.btn-danger{background:rgba(255,77,109,.15);color:var(--danger);border:1px solid rgba(255,77,109,.3)}
.btn-danger:hover{background:rgba(255,77,109,.25);border-color:rgba(255,77,109,.6)}
.btn-sm{padding:6px 14px;font-size:10px}
.btn-ghost{background:transparent;color:var(--text-dim);border:1px solid var(--border)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-success{background:rgba(0,229,200,.15);color:var(--accent);border:1px solid rgba(0,229,200,.3)}
.btn-success:hover{background:rgba(0,229,200,.25)}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px)}
.modal-overlay.open{display:flex}
.modal-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:32px;width:100%;max-width:680px;max-height:90vh;overflow-y:auto;animation:fadeUp .3s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.modal-title{font-family:'Space Mono',monospace;font-size:18px;font-weight:700;color:var(--accent);margin-bottom:8px}
.modal-sub{font-size:14px;color:var(--text-dim);margin-bottom:24px;line-height:1.5}
.modal-actions{display:flex;gap:12px;margin-top:24px;flex-wrap:wrap}
.input-group{margin-bottom:18px}
.input-label{font-family:'Space Mono',monospace;font-size:11px;letter-spacing:1.5px;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px;display:block}
.input,.textarea,.select-input{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px 16px;color:var(--text);font-family:'JetBrains Mono',monospace;font-size:14px;outline:none;transition:border-color .25s,box-shadow .25s}
.input:focus,.textarea:focus,.select-input:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(0,229,200,.08)}
.textarea{resize:vertical;min-height:80px;font-family:'JetBrains Mono',monospace;font-size:13px}
.alert{padding:14px 20px;border-radius:8px;margin-bottom:20px;font-size:14px}
.alert-success{background:rgba(0,229,200,.12);border:1px solid var(--accent);color:var(--accent)}
.alert-error{background:var(--danger-dim);border:1px solid rgba(255,77,109,.4);color:var(--danger)}
.toggle-wrapper{display:flex;align-items:center;gap:12px;justify-content:flex-end;margin-top:-6px}
.toggle-label{font-family:'Space Mono',monospace;font-size:10px;letter-spacing:1px;color:var(--text-muted);text-transform:uppercase}
.switch{position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:var(--border);transition:.3s;border-radius:22px}
.slider:before{content:"";position:absolute;height:16px;width:16px;left:3px;bottom:3px;background-color:var(--text);transition:.3s;border-radius:50%}
input:checked + .slider{background-color:var(--accent)}
input:checked + .slider:before{transform:translateX(18px)}
@media(max-width:1024px){.grid-4{grid-template-columns:repeat(2,1fr)}.grid-2{grid-template-columns:1fr}}
@media(max-width:768px){.sidebar{display:none}.main-content{margin-left:0;padding:20px}.grid-4{grid-template-columns:1fr 1fr}.admin-navbar{padding:0 16px}}
@media(max-width:480px){.grid-4{grid-template-columns:1fr}.main-content{padding:14px}.stat-value{font-size:26px}.modal-card{padding:20px}}
</style>
</head>
<body>
<nav class="admin-navbar">
<div class="admin-brand"><div class="admin-brand-dot"></div>StegoPS <span style="opacity:.4;font-size:12px;margin:0 6px">/</span> Admin Panel</div>
<div class="admin-user"><span class="admin-badge">ADMIN</span><span><?= htmlspecialchars($admin['username']) ?></span><a href="<?= SITE_URL ?>/admin/logout.php" class="btn btn-danger btn-sm">Logout</a><a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-ghost btn-sm">← Main</a></div>
</nav>
<div class="admin-layout">
<div class="sidebar">
<div class="sidebar-label">Navigation</div>
<a href="?page=dashboard" class="sidebar-item <?= $page=='dashboard'?'active':'' ?>"><span class="sidebar-icon">◰</span> Dashboard</a>
<a href="?page=users" class="sidebar-item <?= $page=='users'?'active':'' ?>"><span class="sidebar-icon">◎</span> Users</a>
<a href="?page=algorithms" class="sidebar-item <?= $page=='algorithms'?'active':'' ?>"><span class="sidebar-icon">⚙</span> Algorithms</a>
<a href="?page=backups" class="sidebar-item <?= $page=='backups'?'active':'' ?>"><span class="sidebar-icon">↺</span> Backups</a>
<a href="?page=chains" class="sidebar-item <?= $page=='chains'?'active':'' ?>"><span class="sidebar-icon">⛓</span> Chains</a>
<a href="?page=operations" class="sidebar-item <?= $page=='operations'?'active':'' ?>"><span class="sidebar-icon">◈</span> Operations</a>
<a href="?page=media" class="sidebar-item <?= $page=='media'?'active':'' ?>"><span class="sidebar-icon">⊞</span> Media Files</a>
<a href="?page=shares" class="sidebar-item <?= $page=='shares'?'active':'' ?>"><span class="sidebar-icon">↗</span> Shared Links</a>
<a href="?page=logs" class="sidebar-item <?= $page=='logs'?'active':'' ?>"><span class="sidebar-icon">⎚</span> Logs</a>
</div>
<div class="main-content">
<?php if($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if($page === 'dashboard'): ?>
<div class="page-header"><div><div class="page-title">Dashboard</div><div class="page-sub">System overview — <?= date('Y-m-d H:i') ?></div></div></div>
<div class="grid-4">
<div class="stat-card red"><div class="stat-value red"><?= $stats['users'] ?></div><div class="stat-label">Users</div></div>
<div class="stat-card blue"><div class="stat-value blue"><?= $stats['ops'] ?></div><div class="stat-label">Operations</div></div>
<div class="stat-card purple"><div class="stat-value purple"><?= $stats['algos'] ?></div><div class="stat-label">Algorithms</div></div>
<div class="stat-card orange"><div class="stat-value orange"><?= $stats['shares'] ?></div><div class="stat-label">Active Shares</div></div>
<div class="stat-card green"><div class="stat-value green"><?= $stats['chains'] ?></div><div class="stat-label">Chains</div></div>
<div class="stat-card blue"><div class="stat-value blue"><?= $stats['media'] ?></div><div class="stat-label">Media Files</div></div>
<div class="stat-card purple"><div class="stat-value purple"><?= $stats['logs'] ?></div><div class="stat-label">Log Entries</div></div>
</div>
<div class="grid-2">
<div class="card"><div class="card-title">Recent Operations</div><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>User</th><th>File</th><th>Type</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach($recentOps as $op): ?><tr><td style="color:var(--accent);font-size:12px"><?= htmlspecialchars($op['username']) ?></td><td style="font-size:12px"><?= htmlspecialchars(substr($op['original_filename'],0,16)) ?></td><td><span class="badge badge-image"><?= strtoupper($op['operation_type']) ?></span></td><td><span class="badge badge-<?= $op['status']==='completed'?'success':($op['status']==='failed'?'failed':'pending') ?>"><?= strtoupper($op['status']) ?></span></td><td style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text-muted)"><?= date('d M',strtotime($op['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card"><div class="card-title">Recent Users</div><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>Username</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead><tbody><?php foreach(array_slice($users,0,10) as $u): ?><tr><td style="font-weight:600;color:var(--text)"><?= htmlspecialchars($u['username']) ?></td><td><span class="badge <?= $u['role']==='admin'?'badge-video':'badge-success' ?>"><?= strtoupper($u['role']) ?></span></td><td><span class="badge <?= $u['is_active']?'badge-success':'badge-failed' ?>"><?= $u['is_active']?'ACTIVE':'SUSPENDED' ?></span></td><td style="font-family:'Space Mono',monospace;font-size:11px;color:var(--text-muted)"><?= date('d M Y',strtotime($u['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php elseif($page === 'users'): ?>
<div class="page-header"><div><div class="page-title">Users</div><div class="page-sub">Manage registered users</div></div><button class="btn btn-primary" onclick="openModal('addUserModal')">+ Add User</button></div>
<div class="card"><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Storage</th><th>Created</th><th>Last Login</th><th>Actions</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><?= $u['user_id'] ?></td><td><?= htmlspecialchars($u['username']) ?></td><td><?= htmlspecialchars($u['email']??'—') ?></td><td><span class="badge <?= $u['role']==='admin'?'badge-video':'badge-success' ?>"><?= strtoupper($u['role']) ?></span></td><td><span class="badge <?= $u['is_active']?'badge-success':'badge-failed' ?>"><?= $u['is_active']?'ACTIVE':'SUSPENDED' ?></span></td><td><?= round($u['storage_used']/1024/1024,1) ?>MB</td><td><?= date('d M Y',strtotime($u['created_at'])) ?></td><td><?= $u['last_login']?date('d M',strtotime($u['last_login'])):'—' ?></td><td class="actions"><button class="btn btn-primary btn-sm" onclick="editUser(<?= $u['user_id'] ?>)">Edit</button> <?php if($u['user_id']!=$admin['user_id']): ?><a href="?page=users&action=delete_user&id=<?= $u['user_id'] ?>" class="btn btn-danger btn-sm confirm-link" data-confirm="Delete user?">Delete</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div id="addUserModal" class="modal-overlay"><div class="modal-card"><div class="modal-title">Add User</div><div class="modal-sub">Create a new user account</div><form method="POST"><input type="hidden" name="action" value="add_user"><div class="input-group"><label class="input-label">Username *</label><input type="text" name="username" class="input" required></div><div class="input-group"><label class="input-label">Email</label><input type="email" name="email" class="input"></div><div class="input-group"><label class="input-label">Password *</label><input type="password" name="password" class="input" required></div><div class="input-group"><label class="input-label">Role</label><select name="role" class="select-input"><option value="user">User</option><option value="admin">Admin</option></select></div><div class="modal-actions"><button type="submit" class="btn btn-primary">Add User</button><button type="button" class="btn btn-ghost" onclick="closeModal('addUserModal')">Cancel</button></div></form></div></div>
<div id="editUserModal" class="modal-overlay"><div class="modal-card"><div class="modal-title">Edit User</div><div class="modal-sub">Update user details</div><form method="POST" id="editUserForm"><input type="hidden" name="action" value="edit_user"><input type="hidden" name="user_id" id="edit_user_id"><div class="input-group"><label class="input-label">Username *</label><input type="text" name="username" id="edit_username" class="input" required></div><div class="input-group"><label class="input-label">Email</label><input type="email" name="email" id="edit_email" class="input"></div><div class="input-group"><label class="input-label">Role</label><select name="role" id="edit_role" class="select-input"><option value="user">User</option><option value="admin">Admin</option></select></div><div class="input-group"><label class="input-label">Active</label><input type="checkbox" name="is_active" id="edit_active" value="1"></div><div class="input-group"><label class="input-label">New Password (leave blank to keep)</label><input type="password" name="new_password" class="input"></div><div class="modal-actions"><button type="submit" class="btn btn-primary">Update</button><button type="button" class="btn btn-ghost" onclick="closeModal('editUserModal')">Cancel</button></div></form></div></div>
<?php elseif($page === 'algorithms'): ?>
<div class="page-header"><div><div class="page-title">Algorithms</div><div class="page-sub">Encryption algorithm registry</div></div><button class="btn btn-primary" onclick="openModal('addAlgoModal')">+ Add Algorithm</button></div>
<div class="card"><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>Name</th><th>Slug</th><th>Category</th><th>Module</th><th>Requires Key</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($algos as $a): ?><tr><td><?= htmlspecialchars($a['algo_name']) ?></td><td style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--accent)"><?= htmlspecialchars($a['algo_slug']) ?></td><td><span class="badge badge-<?= $a['category']==='symmetric'?'image':($a['category']==='classical'?'video':($a['category']==='custom'?'audio':'success')) ?>"><?= strtoupper($a['category']) ?></span></td><td><?= htmlspecialchars($a['python_module']) ?></td><td><?= $a['requires_key']?'Yes':'No' ?></td><td><span class="badge <?= $a['is_active']?'badge-success':'badge-failed' ?>"><?= $a['is_active']?'ACTIVE':'DISABLED' ?></span></td><td class="actions"><button class="btn btn-primary btn-sm" onclick="editAlgo(<?= $a['algo_id'] ?>)">Edit</button> <a href="?page=algorithms&action=delete_algo&id=<?= $a['algo_id'] ?>" class="btn btn-danger btn-sm confirm-link" data-confirm="Delete algorithm?">Delete</a> <button class="btn btn-success btn-sm" onclick="testAlgo(<?= $a['algo_id'] ?>)">Test</button></td></tr><?php endforeach; ?></tbody></table></div></div>
<div id="addAlgoModal" class="modal-overlay"><div class="modal-card"><div class="modal-title">Add Algorithm</div><div class="modal-sub">Register a new encryption algorithm with Python code</div><form method="POST"><input type="hidden" name="action" value="add_algo"><div class="input-group"><label class="input-label">Name *</label><input type="text" name="algo_name" class="input" required></div><div class="input-group"><label class="input-label">Slug *</label><input type="text" name="algo_slug" class="input" required></div><div class="input-group"><label class="input-label">Category</label><select name="category" class="select-input"><option value="classical">Classical</option><option value="symmetric">Symmetric</option><option value="custom">Custom</option></select></div><div class="input-group"><label class="input-label">Python Module *</label><input type="text" name="python_module" class="input" required></div><div class="input-group"><label class="input-label">Requires Key</label><input type="checkbox" name="requires_key" value="1"></div><div class="input-group"><label class="input-label">Description</label><textarea name="description" class="textarea" rows="2"></textarea></div><div class="input-group"><label class="input-label">Python Code *<br><span style="font-weight:normal;font-size:12px;color:var(--text-muted)">Must contain encrypt(data: bytes, key: str, **kw) -> bytes and decrypt(...)</span></label><textarea name="python_code" class="textarea" rows="12" required>def encrypt(data: bytes, key: str, **kw) -> bytes:
    return data

def decrypt(data: bytes, key: str, **kw) -> bytes:
    return data</textarea></div><div class="modal-actions"><button type="submit" class="btn btn-primary">Add Algorithm</button><button type="button" class="btn btn-ghost" onclick="closeModal('addAlgoModal')">Cancel</button></div></form></div></div>
<div id="editAlgoModal" class="modal-overlay"><div class="modal-card"><div class="modal-title">Edit Algorithm</div><div class="modal-sub">Update algorithm details and code</div><form method="POST" id="editAlgoForm"><input type="hidden" name="action" value="edit_algo"><input type="hidden" name="algo_id" id="edit_algo_id"><div class="input-group"><label class="input-label">Name *</label><input type="text" name="algo_name" id="edit_algo_name" class="input" required></div><div class="input-group"><label class="input-label">Slug *</label><input type="text" name="algo_slug" id="edit_algo_slug" class="input" required></div><div class="input-group"><label class="input-label">Category</label><select name="category" id="edit_algo_category" class="select-input"><option value="classical">Classical</option><option value="symmetric">Symmetric</option><option value="custom">Custom</option></select></div><div class="input-group"><label class="input-label">Python Module *</label><input type="text" name="python_module" id="edit_algo_module" class="input" required></div><div class="input-group"><label class="input-label">Requires Key</label><input type="checkbox" name="requires_key" id="edit_algo_reqkey" value="1"></div><div class="input-group"><label class="input-label">Active</label><input type="checkbox" name="is_active" id="edit_algo_active" value="1"></div><div class="input-group"><label class="input-label">Description</label><textarea name="description" id="edit_algo_desc" class="textarea" rows="2"></textarea></div><div class="input-group"><label class="input-label">Python Code</label><textarea name="python_code" id="edit_algo_code" class="textarea" rows="12"></textarea></div><div class="modal-actions"><button type="submit" class="btn btn-primary">Update</button><button type="button" class="btn btn-ghost" onclick="closeModal('editAlgoModal')">Cancel</button></div></form></div></div>
<div id="testAlgoModal" class="modal-overlay"><div class="modal-card"><div class="modal-title">Test Algorithm</div><div class="modal-sub">Test encryption/decryption</div><form id="testAlgoForm"><input type="hidden" name="algo_id" id="test_algo_id"><div class="input-group"><label class="input-label">Plaintext</label><input type="text" name="plaintext" id="test_plaintext" class="input" value="Hello World"></div><div class="input-group"><label class="input-label">Key</label><input type="text" name="key" id="test_key" class="input" value="secret"></div><div class="toggle-wrapper"><span class="toggle-label">Convert hex to string</span><label class="switch"><input type="checkbox" id="test_hex2str"><span class="slider"></span></label></div><div class="modal-actions"><button type="button" class="btn btn-primary" onclick="runTest()">Run Test</button><button type="button" class="btn btn-ghost" onclick="closeModal('testAlgoModal')">Close</button></div></form><div id="testResult" style="margin-top:16px;padding:12px;background:var(--surface2);border-radius:8px;font-family:'JetBrains Mono',monospace;font-size:13px;white-space:pre-wrap;display:none;"></div></div></div>
<?php elseif($page === 'backups'): ?>
<div class="page-header"><div><div class="page-title">Deleted Algorithm Backups</div><div class="page-sub">Restore previously deleted algorithms</div></div></div>
<?php if(empty($backups)): ?><div class="alert alert-success">No backups available.</div><?php else: ?><div class="card"><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>Name</th><th>Slug</th><th>Category</th><th>Module</th><th>Deleted At</th><th>Actions</th></tr></thead><tbody><?php foreach($backups as $b): ?><tr><td><?= htmlspecialchars($b['algo_name']) ?></td><td><?= htmlspecialchars($b['algo_slug']) ?></td><td><span class="badge badge-<?= $b['category']==='symmetric'?'image':($b['category']==='classical'?'video':'audio') ?>"><?= strtoupper($b['category']) ?></span></td><td><?= htmlspecialchars($b['python_module']) ?></td><td><?= $b['deleted_at'] ?></td><td class="actions"><form method="POST" class="confirm-form" data-confirm="Restore this algorithm?"><input type="hidden" name="action" value="restore_algo"><input type="hidden" name="backup_file" value="<?= htmlspecialchars($b['backup_file']) ?>"><button type="submit" class="btn btn-success btn-sm">Restore</button></form></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<?php elseif($page === 'chains'): ?>
<div class="page-header"><div><div class="page-title">Encryption Chains</div><div class="page-sub">All user-defined chains</div></div></div>
<div class="card"><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>ID</th><th>Name</th><th>User</th><th>Layers</th><th>Public</th><th>Share Token</th><th>Uses</th><th>Created</th><th>Actions</th></tr></thead><tbody><?php foreach($chains as $c): ?><tr><td><?= $c['chain_id'] ?></td><td><?= htmlspecialchars($c['chain_name']) ?></td><td><?= htmlspecialchars($c['username']) ?></td><td><?= $c['total_layers'] ?></td><td><span class="badge <?= $c['is_public']?'badge-success':'badge-failed' ?>"><?= $c['is_public']?'Public':'Private' ?></span></td><td style="font-family:'JetBrains Mono',monospace;font-size:11px"><?= htmlspecialchars(substr($c['share_token']??'',0,8)) ?></td><td><?= $c['use_count'] ?></td><td><?= date('d M Y',strtotime($c['created_at'])) ?></td><td class="actions"><button class="btn btn-primary btn-sm" onclick="viewChain(<?= $c['chain_id'] ?>)">View</button> <a href="?page=chains&action=delete_chain&id=<?= $c['chain_id'] ?>" class="btn btn-danger btn-sm confirm-link" data-confirm="Delete chain?">Delete</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<div id="viewChainModal" class="modal-overlay"><div class="modal-card"><div class="modal-title">Chain Details</div><div id="chainDetails" class="modal-sub"></div><div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="closeModal('viewChainModal')">Close</button></div></div></div>
<?php elseif($page === 'operations'): ?>
<div class="page-header"><div><div class="page-title">Operations</div><div class="page-sub">All stego operations</div></div>
<div class="actions"><a href="?page=operations&action=delete_all_ops" class="btn btn-danger confirm-link" data-confirm="Delete ALL operations and associated files?">Delete All Operations</a></div>
</div>
<div class="card"><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>ID</th><th>User</th><th>File</th><th>Type</th><th>Technique</th><th>Payload</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody><?php foreach($ops as $o): ?><tr><td><?= $o['op_id'] ?></td><td><?= htmlspecialchars($o['username']) ?></td><td><?= htmlspecialchars(substr($o['original_filename']??'',0,15)) ?></td><td><span class="badge badge-image"><?= strtoupper($o['operation_type']) ?></span></td><td><span class="badge badge-<?= $o['stego_technique']==='lsb'?'image':'audio' ?>"><?= strtoupper($o['stego_technique']) ?></span></td><td><?= $o['payload_type'] ?></td><td><span class="badge badge-<?= $o['status']==='completed'?'success':($o['status']==='failed'?'failed':'pending') ?>"><?= strtoupper($o['status']) ?></span></td><td><?= date('d M Y',strtotime($o['created_at'])) ?></td><td class="actions"><a href="?page=operations&action=delete_op&id=<?= $o['op_id'] ?>" class="btn btn-danger btn-sm confirm-link" data-confirm="Delete operation and its carrier/output files?">Delete</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php elseif($page === 'media'): ?>
<div class="page-header"><div><div class="page-title">Media Files</div><div class="page-sub">All uploaded carrier files</div></div>
<div class="actions"><a href="?page=media&action=delete_all_media" class="btn btn-danger confirm-link" data-confirm="Delete ALL media files from disk and database?">Delete All Media</a></div>
</div>
<div class="card"><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>ID</th><th>Filename</th><th>User</th><th>Type</th><th>Size</th><th>Hash</th><th>Capacity</th><th>Expires</th><th>Actions</th></tr></thead><tbody><?php foreach($media as $m): ?><tr><td><?= $m['file_id'] ?></td><td><?= htmlspecialchars(substr($m['original_filename'],0,20)) ?></td><td><?= htmlspecialchars($m['username']) ?></td><td><span class="badge badge-<?= $m['file_type']==='image'?'image':($m['file_type']==='audio'?'audio':'video') ?>"><?= strtoupper($m['file_type']) ?></span></td><td><?= round($m['file_size_bytes']/1024) ?>KB</td><td style="font-size:11px"><?= substr($m['file_hash'],0,8) ?>…</td><td><?= $m['capacity_bytes']?round($m['capacity_bytes']/1024).'KB':'—' ?></td><td><?= $m['expires_at']?date('d M',strtotime($m['expires_at'])):'∞' ?></td><td class="actions"><a href="?page=media&action=delete_media&id=<?= $m['file_id'] ?>" class="btn btn-danger btn-sm confirm-link" data-confirm="Delete media file from disk and database?">Delete</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php elseif($page === 'shares'): ?>
<div class="page-header"><div><div class="page-title">Shared Links</div><div class="page-sub">Active file shares</div></div></div>
<div class="card"><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>ID</th><th>File</th><th>Shared By</th><th>Token</th><th>Downloads</th><th>Max</th><th>Active</th><th>Expires</th><th>Actions</th></tr></thead><tbody><?php foreach($shares as $s): ?><tr><td><?= $s['share_id'] ?></td><td><?= htmlspecialchars(substr($s['original_filename'],0,15)) ?></td><td><?= htmlspecialchars($s['username']) ?></td><td style="font-size:11px"><?= substr($s['share_token'],0,10) ?>…</td><td><?= $s['download_count'] ?></td><td><?= $s['max_downloads']?:'∞' ?></td><td><span class="badge <?= $s['is_active']?'badge-success':'badge-failed' ?>"><?= $s['is_active']?'Active':'Inactive' ?></span></td><td><?= $s['expires_at']?date('d M',strtotime($s['expires_at'])):'∞' ?></td><td class="actions"><a href="?page=shares&action=delete_share&id=<?= $s['share_id'] ?>" class="btn btn-danger btn-sm confirm-link" data-confirm="Delete share?">Delete</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php elseif($page === 'logs'): ?>
<div class="page-header"><div><div class="page-title">Logs</div><div class="page-sub">System operation logs</div></div><form method="POST" class="confirm-form" data-confirm="Clear all logs?"><input type="hidden" name="action" value="clear_logs"><button type="submit" class="btn btn-danger">Clear Logs</button></form></div>
<div class="card"><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>ID</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?= $l['log_id'] ?></td><td><?= $l['user_id']?:'—' ?></td><td><?= htmlspecialchars($l['action_type']) ?></td><td><?= htmlspecialchars(substr($l['action_detail']??'',0,30)) ?></td><td><?= htmlspecialchars($l['ip_address']) ?></td><td><span class="badge <?= $l['status']==='success'?'badge-success':'badge-failed' ?>"><?= strtoupper($l['status']) ?></span></td><td><?= date('d M H:i',strtotime($l['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
</div>
</div>
<div id="confirmModal" class="modal-overlay"><div class="modal-card" style="max-width:400px"><div class="modal-title">Confirm Action</div><div class="modal-sub" id="confirmMessage">Are you sure?</div><div class="modal-actions"><button id="confirmOk" class="btn btn-danger">Confirm</button><button class="btn btn-ghost" onclick="closeModal('confirmModal')">Cancel</button></div></div></div>
<script>
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
let pendingConfirm = null;
document.getElementById('confirmOk').addEventListener('click', function() {
    closeModal('confirmModal');
    if (pendingConfirm) {
        pendingConfirm(true);
        pendingConfirm = null;
    }
});
document.querySelectorAll('#confirmModal .btn-ghost').forEach(el => {
    el.addEventListener('click', function() {
        closeModal('confirmModal');
        if (pendingConfirm) {
            pendingConfirm(false);
            pendingConfirm = null;
        }
    });
});
function confirmAction(msg) {
    return new Promise((resolve) => {
        document.getElementById('confirmMessage').textContent = msg;
        document.getElementById('confirmModal').classList.add('open');
        pendingConfirm = resolve;
    });
}
document.querySelectorAll('.confirm-link').forEach(el => {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        const href = this.getAttribute('href');
        const msg = this.getAttribute('data-confirm') || 'Are you sure?';
        confirmAction(msg).then(result => {
            if (result) window.location.href = href;
        });
    });
});
document.querySelectorAll('.confirm-form').forEach(el => {
    el.addEventListener('submit', function(e) {
        e.preventDefault();
        const msg = this.getAttribute('data-confirm') || 'Are you sure?';
        confirmAction(msg).then(result => {
            if (result) this.submit();
        });
    });
});
function editUser(id) {
    fetch('?page=users&ajax=get_user&id='+id)
    .then(r=>r.json())
    .then(d => {
        document.getElementById('edit_user_id').value = d.user_id;
        document.getElementById('edit_username').value = d.username;
        document.getElementById('edit_email').value = d.email || '';
        document.getElementById('edit_role').value = d.role;
        document.getElementById('edit_active').checked = d.is_active == 1;
        openModal('editUserModal');
    });
}
function editAlgo(id) {
    fetch('?page=algorithms&ajax=get_algo&id='+id)
    .then(r=>r.json())
    .then(d => {
        document.getElementById('edit_algo_id').value = d.algo_id;
        document.getElementById('edit_algo_name').value = d.algo_name;
        document.getElementById('edit_algo_slug').value = d.algo_slug;
        document.getElementById('edit_algo_category').value = d.category;
        document.getElementById('edit_algo_module').value = d.python_module;
        document.getElementById('edit_algo_reqkey').checked = d.requires_key == 1;
        document.getElementById('edit_algo_active').checked = d.is_active == 1;
        document.getElementById('edit_algo_desc').value = d.description || '';
        document.getElementById('edit_algo_code').value = d.python_code || '';
        openModal('editAlgoModal');
    });
}
function viewChain(id) {
    fetch('?page=chains&ajax=get_chain&id='+id)
    .then(r=>r.json())
    .then(d => {
        let html = '<strong>'+d.chain_name+'</strong> by '+d.username+'<br>Layers: '+d.total_layers+'<br>Public: '+(d.is_public?'Yes':'No')+'<br>Share Token: '+d.share_token+'<br><br><strong>Steps:</strong><ul>';
        if (d.steps) d.steps.forEach(s => {
            html += '<li>'+s.algo_name+' (key hint: '+s.key_hint+')</li>';
        });
        html += '</ul>';
        document.getElementById('chainDetails').innerHTML = html;
        openModal('viewChainModal');
    });
}
function testAlgo(id) {
    document.getElementById('test_algo_id').value = id;
    document.getElementById('testResult').style.display = 'none';
    openModal('testAlgoModal');
}
function runTest() {
    const algo_id = document.getElementById('test_algo_id').value;
    const plaintext = document.getElementById('test_plaintext').value;
    const key = document.getElementById('test_key').value;
    const convert = document.getElementById('test_hex2str').checked;
    const resultDiv = document.getElementById('testResult');
    resultDiv.style.display = 'block';
    resultDiv.textContent = 'Testing...';
    fetch('?page=algorithms&ajax=test_algo', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'algo_id='+algo_id+'&plaintext='+encodeURIComponent(plaintext)+'&key='+encodeURIComponent(key)
    })
    .then(r=>r.json())
    .then(data => {
        let output = JSON.stringify(data, null, 2);
        if (convert) {
            output = output.replace(/"([^"]+)"/g, function(match, p1) {
                if (/^[0-9a-fA-F]{2,}$/.test(p1) && p1.length % 2 === 0) {
                    try {
                        let bytes = new Uint8Array(p1.match(/.{2}/g).map(byte => parseInt(byte, 16)));
                        let str = new TextDecoder('utf-8', { fatal: false }).decode(bytes);
                        return '"' + str + '"';
                    } catch(e) {}
                }
                return match;
            });
        }
        resultDiv.textContent = output;
    })
    .catch(err => {
        resultDiv.textContent = 'Error: '+err;
    });
}
</script>
</body>
</html>