<?php
// ─── System Feature & Scope Toggles ─────────────────────────────────────────
require_once __DIR__ . '/features.php';

// ─── Timezone ────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ─── App URL (change this when deploying) ────────────────────────────────────
// Helper to detect local network or live hosting environment
if (!function_exists('isLocalHostEnv')) {
    function isLocalHostEnv(): bool {
        if (!isset($_SERVER['HTTP_HOST'])) return true;
        $host = parse_url('http://' . $_SERVER['HTTP_HOST'], PHP_URL_HOST) ?? $_SERVER['HTTP_HOST'];
        if ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, '.local') !== false) return true;
        if (strpos($host, '192.168.') === 0 || strpos($host, '10.') === 0) return true;
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host)) return true;
        return false;
    }
}

if (!defined('APP_URL')) {
    if (isLocalHostEnv()) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        define('APP_URL', $protocol . $host . '/palayan_kalinga_hub');
    } else {
        define('APP_URL', 'https://palayan-kalinga-hub.infinityfreeapp.com');
    }
}

// ─── Session Configuration ───────────────────────────────────────────────────
// Configure session security settings BEFORE starting the session.
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);   // Prevent JS access to session cookie
    ini_set('session.use_strict_mode', 1);   // Reject unrecognised session IDs
    ini_set('session.cookie_samesite', 'Lax'); // Basic CSRF mitigation at cookie level
    session_start();
}

// ─── Session Timeout (30 minutes of inactivity) ──────────────────────────────
define('SESSION_TIMEOUT', 1800); // seconds
if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        // Session expired — destroy and redirect to login
        session_unset();
        session_destroy();
        session_start(); // restart clean session for the redirect
        
        $redirect_path = isLocalHostEnv()
            ? '/palayan_kalinga_hub/login.php?timeout=1'
            : '/login.php?timeout=1';
            
        header('Location: ' . $redirect_path);
        exit();
    }
}
$_SESSION['last_activity'] = time();

// ─── Database Class ───────────────────────────────────────────────────────────
class Database
{
    // Cache the PDO connection statically so every new Database() reuses it
    private static ?PDO $connection = null;

    private string $host;
    private string $db_name;
    private string $username;
    private string $password;

    // Public constructor — keeps all existing `new Database()` calls working
    public function __construct()
    {
        // Auto-detect environment to choose credentials
        if (isLocalHostEnv()) {
            // Local development configuration
            $this->host = "localhost";
            $this->db_name = "palayan_kalinga_hub";
            $this->username = "root";
            $this->password = "";
        } else {
            // Live hosting (InfinityFree) configuration
            $this->host = "sql107.infinityfree.com";
            $this->db_name = "if0_42359268_palayan_kalinga_hub";
            $this->username = "if0_42359268";
            $this->password = "PalayanKalinga";
        }
    }

    /**
     * Return (and lazily create) the shared PDO connection.
     * Calling this multiple times per request always returns the same PDO object.
     */
    public function getConnection(): ?PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }
        try {
            self::$connection = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => true,  // Keep true: MySQL needs this for repeated named placeholders in subqueries
                ]
            );
        } catch (PDOException $e) {
            // Never expose DB errors to the end-user
            error_log('[DB Error] ' . $e->getMessage());
            http_response_code(500);
            die('<p style="font-family:sans-serif;padding:2rem;color:#c00;">
                A database error occurred. Please try again later or contact the administrator.
            </p>');
        }

        // Auto-create aics_comments table if it does not exist
        try {
            self::$connection->exec("CREATE TABLE IF NOT EXISTS aics_comments (
                comment_id INT AUTO_INCREMENT PRIMARY KEY,
                aics_id INT NOT NULL,
                user_id INT NOT NULL,
                comment_text TEXT NOT NULL,
                attachment_path VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_aics_id (aics_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        } catch (PDOException $e) {
            error_log('[Migration Error] ' . $e->getMessage());
        }

        // Auto-create roles table and role_id in users (RBAC implementation)
        try {
            self::$connection->exec("CREATE TABLE IF NOT EXISTS roles (
                role_id INT PRIMARY KEY AUTO_INCREMENT,
                role_name VARCHAR(50) NOT NULL UNIQUE,
                display_name VARCHAR(100) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            self::$connection->exec("INSERT INTO roles (role_id, role_name, display_name, description) VALUES
                (1, 'admin', 'Administrator', 'Full system administration access'),
                (2, 'staff', 'Staff Member', 'Authorized operational access'),
                (3, 'resident', 'Resident', 'Access to personal profiles, services, applications, and appointments')
                ON DUPLICATE KEY UPDATE 
                    role_name = VALUES(role_name),
                    display_name = VALUES(display_name),
                    description = VALUES(description)");

            $colCheck = self::$connection->query("SHOW COLUMNS FROM users LIKE 'role_id'")->fetch();
            if (!$colCheck) {
                self::$connection->exec("ALTER TABLE users ADD COLUMN role_id INT NOT NULL DEFAULT 3 AFTER role");
                self::$connection->exec("UPDATE users SET role_id = 1 WHERE role = 'admin'");
                self::$connection->exec("UPDATE users SET role_id = 2 WHERE role = 'staff'");
                self::$connection->exec("UPDATE users SET role_id = 3 WHERE role = 'resident' OR role IS NULL OR role_id IS NULL OR role_id = 0");
                try {
                    self::$connection->exec("ALTER TABLE users ADD INDEX idx_users_role_id (role_id)");
                } catch (Exception $e) {}
            }
        } catch (PDOException $e) {
            error_log('[RBAC Migration Error] ' . $e->getMessage());
        }

        // Auto-create CMS tables and services columns (CMS implementation)
        try {
            self::$connection->exec("CREATE TABLE IF NOT EXISTS cms_content (
                content_id INT AUTO_INCREMENT PRIMARY KEY,
                section VARCHAR(50) NOT NULL,
                content_key VARCHAR(100) NOT NULL UNIQUE,
                content_value LONGTEXT NULL,
                content_type ENUM('text', 'textarea', 'html', 'image', 'boolean', 'json') DEFAULT 'text',
                label VARCHAR(150) NOT NULL,
                help_text VARCHAR(255) NULL,
                sort_order INT DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT NULL,
                INDEX idx_cms_section (section),
                INDEX idx_cms_key (content_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            self::$connection->exec("CREATE TABLE IF NOT EXISTS media_library (
                media_id INT AUTO_INCREMENT PRIMARY KEY,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_type VARCHAR(50) NOT NULL,
                file_size INT NOT NULL,
                dimensions VARCHAR(50) NULL,
                alt_text VARCHAR(255) NULL,
                category VARCHAR(50) DEFAULT 'general',
                uploaded_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_media_cat (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $svcCheck = self::$connection->query("SHOW COLUMNS FROM services LIKE 'badge'")->fetch();
            if (!$svcCheck) {
                self::$connection->exec("ALTER TABLE services 
                    ADD COLUMN badge VARCHAR(100) NULL AFTER service_name,
                    ADD COLUMN icon VARCHAR(100) NULL DEFAULT 'fas fa-hands-helping' AFTER badge,
                    ADD COLUMN short_desc TEXT NULL AFTER description,
                    ADD COLUMN eligibility LONGTEXT NULL AFTER short_desc,
                    ADD COLUMN `procedure` LONGTEXT NULL AFTER requirements,
                    ADD COLUMN fees VARCHAR(150) DEFAULT 'Free of Charge' AFTER processing_time,
                    ADD COLUMN where_to_apply VARCHAR(255) NULL AFTER fees,
                    ADD COLUMN apply_url VARCHAR(255) NULL AFTER where_to_apply,
                    ADD COLUMN sort_order INT DEFAULT 0 AFTER is_active,
                    ADD COLUMN is_featured TINYINT(1) DEFAULT 1 AFTER sort_order,
                    ADD COLUMN image_path VARCHAR(255) NULL AFTER apply_url");
            }
        } catch (PDOException $e) {
            error_log('[CMS Migration Error] ' . $e->getMessage());
        }

        return self::$connection;
    }
}

// ─── RBAC Role Constants ──────────────────────────────────────────────────────
if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 1);
}
if (!defined('ROLE_STAFF')) {
    define('ROLE_STAFF', 2);
}
if (!defined('ROLE_RESIDENT')) {
    define('ROLE_RESIDENT', 3);
}

// ─── Auth & RBAC Helper Functions ─────────────────────────────────────────────
function time_ago($timestamp): string
{
    $difference = time() - $timestamp;
    if ($difference < 1) {
        return 'just now';
    }
    
    $intervals = [
        31536000 => 'year',
        2592000 => 'month',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];
    
    foreach ($intervals as $secs => $label) {
        $div = $difference / $secs;
        if ($div >= 1) {
            $count = round($div);
            return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function getRoleId(): ?int
{
    if (isset($_SESSION['role_id'])) {
        return (int)$_SESSION['role_id'];
    }
    if (isset($_SESSION['role'])) {
        return match($_SESSION['role']) {
            'admin' => ROLE_ADMIN,
            'staff' => ROLE_STAFF,
            'resident' => ROLE_RESIDENT,
            default => null
        };
    }
    return null;
}

function getRoleName(): ?string
{
    if (isset($_SESSION['role'])) {
        return (string)$_SESSION['role'];
    }
    return match(getRoleId()) {
        ROLE_ADMIN => 'admin',
        ROLE_STAFF => 'staff',
        ROLE_RESIDENT => 'resident',
        default => null
    };
}

function isAdmin(): bool
{
    return (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === ROLE_ADMIN) ||
           (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

function isStaff(): bool
{
    return (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === ROLE_STAFF) ||
           (isset($_SESSION['role']) && $_SESSION['role'] === 'staff');
}

function isResident(): bool
{
    return (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === ROLE_RESIDENT) ||
           (isset($_SESSION['role']) && $_SESSION['role'] === 'resident');
}

function hasRole(int|string|array $roles): bool
{
    if (!isLoggedIn()) return false;
    $roles = (array)$roles;
    $currentId = getRoleId();
    $currentName = getRoleName();
    foreach ($roles as $r) {
        if (is_numeric($r) && (int)$r === $currentId) return true;
        if (is_string($r) && strcasecmp($r, (string)$currentName) === 0) return true;
    }
    return false;
}

function getRoleDashboardUrl(?int $role_id = null): string
{
    $role_id = $role_id ?? getRoleId();
    return match($role_id) {
        ROLE_ADMIN => 'admin/dashboard.php',
        ROLE_STAFF => 'staff-dashboard.php',
        ROLE_RESIDENT => 'dashboard.php',
        default => 'dashboard.php'
    };
}

function requireLogin(string $redirect = 'login.php'): void
{
    if (!isLoggedIn()) {
        redirect($redirect);
    }
}

function requireRole(int|string|array $allowed_roles, string $redirect = 'login.php'): void
{
    if (!isLoggedIn()) {
        redirect($redirect);
    }
    if (!hasRole($allowed_roles)) {
        redirect(getRoleDashboardUrl());
    }
}

function redirect(string $url): never
{
    header("Location: $url");
    exit();
}

/**
 * Sanitize a string for safe HTML output.
 * NOTE: Do NOT use this for DB values — use PDO prepared statements instead.
 */
function sanitize(string $input): string
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Build a full URL relative to the application root.
 */
function baseUrl(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Generate a unique, formatted Resident Number (e.g. RES-2026-0001).
 * Finds the highest assigned sequence for the year and increments safely without collisions.
 */
function generateResidentNumber(PDO $db, ?int $year = null): string
{
    $year = $year ?: (int)date('Y');
    
    // Find the current highest sequence number for this year
    $stmt = $db->prepare("SELECT resident_number FROM residents WHERE resident_number LIKE :pattern");
    $stmt->execute([':pattern' => "RES-{$year}-%"]);
    $highest = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['resident_number']) && preg_match("/RES-{$year}-(\d+)/", $row['resident_number'], $matches)) {
            $num = (int)$matches[1];
            if ($num > $highest) {
                $highest = $num;
            }
        }
    }
    
    $nextSeq = $highest + 1;
    $checkStmt = $db->prepare("SELECT id FROM residents WHERE resident_number = :res_num");
    do {
        $candidate = 'RES-' . $year . '-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        $checkStmt->execute([':res_num' => $candidate]);
        $exists = $checkStmt->fetchColumn();
        if ($exists) {
            $nextSeq++;
        }
    } while ($exists);

    return $candidate;
}

// ─── CSRF Protection ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/csrf.php';
