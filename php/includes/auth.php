<?php
// ============================================================
// StegoPS — Authentication & Session Helper
// File: php/includes/auth.php
// ============================================================

require_once __DIR__ . '/../config/database.php';

class Auth {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'secure'   => false, // Set true on HTTPS
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }

    // ── REGISTER ──
    public function register(string $username, string $email, string $password): array {
        // Validate
        if (strlen($username) < 3 || strlen($username) > 50)
            return ['success' => false, 'message' => 'Username must be 3–50 characters.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            return ['success' => false, 'message' => 'Invalid email address.'];
        if (strlen($password) < 8)
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];

        // Check duplicates
        $existing = $this->db->fetchOne(
            "SELECT user_id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
        if ($existing)
            return ['success' => false, 'message' => 'Username or email already exists.'];

        // Hash password
        $salt = bin2hex(random_bytes(32));
        $hash = password_hash($password . $salt, PASSWORD_BCRYPT, ['cost' => 12]);

        $userId = $this->db->insert(
            "INSERT INTO users (username, email, password_hash, salt, is_verified) VALUES (?, ?, ?, ?, 1)",
            [$username, $email, $hash, $salt]
        );

        $this->logAction($userId, 'register', 'New user registered');
        return ['success' => true, 'message' => 'Account created successfully!', 'user_id' => $userId];
    }

    // ── LOGIN ──
    public function login(string $username, string $password, string $ip): array {
        // Check lockout
        $attempts = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM operation_logs
             WHERE ip_address = ? AND action_type = 'login_failed'
             AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            [$ip]
        );
        if ($attempts && $attempts['cnt'] >= MAX_LOGIN_ATTEMPTS) {
            return ['success' => false, 'message' => 'Too many failed attempts. Try again in 15 minutes.'];
        }

        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );

        if (!$user || !password_verify($password . $user['salt'], $user['password_hash'])) {
            $this->logAction(null, 'login_failed', "Failed login for: $username", $ip, 'failed');
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Set session
        session_regenerate_id(true);
        $_SESSION['user_id']  = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        $_SESSION['login_at'] = time();

        // Update last login
        $this->db->query(
            "UPDATE users SET last_login = NOW() WHERE user_id = ?",
            [$user['user_id']]
        );

        $this->logAction($user['user_id'], 'login', 'Successful login', $ip);
        return ['success' => true, 'message' => 'Welcome back, ' . $user['username'] . '!'];
    }

    // ── LOGOUT ──
    public function logout(): void {
        if (isset($_SESSION['user_id'])) {
            $this->logAction($_SESSION['user_id'], 'logout', 'User logged out');
        }
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }

    // ── CHECKS ──
    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && isset($_SESSION['login_at'])
            && (time() - $_SESSION['login_at']) < SESSION_LIFETIME;
    }

    public function isAdmin(): bool {
        return $this->isLoggedIn() && $_SESSION['role'] === 'admin';
    }

    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            header('Location: ' . SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    public function requireAdmin(): void {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    public function currentUser(): ?array {
        if (!$this->isLoggedIn()) return null;
        return $this->db->fetchOne(
            "SELECT user_id, username, email, role, storage_used, created_at, last_login FROM users WHERE user_id = ?",
            [$_SESSION['user_id']]
        );
    }

    // ── LOGGING ──
    public function logAction(
        ?int $userId,
        string $action,
        string $detail = '',
        string $ip = '',
        string $status = 'success'
    ): void {
        if (!$ip) $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->db->insert(
            "INSERT INTO operation_logs (user_id, action_type, action_detail, ip_address, user_agent, status) VALUES (?,?,?,?,?,?)",
            [$userId, $action, $detail, $ip, $ua, $status]
        );
    }
}
