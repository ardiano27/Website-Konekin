<?php
session_start();
require_once 'config/Database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Koneksi database
$database = new DatabaseConnection();
$db = $database->getConnection();

// Query untuk mendapatkan data proyek
$query = "
    SELECT 
        p.id,
        p.uuid,
        p.title,
        p.description,
        p.category,
        p.budget_range_min,
        p.budget_range_max,
        p.budget_type,
        p.deadline,
        p.status,
        p.created_at,
        p.updated_at,
        u.full_name as umkm_name,
        u.email as umkm_email,
        up.business_name,
        COUNT(DISTINCT pr.id) as proposal_count,
        COUNT(DISTINCT c.id) as contract_count
    FROM projects p
    LEFT JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    LEFT JOIN proposals pr ON p.id = pr.project_id
    LEFT JOIN contracts c ON p.id = c.project_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
";

$stmt = $db->prepare($query);
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fungsi untuk format tanggal
function formatDate($date) {
    if (!$date) return '-';
    return date('d M Y', strtotime($date));
}

// Fungsi untuk format budget
function formatBudget($min, $max, $type) {
    if (!$min || !$max) return '-';
    return 'Rp ' . number_format($min, 0, ',', '.') . ' - Rp ' . number_format($max, 0, ',', '.') . ' (' . $type . ')';
}

// Fungsi untuk status badge
function getStatusBadge($status) {
    $badges = [
        'draft' => 'secondary',
        'open' => 'primary',
        'in_progress' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    
    $statusText = [
        'draft' => 'Draft',
        'open' => 'Dibuka',
        'in_progress' => 'Berjalan',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    
    $color = $badges[$status] ?? 'secondary';
    $text = $statusText[$status] ?? ucfirst($status);
    
    return "<span class='badge bg-$color'>$text</span>";
}

// Fungsi untuk kategori badge
function getCategoryBadge($category) {
    $categories = [
        'website' => ['Website', 'primary'],
        'logo' => ['Logo', 'info'],
        'social_media' => ['Media Sosial', 'success'],
        'video' => ['Video', 'warning'],
        'content' => ['Konten', 'secondary'],
        'marketing' => ['Marketing', 'danger'],
        'other' => ['Lainnya', 'dark']
    ];
    
    $data = $categories[$category] ?? ['Lainnya', 'dark'];
    return "<span class='badge bg-{$data[1]}'>{$data[0]}</span>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Proyek - Konekin Admin</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #7209b7;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        /* Navbar Styles */
        .konekin-navbar {
            background: linear-gradient(135deg, #3E7FD5 0%, #3E7FD5 100%);
            padding: 0.8rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand-konekin {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.5rem;
            margin-right: 3rem;
        }

        .navbar-brand-konekin i {
            margin-right: 10px;
            font-size: 1.8rem;
        }

        .navbar-nav-konekin {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-item-konekin {
            position: relative;
        }

        .nav-link-konekin {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.7rem 1.2rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .nav-link-konekin:hover,
        .nav-link-konekin.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .nav-link-konekin i {
            font-size: 1.1rem;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: 2rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            color: white;
        }

        .user-name {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-type {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            margin-left: 1rem;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .navbar-toggler-custom {
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
        }

        /* Mobile Styles */
        @media (max-width: 991.98px) {
            .konekin-navbar {
                padding: 0.8rem 1rem;
            }
            .navbar-brand-konekin {
                margin-right: 1rem;
            }
            .navbar-collapse-konekin {
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 1rem;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                z-index: 999;
            }

            .navbar-nav-konekin {
                flex-direction: column;
                gap: 0.5rem;
            }

            .nav-link-konekin {
                padding: 1rem;
                border-radius: 10px;
                justify-content: flex-start;
            }

            .user-section {
                margin-left: 0;
                margin-top: 1rem;
                justify-content: center;
                flex-direction: column;
                text-align: center;
            }

            .logout-btn {
                margin-left: 0;
                margin-top: 0.5rem;
                width: 100%;
            }
        }

        /* Badge untuk notifikasi */
        .nav-badge {
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -5px;
            right: -5px;
        }
        
        /* Main Content */
        .main-content {
            padding: 2rem;
            margin-top: 20px;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--primary-color);
        }
        
        .page-title {
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #6c757d;
            font-size: 1rem;
        }
        
        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            border-top: 4px solid;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card.total {
            border-top-color: var(--primary-color);
        }
        
        .stats-card.open {
            border-top-color: #4cc9f0;
        }
        
        .stats-card.in-progress {
            border-top-color: #ff9e00;
        }
        
        .stats-card.completed {
            border-top-color: #4CAF50;
        }
        
        .stats-card.cancelled {
            border-top-color: #f44336;
        }
        
        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-icon.total {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }
        
        .stats-icon.open {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
        }
        
        .stats-icon.in-progress {
            background: rgba(255, 158, 0, 0.1);
            color: #ff9e00;
        }
        
        .stats-icon.completed {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .stats-icon.cancelled {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Projects Table */
        .projects-table-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-top: 1rem;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .table-title {
            color: var(--secondary-color);
            font-weight: 600;
            margin: 0;
        }
        
        .search-box {
            position: relative;
            width: 300px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .search-box i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .table th {
            border: none;
            padding: 1rem;
            font-weight: 600;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f0f0f0;
        }
        
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9ff;
        }
        
        .project-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .project-description {
            color: #6c757d;
            font-size: 0.85rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .project-umkm {
            font-weight: 500;
            color: #333;
        }
        
        .project-business {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .project-meta {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .meta-item i {
            color: var(--primary-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-view {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }
        
        .btn-view:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-edit {
            background: rgba(255, 158, 0, 0.1);
            color: #ff9e00;
        }
        
        .btn-edit:hover {
            background: #ff9e00;
            color: white;
        }
        
        .btn-delete {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .btn-delete:hover {
            background: #f44336;
            color: white;
        }
        
        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .pagination .page-link {
            border: none;
            color: var(--primary-color);
            border-radius: 6px;
            margin: 0 3px;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Modal */
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .detail-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        
        .detail-value {
            color: #333;
        }
        
        .detail-value.budget {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #e0e0e0;
            margin-bottom: 1rem;
        }
        
        .empty-state-title {
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .empty-state-description {
            color: #999;
            max-width: 500px;
            margin: 0 auto;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .navbar-nav-konekin {
                margin-top: 1rem;
            }
            
            .user-section {
                margin-top: 1rem;
                margin-left: 0;
            }
            
            .search-box {
                width: 100%;
                margin-bottom: 1rem;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 32px;
                height: 32px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include 'navbar-admin.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-project-diagram me-2"></i>
                Kelola Proyek
            </h1>
            <p class="page-subtitle">
                Kelola semua proyek UMKM yang ada di platform Konekin
            </p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <?php
            // Hitung statistik
            $total_projects = count($projects);
            $open_projects = 0;
            $in_progress_projects = 0;
            $completed_projects = 0;
            $cancelled_projects = 0;
            
            foreach ($projects as $project) {
                switch ($project['status']) {
                    case 'open':
                        $open_projects++;
                        break;
                    case 'in_progress':
                        $in_progress_projects++;
                        break;
                    case 'completed':
                        $completed_projects++;
                        break;
                    case 'cancelled':
                        $cancelled_projects++;
                        break;
                }
            }
            ?>
            <div class="col-md-6 col-lg-3">
                <div class="stats-card total">
                    <div class="stats-icon total">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <div class="stats-number"><?php echo $total_projects; ?></div>
                    <div class="stats-label">Total Proyek</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stats-card open">
                    <div class="stats-icon open">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="stats-number"><?php echo $open_projects; ?></div>
                    <div class="stats-label">Proyek Dibuka</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stats-card in-progress">
                    <div class="stats-icon in-progress">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stats-number"><?php echo $in_progress_projects; ?></div>
                    <div class="stats-label">Sedang Berjalan</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stats-card completed">
                    <div class="stats-icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo $completed_projects; ?></div>
                    <div class="stats-label">Selesai</div>
                </div>
            </div>
        </div>
        
        <!-- Projects Table -->
        <div class="projects-table-container">
            <div class="table-header">
                <h3 class="table-title">Daftar Proyek</h3>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Cari proyek...">
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover" id="projectsTable">
                    <thead>
                        <tr>
                            <th>Proyek</th>
                            <th>UMKM</th>
                            <th>Kategori</th>
                            <th>Budget</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($projects) > 0): ?>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td>
                                        <div class="project-title"><?php echo htmlspecialchars($project['title']); ?></div>
                                        <div class="project-description">
                                            <?php echo htmlspecialchars(substr($project['description'], 0, 100)) . (strlen($project['description']) > 100 ? '...' : ''); ?>
                                        </div>
                                        <div class="project-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-file-alt"></i>
                                                <?php echo $project['proposal_count']; ?> Proposal
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-file-contract"></i>
                                                <?php echo $project['contract_count']; ?> Kontrak
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="project-umkm"><?php echo htmlspecialchars($project['umkm_name']); ?></div>
                                        <div class="project-business"><?php echo htmlspecialchars($project['business_name'] ?? '-'); ?></div>
                                        <div class="project-email" style="font-size: 0.85rem; color: #6c757d;">
                                            <?php echo htmlspecialchars($project['umkm_email']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo getCategoryBadge($project['category']); ?></td>
                                    <td>
                                        <div class="detail-value budget">
                                            <?php echo formatBudget($project['budget_range_min'], $project['budget_range_max'], $project['budget_type']); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #6c757d;">
                                            Deadline: <?php echo formatDate($project['deadline']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo getStatusBadge($project['status']); ?></td>
                                    <td>
                                        <div>Dibuat: <?php echo formatDate($project['created_at']); ?></div>
                                        <div style="font-size: 0.85rem; color: #6c757d;">
                                            Update: <?php echo formatDate($project['updated_at']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#projectModal"
                                                    data-id="<?php echo $project['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($project['title']); ?>"
                                                    data-description="<?php echo htmlspecialchars($project['description']); ?>"
                                                    data-category="<?php echo $project['category']; ?>"
                                                    data-budget="<?php echo formatBudget($project['budget_range_min'], $project['budget_range_max'], $project['budget_type']); ?>"
                                                    data-deadline="<?php echo formatDate($project['deadline']); ?>"
                                                    data-status="<?php echo $project['status']; ?>"
                                                    data-umkm="<?php echo htmlspecialchars($project['umkm_name']); ?>"
                                                    data-business="<?php echo htmlspecialchars($project['business_name'] ?? '-'); ?>"
                                                    data-email="<?php echo htmlspecialchars($project['umkm_email']); ?>"
                                                    data-created="<?php echo formatDate($project['created_at']); ?>"
                                                    data-updated="<?php echo formatDate($project['updated_at']); ?>"
                                                    data-proposals="<?php echo $project['proposal_count']; ?>"
                                                    data-contracts="<?php echo $project['contract_count']; ?>"
                                                    onclick="viewProject(this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-edit" 
                                                    onclick="editProject(<?php echo $project['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action btn-delete" 
                                                    onclick="deleteProject(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['title']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-project-diagram"></i>
                                        </div>
                                        <h4 class="empty-state-title">Belum Ada Proyek</h4>
                                        <p class="empty-state-description">
                                            Saat ini belum ada proyek yang dibuat oleh UMKM. Proyek akan muncul di sini setelah UMKM membuat proyek baru.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if (count($projects) > 0): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Menampilkan <?php echo count($projects); ?> dari <?php echo $total_projects; ?> proyek
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Project Detail Modal -->
    <div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="projectModalLabel">Detail Proyek</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="detail-item">
                        <div class="detail-label">Judul Proyek</div>
                        <div class="detail-value" id="modalTitle"></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Deskripsi</div>
                        <div class="detail-value" id="modalDescription"></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item">
                                <div class="detail-label">Kategori</div>
                                <div class="detail-value" id="modalCategory"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item">
                                <div class="detail-label">Status</div>
                                <div class="detail-value" id="modalStatus"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item">
                                <div class="detail-label">Budget</div>
                                <div class="detail-value budget" id="modalBudget"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item">
                                <div class="detail-label">Deadline</div>
                                <div class="detail-value" id="modalDeadline"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Informasi UMKM</div>
                        <div class="detail-value">
                            <strong id="modalUmkm"></strong> - <span id="modalBusiness"></span>
                            <div style="font-size: 0.9rem; color: #6c757d; margin-top: 0.25rem;">
                                Email: <span id="modalEmail"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item">
                                <div class="detail-label">Dibuat Pada</div>
                                <div class="detail-value" id="modalCreated"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item">
                                <div class="detail-label">Diperbarui Pada</div>
                                <div class="detail-value" id="modalUpdated"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item">
                                <div class="detail-label">Jumlah Proposal</div>
                                <div class="detail-value" id="modalProposals"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item">
                                <div class="detail-label">Jumlah Kontrak</div>
                                <div class="detail-value" id="modalContracts"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">ID Proyek</div>
                        <div class="detail-value" style="font-family: monospace; background: #f5f5f5; padding: 0.5rem; border-radius: 4px;" id="modalProjectId"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" onclick="editProjectFromModal()">
                        <i class="fas fa-edit me-1"></i> Edit Proyek
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#projectsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // View project details
        function viewProject(button) {
            document.getElementById('modalTitle').textContent = button.getAttribute('data-title');
            document.getElementById('modalDescription').textContent = button.getAttribute('data-description');
            document.getElementById('modalCategory').innerHTML = getCategoryBadge(button.getAttribute('data-category'));
            document.getElementById('modalBudget').textContent = button.getAttribute('data-budget');
            document.getElementById('modalDeadline').textContent = button.getAttribute('data-deadline');
            document.getElementById('modalStatus').innerHTML = getStatusBadge(button.getAttribute('data-status'));
            document.getElementById('modalUmkm').textContent = button.getAttribute('data-umkm');
            document.getElementById('modalBusiness').textContent = button.getAttribute('data-business');
            document.getElementById('modalEmail').textContent = button.getAttribute('data-email');
            document.getElementById('modalCreated').textContent = button.getAttribute('data-created');
            document.getElementById('modalUpdated').textContent = button.getAttribute('data-updated');
            document.getElementById('modalProposals').textContent = button.getAttribute('data-proposals');
            document.getElementById('modalContracts').textContent = button.getAttribute('data-contracts');
            document.getElementById('modalProjectId').textContent = button.getAttribute('data-id');
        }
        
        // Helper function for category badge in modal
        function getCategoryBadge(category) {
            const categories = {
                'website': ['Website', 'primary'],
                'logo': ['Logo', 'info'],
                'social_media': ['Media Sosial', 'success'],
                'video': ['Video', 'warning'],
                'content': ['Konten', 'secondary'],
                'marketing': ['Marketing', 'danger'],
                'other': ['Lainnya', 'dark']
            };
            
            const data = categories[category] || ['Lainnya', 'dark'];
            return `<span class="badge bg-${data[1]}">${data[0]}</span>`;
        }
        
        // Helper function for status badge in modal
        function getStatusBadge(status) {
            const badges = {
                'draft': 'secondary',
                'open': 'primary',
                'in_progress': 'warning',
                'completed': 'success',
                'cancelled': 'danger'
            };
            
            const statusText = {
                'draft': 'Draft',
                'open': 'Dibuka',
                'in_progress': 'Berjalan',
                'completed': 'Selesai',
                'cancelled': 'Dibatalkan'
            };
            
            const color = badges[status] || 'secondary';
            const text = statusText[status] || status;
            
            return `<span class="badge bg-${color}">${text}</span>`;
        }
        
        // Edit project
        function editProject(projectId) {
            window.location.href = `edit-project.php?id=${projectId}`;
        }
        
        function editProjectFromModal() {
            const projectId = document.getElementById('modalProjectId').textContent;
            editProject(projectId);
        }
        
        // Delete project confirmation
        function deleteProject(projectId, projectTitle) {
            if (confirm(`Apakah Anda yakin ingin menghapus proyek "${projectTitle}"?`)) {
                // In a real implementation, this would be an AJAX call
                console.log(`Deleting project ${projectId}`);
                
                // Show success message
                alert(`Proyek "${projectTitle}" berhasil dihapus.`);
                
                // Reload the page to reflect changes
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>