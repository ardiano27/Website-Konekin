<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin-payments.php");
    exit;
}

$evidence_id = $_POST['evidence_id'] ?? 0;
$contract_id = $_POST['contract_id'] ?? 0;
$status = $_POST['status'] ?? '';
$admin_notes = $_POST['admin_notes'] ?? '';

if (!$evidence_id || !$contract_id || !in_array($status, ['verified', 'rejected'])) {
    $_SESSION['error_message'] = "Data tidak valid!";
    header("Location: admin-payments.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();
    
    // Update status bukti pembayaran
    $update_evidence_sql = "UPDATE payment_evidences 
                           SET status = :status, 
                               admin_notes = :admin_notes,
                               verified_by = :admin_id,
                               verified_at = NOW()
                           WHERE id = :evidence_id";
    
    $update_evidence_stmt = $conn->prepare($update_evidence_sql);
    $update_evidence_stmt->execute([
        ':status' => $status,
        ':admin_notes' => $admin_notes,
        ':admin_id' => $_SESSION['user_id'],
        ':evidence_id' => $evidence_id
    ]);
    
    // Jika diverifikasi, update status pembayaran kontrak
    if ($status === 'verified') {
        $update_contract_sql = "UPDATE contracts 
                               SET payment_status = 'paid', 
                                   updated_at = NOW()
                               WHERE id = :contract_id";
        
        $update_contract_stmt = $conn->prepare($update_contract_sql);
        $update_contract_stmt->execute([':contract_id' => $contract_id]);
        
        // Log perubahan
        $log_sql = "INSERT INTO payment_logs (contract_id, admin_id, old_status, new_status, notes) 
                   VALUES (:contract_id, :admin_id, 'pending', 'paid', :notes)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':contract_id' => $contract_id,
            ':admin_id' => $_SESSION['user_id'],
            ':notes' => "Bukti pembayaran diverifikasi: " . $admin_notes
        ]);
        
        // Kirim notifikasi ke kedua pihak
        $contract_sql = "SELECT creative_user_id, umkm_user_id, title FROM contracts WHERE id = :contract_id";
        $contract_stmt = $conn->prepare($contract_sql);
        $contract_stmt->execute([':contract_id' => $contract_id]);
        $contract = $contract_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($contract) {
            require_once 'notificationsmanager.php';
            $notificationManager = new NotificationManager();
            
            $message = "Bukti pembayaran untuk proyek '{$contract['title']}' telah diverifikasi. Status pembayaran: PAID";
            
            $notificationManager->createNotification(
                $contract['creative_user_id'],
                "Pembayaran Diverifikasi",
                $message,
                'payment',
                'contract',
                $contract_id
            );
            
            $notificationManager->createNotification(
                $contract['umkm_user_id'],
                "Pembayaran Diverifikasi",
                $message,
                'payment',
                'contract',
                $contract_id
            );
        }
    } else {
        // Jika ditolak, kirim notifikasi ke UMKM
        $contract_sql = "SELECT umkm_user_id, title FROM contracts WHERE id = :contract_id";
        $contract_stmt = $conn->prepare($contract_sql);
        $contract_stmt->execute([':contract_id' => $contract_id]);
        $contract = $contract_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($contract) {
            require_once 'notificationsmanager.php';
            $notificationManager = new NotificationManager();
            
            $message = "Bukti pembayaran untuk proyek '{$contract['title']}' ditolak. Alasan: " . $admin_notes;
            
            $notificationManager->createNotification(
                $contract['umkm_user_id'],
                "Bukti Pembayaran Ditolak",
                $message,
                'payment',
                'contract',
                $contract_id
            );
        }
    }
    
    $conn->commit();
    
    $_SESSION['success_message'] = "Bukti pembayaran berhasil diverifikasi!";
    
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
}

header("Location: admin-payments.php");
exit;
?>