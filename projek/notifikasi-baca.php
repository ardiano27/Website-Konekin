<?php
session_start();
require_once 'config/Database.php';
require_once 'notificationsmanager.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$notification_id = $input['notification_id'] ?? 0;

if ($notification_id === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit();
}

$notificationManager = new NotificationManager();
$success = $notificationManager->markAsRead($notification_id, $_SESSION['user_id']);

header('Content-Type: application/json');
echo json_encode(['success' => $success]);
?>