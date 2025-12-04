<?php
require_once 'notificationsmanager.php';

class NotificationHelper {
    public static function getNotificationIcon($type) {
        $icons = [
            'project' => 'project-diagram',
            'proposal' => 'file-alt',
            'message' => 'envelope',
            'payment' => 'money-bill-wave',
            'system' => 'info-circle',
            'contract' => 'file-contract'
        ];
        return $icons[$type] ?? 'bell';
    }

    public static function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Baru saja';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' menit lalu';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' jam lalu';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . ' hari lalu';
        } else {
            return date('d M Y', $time);
        }
    }
}

// Inisialisasi Manager
$notificationManager = new NotificationManager();
$helper = new NotificationHelper();

// Get unread notifications count
$unread_count = $notificationManager->getUnreadCount($_SESSION['user_id']);

// Get recent notifications
$recent_notifications = $notificationManager->getUserNotifications($_SESSION['user_id'], 5);

// Ambil avatar_url dari database
$user_id = $_SESSION['user_id'];
$avatar_url = '';
$has_avatar = false;

// Cek apakah avatar sudah ada di session
if (!isset($_SESSION['user_avatar'])) {
    try {
        // Koneksi database (sesuaikan dengan kredensial Anda)
        $host = "localhost";
        $dbname = "konekin";
        $username = "root";
        $password = "";
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && !empty($user['avatar_url'])) {
            $_SESSION['user_avatar'] = $user['avatar_url'];
        } else {
            $_SESSION['user_avatar'] = '';
        }
    } catch (Exception $e) {
        error_log("Error getting user avatar: " . $e->getMessage());
        $_SESSION['user_avatar'] = '';
    }
}

$avatar_url = $_SESSION['user_avatar'] ?? '';
// Periksa apakah file avatar ada
if (!empty($avatar_url)) {
    // Coba beberapa lokasi yang mungkin
    $possible_paths = [
        $avatar_url, // Path asli dari database
        __DIR__ . '/' . $avatar_url, // Path relatif dari direktori file ini
        $_SERVER['DOCUMENT_ROOT'] . '/Website-Konekin/projek/' . $avatar_url, // Path absolut dari root
        str_replace('assets/', '', $avatar_url), // Hapus prefix assets jika ada
        str_replace('../', '', $avatar_url), // Hapus parent directory
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $has_avatar = true;
            $avatar_url = $path; // Gunakan path yang valid
            break;
        }
    }
}
?>

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
            padding: 0.5rem 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            font-size: 1.2rem;
            margin-right: 1.5rem;
        }

        .navbar-brand-konekin i {
            margin-right: 8px;
            font-size: 1.3rem;
        }

        .navbar-nav-konekin {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 0.75rem;
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
            padding: 0.5rem 0.9rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .nav-link-konekin:hover,
        .nav-link-konekin.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
        }

        .nav-link-konekin i {
            font-size: 0.85rem;
        }

        /* Search Bar Styles */
        .search-container {
            position: relative;
            margin: 0 1rem;
            flex: 0 1 260px;
        }

        .search-input {
            width: 100%;
            padding: 0.45rem 1rem 0.45rem 2.2rem;
            border-radius: 20px;
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-size: 0.82rem;
            transition: all 0.3s ease;
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.82rem;
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
            margin-left: 1rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .user-avatar i {
            font-size: 0.85rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            color: white;
        }

        .user-name {
            margin: 0;
            font-weight: 600;
            font-size: 0.82rem;
            line-height: 1.2;
        }

        .user-type {
            font-size: 0.72rem;
            opacity: 0.9;
            line-height: 1.2;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.35rem 0.7rem;
            border-radius: 18px;
            transition: all 0.3s ease;
            margin-left: 0.5rem;
            font-size: 0.82rem;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .navbar-toggler-custom {
            border: none;
            color: white;
            font-size: 1.2rem;
            padding: 0.35rem;
        }

        /* Notification Bell Styles */
        .notification-bell {
            position: relative;
            margin-right: 0.7rem;
        }

        .notification-icon {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            padding: 0.45rem;
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
            width: 16px;
            height: 16px;
            font-size: 0.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .notification-dropdown {
            min-width: 360px;
            max-height: 480px;
            overflow-y: auto;
            font-size: 0.9rem;
        }

        .notification-item {
            padding: 0.7rem 0.9rem;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
            cursor: pointer;
            border-left: 3px solid transparent;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background-color: #f0f7ff;
            border-left-color: #3E7FD5;
        }

        .notification-title {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
            color: #2c3e50;
        }

        .notification-message {
            font-size: 0.78rem;
            color: #6c757d;
            margin-bottom: 0.2rem;
            line-height: 1.3;
        }

        .notification-time {
            font-size: 0.72rem;
            color: #adb5bd;
        }

        .notification-type-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.7rem;
            flex-shrink: 0;
            font-size: 0.85rem;
        }

        .notification-type-project {
            background: rgba(62, 127, 213, 0.1);
            color: #3E7FD5;
        }

        .notification-type-proposal {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .notification-type-message {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .notification-type-payment {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .notification-type-system {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .notification-type-contract {
            background: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
        }

        .empty-notifications {
            padding: 1.5rem 1rem;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .empty-notifications i {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }

        /* Mobile Styles */
        @media (max-width: 991.98px) {
            .konekin-navbar {
                padding: 0.4rem 1rem;
            }
            .navbar-brand-konekin {
                margin-right: 0.5rem;
                font-size: 1.1rem;
            }
            .navbar-brand-konekin i {
                font-size: 1.2rem;
            }
            .navbar-collapse-konekin {
                position: fixed;
                top: 55px;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #3E7FD5 0%, #3E7FD5 100%);
                padding: 0.7rem;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                z-index: 999;
            }

            .navbar-nav-konekin {
                flex-direction: column;
                gap: 0.3rem;
            }

            .nav-link-konekin {
                padding: 0.7rem;
                border-radius: 8px;
                justify-content: flex-start;
                font-size: 0.85rem;
            }

            .nav-link-konekin i {
                font-size: 0.85rem;
            }

            .search-container {
                margin: 0.7rem 0;
                flex: 1;
                order: -1;
            }

            .search-input {
                padding: 0.45rem 1rem 0.45rem 2.2rem;
                font-size: 0.85rem;
            }

            .search-icon {
                font-size: 0.85rem;
            }

            .user-section {
                margin-left: 0;
                margin-top: 0.7rem;
                justify-content: center;
                flex-direction: column;
                text-align: center;
            }

            .notification-bell {
                margin-right: 0;
                margin-bottom: 0.7rem;
            }

            .notification-dropdown {
                min-width: 280px;
                max-height: 380px;
            }

            .user-avatar {
                width: 34px;
                height: 34px;
            }

            .user-name {
                font-size: 0.85rem;
            }

            .user-type {
                font-size: 0.75rem;
            }

            .logout-btn {
                margin-left: 0;
                margin-top: 0.3rem;
                width: 100%;
                font-size: 0.85rem;
            }
        }

        /* Additional improvements for better spacing */
        .dropdown-header {
            padding: 0.7rem 0.9rem;
        }
        
        .dropdown-header h6 {
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .dropdown-header .btn {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="konekin-navbar navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand-konekin" href="dashboard.php">
                <i class="fas fa-handshake"></i>
                Konekin
            </a>

            <button class="navbar-toggler-custom d-lg-none" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarContent" aria-controls="navbarContent" 
                    aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <div class="collapse navbar-collapse justify-content-between" id="navbarContent">
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
                            <a class="nav-link-konekin" href="all-projects.php">
                                <i class="fas fa-tasks me-1"></i>
                                <span>Proyek Saya</span>
                            </a>
                        </li>
                        <li class="nav-item-konekin">
                            <a class="nav-link-konekin" href="saved-projects.php">
                                <i class="fas fa-bookmark me-1"></i>
                                <span>Proyek Disimpan</span>
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

                <div class="user-section">
                    <div class="notification-bell dropdown">
                        <div class="notification-icon dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <div class="dropdown-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Notifikasi</h6>
                                <div>
                                    <a href="notifications.php" class="btn btn-sm btn-outline-primary me-1">Lihat Semua</a>
                                    <?php if ($unread_count > 0): ?>
                                        <button class="btn btn-sm btn-outline-secondary mark-all-read">
                                            Tandai Dibaca
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            
                            <?php if (empty($recent_notifications)): ?>
                                <div class="empty-notifications">
                                    <i class="fas fa-bell-slash"></i>
                                    <p class="mb-0">Tidak ada notifikasi</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_notifications as $notification): ?>
                                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                                         data-notification-id="<?php echo $notification['id']; ?>">
                                        <div class="d-flex align-items-start">
                                            <div class="notification-type-icon notification-type-<?php echo $notification['notification_type']; ?>">
                                                <i class="fas fa-<?php echo $helper->getNotificationIcon($notification['notification_type']); ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                                <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                                <div class="notification-time">
                                                    <?php echo $helper->timeAgo($notification['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="user-avatar">
                        <?php if ($has_avatar): ?>
                            <img src="<?php echo htmlspecialchars($avatar_url); ?>" 
                                 alt="<?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>"
                                 onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\"fas fa-user\"></i>';">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
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

    <div class="main-content">
        <!-- Konten akan dimasukkan di sini oleh halaman yang include file ini -->
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

            // Script untuk notifikasi - mark as read ketika diklik
            const notificationItems = document.querySelectorAll('.notification-item');
            notificationItems.forEach(item => {
                item.addEventListener('click', function() {
                    const notificationId = this.getAttribute('data-notification-id');
                    markNotificationAsRead(notificationId, this);
                });
            });

            // Mark all as read functionality
            const markAllAsRead = document.querySelector('.mark-all-read');
            if (markAllAsRead) {
                markAllAsRead.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    fetch('notifikasi-bacasemua.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove unread class from all notifications
                            notificationItems.forEach(item => {
                                item.classList.remove('unread');
                            });
                            
                            // Update notification badge
                            const badge = document.querySelector('.notification-badge');
                            if (badge) {
                                badge.remove();
                            }
                            
                            // Hide mark all read button
                            markAllAsRead.remove();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                });
            }

            // Auto-refresh notifications every 30 seconds
            setInterval(refreshNotifications, 30000);
        });

        function markNotificationAsRead(notificationId, element) {
            fetch('notifikasi-baca.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove unread class
                    element.classList.remove('unread');
                    
                    // Update notification badge count
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent);
                        if (currentCount > 1) {
                            badge.textContent = currentCount - 1;
                        } else {
                            badge.remove();
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function refreshNotifications() {
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                // If there are unread notifications, refresh to check for new ones
                location.reload();
            }
        }
    </script>
</body>
</html>