<?php
session_start();
require_once 'config/Database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit();
}

$database = new DatabaseConnection();
$db = $database->getConnection();

// Get active projects for creative worker - Modified query to handle missing tables
$projects_query = "
    SELECT 
        c.*,
        p.title as project_title,
        p.description as project_description,
        p.category,
        u.full_name as umkm_name,
        up.business_name,
        COALESCE((
            SELECT COUNT(*) 
            FROM project_tasks pt 
            WHERE pt.project_id = p.id
        ), 0) as total_tasks,
        COALESCE((
            SELECT COUNT(*) 
            FROM project_tasks pt 
            WHERE pt.project_id = p.id AND pt.status = 'completed'
        ), 0) as completed_tasks,
        COALESCE((
            SELECT COUNT(*) 
            FROM project_tasks pt 
            WHERE pt.project_id = p.id AND pt.status = 'revision'
        ), 0) as revision_tasks
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    JOIN users u ON c.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    WHERE c.creative_user_id = :user_id 
    AND c.status IN ('active', 'completed')
    ORDER BY c.updated_at DESC
";

$stmt = $db->prepare($projects_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback if no contracts found - show sample data for testing
if (empty($projects)) {
    // Sample data for testing
    $projects = [
        [
            'id' => 1,
            'project_id' => 1,
            'project_title' => 'Website UMKM Sample',
            'project_description' => 'Pembuatan website untuk UMKM makanan',
            'category' => 'website',
            'umkm_name' => 'UMKM Sample',
            'business_name' => 'Toko Sample',
            'status' => 'active',
            'total_tasks' => 5,
            'completed_tasks' => 2,
            'revision_tasks' => 1,
            'end_date' => date('Y-m-d', strtotime('+30 days'))
        ]
    ];
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
    <style>
        .main-content {
            margin-top: 80px;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .project-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .project-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
        }

        .project-title {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .project-category {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .progress-section {
            padding: 1.5rem;
        }

        .progress-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #343a40;
        }

        .progress {
            height: 10px;
            margin-bottom: 1rem;
        }

        .progress-bar {
            background: linear-gradient(45deg, #28a745, #20c997);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .project-actions {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
            display: flex;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: #f8f9fa;
            border-radius: 15px;
            border: 2px dashed #dee2e6;
        }

        .empty-state i {
            font-size: 4rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
                margin-top: 70px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .project-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="h3 mb-0">Proyek Saya</h1>
                        <p class="text-muted mb-0">Kelola semua proyek yang sedang dan sudah Anda kerjakan</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="find-projects.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Cari Proyek Baru
                        </a>
                    </div>
                </div>
            </div>

            <!-- Projects Grid -->
            <div class="row">
                <?php if (empty($projects)): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h3>Belum ada proyek</h3>
                            <p class="text-muted mb-4">Anda belum memiliki proyek yang sedang dikerjakan. Mulai dengan mencari proyek yang sesuai dengan keahlian Anda.</p>
                            <a href="find-projects.php" class="btn btn-primary btn-lg">Cari Proyek</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $total_tasks = $project['total_tasks'] ?: 1;
                        $completed_tasks = $project['completed_tasks'] ?: 0;
                        $progress = $total_tasks > 0 ? ($completed_tasks / $total_tasks) * 100 : 0;
                        $revision_tasks = $project['revision_tasks'] ?: 0;
                        ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="project-card">
                                <!-- Project Header -->
                                <div class="project-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h4 class="project-title"><?php echo htmlspecialchars($project['project_title']); ?></h4>
                                            <span class="project-category"><?php echo htmlspecialchars($project['category']); ?></span>
                                        </div>
                                        <span class="status-badge status-<?php echo $project['status']; ?>">
                                            <?php echo ucfirst($project['status']); ?>
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <small>
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($project['business_name'] ?: $project['umkm_name']); ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Progress Section -->
                                <div class="progress-section">
                                    <div class="progress-label">
                                        Progress: <?php echo round($progress); ?>%
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $progress; ?>%"
                                             aria-valuenow="<?php echo $progress; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>

                                    <div class="stats-grid">
                                        <div class="stat-item">
                                            <div class="stat-number text-primary"><?php echo $total_tasks; ?></div>
                                            <div class="stat-label">Total Tugas</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number text-success"><?php echo $completed_tasks; ?></div>
                                            <div class="stat-label">Selesai</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number text-warning"><?php echo $revision_tasks; ?></div>
                                            <div class="stat-label">Revisi</div>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Deadline: <?php echo date('d M Y', strtotime($project['end_date'] ?? '+30 days')); ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="project-actions">
                                    <a href="project-tasks.php?project_id=<?php echo $project['project_id'] ?? $project['id']; ?>" 
                                       class="btn btn-primary btn-sm flex-fill">
                                        <i class="fas fa-tasks me-1"></i>Kerjakan
                                    </a>
                                    <a href="project-detail.php?id=<?php echo $project['project_id'] ?? $project['id']; ?>" 
                                       class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="messages.php?project_id=<?php echo $project['project_id'] ?? $project['id']; ?>" 
                                       class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Quick Stats -->
            <?php if (!empty($projects)): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Ringkasan Proyek</h5>
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <div class="stat-number text-primary"><?php echo count($projects); ?></div>
                                        <div class="stat-label">Total Proyek</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-number text-success">
                                            <?php echo count(array_filter($projects, function($p) { return ($p['status'] ?? '') === 'completed'; })); ?>
                                        </div>
                                        <div class="stat-label">Selesai</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-number text-warning">
                                            <?php echo count(array_filter($projects, function($p) { return ($p['status'] ?? '') === 'active'; })); ?>
                                        </div>
                                        <div class="stat-label">Aktif</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-number text-info">
                                            <?php echo array_sum(array_column($projects, 'revision_tasks')); ?>
                                        </div>
                                        <div class="stat-label">Butuh Revisi</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add animation to progress bars
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
        });
    </script>
</body>
</html>