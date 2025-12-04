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
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3498db;
            --secondary: #7209b7;
            --accent: #f72585;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border: #dee2e6;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            --hover-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        .main-content {
            padding: 20px;
        }
        
        /* Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 1.1rem;
        }
        
        /* Filter Sidebar */
        .filter-sidebar {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            position: sticky;
            top: 20px;
        }
        
        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }
        
        .filter-header h5 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--dark);
        }
        
        .filter-toggle {
            display: none;
            margin-bottom: 15px;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
        }
        
        @media (max-width: 992px) {
            .filter-toggle {
                display: block;
            }
        }
        
        /* Form Elements */
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
            font-size: 0.95rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid var(--border);
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.15);
        }
        
        .input-group-text {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 10px 0 0 10px;
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary), var(--primary-light));
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn-outline-secondary {
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 500;
        }
        
        .btn-light {
            border-radius: 10px;
            font-weight: 500;
            background: white;
            border: 1px solid var(--border);
        }
        
        /* Project List */
        .project-list {
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .results-header {
            background: white;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .results-count {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .sort-select {
            border-radius: 8px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            font-size: 0.9rem;
        }
        
        /* Project Cards */
        .project-card {
            padding: 25px;
            border-bottom: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .project-card:hover {
            background-color: #f8faff;
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
        }
        
        .project-card:last-child {
            border-bottom: none;
        }
        
        /* Project Elements */
        .project-category {
            display: inline-flex;
            align-items: center;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .project-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 12px;
            line-height: 1.4;
            color: var(--dark);
        }
        
        .project-description {
            color: var(--gray);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 18px;
        }
        
        .skill-tag {
            background: rgba(67, 97, 238, 0.08);
            border: 1px solid rgba(67, 97, 238, 0.2);
            color: var(--primary);
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.8rem;
            margin-right: 8px;
            margin-bottom: 8px;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .skill-tag:hover {
            background: rgba(67, 97, 238, 0.15);
        }
        
        .umkm-info {
            display: flex;
            align-items: center;
            margin-top: 20px;
        }
        
        .umkm-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
            margin-right: 12px;
        }
        
        .umkm-details h6 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .umkm-details p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        /* Project Meta */
        .project-meta {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .budget-badge {
            background: linear-gradient(to right, var(--primary), var(--primary-light));
            color: white;
            font-weight: 600;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 12px;
            box-shadow: 0 3px 5px rgba(67, 97, 238, 0.2);
        }
        
        .deadline-badge {
            background: #fff9e6;
            color: #b38b00;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 15px;
            border: 1px solid #ffeaa7;
        }
        
        .proposal-count {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
        
        .proposal-count i {
            margin-right: 5px;
            color: var(--primary);
        }
        
        .apply-button {
            background: linear-gradient(to right, var(--accent), #ff6b9d);
            border: none;
            color: white;
            font-weight: 600;
            border-radius: 10px;
            padding: 12px 20px;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        
        .apply-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(247, 37, 133, 0.3);
            color: white;
        }
        
        .applied-badge {
            background: var(--success);
            color: white;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        /* Popular Categories */
        .popular-categories {
            margin-top: 25px;
            border-top: 1px solid var(--border);
            padding-top: 20px;
        }
        
        .popular-categories h6 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
        }
        
        .category-checkbox {
            margin-bottom: 10px;
        }
        
        .category-checkbox .form-check-label {
            font-size: 0.9rem;
            cursor: pointer;
            transition: color 0.2s;
            display: flex;
            align-items: center;
        }
        
        .category-checkbox .form-check-label:hover {
            color: var(--primary);
        }
        
        .category-checkbox .form-check-input {
            margin-right: 10px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--primary);
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .empty-state p {
            font-size: 1rem;
            margin-bottom: 25px;
        }
        
        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 10px;
            }
            
            .project-card {
                padding: 20px;
            }
            
            .results-header {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .sort-select {
                margin-top: 10px;
                align-self: flex-end;
            }
            
            .project-title {
                font-size: 1.1rem;
            }
        }
        
        /* Active Filter Indicator */
        .filter-active {
            background: var(--success) !important;
        }
        
        /* Loading Animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
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
                        <h1 class="mb-2"><i class="fas fa-search me-2"></i>Cari Proyek</h1>
                        <p class="mb-0">Temukan proyek yang sesuai dengan keahlian dan minat Anda</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="my-proposals.php" class="btn btn-light">
                            <i class="fas fa-file-alt me-1"></i>Proposal Saya
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Filter Sidebar -->
                <div class="col-lg-3">
                    <button class="btn btn-primary filter-toggle w-100 mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="fas fa-filter me-2"></i>Filter Pencarian
                    </button>
                    
                    <div class="filter-sidebar collapse show" id="filterCollapse">
                        <div class="filter-header">
                            <h5><i class="fas fa-sliders-h me-2"></i>Filter</h5>
                        </div>
                        <form method="GET" action="find-projects.php">
                            <!-- Kata Kunci -->
                            <div class="mb-4">
                                <label class="form-label">Kata Kunci</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control border-start-0" name="query" placeholder="Cari proyek..." value="<?php echo htmlspecialchars($query); ?>">
                                </div>
                            </div>
                            
                            <!-- Kategori -->
                            <div class="mb-4">
                                <label class="form-label">Kategori</label>
                                <select class="form-select" name="category">
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
                            
                            <!-- Budget -->
                            <div class="mb-4">
                                <label class="form-label">Rentang Budget</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">Rp</span>
                                            <input type="number" class="form-control" name="budget_min" placeholder="Min" value="<?php echo $budget_min; ?>">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">Rp</span>
                                            <input type="number" class="form-control" name="budget_max" placeholder="Max" value="<?php echo $budget_max; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Kategori Populer -->
                            <div class="popular-categories">
                                <h6>Kategori Populer</h6>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="logo_design" name="popular_categories[]" value="logo_design">
                                        <label class="form-check-label" for="logo_design">Logo Design</label>
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
                                        <input class="form-check-input" type="checkbox" id="social_media_design" name="popular_categories[]" value="social_media_design">
                                        <label class="form-check-label" for="social_media_design">Social Media Design</label>
                                    </div>
                                </div>
                                <div class="category-checkbox">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="uiux_design" name="popular_categories[]" value="uiux_design">
                                        <label class="form-check-label" for="uiux_design">UI/UX Design</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <button type="submit" class="btn btn-primary w-100 mt-3">
                                <i class="fas fa-filter me-1"></i> Terapkan Filter
                            </button>
                            
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
                            <div class="results-count"><?php echo count($projects); ?> Proyek Ditemukan</div>
                            <div class="text-muted">
                                <span>Urutkan:</span>
                                <select class="sort-select ms-2">
                                    <option>Terbaru</option>
                                    <option>Budget Tertinggi</option>
                                    <option>Deadline Terdekat</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Projects List -->
                        <?php if (count($projects) > 0): ?>
                            <?php foreach ($projects as $project): ?>
                                <div class="project-card fade-in">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <!-- Category & Title -->
                                            <span class="project-category">
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
                                            
                                            <h3 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                                            
                                            <!-- Description -->
                                            <p class="project-description">
                                                <?php 
                                                $description = $project['description'] ?? '';
                                                echo htmlspecialchars(mb_strlen($description) > 200 ? mb_substr($description, 0, 200) . '...' : $description); 
                                                ?>
                                            </p>
                                            
                                            <!-- Skills -->
                                            <?php if (!empty($project['required_skills'])): ?>
                                                <div class="mb-3">
                                                    <?php 
                                                    $skills = json_decode($project['required_skills'], true);
                                                    if (is_array($skills) && count($skills) > 0):
                                                        foreach (array_slice($skills, 0, 5) as $skill):
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
                                            <div class="umkm-info">
                                                <?php if (!empty($project['business_logo_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($project['business_logo_url']); ?>" alt="Logo UMKM" class="umkm-avatar">
                                                <?php else: ?>
                                                    <div class="umkm-avatar bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-store text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="umkm-details">
                                                    <h6><?php echo htmlspecialchars($project['business_name'] ?? $project['umkm_name'] ?? 'Unknown'); ?></h6>
                                                    <p>
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
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="project-meta">
                                                <!-- Budget & Deadline -->
                                                <div class="mb-3">
                                                    <?php if ($project['budget_range_min'] || $project['budget_range_max']): ?>
                                                        <div class="budget-badge">
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
                                                        <div class="deadline-badge">
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
                                                <div class="proposal-count">
                                                    <i class="fas fa-paper-plane me-1"></i>
                                                    <?php echo $project['proposal_count'] ?? 0; ?> proposal
                                                </div>
                                                
                                                <!-- Action Button -->
                                                <div class="mt-auto">
                                                    <?php if (($project['already_applied'] ?? 0) > 0): ?>
                                                        <div class="applied-badge">
                                                            <i class="fas fa-check me-1"></i>Sudah Diajukan
                                                        </div>
                                                        <a href="view-project1.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary w-100">
                                                            Lihat Detail
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="view-project1.php?id=<?php echo $project['id']; ?>" class="apply-button">
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
                            <div class="project-card text-center">
                                <div class="empty-state py-5">
                                    <i class="fas fa-search"></i>
                                    <h3 class="mt-3">Tidak ada proyek ditemukan</h3>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight active filters
            const urlParams = new URLSearchParams(window.location.search);
            const hasFilters = urlParams.has('query') || 
                              (urlParams.has('category') && urlParams.get('category') !== 'all') ||
                              (urlParams.has('budget_min') && parseFloat(urlParams.get('budget_min')) > 0) ||
                              (urlParams.has('budget_max') && parseFloat(urlParams.get('budget_max')) > 0);
            
            if (hasFilters) {
                const filterToggle = document.querySelector('.filter-toggle');
                if (filterToggle) {
                    filterToggle.classList.add('filter-active');
                    filterToggle.innerHTML = '<i class="fas fa-filter me-2"></i>Filter Aktif';
                }
            }
            
            // Add animation to project items
            const items = document.querySelectorAll('.fade-in');
            items.forEach((item, index) => {
                setTimeout(() => {
                    item.classList.add('visible');
                }, index * 100);
            });
            
            // Enhanced interactions
            const applyButtons = document.querySelectorAll('.apply-button');
            applyButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const button = this;
                    const originalText = button.innerHTML;
                    
                    // Show loading state
                    button.innerHTML = '<span class="loading-spinner"></span>Mengajukan Proposal...';
                    button.style.opacity = '0.7';
                    
                    // In a real app, you would submit a form or make an AJAX request here
                    setTimeout(() => {
                        button.innerHTML = '<i class="fas fa-check me-2"></i>Berhasil Diajukan';
                        button.style.background = 'linear-gradient(to right, #28a745, #20c997)';
                        button.style.opacity = '1';
                        
                        // Reset after 3 seconds
                        setTimeout(() => {
                            button.innerHTML = originalText;
                            button.style.background = '';
                        }, 3000);
                    }, 2000);
                });
            });
            
            // Sort functionality
            const sortSelect = document.querySelector('.sort-select');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    // Show loading state
                    const projectList = document.querySelector('.project-list');
                    projectList.style.opacity = '0.7';
                    
                    setTimeout(() => {
                        projectList.style.opacity = '1';
                        // Show a toast notification
                        showToast('Proyek diurutkan berdasarkan: ' + this.value, 'info');
                    }, 800);
                });
            }
            
            function showToast(message, type = 'info') {
                // Create toast element
                const toast = document.createElement('div');
                toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 1060; min-width: 300px;';
                toast.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(toast);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 5000);
            }
            
            // Mobile filter toggle enhancement
            const filterToggle = document.querySelector('.filter-toggle');
            const filterCollapse = document.getElementById('filterCollapse');
            
            if (filterToggle && filterCollapse) {
                filterToggle.addEventListener('click', function() {
                    const isExpanded = filterCollapse.classList.contains('show');
                    
                    if (isExpanded) {
                        this.innerHTML = '<i class="fas fa-filter me-2"></i>Filter Pencarian';
                    } else {
                        this.innerHTML = '<i class="fas fa-times me-2"></i>Tutup Filter';
                    }
                });
            }
        });
    </script>
</body>
</html>