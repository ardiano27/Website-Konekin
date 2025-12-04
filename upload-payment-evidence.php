<?php
session_start();
include "check_login.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$contract_id = $_POST['contract_id'] ?? 0;
$bank_name = $_POST['bank_name'] ?? '';
$account_number = $_POST['account_number'] ?? '';
$account_name = $_POST['account_name'] ?? '';
$payment_amount = $_POST['payment_amount'] ?? 0;
$payment_date = $_POST['payment_date'] ?? '';
$notes = $_POST['notes'] ?? '';

if (!$contract_id || !$bank_name || !$account_number || !$account_name || !$payment_amount || !$payment_date) {
    $_SESSION['error_message'] = "Semua field wajib diisi!";
    header("Location: contract-details.php?id=" . $contract_id);
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Cek apakah kontrak valid dan user adalah UMKM yang berhak
$check_sql = "SELECT c.*, u.user_type 
              FROM contracts c 
              JOIN users u ON c.umkm_user_id = u.id 
              WHERE c.id = :contract_id 
              AND c.umkm_user_id = :user_id 
              AND c.status = 'active' 
              AND c.payment_status = 'pending'";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->execute([
    ':contract_id' => $contract_id,
    ':user_id' => $_SESSION['user_id']
]);
$contract = $check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    $_SESSION['error_message'] = "Kontrak tidak ditemukan atau tidak valid untuk upload bukti pembayaran.";
    header("Location: dashboard.php");
    exit;
}

// Handle file upload
if (!isset($_FILES['evidence_file']) || $_FILES['evidence_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error_message'] = "File bukti pembayaran wajib diupload.";
    header("Location: contract-details.php?id=" . $contract_id);
    exit;
}

$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
$max_file_size = 5 * 1024 * 1024; // 5MB
$file = $_FILES['evidence_file'];

// Validasi file
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_extension, $allowed_extensions)) {
    $_SESSION['error_message'] = "Format file tidak didukung. Hanya JPG, PNG, GIF, dan PDF yang diperbolehkan.";
    header("Location: contract-details.php?id=" . $contract_id);
    exit;
}

if ($file['size'] > $max_file_size) {
    $_SESSION['error_message'] = "Ukuran file terlalu besar. Maksimal 5MB.";
    header("Location: contract-details.php?id=" . $contract_id);
    exit;
}

// Buat folder jika belum ada
$upload_dir = "uploads/payment_evidences/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate nama file unik
$filename = uniqid('payment_', true) . '.' . $file_extension;
$target_path = $upload_dir . $filename;

// Pindahkan file
if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    $_SESSION['error_message'] = "Gagal mengupload file.";
    header("Location: contract-details.php?id=" . $contract_id);
    exit;
}

// Simpan data ke database
try {
    $conn->beginTransaction();
    
    $insert_sql = "INSERT INTO payment_evidences 
                   (contract_id, umkm_user_id, bank_name, account_number, account_name, 
                    payment_amount, payment_date, evidence_file, notes, status) 
                   VALUES (:contract_id, :umkm_user_id, :bank_name, :account_number, :account_name, 
                           :payment_amount, :payment_date, :evidence_file, :notes, 'pending')";
    
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->execute([
        ':contract_id' => $contract_id,
        ':umkm_user_id' => $_SESSION['user_id'],
        ':bank_name' => $bank_name,
        ':account_number' => $account_number,
        ':account_name' => $account_name,
        ':payment_amount' => $payment_amount,
        ':payment_date' => $payment_date,
        ':evidence_file' => $target_path,
        ':notes' => $notes
    ]);
    
    // Create notification for admin
    require_once 'notificationsmanager.php';
    $notificationManager = new NotificationManager();
    
    // Get all admin users
    $admin_sql = "SELECT id FROM users WHERE user_type = 'admin'";
    $admin_stmt = $conn->query($admin_sql);
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($admins as $admin) {
        $notificationManager->createNotification(
            $admin['id'],
            "Bukti Pembayaran Baru",
            "UMKM telah mengupload bukti pembayaran untuk kontrak: {$contract['title']}",
            'payment',
            'contract',
            $contract_id
        );
    }
    
    // Notify creative worker
    $notificationManager->createNotification(
        $contract['creative_user_id'],
        "Bukti Pembayaran Diupload",
        "UMKM telah mengupload bukti pembayaran untuk kontrak: {$contract['title']}. Menunggu verifikasi admin.",
        'payment',
        'contract',
        $contract_id
    );
    
    $conn->commit();
    
    $_SESSION['success_message'] = "Bukti pembayaran berhasil diupload! Admin akan memverifikasi dalam 1x24 jam.";
    
} catch (Exception $e) {
    $conn->rollBack();
    // Hapus file jika gagal menyimpan ke database
    if (file_exists($target_path)) {
        unlink($target_path);
    }
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
}

header("Location: contract-details.php?id=" . $contract_id);
exit;
?>