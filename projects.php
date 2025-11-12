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

// Ambil semua proyek milik UMKM ini
$sql = "
    SELECT 
        p.*,
        COUNT(DISTINCT pr.id) as proposal_count,
        COUNT(DISTINCT CASE WHEN pr.status = 'submitted' THEN pr.id END) as new_proposal_count,
        COUNT(DISTINCT c.id) as contract_count,
        MAX(pr.submitted_at) as last_proposal_date
    FROM projects p
    LEFT JOIN proposals pr ON p.id = pr.project_id
    LEFT JOIN contracts c ON p.id = c.project_id AND c.status = 'active'
    WHERE p.umkm_user_id = :user_id
    GROUP BY p.id
    ORDER BY 
        CASE 
            WHEN p.status = 'open' AND MAX(pr.submitted_at) IS NOT NULL THEN 0
            WHEN p.status = 'open' THEN 1
            WHEN p.status = 'in_progress' THEN 2
            WHEN p.status = 'completed' THEN 3
            ELSE 4
        END,
        p.created_at DESC
";

$projects = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

// Hitung statistik
$stats = [
    'total' => count($projects),
    'open' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'draft' => 0,
    'total_proposals' => 0,
    'new_proposals' => 0
];

foreach ($projects as $project) {
    $stats[$project['status']]++;
    $stats['total_proposals'] += $project['proposal_count'];
    $stats['new_proposals'] += $project['new_proposal_count'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyek Saya - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="projects.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2596be;
            --primary-dark: #1e7a9c;
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
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-medium);
        }
        
        .stats-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-light);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card.new-proposals {
            border-left-color: #dc3545;
        }
        
        .stats-card.in-progress {
            border-left-color: var(--accent-color);
        }
        
        .stats-card.completed {
            border-left-color: #28a745;
        }
        
        .project-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            height: 100%;
            background-color: white;
            box-shadow: var(--shadow-light);
            margin-bottom: 20px;
        }
        
        .project-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .status-open { background-color: #d4edda; color: #155724; }
        .status-in_progress { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d1ecf1; color: #0c5460; }
        .status-draft { background-color: #e2e3e5; color: #383d41; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
        
        .proposal-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            border-radius: 4px;
            transition: width 0.5s ease;
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
        
        .category-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
        }
        
        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .filter-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .project-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            font-size: 0.9rem;
            color: var(--text-muted);
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
                        <h1 class="mb-2"><i class="fas fa-briefcase me-2"></i>Proyek Saya</h1>
                        <p class="mb-0">Kelola dan pantau semua proyek Anda di satu tempat</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="create-project.php" class="btn btn-light">
                            <i class="fas fa-plus-circle me-1"></i>Buat Proyek Baru
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                                <p class="text-muted mb-0">Total Proyek</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-briefcase fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card new-proposals">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-0"><?php echo $stats['new_proposals']; ?></h3>
                                <p class="text-muted mb-0">Proposal Baru</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-bell fa-2x text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card in-progress">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-0"><?php echo $stats['in_progress']; ?></h3>
                                <p class="text-muted mb-0">Sedang Berjalan</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-spinner fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card completed">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-0"><?php echo $stats['completed']; ?></h3>
                                <p class="text-muted mb-0">Selesai</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Buttons -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Daftar Proyek</h4>
                <div class="filter-buttons">
                    <button class="btn btn-outline-primary active" data-filter="all">Semua</button>
                    <button class="btn btn-outline-primary" data-filter="open">Open</button>
                    <button class="btn btn-outline-primary" data-filter="in_progress">Berjalan</button>
                    <button class="btn btn-outline-primary" data-filter="completed">Selesai</button>
                    <button class="btn btn-outline-primary" data-filter="draft">Draft</button>
                </div>
            </div>
            
            <!-- Projects List -->
            <div class="row" id="projectsContainer">
                <?php if (count($projects) > 0): ?>
                    <?php foreach ($projects as $project): ?>
                        <div class="col-xl-6 mb-4 project-item" data-status="<?php echo $project['status']; ?>">
                            <div class="card project-card h-100">
                                <div class="card-body">
                                    <!-- Project Header -->
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="category-icon">
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
                                                <i class="<?php echo $icon; ?>"></i>
                                            </div>
                                            <div>
                                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($project['title']); ?></h5>
                                                <span class="text-muted">
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
                                                    echo isset($category_names[$project['category']]) ? $category_names[$project['category']] : 'Lainnya';
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        <span class="status-badge status-<?php echo $project['status']; ?>">
                                            <?php 
                                            $status_names = [
                                                'draft' => 'Draft',
                                                'open' => 'Open',
                                                'in_progress' => 'Sedang Berjalan',
                                                'completed' => 'Selesai',
                                                'cancelled' => 'Dibatalkan'
                                            ];
                                            echo $status_names[$project['status']] ?? $project['status'];
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Project Description -->
                                    <p class="card-text text-muted">
                                        <?php 
                                        $description = $project['description'] ?? '';
                                        echo htmlspecialchars(mb_strlen($description) > 120 ? mb_substr($description, 0, 120) . '...' : $description); 
                                        ?>
                                    </p>
                                    
                                    <!-- Progress Bar (for in_progress projects) -->
                                    <?php if ($project['status'] === 'in_progress' && $project['contract_count'] > 0): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="text-muted">Progress</small>
                                                <small class="text-muted">70%</small>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: 70%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Project Meta -->
                                    <div class="project-meta">
                                        <div>
                                            <i class="fas fa-paper-plane me-1"></i>
                                            <?php echo $project['proposal_count']; ?> Proposal
                                            <?php if ($project['new_proposal_count'] > 0): ?>
                                                <span class="proposal-badge"><?php echo $project['new_proposal_count']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php 
                                            try {
                                                $created = new DateTime($project['created_at']);
                                                echo $created->format('d M Y');
                                            } catch (Exception $e) {
                                                echo "Tanggal tidak valid";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="action-buttons mt-3">
                                        <?php if ($project['status'] === 'draft'): ?>
                                            <a href="edit-project.php?id=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </a>
                                            <a href="publish-project.php?id=<?php echo $project['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-rocket me-1"></i>Publish
                                            </a>
                                            <a href="delete-project.php?id=<?php echo $project['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus proyek ini?')">
                                                <i class="fas fa-trash me-1"></i>Hapus
                                            </a>
                                        <?php elseif ($project['status'] === 'open'): ?>
                                            <a href="view-project.php?id=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>Lihat Detail
                                            </a>
                                            <a href="project-proposals.php?id=<?php echo $project['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-file-alt me-1"></i>Lihat Proposal
                                                <?php if ($project['new_proposal_count'] > 0): ?>
                                                    <span class="badge bg-danger ms-1"><?php echo $project['new_proposal_count']; ?> baru</span>
                                                <?php endif; ?>
                                            </a>
                                        <?php elseif ($project['status'] === 'in_progress'): ?>
                                            <a href="project-progress.php?id=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-tasks me-1"></i>Progress
                                            </a>
                                            <a href="project-messages.php?id=<?php echo $project['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-comments me-1"></i>Chat
                                            </a>
                                        <?php elseif ($project['status'] === 'completed'): ?>
                                            <a href="project-review.php?id=<?php echo $project['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-star me-1"></i>Beri Review
                                            </a>
                                            <a href="project-details.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-info-circle me-1"></i>Detail
                                            </a>
                                        <?php else: ?>
                                            <a href="view-project.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>Lihat Detail
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-briefcase"></i>
                            <h3 class="mt-3">Belum Ada Proyek</h3>
                            <p class="mb-4">Mulai buat proyek pertama Anda dan temukan creative worker terbaik</p>
                            <a href="create-project.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>Buat Proyek Pertama
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter projects
            const filterButtons = document.querySelectorAll('.filter-buttons .btn');
            const projectItems = document.querySelectorAll('.project-item');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    
                    projectItems.forEach(item => {
                        if (filter === 'all' || item.getAttribute('data-status') === filter) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
            
            // Add animation to cards
            const cards = document.querySelectorAll('.project-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Auto refresh for new proposals (every 30 seconds)
            setInterval(() => {
                // In a real application, you would make an AJAX request here
                // to check for new proposals and update the badge counts
                console.log('Checking for new proposals...');
            }, 30000);
        });
    </script>
    <script src="projects.js"></script>
</body>
</html>