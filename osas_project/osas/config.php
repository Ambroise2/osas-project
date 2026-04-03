<?php
// ============================================
//  OSAS - Database Configuration
//  Edit DB_USER / DB_PASS to match your XAMPP
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Default XAMPP password is empty
define('DB_NAME', 'osas_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;padding:2rem;background:#fff0f0;color:#c00;border-radius:10px;margin:2rem;">
        <strong>Database Connection Error:</strong> ' . htmlspecialchars($conn->connect_error) . '
        <br><br><small>Please ensure XAMPP is running and you have imported <code>setup.sql</code>.</small>
    </div>');
}
$conn->set_charset('utf8mb4');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── HELPER FUNCTIONS ─────────────────────────────────────────

function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin($role = null) {
    if (!isLoggedIn()) {
        redirect('/index.php');
    }
    if ($role && $_SESSION['role'] !== $role) {
        redirect('/index.php');
    }
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function showFlash() {
    $f = getFlash();
    if ($f) {
        $icon = $f['type'] === 'success' ? '✅' : ($f['type'] === 'error' ? '❌' : 'ℹ️');
        echo '<div class="alert alert-' . h($f['type']) . '">' . $icon . ' ' . h($f['message']) . '</div>';
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function initials($name) {
    $parts = explode(' ', trim($name));
    $init = '';
    foreach ($parts as $p) $init .= strtoupper(substr($p, 0, 1));
    return substr($init, 0, 2);
}
?>
