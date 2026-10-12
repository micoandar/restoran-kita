<?php
/**
 * Sample konfigurasi database
 * Copy file ini jadi database.php dan sesuaikan kredensial
 */

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'restoran_test';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

require_once __DIR__ . '/db_helper.php';