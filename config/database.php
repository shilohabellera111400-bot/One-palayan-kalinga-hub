<?php
// ─── Timezone ────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ─── App URL (change this when deploying) ────────────────────────────────────
// Dynamically detect local or live environment to configure APP_URL
if (
    isset($_SERVER['HTTP_HOST']) && 
    (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)
) {
    define('APP_URL', 'http://localhost/palayan_kalinga_hub');
} else {
    define('APP_URL', 'https://palayan-kalinga-hub.infinityfreeapp.com');
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
        
        $redirect_path = (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false))
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
        if (
            isset($_SERVER['HTTP_HOST']) && 
            (strpos($_SERVER['HTTP_HOST'], 'localhost') === false && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false)
        ) {
            // Live hosting (InfinityFree) configuration
            $this->host = "sql107.infinityfree.com";
            $this->db_name = "if0_42359268_palayan_kalinga_hub";
            $this->username = "if0_42359268";
            $this->password = "PalayanKalinga";
        } else {
            // Local development configuration
            $this->host = "localhost";
            $this->db_name = "palayan_kalinga_hub";
            $this->username = "root";
            $this->password = "";
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

        return self::$connection;
    }
}

// ─── Auth Helper Functions ────────────────────────────────────────────────────
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

function isStaff(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

function isAdmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isResident(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'resident';
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

// ─── CSRF Protection ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/csrf.php';
