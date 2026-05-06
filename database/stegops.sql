CREATE DATABASE IF NOT EXISTS stegops_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stegops_db;
CREATE TABLE IF NOT EXISTS users (
user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(50) NOT NULL UNIQUE,
email VARCHAR(150) DEFAULT NULL,
password_hash VARCHAR(255) NOT NULL,
salt VARCHAR(64) NOT NULL,
steg_key VARCHAR(64) DEFAULT NULL,
role ENUM('user','admin') DEFAULT 'user',
profile_picture VARCHAR(255) DEFAULT NULL,
is_verified TINYINT(1) DEFAULT 0,
is_active TINYINT(1) DEFAULT 1,
storage_used BIGINT UNSIGNED DEFAULT 0,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
last_login DATETIME DEFAULT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS encryption_algorithms (
algo_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
algo_name VARCHAR(100) NOT NULL UNIQUE,
algo_slug VARCHAR(50) NOT NULL UNIQUE,
category ENUM('classical','symmetric','custom') NOT NULL,
requires_key TINYINT(1) DEFAULT 1,
description TEXT DEFAULT NULL,
python_module VARCHAR(100) NOT NULL,
is_active TINYINT(1) DEFAULT 1,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS encryption_chains (
chain_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id INT UNSIGNED NOT NULL,
chain_name VARCHAR(100) NOT NULL,
description TEXT DEFAULT NULL,
total_layers INT UNSIGNED DEFAULT 1,
is_public TINYINT(1) DEFAULT 0,
share_token VARCHAR(64) DEFAULT NULL UNIQUE,
use_count INT UNSIGNED DEFAULT 0,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS chain_steps (
step_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
chain_id INT UNSIGNED NOT NULL,
algo_id INT UNSIGNED NOT NULL,
step_order INT UNSIGNED NOT NULL,
key_hint VARCHAR(100) DEFAULT NULL,
custom_params JSON DEFAULT NULL,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (chain_id) REFERENCES encryption_chains(chain_id) ON DELETE CASCADE,
FOREIGN KEY (algo_id) REFERENCES encryption_algorithms(algo_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS media_files (
file_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id INT UNSIGNED NOT NULL,
original_filename VARCHAR(255) NOT NULL,
stored_filename VARCHAR(255) NOT NULL UNIQUE,
file_type ENUM('image','audio','video') NOT NULL,
mime_type VARCHAR(100) NOT NULL,
file_size_bytes BIGINT UNSIGNED NOT NULL,
file_hash VARCHAR(64) NOT NULL,
storage_path VARCHAR(500) NOT NULL,
capacity_bytes BIGINT UNSIGNED DEFAULT NULL,
is_processed TINYINT(1) DEFAULT 0,
uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
expires_at DATETIME DEFAULT NULL,
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS stego_operations (
op_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id INT UNSIGNED NOT NULL,
chain_id INT UNSIGNED DEFAULT NULL,
carrier_file_id INT UNSIGNED NOT NULL,
output_file_id INT UNSIGNED DEFAULT NULL,
operation_type ENUM('embed','extract') NOT NULL,
payload_type ENUM('text','file') NOT NULL,
payload_size BIGINT UNSIGNED DEFAULT 0,
stego_technique ENUM('lsb','xor_pixel','alpha','img_in_img','wav_lsb','phase','frame_lsb') NOT NULL,
status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
error_message TEXT DEFAULT NULL,
processing_ms INT UNSIGNED DEFAULT NULL,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
completed_at DATETIME DEFAULT NULL,
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
FOREIGN KEY (chain_id) REFERENCES encryption_chains(chain_id) ON DELETE SET NULL,
FOREIGN KEY (carrier_file_id) REFERENCES media_files(file_id),
FOREIGN KEY (output_file_id) REFERENCES media_files(file_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS shared_files (
share_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
op_id INT UNSIGNED NOT NULL,
shared_by INT UNSIGNED NOT NULL,
share_token VARCHAR(128) NOT NULL UNIQUE,
password_hash VARCHAR(255) DEFAULT NULL,
max_downloads INT UNSIGNED DEFAULT 0,
download_count INT UNSIGNED DEFAULT 0,
chain_hint TEXT DEFAULT NULL,
is_active TINYINT(1) DEFAULT 1,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
expires_at DATETIME DEFAULT NULL,
last_accessed DATETIME DEFAULT NULL,
FOREIGN KEY (op_id) REFERENCES stego_operations(op_id) ON DELETE CASCADE,
FOREIGN KEY (shared_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS operation_logs (
log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id INT UNSIGNED DEFAULT NULL,
action_type VARCHAR(50) NOT NULL,
action_detail TEXT DEFAULT NULL,
ip_address VARCHAR(45) NOT NULL,
user_agent VARCHAR(500) DEFAULT NULL,
status ENUM('success','failed','blocked') DEFAULT 'success',
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE INDEX IF NOT EXISTS idx_chains_user ON encryption_chains(user_id);
CREATE INDEX IF NOT EXISTS idx_steps_chain ON chain_steps(chain_id,step_order);
CREATE INDEX IF NOT EXISTS idx_files_user ON media_files(user_id,file_type);
CREATE INDEX IF NOT EXISTS idx_ops_user ON stego_operations(user_id,created_at);
CREATE INDEX IF NOT EXISTS idx_share_token ON shared_files(share_token);
CREATE INDEX IF NOT EXISTS idx_logs_user ON operation_logs(user_id,created_at);
INSERT IGNORE INTO encryption_algorithms (algo_name,algo_slug,category,requires_key,description,python_module) VALUES
('Caesar Cipher','caesar','classical',1,'Shifts each byte by a key-derived value','caesar'),
('Vigenere Cipher','vigenere','classical',1,'Polyalphabetic byte substitution with repeating key','vigenere'),
('AES-256','aes_256','symmetric',1,'AES with 256-bit key in CBC mode — strong symmetric encryption','aes256'),
('ChaCha20','chacha20','symmetric',1,'Modern fast stream cipher — ideal for steganography payloads','chacha20'),
('NETRUNNER','netrunner','custom',1,'Red-channel keystream cipher — custom algorithm','netrunner');
INSERT IGNORE INTO users (username,email,password_hash,salt,steg_key,role,is_verified,is_active) VALUES
('admin','admin@stegops.local','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','stegops_default_salt',SUBSTR(MD5(RAND()),1,32),'admin',1,1);
INSERT IGNORE INTO encryption_algorithms (algo_name,algo_slug,category,requires_key,description,python_module) VALUES
('DES','des','symmetric',1,'Data Encryption Standard — 56-bit key CBC mode (legacy/educational)','des_cipher'),
('Triple DES','des3','symmetric',1,'3DES/TDEA — three DES passes, 112-bit effective security','des3_cipher');
SELECT 'StegoPS database ready' AS Status;
SELECT COUNT(*) AS algorithms_loaded FROM encryption_algorithms;
