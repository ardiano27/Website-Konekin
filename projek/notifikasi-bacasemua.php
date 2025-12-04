<?php
session_start();
require_once 'config/Database.php';
require_once 'notificationsmanager.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$notificationManager = new NotificationManager();
$success = $notificationManager->markAllAsRead($_SESSION['user_id']);

header('Content-Type: application/json');
echo json_encode(['success' => $success]);

?>