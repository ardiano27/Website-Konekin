<?php
session_start();
require_once 'config/Database.php';
require_once 'notificationsmanager.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$notificationManager = new NotificationManager();
$notifications = $notificationManager->getUserNotifications($_SESSION['user_id'], 5);
$unread_count = $notificationManager->getUnreadCount($_SESSION['user_id']);
?>

<div class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
    <h6 class="mb-0 fw-semibold">
        <i class="fas fa-bell me-2"></i>Notifikasi
        <?php if ($unread_count > 0): ?>
            <span class="badge bg-primary ms-2"><?php echo $unread_count; ?> baru</span>
        <?php endif; ?>
    </h6>
    <div class="d-flex gap-1">
        <?php if ($unread_count > 0): ?>
            <button class="btn btn-sm btn-outline-primary mark-all-read">
                <i class="fas fa-check-double"></i> Tandai Semua
            </button>
        <?php endif; ?>
        <a href="notifications.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-list"></i> Lihat Semua
        </a>
    </div>
</div>

<div class="dropdown-body" style="max-height: 400px; overflow-y: auto;">
    <?php if (empty($notifications)): ?>
        <div class="text-center py-5">
            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">Tidak ada notifikasi</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
            <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?> 
                 p-3 border-bottom hover-effect" 
                 data-notification-id="<?php echo $notification['id']; ?>"
                 data-url="<?php echo $this->getNotificationUrl($notification); ?>"
                 role="button">
                
                <div class="d-flex gap-3">
                    <!-- Notification Icon -->
                    <div class="notification-icon-wrapper">
                        <div class="notification-icon-circle bg-<?php echo $notification['notification_type']; ?>">
                            <?php echo $notification['icon'] ?? '🔔'; ?>
                        </div>
                    </div>
                    
                    <!-- Notification Content -->
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="mb-0 fw-semibold text-dark">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </h6>
                            <?php if (!$notification['is_read']): ?>
                                <span class="badge bg-primary badge-dot"></span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="mb-1 text-muted small">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="far fa-clock me-1"></i>
                                <?php echo $this->timeAgo($notification['created_at']); ?>
                            </small>
                            
                            <?php if ($notification['related_entity_type']): ?>
                                <span class="badge bg-light text-dark small">
                                    <i class="fas fa-<?php echo $this->getEntityIcon($notification['related_entity_type']); ?> me-1"></i>
                                    <?php echo ucfirst($notification['related_entity_type']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.notification-icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.bg-project { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.bg-proposal { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
.bg-message { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
.bg-payment { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
.bg-contract { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
.bg-system { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }

.notification-item.unread {
    background: linear-gradient(90deg, rgba(62, 127, 213, 0.05) 0%, rgba(62, 127, 213, 0.02) 100%);
    border-left: 3px solid #3E7FD5;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.badge-dot {
    width: 8px;
    height: 8px;
    padding: 0;
    border-radius: 50%;
}

.hover-effect {
    transition: all 0.2s ease;
}

.hover-effect:hover {
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>

<?php
// Helper methods
function getNotificationUrl($notification) {
    $type = $notification['related_entity_type'];
    $id = $notification['related_entity_id'];
    
    $urls = [
        'project' => 'view-project.php?id=' . $id,
        'proposal' => 'view-proposal.php?id=' . $id,
        'contract' => 'view-contract.php?id=' . $id,
        'message' => 'messages.php',
        'payment' => 'payment-history.php?id=' . $id,
        'milestone' => 'milestones.php?contract=' . $id
    ];
    
    return $urls[$type] ?? 'notifications.php';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    return date('d M Y', $time);
}

function getEntityIcon($entity) {
    $icons = [
        'project' => 'project-diagram',
        'proposal' => 'file-alt',
        'contract' => 'file-contract',
        'message' => 'envelope',
        'payment' => 'credit-card',
        'milestone' => 'flag-checkered'
    ];
    return $icons[$entity] ?? 'link';
}
?>