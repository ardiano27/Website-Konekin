<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Optional CORS (aktifkan jika frontend di origin berbeda)
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Development error reporting (ubah/remove di production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/config/Database.php';

    // Check if authenticated
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Not authenticated',
            'messages' => [],
            'count' => 0
        ]);
        exit;
    }

    $database = new DatabaseConnection();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // PDO attributes
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    $current_user = (int) $_SESSION['user_id'];
    $other_user = isset($_GET['other_user']) ? (int) $_GET['other_user'] : 0;
    $since_id = isset($_GET['since_id']) ? (int) $_GET['since_id'] : 0;

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

    // Build query
    $sql = "
        SELECT 
            id, 
            sender_id, 
            receiver_id, 
            message_text, 
            COALESCE(attachment_urls, '') as attachment_urls, 
            is_read, 
            created_at
        FROM messages
        WHERE 
            (
                (sender_id = :current_user AND receiver_id = :other_user)
                OR 
                (sender_id = :other_user2 AND receiver_id = :current_user2)
            )
    ";

    $params = [
        ':current_user' => $current_user,
        ':other_user' => $other_user,
        ':other_user2' => $other_user,
        ':current_user2' => $current_user
    ];

    if ($since_id > 0) {
        $sql .= " AND id > :since_id";
        $params[':since_id'] = $since_id;
    }

    $sql .= " ORDER BY id ASC LIMIT 200";

    $stmt = $conn->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $messages = [];
    $last_id = 0;

    foreach ($rows as $r) {
        $id = isset($r['id']) ? (int)$r['id'] : 0;
        $sender_id = isset($r['sender_id']) ? (int)$r['sender_id'] : 0;
        $receiver_id = isset($r['receiver_id']) ? (int)$r['receiver_id'] : 0;
        $text = isset($r['message_text']) ? $r['message_text'] : '';
        $created_at = isset($r['created_at']) ? $r['created_at'] : date('Y-m-d H:i:s');
        $is_read = (bool)($r['is_read'] ?? false);
        $attachment_raw = $r['attachment_urls'] ?? '';

        // Sanitize text
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $created_at = htmlspecialchars($created_at, ENT_QUOTES, 'UTF-8');

        // Decode attachment_urls as JSON
        $attachments = [];
        if ($attachment_raw !== '' && $attachment_raw !== 'null') {
            $decoded = json_decode($attachment_raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Fix URLs to be correct for web access - DIPERBAIKI DI SINI
                foreach ($decoded as &$attachment) {
                    if (isset($attachment['url'])) {
                        // Pastikan URL benar untuk akses web
                        $url = $attachment['url'];
                        
                        // Jika URL relatif (tanpa http/https), tambahkan slash depan
                        if (!preg_match('/^https?:\/\//', $url) && !str_starts_with($url, '/')) {
                            $attachment['url'] = '/' . $url;
                        }
                        
                        // Add download URL
                        $filename = basename($url);
                        $attachment['download_url'] = '/download_file.php?file=' . urlencode($filename) . 
                                                     '&msg_id=' . $id . 
                                                     '&original=' . urlencode($attachment['original_name'] ?? $filename);
                    }
                }
                $attachments = $decoded;
            } else {
                // Try to handle as string URL
                if (!empty($attachment_raw) && $attachment_raw !== '[]' && $attachment_raw !== 'null') {
                    $url = $attachment_raw;
                    // Fix relative URL
                    if (!preg_match('/^https?:\/\//', $url) && !str_starts_with($url, '/')) {
                        $url = '/' . $url;
                    }
                    $filename = basename($url);
                    $attachments = [[
                        'url' => $url,
                        'original_name' => $filename,
                        'download_url' => '/download_file.php?file=' . urlencode($filename) . 
                                         '&msg_id=' . $id . 
                                         '&original=' . urlencode($filename)
                    ]];
                }
            }
        }

        $messages[] = [
            'id' => $id,
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'message_text' => $text,
            'attachment_urls' => $attachments,
            'is_read' => $is_read,
            'created_at' => $created_at
        ];

        if ($id > $last_id) $last_id = $id;
    }

    // Mark fetched incoming messages as read (only those received by current user and returned)
    if ($last_id > 0) {
        $updateSql = "
            UPDATE messages
            SET is_read = 1
            WHERE receiver_id = :receiver_id
            AND sender_id = :sender_id
            AND id <= :last_id
            AND is_read = 0
        ";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([
            ':receiver_id' => $current_user,
            ':sender_id' => $other_user,
            ':last_id' => $last_id
        ]);
    }

    echo json_encode([
        'messages' => $messages,
        'count' => count($messages),
        'since_id' => $since_id,
        'last_id' => $last_id,
        'current_user' => $current_user,
        'other_user' => $other_user,
        'error' => null
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log('fetch_messages.php PDO error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'messages' => [],
        'count' => 0,
        'debug' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('fetch_messages.php unexpected error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'messages' => [],
        'count' => 0
    ]);
}
?>