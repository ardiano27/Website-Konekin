<?php
// revision-detail.php
include "check_login.php";

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$revision_id = $_GET['id'] ?? 0;

// Get revision details
$sql = "SELECT r.*, 
               c.title as contract_title,
               c.progress_percentage,
               p.title as project_title,
               u1.full_name as requested_by_name,
               u2.full_name as requested_to_name,
               ws.title as work_title,
               ws.final_files as original_files,
               ws.preview_images,
               cp.business_name as umkm_business,
               cp2.business_name as creative_business
        FROM revisions r
        JOIN contracts c ON r.contract_id = c.id
        JOIN projects p ON c.project_id = p.id
        JOIN users u1 ON r.requested_by_user_id = u1.id
        JOIN users u2 ON r.requested_to_user_id = u2.id
        LEFT JOIN work_submissions ws ON r.work_submission_id = ws.id
        LEFT JOIN umkm_profiles cp ON u1.user_type = 'umkm' AND u1.id = cp.user_id
        LEFT JOIN umkm_profiles cp2 ON u2.user_type = 'umkm' AND u2.id = cp2.user_id
        WHERE r.id = :revision_id 
        AND (r.requested_by_user_id = :user_id OR r.requested_to_user_id = :user_id)";

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
    header("Location: dashboard.php");
    exit;
}

$is_requester = ($_SESSION['user_id'] == $revision['requested_by_user_id']);
$is_receiver = ($_SESSION['user_id'] == $revision['requested_to_user_id']);

// Handle status update (for requester)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_revision']) && $is_requester) {
        $action = 'approve';
    } elseif (isset($_POST['reject_revision']) && $is_requester) {
        $action = 'reject';
        $reject_reason = $_POST['reject_reason'] ?? '';
    } else {
        header("Location: revision-detail.php?id=" . $revision_id);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        if ($action == 'approve') {
            // Update revision status
            $update_sql = "UPDATE revisions 
                          SET status = 'completed', 
                              updated_at = NOW()
                          WHERE id = :revision_id";
            
            $stmt = $conn->prepare($update_sql);
            $stmt->execute([':revision_id' => $revision_id]);
            
            // Update work submission status
            if ($revision['work_submission_id']) {
                $update_ws_sql = "UPDATE work_submissions 
                                 SET status = 'accepted', 
                                     updated_at = NOW()
                                 WHERE id = :submission_id";
                $stmt = $conn->prepare($update_ws_sql);
                $stmt->execute([':submission_id' => $revision['work_submission_id']]);
            }
            
            // Update contract progress to 100%
            $update_contract_sql = "UPDATE contracts 
                                   SET progress_percentage = 100, 
                                       status = 'completed',
                                       updated_at = NOW()
                                   WHERE id = :contract_id";
            $stmt = $conn->prepare($update_contract_sql);
            $stmt->execute([':contract_id' => $revision['contract_id']]);
            
            // Create notification
            $notification_sql = "INSERT INTO notifications 
                                (user_id, title, message, notification_type,
                                 related_entity_type, related_entity_id, created_at)
                                VALUES (:user_id, 'Revisi Disetujui', 
                                        'Permintaan revisi untuk proyek \"" . 
                                        $revision['project_title'] . "\" telah disetujui. Proyek selesai.', 
                                        'project', 'revision', :revision_id, NOW())";
            
            $stmt = $conn->prepare($notification_sql);
            $stmt->execute([
                ':user_id' => $revision['requested_to_user_id'],
                ':revision_id' => $revision_id
            ]);
            
            $_SESSION['success'] = "Revisi telah disetujui! Proyek ditandai sebagai selesai.";
            
        } elseif ($action == 'reject') {
            if (empty($reject_reason)) {
                $_SESSION['error'] = "Alasan penolakan harus diisi";
                header("Location: revision-detail.php?id=" . $revision_id);
                exit;
            }
            
            // Update revision status
            $update_sql = "UPDATE revisions 
                          SET status = 'rejected', 
                              admin_notes = :notes,
                              updated_at = NOW()
                          WHERE id = :revision_id";
            
            $stmt = $conn->prepare($update_sql);
            $stmt->execute([
                ':notes' => $reject_reason,
                ':revision_id' => $revision_id
            ]);
            
            // Create notification
            $notification_sql = "INSERT INTO notifications 
                                (user_id, title, message, notification_type,
                                 related_entity_type, related_entity_id, created_at)
                                VALUES (:user_id, 'Revisi Ditolak', 
                                        'Permintaan revisi untuk proyek \"" . 
                                        $revision['project_title'] . "\" ditolak. Alasan: ' . 
                                        $reject_reason . '', 
                                        'project', 'revision', :revision_id, NOW())";
            
            $stmt = $conn->prepare($notification_sql);
            $stmt->execute([
                ':user_id' => $revision['requested_to_user_id'],
                ':revision_id' => $revision_id
            ]);
            
            $_SESSION['error'] = "Revisi telah ditolak. Creative worker dapat mengajukan revisi baru jika diperlukan.";
        }
        
        $conn->commit();
        header("Location: revision-detail.php?id=" . $revision_id);
        exit;
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Gagal update status revisi: " . $e->getMessage();
        error_log("Error updating revision: " . $e->getMessage());
        header("Location: revision-detail.php?id=" . $revision_id);
        exit;
    }
}

// Get messages for this revision
$messages_sql = "SELECT rm.*, u.full_name, u.avatar_url
                FROM revision_messages rm
                JOIN users u ON rm.user_id = u.id
                WHERE rm.revision_id = :revision_id
                ORDER BY rm.created_at ASC";
$messages = [];
try {
    $stmt = $conn->prepare($messages_sql);
    $stmt->execute([':revision_id' => $revision_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Revisi - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Same styles as view-revisions.php */
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
        
        .status-badge {
            font-size: 0.9rem;
            padding: 5px 15px;
            border-radius: 20px;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            margin-bottom: 10px;
            position: relative;
        }
        
        .message-sent {
            background-color: var(--primary-color);
            color: white;
            margin-left: auto;
        }
        
        .message-received {
            background-color: var(--light-bg);
            color: var(--text-dark);
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
                        <h1 class="mb-2">
                            <i class="fas fa-clipboard-check me-2"></i>
                            Detail Revisi #<?php echo $revision['revision_number']; ?>
                        </h1>
                        <p class="mb-0">
                            Proyek: <strong><?php echo htmlspecialchars($revision['project_title']); ?></strong>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="view-revisions.php?contract_id=<?php echo $revision['contract_id']; ?>" 
                           class="btn btn-light me-2">
                            <i class="fas fa-arrow-left me-1"></i>Kembali ke Daftar
                        </a>
                        <a href="project-progress1.php?contract_id=<?php echo $revision['contract_id']; ?>" 
                           class="btn btn-light">
                            <i class="fas fa-tasks me-1"></i>Progress Proyek
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Revision Info -->
            <div class="detail-card">
                <div class="row">
                    <div class="col-md-8">
                        <h3><?php echo htmlspecialchars($revision['title']); ?></h3>
                        <div class="mb-4">
                            <span class="status-badge bg-<?php 
                                switch($revision['status']) {
                                    case 'pending': echo 'warning'; break;
                                    case 'in_progress': echo 'info'; break;
                                    case 'completed': echo 'success'; break;
                                    case 'rejected': echo 'danger'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <i class="fas fa-circle me-1" style="font-size: 0.7rem;"></i>
                                <?php echo strtoupper($revision['status']); ?>
                            </span>
                            <span class="ms-3 text-muted">
                                <i class="far fa-calendar me-1"></i>
                                Diajukan: <?php echo date('d M Y H:i', strtotime($revision['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="mb-3">
                            <strong>Progress Proyek:</strong>
                            <div class="progress mt-1" style="height: 10px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $revision['progress_percentage']; ?>%">
                                </div>
                            </div>
                            <small><?php echo $revision['progress_percentage']; ?>%</small>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-muted">DARI</h6>
                                <h5 class="mb-1"><?php echo htmlspecialchars($revision['requested_by_name']); ?></h5>
                                <?php if ($revision['umkm_business']): ?>
                                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($revision['umkm_business']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-muted">KEPADA</h6>
                                <h5 class="mb-1"><?php echo htmlspecialchars($revision['requested_to_name']); ?></h5>
                                <?php if ($revision['creative_business']): ?>
                                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($revision['creative_business']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Revision Description -->
                <div class="mb-4">
                    <h5><i class="fas fa-align-left me-2"></i>Deskripsi Revisi</h5>
                    <div class="card border">
                        <div class="card-body">
                            <?php echo nl2br(htmlspecialchars($revision['description'])); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Timeline -->
                <div class="mb-4">
                    <h5><i class="fas fa-history me-2"></i>Timeline</h5>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6>Diajukan</h6>
                                <small class="text-muted"><?php echo date('d M Y H:i', strtotime($revision['created_at'])); ?></small>
                            </div>
                        </div>
                        
                        <?php if ($revision['deadline']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-warning"></div>
                            <div class="timeline-content">
                                <h6>Deadline</h6>
                                <small class="text-muted"><?php echo date('d M Y', strtotime($revision['deadline'])); ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($revision['completed_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6>Diselesaikan</h6>
                                <small class="text-muted"><?php echo date('d M Y H:i', strtotime($revision['completed_at'])); ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Attachments -->
                <?php if ($revision['attachment_urls']): 
                    $attachments = json_decode($revision['attachment_urls'], true);
                    if (is_array($attachments) && count($attachments) > 0): ?>
                    <div class="mb-4">
                        <h5><i class="fas fa-paperclip me-2"></i>Referensi & File Pendukung</h5>
                        <div class="row">
                            <?php foreach ($attachments as $attachment): 
                                $file_ext = pathinfo($attachment, PATHINFO_EXTENSION);
                                $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                $file_name = basename($attachment);
                            ?>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card border">
                                    <div class="card-body text-center p-3">
                                        <?php if ($is_image): ?>
                                            <img src="<?php echo htmlspecialchars($attachment); ?>" 
                                                 class="img-fluid rounded mb-2" 
                                                 style="max-height: 100px; object-fit: cover;"
                                                 alt="Preview">
                                        <?php else: ?>
                                            <div class="display-4 text-muted mb-2">
                                                <i class="fas fa-file"></i>
                                            </div>
                                        <?php endif; ?>
                                        <small class="d-block text-truncate"><?php echo htmlspecialchars($file_name); ?></small>
                                        <a href="<?php echo htmlspecialchars($attachment); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Revision Files (if completed) -->
                <?php if ($revision['revision_files'] && in_array($revision['status'], ['completed', 'in_progress'])): 
                    $revision_files = json_decode($revision['revision_files'], true);
                    if (is_array($revision_files) && count($revision_files) > 0): ?>
                    <div class="mb-4">
                        <h5><i class="fas fa-file-upload me-2"></i>Hasil Revisi</h5>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Creative worker telah mengirimkan hasil revisi pada 
                            <?php echo date('d M Y H:i', strtotime($revision['completed_at'])); ?>
                        </div>
                        <div class="row">
                            <?php foreach ($revision_files as $file): 
                                $file_ext = pathinfo($file, PATHINFO_EXTENSION);
                                $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                $file_name = basename($file);
                            ?>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card border border-success">
                                    <div class="card-body text-center p-3">
                                        <?php if ($is_image): ?>
                                            <img src="<?php echo htmlspecialchars($file); ?>" 
                                                 class="img-fluid rounded mb-2" 
                                                 style="max-height: 100px; object-fit: cover;"
                                                 alt="Hasil Revisi">
                                        <?php else: ?>
                                            <div class="display-4 text-success mb-2">
                                                <i class="fas fa-file-alt"></i>
                                            </div>
                                        <?php endif; ?>
                                        <small class="d-block text-truncate"><?php echo htmlspecialchars($file_name); ?></small>
                                        <a href="<?php echo htmlspecialchars($file); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-success mt-2">
                                            <i class="fas fa-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Admin Notes -->
                <?php if ($revision['admin_notes']): ?>
                <div class="mb-4">
                    <h5><i class="fas fa-sticky-note me-2"></i>Catatan</h5>
                    <div class="card border border-info">
                        <div class="card-body">
                            <?php echo nl2br(htmlspecialchars($revision['admin_notes'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <?php if ($is_requester && $revision['status'] == 'completed'): ?>
                <div class="border-top pt-4 mt-4">
                    <h5><i class="fas fa-check-circle me-2"></i>Konfirmasi Hasil Revisi</h5>
                    <p>Silakan review hasil revisi dari creative worker:</p>
                    
                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="approve_revision" id="approveOption" value="1" checked>
                                <label class="form-check-label" for="approveOption">
                                    <strong>Setujui Revisi</strong> - Hasil revisi telah sesuai dengan permintaan
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="reject_revision" id="rejectOption" value="1">
                                <label class="form-check-label" for="rejectOption">
                                    <strong>Tolak Revisi</strong> - Hasil revisi belum sesuai, berikan alasan:
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12" id="rejectReasonContainer" style="display: none;">
                            <label for="reject_reason" class="form-label">Alasan Penolakan</label>
                            <textarea name="reject_reason" id="reject_reason" class="form-control" rows="3"
                                      placeholder="Jelaskan mengapa revisi ditolak dan apa yang masih perlu diperbaiki..."></textarea>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-success me-2">
                                <i class="fas fa-check me-1"></i>Submit Konfirmasi
                            </button>
                            <a href="view-revisions.php?contract_id=<?php echo $revision['contract_id']; ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>Batal
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <?php if ($is_receiver && $revision['status'] == 'pending'): ?>
                <div class="border-top pt-4 mt-4">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Anda memiliki permintaan revisi yang menunggu. Silakan kerjakan revisi sesuai permintaan.
                    </div>
                    <a href="submit-revision.php?id=<?php echo $revision_id; ?>" 
                       class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane me-2"></i>Submit Hasil Revisi
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Messages Section -->
            <div class="detail-card">
                <h5><i class="fas fa-comments me-2"></i>Diskusi Revisi</h5>
                
                <div id="messagesContainer" class="mb-3" style="max-height: 400px; overflow-y: auto;">
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-bubble <?php echo ($msg['user_id'] == $_SESSION['user_id']) ? 'message-sent' : 'message-received'; ?>">
                                <div class="d-flex align-items-start">
                                    <?php if ($msg['avatar_url']): ?>
                                        <img src="<?php echo htmlspecialchars($msg['avatar_url']); ?>" 
                                             class="rounded-circle me-2" 
                                             width="30" height="30"
                                             alt="<?php echo htmlspecialchars($msg['full_name']); ?>">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                             style="width: 30px; height: 30px;">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <small class="d-block fw-semibold">
                                            <?php echo htmlspecialchars($msg['full_name']); ?>
                                            <span class="text-muted ms-2" style="font-size: 0.8rem;">
                                                <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                            </span>
                                        </small>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada diskusi untuk revisi ini.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Message Form -->
                <form id="messageForm" method="POST" action="send-revision-message.php">
                    <input type="hidden" name="revision_id" value="<?php echo $revision_id; ?>">
                    <div class="input-group">
                        <textarea name="message" class="form-control" rows="2" 
                                  placeholder="Ketik pesan untuk diskusi revisi..." required></textarea>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle reject reason
        document.getElementById('rejectOption')?.addEventListener('change', function() {
            document.getElementById('rejectReasonContainer').style.display = 
                this.checked ? 'block' : 'none';
        });
        
        document.getElementById('approveOption')?.addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('rejectReasonContainer').style.display = 'none';
            }
        });
        
        // Auto-scroll messages to bottom
        const messagesContainer = document.getElementById('messagesContainer');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Form validation
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                const textarea = this.querySelector('textarea[name="message"]');
                if (textarea.value.trim().length === 0) {
                    e.preventDefault();
                    alert('Pesan tidak boleh kosong');
                    textarea.focus();
                }
            });
        }
    </script>
</body>
</html>