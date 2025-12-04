<?php
session_start();
include "check_login.php";

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

$proposal_id = $_GET['proposal_id'] ?? 0;

if (!$proposal_id) {
    $_SESSION['error_message'] = "Proposal ID tidak valid.";
    header("Location: projects.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();

    // 1. Ambil data proposal dan project
    $proposal_sql = "
        SELECT p.*, pr.umkm_user_id, pr.title as project_title, pr.description as project_description
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

    // 2. Update status proposal menjadi accepted
    $update_proposal_sql = "UPDATE proposals SET status = 'accepted' WHERE id = :proposal_id";
    $update_proposal_stmt = $conn->prepare($update_proposal_sql);
    $update_proposal_stmt->execute([':proposal_id' => $proposal_id]);

    // 3. Update status project menjadi in_progress
    $update_project_sql = "UPDATE projects SET status = 'in_progress' WHERE id = :project_id";
    $update_project_stmt = $conn->prepare($update_project_sql);
    $update_project_stmt->execute([':project_id' => $proposal['project_id']]);

    // 4. Tolak semua proposal lainnya untuk project ini
    $reject_other_sql = "
        UPDATE proposals 
        SET status = 'rejected' 
        WHERE project_id = :project_id 
        AND id != :proposal_id 
        AND status = 'submitted'
    ";
    $reject_other_stmt = $conn->prepare($reject_other_sql);
    $reject_other_stmt->execute([
        ':project_id' => $proposal['project_id'],
        ':proposal_id' => $proposal_id
    ]);

    // 5.1. Ambil nomor rekening creative worker
    $bank_sql = "SELECT creative_bank_account FROM creative_profiles WHERE user_id = :creative_id";
    $bank_stmt = $conn->prepare($bank_sql);
    $bank_stmt->execute([':creative_id' => $proposal['creative_user_id']]);
    $creative_profile = $bank_stmt->fetch(PDO::FETCH_ASSOC);

    // 5.2. Generate unique project code
    $project_code = 'KONEKIN-' . strtoupper(uniqid());

    // 5.3. Buat kontrak baru dengan field tambahan
    $contract_uuid = uniqid('contract_', true);
    $contract_sql = "
        INSERT INTO contracts (
            uuid, project_id, proposal_id, creative_user_id, umkm_user_id,
            title, description, agreed_budget, timeline_days, status,
            start_date, project_code, creative_bank_account, created_at, updated_at
        ) VALUES (
            :uuid, :project_id, :proposal_id, :creative_user_id, :umkm_user_id,
            :title, :description, :agreed_budget, :timeline_days, :status,
            :start_date, :project_code, :creative_bank_account, NOW(), NOW()
        )
    ";
    
    $contract_stmt = $conn->prepare($contract_sql);
    $contract_stmt->execute([
        ':uuid' => $contract_uuid,
        ':project_id' => $proposal['project_id'],
        ':proposal_id' => $proposal_id,
        ':creative_user_id' => $proposal['creative_user_id'],
        ':umkm_user_id' => $_SESSION['user_id'],
        ':title' => $proposal['project_title'],
        ':description' => $proposal['project_description'],
        ':agreed_budget' => $proposal['proposed_budget'] ?? 0,
        ':timeline_days' => $proposal['timeline_days'] ?? 0,
        ':status' => 'active',
        ':start_date' => date('Y-m-d'),
        ':project_code' => $project_code,
        ':creative_bank_account' => $creative_profile['creative_bank_account'] ?? NULL
    ]);

    $contract_id = $conn->lastInsertId();

    // 6. Buat notifikasi untuk creative worker
    $notification_sql = "
        INSERT INTO notifications (user_id, title, message, notification_type, related_entity_type, related_entity_id)
        VALUES (:user_id, :title, :message, 'proposal', 'contract', :contract_id)
    ";
    
    $notification_stmt = $conn->prepare($notification_sql);
    $notification_stmt->execute([
        ':user_id' => $proposal['creative_user_id'],
        ':title' => 'Proposal Diterima!',
        ':message' => 'Selamat! Proposal Anda untuk project "' . $proposal['project_title'] . '" telah diterima.',
        ':contract_id' => $contract_id
    ]);

    $conn->commit();

    $_SESSION['success_message'] = "Proposal berhasil diterima! Kontrak telah dibuat.";
    header("Location: contract-details.php?id=" . $contract_id);
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
    header("Location: project-proposals.php?id=" . ($proposal['project_id'] ?? ''));
    exit;
}
?>