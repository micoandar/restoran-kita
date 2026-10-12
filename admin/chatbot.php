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
// HANDLE FORM POST (Tambah / Edit / Hapus / Toggle)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // CSRF verify
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "CSRF token tidak valid. Muat ulang halaman.";
        header("Location: chatbot.php");
        exit();
    }
    
    $action = $_POST['action'];
    
    // -------- TAMBAH --------
    if ($action === 'tambah') {
        $keywords = trim($_POST['keywords'] ?? '');
        $question = trim($_POST['question'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        $priority = (int)($_POST['priority'] ?? 5);
        
        if (empty($keywords) || empty($answer)) {
            $_SESSION['error'] = "Keywords dan Jawaban wajib diisi!";
        } else {
            // Normalisasi keywords: hilangkan spasi setelah koma
            $keywords = implode(',', array_map('trim', explode(',', $keywords)));
            
            $affected = db_execute($conn,
                "INSERT INTO chatbot_faq (keywords, question, answer, priority, is_active) 
                 VALUES (?, ?, ?, ?, 1)",
                [$keywords, $question, $answer, $priority]
            );
            
            $_SESSION['success'] = $affected > 0 
                ? "FAQ berhasil ditambahkan!" 
                : "Gagal menambahkan FAQ.";
        }
    }
    
    // -------- EDIT --------
    elseif ($action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $keywords = trim($_POST['keywords'] ?? '');
        $question = trim($_POST['question'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        $priority = (int)($_POST['priority'] ?? 5);
        
        if ($id <= 0 || empty($keywords) || empty($answer)) {
            $_SESSION['error'] = "Data tidak valid.";
        } else {
            $keywords = implode(',', array_map('trim', explode(',', $keywords)));
            
            $affected = db_execute($conn,
                "UPDATE chatbot_faq 
                 SET keywords = ?, question = ?, answer = ?, priority = ? 
                 WHERE id = ?",
                [$keywords, $question, $answer, $priority, $id]
            );
            
            $_SESSION['success'] = $affected > 0 
                ? "FAQ berhasil diperbarui!" 
                : "Tidak ada perubahan (data sama).";
        }
    }
    
    // -------- HAPUS --------
    elseif ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_execute($conn, "DELETE FROM chatbot_faq WHERE id = ?", [$id]);
            $_SESSION['success'] = "FAQ berhasil dihapus!";
        }
    }
    
    // -------- TOGGLE AKTIF --------
    elseif ($action === 'toggle') {
        $id         = (int)($_POST['id'] ?? 0);
        $is_active  = (int)($_POST['is_active'] ?? 0);
        
        if ($id > 0) {
            db_execute($conn, 
                "UPDATE chatbot_faq SET is_active = ? WHERE id = ?", 
                [$is_active, $id]
            );
            $_SESSION['success'] = $is_active 
                ? "FAQ diaktifkan!" 
                : "FAQ dinonaktifkan.";
        }
    }
    
    header("Location: chatbot.php");
    exit();
}

// ============================================================
// AMBIL DATA FAQ
// ============================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search !== '') {
    $search_param = '%' . $search . '%';
    $faqs = db_query($conn, "
        SELECT * FROM chatbot_faq 
        WHERE keywords LIKE ? OR question LIKE ? OR answer LIKE ?
        ORDER BY priority DESC, id DESC
    ", [$search_param, $search_param, $search_param]);
} else {
    $faqs = db_query($conn, "SELECT * FROM chatbot_faq ORDER BY priority DESC, id DESC");
}

$total_faq    = $faqs ? mysqli_num_rows($faqs) : 0;
$total_aktif  = (int) db_val($conn, "SELECT COUNT(*) FROM chatbot_faq WHERE is_active = 1", [], 0);
$total_nonaktif = (int) db_val($conn, "SELECT COUNT(*) FROM chatbot_faq WHERE is_active = 0", [], 0);

// Ambil FAQ untuk diedit (jika ada ?edit=id)
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $edit_data = db_row($conn, "SELECT * FROM chatbot_faq WHERE id = ?", [$edit_id]);
    }
}

$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola FAQ Chatbot - Restoran Kita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary: #FF6B6B;
            --primary-dark: #FF5252;
            --secondary: #4ECDC4;
            --dark: #2c3e50;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --border-radius: 12px;
            --box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .stat-icon.total    { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.active   { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-icon.inactive { background: linear-gradient(135deg, #6c757d, #495057); }

        .stat-info .stat-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-info .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .action-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-box-faq {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box-faq input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s;
            outline: none;
        }

        .search-box-faq input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 107, 107, 0.1);
        }

        .search-box-faq i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .btn-add {
            background: linear-gradient(135deg, var(--primary), #FF8E53);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
            color: white;
        }

        .faq-table-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .faq-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .faq-table thead th {
            background: #1a1f36;
            color: white;
            padding: 15px;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .faq-table tbody td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
            font-size: 0.9rem;
        }

        .faq-table tbody tr:hover {
            background: rgba(255, 107, 107, 0.03);
        }

        .faq-table tbody tr.inactive {
            opacity: 0.55;
            background: #f8f9fa;
        }

        .keyword-chip {
            display: inline-block;
            background: #FFF5F5;
            color: var(--primary);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            margin: 2px 3px 2px 0;
            border: 1px solid #FFE5E5;
        }

        .answer-preview {
            color: #475569;
            line-height: 1.5;
            max-width: 400px;
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .priority-badge.high   { background: rgba(255, 107, 107, 0.15); color: #dc3545; }
        .priority-badge.medium { background: rgba(255, 193, 7, 0.15);  color: #b8860b; }
        .priority-badge.low    { background: rgba(108, 117, 125, 0.15); color: #6c757d; }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .status-pill.active {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }

        .status-pill.active:hover {
            background: #28a745;
            color: white;
        }

        .status-pill.inactive {
            background: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }

        .status-pill.inactive:hover {
            background: #6c757d;
            color: white;
        }

        .btn-action-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-edit-icon {
            background: rgba(78, 205, 196, 0.15);
            color: var(--secondary);
        }

        .btn-edit-icon:hover {
            background: var(--secondary);
            color: white;
        }

        .btn-delete-icon {
            background: rgba(220, 53, 69, 0.15);
            color: var(--danger);
        }

        .btn-delete-icon:hover {
            background: var(--danger);
            color: white;
        }

        .faq-guide {
            background: linear-gradient(135deg, #e0f7ff, #b3e5fc);
            border-left: 4px solid #0288d1;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .faq-guide h6 {
            color: #01579b;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .faq-guide p {
            color: #01579b;
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .faq-guide code {
            background: rgba(1, 87, 155, 0.1);
            color: #01579b;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .modal-content {
            border-radius: 16px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--dark), #34495e);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 20px 24px;
        }

        .modal-header .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }

        .modal-body {
            padding: 24px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 107, 107, 0.1);
        }

        .form-text {
            font-size: 0.82rem;
            color: #64748b;
            margin-top: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state h5 {
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #64748b;
        }
    </style>
</head>
<body class="admin-body">
    <!-- Sidebar -->
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
                <li><a href="report.php"><i class="fas fa-chart-bar"></i><span>Laporan</span></a></li>
                <li><a href="chatbot.php" class="active"><i class="fas fa-robot"></i><span>Chatbot FAQ</span></a></li>
                <li><a href="../index.php" target="_blank"><i class="fas fa-eye"></i><span>Lihat Website Utama</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <header class="admin-header">
            <button class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="mb-0"><i class="fas fa-robot me-2"></i>Chatbot FAQ</h4>
            <div class="header-right">
                <div class="admin-avatar"><?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?></div>
                <div class="fw-bold"><?php echo e($_SESSION['admin']); ?></div>
            </div>
        </header>
        
        <div class="content-wrapper">
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo e($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo e($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Guide -->
            <div class="faq-guide">
                <h6><i class="fas fa-info-circle me-2"></i>Cara Kerja Chatbot FAQ</h6>
                <p>
                    Chatbot akan mencocokkan pertanyaan user dengan <strong>keywords</strong>. 
                    Pisahkan keywords dengan <code>koma tanpa spasi</code>. 
                    Semakin banyak keyword, semakin pintar chatbot mengenali variasi pertanyaan.
                    <br>
                    Contoh: <code>jam buka,jam operasional,open,buka jam</code>
                </p>
            </div>
            
            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-comments"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $total_faq; ?></div>
                        <div class="stat-label">Total FAQ</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon active"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $total_aktif; ?></div>
                        <div class="stat-label">Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon inactive"><i class="fas fa-pause-circle"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $total_nonaktif; ?></div>
                        <div class="stat-label">Nonaktif</div>
                    </div>
                </div>
            </div>
            
            <!-- Action Bar -->
            <div class="action-bar">
                <div class="search-box-faq">
                    <i class="fas fa-search"></i>
                    <form method="GET" style="width:100%;">
                        <input type="text" name="search" placeholder="Cari keyword, pertanyaan, atau jawaban..." 
                               value="<?php echo e($search); ?>">
                    </form>
                </div>
                <button type="button" class="btn-add" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Tambah FAQ
                </button>
            </div>
            
            <!-- Table -->
            <div class="faq-table-card">
                <?php if($faqs && mysqli_num_rows($faqs) > 0): ?>
                <div style="overflow-x:auto;">
                    <table class="faq-table">
                        <thead>
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th style="width:250px;">Keywords</th>
                                <th style="width:180px;">Pertanyaan</th>
                                <th>Jawaban</th>
                                <th style="width:80px;">Priority</th>
                                <th style="width:100px;">Status</th>
                                <th style="width:120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($faq = mysqli_fetch_assoc($faqs)): 
                                $priority = (int)$faq['priority'];
                                if ($priority >= 8)      $prio_class = 'high';
                                elseif ($priority >= 5)  $prio_class = 'medium';
                                else                     $prio_class = 'low';
                                
                                $is_active = $faq['is_active'] == 1;
                                $keywords = array_filter(array_map('trim', explode(',', $faq['keywords'])));
                            ?>
                            <tr class="<?php echo $is_active ? '' : 'inactive'; ?>">
                                <td><strong>#<?php echo $faq['id']; ?></strong></td>
                                <td>
                                    <?php foreach($keywords as $kw): ?>
                                        <span class="keyword-chip"><?php echo e($kw); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><strong style="color:var(--dark);"><?php echo e($faq['question']); ?></strong></td>
                                <td>
                                    <div class="answer-preview">
                                        <?php echo nl2br(e(mb_substr($faq['answer'], 0, 200))); ?><?php echo mb_strlen($faq['answer']) > 200 ? '...' : ''; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="priority-badge <?php echo $prio_class; ?>"><?php echo $priority; ?></span>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $faq['id']; ?>">
                                        <input type="hidden" name="is_active" value="<?php echo $is_active ? 0 : 1; ?>">
                                        <button type="submit" class="status-pill <?php echo $is_active ? 'active' : 'inactive'; ?>">
                                            <?php if($is_active): ?>
                                                <i class="fas fa-check"></i> Aktif
                                            <?php else: ?>
                                                <i class="fas fa-pause"></i> Nonaktif
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo $faq['id']; ?>" 
                                       class="btn-action-icon btn-edit-icon" 
                                       title="Edit"
                                       onclick="openEditModal(<?php echo htmlspecialchars(json_encode($faq), ENT_QUOTES); ?>); return false;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display:inline;" 
                                          onsubmit="return confirm('Yakin ingin menghapus FAQ ini?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?php echo $faq['id']; ?>">
                                        <button type="submit" class="btn-action-icon btn-delete-icon" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-robot"></i>
                    <h5>Belum ada FAQ</h5>
                    <p>Mulai tambahkan FAQ supaya chatbot Anda pintar menjawab pertanyaan pelanggan.</p>
                    <button class="btn-add mt-2" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Tambah FAQ Pertama
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         MODAL TAMBAH FAQ
         ============================================================ -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                    <input type="hidden" name="action" value="tambah">
                    
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah FAQ Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Keywords <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="keywords" required
                                   placeholder="jam buka,jam operasional,open,buka jam"
                                   value="">
                            <div class="form-text">
                                <i class="fas fa-lightbulb me-1"></i>
                                Pisahkan dengan <strong>koma tanpa spasi</strong>. Semakin banyak keyword, semakin pintar chatbot.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Pertanyaan (Referensi Admin)</label>
                            <input type="text" class="form-control" name="question"
                                   placeholder="Jam berapa restoran buka?"
                                   value="">
                            <div class="form-text">Hanya untuk referensi Anda. Tidak ditampilkan ke pelanggan.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Jawaban <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="answer" rows="5" required
                                      placeholder="Kami buka setiap hari:&#10;• Senin-Jumat: 10.00 - 22.00 WIB&#10;• Sabtu-Minggu: 09.00 - 23.00 WIB"></textarea>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Boleh pakai emoji dan baris baru. Format <strong>**teks**</strong> akan jadi <strong>bold</strong>.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Priority (1-10)</label>
                            <input type="number" class="form-control" name="priority" 
                                   min="1" max="10" value="5">
                            <div class="form-text">
                                Semakin tinggi, semakin prioritas. Kalau ada 2 keyword cocok, yang priority tinggi menang.
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" 
                                style="background: linear-gradient(135deg, #FF6B6B, #FF8E53); border:none;">
                            <i class="fas fa-save me-2"></i>Simpan FAQ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         MODAL EDIT FAQ
         ============================================================ -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit FAQ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Keywords <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="keywords" id="edit_keywords" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Pertanyaan (Referensi Admin)</label>
                            <input type="text" class="form-control" name="question" id="edit_question">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Jawaban <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="answer" id="edit_answer" rows="5" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Priority (1-10)</label>
                            <input type="number" class="form-control" name="priority" id="edit_priority" 
                                   min="1" max="10" value="5">
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" 
                                style="background: linear-gradient(135deg, #FF6B6B, #FF8E53); border:none;">
                            <i class="fas fa-save me-2"></i>Update FAQ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
        
        // Modal instances
        const addModal = new bootstrap.Modal(document.getElementById('addModal'));
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        
        // Buka modal tambah
        function openAddModal() {
            addModal.show();
        }
        
        // Buka modal edit + isi data
        function openEditModal(faq) {
            document.getElementById('edit_id').value = faq.id;
            document.getElementById('edit_keywords').value = faq.keywords;
            document.getElementById('edit_question').value = faq.question || '';
            document.getElementById('edit_answer').value = faq.answer;
            document.getElementById('edit_priority').value = faq.priority || 5;
            
            editModal.show();
        }
        
        // Auto-open edit modal kalau URL punya ?edit=...
        <?php if($edit_data): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const faqData = <?php echo json_encode($edit_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            openEditModal(faqData);
            
            // Bersihkan URL
            if (window.history.replaceState) {
                window.history.replaceState({}, '', 'chatbot.php');
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>