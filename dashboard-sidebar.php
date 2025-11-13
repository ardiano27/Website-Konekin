<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konekin - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .konekin-navbar {
            background: linear-gradient(135deg, #3E7FD5 0%, #3E7FD5 100%);
            padding: 0.5rem 2rem; /* Mengurangi padding untuk navbar lebih tipis */
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand-konekin {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.3rem; /* Mengurangi ukuran font brand */
            margin-right: 2rem; /* Mengurangi margin */
        }

        .navbar-brand-konekin i {
            margin-right: 8px; /* Mengurangi margin ikon */
            font-size: 1.5rem; /* Mengurangi ukuran ikon brand */
        }

        .navbar-nav-konekin {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 1rem; /* Mengurangi gap antar item menu */
            align-items: center;
        }

        .nav-item-konekin {
            position: relative;
        }

        .nav-link-konekin {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px; /* Mengurangi gap antara ikon dan teks */
            padding: 0.5rem 1rem; /* Mengurangi padding untuk tampilan lebih rapi */
            border-radius: 20px; /* Sedikit mengurangi border-radius */
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.85rem; /* Mengurangi ukuran font menu */
        }

        .nav-link-konekin:hover,
        .nav-link-konekin.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px); /* Mengurangi efek transform */
        }

        .nav-link-konekin i {
            font-size: 0.9rem; /* Mengurangi ukuran ikon menu */
        }

        /* Search Bar Styles */
        .search-container {
            position: relative;
            margin: 0 1.5rem;
            flex: 0 1 280px; /* Sedikit mengurangi lebar search bar */
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.2rem; /* Mengurangi padding */
            border-radius: 20px; /* Sedikit mengurangi border-radius */
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-size: 0.85rem; /* Mengurangi ukuran font */
            transition: all 0.3s ease;
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .search-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
        }

        .search-icon {
            position: absolute;
            left: 10px; /* Mengurangi posisi kiri */
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem; /* Mengurangi ukuran ikon search */
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 0.8rem; /* Mengurangi gap */
            margin-left: 1.5rem; /* Mengurangi margin */
        }

        .user-avatar {
            width: 38px; /* Mengurangi ukuran avatar */
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .user-avatar i {
            font-size: 0.9rem; /* Mengurangi ukuran ikon user */
        }

        .user-info {
            display: flex;
            flex-direction: column;
            color: white;
        }

        .user-name {
            margin: 0;
            font-weight: 600;
            font-size: 0.85rem; /* Mengurangi ukuran font nama user */
        }

        .user-type {
            font-size: 0.75rem; /* Mengurangi ukuran font tipe user */
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.4rem 0.8rem; /* Mengurangi padding tombol logout */
            border-radius: 18px; /* Sedikit mengurangi border-radius */
            transition: all 0.3s ease;
            margin-left: 0.8rem; /* Mengurangi margin */
            font-size: 0.85rem; /* Mengurangi ukuran ikon logout */
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px); /* Mengurangi efek transform */
        }

        .navbar-toggler-custom {
            border: none;
            color: white;
            font-size: 1.3rem; /* Mengurangi ukuran toggler */
            padding: 0.4rem; /* Mengurangi padding */
        }

        /* Badge untuk notifikasi */
        .nav-badge {
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 18px; /* Mengurangi ukuran badge */
            height: 18px;
            font-size: 0.65rem; /* Mengurangi ukuran font badge */
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -4px; /* Menyesuaikan posisi */
            right: -4px;
        }

        /* Mobile Styles */
        @media (max-width: 991.98px) {
            .konekin-navbar {
                padding: 0.5rem 1rem; /* Mengurangi padding untuk mobile */
            }
            .navbar-brand-konekin {
                margin-right: 0.8rem; /* Sesuaikan untuk mobile */
                font-size: 1.2rem; /* Mengurangi ukuran font brand di mobile */
            }
            .navbar-brand-konekin i {
                font-size: 1.4rem; /* Mengurangi ukuran ikon brand di mobile */
            }
            .navbar-collapse-konekin {
                position: fixed;
                top: 60px; /* Menyesuaikan tinggi navbar yang lebih tipis */
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 0.8rem; /* Mengurangi padding */
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                z-index: 999;
            }

            .navbar-nav-konekin {
                flex-direction: column;
                gap: 0.4rem; /* Mengurangi gap antar item menu di mobile */
            }

            .nav-link-konekin {
                padding: 0.8rem; /* Mengurangi padding di mobile */
                border-radius: 8px; /* Sedikit mengurangi border-radius */
                justify-content: flex-start;
                font-size: 0.9rem; /* Menyesuaikan ukuran font di mobile */
            }

            .nav-link-konekin i {
                font-size: 0.9rem; /* Menyesuaikan ukuran ikon di mobile */
            }

            .search-container {
                margin: 0.8rem 0; /* Mengurangi margin */
                flex: 1;
                order: -1;
            }

            .search-input {
                padding: 0.5rem 1rem 0.5rem 2.2rem; /* Mengurangi padding di mobile */
                font-size: 0.9rem; /* Menyesuaikan ukuran font di mobile */
            }

            .search-icon {
                font-size: 0.9rem; /* Menyesuaikan ukuran ikon search di mobile */
            }

            .user-section {
                margin-left: 0;
                margin-top: 0.8rem; /* Mengurangi margin */
                justify-content: center;
                flex-direction: column;
                text-align: center;
            }

            .user-avatar {
                width: 36px; /* Mengurangi ukuran avatar di mobile */
                height: 36px;
            }

            .user-name {
                font-size: 0.9rem; /* Menyesuaikan ukuran font nama user di mobile */
            }

            .user-type {
                font-size: 0.8rem; /* Menyesuaikan ukuran font tipe user di mobile */
            }

            .logout-btn {
                margin-left: 0;
                margin-top: 0.4rem; /* Mengurangi margin */
                width: 100%;
                font-size: 0.9rem; /* Menyesuaikan ukuran ikon logout di mobile */
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
   <nav class="konekin-navbar navbar navbar-expand-lg">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand-konekin" href="dashboard.php">
            <i class="fas fa-handshake"></i>
            Konekin
        </a>

        <!-- Search Bar -->
     
        <!-- Toggler untuk mobile -->
        <button class="navbar-toggler-custom d-lg-none" type="button" data-bs-toggle="collapse" 
                data-bs-target="#navbarContent" aria-controls="navbarContent" 
                aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navbar Content -->
        <div class="collapse navbar-collapse justify-content-between" id="navbarContent">
            <!-- Menu Navigasi -->
            <ul class="navbar-nav-konekin">
                <li class="nav-item-konekin">
                    <a class="nav-link-konekin" href="<?php echo $_SESSION['user_type'] === 'umkm' ? 'dashboard-umkm.php' : 'dashboard-creative.php'; ?>">
                        <i class="fas fa-home me-1"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <?php if ($_SESSION['user_type'] === 'umkm'): ?>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="projects.php">
                            <i class="fas fa-project-diagram me-1"></i>
                            <span>Proyek Saya</span>
                        </a>
                    </li>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="create-project.php">
                            <i class="fas fa-plus-circle me-1"></i>
                            <span>Buat Proyek</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="find-projects.php">
                            <i class="fas fa-search me-1"></i>
                            <span>Cari Proyek</span>
                        </a>
                    </li>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="my-proposals.php">
                            <i class="fas fa-file-alt me-1"></i>
                            <span>Proposal Saya</span>
                        </a>
                    </li>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="portfolio.php">
                            <i class="fas fa-briefcase me-1"></i>
                            <span>Portfolio</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <li class="nav-item-konekin">
                    <a class="nav-link-konekin" href="messages.php">
                        <i class="fas fa-envelope me-1"></i>
                        <span>Pesan</span>
                    </a>
                </li>
            </ul>
          <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <?php
            // Menentukan placeholder berdasarkan tipe user
            $searchPlaceholder = "Cari...";
            
            if ($_SESSION['user_type'] === 'creative_worker') {
                $searchPlaceholder = "Cari proyek...";
            } elseif ($_SESSION['user_type'] === 'umkm') {
                $searchPlaceholder = "Cari kreator atau freelancer...";
            } elseif ($_SESSION['user_type'] === 'admin') {
                $searchPlaceholder = "Cari pengguna atau proyek...";
            }
            ?>
            <input type="text" class="search-input" placeholder="<?php echo $searchPlaceholder; ?>">
        </div>

            <!-- User Section -->
            <div class="user-section">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-info">
                    <a href="creative-profile.php" style="text-decoration: none; color: inherit;">
                        <h6 class="user-name"><?php echo $_SESSION['full_name'] ?? 'User'; ?></h6>
                    </a>
                    <small class="user-type">
                        <?php 
                        $roleMap = [
                            'admin' => 'Administrator',
                            'umkm' => 'UMKM/Bisnis', 
                            'creative_worker' => 'Creative Worker'
                        ];
                        echo $roleMap[$_SESSION['user_type']] ?? 'User';
                        ?>
                    </small>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Konten dashboard Anda di sini -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script untuk navbar aktif
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link-konekin');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
                
                link.addEventListener('click', function() {
                    navLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            // Script untuk search functionality
            const searchInput = document.querySelector('.search-input');
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const searchTerm = this.value.trim();
                    if (searchTerm) {
                        // Redirect ke halaman pencarian dengan parameter query
                        window.location.href = `search.php?q=${encodeURIComponent(searchTerm)}`;
                    }
                }
            });
        });
    </script>
</body>
</html>