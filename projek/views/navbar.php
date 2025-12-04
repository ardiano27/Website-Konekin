<?php
require_once __DIR__ . '/../config/Database.php'; 
require_once __DIR__ . '/../models/Users.php';

// Cek jika variabel $current_user sudah diset di file pemanggil (misal messages.php)
if (!isset($current_user)) {
    // Jika tidak diset, ambil dari session
    if (isset($_SESSION['user_id'])) {
        try {
            // Kita gunakan model User untuk mendapatkan data user saat ini
            $userModel = new User();
            $current_user = $userModel->getById($_SESSION['user_id']);
        } catch (Exception $e) {
            // Error handling
            $current_user = null;
        }
    }
}

// Logic yang Anda berikan untuk menghitung user
$total_users = 0;
$creative_workers = 0;
$umkm_users = 0;

try {
    $userModel = new User();
    $all_users = $userModel->getAll(9999, 0); // Ambil semua user (maks 9999)
    $total_users = count($all_users);
    
    foreach ($all_users as $user) {
        if (isset($user['user_type'])) {
            if ($user['user_type'] === 'creative_worker') {
                $creative_workers++;
            } else if ($user['user_type'] === 'umkm') {
                $umkm_users++;
            }
        }
    }
} catch (Exception $e) {
    // Fail silently in navbar if DB fails
}

?>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Konekin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#">Home</a>
                </li>
                <li class="nav-item">
                    <span class="nav-link text-muted">Total Users: <?php echo $total_users; ?> | Creative: <?php echo $creative_workers; ?> | UMKM: <?php echo $umkm_users; ?></span>
                </li>
                </ul>
            <ul class="navbar-nav">
                <?php if (isset($current_user)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Halo, <?php echo htmlspecialchars($current_user['full_name'] ?? $current_user['email'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="messages.php">Chat</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>