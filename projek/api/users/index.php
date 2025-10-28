<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$dbname = 'fix';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // 2. Method GET (Read All Users)

        $stmt = $pdo->query("SELECT id, uuid, email, user_type, full_name, phone FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $users,
            'count' => count($users)
        ]);
        
    } elseif ($method === 'POST') {
        // 3. Method POST (Create New User)

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['email'], $data['user_type'], $data['full_name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input data. Required fields: email, user_type, full_name.']);
            exit;
        }

        $uuid = uniqid('', true); 
        $email = $data['email'];
        $user_type = $data['user_type'];
        $full_name = $data['full_name'];
        // Kolom phone bersifat opsional
        $phone = $data['phone'] ?? null; 
        
        // Query INSERT dengan prepared statement (PENTING untuk keamanan)
        $sql = "INSERT INTO users (uuid, email, user_type, full_name, phone) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uuid, $email, $user_type, $full_name, $phone]);

        http_response_code(201); // 201 Created
        echo json_encode([
            'status' => 'success',
            'message' => 'User created successfully',
            'id' => $pdo->lastInsertId(),
            'uuid' => $uuid
        ]);

    } elseif ($method === 'PUT') {
        // 4. Method PUT (Update Existing User)

        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['id']) || !isset($data['full_name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input. Required fields: id, and at least one field to update (e.g., full_name).']);
            exit;
        }
        
        $id = $data['id'];
        $full_name = $data['full_name'];
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;

        $fields = [];
        $params = [];
        
        if ($full_name !== null) {
            $fields[] = "full_name = ?";
            $params[] = $full_name;
        }
        if ($email !== null) {
            $fields[] = "email = ?";
            $params[] = $email;
        }
        if ($phone !== null) {
            $fields[] = "phone = ?";
            $params[] = $phone;
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields provided for update.']);
            exit;
        }

        $params[] = $id; // Tambahkan ID sebagai parameter terakhir untuk klausa WHERE
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'User updated successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'User not found or no changes made.']);
        }

    } elseif ($method === 'DELETE') {
        // 5. Method DELETE (Delete User)

        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user ID for deletion.']);
            exit;
        }
        
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => "User with ID $id deleted successfully"
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => "User with ID $id not found."]);
        }

    } else {
        // 6. Metode Lain
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    // 7. Error Handling (Koneksi/Query)

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed or query error',
        'error' => $e->getMessage()
    ]);
}
?>