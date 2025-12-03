<?php
// my-projects.php
include "check_login.php";

if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Filter status
$status_filter = $_GET['status'] ?? 'all';

// Query untuk mendapatkan proyek yang sedang dikerjakan (active contracts)
$sql = "
    SELECT 
        p.*,
        c.id as contract_id,
        c.status as contract_status,
        c.progress_percentage,
        c.payment_status,
        c.start_date,
        c.end_date,
        u.full_name as umkm_name,
        up.business_name,
        up.business_logo_url
    FROM projects p
    JOIN contracts c ON p.id = c.project_id
    JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    WHERE c.creative_user_id = :user_id
";

$params = [':user_id' => $_SESSION['user_id']];

if ($status_filter !== 'all') {
    $sql .= " AND c.status = :status";
    $params[':status'] = $status_filter;
}

$sql .= " ORDER BY c.updated_at DESC";

$projects = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

// Hitung statistik
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN c.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM contracts c
    WHERE c.creative_user_id = :user_id
";

$stats = [];
try {
    $stmt = $conn->prepare($stats_sql);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
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
        
        .project-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-light);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid var(--primary-color);
        }
        
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .progress-fill-custom {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            border-radius: 4px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow-light);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
        }
        
        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .umkm-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
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
                        <h1 class="mb-2"><i class="fas fa-briefcase me-2"></i>Proyek Saya</h1>
                        <p class="mb-0">Kelola semua proyek yang sedang dan telah Anda kerjakan</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="find-projects.php" class="btn btn-light">
                            <i class="fas fa-search me-1"></i>Cari Proyek Baru
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(84, 158, 254, 0.1); color: var(--primary-color);">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h3 class="mb-1"><?php echo $stats['total'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Total Proyek</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <h3 class="mb-1"><?php echo $stats['active'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Sedang Berjalan</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(108, 117, 125, 0.1); color: #6c757d;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="mb-1"><?php echo $stats['completed'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Selesai</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h3 class="mb-1"><?php echo $stats['cancelled'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Dibatalkan</p>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="detail-card mb-4">
                <h5 class="mb-3">Filter Status</h5>
                <div class="btn-group" role="group">
                    <a href="?status=all" 
                       class="btn btn-outline-primary <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                        Semua
                    </a>
                    <a href="?status=active" 
                       class="btn btn-outline-primary <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                        <i class="fas fa-play-circle me-1"></i>Berjalan
                    </a>
                    <a href="?status=completed" 
                       class="btn btn-outline-primary <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle me-1"></i>Selesai
                    </a>
                    <a href="?status=cancelled" 
                       class="btn btn-outline-primary <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle me-1"></i>Dibatalkan
                    </a>
                </div>
            </div>
            
            <!-- Projects List -->
            <?php if (count($projects) > 0): ?>
                <div class="row">
                    <?php foreach ($projects as $project): ?>
                        <div class="col-lg-6">
                            <div class="project-card">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-start mb-3">
                                            <?php if ($project['business_logo_url']): ?>
                                                <img src="<?php echo htmlspecialchars($project['business_logo_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($project['business_name']); ?>" 
                                                     class="umkm-avatar me-3">
                                            <?php else: ?>
                                                <div class="umkm-avatar bg-primary d-flex align-items-center justify-content-center me-3">
                                                    <i class="fas fa-store text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($project['title']); ?></h5>
                                                <p class="text-muted mb-0"><?php echo htmlspecialchars($project['business_name']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="badge bg-<?php 
                                                echo $project['contract_status'] === 'active' ? 'success' : 
                                                     ($project['contract_status'] === 'completed' ? 'info' : 'danger');
                                            ?>">
                                                <?php 
                                                $status_text = [
                                                    'active' => 'Sedang Berjalan',
                                                    'completed' => 'Selesai',
                                                    'cancelled' => 'Dibatalkan'
                                                ];
                                                echo $status_text[$project['contract_status']] ?? $project['contract_status'];
                                                ?>
                                            </span>
                                            <span class="badge bg-<?php 
                                                echo $project['payment_status'] === 'paid' ? 'success' : 
                                                     ($project['payment_status'] === 'partial' ? 'warning' : 'secondary');
                                            ?> ms-1">
                                                <?php echo strtoupper($project['payment_status'] ?? 'pending'); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($project['contract_status'] === 'active'): ?>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <small class="text-muted">Progress</small>
                                                    <small class="text-muted"><?php echo $project['progress_percentage']; ?>%</small>
                                                </div>
                                                <div class="progress-bar-custom">
                                                    <div class="progress-fill-custom" style="width: <?php echo $project['progress_percentage']; ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="text-md-end">
                                            <div class="mb-2">
                                                <small class="text-muted d-block">Budget</small>
                                                <strong>Rp <?php echo number_format($project['agreed_budget'], 0, ',', '.'); ?></strong>
                                            </div>
                                            <div class="mb-3">
                                                <small class="text-muted d-block">Mulai</small>
                                                <small><?php echo date('d M Y', strtotime($project['start_date'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="project-detail.php?id=<?php echo $project['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>Detail
                                            </a>
                                            
                                            <?php if ($project['contract_status'] === 'active'): ?>
                                                <a href="project-progress.php?id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-tasks me-1"></i>Update Progress
                                                </a>
                                                <a href="project-messages.php?id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-outline-secondary btn-sm">
                                                    <i class="fas fa-comments me-1"></i>Chat
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($project['contract_status'] === 'active' && $project['payment_status'] === 'paid'): ?>
                                                <a href="submit-work.php?contract_id=<?php echo $project['contract_id']; ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-paper-plane me-1"></i>Submit Hasil
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="detail-card text-center py-5">
                    <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Belum ada proyek</h4>
                    <p class="text-muted mb-4">Anda belum memiliki proyek yang sedang dikerjakan</p>
                    <a href="find-projects.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Cari Proyek
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>