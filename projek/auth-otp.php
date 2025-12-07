<?php
session_start();
include "config/Database.php";

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$email = $_POST['email'] ?? '';
$otp_input = $_POST['otp'] ?? '';

$database = new DatabaseConnection();
$conn = $database->getConnection();

// --- ACTION: KIRIM OTP ---
if ($action === 'send_otp') {
    // Cek user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $otp = rand(100000, 999999); // Generate 6 digit
        // Expiry 5 menit dari sekarang
        $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Simpan ke DB
        $update = $conn->prepare("UPDATE users SET otp_code = :otp, otp_expiry = :exp WHERE email = :email");
        $update->execute([':otp' => $otp, ':exp' => $expiry, ':email' => $email]);

        // --- KIRIM EMAIL (Simulasi/Basic) ---
        $subject = "Kode Login KONEKIN Anda";
        $message = "Halo,\n\nKode OTP login Anda adalah: " . $otp . "\n\nKode ini berlaku selama 5 menit. Jangan berikan kepada siapapun.\n\nSalam,\nTim Konekin";
        $headers = "From: no-reply@konekin.id";

        // Logic kirim email (Gunakan PHPMailer di production)
        // mail($email, $subject, $message, $headers); 
        
        // UNTUK DEMO/DEVELOPMENT: Kita kembalikan OTP di response console log
        // (Hapus baris 'otp_debug' saat production!)
        echo json_encode(['success' => true, 'message' => 'OTP terkirim ke email Anda.', 'otp_debug' => $otp]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email tidak terdaftar.']);
    }
    exit;
}

// --- ACTION: VERIFIKASI OTP ---
if ($action === 'verify_otp') {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $now = date('Y-m-d H:i:s');
        
        if ($user['otp_code'] === $otp_input && $user['otp_expiry'] > $now) {
            // Login Berhasil
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_uuid'] = $user['uuid'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];

            // Bersihkan OTP
            $clean = $conn->prepare("UPDATE users SET otp_code = NULL WHERE id = :id");
            $clean->execute([':id' => $user['id']]);

            $redirect = ($user['user_type'] === 'umkm') ? 'dashboard-umkm.php' : 'dashboard-creative.php';
            echo json_encode(['success' => true, 'redirect' => $redirect]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Kode OTP salah atau sudah kadaluarsa.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
    }
    exit;
}
?>