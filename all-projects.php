<?php
session_start();

// Redirect jika belum login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'creative') {
    header("Location: login.php");
    exit();
}

// Koneksi database
require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];

try {
    // Query untuk mendapatkan semua proyek aktif creative worker
    $query_active_projects = "
        SELECT c.*, p.title, p.description, p.category, p.budget_range_max as budget,
               u.business_name, cp.creative_bank_account,
               (SELECT COUNT(*) FROM contract_progress WHERE contract_id = c.id) as progress_updates
        FROM contracts c 
        JOIN projects p ON c.project_id = p.id 
        JOIN umkm_profiles u ON c.umkm_user_id = u.user_id
        LEFT JOIN creative_profiles cp ON c.creative_user_id = cp.user_id
        WHERE c.creative_user_id = :user_id AND c.status = 'active'
        ORDER BY c.created_at DESC
    ";
    $stmt_active_projects = $conn->prepare($query_active_projects);
    $stmt_active_projects->bindParam(':user_id', $user_id);
    $stmt_active_projects->execute();
    $active_projects = $stmt_active_projects->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk proyek selesai
    $query_completed_projects = "
        SELECT c.*, p.title, p.description, p.category, p.budget_range_max as budget,
               u.business_name, cp.creative_bank_account
        FROM contracts c 
        JOIN projects p ON c.project_id = p.id 
        JOIN umkm_profiles u ON c.umkm_user_id = u.user_id
        LEFT JOIN creative_profiles cp ON c.creative_user_id = cp.user_id
        WHERE c.creative_user_id = :user_id AND c.status = 'completed'
        ORDER BY c.updated_at DESC
    ";
    $stmt_completed_projects = $conn->prepare($query_completed_projects);
    $stmt_completed_projects->bindParam(':user_id', $user_id);
    $stmt_completed_projects->execute();
    $completed_projects = $stmt_completed_projects->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $active_projects = [];
    $completed_projects = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konekin - Semua Proyek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .projects-container {
            padding: 2rem;
            background-color: #f8f9fa;
            min-height: calc(100vh - 70px);
        }

        .section-title {
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #343a40;
            font-size: 1.5rem;
            border-bottom: 2px solid #3E7FD5;
            padding-bottom: 0.5rem;
        }

        .project-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #3E7FD5;
        }

        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .project-card.completed {
            border-left-color: #28a745;
            opacity: 0.9;
        }

        .project-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #343a40;
            font-size: 1.2rem;
        }

        .project-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .project-budget {
            font-weight: 700;
            color: #3E7FD5;
            font-size: 1.1rem;
        }

        .project-category {
            background: rgba(62, 127, 213, 0.1);
            color: #3E7FD5;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .project-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-completed {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .progress-section {
            background: rgba(62, 127, 213, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .progress-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #343a40;
        }

        .progress-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3E7FD5, #549efe);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            margin-right: 0.5rem;
        }

        .nav-tabs .nav-link.active {
            color: #3E7FD5;
            background: none;
            border-bottom: 3px solid #3E7FD5;
        }

        .project-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: #343a40;
        }

        @media (max-width: 768px) {
            .projects-container {
                padding: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                text-align: center;
            }
            
            .project-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include 'dashboard-sidebar.php'; ?>

    <!-- Projects Content -->
    <div class="projects-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="section-title">Kelola Proyek</h1>
            <a href="find-projects.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Cari Proyek Baru
            </a>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs" id="projectsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                    Proyek Aktif (<?php echo count($active_projects); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                    Proyek Selesai (<?php echo count($completed_projects); ?>)
                </button>
            </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content" id="projectsTabContent">
            <!-- Active Projects Tab -->
            <div class="tab-pane fade show active" id="active" role="tabpanel">
                <?php if (count($active_projects) > 0): ?>
                    <?php foreach ($active_projects as $project): ?>
                        <div class="project-card">
                            <div class="row">
                                <div class="col-md-8">
                                    <h3 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                                    
                                    <div class="project-info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Klien</span>
                                            <span class="info-value"><?php echo htmlspecialchars($project['business_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Budget</span>
                                            <span class="info-value project-budget">Rp <?php echo number_format($project['agreed_budget'] ?? $project['budget'], 0, ',', '.'); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Kategori</span>
                                            <span class="info-value project-category"><?php echo ucfirst(str_replace('_', ' ', $project['category'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Status</span>
                                            <span class="project-status status-active">Aktif</span>
                                        </div>
                                    </div>

                                    <?php if ($project['description']): ?>
                                        <p class="mb-3"><?php echo htmlspecialchars($project['description']); ?></p>
                                    <?php endif; ?>

                                    <!-- Progress Section -->
                                    <div class="progress-section">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="progress-label">Progress Proyek</span>
                                            <span class="text-muted small"><?php echo $project['progress_percentage']; ?>%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $project['progress_percentage']; ?>%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2">
                                            <small class="text-muted">Mulai: <?php echo date('d M Y', strtotime($project['start_date'])); ?></small>
                                            <small class="text-muted">Deadline: <?php echo date('d M Y', strtotime($project['end_date'] ?? '+30 days')); ?></small>
                                        </div>
                                    </div>

                                    <div class="action-buttons">
                                        <a href="project-detail.php?id=<?php echo $project['project_id']; ?>" class="btn btn-primary btn-action">
                                            <i class="fas fa-eye me-1"></i>Detail Proyek
                                        </a>
                                        <?php if ($project['payment_status'] === 'paid'): ?>
                                            <a href="project-progress1.php?id=<?php echo $project['project_id']; ?>" class="btn btn-outline-primary btn-action">
                                                <i class="fas fa-tasks me-1"></i>Update Progress
                                            </a>
                                        <?php endif; ?>
                                        <a href="messages.php?project_id=<?php echo $project['project_id']; ?>" class="btn btn-outline-secondary btn-action">
                                            <i class="fas fa-comment me-1"></i>Chat Klien
                                        </a>
                                        <?php if ($project['payment_status'] === 'pending'): ?>
                                            <span class="btn btn-warning btn-action">
                                                <i class="fas fa-clock me-1"></i>Menunggu Pembayaran
                                            </span>
                                        <?php elseif ($project['payment_status'] === 'paid'): ?>
                                            <span class="btn btn-success btn-action">
                                                <i class="fas fa-check me-1"></i>Dibayar
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Info Kontrak</h6>
                                            <div class="small">
                                                <div class="mb-2">
                                                    <strong>Kode Proyek:</strong><br>
                                                    <?php echo $project['project_code'] ?? 'N/A'; ?>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Rekening Bank:</strong><br>
                                                    <?php echo $project['creative_bank_account'] ?? 'Belum diatur'; ?>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Update Progress:</strong><br>
                                                    <?php echo $project['progress_updates'] ?? 0; ?> kali
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h4>Belum ada proyek aktif</h4>
                        <p class="mb-4">Mulai kirim proposal untuk mendapatkan proyek pertama Anda!</p>
                        <a href="find-projects.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i>Cari Proyek
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Completed Projects Tab -->
            <div class="tab-pane fade" id="completed" role="tabpanel">
                <?php if (count($completed_projects) > 0): ?>
                    <?php foreach ($completed_projects as $project): ?>
                        <div class="project-card completed">
                            <div class="row">
                                <div class="col-md-8">
                                    <h3 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                                    
                                    <div class="project-info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Klien</span>
                                            <span class="info-value"><?php echo htmlspecialchars($project['business_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Budget</span>
                                            <span class="info-value project-budget">Rp <?php echo number_format($project['agreed_budget'] ?? $project['budget'], 0, ',', '.'); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Kategori</span>
                                            <span class="info-value project-category"><?php echo ucfirst(str_replace('_', ' ', $project['category'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Status</span>
                                            <span class="project-status status-completed">Selesai</span>
                                        </div>
                                    </div>

                                    <?php if ($project['description']): ?>
                                        <p class="mb-3"><?php echo htmlspecialchars($project['description']); ?></p>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between text-muted small mb-3">
                                        <span>Mulai: <?php echo date('d M Y', strtotime($project['start_date'])); ?></span>
                                        <span>Selesai: <?php echo date('d M Y', strtotime($project['updated_at'])); ?></span>
                                    </div>

                                    <div class="action-buttons">
                                        <a href="project-detail.php?id=<?php echo $project['project_id']; ?>" class="btn btn-outline-primary btn-action">
                                            <i class="fas fa-eye me-1"></i>Lihat Detail
                                        </a>
                                        <a href="review.php?contract_id=<?php echo $project['id']; ?>" class="btn btn-outline-success btn-action">
                                            <i class="fas fa-star me-1"></i>Beri Review
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Ringkasan</h6>
                                            <div class="small">
                                                <div class="mb-2">
                                                    <strong>Status Pembayaran:</strong><br>
                                                    <span class="badge bg-<?php echo $project['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                        <?php echo strtoupper($project['payment_status']); ?>
                                                    </span>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Durasi:</strong><br>
                                                    <?php 
                                                        $start = new DateTime($project['start_date']);
                                                        $end = new DateTime($project['updated_at']);
                                                        $interval = $start->diff($end);
                                                        echo $interval->format('%a hari');
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h4>Belum ada proyek selesai</h4>
                        <p>Proyek yang telah Anda selesaikan akan muncul di sini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const triggerTabList = [].slice.call(document.querySelectorAll('#projectsTab button'));
            triggerTabList.forEach(function (triggerEl) {
                const tabTrigger = new bootstrap.Tab(triggerEl);
                triggerEl.addEventListener('click', function (event) {
                    event.preventDefault();
                    tabTrigger.show();
                });
            });
        });
    </script>
</body>
</html> 