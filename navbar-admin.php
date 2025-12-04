<?php

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>
<!-- Navbar -->
<nav class="konekin-navbar navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand-konekin" href="dashboard-admin.php">
            <i class="fas fa-handshake"></i>
            Konekin Admin
        </a>

        <button class="navbar-toggler-custom d-lg-none" type="button" data-bs-toggle="collapse" 
                data-bs-target="#navbarContent" aria-controls="navbarContent" 
                aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>

        <div class="collapse navbar-collapse justify-content-between" id="navbarContent">
            <ul class="navbar-nav-konekin">
                <li class="nav-item-konekin">
                    <a class="nav-link-konekin" href="dashboard-admin.php">
                        <i class="fas fa-home me-1"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item-konekin">
                    <a class="nav-link-konekin" href="kelolauser.php">
                        <i class="fas fa-users me-1"></i>
                        <span>Kelola Pengguna</span>
                    </a>
                </li>
                <li class="nav-item-konekin">
                    <a class="nav-link-konekin" href="admin-projects.php">
                        <i class="fas fa-project-diagram me-1"></i>
                        <span>Kelola Proyek</span>
                    </a>
                </li>
                <li class="nav-item-konekin">
                    <a class="nav-link-konekin" href="admin-payments.php">
                        <i class="fas fa-credit-card me-1"></i>
                        <span>Kelola Pembayaran</span>
                    </a>
                </li>
                <li class="nav-item-konekin">
                    <a class="nav-link-konekin" href="admin-reports.php">
                        <i class="fas fa-chart-bar me-1"></i>
                        <span>Laporan</span>
                    </a>
                </li>
            </ul>
            <div class="user-section">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-info">
                    <a href="admin-profile.php" style="text-decoration: none; color: inherit;">
                        <h6 class="user-name"><?php echo $_SESSION['full_name'] ?? 'Admin'; ?></h6>
                    </a>
                    <small class="user-type">Administrator</small>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</nav>