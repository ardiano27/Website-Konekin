<?php
session_start();
include "check_login.php";

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

$proposal_id = $_GET['proposal_id'] ?? 0;
$reason = $_GET['reason'] ?? '';

if (!$proposal_id) {
    $_SESSION['error_message'] = "Proposal ID tidak valid.";
    header("Location: projects.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

try {
    // Ambil data proposal untuk validasi
    $proposal_sql = "
        SELECT p.*, pr.umkm_user_id, pr.title as project_title
        FROM proposals p
        JOIN projects pr ON p.project_id = pr.id
        WHERE p.id = :proposal_id AND pr.umkm_user_id = :user_id
    ";
    
    $proposal_stmt = $conn->prepare($proposal_sql);
    $proposal_stmt->execute([
        ':proposal_id' => $proposal_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    $proposal = $proposal_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposal) {
        throw new Exception("Proposal tidak ditemukan atau Anda tidak memiliki akses.");
    }

    // Update status proposal menjadi rejected
    $update_sql = "UPDATE proposals SET status = 'rejected' WHERE id = :proposal_id";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([':proposal_id' => $proposal_id]);

    // Buat notifikasi untuk creative worker
    if (!empty($reason)) {
        $message = "Proposal Anda untuk project '{$proposal['project_title']}' telah ditolak. Alasan: " . $reason;
    } else {
        $message = "Proposal Anda untuk project '{$proposal['project_title']}' telah ditolak.";
    }

    $notification_sql = "
        INSERT INTO notifications (user_id, title, message, notification_type, related_entity_type, related_entity_id)
        VALUES (:user_id, :title, :message, 'proposal', 'project', :project_id)
    ";
    
    $notification_stmt = $conn->prepare($notification_sql);
    $notification_stmt->execute([
        ':user_id' => $proposal['creative_user_id'],
        ':title' => 'Proposal Ditolak',
        ':message' => $message,
        ':project_id' => $proposal['project_id']
    ]);

    $_SESSION['success_message'] = "Proposal berhasil ditolak.";
    header("Location: project-proposals.php?id=" . $proposal['project_id']);
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
    header("Location: project-proposals.php?id=" . ($proposal['project_id'] ?? ''));
    exit;
}
?>