<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'umkm') {
    header("Location: login.php");
    exit();
}

require_once 'config/Database.php';

try {
    $database = new DatabaseConnection();
    $db = $database->getConnection();

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
        SELECT prop.*, p.title as project_title, u.full_name as creative_name,
               cp.tagline as creative_tagline, cp.rating as creative_rating,
               u.avatar_url as creative_avatar
        FROM proposals prop
        JOIN projects p ON prop.project_id = p.id
        JOIN users u ON prop.creative_user_id = u.id
        LEFT JOIN creative_profiles cp ON u.id = cp.user_id
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #3E7FD5;
            --primary-light: rgba(62, 127, 213, 0.1);
            --success-color: #28a745;
            --success-light: rgba(40, 167, 69, 0.1);
            --warning-color: #ffc107;
            --warning-light: rgba(255, 193, 7, 0.1);
            --info-color: #17a2b8;
            --info-light: rgba(23, 162, 184, 0.1);
            --purple-color: #6f42c1;
            --purple-light: rgba(111, 66, 193, 0.1);
            --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #3E7FD5 0%, #5a9cff 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        /* Section Title Styling */
        .section-title-container {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .section-title-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--info-color));
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-size: 1.5rem;
            position: relative;
            padding-left: 1rem;
        }

        .section-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        /* Enhanced Stat Cards */
        .stat-card-enhanced {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: var(--shadow-soft);
            border: 2px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .stat-card-enhanced::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--info-color));
            border-radius: 4px 4px 0 0;
        }

        .stat-card-enhanced:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary-light);
        }

        .stat-icon-enhanced {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1.25rem;
            position: relative;
            transition: all 0.3s ease;
        }

        .stat-card-enhanced:hover .stat-icon-enhanced {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-number-enhanced {
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #2c3e50 0%, #4a6fa5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-title-enhanced {
            color: #495057;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .stat-trend {
            font-size: 0.85rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            display: inline-block;
            margin-top: 0.5rem;
        }

        /* Recent Activity Cards */
        .recent-card-enhanced {
            background: white;
            border-radius: 18px;
            padding: 1.5rem 0;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(0,0,0,0.05);
            height: 100%;
            transition: all 0.3s ease;
        }

        .recent-card-enhanced:hover {
            box-shadow: var(--shadow-hover);
        }

        .activity-list-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .activity-list-item:hover {
            background: linear-gradient(to right, rgba(62, 127, 213, 0.03), rgba(62, 127, 213, 0.08));
            transform: translateX(5px);
        }

        .activity-list-item:last-child {
            border-bottom: none;
        }

        .project-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--primary-color);
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .project-info {
            flex: 1;
        }

        .project-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .project-meta {
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .project-meta i {
            margin-right: 0.25rem;
        }

        .proposal-count-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            min-width: 70px;
            text-align: center;
        }

        .status-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 100px;
            text-align: center;
            border: 2px solid transparent;
        }

        .status-open { 
            background: rgba(40, 167, 69, 0.1); 
            color: var(--success-color);
            border-color: rgba(40, 167, 69, 0.2);
        }
        .status-in_progress { 
            background: rgba(255, 193, 7, 0.1); 
            color: var(--warning-color);
            border-color: rgba(255, 193, 7, 0.2);
        }
        .status-draft { 
            background: rgba(108, 117, 125, 0.1); 
            color: #6c757d;
            border-color: rgba(108, 117, 125, 0.2);
        }
        .status-completed { 
            background: rgba(23, 162, 184, 0.1); 
            color: var(--info-color);
            border-color: rgba(23, 162, 184, 0.2);
        }

        /* Proposal Cards */
        .proposal-item-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-soft);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .proposal-item-card:hover {
            transform: translateX(8px);
            box-shadow: var(--shadow-hover);
        }

        .proposal-item-card.new {
            border-left-color: var(--success-color);
            background: linear-gradient(to right, rgba(40, 167, 69, 0.03), white);
        }

        .creative-avatar {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            overflow: hidden;
            margin-right: 1rem;
            flex-shrink: 0;
            border: 3px solid white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .creative-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .creative-avatar.fallback {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .creative-info {
            flex: 1;
        }

        .creative-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        .creative-tagline {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        

        .rating-stars {
            color: #FFC300;
            font-size: 0.9rem;
        }

        .proposal-meta {
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .proposal-meta i {
            margin-right: 0.25rem;
        }

        .proposal-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            background: var(--warning-light);
            color: var(--warning-color);
        }

        .proposal-status.accepted {
            background: var(--success-light);
            color: var(--success-color);
        }

        .proposal-status.rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        /* Quick Actions */
        .quick-action-card-enhanced {
            background: white;
            border-radius: 18px;
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            border: 2px solid transparent;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .quick-action-card-enhanced:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary-light);
        }

        .quick-action-card-enhanced::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--info-color));
            border-radius: 4px 4px 0 0;
        }

        .action-icon-enhanced {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.25rem;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            transition: all 0.3s ease;
        }

        .quick-action-card-enhanced:hover .action-icon-enhanced {
            transform: scale(1.1) rotate(10deg);
        }

        .action-title {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .action-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
        }

        .empty-state h4 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease forwards;
        }

        .animation-delay-1 { animation-delay: 0.1s; }
        .animation-delay-2 { animation-delay: 0.2s; }
        .animation-delay-3 { animation-delay: 0.3s; }
        .animation-delay-4 { animation-delay: 0.4s; }
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
                    <h1 class="display-5 fw-bold mb-3">Halo, <?php echo $_SESSION['full_name'] ?? 'UMKM'; ?>! 👋</h1>
                    <p class="lead mb-0" style="font-weight: 400;">Selamat datang di dashboard UMKM Anda. Kelola proyek dan temukan kreator terbaik untuk bisnis Anda.</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white rounded-pill px-4 py-2 d-inline-flex align-items-center shadow-sm">
                        <div class="me-2">
                            <small class="text-muted d-block" style="font-size: 0.75rem;">Status Akun</small>
                            <span class="fw-bold text-success">Aktif</span>
                        </div>
                        <div class="vr mx-2" style="height: 30px;"></div>
                        <div>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">Bergabung Sejak</small>
                            <span class="fw-bold"><?php echo date('M Y', strtotime($_SESSION['created_at'] ?? 'now')); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Ringkasan Aktivitas Section -->
        <div class="section-title-container animate-fade-in-up">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="section-title">📊 Ringkasan Aktivitas</h2>
                    <p class="section-subtitle">Statistik lengkap aktivitas proyek dan proposal Anda</p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge rounded-pill bg-primary px-3 py-2">
                        <i class="fas fa-sync-alt me-1"></i> Real-time Update
                    </span>
                </div>
            </div>
        </div>

        <!-- Statistik Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-3 animate-fade-in-up animation-delay-1">
                <div class="stat-card-enhanced">
                    <div class="stat-icon-enhanced bg-primary-light">
                        <i class="fas fa-project-diagram text-primary"></i>
                    </div>
                    <div class="stat-number-enhanced"><?php echo $stats['total_projects'] ?? 0; ?></div>
                    <div class="stat-title-enhanced">Total Proyek</div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line me-1"></i> +<?php echo ($stats['open_projects'] ?? 0) + ($stats['active_projects'] ?? 0); ?> Aktif
                    </div>
                </div>
            </div>
            <div class="col-md-3 animate-fade-in-up animation-delay-2">
                <div class="stat-card-enhanced">
                    <div class="stat-icon-enhanced bg-success-light">
                        <i class="fas fa-hourglass-half text-success"></i>
                    </div>
                    <div class="stat-number-enhanced"><?php echo $stats['active_projects'] ?? 0; ?></div>
                    <div class="stat-title-enhanced">Proyek Aktif</div>
                    <div class="stat-trend">
                        <i class="fas fa-clock me-1"></i> <?php echo $stats['open_projects'] ?? 0; ?> Menunggu
                    </div>
                </div>
            </div>
            <div class="col-md-3 animate-fade-in-up animation-delay-3">
                <div class="stat-card-enhanced">
                    <div class="stat-icon-enhanced bg-warning-light">
                        <i class="fas fa-file-alt text-warning"></i>
                    </div>
                    <div class="stat-number-enhanced"><?php echo $stats['total_proposals'] ?? 0; ?></div>
                    <div class="stat-title-enhanced">Total Proposal</div>
                    <div class="stat-trend">
                        <i class="fas fa-bell me-1"></i> <?php echo count($recent_proposals); ?> Baru
                    </div>
                </div>
            </div>
            <div class="col-md-3 animate-fade-in-up animation-delay-4">
                <div class="stat-card-enhanced">
                    <div class="stat-icon-enhanced bg-info-light">
                        <i class="fas fa-handshake text-info"></i>
                    </div>
                    <div class="stat-number-enhanced"><?php echo $stats['total_contracts'] ?? 0; ?></div>
                    <div class="stat-title-enhanced">Kontrak Aktif</div>
                    <div class="stat-trend">
                        <i class="fas fa-check-circle me-1"></i> <?php echo $stats['completed_projects'] ?? 0; ?> Selesai
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="section-title-container mb-4">
                    <h3 class="section-title">🚀 Aksi Cepat</h3>
                    <p class="section-subtitle">Akses cepat ke fitur utama platform</p>
                </div>
                
                <div class="row g-3">
                    <div class="col-12">
                        <a href="create-project.php" class="text-decoration-none">
                            <div class="quick-action-card-enhanced">
                                <div class="action-icon-enhanced">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <h5 class="action-title">Buat Proyek Baru</h5>
                                <p class="action-description">Posting proyek baru untuk dicreative worker</p>
                                <span class="btn btn-outline-primary btn-sm rounded-pill px-3">Mulai Sekarang</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-12">
                        <a href="projects.php" class="text-decoration-none">
                            <div class="quick-action-card-enhanced">
                                <div class="action-icon-enhanced" style="background: linear-gradient(135deg, var(--success-color), #20c997);">
                                    <i class="fas fa-list"></i>
                                </div>
                                <h5 class="action-title">Kelola Proyek</h5>
                                <p class="action-description">Lihat dan kelola semua proyek Anda</p>
                                <span class="btn btn-outline-success btn-sm rounded-pill px-3">Lihat Proyek</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-12">
                        <a href="find-creatives.php" class="text-decoration-none">
                            <div class="quick-action-card-enhanced">
                                <div class="action-icon-enhanced" style="background: linear-gradient(135deg, var(--warning-color), #fd7e14);">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h5 class="action-title">Cari Kreator</h5>
                                <p class="action-description">Temukan creative worker terbaik</p>
                                <span class="btn btn-outline-warning btn-sm rounded-pill px-3">Jelajahi</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Proyek Terbaru dan Proposal -->
            <div class="col-lg-8">
                <div class="row">
                    <!-- Proyek Terbaru -->
                    <div class="col-12 mb-4">
                        <div class="section-title-container">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="section-title">📁 Proyek Terbaru</h3>
                                    <p class="section-subtitle">Proyek-proyek yang sedang Anda kelola</p>
                                </div>
                                <a href="projects.php" class="btn btn-primary btn-sm rounded-pill px-3">
                                    <i class="fas fa-arrow-right me-1"></i> Lihat Semua
                                </a>
                            </div>
                        </div>
                        
                        <div class="recent-card-enhanced">
                            <?php if (!empty($recent_projects)): ?>
                                <div class="activity-list">
                                    <?php foreach ($recent_projects as $project): ?>
                                        <div class="activity-list-item" onclick="window.location.href='view-project.php?id=<?php echo $project['id']; ?>'">
                                            <div class="project-icon">
                                                <?php 
                                                $categoryIcon = match($project['category']) {
                                                    'website' => 'fas fa-globe',
                                                    'logo' => 'fas fa-pen-nib',
                                                    'social_media' => 'fas fa-hashtag',
                                                    'video' => 'fas fa-video',
                                                    'content' => 'fas fa-file-alt',
                                                    'marketing' => 'fas fa-bullhorn',
                                                    default => 'fas fa-project-diagram'
                                                };
                                                ?>
                                                <i class="<?php echo $categoryIcon; ?>"></i>
                                            </div>
                                            <div class="project-info">
                                                <div class="project-title"><?php echo htmlspecialchars($project['title']); ?></div>
                                                <div class="project-meta">
                                                    <span><i class="far fa-calendar"></i> <?php echo date('d M Y', strtotime($project['created_at'])); ?></span>
                                                    <span><i class="far fa-clock"></i> Deadline: <?php echo date('d M', strtotime($project['deadline'])); ?></span>
                                                    <?php if ($project['budget_range_min']): ?>
                                                        <span><i class="fas fa-coins"></i> Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="proposal-count-badge">
                                                    <i class="fas fa-file-alt me-1"></i> <?php echo $project['proposal_count']; ?>
                                                </span>
                                                <span class="status-badge status-<?php echo $project['status']; ?>">
                                                    <?php 
                                                    $statusMap = [
                                                        'draft' => 'Draft',
                                                        'open' => 'Open', 
                                                        'in_progress' => 'Progress',
                                                        'completed' => 'Selesai'
                                                    ];
                                                    echo $statusMap[$project['status']] ?? $project['status'];
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <h4>Belum Ada Proyek</h4>
                                    <p class="mb-4">Mulai dengan membuat proyek pertama Anda</p>
                                    <a href="create-project.php" class="btn btn-primary rounded-pill px-4">
                                        <i class="fas fa-plus me-2"></i> Buat Proyek Pertama
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Proposal Terbaru -->
                    <div class="col-12">
                        <div class="section-title-container">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="section-title">📨 Proposal Terbaru</h3>
                                    <p class="section-subtitle">Penawaran terbaru dari kreator</p>
                                </div>
                                <a href="project-proposals.php" class="btn btn-primary btn-sm rounded-pill px-3">
                                    <i class="fas fa-arrow-right me-1"></i> Lihat Semua
                                </a>
                            </div>
                        </div>
                        
                        <div class="recent-card-enhanced">
                            <?php if (!empty($recent_proposals)): ?>
                                <div class="proposal-list px-3">
                                    <?php foreach ($recent_proposals as $proposal): ?>
                                        <div class="proposal-item-card <?php echo $proposal['status'] === 'submitted' ? 'new' : ''; ?>" 
                                             onclick="window.location.href='project-proposals.php?id=<?php echo $proposal['id']; ?>'">
                                            <div class="d-flex align-items-start">
                                                <!-- Avatar Kreator -->
                                                <div class="creative-avatar <?php echo empty($proposal['creative_avatar']) ? 'fallback' : ''; ?>">
                                                    <?php if (!empty($proposal['creative_avatar'])): ?>
                                                        <img src="<?php echo htmlspecialchars($proposal['creative_avatar']); ?>" alt="<?php echo htmlspecialchars($proposal['creative_name']); ?>">
                                                    <?php else: ?>
                                                        <?php echo strtoupper(substr($proposal['creative_name'], 0, 1)); ?>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Info Kreator & Proposal -->
                                                <div class="creative-info flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div>
                                                            <div class="creative-name"><?php echo htmlspecialchars($proposal['creative_name']); ?></div>
                                                            <?php if (!empty($proposal['creative_tagline'])): ?>
                                                                <div class="creative-tagline"><?php echo htmlspecialchars($proposal['creative_tagline']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="proposal-status <?php echo $proposal['status']; ?>">
                                                            <?php 
                                                            $statusLabel = match($proposal['status']) {
                                                                'submitted' => 'Baru',
                                                                'viewed' => 'Dilihat',
                                                                'accepted' => 'Diterima',
                                                                'rejected' => 'Ditolak',
                                                                default => ucfirst($proposal['status'])
                                                            };
                                                            echo $statusLabel;
                                                            ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="proposal-meta mb-2">
                                                        <span>
                                                            <i class="fas fa-briefcase me-1"></i> 
                                                            <?php echo htmlspecialchars($proposal['project_title']); ?>
                                                        </span>
                                                        <?php if ($proposal['proposed_budget']): ?>
                                                            <span>
                                                                <i class="fas fa-coins me-1"></i> 
                                                                Rp <?php echo number_format($proposal['proposed_budget'], 0, ',', '.'); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <?php if ($proposal['creative_rating'] > 0): ?>
                                                                <div class="rating-stars">
                                                                    <?php 
                                                                    $rating = round($proposal['creative_rating']);
                                                                    for ($i = 1; $i <= 5; $i++): 
                                                                    ?>
                                                                        <i class="fas fa-star<?php echo $i <= $rating ? '' : '-o'; ?>"></i>
                                                                    <?php endfor; ?>
                                                                    <small class="text-muted ms-1">(<?php echo $proposal['creative_rating']; ?>)</small>
                                                                </div>
                                                            <?php else: ?>
                                                                <small class="text-muted"><i class="fas fa-star me-1"></i> Belum ada rating</small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <i class="far fa-clock me-1"></i> 
                                                            <?php echo date('d M H:i', strtotime($proposal['submitted_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h4>Belum Ada Proposal</h4>
                                    <p class="mb-4">Proyek Anda belum menerima proposal dari kreator</p>
                                    <a href="find-creatives.php" class="btn btn-outline-primary rounded-pill px-4">
                                        <i class="fas fa-search me-2"></i> Cari Kreator
                                    </a>
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
        // Animasi untuk kartu statistik
        document.addEventListener('DOMContentLoaded', function() {
            // Animasi hover untuk semua kartu
            const cards = document.querySelectorAll('.stat-card-enhanced, .quick-action-card-enhanced, .recent-card-enhanced');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-8px)';
                });
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0)';
                });
            });

            // Animasi untuk item daftar
            const listItems = document.querySelectorAll('.activity-list-item, .proposal-item-card');
            listItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
                item.classList.add('animate-fade-in-up');
            });

            // Notifikasi interaktif untuk proposal baru
            const newProposals = document.querySelectorAll('.proposal-item-card.new');
            if (newProposals.length > 0) {
                newProposals.forEach(proposal => {
                    proposal.addEventListener('click', () => {
                        proposal.classList.remove('new');
                    });
                });
                
                // Tampilkan notifikasi jumlah proposal baru
                const badge = document.querySelector('.stat-trend .fa-bell').parentElement;
                if (badge) {
                    badge.innerHTML = `<i class="fas fa-bell me-1"></i> ${newProposals.length} Baru`;
                }
            }

            // Tooltip untuk rating bintang
            const ratingElements = document.querySelectorAll('.rating-stars');
            ratingElements.forEach(rating => {
                rating.addEventListener('mouseenter', function() {
                    const ratingValue = this.querySelector('small')?.textContent.match(/\(([^)]+)\)/);
                    if (ratingValue) {
                        this.setAttribute('title', `Rating: ${ratingValue[1]}/5`);
                    }
                });
            });
        });

        // Fungsi untuk refresh data ringkasan
        function refreshStats() {
            const statNumbers = document.querySelectorAll('.stat-number-enhanced');
            statNumbers.forEach(stat => {
                const currentValue = parseInt(stat.textContent);
                const newValue = currentValue + Math.floor(Math.random() * 3);
                
                // Animasi perubahan angka
                let start = currentValue;
                const end = newValue;
                const duration = 1000;
                const increment = (end - start) / (duration / 50);
                
                const timer = setInterval(() => {
                    start += increment;
                    if ((increment > 0 && start >= end) || (increment < 0 && start <= end)) {
                        stat.textContent = end;
                        clearInterval(timer);
                    } else {
                        stat.textContent = Math.floor(start);
                    }
                }, 50);
            });
        }

        // Auto-refresh setiap 30 detik (opsional)
        // setInterval(refreshStats, 30000);
    </script>
</body>
</html>