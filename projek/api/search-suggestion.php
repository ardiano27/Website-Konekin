<?php
// api/search-suggestions.php
session_start();
require_once '../config/Database.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$suggestions = [];

if (!empty($query) && strlen($query) >= 2) {
    $database = new DatabaseConnection();
    $conn = $database->getConnection();
    
    $user_type = $_SESSION['user_type'] ?? 'creative';
    
    try {
        if ($user_type === 'creative') {
            $sql = "SELECT DISTINCT title as text, 'project' as type 
                    FROM projects 
                    WHERE status = 'open' AND title LIKE :query 
                    LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':query' => "%$query%"]);
            $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($user_type === 'umkm') {
            $sql = "SELECT DISTINCT full_name as text, 'creative' as type 
                    FROM users 
                    WHERE user_type = 'creative' AND full_name LIKE :query 
                    LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':query' => "%$query%"]);
            $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Search suggestions error: " . $e->getMessage());
    }
}

echo json_encode(['suggestions' => $suggestions]);
?>