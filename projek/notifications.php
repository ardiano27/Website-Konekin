<?php
session_start();
require_once 'config/Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new DatabaseConnection();
$db = $database->getConnection();

// Get all notifications for user
$notifications_query = "
    SELECT * FROM notifications 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC
";
$stmt = $db->prepare($notifications_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content" style="margin-top: 80px; padding: 2rem;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1>Notifikasi</h1>
                        <button class="btn btn-outline-secondary" id="markAllRead">
                            <i class="fas fa-check-double me-2"></i>Tandai Sudah Dibaca
                        </button>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($notifications)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                    <h5>Tidak ada notifikasi</h5>
                                    <p class="text-muted">Anda belum memiliki notifikasi</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex align-items-start">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-bell text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                    <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <small class="text-muted">
                                                        <?php echo date('d M Y H:i', strtotime($notification['created_at'])); ?>
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
    <script>
        document.getElementById('markAllRead').addEventListener('click', function() {
            // Implement mark all as read functionality
            alert('Semua notifikasi telah ditandai sebagai sudah dibaca');
        });
    </script>
</body>
</html>