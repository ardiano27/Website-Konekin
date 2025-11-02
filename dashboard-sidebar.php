<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="assets/css/dashboard-sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0 sidebar-logo">
                <button class="sidebar-toggle btn btn-primary" id="sidebarToggle">
                    <i class="fas fa-bars" id="toggleIcon"></i>
                </button>
                <i class="fas fa-handshake me-2"></i>
                <span class="logo-text">Konekin</span>
            </h3>
            <button class="btn btn-close btn-close-white d-md-none" id="sidebarClose"></button>
        </div>
        
        <div class="text-center mb-4 sidebar-user-info">
            <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2 user-avatar" 
                 style="width: 60px; height: 60px;">
                <i class="fas fa-user text-primary" style="font-size: 1.5rem;"></i>
            </div>
            <a href="creative-profile.php" style="text-decoration: none; color: white;">
                <h6 class="mb-1 user-name"><?php echo $_SESSION['full_name']; ?></h6>
            </a>

            <small class="text-light user-type">
                <?php 
                echo $_SESSION['user_type'] === 'umkm' ? 'UMKM/Bisnis' : 'Creative Worker';
                ?>
            </small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php" data-tooltip="Dashboard">
                    <i class="fas fa-home me-2"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <?php if ($_SESSION['user_type'] === 'umkm'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="projects.php" data-tooltip="Proyek Saya">
                        <i class="fas fa-briefcase me-2"></i>
                        <span class="nav-text">Proyek Saya</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="create-project.php" data-tooltip="Buat Proyek">
                        <i class="fas fa-plus-circle me-2"></i>
                        <span class="nav-text">Buat Proyek</span>
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="find-projects.php" data-tooltip="Cari Proyek">
                        <i class="fas fa-search me-2"></i>
                        <span class="nav-text">Cari Proyek</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my-proposals.php" data-tooltip="Proposal Saya">
                        <i class="fas fa-file-alt me-2"></i>
                        <span class="nav-text">Proposal Saya</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="portfolio.php" data-tooltip="Portfolio">
                        <i class="fas fa-images me-2"></i>
                        <span class="nav-text">Portfolio</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link" href="messages.php" data-tooltip="Pesan">
                    <i class="fas fa-envelope me-2"></i>
                    <span class="nav-text">Pesan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php" data-tooltip="Logout">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script src="dashboard-sidebar.js"></script>
</body>
</html>