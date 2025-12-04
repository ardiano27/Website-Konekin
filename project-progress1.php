<?php
// project-progress-fixed.php
include "check_login.php";

if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$contract_id = $_GET['contract_id'] ?? $_GET['id'] ?? 0;

// Ambil data contract dan project
$sql = "
    SELECT 
        p.*,
        c.id as contract_id,
        c.title as contract_title,
        c.agreed_budget,
        c.progress_percentage,
        c.status as contract_status,
        c.start_date,
        c.end_date,
        u.full_name as umkm_name,
        up.business_name,
        u.id as umkm_user_id
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    JOIN users u ON c.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    WHERE c.id = :contract_id 
    AND c.creative_user_id = :user_id
    AND c.status = 'active'
";

$project = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':contract_id' => $contract_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

if (!$project) {
    header("Location: all-projects.php");
    exit;
}

// Ambil progress history
$progress_history_sql = "
    (SELECT 
        cp.id,
        cp.contract_id,
        cp.creative_user_id as user_id,
        cp.progress_percentage,
        cp.description,
        cp.attachment_urls,
        cp.created_at,
        'progress' as type,
        NULL as revision_status,
        NULL as revision_number,
        NULL as title,
        NULL as requested_by_user_id,
        NULL as requested_to_user_id
    FROM contract_progress cp
    WHERE cp.contract_id = :contract_id)
    
    UNION
    
    (SELECT 
        r.id,
        r.contract_id,
        r.requested_to_user_id as user_id,  -- Creative yang diminta revisi
        NULL as progress_percentage,
        r.description,
        r.attachment_urls,
        r.created_at,
        'revision' as type,
        r.status as revision_status,
        r.revision_number,
        r.title,
        r.requested_by_user_id,
        r.requested_to_user_id
    FROM revisions r
    WHERE r.contract_id = :contract_id2
    AND (r.requested_by_user_id = :user_id OR r.requested_to_user_id = :user_id))
    
    ORDER BY created_at DESC
";
$progress_history = [];
try {
    $stmt = $conn->prepare($progress_history_sql);
    $stmt->execute([
        ':contract_id' => $contract_id,
        ':contract_id2' => $contract_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $progress_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    // Fallback ke query lama jika error
    $fallback_sql = "SELECT * FROM contract_progress WHERE contract_id = :contract_id ORDER BY created_at DESC";
    $stmt = $conn->prepare($fallback_sql);
    $stmt->execute([':contract_id' => $contract_id]);
    $progress_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $progress_percentage = (int) ($_POST['progress_percentage'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $attachments = $_FILES['attachments'] ?? [];
    
    // Validasi
    $current_progress = (int) $project['progress_percentage'];
    
    if ($progress_percentage < 0 || $progress_percentage > 100) {
        $error_message = "Progress harus antara 0-100%";
    } elseif ($progress_percentage < $current_progress) {
        $error_message = "Progress tidak boleh lebih kecil dari progress sebelumnya ($current_progress%)";
    } else {
        // Upload attachments
        $attachment_urls = [];
        if (!empty($attachments['name'][0]) && $attachments['name'][0] != '') {
            $upload_dir = 'uploads/progress/' . $contract_id . '/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            for ($i = 0; $i < count($attachments['name']); $i++) {
                if ($attachments['error'][$i] === UPLOAD_ERR_OK && $attachments['name'][$i] != '') {
                    $file_name = time() . '_' . uniqid() . '_' . basename($attachments['name'][$i]);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($attachments['tmp_name'][$i], $file_path)) {
                        $attachment_urls[] = $file_path;
                    }
                }
            }
        }
        
        try {
            // Insert progress update
            $insert_sql = "
                INSERT INTO contract_progress 
                (contract_id, creative_user_id, progress_percentage, description, attachment_urls, created_at)
                VALUES (:contract_id, :user_id, :progress_percentage, :description, :attachment_urls, NOW())
            ";
            
            $stmt = $conn->prepare($insert_sql);
            $stmt->execute([
                ':contract_id' => $contract_id,
                ':user_id' => $_SESSION['user_id'],
                ':progress_percentage' => $progress_percentage,
                ':description' => $description,
                ':attachment_urls' => !empty($attachment_urls) ? json_encode($attachment_urls) : null
            ]);
            
            // Update contract progress
            $update_sql = "
                UPDATE contracts 
                SET progress_percentage = :progress_percentage, 
                    updated_at = NOW()
                WHERE id = :contract_id
            ";
            
            $stmt = $conn->prepare($update_sql);
            $stmt->execute([
                ':progress_percentage' => $progress_percentage,
                ':contract_id' => $contract_id
            ]);
            
            // Create notification for UMKM
            $notification_sql = "
                INSERT INTO notifications 
                (user_id, title, message, notification_type, related_entity_type, related_entity_id, created_at)
                VALUES (:umkm_id, :title, :message, 'project', 'contract', :contract_id, NOW())
            ";
            
            $stmt = $conn->prepare($notification_sql);
            $stmt->execute([
                ':umkm_id' => $project['umkm_user_id'],
                ':title' => 'Update Progress Proyek',
                ':message' => 'Creative ' . $_SESSION['full_name'] . ' telah mengupdate progress proyek "' . $project['title'] . '" menjadi ' . $progress_percentage . '%',
                ':contract_id' => $contract_id
            ]);
            
            // Refresh project data
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':contract_id' => $contract_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Refresh progress history
            $stmt = $conn->prepare($progress_history_sql);
            $stmt->execute([
                ':contract_id' => $contract_id,
                ':contract_id2' => $contract_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            $progress_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $success_message = "Progress berhasil diupdate dari $current_progress% menjadi $progress_percentage%!";
            
        } catch (PDOException $e) {
            $error_message = "Terjadi kesalahan: " . $e->getMessage();
            error_log("Database error updating progress: " . $e->getMessage());
            
            // Cek jika progress sudah terupdate
            $check_sql = "SELECT progress_percentage FROM contracts WHERE id = :contract_id";
            $stmt = $conn->prepare($check_sql);
            $stmt->execute([':contract_id' => $contract_id]);
            $check_result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($check_result && $check_result['progress_percentage'] == $progress_percentage) {
                $success_message = "Progress berhasil diupdate, tetapi ada error pada sistem notifikasi.";
                $error_message = ''; // Reset error message jika progress sudah terupdate
            }
        } catch (Exception $e) {
            // Handle non-PDO exceptions
            $error_message = "Terjadi kesalahan sistem: " . $e->getMessage();
            error_log("General error updating progress: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Progress - Konekin</title>
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
            padding: 10px 0px 0px;
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
            border-left: 3px solid #FFC300;
            background-color: rgba(255, 195, 0, 0.05);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .progress-bar-custom {
            height: 12px;
            border-radius: 6px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .progress-fill-custom {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            border-radius: 6px;
            transition: width 0.5s ease;
        }
        
        .attachment-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 10px;
            margin-bottom: 10px;
            border: 2px solid var(--border-color);
        }
        
        .history-item {
            border-left: 3px solid var(--primary-color);
            padding-left: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .history-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .file-item {
            display: inline-flex;
            align-items: center;
            background: var(--light-bg);
            padding: 8px 12px;
            border-radius: 6px;
            margin-right: 10px;
            margin-bottom: 10px;
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
                        <h1 class="mb-2"><i class="fas fa-tasks me-2"></i>Update Progress Proyek</h1>
                        <p class="mb-0">Update progress proyek <?php echo htmlspecialchars($project['title']); ?></p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-light me-2">
                            <i class="fas fa-arrow-left me-1"></i>Kembali ke Detail
                        </a>
                        <a href="all-projects.php" class="btn btn-light">
                            <i class="fas fa-list me-1"></i>Semua Proyek
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Current Progress -->
                    <div class="detail-card">
                        <h4 class="mb-3">Progress Saat Ini</h4>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold"><?php echo $project['progress_percentage']; ?>% Selesai</span>
                                <span class="text-muted">Target: 100%</span>
                            </div>
                            <div class="progress-bar-custom">
                                <div class="progress-fill-custom" style="width: <?php echo $project['progress_percentage']; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Proyek</label>
                                    <p class="fw-semibold"><?php echo htmlspecialchars($project['title']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted">Budget</label>
                                    <p class="fw-semibold">Rp <?php echo number_format($project['agreed_budget'], 0, ',', '.'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted">UMKM</label>
                                    <p class="fw-semibold"><?php echo htmlspecialchars($project['business_name']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted">Tanggal Mulai</label>
                                    <p class="fw-semibold"><?php echo date('d M Y', strtotime($project['start_date'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Update Progress Form -->
                    <div class="detail-card">
                        <h4 class="mb-3">Update Progress</h4>
                        
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
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label for="progress_percentage" class="form-label fw-semibold">
                                    Progress (%)
                                    <span class="text-muted">(Saat ini: <?php echo $project['progress_percentage']; ?>%)</span>
                                </label>
                                <input type="range" 
                                       class="form-range" 
                                       id="progress_percentage" 
                                       name="progress_percentage" 
                                       min="<?php echo $project['progress_percentage']; ?>" 
                                       max="100" 
                                       step="5" 
                                       value="<?php echo $project['progress_percentage']; ?>"
                                       oninput="updateProgressValue(this.value)">
                                <div class="d-flex justify-content-between">
                                    <small><?php echo $project['progress_percentage']; ?>%</small>
                                    <small id="progressValue"><?php echo $project['progress_percentage']; ?>%</small>
                                    <small>100%</small>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label fw-semibold">Deskripsi Update</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="4" 
                                          placeholder="Jelaskan apa yang sudah dikerjakan, pencapaian, atau kendala yang dihadapi..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <div class="form-text">Berikan penjelasan detail tentang progress yang telah dicapai.</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="attachments" class="form-label fw-semibold">Lampiran</label>
                                <input type="file" 
                                       class="form-control" 
                                       id="attachments" 
                                       name="attachments[]" 
                                       multiple
                                       accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar">
                                <div class="form-text">Unggah bukti pekerjaan (gambar, dokumen, dll). Maksimal 5 file, 5MB per file.</div>
                                
                                <div id="filePreview" class="mt-3"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="all-projects.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Batal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Simpan Progress
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Progress History -->
                    <?php if (count($progress_history) > 0): ?>
                        <div class="detail-card">
                            <h4 class="mb-3">Riwayat Update & Revisi</h4>
                            <?php 
                            $user_id = $_SESSION['user_id']; // Ambil user ID dari session
                            ?>
                            <?php foreach ($progress_history as $history): ?>
                                <?php 
                                // Tentukan apakah user saat ini adalah yang meminta revisi atau diminta revisi
                                $is_requester = ($history['type'] === 'revision' && $history['requested_by_user_id'] == $user_id);
                                $is_requestee = ($history['type'] === 'revision' && $history['requested_to_user_id'] == $user_id);
                                ?>
                                
                                <div class="history-item <?php echo $history['type'] === 'revision' ? 'revision-item' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <?php if ($history['type'] === 'progress'): ?>
                                                <h6 class="mb-1">
                                                    <i class="fas fa-chart-line me-1"></i>
                                                    Progress: <?php echo $history['progress_percentage']; ?>%
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php echo date('d M Y H:i', strtotime($history['created_at'])); ?>
                                                </small>
                                            <?php else: ?>
                                                <h6 class="mb-1">
                                                    <i class="fas fa-redo me-1"></i>
                                                    <?php 
                                                    if (!empty($history['title'])) {
                                                        echo htmlspecialchars($history['title']);
                                                    } else {
                                                        echo 'Permintaan Revisi';
                                                    }
                                                    ?>
                                                    <?php if ($history['revision_number']): ?>
                                                        <small class="text-muted ms-2">#<?php echo $history['revision_number']; ?></small>
                                                    <?php endif; ?>
                                                </h6>
                                                <div>
                                                    <small class="text-muted">
                                                        <i class="far fa-clock me-1"></i>
                                                        <?php echo date('d M Y H:i', strtotime($history['created_at'])); ?>
                                                    </small>
                                                    <?php if ($history['revision_status']): ?>
                                                        <span class="badge bg-<?php 
                                                            echo $history['revision_status'] === 'pending' ? 'warning' : 
                                                                ($history['revision_status'] === 'in_progress' ? 'info' :
                                                                ($history['revision_status'] === 'completed' ? 'success' : 
                                                                ($history['revision_status'] === 'rejected' ? 'danger' : 'secondary'))); 
                                                        ?> ms-2">
                                                            <?php echo ucfirst(str_replace('_', ' ', $history['revision_status'])); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($is_requester): ?>
                                                        <span class="badge bg-info ms-2">Anda meminta</span>
                                                    <?php elseif ($is_requestee): ?>
                                                        <span class="badge bg-warning ms-2">Anda diminta</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-<?php echo $history['type'] === 'progress' ? 'primary' : 'warning'; ?>">
                                            <?php echo $history['type'] === 'progress' ? 'Update' : 'Revisi'; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($history['description'])): ?>
                                        <div class="mb-2 p-2 bg-light rounded">
                                            <?php echo nl2br(htmlspecialchars($history['description'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Tampilkan attachments untuk progress dan revisi
                                    $attachments = [];
                                    if (!empty($history['attachment_urls'])) {
                                        $decoded = json_decode($history['attachment_urls'], true);
                                        $attachments = is_array($decoded) ? $decoded : [];
                                    }
                                    
                                    if (is_array($attachments) && count($attachments) > 0): ?>
                                        <div class="mt-2">
                                            <small class="text-muted d-block mb-2">Lampiran:</small>
                                            <div>
                                                <?php foreach ($attachments as $attachment): 
                                                    // Handle jika attachment adalah array atau string
                                                    if (is_array($attachment)) {
                                                        $file_path = $attachment['url'] ?? $attachment['path'] ?? $attachment;
                                                    } else {
                                                        $file_path = $attachment;
                                                    }
                                                    
                                                    if (!empty($file_path)) {
                                                        $file_ext = pathinfo($file_path, PATHINFO_EXTENSION);
                                                        $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
                                                        $file_name = basename($file_path);
                                                    ?>
                                                        <?php if ($is_image): ?>
                                                            <a href="<?php echo htmlspecialchars($file_path); ?>" 
                                                            target="_blank" 
                                                            class="d-inline-block me-2 mb-2"
                                                            title="<?php echo htmlspecialchars($file_name); ?>">
                                                                <img src="<?php echo htmlspecialchars($file_path); ?>" 
                                                                    alt="Lampiran" 
                                                                    class="attachment-preview"
                                                                    onerror="this.src='assets/images/file-placeholder.png'">
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="<?php echo htmlspecialchars($file_path); ?>" 
                                                            target="_blank" 
                                                            class="file-item"
                                                            title="<?php echo htmlspecialchars($file_name); ?>">
                                                                <i class="fas fa-file me-2"></i>
                                                                <span><?php echo !empty($file_ext) ? strtoupper($file_ext) . ' File' : 'File'; ?></span>
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php } ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($history['type'] === 'revision' && $history['revision_status'] === 'pending' && $is_requestee): ?>
                                        <div class="mt-3">
                                            <a href="view-revisions.php?contract_id=<?php echo $contract_id; ?>&revision_id=<?php echo $history['id']; ?>" 
                                            class="btn btn-sm btn-warning">
                                                <i class="fas fa-check me-1"></i>Respon Revisi
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Tips -->
                    <div class="detail-card">
                        <h5 class="mb-3"><i class="fas fa-lightbulb me-2"></i>Tips Update Progress</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <small>Update progress secara berkala (minimal 1x seminggu)</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <small>Sertakan bukti pekerjaan (screenshot, dokumen)</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <small>Jelaskan pencapaian dan kendala dengan jelas</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <small>Progress tidak bisa dikurangi, pastikan akurat</small>
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <small>UMKM akan mendapat notifikasi setiap update</small>
                            </li>
                        </ul>
                    </div>
                    <?php
                        // Di dalam project-progress1.php, sebelum Quick Actions
                        $revisions_sql = "SELECT COUNT(*) as pending_revisions 
                                        FROM revisions 
                                        WHERE contract_id = :contract_id 
                                        AND requested_to_user_id = :user_id 
                                        AND status = 'pending'";
                        $stmt = $conn->prepare($revisions_sql);
                        $stmt->execute([
                            ':contract_id' => $contract_id,
                            ':user_id' => $_SESSION['user_id']
                        ]);
                        $pending_revisions = $stmt->fetchColumn();

                        if ($pending_revisions > 0): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                Anda memiliki <strong><?php echo $pending_revisions; ?> permintaan revisi</strong> yang menunggu.
                                <a href="view-revisions.php?contract_id=<?php echo $contract_id; ?>" class="alert-link">
                                    Lihat detail revisi
                                </a>
                            </div>
                        <?php endif; ?>
                    <!-- Quick Actions -->
                    <div class="detail-card">
                        <h5 class="mb-3">Aksi Cepat</h5>
                        <div class="d-grid gap-2">
                           <a href="messages.php?user_id=<?php echo $project['umkm_user_id']; ?>" class="btn btn-outline-primary">
                             <i class="fas fa-comments me-2"></i>Chat UMKM
                                </a>
                            <a href="contract-details.php?id=<?php echo $contract_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-file-contract me-2"></i>Lihat Kontrak
                            </a>
                            <a href="submit-work.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-outline-success">
                                <i class="fas fa-paper-plane me-2"></i>Submit Hasil Akhir
                            </a>
                        </div>
                    </div>
                    
                    <!-- Project Timeline -->
                    <div class="detail-card">
                        <h5 class="mb-3">Timeline Proyek</h5>
                        <div class="mb-3">
                            <label class="form-label text-muted">Mulai</label>
                            <p class="fw-semibold"><?php echo date('d M Y', strtotime($project['start_date'])); ?></p>
                        </div>
                        <?php if ($project['end_date']): ?>
                            <div class="mb-3">
                                <label class="form-label text-muted">Target Selesai</label>
                                <p class="fw-semibold"><?php echo date('d M Y', strtotime($project['end_date'])); ?></p>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label text-muted">Durasi</label>
                            <p class="fw-semibold">
                                <?php 
                                $start = new DateTime($project['start_date']);
                                $now = new DateTime();
                                $interval = $start->diff($now);
                                echo $interval->days . ' hari';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateProgressValue(value) {
            document.getElementById('progressValue').textContent = value + '%';
        }
        
        // File preview
        document.getElementById('attachments').addEventListener('change', function(e) {
            const preview = document.getElementById('filePreview');
            preview.innerHTML = '';
            
            const files = e.target.files;
            for (let i = 0; i < Math.min(files.length, 5); i++) {
                const file = files[i];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (file.type.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'attachment-preview';
                        img.title = file.name;
                        preview.appendChild(img);
                    } else {
                        const div = document.createElement('div');
                        div.className = 'file-item';
                        div.innerHTML = `
                            <i class="fas fa-file me-2"></i>
                            <span>${file.name}</span>
                        `;
                        preview.appendChild(div);
                    }
                }
                
                reader.readAsDataURL(file);
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const progressInput = document.getElementById('progress_percentage');
            const currentProgress = <?php echo $project['progress_percentage']; ?>;
            
            if (parseInt(progressInput.value) < currentProgress) {
                e.preventDefault();
                alert('Progress tidak boleh lebih kecil dari progress saat ini!');
                return false;
            }
            
            if (parseInt(progressInput.value) > 100) {
                e.preventDefault();
                alert('Progress tidak boleh lebih dari 100%!');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>