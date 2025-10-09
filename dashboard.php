<?php include "check_login.php"; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <i></i>Konekin
            </h3>
            
            <div class="text-center mb-4">
   
                <h6 class="mb-1"><?php echo $_SESSION['full_name']; ?></h6>
                <small class="text-light">
                    <?php 
                    echo $_SESSION['user_type'] === 'umkm' ? 'UMKM/Bisnis' : 'Creative Worker';
                    ?>
                </small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i></i>Dashboard
                    </a>
                </li>
                
                <?php if ($_SESSION['user_type'] === 'umkm'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="projects.php">
                            <i></i>Proyek Saya
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create-project.php">
                            <i></i>Buat Proyek
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="find-projects.php">
                            <i></i>Cari Proyek
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-proposals.php">
                            <i></i>Proposal Saya
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="portfolio.php">
                            <i></i>Portfolio
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="messages.php">
                        <i></i>Pesan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i></i>Edit Profil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dashboard</h2>
            <div class="d-flex align-items-center">
                <span class="me-3">Halo, <?php echo $_SESSION['full_name']; ?>!</span>
               
            </div>
        </div>


  

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>