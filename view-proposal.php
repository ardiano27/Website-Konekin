<?php
include "check_login.php";
require_once 'config/Database.php';

// Hanya creative worker yang bisa akses halaman ini
if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

// Cek apakah parameter ID ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my-proposals.php");
    exit;
}

$proposal_id = $_GET['id'];
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Ambil data proposal beserta informasi terkait
$sql = "
    SELECT 
        p.*,
        pr.title as project_title,
        pr.description as project_description,
        pr.budget_range_min,
        pr.budget_range_max,
        pr.category as project_category,
        pr.deadline as project_deadline,
        pr.status as project_status,
        pr.required_skills,
        pr.attachment_urls as project_attachments,
        u.full_name as umkm_name,
        u.email as umkm_email,
        u.phone as umkm_phone,
        up.business_name,
        up.business_type,
        up.business_description,
        up.business_logo_url,
        up.business_website,
        cp.tagline as creative_tagline,
        cp.bio as creative_bio
    FROM proposals p
    JOIN projects pr ON p.project_id = pr.id
    JOIN users u ON pr.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    LEFT JOIN creative_profiles cp ON p.creative_user_id = cp.user_id
    WHERE p.id = :proposal_id AND p.creative_user_id = :user_id
";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ':proposal_id' => $proposal_id,
    ':user_id' => $_SESSION['user_id']
]);
$proposal = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika proposal tidak ditemukan atau bukan milik user
if (!$proposal) {
    header("Location: my-proposals.php");
    exit;
}

// Cek apakah ada kontrak yang terkait dengan proposal ini
$contract_sql = "SELECT id, status FROM contracts WHERE proposal_id = :proposal_id";
$contract_stmt = $conn->prepare($contract_sql);
$contract_stmt->execute([':proposal_id' => $proposal_id]);
$contract = $contract_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Proposal - Konekin</title>
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
            --shadow-hover: 0 10px 25px rgba(0,0,0,0.15);
            --border-radius: 12px;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: var(--text-dark);
        }
        
        .main-content {
            padding: 10px 10px 10px;
            margin-top: 0;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-medium);
        }
        
        .info-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            background-color: white;
            box-shadow: var(--shadow-light);
            margin-bottom: 20px;
            padding: 25px;
        }
        
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .status-submitted { background-color: #e3f2fd; color: #1976d2; }
        .status-viewed { background-color: #fff3e0; color: #f57c00; }
        .status-accepted { background-color: #e8f5e8; color: #2e7d32; }
        .status-rejected { background-color: #ffebee; color: #c62828; }
        .status-cancelled { background-color: #f5f5f5; color: #616161; }
        
        .project-category {
            display: inline-block;
            background-color: #e9f7fe;
            color: var(--primary-color);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .umkm-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border-color);
        }
        
        .budget-range {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .detail-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .skill-tag {
            display: inline-block;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin: 2px;
        }
        
        .contract-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <a href="my-proposals.php" class="btn btn-light btn-sm me-3">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <div>
                                <h1 class="mb-2"><i class="fas fa-file-alt me-2"></i>Detail Proposal</h1>
                                <p class="mb-0">Lihat detail lengkap proposal yang telah Anda ajukan</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <?php
                        $status_class = 'status-' . $proposal['status'];
                        $status_text = ucfirst($proposal['status']);
                        ?>
                        <span class="status-badge <?php echo $status_class; ?> me-2">
                            <?php echo $status_text; ?>
                        </span>
                        
                        <?php if ($contract): ?>
                            <span class="status-badge contract-badge">
                                <i class="fas fa-file-contract me-1"></i>
                                Kontrak <?php echo ucfirst($contract['status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Informasi Project -->
                <div class="col-lg-8">
                    <div class="info-card">
                        <h4 class="mb-4">
                            <i class="fas fa-project-diagram me-2 text-primary"></i>
                            Informasi Project
                        </h4>
                        
                        <div class="detail-item">
                            <h5><?php echo htmlspecialchars($proposal['project_title']); ?></h5>
                            <span class="project-category mb-3 d-inline-block">
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
                                $icon = isset($category_icons[$proposal['project_category']]) ? $category_icons[$proposal['project_category']] : 'fas fa-folder';
                                ?>
                                <i class="<?php echo $icon; ?> me-1"></i>
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
                                echo isset($category_names[$proposal['project_category']]) ? $category_names[$proposal['project_category']] : 'Lainnya';
                                ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <h6>Deskripsi Project</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($proposal['project_description'])); ?></p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <h6>Budget Range</h6>
                                    <p class="budget-range mb-0">
                                        Rp <?php echo number_format($proposal['budget_range_min'], 0, ',', '.'); ?> - 
                                        Rp <?php echo number_format($proposal['budget_range_max'], 0, ',', '.'); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <h6>Deadline Project</h6>
                                    <p class="mb-0">
                                        <i class="far fa-calendar me-1"></i>
                                        <?php echo date('d M Y', strtotime($proposal['project_deadline'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($proposal['required_skills'])): ?>
                        <div class="detail-item">
                            <h6>Skills yang Dibutuhkan</h6>
                            <?php
                            $skills = json_decode($proposal['required_skills'], true);
                            if (is_array($skills) && count($skills) > 0):
                                foreach ($skills as $skill):
                                    if (!empty(trim($skill))):
                            ?>
                                <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
                            <?php
                                    endif;
                                endforeach;
                            endif;
                            ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-item">
                            <h6>Status Project</h6>
                            <span class="badge bg-<?php 
                                switch($proposal['project_status']) {
                                    case 'open': echo 'success'; break;
                                    case 'in_progress': echo 'primary'; break;
                                    case 'completed': echo 'secondary'; break;
                                    case 'cancelled': echo 'danger'; break;
                                    default: echo 'light';
                                }
                            ?>">
                                <?php echo ucfirst($proposal['project_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Detail Proposal -->
                    <div class="info-card">
                        <h4 class="mb-4">
                            <i class="fas fa-file-signature me-2 text-primary"></i>
                            Detail Proposal Anda
                        </h4>
                        
                        <div class="detail-item">
                            <h6>Cover Letter</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($proposal['cover_letter'])); ?></p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <h6>Budget yang Diusulkan</h6>
                                    <p class="budget-range mb-0">
                                        Rp <?php echo number_format($proposal['proposed_budget'], 0, ',', '.'); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <h6>Timeline Pengerjaan</h6>
                                    <p class="mb-0">
                                        <?php echo $proposal['timeline_days']; ?> hari
                                    </p>
                                    <?php if (!empty($proposal['timeline_description'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($proposal['timeline_description']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <h6>Tanggal Pengajuan</h6>
                            <p class="mb-0">
                                <i class="far fa-clock me-1"></i>
                                <?php echo date('d M Y H:i', strtotime($proposal['submitted_at'])); ?>
                            </p>
                        </div>
                        
                        <?php if ($proposal['status'] === 'submitted'): ?>
                        <div class="mt-4">
                            <a href="edit-proposal.php?id=<?php echo $proposal['id']; ?>" class="btn btn-primary me-2">
                                <i class="fas fa-edit me-1"></i>Edit Proposal
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Informasi UMKM -->
                <div class="col-lg-4">
                    <div class="info-card">
                        <h4 class="mb-4">
                            <i class="fas fa-store me-2 text-primary"></i>
                            Informasi UMKM
                        </h4>
                        
                        <div class="text-center mb-4">
                            <?php if (!empty($proposal['business_logo_url'])): ?>
                                <img src="<?php echo htmlspecialchars($proposal['business_logo_url']); ?>" alt="Logo UMKM" class="umkm-avatar mb-3">
                            <?php else: ?>
                                <div class="umkm-avatar bg-light d-flex align-items-center justify-content-center mx-auto mb-3">
                                    <i class="fas fa-store text-muted fa-2x"></i>
                                </div>
                            <?php endif; ?>
                            
                            <h5><?php echo htmlspecialchars($proposal['business_name'] ?? $proposal['umkm_name']); ?></h5>
                            <?php if (!empty($proposal['business_type'])): ?>
                                <span class="badge bg-light text-dark mb-2">
                                    <?php echo ucfirst($proposal['business_type']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($proposal['business_description'])): ?>
                        <div class="detail-item">
                            <h6>Deskripsi Bisnis</h6>
                            <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($proposal['business_description'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-item">
                            <h6>Kontak</h6>
                            <div class="small">
                                <p class="mb-1">
                                    <i class="fas fa-user me-2"></i>
                                    <?php echo htmlspecialchars($proposal['umkm_name']); ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-envelope me-2"></i>
                                    <?php echo htmlspecialchars($proposal['umkm_email']); ?>
                                </p>
                                <?php if (!empty($proposal['umkm_phone'])): ?>
                                <p class="mb-0">
                                    <i class="fas fa-phone me-2"></i>
                                    <?php echo htmlspecialchars($proposal['umkm_phone']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($proposal['business_website'])): ?>
                        <div class="detail-item">
                            <h6>Website</h6>
                            <a href="<?php echo htmlspecialchars($proposal['business_website']); ?>" target="_blank" class="small">
                                <?php echo htmlspecialchars($proposal['business_website']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Status dan Timeline -->
                    <div class="info-card">
                        <h4 class="mb-4">
                            <i class="fas fa-history me-2 text-primary"></i>
                            Status & Timeline
                        </h4>
                        
                        <div class="timeline">
                            <div class="timeline-item <?php echo $proposal['status'] === 'submitted' ? 'active' : 'completed'; ?>">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Proposal Diajukan</h6>
                                    <small class="text-muted">
                                        <?php echo date('d M Y H:i', strtotime($proposal['submitted_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="timeline-item <?php echo in_array($proposal['status'], ['viewed', 'accepted', 'rejected']) ? 'completed' : ''; ?> <?php echo $proposal['status'] === 'viewed' ? 'active' : ''; ?>">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Proposal Dilihat</h6>
                                    <small class="text-muted">
                                        <?php echo in_array($proposal['status'], ['viewed', 'accepted', 'rejected']) ? 'Telah dilihat oleh UMKM' : 'Menunggu review UMKM'; ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="timeline-item <?php echo in_array($proposal['status'], ['accepted', 'rejected']) ? 'completed' : ''; ?> <?php echo $proposal['status'] === 'accepted' ? 'active' : ''; ?>">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Proposal Diterima</h6>
                                    <small class="text-muted">
                                        <?php echo $proposal['status'] === 'accepted' ? 'Proposal diterima' : ($proposal['status'] === 'rejected' ? 'Proposal ditolak' : 'Menunggu keputusan'); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <?php if ($contract): ?>
                            <div class="timeline-item <?php echo $contract['status'] !== 'draft' ? 'completed' : ''; ?>">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Kontrak Dibuat</h6>
                                    <small class="text-muted">
                                        Status: <?php echo ucfirst($contract['status']); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>