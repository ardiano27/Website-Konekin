<?php
include "check_login.php";

if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

$contract_id = $_POST['contract_id'] ?? 0;
$progress_percentage = $_POST['progress_percentage'] ?? 0;
$description = $_POST['description'] ?? '';

if (!$contract_id) {
    $_SESSION['error_message'] = "Contract ID tidak valid.";
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();

    // 1. Update progress di contracts
    $update_contract_sql = "UPDATE contracts SET progress_percentage = :progress WHERE id = :contract_id";
    $update_contract_stmt = $conn->prepare($update_contract_sql);
    $update_contract_stmt->execute([
        ':progress' => $progress_percentage,
        ':contract_id' => $contract_id
    ]);

    // 2. Tambah progress update history
    $progress_sql = "
        INSERT INTO contract_progress (contract_id, creative_user_id, progress_percentage, description)
        VALUES (:contract_id, :creative_id, :progress, :description)
    ";
    $progress_stmt = $conn->prepare($progress_sql);
    $progress_stmt->execute([
        ':contract_id' => $contract_id,
        ':creative_id' => $_SESSION['user_id'],
        ':progress' => $progress_percentage,
        ':description' => $description
    ]);

    // 3. Buat notifikasi untuk UMKM
    $notification_sql = "
        INSERT INTO notifications (user_id, title, message, notification_type, related_entity_type, related_entity_id)
        SELECT umkm_user_id, 'Progress Update', 
               CONCAT('Progress proyek telah diupdate menjadi ', :progress, '%. ', :description),
               'project', 'contract', :contract_id
        FROM contracts WHERE id = :contract_id
    ";
    $notification_stmt = $conn->prepare($notification_sql);
    $notification_stmt->execute([
        ':progress' => $progress_percentage,
        ':description' => $description ?: 'Tidak ada deskripsi tambahan.',
        ':contract_id' => $contract_id
    ]);

    $conn->commit();

    $_SESSION['success_message'] = "Progress berhasil diupdate menjadi $progress_percentage%!";
    header("Location: contract-details.php?id=$contract_id");
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
    header("Location: contract-details.php?id=$contract_id");
    exit;
}
?>