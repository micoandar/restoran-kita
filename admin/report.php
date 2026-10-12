<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

require_once '../config/database.php';
require_once '../config/security.php';

if (!isset($_SESSION['admin'])) { 
    header("Location: login.php"); 
    exit(); 
}

// ============================================================
// LAPORAN HARIAN
// ============================================================
$report = db_query($conn, "
    SELECT 
        DATE(created_at) as tgl, 
        COUNT(*) as jml_order,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as order_selesai,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as order_batal,
        SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END) as pendapatan_selesai,
        SUM(CASE WHEN status = 'cancelled' THEN total ELSE 0 END) as pendapatan_batal,
        SUM(total) as pendapatan_total
    FROM orders 
    GROUP BY DATE(created_at) 
    ORDER BY tgl DESC
");

// ============================================================
// STATISTIK KESELURUHAN
// ============================================================
$stats = db_row($conn, "
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END) as revenue_completed,
        SUM(CASE WHEN status = 'cancelled' THEN total ELSE 0 END) as revenue_cancelled,
        SUM(total) as total_revenue,
        AVG(CASE WHEN status = 'completed' THEN total ELSE NULL END) as avg_order_value
    FROM orders
");

if (!$stats) {
    $stats = [
        'total_orders' => 0, 'completed_orders' => 0, 'cancelled_orders' => 0,
        'revenue_completed' => 0, 'revenue_cancelled' => 0, 
        'total_revenue' => 0, 'avg_order_value' => 0
    ];
}

// Hitung persentase
$completion_rate = $stats['total_orders'] > 0 ? ($stats['completed_orders'] / $stats['total_orders']) * 100 : 0;
$cancellation_rate = $stats['total_orders'] > 0 ? ($stats['cancelled_orders'] / $stats['total_orders']) * 100 : 0;

// Validasi
$validation_total = $stats['completed_orders'] + $stats['cancelled_orders'];
$validation_message = '';
if ($validation_total != $stats['total_orders']) {
    $unclassified = $stats['total_orders'] - $validation_total;
    $validation_message = "⚠️ Catatan: Ada $unclassified order dengan status tidak terklasifikasi";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pendapatan - Admin Restoran Kita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a0ca3;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #ffc107;
            --info: #7209b7;
            --dark: #14213d;
            --light: #f8f9fa;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .report-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            box-shadow: var(--box-shadow);
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .stat-card.success { border-left-color: var(--success); }
        .stat-card.danger  { border-left-color: var(--danger); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.info    { border-left-color: var(--info); }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .stat-card.success .stat-number { color: var(--success); }
        .stat-card.danger .stat-number  { color: var(--danger); }
        .stat-card.warning .stat-number { color: var(--warning); }
        .stat-card.info .stat-number    { color: var(--info); }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .stat-detail {
            font-size: 0.85rem;
            color: #8d99ae;
            margin-top: 5px;
        }

        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-top: 25px;
        }

        .table-header {
            padding: 20px;
            background: var(--light);
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ============================================================
           TABLE REPORT — Responsive dengan scroll horizontal
           ============================================================ */

        .table-responsive {
            overflow-x: auto !important;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
        }

        /* Tabel utama — paksa min-width supaya kolom tidak terpotong */
        #reportTable {
            min-width: 1000px;
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        /* Header tabel */
        #reportTable thead th {
            background: #14213d !important;
            color: #ffffff !important;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            white-space: nowrap;
            vertical-align: middle;
            border: none !important;
            text-align: left;
        }

        /* Body tabel */
        #reportTable tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            white-space: nowrap;
            border-bottom: 1px solid #e9ecef;
            color: #2b2d42;
            background-color: #ffffff;
        }

        /* Kolom pertama — tanggal: boleh wrap karena ada hari di bawahnya */
        #reportTable tbody td:first-child,
        #reportTable thead th:first-child {
            min-width: 160px;
            white-space: normal;
        }

        /* Baris hover */
        #reportTable tbody tr:hover td {
            background-color: rgba(67, 97, 238, 0.05);
        }

        /* Footer total */
        #reportTable tfoot td {
            padding: 14px 16px;
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(58, 12, 163, 0.05)) !important;
            font-weight: 700;
            white-space: nowrap;
            border-top: 2px solid #14213d;
            color: #14213d;
        }

        /* Badge di dalam tabel */
        #reportTable .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        /* Rupiah */
        #reportTable .revenue-positive { color: #10b981; font-weight: 700; }
        #reportTable .revenue-negative { color: #ef4444; font-weight: 700; }

        /* Scrollbar custom (Webkit) */
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #c1c7d0;
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #a8b0bd;
        }

        /* Badge status umum */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: rgba(76, 201, 240, 0.15);
            color: #4cc9f0;
            border: 1px solid rgba(76, 201, 240, 0.3);
        }

        .badge-danger {
            background: rgba(247, 37, 133, 0.15);
            color: #f72585;
            border: 1px solid rgba(247, 37, 133, 0.3);
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.15);
            color: #b58900;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .badge-info {
            background: rgba(114, 9, 183, 0.15);
            color: #7209b7;
            border: 1px solid rgba(114, 9, 183, 0.3);
        }

        .revenue-positive { color: var(--success); font-weight: 700; }
        .revenue-negative { color: var(--danger); font-weight: 700; }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-print {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.3);
            color: white;
        }

        .btn-export {
            background: var(--light);
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .btn-export:hover {
            background: rgba(67, 97, 238, 0.1);
            transform: translateY(-2px);
        }

        .summary-row {
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(58, 12, 163, 0.05));
            font-weight: 700;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #dee2e6;
        }

        .validation-alert {
            background: rgba(255, 193, 7, 0.15);
            border-left: 4px solid var(--warning);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .stat-card { padding: 15px; }
            .stat-number { font-size: 1.5rem; }
            .table-header { flex-direction: column; gap: 15px; align-items: stretch; }
            .action-buttons { flex-direction: column; }
            .btn-print, .btn-export { width: 100%; justify-content: center; }

            #reportTable {
                min-width: 900px;
                font-size: 0.85rem;
            }
            
            #reportTable thead th,
            #reportTable tbody td,
            #reportTable tfoot td {
                padding: 10px 12px;
            }
            
            #reportTable tbody td:first-child,
            #reportTable thead th:first-child {
                min-width: 120px;
            }
        }

        /* ============================================================
           PRINT
           ============================================================ */
        @media print {
    /* Reset halaman */
    @page {
        size: A4 landscape;
        margin: 10mm;
    }
    
    body {
        margin: 0;
        padding: 0;
        background: white !important;
    }
    
    /* Hide sidebar & elemen non-cetak */
    .sidebar,
    .admin-header,
    .action-buttons,
    .btn-print,
    .btn-export {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
    }
    
    .content-wrapper {
        padding: 0 !important;
    }
    
    /* Header laporan */
    .report-header {
        box-shadow: none !important;
        border: none !important;
        background: #14213d !important;
        color: white !important;
        padding: 15px 20px !important;
        margin-bottom: 15px !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        page-break-inside: avoid;
    }
    
    /* Stat cards */
    .stat-card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
        page-break-inside: avoid;
        margin-bottom: 10px;
    }
    
    /* Table container */
    .table-container {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
        page-break-inside: auto;
    }
    
    .table-header {
        background: #f8f9fa !important;
        border-bottom: 1px solid #dee2e6;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* PENTING: table di print jangan pakai scroll */
    .table-responsive {
        overflow: visible !important;
        width: 100% !important;
    }
    
    /* ============================================================
       TABLE FIX — kunci supaya kolom tidak terpotong di print
       ============================================================ */
    #reportTable {
        min-width: 0 !important;
        width: 100% !important;
        table-layout: fixed !important;
        font-size: 8pt;
        border-collapse: collapse !important;
    }
    
    /* Header tabel di print */
    #reportTable thead th {
        background: #14213d !important;
        color: #ffffff !important;
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        padding: 6px 4px !important;
        white-space: normal !important;
        vertical-align: middle;
        border: 1px solid #0a1520 !important;
        text-align: center;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    /* ============================================================
       LEBAR KOLOM PRESISI UNTUK PRINT (total 100%)
       Tanggal 12% | Total Order 10% | Order Selesai 11%
       Order Batal 11% | Pendapatan Selesai 18%
       Potensi Kerugian 18% | Total Transaksi 20%
       ============================================================ */
    #reportTable thead th:nth-child(1),
    #reportTable tbody td:nth-child(1),
    #reportTable tfoot td:nth-child(1) { width: 12%; min-width: 0 !important; }
    
    #reportTable thead th:nth-child(2),
    #reportTable tbody td:nth-child(2),
    #reportTable tfoot td:nth-child(2) { width: 10%; min-width: 0 !important; }
    
    #reportTable thead th:nth-child(3),
    #reportTable tbody td:nth-child(3),
    #reportTable tfoot td:nth-child(3) { width: 11%; min-width: 0 !important; }
    
    #reportTable thead th:nth-child(4),
    #reportTable tbody td:nth-child(4),
    #reportTable tfoot td:nth-child(4) { width: 11%; min-width: 0 !important; }
    
    #reportTable thead th:nth-child(5),
    #reportTable tbody td:nth-child(5),
    #reportTable tfoot td:nth-child(5) { width: 18%; min-width: 0 !important; }
    
    #reportTable thead th:nth-child(6),
    #reportTable tbody td:nth-child(6),
    #reportTable tfoot td:nth-child(6) { width: 18%; min-width: 0 !important; }
    
    #reportTable thead th:nth-child(7),
    #reportTable tbody td:nth-child(7),
    #reportTable tfoot td:nth-child(7) { width: 20%; min-width: 0 !important; }
    
    /* Body tabel di print */
    #reportTable tbody td {
        padding: 5px 4px !important;
        font-size: 8pt;
        white-space: normal !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
        border: 1px solid #dee2e6 !important;
        color: #000 !important;
        background: white !important;
        text-align: right;
        vertical-align: middle;
    }
    
    /* Kolom pertama rata kiri */
    #reportTable tbody td:first-child {
        text-align: left;
    }
    
    /* Kolom badge — biar center */
    #reportTable tbody td:nth-child(3),
    #reportTable tbody td:nth-child(4) {
        text-align: center;
    }
    
    /* Badge di print — perkecil padding */
    #reportTable .status-badge {
        padding: 2px 6px !important;
        font-size: 7pt !important;
        background: #f0f0f0 !important;
        color: #000 !important;
        border: 1px solid #999 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    #reportTable .badge-success {
        background: #d4edda !important;
        color: #155724 !important;
        border: 1px solid #155724 !important;
    }
    
    #reportTable .badge-danger {
        background: #f8d7da !important;
        color: #721c24 !important;
        border: 1px solid #721c24 !important;
    }
    
    /* Rupiah di print */
    #reportTable .revenue-positive {
        color: #155724 !important;
        font-weight: 700;
    }
    
    #reportTable .revenue-negative {
        color: #721c24 !important;
        font-weight: 700;
    }
    
    /* Footer */
    #reportTable tfoot td {
        padding: 6px 4px !important;
        font-weight: 700;
        font-size: 8pt;
        background: #e9ecef !important;
        color: #14213d !important;
        border: 1px solid #14213d !important;
        white-space: normal !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        text-align: right;
    }
    
    #reportTable tfoot td:first-child {
        text-align: left;
    }
    
    /* Prevent baris terpotong di antara halaman */
    #reportTable tr {
        page-break-inside: avoid;
    }
    
    /* Hilangkan small text */
    #reportTable small,
    #reportTable .text-muted {
        color: #666 !important;
        font-size: 7pt !important;
    }
    
    /* Ringkasan status & validation */
    .badge-warning {
        background: #fff3cd !important;
        color: #856404 !important;
        border: 1px solid #856404 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .badge-info {
        background: #cfe2ff !important;
        color: #084298 !important;
        border: 1px solid #084298 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
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
                <li><a href="order.php"><i class="fas fa-shopping-cart"></i><span>Data Order</span></a></li>
                <li><a href="customer.php"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
                <li><a href="report.php" class="active"><i class="fas fa-chart-bar"></i><span>Laporan</span></a></li>
                <li><a href="chatbot.php"><i class="fas fa-robot"></i><span>Chatbot FAQ</span></a></li>
                <li><a href="../index.php" target="_blank"><i class="fas fa-eye"></i><span>Lihat Website Utama</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
            </ul>
        </div>
    </div>
    
    <div class="main-content">
        <header class="admin-header">
            <button class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Laporan Pendapatan</h4>
            <div class="header-right">
                <div class="admin-avatar"><?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?></div>
                <div class="fw-bold"><?php echo e($_SESSION['admin']); ?></div>
            </div>
        </header>
        
        <div class="content-wrapper">
            <div class="report-header">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <h2><i class="fas fa-file-invoice-dollar me-2"></i>Laporan Pendapatan</h2>
                        <p class="mb-0">Analisis pendapatan berdasarkan status order</p>
                        <?php if($validation_message): ?>
                        <div class="mt-3">
                            <span class="badge-warning status-badge">
                                <i class="fas fa-exclamation-triangle me-1"></i><?php echo e($validation_message); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistik -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card success">
                        <div class="stat-number">Rp <?php echo number_format($stats['revenue_completed'] ?? 0); ?></div>
                        <div class="stat-label">Pendapatan Selesai</div>
                        <div class="stat-detail">
                            <i class="fas fa-check-circle me-1"></i>
                            <?php echo number_format($stats['completed_orders'] ?? 0); ?> order selesai
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card danger">
                        <div class="stat-number">Rp <?php echo number_format($stats['revenue_cancelled'] ?? 0); ?></div>
                        <div class="stat-label">Potensi Kerugian (Batal)</div>
                        <div class="stat-detail">
                            <i class="fas fa-times-circle me-1"></i>
                            <?php echo number_format($stats['cancelled_orders'] ?? 0); ?> order dibatalkan
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card info">
                        <div class="stat-number">Rp <?php echo number_format($stats['total_revenue'] ?? 0); ?></div>
                        <div class="stat-label">Total Transaksi</div>
                        <div class="stat-detail">
                            <i class="fas fa-exchange-alt me-1"></i>
                            <?php echo number_format($stats['total_orders'] ?? 0); ?> semua order
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning">
                        <div class="stat-number">Rp <?php echo number_format($stats['avg_order_value'] ?? 0, 0); ?></div>
                        <div class="stat-label">Rata-rata/Order</div>
                        <div class="stat-detail">
                            <i class="fas fa-percentage me-1"></i>
                            <?php echo number_format($completion_rate, 1); ?>% selesai
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Summary -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="stat-card">
                        <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Ringkasan Status Order</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="badge-success status-badge me-3">
                                        <i class="fas fa-check-circle me-1"></i>SELESAI
                                    </span>
                                    <div>
                                        <div class="fw-bold"><?php echo number_format($stats['completed_orders'] ?? 0); ?> Order</div>
                                        <small class="text-muted"><?php echo number_format($completion_rate, 1); ?>% dari total</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="badge-danger status-badge me-3">
                                        <i class="fas fa-times-circle me-1"></i>BATAL
                                    </span>
                                    <div>
                                        <div class="fw-bold"><?php echo number_format($stats['cancelled_orders'] ?? 0); ?> Order</div>
                                        <small class="text-muted"><?php echo number_format($cancellation_rate, 1); ?>% dari total</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="badge-info status-badge me-3">
                                        <i class="fas fa-question-circle me-1"></i>LAINNYA
                                    </span>
                                    <div>
                                        <div class="fw-bold"><?php echo number_format(($stats['total_orders'] ?? 0) - $validation_total); ?> Order</div>
                                        <small class="text-muted">Status belum terklasifikasi</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print me-2"></i>Cetak Laporan
                </button>
                <button onclick="exportToExcel()" class="btn-export">
                    <i class="fas fa-file-excel me-2"></i>Export Excel
                </button>
                <a href="order.php" class="btn btn-outline-primary">
                    <i class="fas fa-shopping-cart me-2"></i>Lihat Detail Order
                </a>
            </div>

            <!-- Table -->
            <div class="table-container">
                <div class="table-header">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Rincian Harian</h5>
                    <div class="text-muted">
                        Menampilkan <?php echo $report ? mysqli_num_rows($report) : 0; ?> hari
                    </div>
                </div>
                <div class="table-responsive">
                    <?php if($report && mysqli_num_rows($report) > 0): ?>
                    <table class="table" id="reportTable">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Total Order</th>
                                <th>Order Selesai</th>
                                <th>Order Batal</th>
                                <th>Pendapatan Selesai</th>
                                <th>Potensi Kerugian</th>
                                <th>Total Transaksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total = 0;
                            $grand_completed = 0;
                            $grand_cancelled = 0;
                            $grand_revenue_completed = 0;
                            $grand_revenue_cancelled = 0;
                            $grand_total_transactions = 0;
                            
                            mysqli_data_seek($report, 0);
                            while($r = mysqli_fetch_assoc($report)): 
                                $grand_total += $r['jml_order'];
                                $grand_completed += $r['order_selesai'];
                                $grand_cancelled += $r['order_batal'];
                                $grand_revenue_completed += $r['pendapatan_selesai'];
                                $grand_revenue_cancelled += $r['pendapatan_batal'];
                                $grand_total_transactions += $r['pendapatan_total'];
                                
                                $daily_completion_rate = $r['jml_order'] > 0 ? ($r['order_selesai'] / $r['jml_order']) * 100 : 0;
                                $daily_cancellation_rate = $r['jml_order'] > 0 ? ($r['order_batal'] / $r['jml_order']) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('d M Y', strtotime($r['tgl'])); ?></strong><br>
                                    <small class="text-muted"><?php echo date('l', strtotime($r['tgl'])); ?></small>
                                </td>
                                <td>
                                    <?php echo number_format($r['jml_order']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo number_format($daily_completion_rate, 1); ?>% selesai</small>
                                </td>
                                <td>
                                    <span class="status-badge badge-success">
                                        <i class="fas fa-check-circle me-1"></i>
                                        <?php echo number_format($r['order_selesai']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge badge-danger">
                                        <i class="fas fa-times-circle me-1"></i>
                                        <?php echo number_format($r['order_batal']); ?>
                                    </span>
                                    <?php if($r['order_batal'] > 0): ?>
                                    <br><small class="text-danger"><?php echo number_format($daily_cancellation_rate, 1); ?>%</small>
                                    <?php endif; ?>
                                </td>
                                <td class="revenue-positive">Rp <?php echo number_format($r['pendapatan_selesai']); ?></td>
                                <td class="revenue-negative">
                                    Rp <?php echo number_format($r['pendapatan_batal']); ?>
                                    <?php if($r['pendapatan_batal'] > 0): ?>
                                    <br><small class="text-danger">
                                        <?php echo $r['pendapatan_total'] > 0 ? number_format(($r['pendapatan_batal'] / $r['pendapatan_total']) * 100, 1) : 0; ?>%
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td><strong>Rp <?php echo number_format($r['pendapatan_total']); ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr class="summary-row">
                                <td><strong>GRAND TOTAL</strong></td>
                                <td><?php echo number_format($grand_total); ?></td>
                                <td><?php echo number_format($grand_completed); ?></td>
                                <td><?php echo number_format($grand_cancelled); ?></td>
                                <td class="revenue-positive">Rp <?php echo number_format($grand_revenue_completed); ?></td>
                                <td class="revenue-negative">Rp <?php echo number_format($grand_revenue_cancelled); ?></td>
                                <td><strong>Rp <?php echo number_format($grand_total_transactions); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <h5>Tidak ada data laporan</h5>
                        <p>Belum ada transaksi yang tercatat</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        function exportToExcel() {
            const table = document.getElementById('reportTable');
            if (!table) {
                alert('Tidak ada data untuk di-export!');
                return;
            }
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const cols = rows[i].querySelectorAll('td, th');
                const row = [];
                for (let j = 0; j < cols.length; j++) {
                    const text = cols[j].innerText.replace(/\n/g, ' ').trim();
                    row.push('"' + text.replace(/"/g, '""') + '"');
                }
                csv.push(row.join(','));
            }
            
            const csvString = csv.join('\n');
            const blob = new Blob(["\uFEFF" + csvString], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            
            a.href = url;
            a.download = 'Laporan_Pendapatan_<?php echo date('Y-m-d'); ?>.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>