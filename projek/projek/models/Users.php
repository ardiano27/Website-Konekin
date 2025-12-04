<?php
class User {
    private $conn;
    private $table_name = "users";
    public function __construct($db) {
        $this->conn = $db;
    }

    // Login method
    public function login($email, $password) {
        $sql = "SELECT * FROM {$this->table_name} WHERE email = :email AND is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $this->updateLastLogin($user['id']);
            return $user;
        }
        return false;
    }

    private function updateLastLogin($user_id) {
        $sql = "UPDATE {$this->table_name} SET last_login = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $user_id]);
    }

    // Create user dengan password
    public function create($name, $email, $role, $password) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO {$this->table_name} (name, email, role, password) 
                VALUES (:name, :email, :role, :password)";
        $stmt = $this->conn->prepare($sql);
        
        return $stmt->execute([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => $hashed_password
        ]);
    }

    // Update profile
    public function updateProfile($id, $data) {
        $sql = "UPDATE {$this->table_name} SET 
                name = :name, phone = :phone, address = :address, 
                bio = :bio, skills = :skills 
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'bio' => $data['bio'],
            'skills' => $data['skills']
        ]);
    }

    // Get users by role
    public function getByRole($role) {
        $sql = "SELECT id, name, email, bio, skills, profile_picture 
                FROM {$this->table_name} 
                WHERE role = :role AND is_active = 1 
                ORDER BY name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Existing methods...
    public function read() {
        $stmt = $this->conn->query("SELECT * FROM {$this->table_name} ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT * FROM {$this->table_name} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    // Get user by email
public function getByEmail($email) {
    $sql = "SELECT * FROM {$this->table_name} WHERE email = :email";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute(['email' => $email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

    public function update($id, $name, $email, $role) {
        $sql = "UPDATE {$this->table_name} SET name=:name, email=:email, role=:role WHERE id=:id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'role' => $role
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM {$this->table_name} WHERE id=:id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function countAll() {
        $sql = "SELECT COUNT(*) as total FROM {$this->table_name}";
        $stmt = $this->conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
}
?>