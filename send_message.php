<?php
// debug helper (development only)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

$baseDir = __DIR__;
$logsDir = $baseDir . '/logs';
$uploadsDir = $baseDir . '/uploads';

// ensure logs/uploads exist
if (!is_dir($logsDir)) @mkdir($logsDir, 0755, true);
if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0755, true);

$logFile = $logsDir . '/send_message.log';
function dbg($msg) {
    global $logFile;
    @file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Log request basics (safe)
dbg("REQUEST START: " . json_encode([
    'URI' => $_SERVER['REQUEST_URI'] ?? '',
    'METHOD' => $_SERVER['REQUEST_METHOD'] ?? '',
    'GET' => $_GET ?? [],
    'POST' => $_POST ?? [],
    'FILES_keys' => array_keys($_FILES ?? []),
    'COOKIE' => array_keys($_COOKIE ?? []),
    'SESSION_user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
]));

require_once $baseDir . '/config/Database.php';
try {
    $database = new DatabaseConnection();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    dbg("DB CONNECT ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection error']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    $err = 'User belum login';
    dbg("ERROR: $err");
    echo json_encode(['error' => $err]);
    exit;
}

$sender_id   = (int) $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$message_text = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';
$project_id   = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;

dbg("INPUT: sender_id={$sender_id} receiver_id={$receiver_id} text_len=" . strlen($message_text));

if ($receiver_id <= 0 || $sender_id <= 0) {
    http_response_code(400);
    $err = 'ID pengirim/penerima tidak valid';
    dbg("ERROR: $err");
    echo json_encode(['error' => $err]);
    exit;
}
$hasFile = isset($_FILES['attachment']) && !empty($_FILES['attachment']['name']);
if ($message_text === '' && !$hasFile) {
    http_response_code(400);
    $err = 'Pesan tidak boleh kosong';
    dbg("ERROR: $err");
    echo json_encode(['error' => $err]);
    exit;
}

function uuidv4() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$attachment_urls = [];
try {
    // handle single file attachment (optional)
    if ($hasFile) {
        $file = $_FILES['attachment'];
        dbg("Handling upload: " . json_encode(['name'=>$file['name'],'error'=>$file['error'],'size'=>$file['size']]));

        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Upload gagal atau file tidak valid (error=' . $file['error'] . ')');
        }

        $maxBytes = 5 * 1024 * 1024;
        if ($file['size'] > $maxBytes) throw new Exception('File terlalu besar');

        $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx','txt'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) throw new Exception('Tipe file tidak diizinkan: ' . $ext);

        $filename = uniqid('att_', true) . '.' . $ext;
        $dest = $uploadsDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('Gagal menyimpan file ke disk');

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $attachment_urls[] = $baseUrl . $basePath . '/uploads/' . $filename;
        dbg("Upload saved: $dest -> " . $attachment_urls[0]);
    }

    // insert message
    $pdo->beginTransaction();
    $sql = "INSERT INTO messages (uuid, sender_id, receiver_id, project_id, message_text, attachment_urls, is_read)
            VALUES (:uuid, :sender, :receiver, :project, :text, :attach, 0)";
    $stmt = $pdo->prepare($sql);
    $uuid = uuidv4();
    $stmt->execute([
        ':uuid' => $uuid,
        ':sender' => $sender_id,
        ':receiver' => $receiver_id,
        ':project' => $project_id,
        ':text' => $message_text,
        ':attach' => !empty($attachment_urls) ? json_encode($attachment_urls) : null
    ]);
    $id = $pdo->lastInsertId();
    $pdo->commit();

    $resp = [
        'success' => true,
        'message' => [
            'id' => $id,
            'uuid' => $uuid,
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'message_text' => $message_text,
            'attachment_urls' => $attachment_urls,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    dbg("INSERT OK id=$id uuid=$uuid");
    http_response_code(200);
    echo json_encode($resp);
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $err = 'Database / processing error: ' . $e->getMessage();
    dbg("EXCEPTION: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => $err]);
    exit;
    
}