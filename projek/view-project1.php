<?php
include "check_login.php";
require_once 'config/Database.php';

// Di bagian atas file
require_once 'notificationsmanager.php';

if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

$database = new DatabaseConnection();
$conn = $database->getConnection();

// Get project ID from URL
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($project_id === 0) {
    header("Location: find-projects.php");
    exit;
}

// Query untuk mendapatkan project data
$sql = "
    SELECT 
        p.*,
        u.full_name as umkm_name,
        up.business_name,
        up.business_description,
        up.business_type,
        up.business_logo_url,
        up.business_website,
        COUNT(DISTINCT pr.id) as proposal_count,
        (SELECT COUNT(*) FROM proposals pr2 WHERE pr2.project_id = p.id AND pr2.creative_user_id = :user_id) as already_applied,
        (SELECT COUNT(*) FROM saved_projects sp WHERE sp.project_id = p.id AND sp.creative_user_id = :user_id) as is_saved
    FROM projects p
    JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    LEFT JOIN proposals pr ON p.id = pr.project_id
    WHERE p.id = :project_id
    GROUP BY p.id
";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ':project_id' => $project_id,
    ':user_id' => $_SESSION['user_id']
]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: find-projects.php");
    exit;
}

// Inisialisasi NotificationManager SETELAH project data didapatkan
$notificationManager = new NotificationManager();

// Handle proposal submission - PERBAIKI BAGIAN INI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_proposal'])) {
    $cover_letter = $_POST['cover_letter'] ?? '';
    $proposed_budget = $_POST['proposed_budget'] ?? 0;
    $timeline_days = $_POST['timeline_days'] ?? 0;
    
    // Validate input
    $errors = [];
    
    if (empty($cover_letter)) {
        $errors[] = "Cover letter harus diisi";
    }
    
    if ($proposed_budget <= 0) {
        $errors[] = "Budget yang diusulkan harus lebih dari 0";
    }
    
    if ($timeline_days <= 0) {
        $errors[] = "Timeline harus lebih dari 0 hari";
    }
    
    // Check if already applied
    if ($project['already_applied'] > 0) {
        $errors[] = "Anda sudah mengajukan proposal untuk proyek ini";
    }
    
    if (empty($errors)) {
        try {
            // Generate UUID
            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            // Insert proposal
            $insert_sql = "
                INSERT INTO proposals (uuid, project_id, creative_user_id, cover_letter, proposed_budget, timeline_days, status, submitted_at)
                VALUES (:uuid, :project_id, :creative_user_id, :cover_letter, :proposed_budget, :timeline_days, 'submitted', NOW())
            ";
            
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->execute([
                ':uuid' => $uuid,
                ':project_id' => $project_id,
                ':creative_user_id' => $_SESSION['user_id'],
                ':cover_letter' => $cover_letter,
                ':proposed_budget' => $proposed_budget,
                ':timeline_days' => $timeline_days
            ]);
            
            $proposal_id = $conn->lastInsertId();
            
            // CREATE NOTIFICATION - YANG SUDAH DIPERBAIKI
            error_log("Creating notification for proposal: $proposal_id, project: $project_id, UMKM: " . $project['umkm_user_id']);
            
            $notificationManager->notifyProposalSubmitted(
                $proposal_id,
                $_SESSION['user_id'], // creative user id
                $project_id,
                $project['umkm_user_id'] // umkm user id
            );
            
            $success = "Proposal berhasil diajukan! Notifikasi telah dikirim ke UMKM.";
            
            // Refresh project data
            $stmt->execute([
                ':project_id' => $project_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $errors[] = "Terjadi kesalahan: " . $e->getMessage();
            error_log("Proposal submission error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['title']); ?> - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/view-projects.css">
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Navigation & Header -->
            <div class="page-header">
                <div class="header-content">
                    <div class="back-navigation">
                        <a href="find-projects.php" class="btn btn-back">
                            <i class="fas fa-arrow-left me-2"></i>Kembali ke Pencarian
                        </a>
                    </div>
                    <div class="header-actions">
                        <a href="saved-projects.php" class="btn btn-proposals">
                            <i class="fas fa-bookmark me-2"></i>Proyek Disimpan
                        </a>
                        <a href="my-proposals.php" class="btn btn-proposals">
                            <i class="fas fa-file-alt me-2"></i>Proposal Saya
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Project Overview -->
            <div class="project-overview">
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Project Header -->
                        <div class="project-header-card">
                            <div class="project-meta">
                                <span class="project-category">
                                    <?php 
                                    $category_icons = [
                                        'website' => 'fas fa-globe',
                                        'logo' => 'fas fa-palette',
                                        'social_media' => 'fas fa-hashtag',
                                        'video' => 'fas fa-video',
                                        'content' => 'fas fa-file-alt',
                                        'marketing' => 'fas fa-bullhorn',
                                        'other' => 'fas fa-ellipsis-h'
                                    ];
                                    $icon = isset($category_icons[$project['category']]) ? $category_icons[$project['category']] : 'fas fa-folder';
                                    ?>
                                    <i class="<?php echo $icon; ?> me-2"></i>
                                    <?php 
                                    $category_names = [
                                        'website' => 'Website',
                                        'logo' => 'Logo',
                                        'social_media' => 'Social Media',
                                        'video' => 'Video',
                                        'content' => 'Content',
                                        'marketing' => 'Marketing',
                                        'other' => 'Lainnya'
                                    ];
                                    echo isset($category_names[$project['category']]) ? $category_names[$project['category']] : 'Lainnya';
                                    ?>
                                </span>
                                
                                <div class="project-status">
                                    <span class="status-badge status-open">
                                        <i class="fas fa-circle me-1"></i>Open for Proposals
                                    </span>
                                </div>
                            </div>
                            
                            <h1 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h1>
                            <p class="project-description"><?php echo htmlspecialchars($project['description']); ?></p>
                            
                            <!-- Project Stats -->
                            <div class="project-stats">
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-paper-plane"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo $project['proposal_count']; ?></div>
                                        <div class="stat-label">Proposal Diajukan</div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($project['deadline'])): ?>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">
                                            <?php 
                                            try {
                                                $deadline = new DateTime($project['deadline']);
                                                $now = new DateTime();
                                                $interval = $now->diff($deadline);
                                                if ($deadline > $now) {
                                                    echo $interval->days;
                                                } else {
                                                    echo "0";
                                                }
                                            } catch (Exception $e) {
                                                echo "?";
                                            }
                                            ?>
                                        </div>
                                        <div class="stat-label">Hari Tersisa</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">
                                            <?php echo date('d M', strtotime($project['created_at'])); ?>
                                        </div>
                                        <div class="stat-label">Diposting</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Details -->
                        <div class="project-details-card">
                            <h3 class="section-title">
                                <i class="fas fa-info-circle me-2"></i>Detail Proyek
                            </h3>
                            
                            <!-- Required Skills -->
                            <?php if (!empty($project['required_skills'])): ?>
                            <div class="detail-section">
                                <h5 class="detail-label">Keterampilan yang Dibutuhkan</h5>
                                <div class="skills-container">
                                    <?php 
                                    $skills = json_decode($project['required_skills'], true);
                                    if (is_array($skills) && count($skills) > 0):
                                        foreach ($skills as $skill):
                                    ?>
                                        <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php 
                                        endforeach;
                                    endif;
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Project Timeline -->
                            <?php if (!empty($project['deadline'])): ?>
                            <div class="detail-section">
                                <h5 class="detail-label">Timeline Proyek</h5>
                                <div class="timeline-info">
                                    <div class="timeline-item">
                                        <i class="fas fa-flag-start timeline-icon"></i>
                                        <div class="timeline-content">
                                            <div class="timeline-title">Mulai Pengerjaan</div>
                                            <div class="timeline-date">Setelah proposal diterima</div>
                                        </div>
                                    </div>
                                    <div class="timeline-item">
                                        <i class="fas fa-flag-checkered timeline-icon"></i>
                                        <div class="timeline-content">
                                            <div class="timeline-title">Deadline</div>
                                            <div class="timeline-date"><?php echo date('d F Y', strtotime($project['deadline'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Additional Requirements -->
                            <div class="detail-section">
                                <h5 class="detail-label">Persyaratan Tambahan</h5>
                                <div class="requirements-list">
                                    <div class="requirement-item">
                                        <i class="fas fa-check requirement-icon"></i>
                                        <span>Portfolio relevan wajib dilampirkan</span>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="fas fa-check requirement-icon"></i>
                                        <span>Update progress mingguan</span>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="fas fa-check requirement-icon"></i>
                                        <span>Komunikasi aktif via platform</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Budget & Action Card -->
                        <div class="sidebar-card budget-card">
                            <h4 class="card-title">
                                <i class="fas fa-money-bill-wave me-2"></i>Budget Proyek
                            </h4>
                            <div class="budget-amount">
                                <?php if ($project['budget_range_min'] && $project['budget_range_max']): ?>
                                    <span class="budget-min">Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?></span>
                                    <span class="budget-separator">-</span>
                                    <span class="budget-max">Rp <?php echo number_format($project['budget_range_max'], 0, ',', '.'); ?></span>
                                <?php elseif ($project['budget_range_min']): ?>
                                    <span class="budget-min">Mulai dari Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?></span>
                                <?php else: ?>
                                    <span class="budget-negotiable">Harga Negotiable</span>
                                <?php endif; ?>
                            </div>
                            <div class="budget-note">
                                <i class="fas fa-info-circle me-1"></i>
                                Budget dapat disesuaikan dengan kualifikasi
                            </div>
                            
                            <?php if (isset($save_message)): ?>
                                <div class="alert alert-success alert-modern">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $save_message; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($save_error)): ?>
                                <div class="alert alert-danger alert-modern">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo $save_error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($project['already_applied'] > 0): ?>
                                <div class="applied-status">
                                    <div class="applied-badge">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Proposal Telah Diajukan
                                    </div>
                                    <p class="applied-message">
                                        Anda sudah mengajukan proposal untuk proyek ini. 
                                        <a href="my-proposals.php" class="status-link">Lihat Status</a>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="action-buttons">
                                    <button class="btn btn-primary btn-apply" data-bs-toggle="modal" data-bs-target="#proposalModal">
                                        <i class="fas fa-paper-plane me-2"></i>Ajukan Proposal
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="save_project" class="btn <?php echo $project['is_saved'] ? 'btn-warning' : 'btn-outline-secondary'; ?> btn-save">
                                            <i class="fas fa-bookmark me-2"></i>
                                            <?php echo $project['is_saved'] ? '✓ Disimpan' : 'Simpan Proyek'; ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- UMKM Profile Card -->
                        <div class="sidebar-card umkm-card">
                            <h4 class="card-title">
                                <i class="fas fa-store me-2"></i>Tentang Klien
                            </h4>
                            <div class="umkm-profile">
                                <div class="umkm-avatar-container">
                                    <?php if (!empty($project['business_logo_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($project['business_logo_url']); ?>" alt="Logo UMKM" class="umkm-avatar">
                                    <?php else: ?>
                                        <div class="umkm-avatar-placeholder">
                                            <i class="fas fa-store"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="umkm-info">
                                    <h5 class="umkm-name"><?php echo htmlspecialchars($project['business_name'] ?? $project['umkm_name'] ?? 'Unknown'); ?></h5>
                                    <p class="umkm-description"><?php echo htmlspecialchars($project['business_description'] ?? 'Tidak ada deskripsi'); ?></p>
                                    
                                    <div class="umkm-meta">
                                        <div class="umkm-meta-item">
                                            <i class="fas fa-building me-2"></i>
                                            <span>
                                                <?php 
                                                $business_types = [
                                                    'food' => 'Makanan & Minuman',
                                                    'fashion' => 'Fashion',
                                                    'craft' => 'Kerajinan',
                                                    'service' => 'Jasa',
                                                    'retail' => 'Retail',
                                                    'other' => 'Lainnya'
                                                ];
                                                echo isset($business_types[$project['business_type']]) ? $business_types[$project['business_type']] : 'UMKM';
                                                ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($project['business_website'])): ?>
                                        <div class="umkm-meta-item">
                                            <i class="fas fa-globe me-2"></i>
                                            <a href="<?php echo htmlspecialchars($project['business_website']); ?>" target="_blank" class="umkm-website">Visit Website</a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="umkm-rating">
                                        <div class="rating-stars">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star-half-alt"></i>
                                        </div>
                                        <span class="rating-text">4.5 (12 reviews)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Proposal Modal -->
            <?php if ($project['already_applied'] == 0): ?>
            <div class="modal fade" id="proposalModal" tabindex="-1" aria-labelledby="proposalModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title" id="proposalModalLabel">
                                <i class="fas fa-paper-plane me-2"></i>Ajukan Proposal
                            </h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php if (isset($success)): ?>
                                <div class="alert alert-success alert-modern">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $success; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger alert-modern">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="proposalForm">
                                <div class="form-section">
                                    <h5 class="form-section-title">Informasi Budget & Timeline</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="proposed_budget" class="form-label">
                                                    <i class="fas fa-money-bill-wave me-2"></i>Budget yang Diusulkan
                                                </label>
                                                <div class="input-group budget-input">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number" class="form-control" id="proposed_budget" name="proposed_budget" 
                                                           value="<?php echo $_POST['proposed_budget'] ?? ''; ?>" 
                                                           min="10000" step="1000" required>
                                                </div>
                                                <div class="form-hint">
                                                    Budget harus dalam rentang yang wajar
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="timeline_days" class="form-label">
                                                    <i class="fas fa-clock me-2"></i>Timeline Pengerjaan
                                                </label>
                                                <div class="input-group timeline-input">
                                                    <input type="number" class="form-control" id="timeline_days" name="timeline_days" 
                                                           value="<?php echo $_POST['timeline_days'] ?? ''; ?>" 
                                                           min="1" max="365" required>
                                                    <span class="input-group-text">hari</span>
                                                </div>
                                                <div class="form-hint">
                                                    Estimasi waktu pengerjaan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <h5 class="form-section-title">Cover Letter</h5>
                                    <div class="form-group">
                                        <label for="cover_letter" class="form-label">
                                            <i class="fas fa-edit me-2"></i>Deskripsi Proposal
                                        </label>
                                        <textarea class="form-control" id="cover_letter" name="cover_letter" rows="8" 
                                                  placeholder="Jelaskan mengapa Anda cocok untuk proyek ini, pengalaman relevan, dan pendekatan yang akan Anda gunakan..." 
                                                  required><?php echo $_POST['cover_letter'] ?? ''; ?></textarea>
                                        <div class="form-hint">
                                            <span id="charCount">0</span>/5000 karakter. Jelaskan secara detail mengapa Anda adalah pilihan terbaik.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-tips">
                                    <h6><i class="fas fa-lightbulb me-2"></i>Tips Proposal yang Menarik:</h6>
                                    <ul>
                                        <li>Sebutkan pengalaman relevan dengan proyek ini</li>
                                        <li>Jelaskan pendekatan kreatif yang akan digunakan</li>
                                        <li>Sertakan portofolio atau contoh karya sebelumnya</li>
                                        <li>Berikan estimasi timeline yang realistis</li>
                                    </ul>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" form="proposalForm" name="submit_proposal" class="btn btn-primary btn-submit">
                                <i class="fas fa-paper-plane me-2"></i>Ajukan Proposal
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character count for cover letter
        document.getElementById('cover_letter').addEventListener('input', function() {
            document.getElementById('charCount').textContent = this.value.length;
        });
    </script>
</body>
</html>