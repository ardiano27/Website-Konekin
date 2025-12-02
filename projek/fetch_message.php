<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Error reporting untuk development - HAPUS INI DI PRODUCTION
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

    $database = new Database();
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
            'count' => 0,
            'current_user' => $current_user,
            'other_user' => $other_user
        ]);
        exit;
    }

    // Prevent fetching messages with self
    if ($current_user === $other_user) {
        echo json_encode([
            'messages' => [], 
            'error' => 'Cannot chat with yourself', 
            'count' => 0,
            'current_user' => $current_user,
            'other_user' => $other_user
        ]);
        exit;
    }

    // Rate limiting check (sementara di-nonaktifkan untuk testing)
    /*
    $rateLimitKey = 'fetch_rate_' . $current_user;
    $rateLimitFile = sys_get_temp_dir() . '/' . $rateLimitKey;
    
    if (file_exists($rateLimitFile)) {
        $lastRequest = (int)file_get_contents($rateLimitFile);
        $timeDiff = time() - $lastRequest;
        
        if ($timeDiff < 1) {
            http_response_code(429);
            echo json_encode([
                'error' => 'Too many requests', 
                'messages' => [], 
                'count' => 0
            ]);
            exit;
        }
    }
    
    file_put_contents($rateLimitFile, time());
    */

    // Build query dengan positional parameters (menghindari duplikat parameter names)
    $sql = "
        SELECT 
            id, 
            sender_id, 
            receiver_id, 
            message_text, 
            attachment_urls, 
            is_read, 
            created_at
        FROM messages
        WHERE 
            (
                (sender_id = ? AND receiver_id = ?)
                OR 
                (sender_id = ? AND receiver_id = ?)
            )
    ";
    
    $params = [
        $current_user, // sender_id pertama
        $other_user,   // receiver_id pertama  
        $other_user,   // sender_id kedua
        $current_user  // receiver_id kedua
    ];
    
    // Incremental loading - only fetch new messages
    if ($since_id > 0) {
        $sql .= " AND id > ?";
        $params[] = $since_id;
    }
    
    // Limit messages to prevent memory issues
    $sql .= " ORDER BY created_at ASC, id ASC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    
    // Debug logging
    error_log("Fetch messages - User: $current_user, Other: $other_user, Since: $since_id");
    error_log("SQL: " . $sql);
    error_log("Params: " . implode(', ', $params));
    
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log hasil query
    error_log("Found " . count($messages) . " messages between user $current_user and $other_user");

    // Sanitize output - remove any potential XSS
    foreach ($messages as &$msg) {
        // Message text is already escaped in frontend, but double-check
        $msg['message_text'] = htmlspecialchars($msg['message_text'] ?? '', ENT_QUOTES, 'UTF-8');
        
        // Ensure created_at is properly formatted
        if (isset($msg['created_at'])) {
            $msg['created_at'] = htmlspecialchars($msg['created_at'], ENT_QUOTES, 'UTF-8');
        }
        
        // Ensure attachment_urls is properly formatted
        if (isset($msg['attachment_urls'])) {
            $msg['attachment_urls'] = htmlspecialchars($msg['attachment_urls'], ENT_QUOTES, 'UTF-8');
        }
        
        // Convert is_read to boolean
        $msg['is_read'] = (bool)($msg['is_read'] ?? false);
    }
    unset($msg); // Break reference

    // Mark received messages as read (only messages TO current user FROM other user)
    if (!empty($messages)) {
        $updateSql = "
            UPDATE messages 
            SET is_read = 1 
            WHERE receiver_id = ? 
            AND sender_id = ? 
            AND is_read = 0
        ";
        
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([
            $current_user,
            $other_user
        ]);
        
        // Log read receipt count for debugging
        $readCount = $updateStmt->rowCount();
        if ($readCount > 0) {
            error_log("Marked {$readCount} messages as read for user {$current_user} from {$other_user}");
        }
    }

    // Clear any unexpected output sebelum mengirim JSON
    ob_clean();
    
    echo json_encode([
        'messages' => $messages,
        'count' => count($messages),
        'since_id' => $since_id,
        'current_user' => $current_user,
        'other_user' => $other_user,
        'error' => null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Clear buffer before sending error
    ob_clean();
    
    error_log('fetch_message.php PDO error: ' . $e->getMessage());
    error_log('PDO error code: ' . $e->getCode());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'messages' => [],
        'count' => 0,
        'debug_info' => 'Check server logs for details' // Hanya untuk development
    ]);
} catch (Exception $e) {
    // Clear buffer before sending error
    ob_clean();
    
    error_log('fetch_message.php unexpected error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred: ' . $e->getMessage(),
        'messages' => [],
        'count' => 0
    ]);
}
// Di fetch_messages.php, pastikan bagian ini ada:
if ($since_id > 0) {
    $sql .= " AND id > :since_id";
    $params[':since_id'] = $since_id;
}
// End buffering
ob_end_flush();
?>