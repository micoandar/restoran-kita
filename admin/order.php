<?php
// ============================================================
// ANTI-OUTPUT-BOCOR
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

require_once '../config/database.php';
require_once '../config/security.php';

// Redirect jika belum login
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// ============================================================
// CEK KOLOM
// ============================================================
$check_payment_columns = mysqli_query($conn, "SHOW COLUMNS FROM orders");
$columns = [];
if ($check_payment_columns) {
    while ($row = mysqli_fetch_assoc($check_payment_columns)) {
        $columns[] = $row['Field'];
    }
}

if (!in_array('metode_pembayaran', $columns)) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN metode_pembayaran VARCHAR(20) DEFAULT 'cash'");
    $columns[] = 'metode_pembayaran';
}
if (!in_array('status_pembayaran', $columns)) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN status_pembayaran VARCHAR(20) DEFAULT 'pending'");
    $columns[] = 'status_pembayaran';
}
if (!in_array('session_id', $columns)) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN session_id VARCHAR(100) NULL");
    $columns[] = 'session_id';
}

// Generate session_id untuk data lama
$check_no_session = db_query($conn, "SELECT id FROM orders WHERE session_id IS NULL OR session_id = '' LIMIT 1");
if ($check_no_session && mysqli_num_rows($check_no_session) > 0) {
    mysqli_query($conn, "UPDATE orders SET session_id = CONCAT(nama_pelanggan, '_', DATE(created_at), '_', FLOOR(RAND() * 10000)) WHERE session_id IS NULL OR session_id = ''");
}

$group_condition = in_array('session_id', $columns)
    ? "COALESCE(session_id, CONCAT(nama_pelanggan, '_', DATE(created_at), '_', HOUR(created_at), '_', MINUTE(created_at)))"
    : "CONCAT(nama_pelanggan, '_', DATE(created_at), '_', HOUR(created_at), '_', MINUTE(created_at))";

$has_metode = in_array('metode_pembayaran', $columns);
$has_status_pembayaran = in_array('status_pembayaran', $columns);

// ============================================================
// HAPUS ORDER (via GET, CSRF via token di URL)
// ============================================================
if (isset($_GET['hapus'])) {
    if (!verifyCsrfToken($_GET['csrf'] ?? '')) {
        $_SESSION['error'] = "CSRF token tidak valid. Coba lagi.";
        header("Location: order.php");
        exit();
    }
    
    $virtual_session_id = trim($_GET['hapus']);
    
    if (in_array('session_id', $columns)) {
        $check_session = db_row($conn, "SELECT session_id FROM orders WHERE session_id = ? LIMIT 1", [$virtual_session_id]);
        if ($check_session) {
            $affected = db_execute($conn, "DELETE FROM orders WHERE session_id = ?", [$virtual_session_id]);
        } else {
            $affected = db_execute($conn, "DELETE FROM orders WHERE $group_condition = ?", [$virtual_session_id]);
        }
    } else {
        $affected = db_execute($conn, "DELETE FROM orders WHERE $group_condition = ?", [$virtual_session_id]);
    }
    
    if ($affected > 0) {
        $_SESSION['success'] = "Semua item order ($affected item) berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus order atau order tidak ditemukan.";
    }
    
    header("Location: order.php?" . http_build_query(array_diff_key($_GET, ['hapus' => '', 'csrf' => ''])));
    exit();
}

if (isset($_GET['hapus_item'])) {
    if (!verifyCsrfToken($_GET['csrf'] ?? '')) {
        $_SESSION['error'] = "CSRF token tidak valid.";
        header("Location: order.php");
        exit();
    }
    
    $item_id = (int)$_GET['hapus_item'];
    $affected = db_execute($conn, "DELETE FROM orders WHERE id = ?", [$item_id]);
    
    if ($affected > 0) {
        $_SESSION['success'] = "Item berhasil dihapus dari order.";
    } else {
        $_SESSION['error'] = "Gagal menghapus item.";
    }
    
    header("Location: order.php?" . http_build_query(array_diff_key($_GET, ['hapus_item' => '', 'csrf' => ''])));
    exit();
}

// ============================================================
// FILTER & QUERY DISPLAY
// ============================================================
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$records_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

$whereConditions = [];
$whereParams = [];

if (!empty($start_date) && !empty($end_date)) {
    $whereConditions[] = "DATE(created_at) BETWEEN ? AND ?";
    $whereParams[] = $start_date;
    $whereParams[] = $end_date;
} elseif (!empty($start_date)) {
    $whereConditions[] = "DATE(created_at) >= ?";
    $whereParams[] = $start_date;
} elseif (!empty($end_date)) {
    $whereConditions[] = "DATE(created_at) <= ?";
    $whereParams[] = $end_date;
}

if (isset($_GET['status']) && $_GET['status'] != 'all' && $_GET['status'] != '') {
    $whereConditions[] = "status = ?";
    $whereParams[] = $_GET['status'];
}

if ($has_metode && isset($_GET['payment_method']) && $_GET['payment_method'] != 'all' && $_GET['payment_method'] != '') {
    $whereConditions[] = "metode_pembayaran = ?";
    $whereParams[] = $_GET['payment_method'];
}

if ($has_status_pembayaran && isset($_GET['payment_status']) && $_GET['payment_status'] != 'all' && $_GET['payment_status'] != '') {
    $whereConditions[] = "status_pembayaran = ?";
    $whereParams[] = $_GET['payment_status'];
}

if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search_term = '%' . trim($_GET['search']) . '%';
    $whereConditions[] = "(nama_pelanggan LIKE ? OR menu LIKE ?)";
    $whereParams[] = $search_term;
    $whereParams[] = $search_term;
}

$where = "WHERE 1=1";
if (!empty($whereConditions)) {
    $where .= " AND " . implode(" AND ", $whereConditions);
}

$grouped_orders_select = "
    SELECT 
        $group_condition as virtual_session_id,
        MIN(id) as id,
        MIN(created_at) as created_at,
        nama_pelanggan,
        GROUP_CONCAT(DISTINCT menu ORDER BY id SEPARATOR '||') as menu_items,
        GROUP_CONCAT(jumlah ORDER BY id SEPARATOR ',') as quantities,
        GROUP_CONCAT(harga ORDER BY id SEPARATOR ',') as prices,
        SUM(total) as total,
        MIN(status) as status";

if ($has_metode) $grouped_orders_select .= ", MIN(metode_pembayaran) as metode_pembayaran";
if ($has_status_pembayaran) $grouped_orders_select .= ", MIN(status_pembayaran) as status_pembayaran";
if (in_array('session_id', $columns)) $grouped_orders_select .= ", MIN(session_id) as session_id";

$grouped_orders_select .= ",
        GROUP_CONCAT(COALESCE(catatan, '') SEPARATOR '||') as catatan,
        COUNT(*) as item_count
    FROM orders 
    $where 
    GROUP BY $group_condition
    ORDER BY MIN(created_at) DESC";

// Total records
$total_records = (int) db_val($conn, 
    "SELECT COUNT(DISTINCT $group_condition) as total FROM orders $where", 
    $whereParams, 
    0
);
$total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 0;

// Ambil order list dengan pagination
$orders_select = "SELECT grouped.* FROM ($grouped_orders_select) as grouped LIMIT ?, ?";
$orders_params = array_merge($whereParams, [$offset, $records_per_page]);
$orders = db_query($conn, $orders_select, $orders_params);

// Total Pendapatan (hanya completed)
$total_revenue = (float) db_val($conn, "
    SELECT SUM(total_sum) as total FROM (
        SELECT $group_condition, SUM(total) as total_sum 
        FROM orders 
        $where AND status = 'completed'
        GROUP BY $group_condition
    ) as completed_orders
", $whereParams, 0);

// Total semua order (hanya completed, konsisten dengan customer.php)
$all_orders = (int) db_val($conn, 
    "SELECT COUNT(*) as total FROM orders WHERE status = 'completed'", 
    [], 
    0
);

// Pelanggan unik
$unique_customers = (int) db_val($conn, 
    "SELECT COUNT(DISTINCT nama_pelanggan) as total FROM orders $where", 
    $whereParams, 
    0
);

// Ringkasan pembayaran
$cash_paid = 0;
$pending_payment = 0;
if ($has_metode && $has_status_pembayaran) {
    $payment_summary = db_row($conn, "
        SELECT 
            COALESCE(SUM(CASE WHEN metode_pembayaran = 'cash' AND status_pembayaran = 'paid' THEN total ELSE 0 END), 0) as cash_paid,
            COALESCE(SUM(CASE WHEN status_pembayaran = 'pending' THEN total ELSE 0 END), 0) as pending_payment
        FROM (
            SELECT $group_condition, 
                   MIN(metode_pembayaran) as metode_pembayaran,
                   MIN(status_pembayaran) as status_pembayaran,
                   SUM(total) as total
            FROM orders 
            $where
            GROUP BY $group_condition
        ) as payment_summary
    ", $whereParams);
    
    if ($payment_summary) {
        $cash_paid = $payment_summary['cash_paid'] ?: 0;
        $pending_payment = $payment_summary['pending_payment'] ?: 0;
    }
}

// Debug info
$debug_data = db_row($conn, "
    SELECT 
        COUNT(DISTINCT $group_condition) as total_groups, 
        COUNT(*) as total_items
    FROM orders $where
", $whereParams);
if (!$debug_data) {
    $debug_data = ['total_groups' => 0, 'total_items' => 0];
}

// CSRF token
$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Data Order - Restoran Kita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        .filter-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
        .stats-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); text-align: center; margin-bottom: 20px; }
        .stats-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 15px; }
        .stats-icon.orders { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stats-icon.revenue { background: linear-gradient(135deg, #4facfe, #00f2fe); color: white; }
        .stats-icon.customers { background: linear-gradient(135deg, #43e97b, #38f9d7); color: white; }
        .stats-icon.cash { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .stats-icon.pending-payment { background: linear-gradient(135deg, #ffc107, #ffb347); color: white; }
        .stats-value { font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-bottom: 5px; }
        .stats-label { color: #6c757d; font-size: 0.9rem; }
        .order-status { padding: 5px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 500; }
        .status-pending { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .status-processing { background-color: rgba(0, 123, 255, 0.1); color: #007bff; }
        .status-completed { background-color: rgba(40, 167, 69, 0.1); color: #28a745; }
        .status-cancelled { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .payment-method-badge { padding: 5px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 500; }
        .payment-cash { background-color: rgba(40, 167, 69, 0.1); color: #28a745; }
        .payment-status-badge { padding: 5px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 500; }
        .payment-pending { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .payment-paid { background-color: rgba(40, 167, 69, 0.1); color: #28a745; }
        .action-dropdown .dropdown-menu { min-width: 150px; padding: 10px 0; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); border: none; }
        .action-dropdown .dropdown-item { padding: 8px 15px; display: flex; align-items: center; }
        .action-dropdown .dropdown-item i { width: 20px; margin-right: 10px; }
        .action-dropdown .dropdown-item:hover { background-color: rgba(255, 107, 107, 0.1); color: var(--primary); }
        .table-container { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); }
        .table-responsive { border-radius: 15px; }
        .export-btn { background: linear-gradient(135deg, #28a745, #20c997); border: none; color: white; padding: 8px 20px; border-radius: 8px; font-weight: 500; }
        .export-btn:hover { background: linear-gradient(135deg, #218838, #1ea97f); color: white; }
        .empty-state { text-align: center; padding: 50px 20px; color: #6c757d; }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; color: #dee2e6; }
        .database-info { background-color: #f8f9fa; padding: 10px 20px; border-bottom: 1px solid #dee2e6; font-size: 0.9rem; color: #6c757d; }
        .filter-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-reset { background: #6c757d; border: none; color: white; padding: 8px 20px; border-radius: 8px; font-weight: 500; }
        .btn-reset:hover { background: #5a6268; color: white; }
        .search-btn { background: var(--primary); border: none; color: white; padding: 8px 20px; border-radius: 8px; font-weight: 500; }
        .search-btn:hover { background: var(--primary-dark); color: white; }
        .form-group-icon { position: relative; }
        .form-group-icon .form-control { padding-left: 40px; }
        .form-group-icon i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6c757d; }
        .badge-items { background-color: #e9ecef; color: #495057; font-size: 0.8rem; padding: 3px 8px; border-radius: 10px; margin-left: 5px; }
        .item-list { max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 5px; padding: 10px; margin-top: 10px; }
        .item-row { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid #f1f1f1; }
        .item-row:last-child { border-bottom: none; }
        .item-name { flex-grow: 1; }
        .item-qty { margin: 0 10px; color: #666; }
        .item-price { color: #28a745; font-weight: 500; }
        .item-total { font-weight: 600; color: var(--primary); }
        .item-remove { color: #dc3545; cursor: pointer; margin-left: 10px; }
        .item-remove:hover { color: #bd2130; }
        .order-group-header { background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 10px; border-left: 4px solid var(--primary); }
        .refresh-btn { background: var(--primary); border: none; color: white; padding: 8px 20px; border-radius: 8px; font-weight: 500; }
        .refresh-btn:hover { background: var(--primary-dark); color: white; }
        .payment-summary { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); margin-bottom: 20px; }
        .payment-summary-title { font-size: 1.2rem; font-weight: 600; color: var(--dark); margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .filter-row { margin-bottom: 15px; }
        .filter-label { font-weight: 500; margin-bottom: 5px; color: #495057; }
        .order-group-badge { background-color: #6f42c1; color: white; padding: 3px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: 5px; }

        /* Tombol cetak invoice di modal */
        .btn-print-invoice {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            border: none;
            color: white;
            font-weight: 600;
            padding: 8px 20px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .btn-print-invoice:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
            color: white;
        }
    </style>
</head>
<body class="admin-body">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-utensils me-2"></i>Admin Panel</h3>
            <small>Restoran Kita</small>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                <li><a href="menu.php"><i class="fas fa-utensils"></i><span>Kelola Menu</span></a></li>
                <li><a href="order.php" class="active"><i class="fas fa-shopping-cart"></i><span>Data Order</span></a></li>
                <li><a href="customer.php"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
                <li><a href="report.php"><i class="fas fa-chart-bar"></i><span>Laporan</span></a></li>
                <li><a href="chatbot.php"><i class="fas fa-robot"></i><span>Chatbot FAQ</span></a></li>
                <li><a href="../index.php" target="_blank"><i class="fas fa-eye"></i><span>Lihat Website Utama</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
            </ul>
        </div>
    </div>
    
    <div class="main-content">
        <header class="admin-header">
            <button class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="mb-0">Data Order</h4>
            <div class="header-right">
                <div class="admin-avatar"><?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?></div>
                <div>
                    <div class="fw-bold"><?php echo e($_SESSION['admin']); ?></div>
                    <small class="text-muted">Administrator</small>
                </div>
            </div>
        </header>
        
        <div class="content-wrapper">
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-admin">
                <i class="fas fa-check-circle me-2"></i><?php echo e($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-admin">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo e($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>
            
            <div class="database-info">
                <small>
                    <i class="fas fa-database me-1"></i> 
                    Menampilkan: <?php echo $debug_data['total_groups']; ?> order kelompok 
                    (<?php echo $debug_data['total_items']; ?> item total)
                </small>
            </div>
            
            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-icon orders"><i class="fas fa-shopping-cart"></i></div>
                        <div class="stats-value"><?php echo $all_orders; ?></div>
                        <div class="stats-label">Total Order Selesai (Item)</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-icon revenue"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="stats-value">Rp <?php echo number_format($total_revenue); ?></div>
                        <div class="stats-label">Total Pendapatan</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-icon customers"><i class="fas fa-users"></i></div>
                        <div class="stats-value"><?php echo $unique_customers; ?></div>
                        <div class="stats-label">Pelanggan Unik</div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Summary -->
            <?php if($has_metode && $has_status_pembayaran): ?>
            <div class="payment-summary">
                <div class="payment-summary-title">
                    <i class="fas fa-credit-card me-2"></i>Ringkasan Pembayaran
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="stats-card">
                            <div class="stats-icon cash"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="stats-value">Rp <?php echo number_format($cash_paid); ?></div>
                            <div class="stats-label">Tunai (Lunas)</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card">
                            <div class="stats-icon pending-payment"><i class="fas fa-clock"></i></div>
                            <div class="stats-value">Rp <?php echo number_format($pending_payment); ?></div>
                            <div class="stats-label">Menunggu Pembayaran</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Filter -->
            <div class="filter-card">
                <form method="get" id="filterForm">
                    <input type="hidden" name="page" value="1">
                    
                    <div class="row filter-row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label filter-label">Tanggal Mulai (opsional)</label>
                            <div class="form-group-icon">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" class="form-control" name="start_date" value="<?php echo e($start_date); ?>">
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label filter-label">Tanggal Akhir (opsional)</label>
                            <div class="form-group-icon">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" class="form-control" name="end_date" value="<?php echo e($end_date); ?>">
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label filter-label">Status Order</label>
                            <div class="form-group-icon">
                                <i class="fas fa-tags"></i>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo (!isset($_GET['status']) || $_GET['status'] == 'all') ? 'selected' : ''; ?>>Semua Status</option>
                                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo (isset($_GET['status']) && $_GET['status'] == 'processing') ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] == 'cancelled') ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label filter-label">Cari</label>
                            <div class="form-group-icon">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Nama atau menu..." value="<?php echo isset($_GET['search']) ? e($_GET['search']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row filter-row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label filter-label">Metode Pembayaran</label>
                            <div class="form-group-icon">
                                <i class="fas fa-credit-card"></i>
                                <select class="form-select" name="payment_method" <?php echo !$has_metode ? 'disabled' : ''; ?>>
                                    <option value="all" <?php echo (!isset($_GET['payment_method']) || $_GET['payment_method'] == 'all') ? 'selected' : ''; ?>>Semua Metode</option>
                                    <option value="cash" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'cash') ? 'selected' : ''; ?>>Tunai / Cash</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label filter-label">Status Pembayaran</label>
                            <div class="form-group-icon">
                                <i class="fas fa-money-check-alt"></i>
                                <select class="form-select" name="payment_status" <?php echo !$has_status_pembayaran ? 'disabled' : ''; ?>>
                                    <option value="all" <?php echo (!isset($_GET['payment_status']) || $_GET['payment_status'] == 'all') ? 'selected' : ''; ?>>Semua Status</option>
                                    <option value="pending" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'paid') ? 'selected' : ''; ?>>Lunas</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <button type="submit" class="search-btn w-100">
                                <i class="fas fa-filter me-2"></i>Filter Data
                            </button>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" class="export-btn" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </button>
                        <button type="button" class="refresh-btn" onclick="window.location.href='order.php'">
                            <i class="fas fa-redo me-2"></i>Refresh
                        </button>
                        <a href="order.php" class="btn-reset">
                            <i class="fas fa-times me-2"></i>Reset Filter
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Orders Table -->
            <div class="table-container">
                <?php if($orders && mysqli_num_rows($orders) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID Order</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Menu</th>
                                <th>Total Item</th>
                                <th>Total Harga</th>
                                <th>Status Order</th>
                                <?php if($has_metode): ?><th>Metode Bayar</th><?php endif; ?>
                                <?php if($has_status_pembayaran): ?><th>Status Bayar</th><?php endif; ?>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($order = mysqli_fetch_assoc($orders)): 
                                $status = $order['status'] ?? 'pending';
                                $statusClass = 'status-' . $status;
                                $statusText = ucfirst($status);
                                
                                $metode_pembayaran = $order['metode_pembayaran'] ?? 'cash';
                                $paymentMethodClass = 'payment-' . $metode_pembayaran;
                                $paymentMethodText = strtoupper($metode_pembayaran);
                                
                                $status_pembayaran = $order['status_pembayaran'] ?? 'pending';
                                $paymentStatusClass = 'payment-' . $status_pembayaran;
                                $paymentStatusText = $status_pembayaran == 'paid' ? 'Lunas' : 'Pending';
                                
                                $menu_items = explode('||', $order['menu_items']);
                                $quantities = explode(',', $order['quantities']);
                                $prices = explode(',', $order['prices']);
                                $item_count = $order['item_count'];
                                $count = min(count($menu_items), count($quantities), count($prices));
                                
                                $virtual_session_id = $order['virtual_session_id'];
                                $real_session_id = $order['session_id'] ?? $virtual_session_id;
                            ?>
                            <tr>
                                <td>
                                    #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                    <span class="badge-items"><?php echo $item_count; ?> item</span>
                                    <?php if($item_count > 1): ?>
                                    <span class="order-group-badge">Group</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($order['created_at'])); ?></td>
                                <td><?php echo e($order['nama_pelanggan']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#itemsModal<?php echo $order['id']; ?>">
                                        <i class="fas fa-list"></i> Lihat <?php echo $item_count; ?> item
                                    </button>
                                </td>
                                <td><?php echo $item_count; ?></td>
                                <td>Rp <?php echo number_format($order['total']); ?></td>
                                <td><span class="order-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <?php if($has_metode): ?>
                                <td><span class="payment-method-badge <?php echo $paymentMethodClass; ?>"><?php echo $paymentMethodText; ?></span></td>
                                <?php endif; ?>
                                <?php if($has_status_pembayaran): ?>
                                <td><span class="payment-status-badge <?php echo $paymentStatusClass; ?>"><?php echo $paymentStatusText; ?></span></td>
                                <?php endif; ?>
                                <td>
                                    <div class="dropdown action-dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $order['id']; ?>">
                                                    <i class="fas fa-eye"></i>Detail Order
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $order['id']; ?>">
                                                    <i class="fas fa-sync-alt"></i>Ubah Status Order
                                                </a>
                                            </li>
                                            <?php if($has_status_pembayaran): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo $order['id']; ?>">
                                                    <i class="fas fa-money-check-alt"></i>Ubah Status Bayar
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <li>
                                                <a class="dropdown-item" href="print_order.php?id=<?php echo $order['id']; ?>" target="_blank">
                                                    <i class="fas fa-print"></i>Cetak Invoice
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" 
                                                   href="?<?php echo http_build_query(array_merge($_GET, ['hapus' => $virtual_session_id, 'csrf' => $csrf_token])); ?>" 
                                                   onclick="return confirm('Yakin ingin menghapus seluruh order ini (<?php echo $item_count; ?> item)?')">
                                                    <i class="fas fa-trash"></i>Hapus Semua
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Items Modal -->
                                    <div class="modal fade" id="itemsModal<?php echo $order['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Detail Item Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="order-group-header">
                                                        <div class="row">
                                                            <div class="col-md-6"><strong>Pelanggan:</strong> <?php echo e($order['nama_pelanggan']); ?></div>
                                                            <div class="col-md-6"><strong>Tanggal:</strong> <?php echo date('d F Y H:i:s', strtotime($order['created_at'])); ?></div>
                                                        </div>
                                                    </div>
                                                    <h6>Daftar Item:</h6>
                                                    <div class="item-list">
                                                        <?php 
                                                        if (!empty($real_session_id) && in_array('session_id', $columns)) {
                                                            $items_result = db_query($conn, "SELECT * FROM orders WHERE session_id = ? ORDER BY id", [$real_session_id]);
                                                        } else {
                                                            $items_result = db_query($conn, "SELECT * FROM orders WHERE $group_condition = ? ORDER BY id", [$virtual_session_id]);
                                                        }
                                                        
                                                        if ($items_result && mysqli_num_rows($items_result) > 0):
                                                            while($item = mysqli_fetch_assoc($items_result)):
                                                        ?>
                                                        <div class="item-row">
                                                            <div class="item-name"><?php echo e($item['menu']); ?></div>
                                                            <div class="item-qty"><?php echo $item['jumlah']; ?> porsi</div>
                                                            <div class="item-price">Rp <?php echo number_format($item['harga']); ?></div>
                                                            <div class="item-total">Rp <?php echo number_format($item['total']); ?></div>
                                                            <div class="item-remove" onclick="if(confirm('Hapus item ini?')) { window.location.href='?<?php echo http_build_query(array_merge($_GET, ['hapus_item' => $item['id'], 'csrf' => $csrf_token])); ?>' }">
                                                                <i class="fas fa-times"></i>
                                                            </div>
                                                        </div>
                                                        <?php endwhile; endif; ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                    <button type="button" class="btn-print-invoice" onclick="printOrder('<?php echo $order['id']; ?>')">
                                                        <i class="fas fa-print"></i>Cetak Invoice
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Detail Modal -->
                                    <div class="modal fade" id="detailModal<?php echo $order['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Detail Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <table class="table table-sm">
                                                        <tr><td><strong>Nama</strong></td><td><?php echo e($order['nama_pelanggan']); ?></td></tr>
                                                        <tr><td><strong>Tanggal</strong></td><td><?php echo date('d F Y H:i:s', strtotime($order['created_at'])); ?></td></tr>
                                                        <tr><td><strong>Jumlah Item</strong></td><td><?php echo $item_count; ?> item</td></tr>
                                                        <tr><td><strong>Total Harga</strong></td><td class="fw-bold text-primary">Rp <?php echo number_format($order['total']); ?></td></tr>
                                                    </table>
                                                    <h6>Daftar Menu:</h6>
                                                    <table class="table table-sm">
                                                        <thead><tr><th>Menu</th><th>Jumlah</th><th>Harga</th><th>Subtotal</th></tr></thead>
                                                        <tbody>
                                                            <?php for($i = 0; $i < $count; $i++): 
                                                                $subtotal = (int)$quantities[$i] * (int)$prices[$i];
                                                            ?>
                                                            <tr>
                                                                <td><?php echo e($menu_items[$i]); ?></td>
                                                                <td><?php echo $quantities[$i]; ?></td>
                                                                <td>Rp <?php echo number_format($prices[$i]); ?></td>
                                                                <td>Rp <?php echo number_format($subtotal); ?></td>
                                                            </tr>
                                                            <?php endfor; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                    <button type="button" class="btn-print-invoice" onclick="printOrder('<?php echo $order['id']; ?>')">
                                                        <i class="fas fa-print"></i>Cetak Invoice
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Status Modal -->
                                    <div class="modal fade" id="statusModal<?php echo $order['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="post" class="ajax-action-form" action="javascript:void(0);">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Ubah Status Order</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="session_id" value="<?php echo e($real_session_id); ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Status Baru</label>
                                                            <select class="form-select" name="status" required>
                                                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Diproses</option>
                                                                <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Selesai</option>
                                                                <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">
                                                            <i class="fas fa-save me-2"></i>Simpan
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Modal -->
                                    <?php if($has_status_pembayaran): ?>
                                    <div class="modal fade" id="paymentModal<?php echo $order['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="post" class="ajax-action-form" action="javascript:void(0);">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Ubah Status Pembayaran</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="session_id" value="<?php echo e($real_session_id); ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Status Pembayaran Baru</label>
                                                            <select class="form-select" name="payment_status" required>
                                                                <option value="pending" <?php echo $status_pembayaran == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="paid" <?php echo $status_pembayaran == 'paid' ? 'selected' : ''; ?>>Lunas</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="update_payment_status" class="btn btn-primary">
                                                            <i class="fas fa-save me-2"></i>Simpan
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div style="padding: 20px; border-top: 1px solid #dee2e6;">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Sebelumnya
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            if($start_page > 1) $start_page = max(1, $end_page - 4);
                            for($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Selanjutnya <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h4>Tidak ada order</h4>
                    <p>Tidak ditemukan order dengan filter yang dipilih.</p>
                    <a href="order.php" class="btn btn-primary mt-2"><i class="fas fa-redo me-2"></i>Tampilkan Semua Order</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ============================================================
        // CSRF TOKEN
        // ============================================================
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        // ============================================================
        // SCROLL POSITION PRESERVATION
        // ============================================================
        window.addEventListener('load', function() {
            const savedScroll = sessionStorage.getItem('orderPageScroll');
            if (savedScroll !== null) {
                window.scrollTo(0, parseInt(savedScroll));
                sessionStorage.removeItem('orderPageScroll');
            }
        });
        
        // ============================================================
        // TOAST NOTIFICATION
        // ============================================================
        function showAdminToast(message, type = 'success') {
            const existing = document.getElementById('adminToast');
            if (existing) existing.remove();
            
            const colors = {
                success: { bg: '#10B981', icon: 'fa-check-circle' },
                error:   { bg: '#EF4444', icon: 'fa-exclamation-circle' },
                info:    { bg: '#3B82F6', icon: 'fa-info-circle' }
            };
            const style = colors[type] || colors.info;
            
            const toast = document.createElement('div');
            toast.id = 'adminToast';
            toast.style.cssText = `
                position: fixed; top: 20px; right: 20px;
                background: ${style.bg}; color: white;
                padding: 14px 22px; border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                z-index: 99999; font-weight: 600; font-size: 0.9rem;
                display: flex; align-items: center; gap: 10px;
                transform: translateX(120%);
                transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                max-width: 400px;
            `;
            toast.innerHTML = `<i class="fas ${style.icon}"></i><span>${message}</span>`;
            document.body.appendChild(toast);
            
            requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });
            
            setTimeout(() => {
                toast.style.transform = 'translateX(120%)';
                setTimeout(() => toast.remove(), 350);
            }, 3000);
        }
        
        // ============================================================
        // SIDEBAR TOGGLE
        // ============================================================
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
        
        // ============================================================
        // PRINT ORDER — buka halaman invoice di tab baru
        // ============================================================
        function printOrder(orderId) {
            if (!orderId) {
                alert('ID order tidak ditemukan');
                return;
            }
            window.open('print_order.php?id=' + orderId, '_blank');
        }
        
        // ============================================================
        // EXPORT EXCEL
        // ============================================================
        function exportToExcel() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            const status = document.querySelector('select[name="status"]').value;
            const paymentMethod = document.querySelector('select[name="payment_method"]').value;
            const paymentStatus = document.querySelector('select[name="payment_status"]').value;
            const search = document.querySelector('input[name="search"]').value;
            
            let exportUrl = 'export.php?export=excel';
            exportUrl += '&start_date=' + startDate;
            exportUrl += '&end_date=' + endDate;
            exportUrl += '&status=' + status;
            exportUrl += '&payment_method=' + paymentMethod;
            exportUrl += '&payment_status=' + paymentStatus;
            exportUrl += '&search=' + encodeURIComponent(search);
            
            window.location.href = exportUrl;
        }
        
        // ============================================================
        // FILTER FORM
        // ============================================================
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            const formInputs = filterForm.querySelectorAll('input, select');
            
            formInputs.forEach(input => {
                if (input.type !== 'submit' && input.name !== 'search' && input.type !== 'hidden') {
                    input.addEventListener('change', function() { filterForm.submit(); });
                }
            });
            
            let searchTimeout;
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => { filterForm.submit(); }, 500);
                });
            }
        }
        
        // ============================================================
        // AJAX FORM SUBMIT
        // ============================================================
        document.querySelectorAll('.ajax-action-form').forEach(function(form) {
            const modal = form.closest('.modal');
            
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const formData = new FormData(form);
                const isStatusModal = modal && modal.id.startsWith('statusModal');
                
                if (isStatusModal) {
                    formData.set('update_status', '1');
                    formData.delete('update_payment_status');
                } else {
                    formData.set('update_payment_status', '1');
                    formData.delete('update_status');
                }
                
                formData.set('csrf_token', CSRF_TOKEN);
                
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalHTML = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';
                submitBtn.disabled = true;
                
                try {
                    const response = await fetch('ajax_order_action.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    
                    const rawText = await response.text();
                    
                    let data;
                    try {
                        data = JSON.parse(rawText);
                    } catch (parseErr) {
                        console.error('Response BUKAN JSON:', rawText.substring(0, 300));
                        showAdminToast('Server error — cek Console (F12)', 'error');
                        submitBtn.innerHTML = originalHTML;
                        submitBtn.disabled = false;
                        return;
                    }
                    
                    if (data.success) {
                        const row = modal ? modal.closest('tr') : null;
                        if (row) {
                            if (isStatusModal && data.status) {
                                const badge = row.querySelector('td .order-status');
                                if (badge) {
                                    badge.className = 'order-status ' + data.status_class;
                                    badge.textContent = data.status_label;
                                }
                                if (data.status === 'completed') {
                                    const pBadge = row.querySelector('td .payment-status-badge');
                                    if (pBadge) {
                                        pBadge.className = 'payment-status-badge payment-paid';
                                        pBadge.textContent = 'Lunas';
                                    }
                                }
                            }
                            if (!isStatusModal && data.payment_status) {
                                const badge = row.querySelector('td .payment-status-badge');
                                if (badge) {
                                    badge.className = 'payment-status-badge ' + data.payment_class;
                                    badge.textContent = data.payment_label;
                                }
                            }
                        }
                        
                        if (modal) {
                            const bsModal = bootstrap.Modal.getInstance(modal);
                            if (bsModal) bsModal.hide();
                        }
                        
                        showAdminToast(data.message || 'Berhasil disimpan', 'success');
                    } else {
                        showAdminToast(data.message || 'Gagal menyimpan', 'error');
                    }
                } catch (err) {
                    console.error('Fetch error:', err);
                    showAdminToast('Koneksi gagal: ' + err.message, 'error');
                } finally {
                    submitBtn.innerHTML = originalHTML;
                    submitBtn.disabled = false;
                }
            });
        });
        
        // ============================================================
        // AUTO-REFRESH dengan SCROLL PRESERVATION (15s)
        // ============================================================
        setInterval(function() {
            const activeEl = document.activeElement;
            if (activeEl && ['INPUT','SELECT','TEXTAREA'].includes(activeEl.tagName)) return;
            if (document.querySelector('.modal.show')) return;
            if (document.querySelector('.dropdown-menu.show')) return;
            
            sessionStorage.setItem('orderPageScroll', window.scrollY);
            location.reload();
        }, 15000);
    </script>
</body>
</html>