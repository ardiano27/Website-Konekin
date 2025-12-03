<?php
include "check_login.php";

// Hanya UMKM yang bisa akses halaman ini
if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Ambil ID proyek dari URL
$project_id = $_GET['id'] ?? 0;

// Ambil data proyek dan kontrak
$sql = "
    SELECT 
        p.*,
        c.id as contract_id,
        c.title as contract_title,
        c.progress_percentage,
        c.payment_status,
        c.creative_user_id,
        c.umkm_user_id,
        c.start_date,
        c.end_date,
        c.status as contract_status,
        c.project_code,
        u.full_name as creative_name,
        cp.tagline as creative_tagline,
        cp.rating as creative_rating,
        u2.full_name as umkm_name,
        up.business_name
    FROM projects p
    LEFT JOIN contracts c ON p.id = c.project_id 
        AND c.status IN ('active', 'completed')
    LEFT JOIN users u ON c.creative_user_id = u.id
    LEFT JOIN creative_profiles cp ON u.id = cp.user_id
    LEFT JOIN users u2 ON p.umkm_user_id = u2.id
    LEFT JOIN umkm_profiles up ON u2.id = up.user_id
    WHERE p.id = :project_id 
    AND p.umkm_user_id = :user_id
    ORDER BY c.created_at DESC
    LIMIT 1
";

$project = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':project_id' => $project_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

if (!$project || !$project['contract_id']) {
    header("Location: projects.php");
    exit;
}

// Ambil progress updates
$progress_sql = "
    SELECT 
        cp.*,
        cp.created_at as progress_date,
        u.full_name as creative_name,
        u.avatar_url
    FROM contract_progress cp
    LEFT JOIN users u ON cp.creative_user_id = u.id
    WHERE cp.contract_id = :contract_id
    ORDER BY cp.created_at DESC
";

$progress_updates = [];
try {
    $stmt = $conn->prepare($progress_sql);
    $stmt->execute([':contract_id' => $project['contract_id']]);
    $progress_updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

// Ambil work submission terakhir
$work_submission_sql = "
    SELECT ws.*,
           u.full_name as submitted_by_name
    FROM work_submissions ws
    LEFT JOIN users u ON ws.creative_user_id = u.id
    WHERE ws.contract_id = :contract_id
    ORDER BY ws.submitted_at DESC 
    LIMIT 1
";

$work_data = [];
try {
    $stmt = $conn->prepare($work_submission_sql);
    $stmt->execute([':contract_id' => $project['contract_id']]);
    $work_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

// Ambil data revisi yang aktif
$revision_sql = "
    SELECT r.*,
           u.full_name as requested_by_name
    FROM revisions r
    LEFT JOIN users u ON r.requested_by_user_id = u.id
    WHERE r.contract_id = :contract_id
    AND r.status IN ('pending', 'in_progress')
    ORDER BY r.created_at DESC
    LIMIT 1
";

$active_revision = [];
try {
    $stmt = $conn->prepare($revision_sql);
    $stmt->execute([':contract_id' => $project['contract_id']]);
    $active_revision = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

// Cek apakah UMKM sudah memberikan review untuk proyek ini
$review_check_sql = "
    SELECT id FROM reviews 
    WHERE contract_id = :contract_id 
    AND reviewer_id = :reviewer_id
    AND review_type = 'umkm_to_creative'
";

$has_reviewed = false;
try {
    $stmt = $conn->prepare($review_check_sql);
    $stmt->execute([
        ':contract_id' => $project['contract_id'],
        ':reviewer_id' => $_SESSION['user_id']
    ]);
    $has_reviewed = $stmt->fetch() !== false;
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Proyek - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
        
        .progress-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-light);
        }
        
        .progress-bar-main {
            height: 20px;
            border-radius: 10px;
            background-color: #e9ecef;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-fill-main {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            border-radius: 10px;
            transition: width 0.5s ease;
            position: relative;
        }
        
        .progress-fill-main::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background-image: linear-gradient(45deg, rgba(255,255,255,0.15) 25%, transparent 25%, 
                transparent 50%, rgba(255,255,255,0.15) 50%, 
                rgba(255,255,255,0.15) 75%, transparent 75%, transparent);
            background-size: 1rem 1rem;
            animation: progressAnimation 1s linear infinite;
        }
        
        @keyframes progressAnimation {
            0% { background-position: 1rem 0; }
            100% { background-position: 0 0; }
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: var(--primary-color);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--primary-color);
            border: 3px solid white;
            box-shadow: 0 0 0 2px var(--primary-color);
        }
        
        .attachment-thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: transform 0.3s;
            border: 2px solid #dee2e6;
        }
        
        .attachment-thumb:hover {
            transform: scale(1.05);
            border-color: var(--primary-color);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-paid { background-color: #d4edda; color: #155724; }
        .status-partial { background-color: #d1ecf1; color: #0c5460; }
        .status-draft { background-color: #e2e3e5; color: #383d41; }
        .status-active { background-color: #d1ecf1; color: #0c5460; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
        
        .revision-card {
            border-left: 4px solid #ffc107;
            background-color: #fffef0;
        }
        
        .star-rating {
            color: #ffc107;
            font-size: 0.9rem;
        }
        
        .creative-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .project-code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 5px;
            border: 1px dashed #dee2e6;
        }
        
        .file-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background: #f8f9fa;
            border-radius: 20px;
            border: 1px solid #dee2e6;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .modal-image {
            max-height: 70vh;
            object-fit: contain;
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
                        <h1 class="mb-2"><i class="fas fa-tasks me-2"></i>
                            Progress Proyek 
                            <?php if ($project['contract_status'] === 'completed'): ?>
                                <span class="badge bg-success ms-2">SELESAI</span>
                            <?php endif; ?>
                        </h1>
                        <p class="mb-0">Pantau perkembangan proyek: <?php echo htmlspecialchars($project['title']); ?></p>
                        
                        <?php if ($project['project_code']): ?>
                            <div class="mt-2">
                                <small class="opacity-75">
                                    <i class="fas fa-hashtag me-1"></i>
                                    Kode Proyek: <span class="project-code"><?php echo htmlspecialchars($project['project_code']); ?></span>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($project['contract_status'] === 'completed'): ?>
                            <div class="mt-2">
                                <small class="opacity-75">
                                    <i class="fas fa-check-circle me-1"></i>
                                    Proyek selesai pada <?php echo $project['end_date'] ? date('d M Y', strtotime($project['end_date'])) : 'tanggal tidak tersedia'; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="projects.php" class="btn btn-light me-2">
                            <i class="fas fa-arrow-left me-1"></i>Kembali
                        </a>
                        
                        <?php if ($project['contract_status'] !== 'completed'): ?>
                            <a href="project-messages.php?id=<?php echo $project_id; ?>" class="btn btn-light">
                                <i class="fas fa-comments me-1"></i>Chat Creative
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Progress Overview -->
            <div class="row">
                <div class="col-lg-8">
                    <!-- Progress Card -->
                    <div class="progress-card">
                        <h4 class="mb-4">Progress Proyek</h4>
                        
                        <?php if ($project['payment_status'] === 'pending'): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Menunggu Pembayaran</strong><br>
                                Proyek akan dimulai setelah pembayaran dikonfirmasi.
                            </div>
                        <?php else: ?>
                            <!-- Progress Bar -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0"><?php echo $project['progress_percentage'] ?? 0; ?>% Selesai</h5>
                                <span class="status-badge status-<?php echo $project['payment_status'] ?? 'pending'; ?>">
                                    <i class="fas fa-credit-card me-1"></i>
                                    <?php echo strtoupper($project['payment_status'] ?? 'pending'); ?>
                                </span>
                            </div>
                            
                            <div class="progress-bar-main">
                                <div class="progress-fill-main" style="width: <?php echo $project['progress_percentage'] ?? 0; ?>%"></div>
                            </div>
                            
                            <!-- Project Timeline -->
                            <div class="row text-center mt-4">
                                <div class="col-md-4">
                                    <small class="text-muted">Mulai</small>
                                    <div class="fw-bold">
                                        <?php echo $project['start_date'] ? date('d M Y', strtotime($project['start_date'])) : '-'; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Estimasi Selesai</small>
                                    <div class="fw-bold">
                                        <?php echo $project['end_date'] ? date('d M Y', strtotime($project['end_date'])) : '-'; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Status Kontrak</small>
                                    <div>
                                        <span class="status-badge status-<?php echo $project['contract_status'] ?? 'draft'; ?>">
                                            <?php echo strtoupper($project['contract_status'] ?? 'draft'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Progress Updates Timeline -->
                    <?php if ($project['contract_id'] && $project['payment_status'] !== 'pending'): ?>
                        <div class="progress-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="mb-0">Update Progress</h4>
                                <small class="text-muted">
                                    <?php echo count($progress_updates); ?> update
                                </small>
                            </div>
                            
                            <?php if (count($progress_updates) > 0): ?>
                                <div class="timeline">
                                    <?php foreach ($progress_updates as $index => $update): ?>
                                        <div class="timeline-item">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title mb-0">
                                                            <i class="fas fa-chart-line text-primary me-2"></i>
                                                            Progress: <?php echo $update['progress_percentage']; ?>%
                                                        </h6>
                                                        <div class="text-end">
                                                            <small class="text-muted d-block">
                                                                <?php echo date('d M Y', strtotime($update['progress_date'])); ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                <?php echo date('H:i', strtotime($update['progress_date'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($update['description'])): ?>
                                                        <p class="card-text mb-3"><?php echo nl2br(htmlspecialchars($update['description'])); ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($update['attachment_urls']): ?>
                                                        <?php 
                                                        $attachments = json_decode($update['attachment_urls'], true);
                                                        if (is_array($attachments) && count($attachments) > 0):
                                                        ?>
                                                            <div class="mt-3">
                                                                <small class="text-muted d-block mb-2">
                                                                    <i class="fas fa-paperclip me-1"></i>Lampiran:
                                                                </small>
                                                                <div class="d-flex flex-wrap">
                                                                    <?php foreach ($attachments as $attachment): ?>
                                                                        <?php 
                                                                        $file_ext = pathinfo($attachment, PATHINFO_EXTENSION);
                                                                        $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
                                                                        $is_video = in_array(strtolower($file_ext), ['mp4', 'mov', 'avi', 'mkv', 'webm']);
                                                                        ?>
                                                                        <?php if ($is_image): ?>
                                                                            <img src="<?php echo htmlspecialchars($attachment); ?>" 
                                                                                 alt="Lampiran Progress" 
                                                                                 class="attachment-thumb"
                                                                                 data-bs-toggle="modal" 
                                                                                 data-bs-target="#imageModal"
                                                                                 data-image="<?php echo htmlspecialchars($attachment); ?>"
                                                                                 title="Klik untuk melihat">
                                                                        <?php elseif ($is_video): ?>
                                                                            <div class="file-badge">
                                                                                <i class="fas fa-video text-danger"></i>
                                                                                Video
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <a href="<?php echo htmlspecialchars($attachment); ?>" 
                                                                               target="_blank" 
                                                                               class="file-badge text-decoration-none">
                                                                                <i class="fas fa-file me-1"></i>
                                                                                File <?php echo strtoupper($file_ext); ?>
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <div class="mt-3 pt-2 border-top">
                                                        <small class="text-muted">
                                                            <i class="fas fa-user me-1"></i>
                                                            Oleh: <?php echo htmlspecialchars($update['creative_name']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted mb-2">Belum Ada Update Progress</h5>
                                    <p class="text-muted">Creative worker belum mengirimkan update progress.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Active Revision Section -->
                    <?php if ($active_revision): ?>
                        <div class="progress-card revision-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-3" 
                                     style="width: 40px; height: 40px;">
                                    <i class="fas fa-edit text-white"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Revisi Aktif</h5>
                                    <small class="text-muted">
                                        Status: 
                                        <span class="badge bg-<?php echo $active_revision['status'] === 'pending' ? 'warning' : 'info'; ?>">
                                            <?php echo strtoupper(str_replace('_', ' ', $active_revision['status'])); ?>
                                        </span>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong class="d-block mb-1">Deskripsi Revisi:</strong>
                                <p class="mb-2"><?php echo nl2br(htmlspecialchars($active_revision['description'])); ?></p>
                            </div>
                            
                            <?php if ($active_revision['deadline']): ?>
                                <div class="mb-3">
                                    <strong class="d-block mb-1">Deadline Revisi:</strong>
                                    <span class="badge bg-secondary">
                                        <?php echo date('d M Y', strtotime($active_revision['deadline'])); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($active_revision['attachment_urls']): ?>
                                <?php 
                                $revision_attachments = json_decode($active_revision['attachment_urls'], true);
                                if (is_array($revision_attachments) && count($revision_attachments) > 0):
                                ?>
                                    <div class="mb-3">
                                        <strong class="d-block mb-1">Lampiran Revisi:</strong>
                                        <div class="d-flex flex-wrap">
                                            <?php foreach ($revision_attachments as $attachment): ?>
                                                <?php 
                                                $file_ext = pathinfo($attachment, PATHINFO_EXTENSION);
                                                $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
                                                ?>
                                                <?php if ($is_image): ?>
                                                    <img src="<?php echo htmlspecialchars($attachment); ?>" 
                                                         alt="Lampiran Revisi" 
                                                         class="attachment-thumb"
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#imageModal"
                                                         data-image="<?php echo htmlspecialchars($attachment); ?>"
                                                         title="Klik untuk melihat">
                                                <?php else: ?>
                                                    <a href="<?php echo htmlspecialchars($attachment); ?>" 
                                                       target="_blank" 
                                                       class="file-badge text-decoration-none">
                                                        <i class="fas fa-file me-1"></i>
                                                        File <?php echo strtoupper($file_ext); ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="text-muted small">
                                <i class="fas fa-history me-1"></i>
                                Direquest oleh <?php echo htmlspecialchars($active_revision['requested_by_name']); ?> 
                                pada <?php echo date('d M Y H:i', strtotime($active_revision['created_at'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Final Work Submission Section -->
                    <?php if ($work_data): ?>
                        <div class="progress-card mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="mb-0">
                                    <i class="fas fa-paper-plane me-2"></i>Hasil Final
                                </h4>
                                <span class="status-badge status-<?php echo $work_data['status'] ?? 'submitted'; ?>">
                                    <?php echo strtoupper($work_data['status'] ?? 'submitted'); ?>
                                </span>
                            </div>
                            
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($work_data['work_title']); ?></h5>
                                    
                                    <?php if (!empty($work_data['work_description'])): ?>
                                        <div class="mb-3">
                                            <strong class="d-block mb-1">Deskripsi Hasil:</strong>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($work_data['work_description'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($work_data['delivery_notes'])): ?>
                                        <div class="alert alert-info mb-3">
                                            <strong><i class="fas fa-sticky-note me-2"></i>Catatan Pengiriman:</strong>
                                            <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($work_data['delivery_notes'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Final Files -->
                                    <?php if ($work_data['final_files']): 
                                        $final_files = json_decode($work_data['final_files'], true);
                                        if (is_array($final_files) && count($final_files) > 0): ?>
                                            <div class="mb-4">
                                                <strong class="d-block mb-2">
                                                    <i class="fas fa-folder-open me-2"></i>File Hasil Final:
                                                </strong>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php foreach ($final_files as $file): 
                                                        $file_url = $file['url'] ?? $file;
                                                        $file_name = $file['name'] ?? basename($file_url);
                                                        $file_ext = pathinfo($file_url, PATHINFO_EXTENSION);
                                                        $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
                                                    ?>
                                                        <?php if ($is_image): ?>
                                                            <div class="text-center">
                                                                <a href="<?php echo htmlspecialchars($file_url); ?>" 
                                                                   target="_blank" 
                                                                   class="d-inline-block text-decoration-none">
                                                                    <img src="<?php echo htmlspecialchars($file_url); ?>" 
                                                                         alt="<?php echo htmlspecialchars($file_name); ?>" 
                                                                         class="attachment-thumb"
                                                                         data-bs-toggle="modal" 
                                                                         data-bs-target="#imageModal"
                                                                         data-image="<?php echo htmlspecialchars($file_url); ?>"
                                                                         title="Klik untuk melihat">
                                                                </a>
                                                                <small class="d-block mt-1 text-muted">
                                                                    <?php echo htmlspecialchars($file_name); ?>
                                                                </small>
                                                            </div>
                                                        <?php else: ?>
                                                            <a href="<?php echo htmlspecialchars($file_url); ?>" 
                                                               target="_blank" 
                                                               class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                                                <i class="fas fa-file me-2"></i>
                                                                <div class="text-start">
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($file_name); ?></div>
                                                                    <small class="text-muted"><?php echo strtoupper($file_ext); ?> File</small>
                                                                </div>
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Work URL -->
                                    <?php if (!empty($work_data['work_url'])): ?>
                                        <div class="mb-4">
                                            <strong class="d-block mb-2">
                                                <i class="fas fa-link me-2"></i>URL Hasil:
                                            </strong>
                                            <a href="<?php echo htmlspecialchars($work_data['work_url']); ?>" 
                                               target="_blank" 
                                               class="text-decoration-none d-inline-flex align-items-center">
                                                <i class="fas fa-external-link-alt me-2"></i>
                                                <span class="text-truncate" style="max-width: 400px;">
                                                    <?php echo htmlspecialchars($work_data['work_url']); ?>
                                                </span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Preview Images -->
                                    <?php if ($work_data['preview_images']): 
                                        $preview_images = json_decode($work_data['preview_images'], true);
                                        if (is_array($preview_images) && count($preview_images) > 0): ?>
                                            <div class="mb-4">
                                                <strong class="d-block mb-2">
                                                    <i class="fas fa-images me-2"></i>Preview:
                                                </strong>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php foreach ($preview_images as $image): ?>
                                                        <img src="<?php echo htmlspecialchars($image); ?>" 
                                                             alt="Preview" 
                                                             class="attachment-thumb"
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#imageModal"
                                                             data-image="<?php echo htmlspecialchars($image); ?>"
                                                             title="Klik untuk melihat">
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <div class="text-muted small">
                                        <i class="far fa-clock me-1"></i>
                                        Disubmit oleh <?php echo htmlspecialchars($work_data['submitted_by_name']); ?> 
                                        pada <?php echo date('d M Y H:i', strtotime($work_data['submitted_at'])); ?>
                                    </div>
                                    
                                    <!-- Action Buttons untuk UMKM -->
                                    <?php if ($work_data['status'] === 'submitted' && $_SESSION['user_type'] === 'umkm'): ?>
                                        <hr class="my-4">
                                        <div class="mt-3">
                                            <p class="text-muted mb-3">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Silakan review hasil pekerjaan ini. Anda dapat menerima hasil, meminta revisi, atau menolak hasil.
                                            </p>
                                            
                                            <div class="d-flex gap-2 flex-wrap">
                                                <button onclick="confirmAcceptWork(<?php echo $work_data['id']; ?>, <?php echo $project['id']; ?>)" 
                                                        class="btn btn-success">
                                                    <i class="fas fa-check me-1"></i> Terima Hasil
                                                </button>
                                                
                                                <a href="request-revision.php?contract_id=<?php echo $project['contract_id']; ?>&submission_id=<?php echo $work_data['id']; ?>" 
                                                   class="btn btn-warning">
                                                    <i class="fas fa-edit me-1"></i> Minta Revisi
                                                </a>
                                                
                                                <button onclick="confirmRejectWork(<?php echo $work_data['id']; ?>, <?php echo $project['id']; ?>)" 
                                                        class="btn btn-danger">
                                                    <i class="fas fa-times me-1"></i> Tolak Hasil
                                                </button>
                                            </div>
                                            
                                            <small class="text-muted mt-3 d-block">
                                                <strong>Note:</strong> Dengan menerima hasil, proyek akan dinyatakan selesai dan creative worker akan menerima rating.
                                            </small>
                                        </div>
                                    <?php elseif ($work_data['status'] === 'accepted'): ?>
                                        <div class="alert alert-success mt-3">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-check-circle fa-2x me-3"></i>
                                                <div>
                                                    <strong>Hasil telah diterima!</strong><br>
                                                    Proyek ini telah selesai pada <?php echo date('d M Y', strtotime($work_data['reviewed_at'])); ?>.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Tombol untuk memberikan review -->
                                        <?php if (!$has_reviewed && $_SESSION['user_type'] === 'umkm'): ?>
                                            <div class="mt-3">
                                                <a href="give-review.php?contract_id=<?php echo $project['contract_id']; ?>&project_id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-outline-primary">
                                                    <i class="fas fa-star me-1"></i> Beri Rating & Review
                                                </a>
                                                <small class="text-muted d-block mt-1">
                                                    Berikan penilaian untuk pengalaman bekerja dengan creative worker ini.
                                                </small>
                                            </div>
                                        <?php elseif ($has_reviewed): ?>
                                            <div class="alert alert-info mt-3">
                                                <i class="fas fa-check-circle me-2"></i>
                                                Anda sudah memberikan review untuk proyek ini.
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($work_data['status'] === 'revised'): ?>
                                        <div class="alert alert-warning mt-3">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-edit fa-2x me-3"></i>
                                                <div>
                                                    <strong>Revisi sedang dikerjakan</strong><br>
                                                    Creative worker sedang mengerjakan revisi yang diminta.
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($project['progress_percentage'] >= 100 && $project['contract_status'] !== 'completed'): ?>
                        <div class="progress-card">
                            <div class="text-center py-4">
                                <i class="fas fa-hourglass-half fa-3x text-warning mb-3"></i>
                                <h5 class="text-warning mb-2">Menunggu Submission Hasil Final</h5>
                                <p class="text-muted">Progress sudah 100%. Creative worker diharapkan segera mengirimkan hasil final.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar Info -->
                <div class="col-lg-4">
                    <!-- Project Info -->
                    <div class="progress-card">
                        <h5 class="mb-3">
                            <i class="fas fa-info-circle me-2"></i>Info Proyek
                        </h5>
                        <div class="mb-3">
                            <small class="text-muted d-block">Judul Proyek</small>
                            <strong><?php echo htmlspecialchars($project['title']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Deskripsi</small>
                            <p class="mb-0"><?php echo htmlspecialchars($project['description']); ?></p>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Kategori</small>
                            <strong>
                                <?php 
                                $category_names = [
                                    'website' => 'Website',
                                    'logo' => 'Logo & Branding',
                                    'social_media' => 'Social Media',
                                    'video' => 'Video',
                                    'content' => 'Konten',
                                    'marketing' => 'Marketing',
                                    'other' => 'Lainnya'
                                ];
                                echo $category_names[$project['category']] ?? 'Lainnya';
                                ?>
                            </strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Status Proyek</small>
                            <span class="status-badge status-<?php echo $project['status'] ?? 'draft'; ?>">
                                <?php echo strtoupper($project['status'] ?? 'draft'); ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Dibuat Pada</small>
                            <strong><?php echo date('d M Y', strtotime($project['created_at'])); ?></strong>
                        </div>
                    </div>
                    
                    <!-- Creative Worker Info -->
                    <!-- Creative Worker Info -->
                    <?php if ($project['creative_user_id']): ?>
                        <div class="progress-card">
                            <h5 class="mb-3">Creative Worker</h5>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 50px; height: 50px;">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($project['creative_name']); ?></h6>
                                    <?php if ($project['creative_tagline']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($project['creative_tagline']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="project-messages.php?id=<?php echo $project_id; ?>" class="btn btn-primary w-100">
                                <i class="fas fa-comments me-2"></i>Hubungi Creative
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- UMKM Info -->
                    <div class="progress-card">
                        <h5 class="mb-3">
                            <i class="fas fa-store me-2"></i>UMKM
                        </h5>
                        <div class="mb-3">
                            <small class="text-muted d-block">Nama Bisnis</small>
                            <strong><?php echo htmlspecialchars($project['business_name'] ?? 'N/A'); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Nama Pemilik</small>
                            <strong><?php echo htmlspecialchars($project['umkm_name']); ?></strong>
                        </div>
                    </div>
                    
                    <!-- Payment Status -->
                    <div class="progress-card">
                        <h5 class="mb-3">
                            <i class="fas fa-credit-card me-2"></i>Status Pembayaran
                        </h5>
                        <div class="text-center py-3">
                            <div class="display-6 mb-2">
                                <span class="status-badge status-<?php echo $project['payment_status'] ?? 'pending'; ?> px-4 py-2">
                                    <?php echo strtoupper($project['payment_status'] ?? 'pending'); ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                <?php if ($project['payment_status'] === 'pending'): ?>
                                    Menunggu konfirmasi pembayaran
                                <?php elseif ($project['payment_status'] === 'paid'): ?>
                                    Pembayaran telah dikonfirmasi
                                <?php else: ?>
                                    Pembayaran sebagian
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview Gambar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" id="modalImage" class="img-fluid modal-image">
                </div>
                <div class="modal-footer">
                    <a href="#" id="downloadImage" class="btn btn-primary" download>
                        <i class="fas fa-download me-1"></i>Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Image modal functionality
        const imageModal = document.getElementById('imageModal');
        if (imageModal) {
            imageModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const imageUrl = button.getAttribute('data-image');
                const modalImage = document.getElementById('modalImage');
                const downloadLink = document.getElementById('downloadImage');
                
                modalImage.src = imageUrl;
                downloadLink.href = imageUrl;
                
                // Extract filename for download
                const filename = imageUrl.split('/').pop();
                downloadLink.download = filename;
            });
        }
        
        // Fungsi untuk konfirmasi terima hasil
        function confirmAcceptWork(submissionId, projectId) {
            Swal.fire({
                title: 'Terima Hasil Pekerjaan?',
                html: `
                    <div class="text-start">
                        <p>Anda akan menyatakan proyek ini <strong>SELESAI</strong> dengan:</p>
                        <ul class="text-start">
                            <li>Status proyek berubah menjadi "Selesai"</li>
                            <li>Creative worker dapat menerima rating</li>
                            <li>Proyek tidak dapat dikembalikan</li>
                            <li>Semua file hasil menjadi final</li>
                        </ul>
                        <p class="text-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i> Pastikan hasil sudah sesuai sebelum menerima.
                        </p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check me-1"></i> Ya, Terima Hasil',
                cancelButtonText: '<i class="fas fa-times me-1"></i> Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'accept-work.php?submission_id=' + submissionId + '&project_id=' + projectId;
                }
            });
        }
        
        // Fungsi untuk konfirmasi tolak hasil
        function confirmRejectWork(submissionId, projectId) {
            Swal.fire({
                title: 'Tolak Hasil Pekerjaan?',
                html: `
                    <div class="text-start">
                        <p>Anda akan menolak hasil pekerjaan dan perlu:</p>
                        <ul class="text-start">
                            <li>Memberikan alasan penolakan</li>
                            <li>Mengembalikan proyek ke tahap revisi</li>
                            <li>Memberikan kesempatan perbaikan</li>
                            <li>Memperpanjang timeline jika diperlukan</li>
                        </ul>
                        <div class="form-group mt-3">
                            <label for="rejectReason" class="form-label">Alasan Penolakan:</label>
                            <textarea id="rejectReason" class="form-control" rows="3" 
                                      placeholder="Jelaskan alasan penolakan..."></textarea>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-times me-1"></i> Ya, Tolak Hasil',
                cancelButtonText: '<i class="fas fa-arrow-left me-1"></i> Kembali',
                reverseButtons: true,
                preConfirm: () => {
                    const reason = document.getElementById('rejectReason').value;
                    if (!reason.trim()) {
                        Swal.showValidationMessage('Silakan isi alasan penolakan');
                        return false;
                    }
                    return reason;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    window.location.href = 'reject-work.php?submission_id=' + submissionId + 
                                          '&project_id=' + projectId + 
                                          '&reason=' + encodeURIComponent(result.value);
                }
            });
        }
        
        // Tampilkan pesan sukses/error dari URL parameter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('success')) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    html: `
                        <div class="text-start">
                            <p>Hasil pekerjaan telah diterima.</p>
                            <ul class="text-start">
                                <li>Proyek dinyatakan <strong>SELESAI</strong></li>
                                <li>Creative worker telah dinotifikasi</li>
                                <li>Status pembayaran tetap tercatat</li>
                            </ul>
                            <p class="mt-3">
                                <a href="give-review.php?contract_id=<?php echo $project['contract_id']; ?>&project_id=<?php echo $project['id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-star me-1"></i> Beri Review Sekarang
                                </a>
                            </p>
                        </div>
                    `,
                    showConfirmButton: true,
                    confirmButtonText: 'Lanjutkan'
                });
            }
            
            if (urlParams.has('review_success')) {
                Swal.fire({
                    icon: 'success',
                    title: 'Review Terkirim!',
                    text: 'Terima kasih telah memberikan review untuk creative worker.',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
            
            if (urlParams.has('error')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Gagal memproses permintaan. Silakan coba lagi.',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        });
        
        // Auto-refresh progress setiap 30 detik (opsional)
        let autoRefreshEnabled = true;
        
        function checkForUpdates() {
            if (autoRefreshEnabled && document.visibilityState === 'visible') {
                // Anda bisa menambahkan AJAX request di sini untuk real-time updates
                console.log('Checking for updates...');
            }
        }
        
        // Refresh setiap 30 detik
        setInterval(checkForUpdates, 30000);
        
        // Stop auto-refresh ketika tab tidak aktif
        document.addEventListener('visibilitychange', function() {
            autoRefreshEnabled = !document.hidden;
        });
        
        // Fungsi untuk download semua file
        function downloadAllFiles() {
            Swal.fire({
                title: 'Download Semua File',
                text: 'Semua file hasil akan didownload dalam format ZIP.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Download',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Implementasi download ZIP di sini
                    window.location.href = 'download-files.php?contract_id=<?php echo $project['contract_id']; ?>';
                }
            });
        }
    </script>
</body>
</html>