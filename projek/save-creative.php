<?php
session_start();
require_once 'config/Database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'umkm') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['creative_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Creative ID required']);
    exit;
}

$creative_id = intval($_POST['creative_id']);
$umkm_id = $_SESSION['user_id'];

try {
    $database = new DatabaseConnection();
    $conn = $database->getConnection();
    
    // Check if already saved (but first, check if table exists)
    $table_exists = false;
    try {
        $check_table = $conn->query("SHOW TABLES LIKE 'saved_creatives'");
        $table_exists = $check_table->rowCount() > 0;
    } catch (Exception $e) {
        $table_exists = false;
    }
    
    if (!$table_exists) {
        // Create table if it doesn't exist
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS `saved_creatives` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `umkm_user_id` int(11) NOT NULL,
                `creative_user_id` int(11) NOT NULL,
                `saved_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_saved_creative` (`umkm_user_id`,`creative_user_id`),
                KEY `umkm_user_id` (`umkm_user_id`),
                KEY `creative_user_id` (`creative_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
        $conn->exec($create_table_sql);
    }
    
    // Check if already saved
    $check_sql = "SELECT id FROM saved_creatives WHERE umkm_user_id = ? AND creative_user_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->execute([$umkm_id, $creative_id]);
    
    if ($stmt->rowCount() > 0) {
        // Remove from saved
        $delete_sql = "DELETE FROM saved_creatives WHERE umkm_user_id = ? AND creative_user_id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->execute([$umkm_id, $creative_id]);
        
        $message = 'Kreator berhasil dihapus dari daftar tersimpan';
    } else {
        // Add to saved
        $insert_sql = "INSERT INTO saved_creatives (umkm_user_id, creative_user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->execute([$umkm_id, $creative_id]);
        
        $message = 'Kreator berhasil disimpan';
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}