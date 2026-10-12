# 🍽️ Restoran Kita — Sistem Pemesanan Makanan Berbasis Web

Sistem pemesanan makanan berbasis web dengan alur lengkap dari katalog menu pelanggan hingga dashboard admin, dilengkapi **chatbot rule-based tanpa API**.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

---

## ✨ Fitur Utama

### 👤 Sisi Pelanggan
- **Katalog menu** dengan filter kategori
- **Keranjang belanja** berbasis session
- **Checkout** dengan input nama & catatan
- **Halaman status pesanan** dengan polling AJAX real-time (5 detik)
- **Chatbot pintar** — jawab pertanyaan otomatis tanpa API
  - Info menu & harga (query database)
  - Rekomendasi menu terlaris (JOIN dengan order history)
  - Cek status pesanan berdasarkan nama
  - FAQ custom (jam buka, alamat, promo, dll)
  - 14 tombol quick reply

### 🔐 Panel Admin
- **Dashboard statistik** dengan Chart.js (7 hari terakhir)
- **Manajemen menu CRUD** dengan upload gambar
- **Data order** — grouping by `session_id`, filter multi-kriteria, pagination
- **Update status order & pembayaran** via AJAX tanpa reload
- **Kelola pelanggan** — analitik tipe pelanggan
- **Laporan** dengan export Excel & print
- **Kelola FAQ chatbot** — CRUD via admin panel
- **Cetak invoice** per order

### 🛡️ Security Best Practices
- ✅ **Bcrypt password hashing** + auto-migrasi dari MD5
- ✅ **Prepared statement** untuk seluruh query MySQL
- ✅ **CSRF token** di semua form & endpoint AJAX
- ✅ **Session regeneration** setelah login (anti session fixation)
- ✅ **MIME type validation** untuk upload file
- ✅ **Security logging** terpusat

---

## 🛠️ Teknologi

| Layer | Teknologi |
|-------|-----------|
| Backend | PHP Native 8.x (prosedural) |
| Database | MySQL 8.x |
| Frontend | HTML5, CSS3, Bootstrap 5.3 |
| JavaScript | Vanilla JS, Fetch API, AJAX |
| Chart | Chart.js |
| Date Picker | Flatpickr |
| Icon | Font Awesome 6.4 |
| Font | Google Fonts (Plus Jakarta Sans) |

---

## 📁 Struktur Folder
restoran_test/
├── admin/ # Panel admin
│ ├── ajax_order_action.php # Endpoint AJAX order
│ ├── chatbot.php # Kelola FAQ chatbot
│ ├── customer.php # Data pelanggan
│ ├── dashboard.php # Dashboard admin
│ ├── login.php # Login admin
│ ├── logout.php
│ ├── menu.php # CRUD menu
│ ├── order.php # Data order
│ ├── print_order.php # Cetak invoice
│ └── report.php # Laporan
├── assets/
│ ├── css/
│ └── js/
├── config/
│ ├── database.sample.php # Template config (rename ke database.php)
│ ├── db_helper.php # Wrapper prepared statement
│ └── security.php # Helper CSRF & security
├── uploads/ # Gambar menu (gitignored)
├── chatbot.php # Endpoint AJAX chatbot
├── chatbot_widget.php # Widget chatbot (include di index)
├── get_order_status.php # Endpoint cek status order
├── index.php # Halaman utama
├── order.php # Checkout pelanggan
├── process_order.php # Proses pesanan
└── README.md

text

---

## 🚀 Cara Install

### 1. Clone repo
```bash
git clone https://github.com/username/restoran-kita.git
cd restoran-kita
2. Setup database
Buka phpMyAdmin → buat database baru dengan nama restoran_test

Import file SQL struktur tabel:

sql
-- Buka file database.sql di repo (lihat langkah 3)
3. Setup config
bash
cd config
cp database.sample.php database.php
Edit database.php, sesuaikan:

php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';           // password MySQL Anda
$db_name = 'restoran_test';
4. Jalankan
Taruh project di htdocs/ XAMPP, lalu buka:

text
http://localhost/restoran_test/
5. Login admin default
text
Username: admin
Password: admin123
⚠️ Segera ganti password setelah login pertama.

📸 Screenshot
Halaman Utama
https://screenshots/hero.png

Chatbot
https://screenshots/chatbot.png

Dashboard Admin
https://screenshots/dashboard.png

Data Order
https://screenshots/order.png

🗄️ Skema Database
4 Tabel Utama:

Tabel	Fungsi
admin	Akun admin (login)
menu	Katalog menu restoran
orders	Riwayat pesanan pelanggan
chatbot_faq	FAQ custom untuk chatbot
Relasi: orders.menu → menu.nama (text-based, bukan FK)

💡 Highlight Teknis
Chatbot tanpa API — Menggunakan rule-based matching (keyword + priority scoring) dan query dinamis ke database. Bisa di-custom sendiri oleh pemilik restoran tanpa coding.

Grouping order by session_id — Multi-item order dari 1 pelanggan dianggap 1 kesatuan, memudahkan manajemen.

Auto-migrasi password — User lama dengan MD5 otomatis di-upgrade ke bcrypt saat login pertama.

Scroll preservation pada auto-refresh — Admin tidak kehilangan posisi scroll saat halaman auto-refresh 15 detik.

📝 Lisensi
MIT License — silakan pakai, modifikasi, dan distribusikan.

👨‍💻 Author
Nama Micoandar Justinus Marbun

GitHub: @micoandar

Email: micoandar@gmail.com