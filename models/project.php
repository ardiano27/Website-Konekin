<?php
class Project {
    private $conn;
    private $table_name = "projects";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table_name} 
                (title, description, category, budget, deadline, user_id, file_path) 
                VALUES (:title, :description, :category, :budget, :deadline, :user_id, :file_path)";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($data);
    }

    public function getByUser($user_id) {
        $sql = "SELECT * FROM {$this->table_name} WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllOpen() {
        $sql = "SELECT p.*, u.name as user_name, u.role as user_role 
                FROM {$this->table_name} p 
                LEFT JOIN users u ON p.user_id = u.id 
                WHERE p.status = 'open' 
                ORDER BY p.created_at DESC";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT p.*, u.name as user_name, u.email as user_email 
                FROM {$this->table_name} p 
                LEFT JOIN users u ON p.user_id = u.id 
                WHERE p.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>