<?php
include 'config/database.php';
require_once 'config/security.php';
// BARU kode lain

header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['order_id']) || isset($_GET['session_id'])) {
    $order_id   = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    $session_id = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';
    
    // Cek kolom yang tersedia
    $columns = [];
    $r = mysqli_query($conn, "SHOW COLUMNS FROM orders");
    while ($row = mysqli_fetch_assoc($r)) $columns[] = $row['Field'];
    
    $has_session_col = in_array('session_id', $columns);
    $has_payment_col = in_array('status_pembayaran', $columns);
    
    // Bangun SELECT
    $select = "status";
    if ($has_payment_col) {
        $select .= ", status_pembayaran";
    }
    
    // Prioritas: cari berdasarkan session_id (grouping), fallback ke order_id
    if (!empty($session_id) && $has_session_col) {
        // Agregasi: kalau salah satu cancelled → cancelled (prioritas tertinggi)
        // kalau semua completed → completed
        $sql = "SELECT 
                    CASE 
                        WHEN SUM(status = 'cancelled') > 0 THEN 'cancelled'
                        WHEN SUM(status = 'processing') > 0 THEN 'processing'
                        WHEN SUM(status = 'pending') > 0 THEN 'pending'
                        WHEN SUM(status = 'completed') = COUNT(*) THEN 'completed'
                        ELSE MIN(status)
                    END as status";
        
        if ($has_payment_col) {
            $sql .= ", CASE 
                        WHEN SUM(status_pembayaran = 'pending') > 0 THEN 'pending'
                        ELSE 'paid'
                      END as status_pembayaran";
        }
        
        $sql .= " FROM orders WHERE session_id = ?";
        
        $order = db_row($conn, $sql, [$session_id]);
        
    } elseif ($order_id > 0) {
        $sql = "SELECT $select FROM orders WHERE id = ? LIMIT 1";
        $order = db_row($conn, $sql, [$order_id]);
    } else {
        echo json_encode(['error' => 'Invalid parameters']);
        exit();
    }
    
    if ($order) {
        echo json_encode([
            'status' => $order['status'] ?? 'pending',
            'payment_status' => $order['status_pembayaran'] ?? 'pending'
        ]);
    } else {
        echo json_encode(['error' => 'Order not found']);
    }
} else {
    echo json_encode(['error' => 'No parameters provided']);
}