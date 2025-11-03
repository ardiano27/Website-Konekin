<?php
// Simple router for Vercel
$path = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'konekin';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Simple routing
    if ($method === 'GET' && preg_match('#/api/users/([0-9]+)#', $path, $matches)) {
        // GET /api/users/1
        get_user_by_id($pdo, $matches[1]);
    } elseif ($method === 'GET' && strpos($path, '/api/users') !== false) {
        // GET /api/users
        get_all_users($pdo);
    } elseif ($method === 'POST' && strpos($path, '/api/users') !== false) {
        // POST /api/users
        create_user($pdo);
    } elseif ($method === 'PUT' && strpos($path, '/api/users') !== false) {
        // PUT /api/users
        update_user($pdo);
    } elseif ($method === 'DELETE' && strpos($path, '/api/users') !== false) {
        // DELETE /api/users
        delete_user($pdo);
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

function get_all_users($pdo) {
    $filters = [];
    $params = [];
    
    $sql = "SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE 1=1";
    
    if (isset($_GET['user_type'])) {
        $sql .= " AND user_type = ?";
        $params[] = $_GET['user_type'];
    }
    
    if (isset($_GET['is_active'])) {
        $sql .= " AND is_active = ?";
        $params[] = $_GET['is_active'];
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
        'pagination' => [
            'page' => $page,
            'limit' => $limit
        ]
    ]);
}

function get_user_by_id($pdo, $id) {
    $stmt = $pdo->prepare("SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode(['status' => 'success', 'data' => $user]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
    }
}

function create_user($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || !isset($input['password']) || !isset($input['user_type']) || !isset($input['full_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: email, password, user_type, full_name']);
        return;
    }
    
    if (!in_array($input['user_type'], ['creative', 'umkm'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user_type. Must be "creative" or "umkm"']);
        return;
    }
    
    // Check if email exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$input['email']]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already exists']);
        return;
    }
    
    $uuid = generateUUID();
    $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (uuid, email, password_hash, user_type, full_name, phone, avatar_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $uuid,
        $input['email'],
        $password_hash,
        $input['user_type'],
        $input['full_name'],
        $input['phone'] ?? null,
        $input['avatar_url'] ?? null
    ]);
    
    $userId = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'User created successfully',
        'data' => $user
    ]);
}

function update_user($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user ID']);
        return;
    }
    
    $updateFields = [];
    $params = [];
    $allowedFields = ['full_name', 'phone', 'avatar_url', 'is_verified', 'is_active'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (isset($input['password'])) {
        $updateFields[] = "password_hash = ?";
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }
    
    $params[] = $input['id'];
    $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$input['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found or no changes made']);
    }
}

function delete_user($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user ID']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE users SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$input['id']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'User deleted successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
    }
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