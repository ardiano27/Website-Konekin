<?php
session_start();
include "config/Database.php";

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id_token = $data['credential'] ?? '';

if (!$id_token) {
    echo json_encode(['success' => false, 'message' => 'Token tidak ditemukan']);
    exit;
}

// 1. Verifikasi Token ke Google Server (Secure)
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$payload = json_decode($response, true);

if (isset($payload['error_description']) || !isset($payload['email'])) {
    echo json_encode(['success' => false, 'message' => 'Token Google tidak valid']);
    exit;
}

$google_id = $payload['sub'];
$email = $payload['email'];
$name = $payload['name'];
$picture = $payload['picture'];

// 2. Koneksi Database
$database = new DatabaseConnection();
$conn = $database->getConnection();

// 3. Cek apakah user sudah ada berdasarkan email atau google_id
$sql = "SELECT * FROM users WHERE email = :email OR google_id = :google_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':email', $email);
$stmt->bindParam(':google_id', $google_id);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    // User ADA: Login
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update google_id jika belum ada (linking account)
    if (empty($user['google_id'])) {
        $upd = $conn->prepare("UPDATE users SET google_id = :gid WHERE id = :id");
        $upd->execute([':gid' => $google_id, ':id' => $user['id']]);
    }
    
    // Set Session (Sama seperti login.php biasa)
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_uuid'] = $user['uuid'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['avatar_url'] = $picture; // Pakai foto google jika login via google
    
    echo json_encode(['success' => true, 'redirect' => ($user['user_type'] === 'umkm' ? 'dashboard-umkm.php' : 'dashboard-creative.php')]);

} else {
    // User BELUM ADA: Redirect ke halaman registrasi khusus atau buat otomatis
    // Di sini kita arahkan ke register choice karena kita butuh tahu dia UMKM atau Creative
    // Kita simpan info google di session sementara
    $_SESSION['google_data'] = [
        'google_id' => $google_id,
        'email' => $email,
        'name' => $name,
        'picture' => $picture
    ];
    
    echo json_encode(['success' => true, 'redirect' => 'register-choice.php?from=google']);
}
?>