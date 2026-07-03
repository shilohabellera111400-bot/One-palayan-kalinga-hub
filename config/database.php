<?php
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
        header('Location: /palayan_kalinga_hub/login.php?timeout=1');
        exit();
    }
}
$_SESSION['last_activity'] = time();

// ─── Database Class ───────────────────────────────────────────────────────────
class Database {
    // Cache the PDO connection statically so every new Database() reuses it
    private static ?PDO $connection = null;

    private string $host     = "localhost";
    private string $db_name  = "palayan_kalinga_hub";
    private string $username = "root";
    private string $password = "";

    // Public constructor — keeps all existing `new Database()` calls working
    public function __construct() {}

    /**
     * Return (and lazily create) the shared PDO connection.
     * Calling this multiple times per request always returns the same PDO object.
     */
    public function getConnection(): ?PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }
        try {
            self::$connection = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => true,  // Keep true: MySQL needs this for repeated named placeholders in subqueries
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
        return self::$connection;
    }
}

// ─── Auth Helper Functions ────────────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isStaff(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isResident(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'resident';
}

function redirect(string $url): never {
    header("Location: $url");
    exit();
}

/**
 * Sanitize a string for safe HTML output.
 * NOTE: Do NOT use this for DB values — use PDO prepared statements instead.
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Build a full URL relative to the application root.
 */
function baseUrl(string $path = ''): string {
    $base = 'http://' . $_SERVER['HTTP_HOST'] . '/palayan_kalinga_hub/';
    return $base . ltrim($path, '/');
}

// ─── CSRF Protection ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/csrf.php';