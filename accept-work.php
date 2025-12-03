<?php
// accept-work.php
include "check_login.php";

// Hanya UMKM yang bisa menerima hasil
if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Validasi submission_id
$submission_id = $_GET['submission_id'] ?? 0;
if (!$submission_id) {
    header("Location: projects.php");
    exit;
}

try {
    // Mulai transaksi
    $conn->beginTransaction();
    
    // 1. Update status work submission menjadi 'accepted'
    $sql_submission = "UPDATE work_submissions 
                      SET status = 'accepted',
                          reviewed_at = NOW(),
                          reviewed_by = :user_id
                      WHERE id = :submission_id
                      AND status = 'submitted'";
    
    $stmt_submission = $conn->prepare($sql_submission);
    $stmt_submission->execute([
        ':user_id' => $_SESSION['user_id'],
        ':submission_id' => $submission_id
    ]);
    
    if ($stmt_submission->rowCount() > 0) {
        // 2. Ambil contract_id dari work submission
        $sql_contract = "SELECT contract_id FROM work_submissions WHERE id = :submission_id";
        $stmt_contract = $conn->prepare($sql_contract);
        $stmt_contract->execute([':submission_id' => $submission_id]);
        $contract = $stmt_contract->fetch(PDO::FETCH_ASSOC);
        
        if ($contract) {
            // 3. Update contract status menjadi 'completed'
            $sql_update_contract = "UPDATE contracts 
                                  SET status = 'completed',
                                      progress_percentage = 100,
                                      end_date = CURDATE(),
                                      updated_at = NOW()
                                  WHERE id = :contract_id
                                  AND status = 'active'";
            
            $stmt_update_contract = $conn->prepare($sql_update_contract);
            $stmt_update_contract->execute([':contract_id' => $contract['contract_id']]);
            
            // 4. Update project status menjadi 'completed'
            $sql_update_project = "UPDATE projects p
                                 JOIN contracts c ON p.id = c.project_id
                                 SET p.status = 'completed',
                                     p.updated_at = NOW()
                                 WHERE c.id = :contract_id";
            
            $stmt_update_project = $conn->prepare($sql_update_project);
            $stmt_update_project->execute([':contract_id' => $contract['contract_id']]);
            
            // 5. Ambil data untuk notifikasi
            $sql_notif_data = "SELECT 
                                c.title,
                                c.creative_user_id,
                                c.umkm_user_id,
                                u.full_name as creative_name,
                                u2.full_name as umkm_name
                              FROM contracts c
                              JOIN users u ON c.creative_user_id = u.id
                              JOIN users u2 ON c.umkm_user_id = u2.id
                              WHERE c.id = :contract_id";
            
            $stmt_notif = $conn->prepare($sql_notif_data);
            $stmt_notif->execute([':contract_id' => $contract['contract_id']]);
            $contract_data = $stmt_notif->fetch(PDO::FETCH_ASSOC);
            
            if ($contract_data) {
                // 6. Buat notifikasi untuk creative worker
                $sql_notif_creative = "INSERT INTO notifications 
                                      (user_id, title, message, notification_type, related_entity_type, related_entity_id, is_read, created_at)
                                      VALUES (:user_id, :title, :message, 'project', 'contract', :contract_id, 0, NOW())";
                
                $stmt_notif_creative = $conn->prepare($sql_notif_creative);
                $stmt_notif_creative->execute([
                    ':user_id' => $contract_data['creative_user_id'],
                    ':title' => 'Hasil Diterima!',
                    ':message' => 'UMKM ' . htmlspecialchars($contract_data['umkm_name']) . ' telah menerima hasil pekerjaan untuk proyek "' . htmlspecialchars($contract_data['title']) . '".',
                    ':contract_id' => $contract['contract_id']
                ]);
                
                // 7. Buat notifikasi untuk UMKM
                $sql_notif_umkm = "INSERT INTO notifications 
                                  (user_id, title, message, notification_type, related_entity_type, related_entity_id, is_read, created_at)
                                  VALUES (:user_id, :title, :message, 'project', 'contract', :contract_id, 0, NOW())";
                
                $stmt_notif_umkm = $conn->prepare($sql_notif_umkm);
                $stmt_notif_umkm->execute([
                    ':user_id' => $contract_data['umkm_user_id'],
                    ':title' => 'Proyek Selesai!',
                    ':message' => 'Anda telah menerima hasil pekerjaan untuk proyek "' . htmlspecialchars($contract_data['title']) . '". Proyek dinyatakan selesai.',
                    ':contract_id' => $contract['contract_id']
                ]);
                
                // 8. Update completed_projects count untuk creative worker
                $sql_update_count = "UPDATE creative_profiles 
                                   SET completed_projects = completed_projects + 1,
                                       rating = (
                                           SELECT AVG(rating) 
                                           FROM reviews 
                                           WHERE reviewed_id = :creative_id 
                                           AND review_type = 'umkm_to_creative'
                                       )
                                   WHERE user_id = :creative_id";
                
                $stmt_update_count = $conn->prepare($sql_update_count);
                $stmt_update_count->execute([':creative_id' => $contract_data['creative_user_id']]);
                
                // 9. Log aktivitas
                $sql_activity = "INSERT INTO activity_logs 
                                (user_id, action, entity_type, entity_id, details, created_at)
                                VALUES (:user_id, 'accept_work', 'contract', :contract_id, 
                                        :details, NOW())";
                
                $stmt_activity = $conn->prepare($sql_activity);
                $stmt_activity->execute([
                    ':user_id' => $_SESSION['user_id'],
                    ':contract_id' => $contract['contract_id'],
                    ':details' => json_encode([
                        'work_submission_id' => $submission_id,
                        'action' => 'accepted',
                        'timestamp' => date('Y-m-d H:i:s')
                    ])
                ]);
            }
            
            // Commit transaksi
            $conn->commit();
            
            // Redirect dengan pesan sukses
            header("Location: project-progress.php?id=" . $contract['project_id'] . "&success=1");
            exit;
        }
    }
    
    // Jika tidak ada baris yang diupdate, rollback
    $conn->rollBack();
    header("Location: project-progress.php?id=" . $_GET['project_id'] . "&error=1");
    exit;
    
} catch (PDOException $e) {
    // Rollback jika ada error
    $conn->rollBack();
    error_log("Error accepting work: " . $e->getMessage());
    header("Location: project-progress.php?id=" . $_GET['project_id'] . "&error=1");
    exit;
}
?>