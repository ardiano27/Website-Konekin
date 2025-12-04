<?php
// File: models/Users.php
require_once __DIR__ . '/../config/Database.php';

class User {
    private $conn;
    private $table_name = "users";

    public function __construct() {
        $database = new DatabaseConnection();
        $this->conn = $database->getConnection();
        if (!$this->conn) {
            throw new Exception("Database connection failed in User model.");
        }
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
    
    // Method untuk mendapatkan user berdasarkan email
    public function getByEmail($email) {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method untuk mendapatkan user berdasarkan ID
    public function getById($id) {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method untuk mendapatkan semua user (dengan pagination)
    public function getAll($limit = 10, $offset = 0) {
        $sql = "SELECT * FROM " . $this->table_name . " LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Method untuk mendapatkan user beserta data profil (UMKM/Creative Worker)
    public function getWithProfile($id) {
        $user = $this->getById($id);
        
        if (!$user) {
            return null;
        }
        
        // Memeriksa tipe user untuk mengambil profil yang benar
        if ($user['user_type'] === 'umkm') {
            $sql = "SELECT up.* FROM umkm_profiles up WHERE up.user_id = :user_id";
        } else if ($user['user_type'] === 'creative_worker') {
            $sql = "SELECT cp.* FROM creative_profiles cp WHERE cp.user_id = :user_id";
        } else {
            // Jika user_type tidak terdaftar
            $user['profile'] = null;
            return $user;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['user_id' => $id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $user['profile'] = $profile;
        return $user;
    }

    public function emailExists($email) {
        $sql = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetchColumn() > 0;
    }
}