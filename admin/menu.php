<?php
// ============================================================
// MENU MANAGEMENT — dengan Prepared Statement + CSRF
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
// FUNGSI UPLOAD GAMBAR
// ============================================================
function uploadGambar($fileKey = 'gambar') {
    if (!isset($_FILES[$fileKey])) return null;
    
    $namaFile   = $_FILES[$fileKey]['name'];
    $ukuranFile = $_FILES[$fileKey]['size'];
    $error      = $_FILES[$fileKey]['error'];
    $tmpName    = $_FILES[$fileKey]['tmp_name'];
    
    // Tidak ada file diupload
    if ($error === UPLOAD_ERR_NO_FILE || empty($namaFile)) {
        return null;
    }
    
    // Cek error upload
    if ($error !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Gagal upload gambar (error code: $error)";
        return false;
    }
    
    // Cek file benar-benar diupload lewat HTTP POST
    if (!is_uploaded_file($tmpName)) {
        $_SESSION['error'] = "File tidak valid.";
        return false;
    }
    
    // Validasi ekstensi
    $ekstensiValid = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ekstensi = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));
    
    if (!in_array($ekstensi, $ekstensiValid)) {
        $_SESSION['error'] = "Format gambar harus JPG, JPEG, PNG, WEBP, atau GIF!";
        return false;
    }
    
    // Validasi ukuran (maks 2MB)
    if ($ukuranFile > 2000000) {
        $_SESSION['error'] = "Ukuran gambar terlalu besar! Maksimal 2MB.";
        return false;
    }
    
    // Validasi MIME type asli (bukan hanya ekstensi)
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (function_exists('mime_content_type')) {
        $realMime = @mime_content_type($tmpName);
        if ($realMime && !in_array($realMime, $allowedMimes)) {
            $_SESSION['error'] = "File yang diupload bukan gambar yang valid.";
            return false;
        }
    }
    
    // Generate nama unik
    $namaBaru = uniqid() . '_' . date('Ymd') . '.' . $ekstensi;
    
    $uploadDir = '../uploads/';
    if (!file_exists($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    
    $uploadFile = $uploadDir . $namaBaru;
    
    if (move_uploaded_file($tmpName, $uploadFile)) {
        return $namaBaru;
    }
    
    $_SESSION['error'] = "Gagal mengupload gambar!";
    return false;
}

// ============================================================
// TAMBAH MENU
// ============================================================
if (isset($_POST['tambah'])) {
    // CSRF check
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "CSRF token tidak valid. Muat ulang halaman.";
        header("Location: menu.php");
        exit();
    }
    
    $nama      = trim($_POST['nama'] ?? '');
    $harga     = (int)($_POST['harga'] ?? 0);
    $kategori  = trim($_POST['kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    
    // Validasi input
    if (empty($nama) || $harga <= 0 || empty($kategori)) {
        $_SESSION['error'] = "Nama, harga, dan kategori wajib diisi dengan benar.";
        header("Location: menu.php");
        exit();
    }
    
    // Upload gambar
    $gambar = uploadGambar();
    
    if ($gambar === false) {
        header("Location: menu.php");
        exit();
    }
    
    if ($gambar === null) {
        $_SESSION['error'] = "Gambar wajib diupload untuk menu baru!";
        header("Location: menu.php");
        exit();
    }
    
    // Insert pakai prepared statement
    $affected = db_execute($conn, 
        "INSERT INTO menu (nama, harga, kategori, deskripsi, gambar, created_at) 
         VALUES (?, ?, ?, ?, ?, NOW())",
        [$nama, $harga, $kategori, $deskripsi, $gambar]
    );
    
    if ($affected > 0) {
        $_SESSION['success'] = "Menu berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan menu.";
        // Hapus file yang sudah terupload biar tidak jadi sampah
        @unlink('../uploads/' . $gambar);
    }
    
    header("Location: menu.php");
    exit();
}

// ============================================================
// EDIT MENU
// ============================================================
if (isset($_POST['edit'])) {
    // CSRF check
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "CSRF token tidak valid. Muat ulang halaman.";
        header("Location: menu.php");
        exit();
    }
    
    $id        = (int)($_POST['id'] ?? 0);
    $nama      = trim($_POST['nama'] ?? '');
    $harga     = (int)($_POST['harga'] ?? 0);
    $kategori  = trim($_POST['kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $gambarLama= trim($_POST['gambarLama'] ?? '');
    
    if ($id <= 0 || empty($nama) || $harga <= 0) {
        $_SESSION['error'] = "Data tidak valid.";
        header("Location: menu.php");
        exit();
    }
    
    // Default pakai gambar lama
    $gambar = $gambarLama;
    
    // Cek apakah user upload gambar baru
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE && !empty($_FILES['gambar']['name'])) {
        $gambarBaru = uploadGambar();
        
        if ($gambarBaru === false) {
            header("Location: menu.php?edit=" . $id);
            exit();
        }
        
        if ($gambarBaru !== null) {
            $gambar = $gambarBaru;
            
            // Hapus gambar lama (kalau bukan default & bukan kosong)
            if (!empty($gambarLama) && $gambarLama !== 'default.jpg') {
                $oldFile = '../uploads/' . $gambarLama;
                if (file_exists($oldFile) && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }
    }
    
    // Update pakai prepared
    $affected = db_execute($conn,
        "UPDATE menu SET 
            nama = ?, 
            harga = ?, 
            kategori = ?,
            deskripsi = ?, 
            gambar = ?,
            updated_at = NOW()
         WHERE id = ?",
        [$nama, $harga, $kategori, $deskripsi, $gambar, $id]
    );
    
    if ($affected > 0) {
        $_SESSION['success'] = "Menu berhasil diperbarui!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui menu (mungkin tidak ada perubahan).";
    }
    
    header("Location: menu.php");
    exit();
}

// ============================================================
// HAPUS MENU
// ============================================================
if (isset($_GET['hapus'])) {
    // CSRF check via query
    if (!verifyCsrfToken($_GET['csrf'] ?? '')) {
        $_SESSION['error'] = "CSRF token tidak valid.";
        header("Location: menu.php");
        exit();
    }
    
    $id = (int)$_GET['hapus'];
    
    if ($id <= 0) {
        $_SESSION['error'] = "ID menu tidak valid.";
        header("Location: menu.php");
        exit();
    }
    
    // Ambil nama file gambar
    $menu = db_row($conn, "SELECT gambar FROM menu WHERE id = ?", [$id]);
    
    if (!$menu) {
        $_SESSION['error'] = "Menu tidak ditemukan.";
        header("Location: menu.php");
        exit();
    }
    
    // Hapus dari DB
    $affected = db_execute($conn, "DELETE FROM menu WHERE id = ?", [$id]);
    
    if ($affected > 0) {
        // Hapus file gambar kalau bukan default
        if (!empty($menu['gambar']) && $menu['gambar'] !== 'default.jpg') {
            $fileToDelete = '../uploads/' . $menu['gambar'];
            if (file_exists($fileToDelete) && is_file($fileToDelete)) {
                @unlink($fileToDelete);
            }
        }
        $_SESSION['success'] = "Menu berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus menu.";
    }
    
    header("Location: menu.php");
    exit();
}

// ============================================================
// AMBIL DATA MENU
// ============================================================
$menus = db_query($conn, "SELECT * FROM menu ORDER BY id DESC");
$totalMenu = $menus ? mysqli_num_rows($menus) : 0;

// Ambil data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $edit_data = db_row($conn, "SELECT * FROM menu WHERE id = ?", [$edit_id]);
    }
}

// Statistik
$expensive = (int) db_val($conn, "SELECT MAX(harga) as max FROM menu", [], 0);
$cheapest  = (int) db_val($conn, "SELECT MIN(harga) as min FROM menu", [], 0);

// CSRF token
$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - Admin Restoran Kita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        :root {
            --primary: #ff6b6b;
            --primary-dark: #ff5252;
            --secondary: #4d96ff;
            --secondary-dark: #3a7df8;
            --dark: #2c3e50;
            --dark-light: #34495e;
            --light: #f8f9fa;
            --light-gray: #e9ecef;
            --gray: #6c757d;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --border-radius: 12px;
            --box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            --box-shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --transition-slow: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .menu-item {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            transition: var(--transition);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
        }
        
        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }
        
        .menu-item:hover { transform: translateY(-5px); box-shadow: var(--box-shadow-hover); }
        .menu-item:hover::before { transform: scaleX(1); }
        
        .menu-img {
            height: 200px;
            overflow: hidden;
            position: relative;
            background-color: #f8f9fa;
        }
        
        .menu-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition-slow);
        }
        
        .menu-item:hover .menu-img img { transform: scale(1.08); }

        .menu-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .menu-body { padding: 20px; position: relative; }
        
        .menu-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
            font-size: 1.1rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .menu-desc {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.6;
            min-height: 68px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            opacity: 0.9;
        }
        
        .menu-price {
            color: var(--primary);
            font-weight: 800;
            font-size: 1.2rem;
            margin-bottom: 15px;
            display: block;
        }
        
        .menu-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .menu-actions { display: flex; gap: 10px; }
        
        .btn-action {
            flex: 1;
            border-radius: 8px;
            padding: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-action:hover { transform: translateY(-2px); }
        
        .btn-edit {
            background: linear-gradient(135deg, var(--secondary), var(--secondary-dark));
            color: white;
        }
        .btn-edit:hover { background: linear-gradient(135deg, var(--secondary-dark), #2a6bd6); color: white; }
        
        .btn-delete {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        .btn-delete:hover { background: linear-gradient(135deg, var(--primary-dark), #ff3838); color: white; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
            background: var(--light);
            border-radius: var(--border-radius);
            margin: 30px 0;
        }
        
        .empty-state i { font-size: 4rem; margin-bottom: 20px; color: var(--light-gray); opacity: 0.7; }
        .empty-state h4 { font-weight: 600; margin-bottom: 15px; color: var(--dark); }
        .empty-state p { max-width: 400px; margin: 0 auto 30px; line-height: 1.6; }

        .tab-content {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-top: 20px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .nav-tabs { border: none; gap: 10px; margin-bottom: 0; }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            color: var(--gray);
            font-weight: 600;
            background: var(--light);
            transition: var(--transition);
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--dark);
            background: var(--light-gray);
            transform: translateY(-2px);
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }

        .form-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            padding: 25px;
            background: white;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            padding: 10px 15px;
            transition: var(--transition);
            font-size: 0.95rem;
            background-color: var(--light);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(255, 107, 107, 0.15);
            background-color: white;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .form-label .required { color: var(--primary); }

        .image-preview-container { position: relative; margin-top: 15px; }
        
        .image-preview {
            width: 100%;
            min-height: 180px;
            border: 2px dashed var(--light-gray);
            border-radius: var(--border-radius);
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light);
            transition: var(--transition);
            overflow: hidden;
        }
        
        .image-preview:hover { border-color: var(--primary); background: var(--light-gray); }
        
        .image-preview img {
            max-width: 100%;
            max-height: 160px;
            border-radius: 8px;
            object-fit: cover;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }
        
        .image-preview img:hover { transform: scale(1.02); }
        
        .image-preview-placeholder { text-align: center; color: var(--gray); padding: 20px; }
        .image-preview-placeholder i { font-size: 2.5rem; margin-bottom: 10px; opacity: 0.5; }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 10px;
            padding: 10px 30px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
            background: linear-gradient(135deg, var(--primary-dark), var(--secondary-dark));
            color: white;
        }
        
        .btn-secondary {
            background: var(--light);
            border: 2px solid var(--light-gray);
            color: var(--gray);
            border-radius: 10px;
            padding: 10px 30px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary:hover {
            background: var(--light-gray);
            border-color: var(--gray);
            color: var(--dark);
            transform: translateY(-2px);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }

        .alert-admin {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary);
            height: 100%;
            margin-bottom: 20px;
        }
        
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--box-shadow-hover); }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .stat-icon { font-size: 1.5rem; opacity: 0.8; text-align: right; }
        
        .stat-card.total .stat-number { color: var(--primary); }
        .stat-card.expensive .stat-number { color: var(--warning); }
        .stat-card.cheapest .stat-number { color: var(--success); }

        @media (max-width: 768px) {
            .menu-img { height: 160px; }
            .menu-body { padding: 15px; }
            .menu-title { font-size: 1rem; }
            .menu-price { font-size: 1.1rem; }
            .menu-actions { flex-direction: column; gap: 8px; }
            .btn-action { width: 100%; }
            .tab-content { padding: 20px; }
            .nav-tabs .nav-link { padding: 10px 15px; font-size: 0.9rem; }
            .empty-state i { font-size: 3rem; }
            .form-actions { flex-direction: column; }
            .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
            .stat-card { padding: 15px; }
            .stat-number { font-size: 1.5rem; }
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
                <li><a href="menu.php" class="active"><i class="fas fa-utensils"></i><span>Kelola Menu</span></a></li>
                <li><a href="order.php"><i class="fas fa-shopping-cart"></i><span>Data Order</span></a></li>
                <li><a href="customer.php"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
                <li><a href="report.php"><i class="fas fa-chart-bar"></i><span>Laporan</span></a></li>
                <li><a href="chatbot.php"><i class="fas fa-robot"></i><span>Chatbot FAQ</span></a></li>
                    <a href="../index.php" target="_blank">
                        <i class="fas fa-eye"></i>
                        <span>Lihat Website Utama</span>
                    </a>
                </li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
            </ul>
        </div>
    </div>
    
    <div class="main-content">
        <header class="admin-header">
            <button class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="mb-0">
                <i class="fas fa-utensils me-2"></i>Kelola Menu
                <?php if(isset($edit_data)): ?>
                    <small class="text-muted">/ Edit Menu</small>
                <?php endif; ?>
            </h4>
            <div class="header-right">
                <div class="admin-avatar"><?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?></div>
                <div class="fw-bold"><?php echo e($_SESSION['admin']); ?></div>
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
            
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card total">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number"><?php echo $totalMenu; ?></div>
                                <div class="stat-label">Total Menu</div>
                            </div>
                            <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card expensive">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number">Rp <?php echo number_format($expensive, 0, ',', '.'); ?></div>
                                <div class="stat-label">Menu Termahal</div>
                            </div>
                            <div class="stat-icon"><i class="fas fa-crown"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card cheapest">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number">Rp <?php echo number_format($cheapest, 0, ',', '.'); ?></div>
                                <div class="stat-label">Menu Termurah</div>
                            </div>
                            <div class="stat-icon"><i class="fas fa-coins"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <ul class="nav nav-tabs" id="menuTabs">
                <li class="nav-item">
                    <button class="nav-link <?php echo !isset($_GET['edit']) ? 'active' : ''; ?>" 
                            data-bs-toggle="tab" 
                            data-bs-target="#list-menu">
                        <i class="fas fa-list me-2"></i>Daftar Menu
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo isset($_GET['edit']) ? 'active' : ''; ?>" 
                            data-bs-toggle="tab" 
                            data-bs-target="#add-menu">
                        <i class="fas <?php echo isset($edit_data) ? 'fa-edit' : 'fa-plus'; ?> me-2"></i>
                        <?php echo isset($edit_data) ? 'Edit Menu' : 'Tambah Menu Baru'; ?>
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="menuTabContent">
                <!-- TAB DAFTAR MENU -->
                <div class="tab-pane fade <?php echo !isset($_GET['edit']) ? 'show active' : ''; ?>" id="list-menu">
                    <?php if($menus && mysqli_num_rows($menus) > 0): ?>
                        <div class="row mt-4">
                            <?php while($menu = mysqli_fetch_assoc($menus)): 
                                $gambarFile = '../uploads/' . $menu['gambar'];
                                $gambarDisplay = (!empty($menu['gambar']) && file_exists($gambarFile)) 
                                    ? $gambarFile 
                                    : 'https://images.unsplash.com/photo-1559314809-2b99056a8c4a?w=400&h=300&fit=crop';
                                $createdDate = date('d M Y', strtotime($menu['created_at']));
                            ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="menu-item">
                                    <?php if($menu['harga'] > 100000): ?>
                                        <div class="menu-badge">
                                            <i class="fas fa-crown me-1"></i>Premium
                                        </div>
                                    <?php endif; ?>
                                    <div class="menu-img">
                                        <img src="<?php echo $gambarDisplay; ?>" 
                                             alt="<?php echo e($menu['nama']); ?>"
                                             onerror="this.src='https://images.unsplash.com/photo-1559314809-2b99056a8c4a?w=400&h=300&fit=crop'">
                                    </div>
                                    <div class="menu-body">
                                        <h5 class="menu-title"><?php echo e($menu['nama']); ?></h5>
                                        <div class="badge bg-light text-dark mb-2"><?php echo ucfirst(e($menu['kategori'] ?? 'makanan')); ?></div>
                                        <p class="menu-desc"><?php echo e($menu['deskripsi']); ?></p>
                                        
                                        <div class="menu-meta">
                                            <span class="text-muted">
                                                <i class="fas fa-calendar me-1"></i><?php echo $createdDate; ?>
                                            </span>
                                        </div>
                                        
                                        <span class="menu-price">Rp <?php echo number_format($menu['harga'], 0, ',', '.'); ?></span>
                                        
                                        <div class="menu-actions">
                                            <a href="?edit=<?php echo $menu['id']; ?>" 
                                               class="btn btn-action btn-edit">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </a>
                                            <a href="?hapus=<?php echo $menu['id']; ?>&csrf=<?php echo e($csrf_token); ?>" 
                                               class="btn btn-action btn-delete" 
                                               onclick="return confirm('Yakin ingin menghapus menu ini?')">
                                                <i class="fas fa-trash me-1"></i>Hapus
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state mt-4">
                            <i class="fas fa-utensils"></i>
                            <h4>Belum ada menu</h4>
                            <p>Tambahkan menu pertama Anda untuk mulai menjual</p>
                            <a href="menu.php?add" class="btn btn-primary px-4">
                                <i class="fas fa-plus me-2"></i>Tambah Menu Pertama
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- TAB TAMBAH/EDIT MENU -->
                <div class="tab-pane fade <?php echo isset($_GET['edit']) ? 'show active' : ''; ?>" id="add-menu">
                    <div class="form-card mt-3">
                        <h5 class="mb-4">
                            <i class="fas <?php echo isset($edit_data) ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                            <?php echo isset($edit_data) ? 'Edit Menu' : 'Tambah Menu Baru'; ?>
                        </h5>
                        
                        <form method="post" enctype="multipart/form-data" id="menuForm">
                            <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                            
                            <?php if(isset($edit_data)): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                                <input type="hidden" name="gambarLama" value="<?php echo e($edit_data['gambar'] ?? ''); ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-utensils me-1"></i>Nama Menu
                                            <span class="required">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="nama" 
                                               value="<?php echo e($edit_data['nama'] ?? ''); ?>" 
                                               required
                                               placeholder="Contoh: Nasi Goreng Spesial">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-list me-1"></i>Kategori
                                            <span class="required">*</span>
                                        </label>
                                        <select class="form-select" name="kategori" required>
                                            <option value="makanan" <?php echo (isset($edit_data['kategori']) && $edit_data['kategori'] == 'makanan') ? 'selected' : ''; ?>>Makanan</option>
                                            <option value="minuman" <?php echo (isset($edit_data['kategori']) && $edit_data['kategori'] == 'minuman') ? 'selected' : ''; ?>>Minuman</option>
                                            <option value="paket" <?php echo (isset($edit_data['kategori']) && $edit_data['kategori'] == 'paket') ? 'selected' : ''; ?>>Paket</option>
                                            <option value="dessert" <?php echo (isset($edit_data['kategori']) && $edit_data['kategori'] == 'dessert') ? 'selected' : ''; ?>>Dessert</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-tag me-1"></i>Harga (Rp)
                                            <span class="required">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   name="harga" 
                                                   value="<?php echo $edit_data['harga'] ?? ''; ?>" 
                                                   required
                                                   min="1000"
                                                   placeholder="15000">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-align-left me-1"></i>Deskripsi
                                            <span class="required">*</span>
                                        </label>
                                        <textarea class="form-control" 
                                                  name="deskripsi" 
                                                  rows="4" 
                                                  required
                                                  placeholder="Deskripsi menu"><?php echo e($edit_data['deskripsi'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-image me-1"></i>Gambar Menu
                                            <?php if(!isset($edit_data)): ?>
                                            <span class="required">*</span>
                                            <?php endif; ?>
                                        </label>
                                        <input type="file" 
                                               name="gambar" 
                                               class="form-control" 
                                               accept="image/jpeg,image/png,image/webp,image/gif"
                                               onchange="previewImage(this)"
                                               <?php if(!isset($edit_data)) echo 'required'; ?>>
                                        <small class="text-muted d-block mt-1">Format: JPG, PNG, WEBP, GIF. Maks: 2MB</small>
                                        
                                        <div class="image-preview-container mt-3">
                                            <div class="image-preview" id="imagePreview">
                                                <?php if(isset($edit_data['gambar']) && !empty($edit_data['gambar'])): 
                                                    $gambarEditPath = '../uploads/' . $edit_data['gambar'];
                                                    $gambarEditExists = file_exists($gambarEditPath);
                                                ?>
                                                    <?php if($gambarEditExists): ?>
                                                        <img src="<?php echo $gambarEditPath; ?>" 
                                                             id="previewImg"
                                                             alt="Preview Gambar">
                                                    <?php else: ?>
                                                        <div class="image-preview-placeholder">
                                                            <i class="fas fa-image"></i>
                                                            <p class="mb-0">Gambar tidak ditemukan</p>
                                                        </div>
                                                        <img src="" id="previewImg" style="display:none;">
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="image-preview-placeholder">
                                                        <i class="fas fa-image"></i>
                                                        <p class="mb-0">Preview gambar akan muncul di sini</p>
                                                    </div>
                                                    <img src="" id="previewImg" style="display:none;">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" 
                                        name="<?php echo isset($edit_data) ? 'edit' : 'tambah'; ?>" 
                                        class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo isset($edit_data) ? 'Update Menu' : 'Simpan Menu'; ?>
                                </button>
                                <a href="menu.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        // Preview gambar
        function previewImage(input) {
            const preview = document.getElementById('previewImg');
            const previewContainer = document.getElementById('imagePreview');
            const placeholder = previewContainer.querySelector('.image-preview-placeholder');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validasi ukuran di client-side
                if (file.size > 2000000) {
                    alert('Ukuran gambar terlalu besar! Maksimal 2MB.');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        // Auto tab switch jika edit mode
        <?php if(isset($_GET['edit'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const editTab = document.querySelector('[data-bs-target="#add-menu"]');
            if (editTab) {
                const tab = new bootstrap.Tab(editTab);
                tab.show();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>