<?php
// submit-revision.php
include "check_login.php";

if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$revision_id = $_GET['id'] ?? 0;

// Ambil data revisi
$sql = "SELECT r.*, c.title as project_title, u.full_name as umkm_name,
               c.id as contract_id, c.creative_user_id, c.umkm_user_id
        FROM revisions r
        JOIN contracts c ON r.contract_id = c.id
        JOIN users u ON r.requested_by_user_id = u.id
        WHERE r.id = :revision_id 
        AND r.requested_to_user_id = :user_id
        AND r.status = 'pending'";

$revision = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':revision_id' => $revision_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $revision = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

if (!$revision) {
    header("Location: all-projects.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $revision_notes = $_POST['revision_notes'] ?? '';
    $files = $_FILES['revision_files'] ?? [];
    
    try {
        $conn->beginTransaction();
        
        // Upload revision files
        $revision_file_urls = [];
        if (!empty($files['name'][0])) {
            $upload_dir = 'uploads/revisions-work/' . $revision_id . '/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . basename($files['name'][$i]);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                        $revision_file_urls[] = $file_path;
                    }
                }
            }
        }
        
        // PERBAIKAN: Update revision status TANPA kolom revision_files
        $update_sql = "UPDATE revisions 
                      SET status = 'completed', 
                          admin_notes = :notes,
                          completed_at = NOW(),
                          updated_at = NOW()
                      WHERE id = :revision_id";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->execute([
            ':notes' => $revision_notes,
            ':revision_id' => $revision_id
        ]);
        
        // PERBAIKAN: Simpan file revisi ke work_submissions sebagai revisi baru
        if (!empty($revision_file_urls) && $revision['work_submission_id']) {
            // Ambil data work submission lama
            $old_ws_sql = "SELECT * FROM work_submissions WHERE id = :submission_id";
            $stmt = $conn->prepare($old_ws_sql);
            $stmt->execute([':submission_id' => $revision['work_submission_id']]);
            $old_work = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($old_work) {
                // Insert work submission baru sebagai hasil revisi
                $insert_ws_sql = "INSERT INTO work_submissions 
                                 (contract_id, creative_user_id, work_title, 
                                  work_description, work_url, final_files, 
                                  preview_images, delivery_notes, status, submitted_at)
                                 VALUES (:contract_id, :creative_id, :title, 
                                         :description, :work_url, :final_files,
                                         :preview_images, :notes, 'submitted', NOW())";
                
                // Buat preview images dari file yang diupload
                $preview_images = [];
                foreach ($revision_file_urls as $file) {
                    $file_ext = pathinfo($file, PATHINFO_EXTENSION);
                    if (in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $preview_images[] = $file;
                    }
                }
                
                $stmt = $conn->prepare($insert_ws_sql);
                $stmt->execute([
                    ':contract_id' => $revision['contract_id'],
                    ':creative_id' => $_SESSION['user_id'],
                    ':title' => $old_work['work_title'] . ' (Revisi #' . $revision['revision_number'] . ')',
                    ':description' => $old_work['work_description'] . "\n\n--- HASIL REVISI ---\n" . $revision_notes,
                    ':work_url' => $old_work['work_url'],
                    ':final_files' => json_encode($revision_file_urls),
                    ':preview_images' => !empty($preview_images) ? json_encode($preview_images) : null,
                    ':notes' => 'Hasil revisi permintaan: ' . $revision['title']
                ]);
                
                $new_submission_id = $conn->lastInsertId();
                
                // Update revisi untuk merujuk ke work submission baru
                $update_rev_sql = "UPDATE revisions 
                                  SET work_submission_id = :new_submission_id,
                                      updated_at = NOW()
                                  WHERE id = :revision_id";
                $stmt = $conn->prepare($update_rev_sql);
                $stmt->execute([
                    ':new_submission_id' => $new_submission_id,
                    ':revision_id' => $revision_id
                ]);
            }
        }
        
        // Update contract progress menjadi 95% setelah revisi selesai
        $update_contract_sql = "UPDATE contracts 
                               SET progress_percentage = 95, updated_at = NOW()
                               WHERE id = :contract_id AND progress_percentage < 95";
        $stmt = $conn->prepare($update_contract_sql);
        $stmt->execute([':contract_id' => $revision['contract_id']]);
        
        // Create notification for UMKM
        $notification_sql = "INSERT INTO notifications 
                            (user_id, title, message, notification_type, 
                             related_entity_type, related_entity_id, created_at)
                            VALUES (:user_id, 'Revisi Selesai', 
                                    'Creative " . $_SESSION['full_name'] . " telah menyelesaikan revisi untuk proyek \"" . 
                                    $revision['project_title'] . "\". Silakan review kembali.', 
                                    'project', 'revision', :revision_id, NOW())";
        
        $stmt = $conn->prepare($notification_sql);
        $stmt->execute([
            ':user_id' => $revision['requested_by_user_id'],
            ':revision_id' => $revision_id
        ]);
        
        $conn->commit();
        
        $_SESSION['success'] = "Revisi berhasil disubmit!";
        header("Location: project-progress1.php?contract_id=" . $revision['contract_id']);
        exit;
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Gagal submit revisi: " . $e->getMessage();
        error_log("Error submitting revision: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Submit Revisi - Konekin</title>
    <!-- Include your CSS/JS -->
</head>
<body>
    <div class="container">
        <h1>Submit Revisi</h1>
        <p>Revisi #<?php echo $revision['revision_number']; ?> untuk: 
           <strong><?php echo htmlspecialchars($revision['umkm_name']); ?></strong></p>
        
        <div class="card mb-4">
    <div class="card-header">
        <h5>Permintaan Revisi dari UMKM</h5>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <strong>Proyek:</strong> <?php echo htmlspecialchars($revision['project_title']); ?>
        </div>
        <div class="mb-3">
            <strong>Revisi #<?php echo $revision['revision_number']; ?>:</strong>
            <h6><?php echo htmlspecialchars($revision['title']); ?></h6>
        </div>
        <div class="mb-3">
            <strong>Deskripsi:</strong>
            <p class="mb-0"><?php echo nl2br(htmlspecialchars($revision['description'])); ?></p>
        </div>
        
        <?php if ($revision['deadline']): ?>
            <div class="mb-3">
                <strong>Deadline:</strong> 
                <?php echo date('d M Y', strtotime($revision['deadline'])); ?>
                <?php 
                $days_left = floor((strtotime($revision['deadline']) - time()) / (60 * 60 * 24));
                if ($days_left > 0) {
                    echo "<span class='badge bg-success ms-2'>$days_left hari tersisa</span>";
                } elseif ($days_left == 0) {
                    echo "<span class='badge bg-warning ms-2'>Deadline hari ini</span>";
                } else {
                    echo "<span class='badge bg-danger ms-2'>Terlambat " . abs($days_left) . " hari</span>";
                }
                ?>
            </div>
        <?php endif; ?>
        
        <?php if ($revision['attachment_urls']): 
            $attachments = json_decode($revision['attachment_urls'], true);
            if (is_array($attachments) && count($attachments) > 0): ?>
                <div class="mb-3">
                    <strong>Referensi dari UMKM:</strong>
                    <div class="mt-2">
                        <?php foreach ($attachments as $attachment): 
                            $file_ext = pathinfo($attachment, PATHINFO_EXTENSION);
                            $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        ?>
                            <?php if ($is_image): ?>
                                <a href="<?php echo htmlspecialchars($attachment); ?>" 
                                   target="_blank" 
                                   class="d-inline-block me-2 mb-2">
                                    <img src="<?php echo htmlspecialchars($attachment); ?>" 
                                         alt="Referensi" 
                                         style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">
                                </a>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($attachment); ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-outline-primary me-2 mb-2">
                                    <i class="fas fa-file me-1"></i>
                                    <?php echo strtoupper($file_ext); ?> File
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<form method="POST" enctype="multipart/form-data">
    <div class="card mb-4">
        <div class="card-header">
            <h5>Submit Hasil Revisi</h5>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <label class="form-label">Catatan Perubahan (Opsional)</label>
                <textarea name="revision_notes" class="form-control" rows="4"
                          placeholder="Jelaskan perubahan yang telah dilakukan, bagian mana yang diperbaiki, dll..."></textarea>
                <div class="form-text">Penjelasan ini akan membantu UMKM memahami perubahan yang Anda lakukan.</div>
            </div>
            
            <div class="mb-4">
                <label class="form-label">File Hasil Revisi <span class="text-danger">*</span></label>
                <input type="file" name="revision_files[]" multiple class="form-control" required>
                <div class="form-text">
                    Unggah file hasil revisi. Format yang didukung: JPG, PNG, PDF, DOC, ZIP, RAR, PSD, AI.
                    Maksimal 10 file, 10MB per file.
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Tips:</strong> Pastikan file yang diupload sudah sesuai dengan permintaan revisi.
                    Jika perlu, sertakan file sebelum dan sesudah revisi untuk perbandingan.
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-between">
        <a href="view-revisions.php?contract_id=<?php echo $revision['contract_id']; ?>" 
           class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Kembali
        </a>
        <button type="submit" class="btn btn-success">
            <i class="fas fa-paper-plane me-1"></i>Submit Hasil Revisi
        </button>
    </div>
</form>
    </div>
</body>
</html>