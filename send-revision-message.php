<?php
// send-revision-message.php
include "check_login.php";

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$revision_id = $_POST['revision_id'] ?? 0;
$message = $_POST['message'] ?? '';

if (empty($revision_id) || empty(trim($message))) {
    $_SESSION['error'] = "Pesan tidak boleh kosong";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
    exit;
}

// Check if user has access to this revision
$check_sql = "SELECT id FROM revisions 
              WHERE id = :revision_id 
              AND (requested_by_user_id = :user_id OR requested_to_user_id = :user_id)";
$stmt = $conn->prepare($check_sql);
$stmt->execute([
    ':revision_id' => $revision_id,
    ':user_id' => $_SESSION['user_id']
]);

if (!$stmt->fetch()) {
    $_SESSION['error'] = "Akses ditolak";
    header("Location: dashboard.php");
    exit;
}

try {
    // Insert message
    $insert_sql = "INSERT INTO revision_messages 
                  (revision_id, user_id, message, created_at)
                  VALUES (:revision_id, :user_id, :message, NOW())";
    
    $stmt = $conn->prepare($insert_sql);
    $stmt->execute([
        ':revision_id' => $revision_id,
        ':user_id' => $_SESSION['user_id'],
        ':message' => trim($message)
    ]);
    
    // Get revision details for notification
    $revision_sql = "SELECT r.*, u.full_name as other_user_name
                    FROM revisions r
                    JOIN users u ON (r.requested_by_user_id = u.id AND r.requested_by_user_id != :user_id)
                        OR (r.requested_to_user_id = u.id AND r.requested_to_user_id != :user_id)
                    WHERE r.id = :revision_id";
    
    $stmt = $conn->prepare($revision_sql);
    $stmt->execute([
        ':revision_id' => $revision_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $revision = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($revision) {
        // Create notification for the other user
        $notification_sql = "INSERT INTO notifications 
                            (user_id, title, message, notification_type,
                             related_entity_type, related_entity_id, created_at)
                            VALUES (:user_id, 'Pesan Revisi Baru', 
                                    '" . $_SESSION['full_name'] . " mengirim pesan pada diskusi revisi', 
                                    'project', 'revision', :revision_id, NOW())";
        
        $other_user_id = ($revision['requested_by_user_id'] == $_SESSION['user_id']) 
            ? $revision['requested_to_user_id'] 
            : $revision['requested_by_user_id'];
        
        $stmt = $conn->prepare($notification_sql);
        $stmt->execute([
            ':user_id' => $other_user_id,
            ':revision_id' => $revision_id
        ]);
    }
    
    $_SESSION['success'] = "Pesan berhasil dikirim";
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Gagal mengirim pesan: " . $e->getMessage();
    error_log("Error sending revision message: " . $e->getMessage());
}

header("Location: revision-detail.php?id=" . $revision_id);
exit;
?>