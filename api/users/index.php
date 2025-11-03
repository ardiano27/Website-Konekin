<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/Database.php';

class UserAPI {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Generate UUID
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    // Hash password
    private function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    // GET all users or single user by ID
    public function getUsers() {
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        $email = isset($_GET['email']) ? $_GET['email'] : null;

        try {
            if ($id) {
                // Get single user by ID
                $query = "SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at, updated_at 
                          FROM " . $this->table_name . " 
                          WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'data' => $user
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'User not found'
                    ]);
                }
            } elseif ($email) {
                // Get single user by email
                $query = "SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at, updated_at 
                          FROM " . $this->table_name . " 
                          WHERE email = :email";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'data' => $user
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'User not found'
                    ]);
                }
            } else {
                // Get all users
                $query = "SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at, updated_at 
                          FROM " . $this->table_name . " 
                          ORDER BY created_at DESC";
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
                
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $users,
                    'count' => count($users)
                ]);
            }
        } catch (PDOException $exception) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $exception->getMessage()
            ]);
        }
    }

    // POST - Create new user
    public function createUser() {
        $data = json_decode(file_get_contents("php://input"), true);

        // Validation
        $required_fields = ['email', 'password', 'user_type', 'full_name'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Field '$field' is required"
                ]);
                return;
            }
        }

        try {
            // Check if email already exists
            $check_query = "SELECT id FROM " . $this->table_name . " WHERE email = :email";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':email', $data['email']);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Email already exists'
                ]);
                return;
            }

            // Generate UUID and hash password
            $uuid = $this->generateUUID();
            $password_hash = $this->hashPassword($data['password']);

            // Insert query
            $query = "INSERT INTO " . $this->table_name . " 
                      (uuid, email, password_hash, user_type, full_name, phone, avatar_url, is_verified, is_active) 
                      VALUES (:uuid, :email, :password_hash, :user_type, :full_name, :phone, :avatar_url, :is_verified, :is_active)";

            $stmt = $this->conn->prepare($query);
            
            // Set default values
            $phone = $data['phone'] ?? null;
            $avatar_url = $data['avatar_url'] ?? null;
            $is_verified = $data['is_verified'] ?? 0;
            $is_active = $data['is_active'] ?? 1;

            // Bind parameters
            $stmt->bindParam(':uuid', $uuid);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':user_type', $data['user_type']);
            $stmt->bindParam(':full_name', $data['full_name']);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':avatar_url', $avatar_url);
            $stmt->bindParam(':is_verified', $is_verified, PDO::PARAM_INT);
            $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $user_id = $this->conn->lastInsertId();
                
                // Get the created user
                $select_query = "SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at, updated_at 
                                FROM " . $this->table_name . " 
                                WHERE id = :id";
                $select_stmt = $this->conn->prepare($select_query);
                $select_stmt->bindParam(':id', $user_id);
                $select_stmt->execute();
                $user = $select_stmt->fetch(PDO::FETCH_ASSOC);

                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'User created successfully',
                    'data' => $user
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create user'
                ]);
            }
        } catch (PDOException $exception) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $exception->getMessage()
            ]);
        }
    }

    // PUT - Update user
    public function updateUser() {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = isset($_GET['id']) ? $_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'User ID is required'
            ]);
            return;
        }

        try {
            // Check if user exists
            $check_query = "SELECT id FROM " . $this->table_name . " WHERE id = :id";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();

            if ($check_stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'User not found'
                ]);
                return;
            }

            // Build update query dynamically based on provided fields
            $update_fields = [];
            $params = [];

            $allowed_fields = ['email', 'user_type', 'full_name', 'phone', 'avatar_url', 'is_verified', 'is_active'];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_fields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }

            // Handle password update separately
            if (isset($data['password'])) {
                $update_fields[] = "password_hash = :password_hash";
                $params[":password_hash"] = $this->hashPassword($data['password']);
            }

            if (empty($update_fields)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No valid fields to update'
                ]);
                return;
            }

            // Check email uniqueness if email is being updated
            if (isset($data['email'])) {
                $email_check_query = "SELECT id FROM " . $this->table_name . " WHERE email = :email AND id != :id";
                $email_check_stmt = $this->conn->prepare($email_check_query);
                $email_check_stmt->bindParam(':email', $data['email']);
                $email_check_stmt->bindParam(':id', $id);
                $email_check_stmt->execute();

                if ($email_check_stmt->rowCount() > 0) {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Email already exists'
                    ]);
                    return;
                }
            }

            $params[':id'] = $id;

            $query = "UPDATE " . $this->table_name . " 
                      SET " . implode(", ", $update_fields) . ", updated_at = CURRENT_TIMESTAMP 
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            
            // Bind all parameters
            foreach ($params as $key => &$value) {
                $stmt->bindParam($key, $value);
            }

            if ($stmt->execute()) {
                // Get updated user
                $select_query = "SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at, updated_at 
                                FROM " . $this->table_name . " 
                                WHERE id = :id";
                $select_stmt = $this->conn->prepare($select_query);
                $select_stmt->bindParam(':id', $id);
                $select_stmt->execute();
                $user = $select_stmt->fetch(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'User updated successfully',
                    'data' => $user
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update user'
                ]);
            }
        } catch (PDOException $exception) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $exception->getMessage()
            ]);
        }
    }

    // DELETE - Delete user
    public function deleteUser() {
        $id = isset($_GET['id']) ? $_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'User ID is required'
            ]);
            return;
        }

        try {
            // Check if user exists
            $check_query = "SELECT id FROM " . $this->table_name . " WHERE id = :id";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();

            if ($check_stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'User not found'
                ]);
                return;
            }

            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete user'
                ]);
            }
        } catch (PDOException $exception) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $exception->getMessage()
            ]);
        }
    }
}

// Initialize database connection and API
$database = new DatabaseConnection();
$db = $database->getConnection();
$userAPI = new UserAPI($db);

// Route the request
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $userAPI->getUsers();
            break;
        case 'POST':
            $userAPI->createUser();
            break;
        case 'PUT':
            $userAPI->updateUser();
            break;
        case 'DELETE':
            $userAPI->deleteUser();
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>