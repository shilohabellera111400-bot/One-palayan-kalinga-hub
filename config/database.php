<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class Database {
    private $host = "localhost";
    private $db_name = "palayan_kalinga_hub";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isStaff() {
    return isset($_SESSION['role']) && ($_SESSION['role'] == 'staff');
}

function isAdmin() {
    return isset($_SESSION['role']) && ($_SESSION['role'] == 'admin');
}

function isResident() {
    return isset($_SESSION['role']) && ($_SESSION['role'] == 'resident');
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Base URL function (helps with paths)
function baseUrl($path = '') {
    // Get the current script path
    $base = 'http://' . $_SERVER['HTTP_HOST'] . '/palayan_kalinga_hub/';
    return $base . ltrim($path, '/');
}
?>