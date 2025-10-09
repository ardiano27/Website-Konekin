<?php
require_once "config/Database.php";

class User {
    private $conn;
    private $table_name = "users";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // Method untuk membuat user baru
    public function create($uuid, $email, $password_hash, $user_type, $full_name, $phone = null) {
        $sql = "INSERT INTO " . $this->table_name . " (uuid, email, password_hash, user_type, full_name, phone) 
                VALUES (:uuid, :email, :password_hash, :user_type, :full_name, :phone)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            'uuid' => $uuid,
            'email' => $email,
            'password_hash' => $password_hash,
            'user_type' => $user_type,
            'full_name' => $full_name,
            'phone' => $phone
        ]);
    }
    
    // Method untuk mendapatkan user by email
    public function getByEmail($email) {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Method untuk mendapatkan user by ID
    public function getById($id) {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Method untuk mendapatkan user by UUID
    public function getByUUID($uuid) {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE uuid = :uuid";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['uuid' => $uuid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Method untuk update profil user
    public function update($id, $full_name, $phone, $avatar_url = null) {
        $sql = "UPDATE " . $this->table_name . " SET full_name = :full_name, phone = :phone";
        $params = [
            'id' => $id,
            'full_name' => $full_name,
            'phone' => $phone
        ];
        
        if ($avatar_url) {
            $sql .= ", avatar_url = :avatar_url";
            $params['avatar_url'] = $avatar_url;
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }
    
    // Method untuk verifikasi email
    public function verifyEmail($id) {
        $sql = "UPDATE " . $this->table_name . " SET is_verified = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    // Method untuk menonaktifkan akun
    public function deactivate($id) {
        $sql = "UPDATE " . $this->table_name . " SET is_active = 0 WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    // Method untuk mendapatkan semua users (dengan pagination)
    public function getAll($limit = 10, $offset = 0) {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE is_active = 1 ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Method untuk mendapatkan user dengan profil lengkap
    public function getWithProfile($id) {
        $user = $this->getById($id);
        
        if (!$user) {
            return null;
        }
        
        if ($user['user_type'] === 'umkm') {
            $sql = "SELECT up.* FROM umkm_profiles up WHERE up.user_id = :user_id";
        } else {
            $sql = "SELECT cp.* FROM creative_profiles cp WHERE cp.user_id = :user_id";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['user_id' => $id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $user['profile'] = $profile;
        return $user;
    }
    
    // Method untuk mengecek apakah email sudah terdaftar
    public function emailExists($email) {
        $sql = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetchColumn() > 0;
    }
    
    // Method untuk update password
    public function updatePassword($id, $new_password_hash) {
        $sql = "UPDATE " . $this->table_name . " SET password_hash = :password_hash WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'password_hash' => $new_password_hash
        ]);
    }
}