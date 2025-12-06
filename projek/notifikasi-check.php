<?php
session_start();
require_once 'config/Database.php';
require_once 'notificationsmanager.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['has_new' => false]);
    exit();
}

$notificationManager = new NotificationManager();
$db = $notificationManager->getDbConnection(); // Gunakan getter
$lastCheck = $_GET['last_check'] ?? time() - 300;

try {
    $sql = "SELECT COUNT(*) as count FROM notifications 
            WHERE user_id = :user_id 
            AND is_read = 0 
            AND created_at > FROM_UNIXTIME(:last_check)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':last_check' => $lastCheck
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasNew = ($result['count'] ?? 0) > 0;
    
    header('Content-Type: application/json');
    echo json_encode([
        'has_new' => $hasNew,
        'timestamp' => time(),
        'count' => $result['count'] ?? 0
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'has_new' => false, 
        'error' => $e->getMessage(),
        'timestamp' => time()
    ]);
}
?>