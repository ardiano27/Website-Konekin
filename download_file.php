<?php
session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Only allow authenticated users
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized access. Please login first.';
    exit;
}

$current_user_id = (int) $_SESSION['user_id'];

// Get parameters
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
$message_id = isset($_GET['msg_id']) ? (int) $_GET['msg_id'] : 0;
$original_name = isset($_GET['original']) ? urldecode($_GET['original']) : '';

if (empty($filename)) {
    http_response_code(400);
    echo 'File name required';
    exit;
}

try {
    require_once __DIR__ . '/config/Database.php';
    $database = new DatabaseConnection();
    $conn = $database->getConnection();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if user has permission to download this file
    $sql = "SELECT m.sender_id, m.receiver_id, m.attachment_urls 
            FROM messages m 
            WHERE m.id = :message_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
    $stmt->execute();
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify user has permission (either sender or receiver)
    if (!$message || 
        ($message['sender_id'] !== $current_user_id && 
         $message['receiver_id'] !== $current_user_id)) {
        http_response_code(403);
        echo 'Access denied to this file';
        exit;
    }

    // Decode attachment URLs to verify file exists
    $attachment_urls = json_decode($message['attachment_urls'], true);
    $file_info = null;
    $file_exists_in_db = false;
    
    if (is_array($attachment_urls)) {
        foreach ($attachment_urls as $attachment) {
            $basename = basename($attachment['url'] ?? '');
            if ($basename === $filename) {
                $file_info = $attachment;
                $file_exists_in_db = true;
                break;
            }
        }
    }

    if (!$file_exists_in_db) {
        http_response_code(404);
        echo 'File not found in database records';
        exit;
    }

    // Construct full file path - DIPERBAIKI PATH INI
    // Pastikan folder uploads ada di root project
    $base_upload_dir = __DIR__ . '/uploads/'; // CHANGED FROM '/uploads/messages'
    $file_path = $base_upload_dir . $filename;
    
    // Security: Prevent directory traversal
    $real_base = realpath($base_upload_dir);
    $real_path = realpath($file_path);
    
    if ($real_path === false || strpos($real_path, $real_base) !== 0) {
        http_response_code(403);
        echo 'Invalid file path';
        exit;
    }

    // Check if file exists
    if (!file_exists($file_path)) {
        http_response_code(404);
        echo 'File not found on server: ' . htmlspecialchars($file_path);
        exit;
    }

    // Get original filename from attachment info or parameter
    $download_filename = $original_name ?: ($file_info['original_name'] ?? $filename);
    
    // Clean filename for safe download
    $download_filename = preg_replace('/[^\p{L}\p{N}\s\.\-_]/u', '', $download_filename);
    $download_filename = substr($download_filename, 0, 200);
    
    // Jika tidak ada extension, tambahkan dari file asli
    if (!pathinfo($download_filename, PATHINFO_EXTENSION)) {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if ($extension) {
            $download_filename .= '.' . $extension;
        }
    }

    // Get file size and type
    $file_size = filesize($file_path);
    $file_type = mime_content_type($file_path);

    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $file_type);
    header('Content-Disposition: attachment; filename="' . $download_filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $file_size);
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Read file and output
    readfile($file_path);
    exit;

} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    http_response_code(500);
    echo 'Download failed. Please try again. Error: ' . $e->getMessage();
    exit;
}
?>