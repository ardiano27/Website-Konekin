<?php
class Portfolio {
    private $conn;
    private $table_name = "portfolios";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table_name} 
                (user_id, title, description, file_path, file_type, category) 
                VALUES (:user_id, :title, :description, :file_path, :file_type, :category)";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($data);
    }

    public function getByUser($user_id) {
        $sql = "SELECT * FROM {$this->table_name} WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFeatured() {
        $sql = "SELECT p.*, u.name as user_name 
                FROM {$this->table_name} p 
                LEFT JOIN users u ON p.user_id = u.id 
                WHERE p.is_featured = 1 
                ORDER BY p.created_at DESC 
                LIMIT 6";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>