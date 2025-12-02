<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

try {
    require_once __DIR__ . 'config/Database.php';

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }

    $database = new DatabaseConnection();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $current_user = (int) $_SESSION['user_id'];
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $message_text = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';
    $attachment_urls = isset($_POST['attachment_urls']) ? $_POST['attachment_urls'] : '[]';

    // Validate input (jika pesan dan attachment kosong, error)
    if (empty($message_text) && $attachment_urls === '[]') {
        throw new Exception('Message and attachment cannot be empty');
    }

    // Insert Message
    $sql = "INSERT INTO messages (uuid, sender_id, receiver_id, message_text, attachment_urls) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $stmt->execute([
        $uuid,
        $current_user,
        $receiver_id,
        $message_text,
        $attachment_urls // Disimpan sebagai string JSON
    ]);

    $message_id = $conn->lastInsertId();

    // Fetch the created message to return (Opsional, tapi bagus untuk feedback instan)
    $stmt = $conn->prepare("SELECT * FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    // Process message data (sama dengan di fetch_message.php agar konsisten)
    if (!empty($message['attachment_urls'])) {
        $message['attachment_urls'] = json_decode($message['attachment_urls'], true) ?: [];
    } else {
        $message['attachment_urls'] = [];
    }
    
    $timestamp = strtotime($message['created_at']);
    $message['created_at_time'] = date('H:i', $timestamp);
    $message['created_at_date'] = date('Y-m-d', $timestamp);
    

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => $message // Kembalikan pesan yang baru dibuat
    ]);

} catch (Exception $e) {
    ob_clean();
    error_log('send_message.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}