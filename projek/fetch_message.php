<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Buffer output untuk menangkap error
ob_start();

try {
    require_once __DIR__ . '/config/Database.php';

    // Check if authenticated
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }

    $database = new DatabaseConnection();
    $conn = $database->getConnection();

    // Validate database connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $current_user = (int) $_SESSION['user_id'];
    $other_user = isset($_GET['other_user']) ? (int)$_GET['other_user'] : 0;
    $since_id = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

    // Validate input
    if ($other_user <= 0) {
        echo json_encode([
            'messages' => [], 
            'error' => 'Invalid other_user parameter', 
            'count' => 0
        ]);
        exit;
    }

    // 1. Fetch Messages
    $sql = "
        SELECT 
            * FROM messages 
        WHERE id > :since_id
        AND (
            (sender_id = :current_user AND receiver_id = :other_user) 
            OR 
            (sender_id = :other_user AND receiver_id = :current_user)
        ) 
        ORDER BY created_at ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':current_user', $current_user, PDO::PARAM_INT);
    $stmt->bindParam(':other_user', $other_user, PDO::PARAM_INT);
    $stmt->bindParam(':since_id', $since_id, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Format Messages for Frontend
    foreach ($messages as &$msg) {
        // Decode attachment_urls (JSON string -> PHP Array)
        if (isset($msg['attachment_urls'])) {
            $msg['attachment_urls'] = json_decode($msg['attachment_urls'], true) ?: [];
        } else {
            $msg['attachment_urls'] = [];
        }
        
        // Format created_at untuk logic di JS (Penting untuk pemisah tanggal)
        if (isset($msg['created_at'])) {
            $timestamp = strtotime($msg['created_at']);
            // Tambahkan waktu (H:i) dan tanggal (Y-m-d) secara terpisah
            $msg['created_at_time'] = date('H:i', $timestamp);
            $msg['created_at_date'] = date('Y-m-d', $timestamp);
        }
    }
    unset($msg); // Penting untuk un-set reference pada foreach (&)

    // 3. Mark received messages as read
    if (!empty($messages)) {
        $updateSql = "
            UPDATE messages 
            SET is_read = 1 
            WHERE receiver_id = :current_user 
            AND sender_id = :other_user 
            AND is_read = 0
        ";
        
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bindParam(':current_user', $current_user, PDO::PARAM_INT);
        $updateStmt->bindParam(':other_user', $other_user, PDO::PARAM_INT);
        $updateStmt->execute();
    }

    // Clear any unexpected output sebelum mengirim JSON
    ob_clean();
    
    // 4. Send Response
    $response = [
        'messages' => $messages,
        'count' => count($messages),
        'since_id' => $since_id,
        'current_user' => $current_user,
        'other_user' => $other_user,
        'error' => null
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    ob_clean();
    error_log('fetch_message.php PDO error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred: ' . $e->getMessage(), // Beri detail error
        'messages' => [],
        'count' => 0
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_clean();
    error_log('fetch_message.php error: ' . $e->getMessage());
    
    http_response_code(400); // Bad Request / Not Authenticated
    echo json_encode([
        'error' => $e->getMessage(),
        'messages' => [],
        'count' => 0
    ], JSON_UNESCAPED_UNICODE);
}