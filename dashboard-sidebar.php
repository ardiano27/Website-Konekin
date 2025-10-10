<!-- Sidebar -->
 <!DOCTYPE html>
 <html lang="en">
 <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        :root {
            --primary-color: #2596be;
            --primary-dark: #1e7a9c;
        }
        
        .sidebar {
            background-color: var(--primary-color);
            color: white;
            min-height: 100vh;
            position: fixed;
            width: 250px;
        }
        
        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 5px;
        }
        
        .sidebar .nav-link:hover {
            background-color: var(--primary-dark);
        }
        
        .sidebar .nav-link.active {
            background-color: var(--primary-dark);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
        }
        
        .stat-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .card-body {
            padding: 25px;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
    </style>
 </head>
 <body>
    <div class="sidebar">
    <div class="p-4">
        <h3 class="text-center mb-4">
            <i class="fas fa-handshake me-2"></i>Konekin
        </h3>
        
        <div class="text-center mb-4">
            <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                 style="width: 60px; height: 60px;">
                <i class="fas fa-user text-primary" style="font-size: 1.5rem;"></i>
            </div>
            <h6 class="mb-1"><?php echo $_SESSION['full_name']; ?></h6>
            <small class="text-light">
                <?php 
                echo $_SESSION['user_type'] === 'umkm' ? 'UMKM/Bisnis' : 'Creative Worker';
                ?>
            </small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
            </li>
            
            <?php if ($_SESSION['user_type'] === 'umkm'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="projects.php">
                        <i class="fas fa-briefcase me-2"></i>Proyek Saya
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="create-project.php">
                        <i class="fas fa-plus-circle me-2"></i>Buat Proyek
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="find-projects.php">
                        <i class="fas fa-search me-2"></i>Cari Proyek
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my-proposals.php">
                        <i class="fas fa-file-alt me-2"></i>Proposal Saya
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="portfolio.php">
                        <i class="fas fa-images me-2"></i>Portfolio
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link" href="messages.php">
                    <i class="fas fa-envelope me-2"></i>Pesan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-user-edit me-2"></i>Edit Profil
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </li>
        </ul>
    </div>
</div>
</body>
</html>
