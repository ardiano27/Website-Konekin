<!-- Sidebar -->
 <!DOCTYPE html>
 <html lang="en">
 <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="sidebar.css">
 </head>
 <body>
<!-- Sidebar -->
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
            <h6 class="mb-1 user-name"><?php echo $_SESSION['full_name']; ?></h6>
            <small class="text-light user-type">
                <?php 
                echo $_SESSION['user_type'] === 'umkm' ? 'UMKM/Bisnis' : 'Creative Worker';
                ?>
            </small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home me-2"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <?php if ($_SESSION['user_type'] === 'umkm'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="projects.php">
                        <i class="fas fa-briefcase me-2"></i>
                        <span class="nav-text">Proyek Saya</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="create-project.php">
                        <i class="fas fa-plus-circle me-2"></i>
                        <span class="nav-text">Buat Proyek</span>
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="find-projects.php">
                        <i class="fas fa-search me-2"></i>
                        <span class="nav-text">Cari Proyek</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my-proposals.php">
                        <i class="fas fa-file-alt me-2"></i>
                        <span class="nav-text">Proposal Saya</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="portfolio.php">
                        <i class="fas fa-images me-2"></i>
                        <span class="nav-text">Portfolio</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link" href="messages.php">
                    <i class="fas fa-envelope me-2"></i>
                    <span class="nav-text">Pesan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-user-edit me-2"></i>
                    <span class="nav-text">Edit Profil</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
            
        </ul>
    </div>
</div>

<!-- Overlay untuk mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Toggle Button untuk Mobile & Desktop -->

<script src="sidebar.js"></script>
</body>
</html>
