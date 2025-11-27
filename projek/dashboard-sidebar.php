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
            padding: 0.5rem 2rem;
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
            font-size: 1.3rem;
            margin-right: 2rem;
        }

        .navbar-brand-konekin i {
            margin-right: 8px;
            font-size: 1.5rem;
        }

        .navbar-nav-konekin {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 1rem;
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
            gap: 6px;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .nav-link-konekin:hover,
        .nav-link-konekin.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
        }

        .nav-link-konekin i {
            font-size: 0.9rem;
        }

        /* Search Bar Styles */
        .search-container {
            position: relative;
            margin: 0 1.5rem;
            flex: 0 1 280px;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.2rem;
            border-radius: 20px;
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-size: 0.85rem;
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
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-left: 1.5rem;
        }

        .user-avatar {
            width: 38px;
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
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            color: white;
        }

        .user-name {
            margin: 0;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .user-type {
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 18px;
            transition: all 0.3s ease;
            margin-left: 0.8rem;
            font-size: 0.85rem;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .navbar-toggler-custom {
            border: none;
            color: white;
            font-size: 1.3rem;
            padding: 0.4rem;
        }

        /* Notification Bell Styles */
        .notification-bell {
            position: relative;
            margin-right: 0.8rem;
        }

        .notification-icon {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .notification-icon:hover {
            color: white;
            background: rgba(255, 255, 255, 0.15);
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .notification-dropdown {
            min-width: 350px;
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background-color: #f0f7ff;
            border-left: 3px solid #3E7FD5;
        }

        .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .notification-message {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #adb5bd;
        }

        .notification-type-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .notification-type-project {
            background: rgba(62, 127, 213, 0.1);
            color: #3E7FD5;
        }

        .notification-type-message {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .notification-type-payment {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .notification-type-system {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        /* Badge untuk notifikasi */
        .nav-badge {
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -4px;
            right: -4px;
        }

        /* Mobile Styles */
        @media (max-width: 991.98px) {
            .konekin-navbar {
                padding: 0.5rem 1rem;
            }
            .navbar-brand-konekin {
                margin-right: 0.8rem;
                font-size: 1.2rem;
            }
            .navbar-brand-konekin i {
                font-size: 1.4rem;
            }
            .navbar-collapse-konekin {
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 0.8rem;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                z-index: 999;
            }

            .navbar-nav-konekin {
                flex-direction: column;
                gap: 0.4rem;
            }

            .nav-link-konekin {
                padding: 0.8rem;
                border-radius: 8px;
                justify-content: flex-start;
                font-size: 0.9rem;
            }

            .nav-link-konekin i {
                font-size: 0.9rem;
            }

            .search-container {
                margin: 0.8rem 0;
                flex: 1;
                order: -1;
            }

            .search-input {
                padding: 0.5rem 1rem 0.5rem 2.2rem;
                font-size: 0.9rem;
            }

            .search-icon {
                font-size: 0.9rem;
            }

            .user-section {
                margin-left: 0;
                margin-top: 0.8rem;
                justify-content: center;
                flex-direction: column;
                text-align: center;
            }

            .notification-bell {
                margin-right: 0;
                margin-bottom: 0.8rem;
            }

            .notification-dropdown {
                min-width: 300px;
                max-height: 350px;
            }

            .user-avatar {
                width: 36px;
                height: 36px;
            }

            .user-name {
                font-size: 0.9rem;
            }

            .user-type {
                font-size: 0.8rem;
            }

            .logout-btn {
                margin-left: 0;
                margin-top: 0.4rem;
                width: 100%;
                font-size: 0.9rem;
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
                            <a class="nav-link-konekin" href="projects.php">
                                <i class="fas fa-tasks me-1"></i>
                                <span>Proyek Saya</span>
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
                    
                    if ($_SESSION['user_type'] === 'creative') {
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
                    <!-- Notification Bell -->
                    <div class="notification-bell dropdown">
                        <div class="notification-icon dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge">3</span>
                        </div>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <div class="dropdown-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Notifikasi</h6>
                                <a href="notifications.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                            </div>
                            <div class="dropdown-divider"></div>
                            
                            <!-- Notification Items -->
                            <div class="notification-item unread">
                                <div class="d-flex align-items-start">
                                    <div class="notification-type-icon notification-type-project">
                                        <i class="fas fa-project-diagram"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="notification-title">Proyek Disetujui</div>
                                        <div class="notification-message">Proyek "Website UMKM" telah disetujui dan siap dikerjakan</div>
                                        <div class="notification-time">2 menit lalu</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="notification-item unread">
                                <div class="d-flex align-items-start">
                                    <div class="notification-type-icon notification-type-message">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="notification-title">Pesan Baru</div>
                                        <div class="notification-message">Anda mendapat pesan baru dari UMKM Sejahtera</div>
                                        <div class="notification-time">1 jam lalu</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="notification-item">
                                <div class="d-flex align-items-start">
                                    <div class="notification-type-icon notification-type-payment">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="notification-title">Pembayaran Diterima</div>
                                        <div class="notification-message">Pembayaran untuk proyek "Desain Logo" telah diterima</div>
                                        <div class="notification-time">3 jam lalu</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="notification-item">
                                <div class="d-flex align-items-start">
                                    <div class="notification-type-icon notification-type-system">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="notification-title">Update Sistem</div>
                                        <div class="notification-message">Fitur progress tracking telah tersedia</div>
                                        <div class="notification-time">1 hari lalu</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <a href="<?php echo $_SESSION['user_type'] === 'creative' ? 'creative-profile.php' : 'umkm-profile.php'; ?>" style="text-decoration: none; color: inherit;">
                            <h6 class="user-name"><?php echo $_SESSION['full_name'] ?? 'User'; ?></h6>
                        </a>
                        <small class="user-type">
                            <?php 
                            $roleMap = [
                                'admin' => 'Administrator',
                                'umkm' => 'UMKM/Bisnis', 
                                'creative' => 'Creative Worker'
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

            // Script untuk notifikasi
            const notificationItems = document.querySelectorAll('.notification-item');
            notificationItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove unread class when clicked
                    this.classList.remove('unread');
                    
                    // Update notification badge count
                    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
                    const badge = document.querySelector('.notification-badge');
                    badge.textContent = unreadCount;
                    
                    if (unreadCount === 0) {
                        badge.style.display = 'none';
                    }
                });
            });

            // Mark all as read functionality
            const markAllAsRead = document.querySelector('.mark-all-read');
            if (markAllAsRead) {
                markAllAsRead.addEventListener('click', function(e) {
                    e.preventDefault();
                    notificationItems.forEach(item => {
                        item.classList.remove('unread');
                    });
                    const badge = document.querySelector('.notification-badge');
                    badge.textContent = '0';
                    badge.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>