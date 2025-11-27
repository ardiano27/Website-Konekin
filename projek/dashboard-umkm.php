<?php
session_start();

// Redirect jika belum login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'umkm') {
    header("Location: login.php");
    exit();
}

require_once 'config/Database.php';

try {
    $database = new DatabaseConnection();
    $db = $database->getConnection();

    // Query untuk mendapatkan statistik UMKM
    $stats_query = "
        SELECT 
            COUNT(p.id) as total_projects,
            COUNT(CASE WHEN p.status = 'open' THEN 1 END) as open_projects,
            COUNT(CASE WHEN p.status = 'in_progress' THEN 1 END) as active_projects,
            COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_projects,
            COUNT(prop.id) as total_proposals,
            COUNT(c.id) as total_contracts
        FROM projects p
        LEFT JOIN proposals prop ON p.id = prop.project_id
        LEFT JOIN contracts c ON p.id = c.project_id
        WHERE p.umkm_user_id = :user_id
    ";

    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Query untuk mendapatkan proyek terbaru
    $recent_projects_query = "
        SELECT p.*, COUNT(prop.id) as proposal_count
        FROM projects p 
        LEFT JOIN proposals prop ON p.id = prop.project_id
        WHERE p.umkm_user_id = :user_id 
        GROUP BY p.id 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ";

    $stmt = $db->prepare($recent_projects_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $recent_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk mendapatkan proposal terbaru
    $recent_proposals_query = "
        SELECT prop.*, p.title as project_title, u.full_name as creative_name
        FROM proposals prop
        JOIN projects p ON prop.project_id = p.id
        JOIN users u ON prop.creative_user_id = u.id
        WHERE p.umkm_user_id = :user_id
        ORDER BY prop.submitted_at DESC
        LIMIT 5
    ";

    $stmt = $db->prepare($recent_proposals_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $recent_proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $stats = [];
    $recent_projects = [];
    $recent_proposals = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard UMKM - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #6eadffff 0%, #3E7FD5 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-title {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .recent-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: none;
            height: 100%;
        }

        .project-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-open { background: #d4edda; color: #155724; }
        .status-in_progress { background: #fff3cd; color: #856404; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .status-draft { background: #e2e3e5; color: #383d41; }

        .quick-action-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: none;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .action-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }

        .bg-primary-light { background: rgba(62, 127, 213, 0.1); color: #3E7FD5; }
        .bg-success-light { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .bg-warning-light { background: rgba(255, 193, 7, 0.1); color: #ffc107; }

        .section-title {
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }

        .proposal-badge {
            background: #3E7FD5;
            color: white;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include 'dashboard-sidebar.php'; ?>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">Halo, <?php echo $_SESSION['full_name'] ?? 'UMKM'; ?>! 👋</h1>
                    <p class="lead mb-0">Selamat datang di dashboard UMKM Anda. Kelola proyek dan temukan kreator terbaik untuk bisnis Anda.</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white rounded-pill px-3 py-2 d-inline-block">
                        <small class="text-muted">Status:</small>
                        <span class="badge bg-success ms-1">Aktif</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Statistik Cepat -->
        <div class="row mb-4">
            <div class="col-12">
                <h3 class="section-title">Ringkasan Aktivitas</h3>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary-light">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <div class="stat-number text-primary"><?php echo $stats['total_projects'] ?? 0; ?></div>
                    <div class="stat-title">Total Proyek</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success-light">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-number text-success"><?php echo $stats['active_projects'] ?? 0; ?></div>
                    <div class="stat-title">Proyek Aktif</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-warning-light">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-number text-warning"><?php echo $stats['total_proposals'] ?? 0; ?></div>
                    <div class="stat-title">Total Proposal</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary-light">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="stat-number text-info"><?php echo $stats['total_contracts'] ?? 0; ?></div>
                    <div class="stat-title">Kontrak Aktif</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Quick Actions -->
            <div class="col-lg-4">
                <h4 class="section-title">Aksi Cepat</h4>
                <div class="row g-3">
                    <div class="col-12">
                        <a href="create-project.php" class="text-decoration-none">
                            <div class="quick-action-card">
                                <div class="action-icon bg-primary-light">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <h5>Buat Proyek Baru</h5>
                                <p class="text-muted mb-0">Posting proyek baru untuk dicreative worker</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-12">
                        <a href="projects.php" class="text-decoration-none">
                            <div class="quick-action-card">
                                <div class="action-icon bg-success-light">
                                    <i class="fas fa-list"></i>
                                </div>
                                <h5>Kelola Proyek</h5>
                                <p class="text-muted mb-0">Lihat dan kelola semua proyek Anda</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-12">
                        <a href="find-creatives.php" class="text-decoration-none">
                            <div class="quick-action-card">
                                <div class="action-icon bg-warning-light">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h5>Cari Kreator</h5>
                                <p class="text-muted mb-0">Temukan creative worker terbaik</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Proyek Terbaru -->
            <div class="col-lg-8">
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="section-title">Proyek Terbaru</h4>
                            <a href="projects.php" class="btn btn-outline-primary btn-sm">Lihat Semua</a>
                        </div>
                        <div class="recent-card">
                            <?php if (!empty($recent_projects)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_projects as $project): ?>
                                        <div class="list-group-item px-0">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($project['title']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo date('d M Y', strtotime($project['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-3">
                                                    <span class="proposal-badge">
                                                        <?php echo $project['proposal_count']; ?> Proposal
                                                    </span>
                                                </div>
                                                <div class="col-md-3 text-end">
                                                    <span class="project-status status-<?php echo $project['status']; ?>">
                                                        <?php 
                                                        $statusMap = [
                                                            'draft' => 'Draft',
                                                            'open' => 'Open', 
                                                            'in_progress' => 'In Progress',
                                                            'completed' => 'Selesai'
                                                        ];
                                                        echo $statusMap[$project['status']] ?? $project['status'];
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada proyek</p>
                                    <a href="create-project.php" class="btn btn-primary">Buat Proyek Pertama</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Proposal Terbaru -->
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="section-title">Proposal Terbaru</h4>
                            <a href="proposals.php" class="btn btn-outline-primary btn-sm">Lihat Semua</a>
                        </div>
                        <div class="recent-card">
                            <?php if (!empty($recent_proposals)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_proposals as $proposal): ?>
                                        <div class="list-group-item px-0">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($proposal['creative_name']); ?></h6>
                                                    <small class="text-muted">
                                                        Untuk: <?php echo htmlspecialchars($proposal['project_title']); ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted">
                                                        <?php echo date('d M Y H:i', strtotime($proposal['submitted_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-2 text-end">
                                                    <span class="badge bg-warning"><?php echo $proposal['status']; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada proposal</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animasi untuk stat cards
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>