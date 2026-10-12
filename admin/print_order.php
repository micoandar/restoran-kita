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

// Validasi ID
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    die("ID order tidak ditemukan!");
}

// ============================================================
// CEK KOLOM
// ============================================================
$cols = [];
$r = mysqli_query($conn, "SHOW COLUMNS FROM orders");
while ($c = mysqli_fetch_assoc($r)) $cols[] = $c['Field'];

$has_session = in_array('session_id', $cols);
$has_telepon = in_array('telepon', $cols);

// ============================================================
// AMBIL DATA ORDER
// ============================================================
if ($has_session) {
    // Cari order_id → ambil session_id-nya → tarik semua item sekelompok
    $first = db_row($conn, "SELECT session_id, nama_pelanggan FROM orders WHERE id = ? LIMIT 1", [$order_id]);
    
    if (!$first) {
        die("Order tidak ditemukan!");
    }
    
    $session_id = $first['session_id'] ?? '';
    
    if (!empty($session_id)) {
        $items = db_query($conn, 
            "SELECT * FROM orders WHERE session_id = ? ORDER BY id",
            [$session_id]
        );
    } else {
        $items = db_query($conn, "SELECT * FROM orders WHERE id = ?", [$order_id]);
    }
} else {
    $items = db_query($conn, "SELECT * FROM orders WHERE id = ?", [$order_id]);
}

if (!$items || mysqli_num_rows($items) == 0) {
    die("Order tidak ditemukan!");
}

// Kumpulkan data dari semua item
$all_items = [];
$total_all = 0;
$first_item = null;
$catatan_list = [];

while ($row = mysqli_fetch_assoc($items)) {
    if ($first_item === null) $first_item = $row;
    $all_items[] = $row;
    $total_all += (int)$row['total'];
    
    if (!empty($row['catatan']) && trim($row['catatan']) !== '') {
        $catatan_list[] = trim($row['catatan']);
    }
}

$unique_catatan = implode(' | ', array_unique($catatan_list));

$nama_pelanggan    = $first_item['nama_pelanggan'];
$tanggal_order     = $first_item['created_at'];
$status_order      = $first_item['status'] ?? 'pending';
$metode_pembayaran = $first_item['metode_pembayaran'] ?? 'cash';
$status_pembayaran = $first_item['status_pembayaran'] ?? 'pending';
$telepon           = $first_item['telepon'] ?? '';

$item_count = count($all_items);

// Auto-print kalau ada parameter
$auto_print = isset($_GET['auto_print']) && $_GET['auto_print'] == '1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Order #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }

        .invoice-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            padding: 30px;
            max-width: 800px;
        }

        .invoice-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .restaurant-name {
            color: #007bff;
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .restaurant-tagline {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .invoice-title {
            text-align: right;
            color: #495057;
        }

        .invoice-title h2 {
            font-weight: 700;
            color: #007bff;
            margin-bottom: 5px;
        }

        .invoice-title .invoice-number {
            color: #6c757d;
            font-size: 1rem;
        }

        .customer-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            width: 130px;
            display: inline-block;
        }

        .info-value { color: #212529; }

        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .table-items thead {
            background-color: #007bff;
            color: white;
        }

        .table-items th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }

        .table-items tbody tr {
            border-bottom: 1px solid #dee2e6;
        }

        .table-items tbody tr:last-child {
            border-bottom: none;
        }

        .table-items td {
            padding: 15px;
            vertical-align: top;
        }

        .table-items tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }

        .total-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }

        .total-label {
            font-weight: 600;
            color: #495057;
        }

        .total-value {
            font-weight: 700;
            color: #007bff;
            font-size: 1.1rem;
        }

        .grand-total {
            border-top: 2px solid #dee2e6;
            padding-top: 15px;
            margin-top: 15px;
        }

        .grand-total .total-label { font-size: 1.2rem; }
        .grand-total .total-value { font-size: 1.4rem; color: #28a745; }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-pending     { background: rgba(255, 193, 7, 0.15);  color: #b8860b; }
        .status-processing  { background: rgba(0, 123, 255, 0.1);   color: #007bff; }
        .status-completed   { background: rgba(40, 167, 69, 0.1);   color: #28a745; }
        .status-cancelled   { background: rgba(220, 53, 69, 0.1);   color: #dc3545; }

        .payment-badge-paid    { background: rgba(40, 167, 69, 0.15); color: #28a745; }
        .payment-badge-pending { background: rgba(255, 193, 7, 0.15); color: #b8860b; }

        .notes-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .notes-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
        }

        .notes-content {
            color: #212529;
            line-height: 1.6;
        }

        .print-btn-container {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .btn-print {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-print:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
            color: white;
        }

        .btn-back {
            background: #6c757d;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 10px;
        }

        .btn-back:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }

        .header-actions {
            margin-bottom: 20px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ============================================================
           PRINT STYLES
           ============================================================ */
        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }
            
            body {
                margin: 0;
                padding: 0;
                background: white !important;
                font-size: 11pt;
            }
            
            .no-print { display: none !important; }
            
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .invoice-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            
            .invoice-header {
                border-bottom: 2px solid #000;
                padding-bottom: 15px;
                margin-bottom: 25px;
            }
            
            .restaurant-name { color: #000 !important; }
            .invoice-title h2 { color: #000 !important; }
            
            .table-items thead {
                background-color: #000 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .table-items th,
            .table-items td {
                border: 1px solid #000;
                padding: 10px;
            }
            
            .status-badge {
                border: 1px solid #000;
                background: white !important;
                color: #000 !important;
            }
            
            .total-section,
            .customer-info,
            .notes-section {
                background: white !important;
                border: 1px solid #ccc;
            }
            
            .grand-total .total-value {
                color: #000 !important;
                font-size: 1.5rem;
            }
            
            .footer {
                border-top: 1px solid #000;
                margin-top: 30px;
            }
            
            .table-items tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header-actions no-print">
    <a href="order.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Kembali ke Order
    </a>
</div>
    
    <div class="container">
        <div class="invoice-container">
            <!-- Header -->
            <div class="invoice-header">
                <div class="row">
                    <div class="col-6">
                        <div class="restaurant-name">Restoran Kita</div>
                        <div class="restaurant-tagline">
                            <i class="fas fa-map-marker-alt me-1"></i> Jl. Contoh No. 123, Kota Kita
                        </div>
                        <div class="restaurant-tagline">
                            <i class="fas fa-phone me-1"></i> (021) 1234-5678
                        </div>
                        <div class="restaurant-tagline">
                            <i class="fas fa-envelope me-1"></i> info@restorankita.com
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="invoice-title">
                            <h2>INVOICE</h2>
                            <div class="invoice-number">
                                #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?>
                            </div>
                            <div class="mt-2">
                                <span class="status-badge status-<?php echo e($status_order); ?>">
                                    <?php echo ucfirst(e($status_order)); ?>
                                </span>
                                <span class="status-badge payment-badge-<?php echo e($status_pembayaran); ?> ms-1">
                                    <?php echo $status_pembayaran === 'paid' ? 'LUNAS' : 'BELUM BAYAR'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Info -->
            <div class="customer-info">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="d-flex mb-2">
                            <span class="info-label">Pelanggan:</span>
                            <span class="info-value"><?php echo e($nama_pelanggan); ?></span>
                        </div>
                        <?php if($has_telepon && !empty($telepon)): ?>
                        <div class="d-flex mb-2">
                            <span class="info-label">Telepon:</span>
                            <span class="info-value"><?php echo e($telepon); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex mb-2">
                            <span class="info-label">Metode Bayar:</span>
                            <span class="info-value"><?php echo strtoupper(e($metode_pembayaran)); ?></span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="d-flex mb-2">
                            <span class="info-label">Tanggal Order:</span>
                            <span class="info-value"><?php echo date('d F Y', strtotime($tanggal_order)); ?></span>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="info-label">Waktu Order:</span>
                            <span class="info-value"><?php echo date('H:i', strtotime($tanggal_order)); ?> WIB</span>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="info-label">Invoice Date:</span>
                            <span class="info-value"><?php echo date('d/m/Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Items Table -->
            <div class="table-responsive">
                <table class="table-items">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Menu</th>
                            <th>Jumlah</th>
                            <th>Harga Satuan</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($all_items as $item):
                            $subtotal = (int)$item['jumlah'] * (int)$item['harga'];
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo e($item['menu']); ?></td>
                            <td><?php echo (int)$item['jumlah']; ?> porsi</td>
                            <td>Rp <?php echo number_format($item['harga']); ?></td>
                            <td>Rp <?php echo number_format($subtotal); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Notes -->
            <?php if(!empty($unique_catatan)): ?>
            <div class="notes-section">
                <div class="notes-title">
                    <i class="fas fa-sticky-note me-2"></i>Catatan:
                </div>
                <div class="notes-content">
                    <?php echo nl2br(e($unique_catatan)); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Total -->
            <div class="total-section">
                <div class="total-row">
                    <div class="total-label">Jumlah Item:</div>
                    <div class="total-value"><?php echo $item_count; ?> item</div>
                </div>
                <div class="total-row grand-total">
                    <div class="total-label">TOTAL PEMBAYARAN:</div>
                    <div class="total-value">Rp <?php echo number_format($total_all); ?></div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Terima Kasih Atas Pesanan Anda!</strong></p>
                        <p>Silakan menunggu pesanan Anda disiapkan.</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Informasi Pembayaran:</strong></p>
                        <p>Pembayaran dilakukan secara tunai di kasir.</p>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="mb-0">Invoice ini dicetak pada: <?php echo date('d F Y H:i:s'); ?></p>
                    <p class="mb-0">Staff: <?php echo e($_SESSION['admin']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Print Button -->
        <div class="print-btn-container no-print">
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Invoice
            </button>
        </div>
    </div>
    
    <script>
        // Auto print jika ?auto_print=1
        <?php if($auto_print): ?>
        window.addEventListener('load', function() {
            setTimeout(() => window.print(), 300);
        });
        <?php endif; ?>
    </script>
</body>
</html>