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

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$budget_min = isset($_GET['budget_min']) ? floatval($_GET['budget_min']) : 0;
$budget_max = isset($_GET['budget_max']) ? floatval($_GET['budget_max']) : 0;

// Build query dengan PDO
$sql = "
    SELECT 
        p.*, 
        u.full_name as umkm_name,
        up.business_name,
        up.business_logo_url,
        COUNT(DISTINCT pr.id) as proposal_count,
        (SELECT COUNT(*) FROM proposals pr2 WHERE pr2.project_id = p.id AND pr2.creative_user_id = :user_id) as already_applied
    FROM projects p
    JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    LEFT JOIN proposals pr ON p.id = pr.project_id
    WHERE p.status = 'open'
";

$params = [':user_id' => $_SESSION['user_id']];

// Tambahkan kondisi pencarian
if (!empty($query)) {
    $sql .= " AND (p.title LIKE :query OR p.description LIKE :query_desc)";
    $params[':query'] = "%$query%";
    $params[':query_desc'] = "%$query%";
}

if (!empty($category) && $category !== 'all') {
    $sql .= " AND p.category = :category";
    $params[':category'] = $category;
}

if ($budget_min > 0) {
    $sql .= " AND p.budget_range_max >= :budget_min";
    $params[':budget_min'] = $budget_min;
}

if ($budget_max > 0) {
    $sql .= " AND p.budget_range_min <= :budget_max";
    $params[':budget_max'] = $budget_max;
}

$sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

// Eksekusi query
$projects = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari Proyek - Konekin</title>
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
        
        h1, h2, h3, h4, h5, h6, .project-card h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        .main-content {
            padding: 10px 10px 10px;
            margin-top: 0;
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
        
        .filter-sidebar {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .project-list {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 0;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .project-item {
            padding: 25px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s;
        }
        
        .project-item:hover {
            background-color: #f8f9fa;
        }
        
        .project-item:last-child {
            border-bottom: none;
        }
        
        .budget-badge {
            background-color: var(--primary-color);
            color: white;
            font-weight: 700;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        
        .skill-tag {
            background-color: #f1f8ff;
            border: 1px solid #d0e7ff;
            color: var(--primary-color);
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
            font-weight: 500;
        }
        
        .apply-button {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: var(--text-dark) !important;
            font-weight: 700;
            transition: all 0.2s ease;
            border-radius: 8px;
            padding: 8px 20px;
        }
        
        .apply-button:hover {
            background-color: #e5b100;
            border-color: #e5b100;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 195, 0, 0.4);
        }
        
        .umkm-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }
        
        .project-category {
            display: inline-block;
            background-color: #e9f7fe;
            color: var(--primary-color);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .project-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .proposal-count {
            display: flex;
            align-items: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .deadline-badge {
            background-color: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
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
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 150, 190, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .filter-toggle {
            display: none;
            margin-bottom: 15px;
        }
        
        @media (max-width: 992px) {
            .filter-toggle {
                display: block;
            }
            
            .filter-sidebar {
                margin-bottom: 20px;
            }
        }
        
        .category-icon {
            width: 24px;
            height: 24px;
            margin-right: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .popular-categories {
            margin-top: 20px;
        }
        
        .category-checkbox {
            margin-bottom: 10px;
        }
        
        .category-checkbox input {
            margin-right: 10px;
        }
        
        .results-header {
            background-color: white;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
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
                        <h1 class="mb-2"><i class="fas fa-search me-2"></i>Cari Proyek</h1>
                        <p class="mb-0">Temukan proyek yang sesuai dengan keahlian dan minat Anda</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="my-proposals.php" class="btn btn-light me-2">
                            <i class="fas fa-file-alt me-1"></i>Proposal Saya
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Filter Sidebar -->
                <div class="col-lg-3">
                    <!-- Filter Toggle for Mobile -->
                    <button class="btn btn-primary filter-toggle w-100 mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="fas fa-filter me-2"></i>Filter Pencarian
                    </button>
                    
                    <div class="filter-sidebar collapse show" id="filterCollapse">
                        <h5 class="mb-3"><i class="fas fa-sliders-h me-2"></i>Saring Hasil</h5>
                        <form method="GET" action="find-projects.php">
                            <div class="mb-3">
                                <label for="query" class="form-label">Kata Kunci</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control border-start-0" id="query" name="query" placeholder="Cari proyek..." value="<?php echo htmlspecialchars($query); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category" class="form-label">Kategori</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>Semua Kategori</option>
                                    <option value="website" <?php echo $category === 'website' ? 'selected' : ''; ?>>Website</option>
                                    <option value="logo" <?php echo $category === 'logo' ? 'selected' : ''; ?>>Logo</option>
                                    <option value="social_media" <?php echo $category === 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                                    <option value="video" <?php echo $category === 'video' ? 'selected' : ''; ?>>Video</option>
                                    <option value="content" <?php echo $category === 'content' ? 'selected' : ''; ?>>Content</option>
                                    <option value="marketing" <?php echo $category === 'marketing' ? 'selected' : ''; ?>>Marketing</option>
                                    <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Rentang Budget</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">Rp</span>
                                            <input type="number" class="form-control" id="budget_min" name="budget_min" placeholder="Min" value="<?php echo $budget_min; ?>">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">Rp</span>
                                            <input type="number" class="form-control" id="budget_max" name="budget_max" placeholder="Max" value="<?php echo $budget_max; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="popular-categories">
                                <h6 class="mb-3">POPULAR</h6>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="logo_design" name="popular_categories[]" value="logo_design">
                                        <label class="form-check-label" for="logo_design">Logo Design</label>
                                    </div>
                                </div>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="branding_services" name="popular_categories[]" value="branding_services">
                                        <label class="form-check-label" for="branding_services">Branding Services</label>
                                    </div>
                                </div>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="social_media_design" name="popular_categories[]" value="social_media_design">
                                        <label class="form-check-label" for="social_media_design">Social Media Design</label>
                                    </div>
                                </div>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="website_design" name="popular_categories[]" value="website_design">
                                        <label class="form-check-label" for="website_design">Website Design</label>
                                    </div>
                                </div>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="illustrations" name="popular_categories[]" value="illustrations">
                                        <label class="form-check-label" for="illustrations">Illustrations</label>
                                    </div>
                                </div>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="packaging_design" name="popular_categories[]" value="packaging_design">
                                        <label class="form-check-label" for="packaging_design">Packaging Design</label>
                                    </div>
                                </div>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="landing_page_design" name="popular_categories[]" value="landing_page_design">
                                        <label class="form-check-label" for="landing_page_design">Landing Page Design</label>
                                    </div>
                                </div>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="uiux_design" name="popular_categories[]" value="uiux_design">
                                        <label class="form-check-label" for="uiux_design">UI/UX Design</label>
                                    </div>
                                </div>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="architecture_design" name="popular_categories[]" value="architecture_design">
                                        <label class="form-check-label" for="architecture_design">Architecture & Interior Design</label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mt-3"><i class="fas fa-filter me-1"></i> Terapkan Filter</button>
                            
                            <?php if (!empty($query) || !empty($category) || $budget_min > 0 || $budget_max > 0): ?>
                                <a href="find-projects.php" class="btn btn-outline-secondary w-100 mt-2">
                                    <i class="fas fa-times me-1"></i>Reset Filter
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- Projects List -->
                <div class="col-lg-9">
                    <div class="project-list">
                        <!-- Results Header -->
                        <div class="results-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0"><?php echo count($projects); ?> Proyek Ditemukan</h4>
                                <div class="text-muted">
                                    <span>Urutkan:</span>
                                    <select class="form-select form-select-sm d-inline-block w-auto ms-2">
                                        <option>Terbaru</option>
                                        <option>Budget Tertinggi</option>
                                        <option>Deadline Terdekat</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Projects -->
                        <?php if (count($projects) > 0): ?>
                            <?php foreach ($projects as $project): ?>
                                <div class="project-item">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <!-- Project Header -->
                                            <div class="d-flex align-items-start mb-2">
                                                <span class="project-category me-3">
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
                                                    echo isset($category_names[$project['category']]) ? $category_names[$project['category']] : 'Lainnya';
                                                    ?>
                                                </span>
                                                <h5 class="card-title text-dark mb-0"><?php echo htmlspecialchars($project['title']); ?></h5>
                                            </div>
                                            
                                            <!-- Project Description -->
                                            <p class="text-muted mb-3">
                                                <?php 
                                                $description = $project['description'] ?? '';
                                                echo htmlspecialchars(mb_strlen($description) > 200 ? mb_substr($description, 0, 200) . '...' : $description); 
                                                ?>
                                            </p>
                                            
                                            <!-- Required Skills -->
                                            <?php if (!empty($project['required_skills'])): ?>
                                                <div class="mb-3">
                                                    <?php 
                                                    $skills = json_decode($project['required_skills'], true);
                                                    if (is_array($skills) && count($skills) > 0):
                                                        foreach (array_slice($skills, 0, 5) as $skill): // Limit to 5 skills
                                                    ?>
                                                        <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                                    <?php 
                                                        endforeach;
                                                        if (count($skills) > 5): 
                                                    ?>
                                                        <span class="skill-tag">+<?php echo count($skills) - 5; ?> lainnya</span>
                                                    <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- UMKM Info -->
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($project['business_logo_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($project['business_logo_url']); ?>" alt="Logo UMKM" class="umkm-avatar me-2">
                                                <?php else: ?>
                                                    <div class="umkm-avatar bg-light d-flex align-items-center justify-content-center me-2">
                                                        <i class="fas fa-store text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="mb-0 fw-bold"><?php echo htmlspecialchars($project['business_name'] ?? $project['umkm_name'] ?? 'Unknown'); ?></p>
                                                    <small class="text-muted">
                                                        <?php 
                                                        $business_type = $project['business_type'] ?? '';
                                                        if ($business_type) {
                                                            $business_types = [
                                                                'food' => 'Makanan & Minuman',
                                                                'fashion' => 'Fashion',
                                                                'craft' => 'Kerajinan',
                                                                'service' => 'Jasa',
                                                                'retail' => 'Retail',
                                                                'other' => 'Lainnya'
                                                            ];
                                                            echo isset($business_types[$business_type]) ? $business_types[$business_type] : 'UMKM';
                                                        } else {
                                                            echo 'UMKM';
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="d-flex flex-column h-100">
                                                <!-- Budget & Deadline -->
                                                <div class="mb-3">
                                                    <?php if ($project['budget_range_min'] || $project['budget_range_max']): ?>
                                                        <div class="budget-badge text-center mb-2">
                                                            <?php if ($project['budget_range_min'] && $project['budget_range_max']): ?>
                                                                Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?> - 
                                                                Rp <?php echo number_format($project['budget_range_max'], 0, ',', '.'); ?>
                                                            <?php elseif ($project['budget_range_min']): ?>
                                                                Mulai Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?>
                                                            <?php else: ?>
                                                                Harga negotiable
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($project['deadline'])): ?>
                                                        <div class="deadline-badge text-center">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php 
                                                            try {
                                                                $deadline = new DateTime($project['deadline']);
                                                                $now = new DateTime();
                                                                $interval = $now->diff($deadline);
                                                                if ($deadline > $now) {
                                                                    echo $interval->days . " hari lagi";
                                                                } else {
                                                                    echo "Tenggat waktu terlewat";
                                                                }
                                                            } catch (Exception $e) {
                                                                echo "Tanggal tidak valid";
                                                            }
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Proposal Count -->
                                                <div class="proposal-count text-center mb-3">
                                                    <i class="fas fa-paper-plane me-1"></i>
                                                    <?php echo $project['proposal_count'] ?? 0; ?> proposal
                                                </div>
                                                
                                                <!-- Action Button -->
                                                <div class="mt-auto">
                                                    <?php if (($project['already_applied'] ?? 0) > 0): ?>
                                                        <div class="d-grid">
                                                            <span class="badge bg-success p-2 text-center"><i class="fas fa-check me-1"></i>Sudah Diajukan</span>
                                                            <a href="view-project1.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary btn-sm mt-2">Lihat Detail</a>
                                                        </div>
                                                    <?php else: ?>
                                                        <a href="view-project1.php?id=<?php echo $project['id']; ?>" class="btn apply-button w-100">
                                                            Ajukan Proposal <i class="fas fa-arrow-right ms-2"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="project-item text-center">
                                <div class="empty-state py-5">
                                    <i class="fas fa-search"></i>
                                    <h3 class="mt-3">Maaf, hasil pencarian tidak ditemukan</h3>
                                    <p class="mb-4">Coba ubah kata kunci atau filter pencarian Anda</p>
                                    <a href="find-projects.php" class="btn btn-primary">
                                        <i class="fas fa-refresh me-2"></i>Reset Pencarian
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Highlight active filters
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const hasFilters = urlParams.has('query') || 
                              (urlParams.has('category') && urlParams.get('category') !== 'all') ||
                              (urlParams.has('budget_min') && parseFloat(urlParams.get('budget_min')) > 0) ||
                              (urlParams.has('budget_max') && parseFloat(urlParams.get('budget_max')) > 0);
            
            if (hasFilters) {
                const filterToggle = document.querySelector('.filter-toggle');
                if (filterToggle) {
                    filterToggle.classList.add('btn-success');
                    filterToggle.innerHTML = '<i class="fas fa-filter me-2"></i>Filter Aktif';
                }
            }
            
            // Add animation to project items on load
            const items = document.querySelectorAll('.project-item');
            items.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>