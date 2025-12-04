<?php
include "check_login.php";
require_once 'config/Database.php';

// Hanya creative worker yang bisa akses halaman ini
if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

$database = new DatabaseConnection();
$conn = $database->getConnection();
$sql = "
    SELECT 
        p.*,
        pr.title as project_title,
        pr.description as project_description,
        pr.budget_range_min,
        pr.budget_range_max,
        pr.category as project_category,
        pr.deadline as project_deadline,
        u.full_name as umkm_name,
        up.business_name,
        up.business_logo_url,
        (SELECT COUNT(*) FROM proposals p2 WHERE p2.project_id = pr.id) as total_proposals
    FROM proposals p
    JOIN projects pr ON p.project_id = pr.id
    JOIN users u ON pr.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    WHERE p.creative_user_id = :user_id
    ORDER BY p.submitted_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Saya - Konekin</title>
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
        
        .proposal-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            background-color: white;
            box-shadow: var(--shadow-light);
            margin-bottom: 20px;
            padding: 25px;
        }
        
        .proposal-card:hover {
            transform: translateY(-5px);
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }
        
        .budget-range {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
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
                        <h1 class="mb-2"><i class="fas fa-file-alt me-2"></i>Proposal Saya</h1>
                        <p class="mb-0">Kelola dan pantau semua proposal yang telah Anda ajukan</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="find-projects.php" class="btn btn-light">
                            <i class="fas fa-search me-1"></i>Cari Proyek Baru
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Proposal List -->
            <div class="row">
                <div class="col-12">
                    <?php if (count($proposals) > 0): ?>
                        <?php foreach ($proposals as $proposal): ?>
                            <div class="proposal-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <!-- Project Info -->
                                            <div class="d-flex align-items-start mb-3">
                                                <div class="flex-grow-1">
                                                    <span class="project-category mb-2 d-inline-block">
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
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($proposal['project_title']); ?></h5>
                                                    <p class="text-muted mb-2">
                                                        <?php 
                                                        $description = $proposal['project_description'] ?? '';
                                                        echo htmlspecialchars(mb_strlen($description) > 200 ? mb_substr($description, 0, 200) . '...' : $description); 
                                                        ?>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <!-- UMKM Info -->
                                            <div class="d-flex align-items-center mb-3">
                                                <?php if (!empty($proposal['business_logo_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($proposal['business_logo_url']); ?>" alt="Logo UMKM" class="umkm-avatar me-2">
                                                <?php else: ?>
                                                    <div class="umkm-avatar bg-light d-flex align-items-center justify-content-center me-2">
                                                        <i class="fas fa-store text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="mb-0 fw-bold"><?php echo htmlspecialchars($proposal['business_name'] ?? $proposal['umkm_name'] ?? 'Unknown'); ?></p>
                                                    <small class="text-muted">
                                                        <?php echo $proposal['total_proposals']; ?> proposal diajukan
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <!-- Proposal Details -->
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <small class="text-muted">Budget Diusulkan</small>
                                                    <p class="mb-0 fw-bold text-primary">
                                                        Rp <?php echo number_format($proposal['proposed_budget'], 0, ',', '.'); ?>
                                                    </p>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted">Timeline</small>
                                                    <p class="mb-0">
                                                        <?php echo $proposal['timeline_days']; ?> hari
                                                    </p>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted">Diajukan Pada</small>
                                                    <p class="mb-0">
                                                        <?php echo date('d M Y', strtotime($proposal['submitted_at'])); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 text-end">
                                            <!-- Status -->
                                            <div class="mb-3">
                                                <?php
                                                $status_class = 'status-' . $proposal['status'];
                                                $status_text = ucfirst($proposal['status']);
                                                ?>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </div>
                                            
                                            <!-- Actions -->
                                            <div class="btn-group">
                                                <a href="view-proposal.php?id=<?php echo $proposal['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>Detail
                                                </a>
                                                <?php if ($proposal['status'] === 'submitted'): ?>
                                                    <a href="edit-proposal.php?id=<?php echo $proposal['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                        <i class="fas fa-edit me-1"></i>Edit
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3 class="mt-3">Belum ada proposal</h3>
                            <p class="mb-4">Mulai ajukan proposal pertama Anda untuk proyek yang tersedia</p>
                            <a href="find-projects.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Cari Proyek
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>