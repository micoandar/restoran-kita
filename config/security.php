<?php
/**
 * Security Helper Functions
 * Restoran Kita — Upgrade Phase 1
 */

// Pastikan session sudah aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token (sekali per session)
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifikasi CSRF token dari POST
 */
function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output hidden input CSRF siap pakai di form
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Cek apakah string adalah hash MD5 (32 hex chars)
 * Untuk mendeteksi password lama yang perlu di-upgrade
 */
function isMd5Hash($str) {
    return is_string($str) && strlen($str) === 32 && ctype_xdigit($str);
}

/**
 * Cek apakah string adalah hash bcrypt/argon (modern)
 */
function isModernHash($str) {
    if (!is_string($str) || strlen($str) < 50) return false;
    return (strpos($str, '$2y$') === 0 || strpos($str, '$2a$') === 0 || strpos($str, '$argon2') === 0);
}

/**
 * Regenerate session ID (panggil setelah login sukses)
 * Cegah session fixation attack
 */
function regenerateSessionAfterLogin() {
    session_regenerate_id(true);
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * Escape output HTML (shortcut)
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Log error tanpa bocor ke user
 */
function logSecurityEvent($message) {
    $logFile = __DIR__ . '/../logs/security.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
    @file_put_contents(
        $logFile,
        "[$timestamp] [IP: $ip] $message" . PHP_EOL,
        FILE_APPEND
    );
}