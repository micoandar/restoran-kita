<?php
include 'config/database.php';
require_once 'config/security.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $nama = trim($_POST['nama'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    
    // Validasi dasar
    if (empty($nama)) {
        echo "<script>alert('Nama wajib diisi.'); window.history.back();</script>";
        exit();
    }
    
    // Metode pembayaran dipaksa CASH
    $metode_pembayaran = 'cash';
    
    // Cek apakah nama pelanggan sudah ada dan masih dalam proses
    $existing = db_row($conn, 
        "SELECT id FROM orders 
         WHERE nama_pelanggan = ? 
         AND status IN ('pending', 'processing')
         AND DATE(created_at) = CURDATE()
         LIMIT 1",
        [$nama]
    );
    
    if ($existing) {
        echo "<script>
            alert('Maaf, nama \"$nama\" sudah digunakan untuk pesanan yang belum selesai hari ini. Silakan gunakan nama lain atau tunggu pesanan sebelumnya selesai.');
            window.history.back();
        </script>";
        exit();
    }
    
    // Ambil waktu saat ini
    $waktu_pesan = date('Y-m-d H:i:s');
    $waktu_cetak = date('d/m/Y H:i:s');
    $session_id = uniqid($nama . '_', true);
    
    if (isset($_POST['menu']) && is_array($_POST['menu'])) {
        $menus = $_POST['menu'];
        $hargas = $_POST['harga'];
        $jumlahs = $_POST['jumlah'];
        
        $total_all = 0;
        $order_ids = [];
        $first_order_id = 0;
        
        // Cek kolom yang tersedia
        $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM orders");
        $columns = [];
        while ($row = mysqli_fetch_assoc($check_columns)) {
            $columns[] = $row['Field'];
        }
        
        $has_session          = in_array('session_id', $columns);
        $has_metode           = in_array('metode_pembayaran', $columns);
        $has_status_pembayaran= in_array('status_pembayaran', $columns);
        $has_waktu_pesan      = in_array('waktu_pesan', $columns);
        $has_catatan          = in_array('catatan', $columns);
        
        // Loop setiap item
        for ($i = 0; $i < count($menus); $i++) {
            $menu_item = trim($menus[$i]);
            $harga = (int)$hargas[$i];
            $jumlah = (int)$jumlahs[$i];
            $total = $jumlah * $harga;
            
            // Skip item kosong
            if (empty($menu_item) || $jumlah < 1) continue;
            
            $total_all += $total;
            
            // Bangun query INSERT dinamis
            $fields = ['nama_pelanggan', 'menu', 'harga', 'jumlah', 'total', 'status'];
            $params = [$nama, $menu_item, $harga, $jumlah, $total, 'pending'];
            $placeholders = ['?', '?', '?', '?', '?', '?'];
            
            if ($has_catatan) {
                $fields[] = 'catatan';
                $params[] = $catatan;
                $placeholders[] = '?';
            }
            if ($has_metode) {
                $fields[] = 'metode_pembayaran';
                $params[] = $metode_pembayaran;
                $placeholders[] = '?';
            }
            if ($has_status_pembayaran) {
                $fields[] = 'status_pembayaran';
                $params[] = 'pending';
                $placeholders[] = '?';
            }
            if ($has_waktu_pesan) {
                $fields[] = 'waktu_pesan';
                $params[] = $waktu_pesan;
                $placeholders[] = '?';
            }
            if ($has_session) {
                $fields[] = 'session_id';
                $params[] = $session_id;
                $placeholders[] = '?';
            }
            
            $sql = "INSERT INTO orders (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            $affected = db_execute($conn, $sql, $params);
            
            if ($affected > 0) {
                $new_order_id = db_insert_id($conn);
                $order_ids[] = $new_order_id;
                
                if ($first_order_id === 0) {
                    $first_order_id = $new_order_id;
                }
            } else {
                error_log("Gagal insert order untuk: $nama");
                echo "<div class='alert alert-danger'>Terjadi kesalahan saat menyimpan pesanan. Silakan coba lagi.</div>";
                echo "<a href='order.php' class='btn btn-primary'>Kembali ke Keranjang</a>";
                exit();
            }
        }
        
        // Simpan session
        $_SESSION['order_ids'] = $order_ids;
        $_SESSION['last_order_name'] = $nama;
        $_SESSION['metode_pembayaran'] = $metode_pembayaran;
        $_SESSION['order_session_id'] = $session_id;
        
        // HTML halaman sukses (TETAP SAMA)
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Pesanan Berhasil - Restoran Kita</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="assets/css/style.css">
            <style>
                :root {
                    --primary: #FF6B6B;
                    --primary-dark: #FF5252;
                    --primary-light: #FFE5E5;
                    --secondary: #4ECDC4;
                    --secondary-dark: #36B9B0;
                    --accent: #FF9F43;
                    --dark: #1A1F36;
                    --light-bg: #F8FAFC;
                    --gradient-primary: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
                    --gradient-secondary: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
                    --border-radius-xl: 24px;
                    --border-radius-lg: 20px;
                    --border-radius-md: 16px;
                    --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.08);
                }

                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background-color: var(--light-bg);
                    color: var(--dark);
                    min-height: 100vh;
                }

                .success-container {
                    max-width: 800px;
                    margin: 50px auto;
                    background: white;
                    border-radius: var(--border-radius-xl);
                    box-shadow: var(--shadow-medium);
                    overflow: hidden;
                    border-top: 5px solid var(--primary);
                }

                .success-header {
                    background: var(--gradient-primary);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                    position: relative;
                }

                .success-icon {
                    width: 80px;
                    height: 80px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    font-size: 2.5rem;
                }

                .success-header h1 {
                    font-weight: 700;
                    margin-bottom: 10px;
                }

                .success-header p {
                    opacity: 0.9;
                    font-size: 1.1rem;
                }

                .cash-info-section {
                    padding: 35px 30px;
                    text-align: center;
                    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                    margin: 20px;
                    border-radius: var(--border-radius-lg);
                    border: 2px solid #28a745;
                }

                .cash-info-section .icon-cash {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, #28a745, #20c997);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    color: white;
                    font-size: 2.2rem;
                    box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
                }

                .cash-info-section h3 {
                    color: #155724;
                    font-weight: 800;
                    margin-bottom: 12px;
                }

                .cash-info-section p {
                    color: #155724;
                    margin-bottom: 0;
                    font-size: 1.05rem;
                }

                .payment-amount-cash {
                    font-size: 2.2rem;
                    font-weight: 800;
                    color: #28a745;
                    margin: 20px 0;
                    letter-spacing: -1px;
                }

                .payment-instructions {
                    text-align: left;
                    background: white;
                    padding: 20px;
                    border-radius: 15px;
                    margin-top: 20px;
                    border-left: 4px solid #28a745;
                }

                .payment-instructions ol {
                    margin-bottom: 0;
                    padding-left: 20px;
                }

                .payment-instructions li {
                    margin-bottom: 8px;
                    color: var(--dark);
                }

                .order-details {
                    padding: 30px;
                }

                .detail-section {
                    margin-bottom: 30px;
                }

                .section-title {
                    font-weight: 700;
                    color: var(--dark);
                    margin-bottom: 20px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid var(--primary-light);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 15px;
                }

                .info-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 10px;
                    border-left: 3px solid var(--primary);
                }

                .info-label {
                    font-weight: 600;
                    color: #666;
                }

                .info-value {
                    font-weight: 700;
                    color: var(--dark);
                }

                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                    background: white;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                }

                .items-table th {
                    background: var(--primary-light);
                    padding: 15px;
                    text-align: left;
                    font-weight: 700;
                    color: var(--dark);
                    border-bottom: 2px solid var(--primary);
                }

                .items-table td {
                    padding: 15px;
                    border-bottom: 1px solid #eee;
                }

                .items-table tr:last-child td {
                    border-bottom: none;
                }

                .total-section {
                    background: linear-gradient(135deg, var(--dark) 0%, #2D3748 100%);
                    color: white;
                    padding: 25px;
                    border-radius: 15px;
                    margin-top: 30px;
                    text-align: right;
                }

                .grand-total {
                    font-size: 2.2rem;
                    font-weight: 800;
                    color: var(--secondary);
                }

                .btn-group {
                    display: flex;
                    gap: 15px;
                    margin-top: 30px;
                    flex-wrap: wrap;
                    justify-content: center;
                }

                .btn-print {
                    background: linear-gradient(135deg, #6c757d, #495057);
                    border: none;
                    color: white;
                    padding: 12px 25px;
                    border-radius: 50px;
                    font-weight: 600;
                    display: inline-flex;
                    align-items: center;
                    transition: all 0.3s ease;
                }

                .btn-print:hover {
                    background: linear-gradient(135deg, #5a6268, #343a40);
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
                }

                .btn-home {
                    background: var(--gradient-primary);
                    border: none;
                    color: white;
                    padding: 12px 25px;
                    border-radius: 50px;
                    font-weight: 600;
                    display: inline-flex;
                    align-items: center;
                    transition: all 0.3s ease;
                }

                .btn-home:hover {
                    background: linear-gradient(135deg, #ff5252, #ff6b6b);
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
                }

                .btn-order-again {
                    background: var(--gradient-secondary);
                    border: none;
                    color: white;
                    padding: 12px 25px;
                    border-radius: 50px;
                    font-weight: 600;
                    display: inline-flex;
                    align-items: center;
                    transition: all 0.3s ease;
                }

                .btn-order-again:hover {
                    background: linear-gradient(135deg, #3db9af, #398d7a);
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
                }

                .badge {
                    padding: 8px 15px;
                    border-radius: 50px;
                    font-weight: 600;
                    font-size: 0.9rem;
                }

                .badge-pending {
                    background: #ffc107;
                    color: #000;
                }

                .badge-processing {
                    background: #17a2b8;
                    color: white;
                }

                .badge-completed {
                    background: #28a745;
                    color: white;
                }

                .badge-cancelled {
                    background: #dc3545;
                    color: white;
                }

                .badge-cash {
                    background: #20c997;
                    color: white;
                }

                .badge-qris {
                    background: #007bff;
                    color: white;
                }

                .badge-payment-pending {
                    background: #ffc107;
                    color: #000;
                }

                .badge-payment-paid {
                    background: #28a745;
                    color: white;
                }

                .badge-secondary {
                    background: #6c757d;
                    color: white;
                }

                @media print {
                    .no-print, .btn-group, .cash-info-section {
                        display: none !important;
                    }
                    
                    body {
                        margin: 0;
                        padding: 0;
                        background: white !important;
                    }
                    
                    .success-container {
                        max-width: 100%;
                        margin: 0;
                        box-shadow: none;
                        border: none;
                    }
                    
                    .success-header {
                        background: #f8f9fa !important;
                        color: #000 !important;
                        padding: 20px !important;
                    }
                    
                    .success-icon {
                        background: #dee2e6 !important;
                        color: #000 !important;
                    }
                }

                @media (max-width: 768px) {
                    .success-container {
                        margin: 20px;
                        border-radius: 15px;
                    }
                    
                    .success-header {
                        padding: 30px 20px;
                    }
                    
                    .order-details {
                        padding: 20px;
                    }
                    
                    .cash-info-section {
                        margin: 15px;
                        padding: 25px 20px;
                    }
                    
                    .btn-group {
                        flex-direction: column;
                        align-items: center;
                    }
                    
                    .btn-print, .btn-home, .btn-order-again {
                        width: 100%;
                        max-width: 300px;
                        justify-content: center;
                    }
                }
            </style>
        </head>
        <body>
            <nav class="navbar navbar-expand-lg navbar-dark no-print" style="background: var(--dark);">
                <div class="container">
                    <a class="navbar-brand" href="index.php">
                        <i class="fas fa-utensils me-2"></i>Restoran Kita
                    </a>
                    <div class="navbar-text text-white">
                        <i class="fas fa-clock me-2"></i>
                        <span id="current-time"><?php echo date('H:i:s'); ?></span>
                    </div>
                </div>
            </nav>
            
            <div class="container">
                <div class="success-container">
                    <div class="success-header">
                        <div class="success-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h1>Pesanan Berhasil!</h1>
                        <p>Terima kasih <strong><?php echo htmlspecialchars($nama); ?></strong> telah memesan di Restoran Kita.</p>
                    </div>
                    
                    <div class="cash-info-section">
                        <div class="icon-cash">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3>Pembayaran Tunai</h3>
                        <p>Silakan lakukan pembayaran di kasir</p>
                        
                        <div class="payment-amount-cash">
                            Rp <?php echo number_format($total_all, 0, ',', '.'); ?>
                        </div>
                        
                        <div class="payment-instructions">
                            <h6><i class="fas fa-list-ol me-2"></i>Instruksi Pembayaran:</h6>
                            <ol>
                                <li>Menuju ke <strong>kasir</strong> restoran</li>
                                <li>Sebutkan <strong>nomor pesanan</strong> Anda:
                                    <strong>#<?php echo str_pad($first_order_id, 6, '0', STR_PAD_LEFT); ?></strong>
                                </li>
                                <li>Bayar sesuai jumlah total: <strong>Rp <?php echo number_format($total_all, 0, ',', '.'); ?></strong></li>
                                <li>Kasir akan mengubah status pembayaran menjadi <strong>LUNAS</strong></li>
                                <li>Pesanan Anda akan segera disiapkan</li>
                            </ol>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Penting:</strong> Simpan nomor pesanan Anda. Pesanan diproses setelah pembayaran diterima.
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-section">
                            <h4 class="section-title">
                                <i class="fas fa-receipt"></i> Informasi Pesanan
                            </h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Nomor Pesanan</span>
                                    <span class="info-value">#<?php echo str_pad($first_order_id, 6, '0', STR_PAD_LEFT); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Nama Pelanggan</span>
                                    <span class="info-value"><?php echo htmlspecialchars($nama); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Tanggal Pesan</span>
                                    <span class="info-value" id="order-time"><?php echo $waktu_cetak; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Metode Pembayaran</span>
                                    <span class="info-value">
                                        <span class="badge badge-cash">
                                            <i class="fas fa-money-bill-wave me-1"></i>TUNAI / CASH
                                        </span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status Pembayaran</span>
                                    <span class="info-value">
                                        <span class="badge badge-payment-pending" id="payment-status">
                                            Bayar di Kasir
                                        </span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Jumlah Pesanan</span>
                                    <span class="info-value"><?php echo count($menus); ?> item</span>
                                </div>
                                <?php if(!empty($catatan)): ?>
                                <div class="info-item" style="grid-column: span 2;">
                                    <span class="info-label">Catatan</span>
                                    <span class="info-value"><?php echo htmlspecialchars($catatan); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4 class="section-title">
                                <i class="fas fa-truck"></i> Status Pesanan
                            </h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Status Pesanan</span>
                                    <span class="info-value">
                                        <span class="badge badge-pending" id="order-status">Menunggu Konfirmasi</span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Terakhir Diperbarui</span>
                                    <span class="info-value" id="last-updated">Baru saja</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4 class="section-title">
                                <i class="fas fa-utensils"></i> Detail Pesanan
                            </h4>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Menu</th>
                                        <th>Harga</th>
                                        <th>Jumlah</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for($i = 0; $i < count($menus); $i++): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo htmlspecialchars($menus[$i]); ?></td>
                                        <td>Rp <?php echo number_format($hargas[$i]); ?></td>
                                        <td><?php echo $jumlahs[$i]; ?></td>
                                        <td>Rp <?php echo number_format($hargas[$i] * $jumlahs[$i]); ?></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                            
                            <div class="total-section">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1 text-white-50">Total Pembayaran</h5>
                                        <p class="mb-0 text-white-50"><small>Bayar di kasir</small></p>
                                    </div>
                                    <div class="grand-total">
                                        Rp <?php echo number_format($total_all); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="btn-group no-print">
                            <button onclick="window.print()" class="btn-print">
                                <i class="fas fa-print me-2"></i>Cetak Invoice
                            </button>
                            <a href="index.php" class="btn-home">
                                <i class="fas fa-home me-2"></i>Kembali ke Beranda
                            </a>
                            <a href="index.php" class="btn-order-again">
                                <i class="fas fa-plus me-2"></i>Pesan Lagi
                            </a>
                        </div>
                        
                        <div class="mt-4 text-center text-muted">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Status akan diperbarui otomatis setiap 5 detik
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
    const orderIds = <?php echo json_encode($order_ids); ?>;
    const sessionId = '<?php echo $session_id; ?>';
    
    let latestRequestId = 0;
    let currentOrderStatus = 'pending';
    
    function updateRealTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('id-ID', { 
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
        const dateString = now.toLocaleDateString('id-ID', {
            day: '2-digit', month: '2-digit', year: 'numeric'
        });
        const fullDateTime = `${dateString} ${timeString}`;
        
        const currentTimeElement = document.getElementById('current-time');
        if (currentTimeElement) currentTimeElement.textContent = timeString;
        
        const orderTimeElement = document.getElementById('order-time');
        if (orderTimeElement) orderTimeElement.textContent = fullDateTime;
    }
    
    function updateOrderStatus() {
        if (orderIds.length === 0) return;
        
        const firstOrderId = orderIds[0];
        const myRequestId = ++latestRequestId;
        
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `get_order_status.php?order_id=${firstOrderId}&session_id=${sessionId}&_t=${Date.now()}`, true);
        
        xhr.onload = function() {
            if (myRequestId !== latestRequestId) {
                console.log('[SKIP] Response lama diabaikan (req#' + myRequestId + ')');
                return;
            }
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    console.log('[STATUS] req#' + myRequestId + ' →', response);
                    
                    if (response.status) {
                        applyOrderStatus(response.status);
                    }
                    
                    if (response.payment_status) {
                        updatePaymentStatus(response.payment_status);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                }
            }
        };
        
        xhr.onerror = function() {
            console.error('Request failed for req#' + myRequestId);
        };
        
        xhr.send();
    }
    
    function applyOrderStatus(status) {
        const statusElement = document.getElementById('order-status');
        const lastUpdatedElement = document.getElementById('last-updated');
        if (!statusElement) return;
        
        if (status === currentOrderStatus) return;
        
        currentOrderStatus = status;
        
        statusElement.className = 'badge';
        
        if (status === 'pending') {
            statusElement.textContent = 'Menunggu Konfirmasi';
            statusElement.classList.add('badge-pending');
            removeCancelledState();
        } else if (status === 'processing') {
            statusElement.textContent = 'Sedang Diproses';
            statusElement.classList.add('badge-processing');
            removeCancelledState();
        } else if (status === 'completed') {
            statusElement.textContent = 'Selesai';
            statusElement.classList.add('badge-completed');
            removeCancelledState();
            showCompletionNotification();
        } else if (status === 'cancelled') {
            statusElement.textContent = 'Dibatalkan';
            statusElement.classList.add('badge-cancelled');
            showCancelledState();
        } else {
            statusElement.textContent = status;
            statusElement.classList.add('badge-secondary');
        }
        
        const now = new Date();
        const timeString = now.toLocaleTimeString('id-ID', { 
            hour: '2-digit', minute: '2-digit'
        });
        if (lastUpdatedElement) lastUpdatedElement.textContent = timeString;
    }
    
    function showCancelledState() {
        const cashSection = document.querySelector('.cash-info-section');
        if (cashSection && !cashSection.dataset.cancelled) {
            cashSection.dataset.cancelled = '1';
            cashSection.dataset.originalHtml = cashSection.innerHTML;
            cashSection.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-times-circle fa-3x mb-3 d-block"></i>
                    <h4 class="fw-bold mb-2">Pesanan Dibatalkan</h4>
                    <p class="mb-0">Pesanan ini telah dibatalkan oleh kasir.</p>
                    <p class="mb-0"><small>Silakan hubungi kasir untuk informasi lebih lanjut.</small></p>
                </div>
            `;
            cashSection.style.border = '2px solid #dc3545';
            cashSection.style.background = '#f8d7da';
        }
        
        showCancelledNotification();
    }
    
    function removeCancelledState() {
        const cashSection = document.querySelector('.cash-info-section');
        if (cashSection && cashSection.dataset.cancelled) {
            if (cashSection.dataset.originalHtml) {
                cashSection.innerHTML = cashSection.dataset.originalHtml;
            }
            cashSection.removeAttribute('data-cancelled');
            cashSection.style.border = '2px solid #28a745';
            cashSection.style.background = 'linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%)';
            
            paymentAlreadyPaid = false;
        }
        
        const existingNotif = document.getElementById('cancelledNotification');
        if (existingNotif) existingNotif.remove();
        sessionStorage.removeItem('cancelled_notification_shown');
    }
    
    let paymentAlreadyPaid = false;
    
    function updatePaymentStatus(paymentStatus) {
        const paymentStatusElement = document.getElementById('payment-status');
        if (!paymentStatusElement) return;
        
        const cashSection = document.querySelector('.cash-info-section');
        if (cashSection && cashSection.dataset.cancelled) {
            return;
        }
        
        paymentStatusElement.className = 'badge';
        
        if (paymentStatus === 'paid') {
            paymentStatusElement.textContent = 'LUNAS';
            paymentStatusElement.classList.add('badge-payment-paid');
            
            if (!paymentAlreadyPaid) {
                paymentAlreadyPaid = true;
                const cashSectionEl = document.querySelector('.cash-info-section');
                if (cashSectionEl && !cashSectionEl.dataset.cancelled) {
                    cashSectionEl.innerHTML = `
                        <div class="icon-cash" style="background: linear-gradient(135deg, #28a745, #20c997);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Pembayaran Diterima!</h3>
                        <p>Terima kasih, pembayaran Anda telah dikonfirmasi</p>
                        <div class="payment-amount-cash">
                            Rp <?php echo number_format($total_all, 0, ',', '.'); ?>
                        </div>
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Status: LUNAS</strong> — Pesanan Anda sedang disiapkan
                        </div>
                    `;
                }
            }
        } else if (paymentStatus === 'pending') {
            paymentStatusElement.textContent = 'Bayar di Kasir';
            paymentStatusElement.classList.add('badge-payment-pending');
        }
    }
    
    function showCompletionNotification() {
        if (sessionStorage.getItem('notification_shown') === 'true') return;
        
        const notification = document.createElement('div');
        notification.className = 'alert alert-success position-fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '1050';
        notification.style.minWidth = '300px';
        notification.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>
            <strong>Pesanan Selesai!</strong> Pesanan Anda telah selesai diproses.
        `;
        document.body.appendChild(notification);
        sessionStorage.setItem('notification_shown', 'true');
        
        setTimeout(() => {
            if (notification.parentNode) notification.remove();
        }, 5000);
    }
    
    function showCancelledNotification() {
        if (sessionStorage.getItem('cancelled_notification_shown') === 'true') return;
        
        const existing = document.getElementById('cancelledNotification');
        if (existing) existing.remove();
        
        const notification = document.createElement('div');
        notification.id = 'cancelledNotification';
        notification.className = 'alert alert-danger position-fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '1050';
        notification.style.minWidth = '300px';
        notification.innerHTML = `
            <i class="fas fa-times-circle me-2"></i>
            <strong>Pesanan Dibatalkan!</strong> Silakan hubungi kasir untuk info lebih lanjut.
        `;
        document.body.appendChild(notification);
        sessionStorage.setItem('cancelled_notification_shown', 'true');
        
        setTimeout(() => {
            if (notification.parentNode) notification.remove();
        }, 5000);
    }
    
    function startStatusPolling() {
        updateOrderStatus();
        setInterval(updateOrderStatus, 5000);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        updateRealTime();
        setInterval(updateRealTime, 1000);
        startStatusPolling();
        
        sessionStorage.removeItem('cart_items');
        localStorage.removeItem('timer_expired_shown');
        sessionStorage.removeItem('notification_shown');
        sessionStorage.removeItem('cancelled_notification_shown');
    });
</script>
            
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        unset($_SESSION['cart']);
        exit();
        
    } else {
        // Fallback single item
        $menu_item = trim($_POST['menu'] ?? '');
        $harga = (int)($_POST['harga'] ?? 0);
        $jumlah = (int)($_POST['jumlah'] ?? 1);
        $total = $jumlah * $harga;
        
        if (empty($menu_item) || $harga <= 0) {
            echo "<script>alert('Data pesanan tidak valid.'); window.history.back();</script>";
            exit();
        }
        
        // Cek kolom yang tersedia
        $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM orders");
        $columns = [];
        while ($row = mysqli_fetch_assoc($check_columns)) {
            $columns[] = $row['Field'];
        }
        
        $has_session          = in_array('session_id', $columns);
        $has_metode           = in_array('metode_pembayaran', $columns);
        $has_status_pembayaran= in_array('status_pembayaran', $columns);
        $has_waktu_pesan      = in_array('waktu_pesan', $columns);
        $has_catatan          = in_array('catatan', $columns);
        
        // Bangun query INSERT dinamis
        $fields = ['nama_pelanggan', 'menu', 'harga', 'jumlah', 'total', 'status'];
        $params = [$nama, $menu_item, $harga, $jumlah, $total, 'pending'];
        $placeholders = ['?', '?', '?', '?', '?', '?'];
        
        if ($has_catatan) {
            $fields[] = 'catatan';
            $params[] = $catatan;
            $placeholders[] = '?';
        }
        if ($has_metode) {
            $fields[] = 'metode_pembayaran';
            $params[] = $metode_pembayaran;
            $placeholders[] = '?';
        }
        if ($has_status_pembayaran) {
            $fields[] = 'status_pembayaran';
            $params[] = 'pending';
            $placeholders[] = '?';
        }
        if ($has_waktu_pesan) {
            $fields[] = 'waktu_pesan';
            $params[] = $waktu_pesan;
            $placeholders[] = '?';
        }
        if ($has_session) {
            $fields[] = 'session_id';
            $params[] = $session_id;
            $placeholders[] = '?';
        }
        
        $sql = "INSERT INTO orders (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $affected = db_execute($conn, $sql, $params);
        
        if ($affected > 0) {
            $order_id = db_insert_id($conn);
            $_SESSION['order_id'] = $order_id;
            $_SESSION['last_order_name'] = $nama;
            $_SESSION['metode_pembayaran'] = $metode_pembayaran;
            $_SESSION['order_session_id'] = $session_id;
            
            unset($_SESSION['cart']);
            header("Location: index.php");
            exit();
        } else {
            echo "<div class='alert alert-danger'>Terjadi kesalahan sistem.</div>";
            echo "<a href='order.php' class='btn btn-primary'>Kembali</a>";
            exit();
        }
    }
    
} else {
    header("Location: index.php");
    exit();
}
?>