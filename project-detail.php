<?php
include "check_login.php";

if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();
$project_id = $_GET['id'] ?? 0;

$sql = "
    SELECT 
        p.*,
        u.full_name as umkm_name,
        up.business_name,
        up.business_type,
        up.business_size,
        up.business_logo_url,
        pr.id as proposal_id,
        pr.status as proposal_status,
        pr.proposed_budget,
        pr.timeline_days,
        pr.cover_letter,
        pr.submitted_at,
        c.id as contract_id,
        c.status as contract_status,
        c.agreed_budget,
        c.progress_percentage,
        c.payment_status,
        c.start_date,
        c.end_date,
        c.contract_terms
    FROM projects p
    LEFT JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    LEFT JOIN proposals pr ON p.id = pr.project_id AND pr.creative_user_id = :user_id
    LEFT JOIN contracts c ON p.id = c.project_id AND c.creative_user_id = :user_id
    WHERE p.id = :project_id
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

if (!$project) {
    header("Location: find-projects.php");
    exit;
}

$skills_sql = "
    SELECT s.name, s.category 
    FROM project_skills ps 
    JOIN skills s ON ps.skill_id = s.id 
    WHERE ps.project_id = :project_id
";
$required_skills = [];
try {
    $stmt = $conn->prepare($skills_sql);
    $stmt->execute([':project_id' => $project_id]);
    $required_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

$attachments = [];
if ($project['attachment_urls']) {
    $attachments = json_decode($project['attachment_urls'], true);
    if (!is_array($attachments)) {
        $attachments = [];
    }
}

$budget_min = number_format($project['budget_range_min'] ?? 0, 0, ',', '.');
$budget_max = number_format($project['budget_range_max'] ?? 0, 0, ',', '.');
$budget_type = $project['budget_type'] ?? 'negotiable';

$status_badges = [
    'draft' => ['class' => 'secondary', 'text' => 'Draft'],
    'open' => ['class' => 'success', 'text' => 'Mencari Creative'],
    'in_progress' => ['class' => 'primary', 'text' => 'Sedang Berjalan'],
    'completed' => ['class' => 'info', 'text' => 'Selesai'],
    'cancelled' => ['class' => 'danger', 'text' => 'Dibatalkan']
];

$proposal_status_badges = [
    'submitted' => ['class' => 'warning', 'text' => 'Menunggu Review'],
    'viewed' => ['class' => 'info', 'text' => 'Dilihat UMKM'],
    'accepted' => ['class' => 'success', 'text' => 'Diterima'],
    'rejected' => ['class' => 'danger', 'text' => 'Ditolak'],
    'cancelled' => ['class' => 'secondary', 'text' => 'Dibatalkan']
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Proyek - Konekin</title>
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
        
        .info-item {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            background: rgba(84, 158, 254, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .info-value {
            color: var(--text-muted);
        }
        
        .skill-badge {
            background: rgba(84, 158, 254, 0.1);
            color: var(--primary-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-right: 8px;
            margin-bottom: 8px;
            display: inline-block;
        }
        
        .attachment-thumb {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: transform 0.3s;
            border: 2px solid var(--border-color);
        }
        
        .attachment-thumb:hover {
            transform: scale(1.05);
            border-color: var(--primary-color);
        }
        
        .progress-bar-custom {
            height: 10px;
            border-radius: 5px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .progress-fill-custom {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            border-radius: 5px;
            transition: width 0.5s ease;
        }
        
        .action-buttons .btn {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .umkm-card {
            text-align: center;
            padding: 20px;
        }
        
        .umkm-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px;
            border: 3px solid var(--primary-color);
        }
        
        .contract-section {
            background: rgba(84, 158, 254, 0.05);
            border-radius: var(--border-radius);
            padding: 20px;
            border-left: 4px solid var(--primary-color);
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
                        <h1 class="mb-2"><i class="fas fa-briefcase me-2"></i>Detail Proyek</h1>
                        <p class="mb-0">Informasi lengkap tentang proyek <?php echo htmlspecialchars($project['title']); ?></p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="find-projects.php" class="btn btn-light me-2">
                            <i class="fas fa-arrow-left me-1"></i>Kembali
                        </a>
                        <?php if (!$project['proposal_id'] && $project['status'] === 'open'): ?>
                            <a href="submit-proposal.php?project_id=<?php echo $project_id; ?>" class="btn btn-light">
                                <i class="fas fa-paper-plane me-1"></i>Ajukan Proposal
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Project Overview -->
                    <div class="detail-card">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                            <span class="badge bg-<?php echo $status_badges[$project['status']]['class']; ?>">
                                <?php echo $status_badges[$project['status']]['text']; ?>
                            </span>
                        </div>
                        
                        <p class="lead"><?php echo htmlspecialchars($project['description']); ?></p>
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-wallet"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Budget</div>
                                        <div class="info-value">
                                              Rp <?php echo $budget_min; ?> - Rp <?php echo $budget_max; ?>
                                            <span class="badge bg-light text-dark ms-2">
                                                <?php 
                                                $budget_types = [
                                                    'fixed' => 'Fixed',
                                                    'hourly' => 'Per Jam',
                                                    'negotiable' => 'Negosiasi'
                                                ];
                                                echo $budget_types[$budget_type] ?? 'Negosiasi';
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Deadline</div>
                                        <div class="info-value">
                                            <?php 
                                            try {
                                                $deadline = new DateTime($project['deadline']);
                                                echo $deadline->format('d M Y');
                                            } catch (Exception $e) {
                                                echo 'Flexible';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-tags"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Kategori</div>
                                        <div class="info-value">
                                            <?php 
                                            $category_names = [
                                                'website' => 'Website Development',
                                                'logo' => 'Logo & Branding',
                                                'social_media' => 'Social Media',
                                                'video' => 'Video Production',
                                                'content' => 'Content Creation',
                                                'marketing' => 'Digital Marketing',
                                                'other' => 'Lainnya'
                                            ];
                                            echo $category_names[$project['category']] ?? 'Lainnya';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Dibuat</div>
                                        <div class="info-value">
                                            <?php 
                                            try {
                                                $created = new DateTime($project['created_at']);
                                                echo $created->format('d M Y H:i');
                                            } catch (Exception $e) {
                                                echo 'Tanggal tidak valid';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Required Skills -->
                    <?php if (count($required_skills) > 0): ?>
                        <div class="detail-card">
                            <h4 class="mb-3"><i class="fas fa-tools me-2"></i>Skills yang Dibutuhkan</h4>
                            <div>
                                <?php foreach ($required_skills as $skill): ?>
                                    <span class="skill-badge">
                                        <i class="fas fa-check me-1"></i><?php echo htmlspecialchars($skill['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Attachments -->
                    <?php if (count($attachments) > 0): ?>
                        <div class="detail-card">
                            <h4 class="mb-3"><i class="fas fa-paperclip me-2"></i>File Lampiran</h4>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($attachments as $attachment): ?>
                                    <?php 
                                    $file_ext = pathinfo($attachment, PATHINFO_EXTENSION);
                                    $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                    ?>
                                    <?php if ($is_image): ?>
                                        <img src="<?php echo htmlspecialchars($attachment); ?>" 
                                             alt="Lampiran" 
                                             class="attachment-thumb"
                                             data-bs-toggle="modal" 
                                             data-bs-target="#imageModal"
                                             data-image="<?php echo htmlspecialchars($attachment); ?>">
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($attachment); ?>" 
                                           target="_blank" 
                                           class="btn btn-outline-secondary d-flex align-items-center me-2 mb-2">
                                            <i class="fas fa-file me-2"></i>
                                            <div>
                                                <div>File <?php echo strtoupper($file_ext); ?></div>
                                                <small class="text-muted">Download</small>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($project['proposal_id']): ?>
                        <div class="detail-card">
                            <h4 class="mb-3"><i class="fas fa-paper-plane me-2"></i>Status Proposal Anda</h4>
                            <div class="contract-section">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5>Proposal Anda</h5>
                                        <p class="mb-2">Dikirim: <?php echo date('d M Y H:i', strtotime($project['submitted_at'])); ?></p>
                                        <p class="mb-2">Budget yang diajukan: <strong>Rp <?php echo number_format($project['proposed_budget'], 0, ',', '.'); ?></strong></p>
                                        <p class="mb-0">Timeline: <strong><?php echo $project['timeline_days']; ?> hari</strong></p>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <span class="badge bg-<?php echo $proposal_status_badges[$project['proposal_status']]['class']; ?> fs-6">
                                            <?php echo $proposal_status_badges[$project['proposal_status']]['text']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($project['contract_id']): ?>
                        <div class="detail-card">
                            <h4 class="mb-3"><i class="fas fa-file-contract me-2"></i>Informasi Kontrak</h4>
                            <div class="contract-section">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <div class="info-label">Status Kontrak</div>
                                            <div class="info-value">
                                                <span class="badge bg-<?php echo $status_badges[$project['contract_status']]['class']; ?>">
                                                    <?php echo $status_badges[$project['contract_status']]['text']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Budget Disetujui</div>
                                            <div class="info-value">
                                                <strong>Rp <?php echo number_format($project['agreed_budget'], 0, ',', '.'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <div class="info-label">Status Pembayaran</div>
                                            <div class="info-value">
                                                <span class="badge bg-<?php 
                                                    echo $project['payment_status'] === 'paid' ? 'success' : 
                                                         ($project['payment_status'] === 'partial' ? 'warning' : 'secondary');
                                                ?>">
                                                    <?php echo strtoupper($project['payment_status'] ?? 'pending'); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Progress</div>
                                            <div class="info-value">
                                                <div class="progress-bar-custom mt-1">
                                                    <div class="progress-fill-custom" style="width: <?php echo $project['progress_percentage'] ?? 0; ?>%"></div>
                                                </div>
                                                <small><?php echo $project['progress_percentage'] ?? 0; ?>% selesai</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($project['contract_status'] === 'active'): ?>
                                    <div class="action-buttons mt-3">
                                        <?php if ($project['payment_status'] === 'paid'): ?>
                                            <a href="project-progress1.php?id=<?php echo $project_id; ?>" class="btn btn-primary">
                                                <i class="fas fa-tasks me-1"></i>Update Progress
                                            </a>
                                        <?php endif; ?>
                                        <a href="project-messages.php?id=<?php echo $project_id; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-comments me-1"></i>Chat UMKM
                                        </a>
                                        <?php if ($project['contract_status'] === 'active' && $project['progress_percentage'] >= 100 && $project['payment_status'] === 'paid'): ?>
                                            <a href="submit-work.php?contract_id=<?php echo $project['contract_id']; ?>" class="btn btn-success">
                                                <i class="fas fa-paper-plane me-1"></i>Submit Hasil Akhir
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- UMKM Info -->
                    <div class="detail-card umkm-card">
                        <h4 class="mb-3">Pemilik Proyek</h4>
                        <?php if ($project['business_logo_url']): ?>
                            <img src="<?php echo htmlspecialchars($project['business_logo_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($project['business_name']); ?>" 
                                 class="umkm-avatar">
                        <?php else: ?>
                            <div class="umkm-avatar bg-primary d-flex align-items-center justify-content-center">
                                <i class="fas fa-store fa-2x text-white"></i>
                            </div>
                        <?php endif; ?>
                        
                        <h5><?php echo htmlspecialchars($project['business_name']); ?></h5>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($project['umkm_name']); ?></p>
                        
                        <div class="mb-3">
                            <span class="badge bg-light text-dark">
                                <?php 
                                $business_types = [
                                    'food' => 'Makanan',
                                    'fashion' => 'Fashion',
                                    'craft' => 'Kerajinan',
                                    'service' => 'Jasa',
                                    'retail' => 'Retail',
                                    'other' => 'Lainnya'
                                ];
                                echo $business_types[$project['business_type']] ?? 'Lainnya';
                                ?>
                            </span>
                            <span class="badge bg-light text-dark ms-1">
                                <?php 
                                $business_sizes = [
                                    'micro' => 'Mikro',
                                    'small' => 'Kecil',
                                    'medium' => 'Menengah'
                                ];
                                echo $business_sizes[$project['business_size']] ?? '-';
                                ?>
                            </span>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="send-message.php?to=<?php echo $project['umkm_user_id']; ?>&project=<?php echo $project_id; ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-envelope me-1"></i>Kirim Pesan
                            </a>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h5 class="mb-3">Aksi Cepat</h5>
                        <div class="d-grid gap-2">
                            <?php if (!$project['proposal_id'] && $project['status'] === 'open'): ?>
                                <a href="submit-proposal.php?project_id=<?php echo $project_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Ajukan Proposal
                                </a>
                            <?php elseif ($project['proposal_id'] && $project['proposal_status'] === 'submitted'): ?>
                                <button class="btn btn-warning" disabled>
                                    <i class="fas fa-clock me-2"></i>Proposal Menunggu Review
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($project['contract_id']): ?>
                                <a href="project-messages.php?id=<?php echo $project_id; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-comments me-2"></i>Chat UMKM
                                </a>
                                <a href="contract-details.php?id=<?php echo $project['contract_id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-file-contract me-2"></i>Lihat Kontrak
                                </a>
                            <?php endif; ?>
                            
                            <a href="find-projects.php" class="btn btn-outline-secondary">
                                <i class="fas fa-search me-2"></i>Cari Proyek Lain
                            </a>
                        </div>
                    </div>
                    
                    <!-- Project Stats -->
                    <div class="detail-card">
                        <h5 class="mb-3">Statistik Proyek</h5>
                        <div class="info-item">
                            <div class="info-content">
                                <div class="info-label">Status</div>
                                <div class="info-value">
                                    <span class="badge bg-<?php echo $status_badges[$project['status']]['class']; ?>">
                                        <?php echo $status_badges[$project['status']]['text']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-content">
                                <div class="info-label">Kategori</div>
                                <div class="info-value"><?php echo $category_names[$project['category']] ?? 'Lainnya'; ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-content">
                                <div class="info-label">Budget Range</div>
                                <div class="info-value">Rp <?php echo $budget_min; ?> - Rp <?php echo $budget_max; ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-content">
                                <div class="info-label">Deadline</div>
                                <div class="info-value">
                                    <?php 
                                    try {
                                        $deadline = new DateTime($project['deadline']);
                                        echo $deadline->format('d M Y');
                                    } catch (Exception $e) {
                                        echo 'Flexible';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview Gambar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" id="modalImage" class="img-fluid" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const imageModal = document.getElementById('imageModal');
        if (imageModal) {
            imageModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const imageUrl = button.getAttribute('data-image');
                const modalImage = document.getElementById('modalImage');
                modalImage.src = imageUrl;
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.detail-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>