<?php
// view-revisions.php
include "check_login.php";

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$contract_id = $_GET['contract_id'] ?? 0;
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Validasi hak akses
$sql = "SELECT c.*, p.title as project_title, 
               u1.full_name as creative_name,
               u2.full_name as umkm_name,
               cp.business_name as umkm_business
        FROM contracts c 
        JOIN projects p ON c.project_id = p.id
        JOIN users u1 ON c.creative_user_id = u1.id
        JOIN users u2 ON c.umkm_user_id = u2.id
        LEFT JOIN umkm_profiles cp ON u2.id = cp.user_id
        WHERE c.id = :contract_id 
        AND (c.creative_user_id = :user_id OR c.umkm_user_id = :user_id)";
$stmt = $conn->prepare($sql);
$stmt->execute([
    ':contract_id' => $contract_id,
    ':user_id' => $_SESSION['user_id']
]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header("Location: dashboard.php");
    exit;
}

$is_creative = ($_SESSION['user_id'] == $contract['creative_user_id']);
$is_umkm = ($_SESSION['user_id'] == $contract['umkm_user_id']);

// Handle request revision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_revision'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $deadline = $_POST['deadline'] ?? '';
    $files = $_FILES['attachments'] ?? [];
    
    // Validate
    if (empty($title) || empty($description)) {
        $_SESSION['error'] = "Judul dan deskripsi revisi harus diisi";
        header("Location: view-revisions.php?contract_id=" . $contract_id);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        // Get latest revision number
        $rev_number_sql = "SELECT COALESCE(MAX(revision_number), 0) + 1 as next_number 
                          FROM revisions 
                          WHERE contract_id = :contract_id";
        $stmt = $conn->prepare($rev_number_sql);
        $stmt->execute([':contract_id' => $contract_id]);
        $next_number = $stmt->fetchColumn();
        
        // Upload files
        $attachment_urls = [];
        if (!empty($files['name'][0])) {
            $upload_dir = 'uploads/revisions/' . $contract_id . '/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . basename($files['name'][$i]);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                        $attachment_urls[] = $file_path;
                    }
                }
            }
        }
        
        // Get latest work submission
        $submission_sql = "SELECT id FROM work_submissions 
                          WHERE contract_id = :contract_id 
                          ORDER BY submitted_at DESC LIMIT 1";
        $stmt = $conn->prepare($submission_sql);
        $stmt->execute([':contract_id' => $contract_id]);
        $submission_id = $stmt->fetchColumn();
        
        // Insert revision
        $insert_sql = "INSERT INTO revisions 
                      (contract_id, work_submission_id, revision_number,
                       requested_by_user_id, requested_to_user_id,
                       title, description, deadline, attachment_urls,
                       status, created_at, updated_at)
                      VALUES (:contract_id, :submission_id, :revision_number,
                              :requested_by, :requested_to,
                              :title, :description, :deadline, :attachments,
                              'pending', NOW(), NOW())";
        
        $stmt = $conn->prepare($insert_sql);
        $stmt->execute([
            ':contract_id' => $contract_id,
            ':submission_id' => $submission_id ?: null,
            ':revision_number' => $next_number,
            ':requested_by' => $_SESSION['user_id'],
            ':requested_to' => $is_umkm ? $contract['creative_user_id'] : $contract['umkm_user_id'],
            ':title' => $title,
            ':description' => $description,
            ':deadline' => $deadline ? date('Y-m-d', strtotime($deadline)) : null,
            ':attachments' => !empty($attachment_urls) ? json_encode($attachment_urls) : null
        ]);
        
        $revision_id = $conn->lastInsertId();
        
        // Update work submission status
        if ($submission_id) {
            $update_ws_sql = "UPDATE work_submissions 
                             SET status = 'revised', updated_at = NOW()
                             WHERE id = :submission_id";
            $stmt = $conn->prepare($update_ws_sql);
            $stmt->execute([':submission_id' => $submission_id]);
        }
        
        // Update contract progress to 90% when revision requested
        $update_contract_sql = "UPDATE contracts 
                               SET progress_percentage = 90, updated_at = NOW()
                               WHERE id = :contract_id AND progress_percentage < 90";
        $stmt = $conn->prepare($update_contract_sql);
        $stmt->execute([':contract_id' => $contract_id]);
        
        // Create notification
        $notification_sql = "INSERT INTO notifications 
                            (user_id, title, message, notification_type,
                             related_entity_type, related_entity_id, created_at)
                            VALUES (:user_id, :title, :message, 'project',
                                    'revision', :revision_id, NOW())";
        
        $stmt = $conn->prepare($notification_sql);
        $stmt->execute([
            ':user_id' => $is_umkm ? $contract['creative_user_id'] : $contract['umkm_user_id'],
            ':title' => 'Permintaan Revisi Baru',
            ':message' => ($is_umkm ? 'UMKM ' : 'Creative ') . $_SESSION['full_name'] . 
                         ' mengajukan permintaan revisi untuk proyek "' . 
                         $contract['project_title'] . '"',
            ':revision_id' => $revision_id
        ]);
        
        $conn->commit();
        
        $_SESSION['success'] = "Permintaan revisi berhasil dikirim!";
        header("Location: view-revisions.php?contract_id=" . $contract_id);
        exit;
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Gagal mengajukan revisi: " . $e->getMessage();
        error_log("Error requesting revision: " . $e->getMessage());
        header("Location: view-revisions.php?contract_id=" . $contract_id);
        exit;
    }
}

// DEBUG: Cek apakah ada data di tabel revisions untuk kontrak ini
$debug_sql = "SELECT COUNT(*) as total FROM revisions WHERE contract_id = :contract_id";
$stmt = $conn->prepare($debug_sql);
$stmt->execute([':contract_id' => $contract_id]);
$total_revisions = $stmt->fetchColumn();

// Ambil semua revisi (PERBAIKAN QUERY: hapus reference ke kolom yang tidak ada)
$revisions_sql = "SELECT r.*, 
                         u1.full_name as requested_by_name,
                         u2.full_name as requested_to_name
                  FROM revisions r
                  JOIN users u1 ON r.requested_by_user_id = u1.id
                  JOIN users u2 ON r.requested_to_user_id = u2.id
                  WHERE r.contract_id = :contract_id
                  ORDER BY r.created_at DESC";

$revisions = [];
try {
    $stmt = $conn->prepare($revisions_sql);
    $stmt->execute([':contract_id' => $contract_id]);
    $revisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

// Ambil work submission terbaru untuk kontrak ini
$work_sql = "SELECT ws.* FROM work_submissions ws
             WHERE ws.contract_id = :contract_id
             ORDER BY ws.submitted_at DESC LIMIT 1";
$stmt = $conn->prepare($work_sql);
$stmt->execute([':contract_id' => $contract_id]);
$latest_work = $stmt->fetch(PDO::FETCH_ASSOC);

// DEBUG info
error_log("Contract ID: $contract_id, Total revisions in DB: $total_revisions, Found in query: " . count($revisions));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Revisi - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #549efeff;
            --primary-dark: #82a8db;
            --accent-color: #FFC300;
            --light-bg: #f8f9fa;
            --text-dark: #343a40;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow-light: 0 2px 10px rgba(0,0,0,0.08);
            --shadow-medium: 0 5px 15px rgba(0,0,0,0.1);
            --border-radius: 12px;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: var(--text-dark);
        }
        
        .main-content {
            padding: 20px 0;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-medium);
        }
        
        .detail-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-light);
        }
        
        .revision-item {
            border-left: 4px solid;
            padding-left: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .revision-pending { border-color: #ffc107; }
        .review-progress { border-color: #17a2b8; }
        .revision-completed { border-color: #28a745; }
        .revision-rejected { border-color: #dc3545; }
        
        .attachment-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 10px;
            margin-bottom: 10px;
            border: 2px solid var(--border-color);
        }
        
        .file-item {
            display: inline-flex;
            align-items: center;
            background: var(--light-bg);
            padding: 8px 12px;
            border-radius: 6px;
            margin-right: 10px;
            margin-bottom: 10px;
            text-decoration: none;
            color: var(--text-dark);
        }
        
        .file-item:hover {
            background: #e9ecef;
            color: var(--primary-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Debug info - hanya tampil di development */
        .debug-info {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 12px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2"><i class="fas fa-exchange-alt me-2"></i>Riwayat Revisi</h1>
                        <p class="mb-0">
                            Proyek: <strong><?php echo htmlspecialchars($contract['project_title']); ?></strong> |
                            Creative: <?php echo htmlspecialchars($contract['creative_name']); ?> |
                            UMKM: <?php echo htmlspecialchars($contract['umkm_business'] ?? $contract['umkm_name']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="project-progress1.php?contract_id=<?php echo $contract_id; ?>" 
                           class="btn btn-light me-2">
                            <i class="fas fa-arrow-left me-1"></i>Kembali ke Progress
                        </a>
                        <?php if ($is_umkm): ?>
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#requestRevisionModal">
                                <i class="fas fa-plus me-1"></i>Ajukan Revisi
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- DEBUG INFO (akan muncul jika ada masalah) -->
            <?php if (count($revisions) === 0 && $total_revisions > 0): ?>
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    Contract ID: <?php echo $contract_id; ?><br>
                    Revisions in database: <?php echo $total_revisions; ?><br>
                    Revisions found in query: 0<br>
                    <small>Mungkin ada masalah dengan query atau kondisi WHERE</small>
                </div>
            <?php endif; ?>
            
            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Revisions List -->
            <div class="detail-card">
                <h4 class="mb-4">
                    <i class="fas fa-history me-2"></i>Daftar Revisi
                    <span class="badge bg-secondary ms-2"><?php echo count($revisions); ?></span>
                </h4>
                
                <?php if (count($revisions) > 0): ?>
                    <?php foreach ($revisions as $rev): 
                        $status_class = '';
                        switch($rev['status']) {
                            case 'pending': $status_class = 'revision-pending'; break;
                            case 'in_progress': $status_class = 'review-progress'; break;
                            case 'completed': $status_class = 'revision-completed'; break;
                            case 'rejected': $status_class = 'revision-rejected'; break;
                        }
                    ?>
                        <div class="revision-item <?php echo $status_class; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5>
                                        Revisi #<?php echo $rev['revision_number']; ?>: 
                                        <?php echo htmlspecialchars($rev['title']); ?>
                                    </h5>
                                    <div class="mb-2">
                                        <span class="badge bg-<?php 
                                            switch($rev['status']) {
                                                case 'pending': echo 'warning'; break;
                                                case 'in_progress': echo 'info'; break;
                                                case 'completed': echo 'success'; break;
                                                case 'rejected': echo 'danger'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>">
                                            <?php echo ucfirst($rev['status']); ?>
                                        </span>
                                        <small class="text-muted ms-2">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo date('d M Y H:i', strtotime($rev['created_at'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-2">
                                        <strong>Dari:</strong> <?php echo htmlspecialchars($rev['requested_by_name']); ?><br>
                                        <strong>Ke:</strong> <?php echo htmlspecialchars($rev['requested_to_name']); ?>
                                    </p>
                                </div>
                                
                                <div class="action-buttons">
                                    <a href="revision-detail.php?id=<?php echo $rev['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>Detail
                                    </a>
                                    
                                    <?php if ($rev['status'] == 'pending' && $_SESSION['user_id'] == $rev['requested_to_user_id']): ?>
                                        <a href="submit-revision.php?id=<?php echo $rev['id']; ?>" 
                                           class="btn btn-sm btn-success">
                                            <i class="fas fa-paper-plane me-1"></i>Submit Revisi
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Deskripsi:</strong>
                                <p class="mb-1"><?php echo nl2br(htmlspecialchars($rev['description'])); ?></p>
                            </div>
                            
                            <?php if ($rev['deadline']): ?>
                                <div class="mb-3">
                                    <strong>Deadline:</strong>
                                    <?php echo date('d M Y', strtotime($rev['deadline'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($rev['attachment_urls']): 
                                $attachments = json_decode($rev['attachment_urls'], true);
                                if (is_array($attachments) && count($attachments) > 0): ?>
                                    <div class="mb-3">
                                        <strong>Referensi:</strong>
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
                                                             class="attachment-preview">
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo htmlspecialchars($attachment); ?>" 
                                                       target="_blank" 
                                                       class="file-item">
                                                        <i class="fas fa-file me-2"></i>
                                                        <span>Download Referensi</span>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Tampilkan hasil revisi dari work submission terbaru -->
                            <?php if (in_array($rev['status'], ['completed', 'in_progress']) && $latest_work && $latest_work['final_files']): 
                                $result_files = json_decode($latest_work['final_files'], true);
                                if (is_array($result_files) && count($result_files) > 0): ?>
                                    <div class="mb-3">
                                        <strong>Hasil Revisi:</strong>
                                        <div class="mt-2">
                                            <?php foreach ($result_files as $file): 
                                                $file_ext = pathinfo($file, PATHINFO_EXTENSION);
                                                $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                            ?>
                                                <?php if ($is_image): ?>
                                                    <a href="<?php echo htmlspecialchars($file); ?>" 
                                                       target="_blank" 
                                                       class="d-inline-block me-2 mb-2">
                                                        <img src="<?php echo htmlspecialchars($file); ?>" 
                                                             alt="Hasil Revisi" 
                                                             class="attachment-preview">
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo htmlspecialchars($file); ?>" 
                                                       target="_blank" 
                                                       class="file-item">
                                                        <i class="fas fa-file-download me-2"></i>
                                                        <span>Download Hasil</span>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($rev['admin_notes']): ?>
                                <div class="alert alert-info mt-3">
                                    <strong><i class="fas fa-sticky-note me-2"></i>Catatan:</strong>
                                    <?php echo nl2br(htmlspecialchars($rev['admin_notes'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-exchange-alt fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Belum Ada Revisi</h4>
                        <p class="text-muted">Belum ada permintaan revisi untuk proyek ini.</p>
                        <?php if ($is_umkm): ?>
                            <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#requestRevisionModal">
                                <i class="fas fa-plus me-1"></i>Ajukan Revisi Pertama
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Request Revision Modal (for UMKM only) -->
    <?php if ($is_umkm): ?>
    <div class="modal fade" id="requestRevisionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajukan Permintaan Revisi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="request_revision" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Judul Revisi <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                   placeholder="Contoh: Perbaikan warna logo, Penambahan fitur, dll.">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Deskripsi Detail <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="5" required
                                      placeholder="Jelaskan secara detail apa yang perlu direvisi, alasan revisi, dan harapan hasil akhir."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Deadline Revisi (Opsional)</label>
                                    <input type="date" name="deadline" class="form-control"
                                           min="<?php echo date('Y-m-d'); ?>">
                                    <div class="form-text">Beri waktu yang wajar untuk creative worker.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prioritas (Opsional)</label>
                                    <select name="priority" class="form-select">
                                        <option value="low">Rendah</option>
                                        <option value="medium" selected>Sedang</option>
                                        <option value="high">Tinggi</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Referensi & File Pendukung (Opsional)</label>
                            <input type="file" name="attachments[]" multiple class="form-control"
                                   accept="image/*,.pdf,.doc,.docx,.zip,.rar">
                            <div class="form-text">
                                Unggah file referensi, contoh, atau penjelasan tambahan. 
                                Maksimal 5 file, 10MB per file.
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Perhatian:</strong> Setelah mengajukan revisi, status proyek akan berubah dan 
                            creative worker akan diberi waktu untuk memperbaiki hasil kerja.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-paper-plane me-1"></i>Ajukan Revisi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File preview for modal
        document.querySelector('input[name="attachments[]"]')?.addEventListener('change', function(e) {
            const previewContainer = document.createElement('div');
            previewContainer.className = 'mt-3';
            previewContainer.id = 'filePreviewModal';
            
            // Remove existing preview
            const existingPreview = document.getElementById('filePreviewModal');
            if (existingPreview) {
                existingPreview.remove();
            }
            
            const files = e.target.files;
            if (files.length > 0) {
                e.target.parentNode.appendChild(previewContainer);
                
                for (let i = 0; i < Math.min(files.length, 5); i++) {
                    const file = files[i];
                    const div = document.createElement('div');
                    div.className = 'alert alert-light d-flex align-items-center mb-2';
                    div.innerHTML = `
                        <i class="fas fa-file me-2"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">${file.name}</div>
                            <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                        </div>
                    `;
                    previewContainer.appendChild(div);
                }
                
                if (files.length > 5) {
                    const warning = document.createElement('div');
                    warning.className = 'alert alert-warning mt-2';
                    warning.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Hanya 5 file pertama yang akan diupload.';
                    previewContainer.appendChild(warning);
                }
            }
        });
        
        // Validate deadline date
        const deadlineInput = document.querySelector('input[name="deadline"]');
        if (deadlineInput) {
            deadlineInput.min = new Date().toISOString().split('T')[0];
        }
        
        // Debug: Log untuk mengetahui apakah halaman berjalan dengan baik
        console.log('View Revisions Loaded');
        console.log('Contract ID:', '<?php echo $contract_id; ?>');
        console.log('Total Revisions:', <?php echo count($revisions); ?>);
        console.log('Is UMKM:', <?php echo $is_umkm ? 'true' : 'false'; ?>);
        console.log('Is Creative:', <?php echo $is_creative ? 'true' : 'false'; ?>);
    </script>
</body>
</html>