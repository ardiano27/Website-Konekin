<?php
// delete-project.php
include "check_login.php";

// Hanya UMKM yang bisa menghapus proyeknya sendiri
if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

$project_id = $_GET['id'] ?? 0;
$confirm = $_GET['confirm'] ?? 0;

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Ambil detail proyek untuk validasi
$project_sql = "SELECT * FROM projects WHERE id = :id AND umkm_user_id = :user_id";
$project_stmt = $conn->prepare($project_sql);
$project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
$project = $project_stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    $_SESSION['error'] = 'Proyek tidak ditemukan atau Anda tidak memiliki akses.';
    header("Location: projects.php");
    exit;
}

// Cek apakah proyek bisa dihapus (hanya draft dan open yang bisa dihapus)
$cannot_delete_statuses = ['in_progress', 'completed'];
if (in_array($project['status'], $cannot_delete_statuses)) {
    $_SESSION['error'] = 'Proyek yang sedang berjalan atau sudah selesai tidak dapat dihapus.';
    header("Location: view-project.php?id=" . $project_id);
    exit;
}

// Konfirmasi penghapusan
if ($confirm == 1) {
    try {
        $conn->beginTransaction();
        
        // 1. Hapus proposal terkait
        $delete_proposals_sql = "DELETE FROM proposals WHERE project_id = :project_id";
        $delete_proposals_stmt = $conn->prepare($delete_proposals_sql);
        $delete_proposals_stmt->execute([':project_id' => $project_id]);
        
        // 2. Hapus proyek yang disimpan
        $delete_saved_sql = "DELETE FROM saved_projects WHERE project_id = :project_id";
        $delete_saved_stmt = $conn->prepare($delete_saved_sql);
        $delete_saved_stmt->execute([':project_id' => $project_id]);
        
        // 3. Hapus lampiran fisik
        $attachment_urls = json_decode($project['attachment_urls'] ?? '[]', true) ?: [];
        foreach ($attachment_urls as $file_path) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // 4. Hapus proyek
        $delete_project_sql = "DELETE FROM projects WHERE id = :id AND umkm_user_id = :user_id";
        $delete_project_stmt = $conn->prepare($delete_project_sql);
        $delete_project_stmt->execute([
            ':id' => $project_id,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        // 5. Buat log aktivitas
        $log_sql = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) 
                    VALUES (:user_id, 'delete_project', 'project', :project_id, :details)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':project_id' => $project_id,
            ':details' => json_encode([
                'title' => $project['title'],
                'category' => $project['category'],
                'status' => $project['status']
            ])
        ]);
        
        $conn->commit();
        
        $_SESSION['success'] = 'Proyek berhasil dihapus secara permanen.';
        header("Location: projects.php");
        exit;
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'Terjadi kesalahan saat menghapus proyek: ' . $e->getMessage();
        header("Location: view-project.php?id=" . $project_id);
        exit;
    }
} else {
    // Redirect ke halaman konfirmasi
    header("Location: view-project.php?id=" . $project_id . "#confirmDeleteModal");
    exit;
}
?>