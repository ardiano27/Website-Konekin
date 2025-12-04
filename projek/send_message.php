<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Enable debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Konfigurasi upload
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/bmp',
    'application/pdf',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',
    'application/x-rar-compressed'
]);

// Fungsi untuk generate UUID v4
function generateUUID() {
    if (function_exists('random_bytes')) {
        $data = random_bytes(16);
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $data = openssl_random_pseudo_bytes(16);
    } else {
        $data = '';
        for ($i = 0; $i < 16; $i++) {
            $data .= chr(mt_rand(0, 255));
        }
    }
    
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    
    return $uuid;
}

// Fungsi untuk sanitasi nama file
function sanitizeFileName($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $filename);
    $filename = substr($filename, 0, 100);
    return $filename;
}

// Fungsi untuk format file size
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

try {
    // Debug log
    error_log("=== SEND_MESSAGE START ===");
    error_log("Session ID: " . session_id());
    error_log("POST Data: " . print_r($_POST, true));
    error_log("FILES Data: " . print_r($_FILES, true));

    // Include database config
    require_once __DIR__ . '/config/Database.php';

    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        error_log("Not authenticated - no user_id in session");
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated. Please login first.'
        ]);
        exit;
    }

    $sender_id = (int) $_SESSION['user_id'];
    error_log("Sender ID from session: " . $sender_id);

    // Get POST data
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $message_text = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';
    
    error_log("Receiver ID: " . $receiver_id);
    error_log("Message text: '" . $message_text . "' (length: " . strlen($message_text) . ")");

    // Basic validation
    if ($receiver_id <= 0) {
        error_log("Invalid receiver ID: " . $receiver_id);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid receiver ID'
        ]);
        exit;
    }

    if ($sender_id === $receiver_id) {
        error_log("Cannot send to self");
        echo json_encode([
            'success' => false,
            'error' => 'Cannot send message to yourself'
        ]);
        exit;
    }

    // Check if we have files uploaded
    $has_uploaded_files = false;
    if (!empty($_FILES)) {
        error_log("Checking uploaded files...");
        
        // Check for any valid uploaded files
        foreach ($_FILES as $file_key => $file) {
            if (is_array($file['name'])) {
                // Multiple files with array notation (attachment[0], etc)
                foreach ($file['error'] as $error) {
                    if ($error === UPLOAD_ERR_OK) {
                        $has_uploaded_files = true;
                        break 2;
                    }
                }
            } elseif ($file['error'] === UPLOAD_ERR_OK) {
                // Single file
                $has_uploaded_files = true;
                break;
            }
        }
        error_log("Has uploaded files: " . ($has_uploaded_files ? 'Yes' : 'No'));
    }

    // Validasi file upload jika ada file yang benar-benar diupload
    if ($has_uploaded_files) {
        error_log("Validating uploaded files...");
        
        foreach ($_FILES as $file_key => $file) {
            if (is_array($file['name'])) {
                // Multiple files
                for ($i = 0; $i < count($file['name']); $i++) {
                    if ($file['error'][$i] === UPLOAD_ERR_OK) {
                        $single_file = [
                            'name' => $file['name'][$i],
                            'type' => $file['type'][$i],
                            'tmp_name' => $file['tmp_name'][$i],
                            'error' => $file['error'][$i],
                            'size' => $file['size'][$i]
                        ];
                        
                        validateFile($single_file);
                    }
                }
            } elseif ($file['error'] === UPLOAD_ERR_OK) {
                // Single file
                validateFile($file);
            }
        }
    }

    // PERBAIKAN DI SINI: Validasi yang benar
    // Boleh mengirim hanya file, hanya teks, atau keduanya
    if (empty($message_text) && !$has_uploaded_files) {
        error_log("Empty message and no files");
        echo json_encode([
            'success' => false,
            'error' => 'Silakan ketik pesan atau lampirkan file'
        ]);
        exit;
    }

    // Connect to database
    error_log("Connecting to database...");
    $database = new DatabaseConnection();
    $conn = $database->getConnection();
    
    if (!$conn) {
        error_log("Database connection failed");
        throw new Exception('Database connection failed');
    }
    
    error_log("Database connected successfully");

    // Set PDO attributes
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // === GENERATE UUID ===
    $uuid = generateUUID();
    error_log("Generated UUID: " . $uuid);
    
    // Cek apakah UUID sudah ada
    $checkUuidSql = "SELECT COUNT(*) as count FROM messages WHERE uuid = :uuid";
    $checkStmt = $conn->prepare($checkUuidSql);
    $checkStmt->bindValue(':uuid', $uuid, PDO::PARAM_STR);
    $checkStmt->execute();
    $uuidExists = $checkStmt->fetchColumn();
    
    if ($uuidExists > 0) {
        error_log("UUID collision detected, generating new one...");
        $uuid = generateUUID();
        error_log("New UUID: " . $uuid);
    }

    // Process attachments if any
    $attachment_urls = null;
    $uploaded_files = [];
    
    if ($has_uploaded_files) {
        error_log("Processing uploaded files...");
        
        $upload_dir = __DIR__ . '/uploads/messages';
        
        // Auto-create directory if not exists
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                error_log("Failed to create upload directory: " . $upload_dir);
                throw new Exception('Failed to create upload directory');
            } else {
                // Tambah .htaccess untuk keamanan
                $htaccess_content = "Deny from all\n<Files ~ \"\.(jpg|jpeg|png|gif|webp|bmp|pdf|txt|doc|docx|zip|rar)$\">\n    Allow from all\n</Files>";
                file_put_contents($upload_dir . '/.htaccess', $htaccess_content);
                error_log("Created upload directory: " . $upload_dir);
            }
        }
        
        // Cek apakah direktori writable
        if (!is_writable($upload_dir)) {
            error_log("Upload directory not writable: " . $upload_dir);
            throw new Exception('Upload directory not writable');
        }
        
        // Process all uploaded files
        foreach ($_FILES as $file_key => $file) {
            if (is_array($file['name'])) {
                // Multiple files
                for ($i = 0; $i < count($file['name']); $i++) {
                    if ($file['error'][$i] === UPLOAD_ERR_OK) {
                        $single_file = [
                            'name' => $file['name'][$i],
                            'type' => $file['type'][$i],
                            'tmp_name' => $file['tmp_name'][$i],
                            'error' => $file['error'][$i],
                            'size' => $file['size'][$i]
                        ];
                        
                        $uploaded_files[] = processSingleFile($single_file, $upload_dir);
                    }
                }
            } elseif ($file['error'] === UPLOAD_ERR_OK) {
                // Single file
                $uploaded_files[] = processSingleFile($file, $upload_dir);
            }
        }
        
        if (!empty($uploaded_files)) {
            $attachment_urls = json_encode($uploaded_files, JSON_UNESCAPED_SLASHES);
            error_log("Attachment URLs JSON: " . $attachment_urls);
        }
    }

    // Check if receiver exists
    $checkReceiverSql = "SELECT id FROM users WHERE id = :receiver_id LIMIT 1";
    $checkReceiverStmt = $conn->prepare($checkReceiverSql);
    $checkReceiverStmt->bindValue(':receiver_id', $receiver_id, PDO::PARAM_INT);
    $checkReceiverStmt->execute();
    
    if ($checkReceiverStmt->rowCount() === 0) {
        error_log("Receiver does not exist: " . $receiver_id);
        echo json_encode([
            'success' => false,
            'error' => 'Receiver does not exist'
        ]);
        exit;
    }

    // Insert message with UUID
    $sql = "INSERT INTO messages (
                uuid, 
                sender_id, 
                receiver_id, 
                message_text, 
                attachment_urls, 
                is_read, 
                created_at
            ) VALUES (
                :uuid, 
                :sender_id, 
                :receiver_id, 
                :message_text, 
                :attachment_urls, 
                0, 
                NOW()
            )";
    
    error_log("Insert SQL: " . $sql);
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':uuid', $uuid, PDO::PARAM_STR);
    $stmt->bindValue(':sender_id', $sender_id, PDO::PARAM_INT);
    $stmt->bindValue(':receiver_id', $receiver_id, PDO::PARAM_INT);
    $stmt->bindValue(':message_text', $message_text, PDO::PARAM_STR);
    $stmt->bindValue(':attachment_urls', $attachment_urls, 
        $attachment_urls !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    
    error_log("Executing insert statement...");
    
    if ($stmt->execute()) {
        $message_id = $conn->lastInsertId();
        error_log("Message inserted successfully. ID: " . $message_id);
        
        // Update download URLs dengan message ID yang benar
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as &$file_info) {
                $filename = basename($file_info['url']);
                $file_info['download_url'] = '/download_file.php?file=' . urlencode($filename) . 
                                            '&msg_id=' . $message_id . 
                                            '&original=' . urlencode($file_info['original_name']);
            }
            
            // Update database dengan download URLs
            $update_attachment_urls = json_encode($uploaded_files, JSON_UNESCAPED_SLASHES);
            $updateSql = "UPDATE messages SET attachment_urls = :attachment_urls WHERE id = :id";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([
                ':attachment_urls' => $update_attachment_urls,
                ':id' => $message_id
            ]);
            
            error_log("Updated attachment URLs with download links");
        }
        
        // Get the inserted message
        $selectSql = "SELECT * FROM messages WHERE id = :id";
        $selectStmt = $conn->prepare($selectSql);
        $selectStmt->bindValue(':id', $message_id, PDO::PARAM_INT);
        $selectStmt->execute();
        $message = $selectStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($message) {
            // Process attachment URLs for response
            $response_attachments = [];
            if (!empty($message['attachment_urls'])) {
                $attachments_json = json_decode($message['attachment_urls'], true);
                if (is_array($attachments_json)) {
                    $response_attachments = $attachments_json;
                }
            }
            
            $response = [
                'success' => true,
                'message' => [
                    'id' => (int)$message['id'],
                    'uuid' => $message['uuid'],
                    'sender_id' => (int)$message['sender_id'],
                    'receiver_id' => (int)$message['receiver_id'],
                    'message_text' => htmlspecialchars($message['message_text'], ENT_QUOTES, 'UTF-8'),
                    'attachment_urls' => $response_attachments,
                    'is_read' => (bool)$message['is_read'],
                    'created_at' => $message['created_at']
                ]
            ];
            
            error_log("Sending success response");
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } else {
            error_log("Could not retrieve inserted message");
            // Fallback response
            $response = [
                'success' => true,
                'message' => [
                    'id' => $message_id,
                    'uuid' => $uuid,
                    'sender_id' => $sender_id,
                    'receiver_id' => $receiver_id,
                    'message_text' => htmlspecialchars($message_text, ENT_QUOTES, 'UTF-8'),
                    'attachment_urls' => $uploaded_files,
                    'is_read' => false,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Execute failed. Error info: " . print_r($errorInfo, true));
        throw new Exception('Failed to insert message. SQL State: ' . $errorInfo[0]);
    }

} catch (PDOException $e) {
    error_log("PDO Exception: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("SQL State: " . $e->errorInfo[0]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    error_log("General Exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

// === HELPER FUNCTIONS ===

function validateFile($file) {
    error_log("Validating file: " . $file['name']);
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        error_log("File size exceeded: " . $file['name'] . " - " . $file['size']);
        throw new Exception('File "' . htmlspecialchars($file['name']) . '" exceeds maximum limit (10MB)');
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
        error_log("Invalid file type: " . $mime_type . " - " . $file['name']);
        throw new Exception('File type "' . $mime_type . '" not allowed for "' . htmlspecialchars($file['name']) . '"');
    }
    
    // Check for PHP files disguised as images
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (stripos($file_ext, 'php') !== false || 
        stripos($file_ext, 'phtml') !== false ||
        stripos($file_ext, 'phar') !== false) {
        error_log("PHP file disguised: " . $file['name']);
        throw new Exception('File type not allowed');
    }
    
    error_log("File validation passed: " . $file['name']);
}

function processSingleFile($file, $upload_dir) {
    error_log("Processing file: " . $file['name']);
    
    $original_name = basename($file['name']);
    $sanitized_name = sanitizeFileName($original_name);
    $file_ext = pathinfo($sanitized_name, PATHINFO_EXTENSION);
    
    // Pastikan extension valid
    $safe_ext = preg_replace('/[^a-z0-9]/', '', strtolower($file_ext));
    if (empty($safe_ext)) {
        // Tentukan extension dari MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $mime_to_ext = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar'
        ];
        
        $safe_ext = $mime_to_ext[$mime_type] ?? 'dat';
    }
    
    // Generate nama file yang aman dan unik
    $timestamp = date('Ymd_His');
    $random = bin2hex(random_bytes(8));
    $safe_name = $timestamp . '_' . $random . '.' . $safe_ext;
    $dest_path = $upload_dir . '/' . $safe_name;
    
    // Pastikan nama file unik
    $counter = 1;
    while (file_exists($dest_path)) {
        $safe_name = $timestamp . '_' . $random . '_' . $counter . '.' . $safe_ext;
        $dest_path = $upload_dir . '/' . $safe_name;
        $counter++;
    }
    
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        // Set permission file
        chmod($dest_path, 0644);
        
        $file_url = '/uploads/messages/' . $safe_name;
        $result = [
            'url' => $file_url,
            'original_name' => $original_name,
            'size' => $file['size'],
            'formatted_size' => formatFileSize($file['size']),
            'type' => mime_content_type($dest_path),
            // Download URL akan di-update nanti dengan message ID
            'download_url' => '/download_file.php?file=' . urlencode($safe_name) . '&msg_id=0&original=' . urlencode($original_name)
        ];
        
        error_log("File uploaded successfully: " . $file_url . " (Original: " . $original_name . ")");
        return $result;
    } else {
        error_log("Failed to move uploaded file: " . $original_name);
        throw new Exception('Failed to save uploaded file: ' . $original_name);
    }
}

error_log("=== SEND_MESSAGE END ===");
?>