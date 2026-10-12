<?php
/**
 * Chatbot Backend — Rule-based + Query Database
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

require_once 'config/database.php';
require_once 'config/security.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Helper response
function cb_respond($reply) {
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode(['success' => true, 'reply' => $reply]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'reply' => 'Method not allowed']);
    exit();
}

// Ambil pesan (support JSON body atau form-data)
$input = json_decode(file_get_contents('php://input'), true);
if (is_array($input) && isset($input['message'])) {
    $message = trim($input['message']);
} else {
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
}

if ($message === '') {
    cb_respond('Silakan ketik pertanyaan Anda.');
}

// Batasi panjang
if (mb_strlen($message) > 500) {
    $message = mb_substr($message, 0, 500);
}

$msg = mb_strtolower($message);
$msg = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $msg);
$msg = preg_replace('/\s+/', ' ', trim($msg));

// ============================================================
// 1. FAQ dari database
// ============================================================
$faqs = db_query($conn, "SELECT keywords, answer, priority FROM chatbot_faq WHERE is_active = 1 ORDER BY priority DESC");
if ($faqs) {
    $best_match = null;
    $best_score = 0;
    
    while ($faq = mysqli_fetch_assoc($faqs)) {
        $keywords = array_map('trim', explode(',', $faq['keywords']));
        foreach ($keywords as $kw) {
            $kw = mb_strtolower(trim($kw));
            if ($kw === '') continue;
            if (mb_strpos($msg, $kw) !== false) {
                $score = (int)$faq['priority'] + mb_strlen($kw);
                if ($score > $best_score) {
                    $best_score = $score;
                    $best_match = $faq['answer'];
                }
            }
        }
    }
    if ($best_match !== null) {
        cb_respond($best_match);
    }
}

// ============================================================
// 2. Greeting & small talk
// ============================================================
foreach (['halo','hai','hi','hey','hallo','assalamualaikum','selamat pagi','selamat siang','selamat sore','selamat malam'] as $g) {
    if (mb_strpos($msg, $g) !== false) {
        cb_respond("Halo! 👋 Saya asisten Restoran Kita.\n\nSaya bisa membantu:\n• Info menu & harga\n• Rekomendasi menu\n• Status pesanan\n• Cara pesan & bayar\n\nApa yang bisa saya bantu?");
    }
}

foreach (['terima kasih','makasih','thanks','thank you','thx'] as $t) {
    if (mb_strpos($msg, $t) !== false) {
        cb_respond("Sama-sama! 😊 Senang bisa membantu. Kalau ada pertanyaan lain, tanyakan saja ya!");
    }
}

foreach (['bye','selamat tinggal','sampai jumpa','goodbye'] as $g) {
    if (mb_strpos($msg, $g) !== false) {
        cb_respond("Terima kasih sudah mampir! Selamat menikmati hidangan kami. 🙏");
    }
}

// ============================================================
// 3. Status pesanan — "status pesanan [nama]"
// ============================================================
if (preg_match('/(status|cek|pesanan|order)\s+(?:atas nama|nama|untuk)?\s*(.+)/i', $message, $m)) {
    $nama = trim($m[2]);
    $nama = preg_replace('/\b(status|pesanan|order|saya|ku|cek|untuk|atas nama|nama|adalah|itu|nya)\b/i', '', $nama);
    $nama = trim($nama);
    
    if (mb_strlen($nama) >= 2) {
        $order = db_row($conn, "
            SELECT nama_pelanggan, GROUP_CONCAT(menu SEPARATOR ', ') as menus,
                   SUM(total) as total, MIN(status) as status,
                   MIN(status_pembayaran) as status_pembayaran,
                   MAX(created_at) as last_order
            FROM orders WHERE nama_pelanggan LIKE ?
            GROUP BY nama_pelanggan ORDER BY last_order DESC LIMIT 1
        ", ['%' . $nama . '%']);
        
        if ($order) {
            $status_map = [
                'pending' => '⏳ Menunggu Konfirmasi',
                'processing' => '👨‍🍳 Sedang Diproses',
                'completed' => '✅ Selesai',
                'cancelled' => '❌ Dibatalkan'
            ];
            $reply = "📋 Pesanan atas nama {$order['nama_pelanggan']}\n\n";
            $reply .= "🍽 Menu: {$order['menus']}\n";
            $reply .= "💰 Total: Rp " . number_format($order['total']) . "\n";
            $reply .= "📊 Status: " . ($status_map[$order['status']] ?? $order['status']) . "\n";
            $reply .= "💳 Pembayaran: " . ($order['status_pembayaran'] === 'paid' ? '✅ LUNAS' : '⏳ Belum Bayar');
            cb_respond($reply);
        } else {
            cb_respond("Maaf, saya tidak menemukan pesanan atas nama \"$nama\".\n\nPastikan nama sesuai dengan yang diisi saat memesan.");
        }
    }
}

// ============================================================
// 4. Menu detail spesifik
// ============================================================
$menu = db_row($conn, "SELECT nama, harga, kategori, deskripsi FROM menu WHERE LOWER(nama) LIKE ? LIMIT 1", ['%' . $msg . '%']);
if ($menu) {
    $reply = "🍽 {$menu['nama']}\n\n";
    $reply .= "💰 Harga: Rp " . number_format($menu['harga']) . "\n";
    $reply .= "📂 Kategori: " . ucfirst($menu['kategori']);
    if (!empty($menu['deskripsi'])) {
        $reply .= "\n\n📝 " . $menu['deskripsi'];
    }
    $reply .= "\n\nMau pesan? Klik menu ini di halaman utama! 🛒";
    cb_respond($reply);
}

// ============================================================
// 5. Termurah / Termahal
// ============================================================
if (preg_match('/(termurah|paling murah|murah)/i', $msg)) {
    $item = db_row($conn, "SELECT nama, harga FROM menu ORDER BY harga ASC LIMIT 1");
    if ($item) cb_respond("💸 Menu termurah kami:\n\n{$item['nama']} — Rp " . number_format($item['harga']));
}

if (preg_match('/(termahal|paling mahal|mahal|premium)/i', $msg)) {
    $item = db_row($conn, "SELECT nama, harga FROM menu ORDER BY harga DESC LIMIT 1");
    if ($item) cb_respond("👑 Menu premium kami:\n\n{$item['nama']} — Rp " . number_format($item['harga']));
}

// ============================================================
// 6. Rekomendasi
// ============================================================
if (preg_match('/(rekomendasi|saran|recommend|favorit|best seller|laris)/i', $msg)) {
    $items = db_query($conn, "
        SELECT m.nama, m.harga, COUNT(o.id) as total_order
        FROM menu m LEFT JOIN orders o ON o.menu = m.nama
        GROUP BY m.id, m.nama, m.harga
        ORDER BY total_order DESC, m.id DESC LIMIT 5
    ");
    if ($items && mysqli_num_rows($items) > 0) {
        $reply = "⭐ Rekomendasi Menu Terlaris\n\n";
        $no = 1;
        while ($item = mysqli_fetch_assoc($items)) {
            $reply .= "$no. {$item['nama']} — Rp " . number_format($item['harga']) . "\n";
            $no++;
        }
        $reply .= "\nMenu ini paling banyak dipesan pelanggan kami! 😋";
        cb_respond($reply);
    }
}

// ============================================================
// 7. List menu
// ============================================================
if (preg_match('/(menu|makanan|minuman|dessert|paket|apa saja|list|daftar|jual)/i', $msg)) {
    $kategori = null;
    if (preg_match('/(makanan)/i', $msg)) $kategori = 'makanan';
    elseif (preg_match('/(minuman)/i', $msg)) $kategori = 'minuman';
    elseif (preg_match('/(dessert|penutup|manis)/i', $msg)) $kategori = 'dessert';
    elseif (preg_match('/(paket)/i', $msg)) $kategori = 'paket';
    
    if ($kategori) {
        $items = db_query($conn, "SELECT nama, harga FROM menu WHERE kategori = ? ORDER BY harga ASC LIMIT 15", [$kategori]);
        $title = "Kategori " . ucfirst($kategori);
    } else {
        $items = db_query($conn, "SELECT nama, harga, kategori FROM menu ORDER BY kategori, harga ASC LIMIT 20");
        $title = "Semua Menu";
    }
    
    if ($items && mysqli_num_rows($items) > 0) {
        $reply = "📋 $title\n\n";
        $last_kat = null;
        while ($item = mysqli_fetch_assoc($items)) {
            if (!$kategori && isset($item['kategori']) && $item['kategori'] !== $last_kat) {
                $reply .= "\n" . strtoupper($item['kategori']) . "\n";
                $last_kat = $item['kategori'];
            }
            $reply .= "• {$item['nama']} — Rp " . number_format($item['harga']) . "\n";
        }
        $reply .= "\nKetik nama menu untuk lihat detail/harga.";
        cb_respond($reply);
    }
}

// ============================================================
// 8. Fallback
// ============================================================
cb_respond("Maaf, saya belum mengerti pertanyaan itu. 🤔\n\nCoba tanyakan:\n• \"Jam buka berapa?\"\n• \"Ada menu apa saja?\"\n• \"Harga Nasi Goreng berapa?\"\n• \"Rekomendasi menu terlaris\"\n• \"Status pesanan [nama saya]\"\n• \"Cara pesan bagaimana?\"\n\nAtau ketik \"halo\" untuk memulai.");