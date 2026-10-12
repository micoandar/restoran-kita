<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

// ============================================================
// INCLUDE PALING ATAS — security.php akan start session
// ============================================================
require_once '../config/database.php';    // sudah auto-load db_helper.php
require_once '../config/security.php';     // auto session_start + CSRF helper

// ============================================================
// BARU CEK SESSION
// ============================================================
if (!isset($_SESSION['admin'])) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Session expired, silakan login ulang']);
    exit();
}

// Header JSON
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Helper response
function respond($data, $code = 200) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

// Hanya POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

// ============================================================
// CSRF VERIFICATION
// ============================================================
$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    logSecurityEvent("CSRF invalid di ajax_order_action.php");
    respond(['success' => false, 'message' => 'CSRF token tidak valid. Refresh halaman.'], 403);
}

// ============================================================
// CEK KOLOM
// ============================================================
$cols = [];
$r = mysqli_query($conn, "SHOW COLUMNS FROM orders");
if ($r) {
    while ($c = mysqli_fetch_assoc($r)) $cols[] = $c['Field'];
}

$has_session           = in_array('session_id', $cols);
$has_metode            = in_array('metode_pembayaran', $cols);
$has_status_pembayaran = in_array('status_pembayaran', $cols);

if (!$has_status_pembayaran) {
    respond(['success' => false, 'message' => 'Kolom status_pembayaran tidak ada'], 500);
}

$group_condition = $has_session
    ? "COALESCE(session_id, CONCAT(nama_pelanggan, '_', DATE(created_at), '_', HOUR(created_at), '_', MINUTE(created_at)))"
    : "CONCAT(nama_pelanggan, '_', DATE(created_at), '_', HOUR(created_at), '_', MINUTE(created_at))";

// Helper: cari by session_id atau virtual group
function buildWhereBySession($conn, $virtual_session_id, $has_session, $group_condition) {
    if ($has_session) {
        $exists = db_row($conn, "SELECT session_id FROM orders WHERE session_id = ? LIMIT 1", [$virtual_session_id]);
        if ($exists) {
            return ['session_id = ?', [$virtual_session_id]];
        }
    }
    return ["$group_condition = ?", [$virtual_session_id]];
}

// ============================================================
// UPDATE STATUS ORDER
// ============================================================
if (isset($_POST['update_status'])) {
    $virtual_session_id = trim($_POST['session_id'] ?? '');
    $status = trim($_POST['status'] ?? '');
    
    if (empty($virtual_session_id) || empty($status)) {
        respond(['success' => false, 'message' => 'Parameter tidak lengkap'], 400);
    }
    
    $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        respond(['success' => false, 'message' => 'Status tidak valid'], 400);
    }
    
    [$where_sql, $where_params] = buildWhereBySession($conn, $virtual_session_id, $has_session, $group_condition);
    
    // Kalau status = completed → auto set status_pembayaran = paid
    if ($status === 'completed') {
        $sql = "UPDATE orders SET status = ?, status_pembayaran = 'paid' WHERE $where_sql";
        $params = array_merge([$status], $where_params);
    } else {
        $sql = "UPDATE orders SET status = ? WHERE $where_sql";
        $params = array_merge([$status], $where_params);
    }
    
    $affected = db_execute($conn, $sql, $params);
    
    if ($affected > 0) {
        respond([
            'success'      => true,
            'message'      => "Status order berhasil diperbarui ($affected item)",
            'affected'     => $affected,
            'status'       => $status,
            'status_label' => ucfirst($status),
            'status_class' => 'status-' . $status
        ]);
    } else {
        respond(['success' => false, 'message' => 'Tidak ada perubahan (mungkin status sudah sama)'], 200);
    }
}

// ============================================================
// UPDATE STATUS PEMBAYARAN
// ============================================================
if (isset($_POST['update_payment_status'])) {
    $virtual_session_id = trim($_POST['session_id'] ?? '');
    $payment_status = trim($_POST['payment_status'] ?? '');
    
    if (empty($virtual_session_id) || empty($payment_status)) {
        respond(['success' => false, 'message' => 'Parameter tidak lengkap'], 400);
    }
    
    if (!in_array($payment_status, ['pending', 'paid'])) {
        respond(['success' => false, 'message' => 'Status pembayaran tidak valid'], 400);
    }
    
    [$where_sql, $where_params] = buildWhereBySession($conn, $virtual_session_id, $has_session, $group_condition);
    
    $sql = "UPDATE orders SET status_pembayaran = ? WHERE $where_sql";
    $params = array_merge([$payment_status], $where_params);
    
    $affected = db_execute($conn, $sql, $params);
    
    $label = ($payment_status === 'paid') ? 'Lunas' : 'Pending';
    
    respond([
        'success'         => true,
        'message'         => "Status pembayaran berhasil diperbarui ($affected item)",
        'affected'        => $affected,
        'payment_status'  => $payment_status,
        'payment_label'   => $label,
        'payment_class'   => 'payment-' . $payment_status
    ]);
}

respond(['success' => false, 'message' => 'Action tidak dikenali'], 400);