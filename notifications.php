<?php
session_start();
require_once 'config/Database.php';
require_once 'notificationsmanager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$notificationManager = new NotificationManager();

// Mark all as read when visiting notifications page
$notificationManager->markAllAsRead($_SESSION['user_id']);

// Get all notifications
$notifications = $notificationManager->getUserNotifications($_SESSION['user_id'], 0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .notification-item {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .notification-type-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        .notification-type-project { background: rgba(62, 127, 213, 0.1); color: #3E7FD5; }
        .notification-type-proposal { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .notification-type-message { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .notification-type-system { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content" style="margin-top: 80px; padding: 2rem;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-1"><i class="fas fa-bell me-2"></i>Notifikasi</h1>
                            <p class="text-muted">Kelola semua notifikasi Anda di satu tempat</p>
                        </div>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali ke Dashboard
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body p-0">
                            <?php if (empty($notifications)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-bell-slash"></i>
                                    <h4>Tidak ada notifikasi</h4>
                                    <p class="text-muted">Anda belum memiliki notifikasi. Notifikasi akan muncul di sini ketika ada aktivitas baru.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="list-group-item notification-item">
                                            <div class="d-flex align-items-start">
                                                <div class="notification-type-icon notification-type-<?php echo $notification['notification_type']; ?>">
                                                    <i class="fas fa-<?php 
                                                        $icons = [
                                                            'project' => 'project-diagram',
                                                            'proposal' => 'file-alt',
                                                            'message' => 'envelope',
                                                            'payment' => 'money-bill-wave',
                                                            'system' => 'info-circle',
                                                            'contract' => 'file-contract'
                                                        ];
                                                        echo $icons[$notification['notification_type']] ?? 'bell';
                                                    ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 text-dark"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                    <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php 
                                                        $time = strtotime($notification['created_at']);
                                                        $now = time();
                                                        $diff = $now - $time;
                                                        
                                                        if ($diff < 60) {
                                                            echo 'Baru saja';
                                                        } elseif ($diff < 3600) {
                                                            echo floor($diff / 60) . ' menit lalu';
                                                        } elseif ($diff < 86400) {
                                                            echo floor($diff / 3600) . ' jam lalu';
                                                        } elseif ($diff < 2592000) {
                                                            echo floor($diff / 86400) . ' hari lalu';
                                                        } else {
                                                            echo date('d M Y H:i', $time);
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>