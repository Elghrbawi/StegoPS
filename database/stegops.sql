-- ============================================================
-- StegoPS Database Setup
-- Run this in phpMyAdmin or MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS stegops_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE stegops_db;

-- ── USERS ──
CREATE TABLE IF NOT EXISTS users (
  user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(50)  NOT NULL UNIQUE,
  email           VARCHAR(150) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  salt            VARCHAR(64)  NOT NULL,
  role            ENUM('user','admin') DEFAULT 'user',
  profile_picture VARCHAR(255) DEFAULT NULL,
  is_verified     TINYINT(1)   DEFAULT 0,
  is_active       TINYINT(1)   DEFAULT 1,
  storage_used    BIGINT UNSIGNED DEFAULT 0,
  created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  last_login      DATETIME     DEFAULT NULL
) ENGINE=InnoDB;

-- ── ENCRYPTION ALGORITHMS ──
CREATE TABLE IF NOT EXISTS encryption_algorithms (
  algo_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  algo_name     VARCHAR(100) NOT NULL UNIQUE,
  algo_slug     VARCHAR(50)  NOT NULL UNIQUE,
  category      ENUM('classical','symmetric','asymmetric','custom','hashing') NOT NULL,
  requires_key  TINYINT(1)   DEFAULT 1,
  description   TEXT         DEFAULT NULL,
  python_module VARCHAR(100) NOT NULL,
  is_active     TINYINT(1)   DEFAULT 1,
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── ENCRYPTION CHAINS ──
CREATE TABLE IF NOT EXISTS encryption_chains (
  chain_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  chain_name    VARCHAR(100) NOT NULL,
  description   TEXT         DEFAULT NULL,
  total_layers  INT UNSIGNED DEFAULT 1,
  is_public     TINYINT(1)   DEFAULT 0,
  share_token   VARCHAR(64)  DEFAULT NULL UNIQUE,
  use_count     INT UNSIGNED DEFAULT 0,
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── CHAIN STEPS ──
CREATE TABLE IF NOT EXISTS chain_steps (
  step_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chain_id     INT UNSIGNED NOT NULL,
  algo_id      INT UNSIGNED NOT NULL,
  step_order   INT UNSIGNED NOT NULL,
  key_hint     VARCHAR(100) DEFAULT NULL,
  custom_params JSON        DEFAULT NULL,
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (chain_id) REFERENCES encryption_chains(chain_id) ON DELETE CASCADE,
  FOREIGN KEY (algo_id)  REFERENCES encryption_algorithms(algo_id)
) ENGINE=InnoDB;

-- ── MEDIA FILES ──
CREATE TABLE IF NOT EXISTS media_files (
  file_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename   VARCHAR(255) NOT NULL UNIQUE,
  file_type         ENUM('image','audio','video') NOT NULL,
  mime_type         VARCHAR(100) NOT NULL,
  file_size_bytes   BIGINT UNSIGNED NOT NULL,
  file_hash         VARCHAR(64)  NOT NULL,
  storage_path      VARCHAR(500) NOT NULL,
  capacity_bytes    BIGINT UNSIGNED DEFAULT NULL,
  is_processed      TINYINT(1)   DEFAULT 0,
  uploaded_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  expires_at        DATETIME     DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── STEGO OPERATIONS ──
CREATE TABLE IF NOT EXISTS stego_operations (
  op_id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,
  chain_id          INT UNSIGNED DEFAULT NULL,
  carrier_file_id   INT UNSIGNED NOT NULL,
  output_file_id    INT UNSIGNED DEFAULT NULL,
  operation_type    ENUM('embed','extract') NOT NULL,
  payload_type      ENUM('text','file') NOT NULL,
  payload_size      BIGINT UNSIGNED DEFAULT 0,
  stego_technique   ENUM('lsb','phase_coding','echo_hiding','frame_embed') NOT NULL,
  status            ENUM('pending','processing','completed','failed') DEFAULT 'pending',
  error_message     TEXT DEFAULT NULL,
  processing_ms     INT UNSIGNED DEFAULT NULL,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at      DATETIME DEFAULT NULL,
  FOREIGN KEY (user_id)         REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (chain_id)        REFERENCES encryption_chains(chain_id) ON DELETE SET NULL,
  FOREIGN KEY (carrier_file_id) REFERENCES media_files(file_id),
  FOREIGN KEY (output_file_id)  REFERENCES media_files(file_id)
) ENGINE=InnoDB;

-- ── SHARED FILES ──
CREATE TABLE IF NOT EXISTS shared_files (
  share_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  op_id          INT UNSIGNED NOT NULL,
  shared_by      INT UNSIGNED NOT NULL,
  share_token    VARCHAR(128) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) DEFAULT NULL,
  max_downloads  INT UNSIGNED DEFAULT 0,
  download_count INT UNSIGNED DEFAULT 0,
  chain_hint     TEXT DEFAULT NULL,
  is_active      TINYINT(1)   DEFAULT 1,
  created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
  expires_at     DATETIME     DEFAULT NULL,
  last_accessed  DATETIME     DEFAULT NULL,
  FOREIGN KEY (op_id)     REFERENCES stego_operations(op_id) ON DELETE CASCADE,
  FOREIGN KEY (shared_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── OPERATION LOGS ──
CREATE TABLE IF NOT EXISTS operation_logs (
  log_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED DEFAULT NULL,
  action_type VARCHAR(50) NOT NULL,
  action_detail TEXT DEFAULT NULL,
  ip_address  VARCHAR(45) NOT NULL,
  user_agent  VARCHAR(500) DEFAULT NULL,
  status      ENUM('success','failed','blocked') DEFAULT 'success',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── INDEXES ──
CREATE INDEX idx_chains_user     ON encryption_chains(user_id);
CREATE INDEX idx_steps_chain     ON chain_steps(chain_id, step_order);
CREATE INDEX idx_files_user      ON media_files(user_id, file_type);
CREATE INDEX idx_ops_user        ON stego_operations(user_id, created_at);
CREATE INDEX idx_share_token     ON shared_files(share_token);
CREATE INDEX idx_logs_user       ON operation_logs(user_id, created_at);
CREATE INDEX idx_logs_ip         ON operation_logs(ip_address, created_at);

-- ── SEED: ENCRYPTION ALGORITHMS ──
INSERT INTO encryption_algorithms (algo_name, algo_slug, category, requires_key, description, python_module) VALUES
('Caesar Cipher',      'caesar',        'classical',   1, 'Shifts each byte by a key-derived value',             'algos.classical.caesar'),
('Vigenere Cipher',    'vigenere',      'classical',   1, 'Polyalphabetic byte substitution with repeating key', 'algos.classical.vigenere'),
('Playfair Cipher',    'playfair',      'classical',   1, 'Digraph substitution on a key-derived grid',          'algos.classical.playfair'),
('Rail Fence Cipher',  'rail_fence',    'classical',   1, 'Zigzag transposition across multiple rails',          'algos.classical.rail_fence'),
('Atbash Cipher',      'atbash',        'classical',   0, 'Reverses byte values: b → (255 - b)',                 'algos.classical.atbash'),
('ROT13',              'rot13',         'classical',   0, 'Rotates letter bytes by 13 positions',                'algos.classical.rot13'),
('Beaufort Cipher',    'beaufort',      'classical',   1, 'Symmetric polyalphabetic cipher (encrypt=decrypt)',    'algos.classical.beaufort'),
('Affine Cipher',      'affine',        'classical',   1, 'Linear byte transformation: (a*b + b) mod 256',       'algos.classical.affine'),
('Polybius Square',    'polybius',      'classical',   0, 'Maps each byte to a 2-byte coordinate pair',          'algos.classical.polybius'),
('Columnar Trans.',    'columnar',      'classical',   1, 'Writes data by rows, reads by key-ordered columns',   'algos.classical.columnar'),
('AES-128',            'aes_128',       'symmetric',   1, 'AES encryption with 128-bit key in CBC mode',         'algos.symmetric.aes128'),
('AES-256',            'aes_256',       'symmetric',   1, 'AES encryption with 256-bit key in CBC mode',         'algos.symmetric.aes256'),
('DES',                'des',           'symmetric',   1, 'Data Encryption Standard in CBC mode',                'algos.symmetric.des'),
('Triple DES',         'triple_des',    'symmetric',   1, '3DES: applies DES three times for stronger security', 'algos.symmetric.triple_des'),
('Blowfish',           'blowfish',      'symmetric',   1, 'Fast symmetric block cipher by Bruce Schneier',       'algos.symmetric.blowfish'),
('Twofish',            'twofish',       'symmetric',   1, 'Successor to Blowfish with 128-bit blocks',           'algos.symmetric.twofish'),
('RC4',                'rc4',           'symmetric',   1, 'Stream cipher — fast and simple',                     'algos.symmetric.rc4'),
('RC6',                'rc6',           'symmetric',   1, 'Block cipher derived from RC5',                       'algos.symmetric.rc6'),
('IDEA',               'idea',          'symmetric',   1, 'International Data Encryption Algorithm',             'algos.symmetric.idea'),
('ChaCha20',           'chacha20',      'symmetric',   1, 'Modern stream cipher — faster than AES on mobile',   'algos.symmetric.chacha20'),
('Salsa20',            'salsa20',       'symmetric',   1, 'Stream cipher predecessor to ChaCha20',               'algos.symmetric.salsa20'),
('RSA-2048',           'rsa_2048',      'asymmetric',  1, 'Public-key encryption with 2048-bit key pair',        'algos.asymmetric.rsa2048'),
('ElGamal',            'elgamal',       'asymmetric',  1, 'Asymmetric cipher based on Diffie-Hellman',           'algos.asymmetric.elgamal'),
('Diffie-Hellman',     'diffie_hellman','asymmetric',  1, 'Key exchange protocol',                               'algos.asymmetric.diffie_hellman'),
('3-Layer Custom',     'three_layer',   'custom',      1, 'Original algorithm: substitution + XOR + modular',    'algos.custom.three_layer'),
('XOR Cipher',         'xor_cipher',    'custom',      1, 'XOR with hash-chain extended key',                    'algos.custom.xor_cipher'),
('ASCII Shift',        'ascii_shift',   'custom',      1, 'Position-dependent modular shift',                    'algos.custom.ascii_shift'),
('Bit Manipulation',   'bit_manip',     'custom',      1, 'Bit rotation and swap cipher',                        'algos.custom.bit_manip'),
('Position Encoding',  'pos_encoding',  'custom',      1, 'Index-based byte transformation',                     'algos.custom.pos_encoding'),
('Hybrid XOR-Sub',     'hybrid_xor',    'custom',      1, 'Two-phase XOR then substitution',                     'algos.custom.hybrid_xor'),
('SHA-256 Integrity',  'sha256',        'hashing',     0, 'Appends SHA-256 hash for integrity verification',     'algos.hashing.sha256'),
('SHA-512 Integrity',  'sha512',        'hashing',     0, 'Appends SHA-512 hash for stronger integrity check',   'algos.hashing.sha512'),
('MD5 Integrity',      'md5',           'hashing',     0, 'MD5 hash integrity layer (legacy support)',           'algos.hashing.md5'),
('HMAC-SHA256',        'hmac_sha256',   'hashing',     1, 'Keyed-hash for authenticated integrity',              'algos.hashing.hmac_sha256');

-- ── DEFAULT ADMIN USER ──
-- Password: Admin@StegoPS2025 (change immediately after setup!)
INSERT INTO users (username, email, password_hash, salt, role, is_verified, is_active) VALUES
('admin', 'admin@stegops.local',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'stegops_default_salt',
 'admin', 1, 1);

SELECT 'StegoPS database setup complete!' AS Status;
SELECT COUNT(*) AS algorithms_loaded FROM encryption_algorithms;
