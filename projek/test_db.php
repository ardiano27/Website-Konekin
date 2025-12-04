<?php
require_once __DIR__ . '/config/Database.php';
$database = new Database();
$conn = $database->getConnection();

if ($conn) {
    echo "✅ Class Database ditemukan dan koneksi ke database berhasil!";
} else {
    echo "❌ Gagal membuat koneksi.";
}
