<?php
// Path correction - go up one level from views folder
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Users.php';

$database = new DatabaseConnection();
$db = $database->getConnection();
$userModel = new User($db);

// PERBAIKAN: Ganti read() dengan getAll()
$users = $userModel->getAll(1000, 0); // Ambil semua user tanpa limit
$total_users = count($users);

$creative_workers = 0;
$umkm_users = 0;

foreach ($users as $user) {
    if ($user['user_type'] === 'creative_worker') {
        $creative_workers++;
    } else if ($user['user_type'] === 'umkm') {
        $umkm_users++;
    }
}
?>

<!-- Sisanya tetap sama -->