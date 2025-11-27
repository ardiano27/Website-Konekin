<?php
class Certificate {
    private $conn;
    private $table_name = "certificates";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table_name} 
                (user_id, title, issuer, issue_date, file_path, credential_url) 
                VALUES (:user_id, :title, :issuer, :issue_date, :file_path, :credential_url)";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($data);
    }

    public function getByUser($user_id) {
        $sql = "SELECT * FROM {$this->table_name} WHERE user_id = :user_id ORDER BY issue_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>