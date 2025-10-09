<?php
require_once __DIR__ . 'config/Database.php';
require_once __DIR__ . 'models/Users.php';

$database = new Database();
$db = $database->getConnection();
$userModel = new User($db);


$users = $userModel->read();
$total_users = count($users);

$creative_workers = 0;
$umkm_users = 0;

foreach ($users as $user) {
    if ($user['role'] === 'creative_worker') {
        $creative_workers++;
    } else if ($user['role'] === 'umkm') {
        $umkm_users++;
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-light glass-card mb-4">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-users-cog me-2"></i>User Management System
        </a>
        
        <div class="navbar-nav ms-auto">
            <a href="../index.php" class="btn btn-primary">
                <i class="fas fa-home me-1"></i>Dashboard
            </a>
        </div>
    </div>
</nav>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card glass-card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                <h3><?php echo $total_users; ?></h3>
                <p class="text-muted">Total Users</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card glass-card text-center">
            <div class="card-body">
                <i class="fas fa-palette fa-2x text-info mb-2"></i>
                <h3><?php echo $creative_workers; ?></h3>
                <p class="text-muted">Creative Workers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card glass-card text-center">
            <div class="card-body">
                <i class="fas fa-store fa-2x text-warning mb-2"></i>
                <h3><?php echo $umkm_users; ?></h3>
                <p class="text-muted">UMKM</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card glass-card text-center">
            <div class="card-body">
                <i class="fas fa-sync fa-2x text-success mb-2"></i>
                <h3><?php echo date('H:i'); ?></h3>
                <p class="text-muted">Last Updated</p>
            </div>
        </div>
    </div>
</div>