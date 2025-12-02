<?php
include "check_login.php";
require_once 'config/Database.php';
require_once 'notificationsmanager.php';

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

$proposal_id = $_GET['proposal_id'] ?? 0;

if ($proposal_id === 0) {
    header("Location: projects.php");
    exit;
}

$database = new DatabaseConnection();
$conn = $database->getConnection();
$notificationManager = new NotificationManager();

// Get proposal data
$sql = "
    SELECT 
        pr.*,
        p.title as project_title,
        p.umkm_user_id,
        p.id as project_id,
        pr.creative_user_id
    FROM proposals pr
    JOIN projects p ON pr.project_id = p.id
    WHERE pr.id = :proposal_id
";

$stmt = $conn->prepare($sql);
$stmt->execute([':proposal_id' => $proposal_id]);
$proposal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposal) {
    header("Location: projects.php");
    exit;
}

// Verify UMKM owns the project
if ($proposal['umkm_user_id'] != $_SESSION['user_id']) {
    header("Location: projects.php");
    exit;
}

try {
    // Update proposal status to accepted
    $update_sql = "UPDATE proposals SET status = 'accepted' WHERE id = :proposal_id";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([':proposal_id' => $proposal_id]);
    
    // Update project status to in_progress
    $update_project_sql = "UPDATE projects SET status = 'in_progress' WHERE id = :project_id";
    $update_project_stmt = $conn->prepare($update_project_sql);
    $update_project_stmt->execute([':project_id' => $proposal['project_id']]);
    
    // Reject all other proposals for this project
    $reject_others_sql = "UPDATE proposals SET status = 'rejected' WHERE project_id = :project_id AND id != :proposal_id";
    $reject_others_stmt = $conn->prepare($reject_others_sql);
    $reject_others_stmt->execute([
        ':project_id' => $proposal['project_id'],
        ':proposal_id' => $proposal_id
    ]);
    
    // Create notification for creative worker
    $notificationManager->notifyProposalAccepted(
        $proposal_id,
        $proposal['creative_user_id'],
        $_SESSION['user_id'],
        $proposal['project_id']
    );
    
    // Redirect back with success message
    header("Location: project-proposals.php?id=" . $proposal['project_id'] . "&success=Proposal berhasil diterima");
    exit;
    
} catch (PDOException $e) {
    header("Location: project-proposals.php?id=" . $proposal['project_id'] . "&error=Terjadi kesalahan: " . $e->getMessage());
    exit;
}
?>