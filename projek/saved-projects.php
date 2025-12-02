<?php
include "check_login.php";

// Validasi akses hanya untuk Creative
if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Handle aksi Unsave (Hapus dari simpanan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsave_project'])) {
    $project_id = $_POST['project_id'] ?? 0;
    
    if ($project_id > 0) {
        try {
            $unsave_sql = "DELETE FROM saved_projects WHERE creative_user_id = :user_id AND project_id = :project_id";
            $unsave_stmt = $conn->prepare($unsave_sql);
            $unsave_stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':project_id' => $project_id
            ]);
            
            // Set session flash message untuk notifikasi JS (opsional)
            $_SESSION['flash_message'] = "Proyek berhasil dihapus dari daftar simpanan.";
            $_SESSION['flash_type'] = "success";
            
            header("Location: saved-projects.php");
            exit;
        } catch (PDOException $e) {
            error_log("Error unsaving project: " . $e->getMessage());
        }
    }
}

// Ambil data proyek yang disimpan
$saved_projects_sql = "
    SELECT 
        p.*,
        u.avatar_url as umkm_avatar,
        u.full_name as umkm_name,
        up.business_name,
        up.business_logo_url,
        (SELECT COUNT(*) FROM proposals pr WHERE pr.project_id = p.id) as total_proposals,
        (SELECT COUNT(*) FROM proposals pr2 WHERE pr2.project_id = p.id AND pr2.creative_user_id = :user_id) as already_applied
    FROM saved_projects sp
    JOIN projects p ON sp.project_id = p.id
    JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    WHERE sp.creative_user_id = :user_id
    ORDER BY sp.saved_at DESC
";

try {
    $stmt = $conn->prepare($saved_projects_sql);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $saved_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching saved projects: " . $e->getMessage());
    $saved_projects = [];
}

// Hitung Statistik Sederhana
$stats = [
    'total' => count($saved_projects),
    'applied' => 0,
    'expiring_soon' => 0,
    'open' => 0
];

foreach ($saved_projects as $proj) {
    if ($proj['already_applied'] > 0) $stats['applied']++;
    if ($proj['status'] === 'open') $stats['open']++;
    
    if (!empty($proj['deadline'])) {
        $days_left = ceil((strtotime($proj['deadline']) - time()) / (60 * 60 * 24));
        if ($days_left <= 7 && $days_left >= 0) $stats['expiring_soon']++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyek Disimpan - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        /* Menggunakan Style Variables yang sama dengan projects.php untuk konsistensi */
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2c5ea3;
            --primary-light: rgba(62, 127, 213, 0.1);
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --success-light: rgba(40, 167, 69, 0.1);
            --warning-color: #ffc107;
            --warning-light: rgba(255, 193, 7, 0.1);
            --danger-color: #dc3545;
            --danger-light: rgba(220, 53, 69, 0.1);
            --info-color: #17a2b8;
            --info-light: rgba(23, 162, 184, 0.1);
            --bg-light: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.12);
            --border-radius: 16px;
            --border-radius-sm: 12px;
            --border-radius-lg: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        .main-content {
            padding: 20px 0;
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Hero Header Consistent with Projects.php */
        .page-hero {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius-lg);
            padding: 2.5rem 2rem;
            margin-bottom: 2.5rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            animation: slideInDown 0.6s ease;
        }

        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .hero-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 2px solid transparent;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease backwards;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 4px;
            background: var(--primary-color);
            border-radius: 4px 4px 0 0;
        }
        
        .stat-card.total::before { background: var(--primary-color); }
        .stat-card.applied::before { background: var(--success-color); }
        .stat-card.deadline::before { background: var(--danger-color); }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.2rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Projects Grid */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-box {
            position: relative;
            min-width: 250px;
        }

        .search-input {
            padding-left: 2.5rem;
            border-radius: 50px;
            border: 2px solid var(--border-color);
            padding-top: 0.6rem;
            padding-bottom: 0.6rem;
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 1.5rem;
        }

        /* Project Card Design */
        .project-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
        }

        .project-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }

        .card-top {
            padding: 1.5rem 1.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .category-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.8rem;
            background: var(--bg-light);
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dark);
            transition: var(--transition);
        }
        
        .project-card:hover .category-badge {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-open { background: var(--success-light); color: var(--success-color); }
        .status-completed { background: var(--info-light); color: var(--info-color); }
        .status-closed { background: var(--secondary-color); color: white; opacity: 0.7; }

        .card-body-content {
            padding: 0 1.5rem 1.5rem;
            flex: 1;
        }

        .project-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .project-title a {
            color: var(--text-dark);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .project-title a:hover {
            color: var(--primary-color);
        }

        .umkm-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px dashed var(--border-color);
        }

        .umkm-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: var(--shadow-sm);
            background-color: var(--primary-light);
        }

        .umkm-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .umkm-type {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 0.1rem;
        }

        .meta-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .meta-value.budget { color: var(--success-color); }

        .card-footer-action {
            padding: 1.25rem 1.5rem;
            background: var(--bg-light);
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .btn-action {
            flex: 1;
            padding: 0.6rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .btn-view {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(62, 127, 213, 0.3);
        }

        .btn-unsave {
            background: transparent;
            color: var(--danger-color);
            border: 1px solid var(--danger-light);
            max-width: 45px; /* Icon only style mostly */
        }
        
        .btn-unsave:hover {
            background: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
        }

        .applied-badge {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255, 255, 255, 0.9);
            color: var(--success-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--success-light);
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            grid-column: 1 / -1;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--primary-light);
            margin-bottom: 1.5rem;
            display: inline-block;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @media (max-width: 768px) {
            .projects-grid {
                grid-template-columns: 1fr;
            }
            .section-header {
                flex-direction: column;
                align-items: stretch;
            }
            .search-box {
                width: 100%;
            }
            .hero-title {
                font-size: 1.75rem;
            }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeOutDown {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(20px); }
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            
            <div class="page-hero">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h1 class="hero-title">
                            <i class="fas fa-bookmark me-3"></i>Proyek Disimpan
                        </h1>
                        <p class="hero-subtitle mb-0 opacity-75" style="font-size: 1.1rem; max-width: 600px;">
                            Kumpulan proyek yang menarik perhatian Anda. Tinjau kembali detailnya dan ajukan proposal sebelum terlambat.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end d-none d-lg-block">
                        <div class="d-inline-block bg-white bg-opacity-10 p-4 rounded-4 backdrop-blur">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-folder-open fa-lg"></i>
                                </div>
                                <div class="text-start text-white">
                                    <h5 class="mb-0 fw-bold"><?php echo $stats['total']; ?> Proyek</h5>
                                    <small class="opacity-75">Dalam koleksi Anda</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label"><i class="fas fa-layer-group me-1"></i> Total Disimpan</div>
                </div>
                <div class="stat-card applied">
                    <div class="stat-number text-success"><?php echo $stats['applied']; ?></div>
                    <div class="stat-label"><i class="fas fa-check-circle me-1"></i> Sudah Diajukan</div>
                </div>
                <div class="stat-card deadline">
                    <div class="stat-number text-danger"><?php echo $stats['expiring_soon']; ?></div>
                    <div class="stat-label"><i class="fas fa-clock me-1"></i> Segera Berakhir</div>
                </div>
                <div class="stat-card open">
                    <div class="stat-number text-info"><?php echo $stats['open']; ?></div>
                    <div class="stat-label"><i class="fas fa-door-open me-1"></i> Masih Open</div>
                </div>
            </div>

            <div class="section-header">
                <h3 class="mb-0">Daftar Koleksi</h3>
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" class="form-control search-input" placeholder="Cari proyek disimpan...">
                </div>
            </div>

            <div class="projects-grid" id="projectsContainer">
                <?php if (count($saved_projects) > 0): ?>
                    <?php foreach ($saved_projects as $index => $project): ?>
                        <?php 
                        // Category Icon Logic
                        $category_icons = [
                            'website' => ['icon' => 'fas fa-globe', 'color' => '#3E7FD5'],
                            'logo' => ['icon' => 'fas fa-palette', 'color' => '#FF6B6B'],
                            'social_media' => ['icon' => 'fas fa-hashtag', 'color' => '#6BC5D2'],
                            'video' => ['icon' => 'fas fa-video', 'color' => '#FFC300'],
                            'content' => ['icon' => 'fas fa-file-alt', 'color' => '#28a745'],
                            'marketing' => ['icon' => 'fas fa-bullhorn', 'color' => '#9C27B0'],
                            'other' => ['icon' => 'fas fa-ellipsis-h', 'color' => '#6c757d']
                        ];
                        
                        $cat_key = $project['category'] ?? 'other';
                        $icon_data = $category_icons[$cat_key] ?? $category_icons['other'];
                        $cat_label = ucfirst(str_replace('_', ' ', $cat_key));
                        
                        // Status Label
                        $status_label = ($project['status'] == 'in_progress') ? 'In Progress' : ucfirst($project['status']);
                        $status_class = 'status-' . ($project['status'] == 'open' ? 'open' : ($project['status'] == 'completed' ? 'completed' : 'closed'));
                        
                        // Budget Format
                        $budget_display = "Negotiable";
                        if ($project['budget_range_min']) {
                            $budget_display = "Rp " . number_format($project['budget_range_min'], 0, ',', '.');
                            if ($project['budget_range_max'] && $project['budget_range_max'] > $project['budget_range_min']) {
                                $budget_display .= "+"; // Simplified for card
                            }
                        }
                        
                        // UMKM Name logic
                        $umkm_display_name = !empty($project['business_name']) ? $project['business_name'] : $project['umkm_name'];
                        ?>
                        
                        <div class="project-card animate__animated animate__fadeInUp" 
                             style="animation-delay: <?php echo $index * 0.1; ?>s"
                             data-title="<?php echo strtolower($project['title']); ?>"
                             data-category="<?php echo strtolower($cat_label); ?>"
                             data-umkm="<?php echo strtolower($umkm_display_name); ?>">
                            
                            <?php if ($project['already_applied'] > 0): ?>
                                <div class="applied-badge">
                                    <i class="fas fa-check-circle"></i> Applied
                                </div>
                            <?php endif; ?>

                            <div class="card-top">
                                <div class="category-badge">
                                    <i class="<?php echo $icon_data['icon']; ?>" style="color: <?php echo $icon_data['color']; ?>"></i>
                                    <span><?php echo $cat_label; ?></span>
                                </div>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_label; ?>
                                </span>
                            </div>

                            <div class="card-body-content">
                                <h4 class="project-title">
                                    <a href="view-project.php?id=<?php echo $project['id']; ?>">
                                        <?php echo htmlspecialchars($project['title']); ?>
                                    </a>
                                </h4>
                                
                                <div class="umkm-info">
                                    <?php if (!empty($project['business_logo_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($project['business_logo_url']); ?>" alt="Logo" class="umkm-avatar">
                                    <?php else: ?>
                                        <div class="umkm-avatar d-flex align-items-center justify-content-center text-primary fw-bold" style="font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($umkm_display_name, 0, 2)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="umkm-name"><?php echo htmlspecialchars($umkm_display_name); ?></div>
                                        <div class="umkm-type">UMKM Owner</div>
                                    </div>
                                </div>
                                
                                <div class="meta-grid">
                                    <div class="meta-item">
                                        <span class="meta-label"><i class="fas fa-coins me-1"></i> Budget</span>
                                        <span class="meta-value budget"><?php echo $budget_display; ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label"><i class="fas fa-calendar-alt me-1"></i> Deadline</span>
                                        <span class="meta-value">
                                            <?php echo !empty($project['deadline']) ? date('d M Y', strtotime($project['deadline'])) : '-'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer-action">
                                <a href="view-project.php?id=<?php echo $project['id']; ?>" class="btn-action btn-view">
                                    <i class="fas fa-eye"></i> Detail
                                </a>
                                
                                <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus proyek ini dari daftar disimpan?');">
                                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                    <input type="hidden" name="unsave_project" value="1">
                                    <button type="submit" class="btn-action btn-unsave" title="Hapus dari Disimpan" data-bs-toggle="tooltip">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="far fa-bookmark"></i>
                        </div>
                        <h3>Belum Ada Proyek Disimpan</h3>
                        <p class="text-muted mb-4">
                            Jelajahi proyek yang tersedia dan simpan yang menarik perhatian Anda<br>
                            untuk ditinjau atau diajukan nanti.
                        </p>
                        <a href="find-projects.php" class="btn btn-primary px-4 py-2 rounded-pill">
                            <i class="fas fa-search me-2"></i>Cari Proyek
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Live Search Functionality
            const searchInput = document.getElementById('searchInput');
            const projectsContainer = document.getElementById('projectsContainer');
            const projects = document.querySelectorAll('.project-card');

            if(searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    let hasResults = false;

                    projects.forEach(project => {
                        const title = project.dataset.title;
                        const category = project.dataset.category;
                        const umkm = project.dataset.umkm;
                        
                        if (title.includes(searchTerm) || category.includes(searchTerm) || umkm.includes(searchTerm)) {
                            project.style.display = 'flex'; // Reset to flex as defined in CSS
                            project.classList.remove('animate__fadeOutDown');
                            project.classList.add('animate__fadeInUp');
                            hasResults = true;
                        } else {
                            project.classList.remove('animate__fadeInUp');
                            project.classList.add('animate__fadeOutDown');
                            setTimeout(() => {
                                if(project.classList.contains('animate__fadeOutDown')) {
                                    project.style.display = 'none';
                                }
                            }, 300); // Wait for animation
                        }
                    });
                });
            }

            // Optional: Animate stats numbers
            const statsNumbers = document.querySelectorAll('.stat-number');
            statsNumbers.forEach(stat => {
                const target = parseInt(stat.innerText);
                if(target > 0) {
                    let current = 0;
                    const increment = Math.ceil(target / 20);
                    const timer = setInterval(() => {
                        current += increment;
                        if(current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        stat.innerText = current;
                    }, 50);
                }
            });
        });
    </script>
</body>
</html>