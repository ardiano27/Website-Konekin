<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get database config from environment variables
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'konekin';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

// For Vercel serverless, we need to use PlanetScale or similar
// Since Vercel doesn't support traditional MySQL directly

try {
    // Check if we're using PlanetScale (recommended for Vercel)
    if (getenv('PLANETSCALE_DB_HOST')) {
        $host = getenv('PLANETSCALE_DB_HOST');
        $dbname = getenv('PLANETSCALE_DB');
        $username = getenv('PLANETSCALE_DB_USERNAME');
        $password = getenv('PLANETSCALE_DB_PASSWORD');
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;sslmode=require", $username, $password, [
            PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt'
        ]);
    } else {
        // Fallback to regular MySQL (for testing)
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    }
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get request method
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Get path to handle different endpoints
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathSegments = explode('/', trim($path, '/'));
    
    // Main users endpoint
    if (end($pathSegments) === 'users' || basename($_SERVER['PHP_SELF']) === 'index.php') {
        handleUsersRequest($method, $pdo);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ]);
}

function handleUsersRequest($method, $pdo) {
    switch ($method) {
        case 'GET':
            handleGetUsers($pdo);
            break;
            
        case 'POST':
            handlePostUser($pdo);
            break;
            
        case 'PUT':
            handlePutUser($pdo);
            break;
            
        case 'DELETE':
            handleDeleteUser($pdo);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handleGetUsers($pdo) {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode(['status' => 'success', 'data' => $user]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
    } else {
        // Your existing GET logic here...
        $filters = [];
        $params = [];
        
        $sql = "SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE 1=1";
        
        if (isset($_GET['user_type'])) {
            $sql .= " AND user_type = ?";
            $params[] = $_GET['user_type'];
        }
        
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'data' => $users,
            'pagination' => ['page' => $page, 'limit' => $limit]
        ]);
    }
}

function handlePostUser($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || !isset($input['password']) || !isset($input['user_type']) || !isset($input['full_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    // Your existing POST logic here...
    $uuid = generateUUID();
    $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (uuid, email, password_hash, user_type, full_name, phone) VALUES (?, ?, ?, ?, ?, ?)");
    
    try {
        $stmt->execute([
            $uuid,
            $input['email'],
            $password_hash,
            $input['user_type'],
            $input['full_name'],
            $input['phone'] ?? null
        ]);
        
        $userId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'User created successfully',
            'data' => $user
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            http_response_code(409);
            echo json_encode(['error' => 'Email already exists']);
        } else {
            throw $e;
        }
    }
}

function handlePutUser($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Your existing PUT logic here...
    // (Copy from your original code)
}

function handleDeleteUser($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Your existing DELETE logic here...
    // (Copy from your original code)
}

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>