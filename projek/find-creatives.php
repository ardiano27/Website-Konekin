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

// Definisi labels untuk pengalaman
$experience_labels = [
    'beginner' => 'Pemula',
    'intermediate' => 'Menengah', 
    'expert' => 'Expert'
];

// Ambil parameter filter dari URL
$search_query = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$experience_filter = $_GET['experience'] ?? '';
$rating_filter = $_GET['rating'] ?? '';
$availability_filter = $_GET['availability'] ?? '';

// Query dasar untuk mengambil kreator
$sql = "
    SELECT 
        u.id,
        u.uuid,
        u.full_name,
        u.email,
        u.avatar_url,
        u.background_url,
        u.created_at,
        cp.tagline,
        cp.bio,
        cp.hourly_rate,
        cp.experience_level,
        cp.location,
        cp.rating,
        cp.completed_projects,
        cp.is_available,
        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as skills,
        COUNT(DISTINCT p.id) as portfolio_count,
        COUNT(DISTINCT pr.id) as completed_contracts,
        MAX(DISTINCT p.created_at) as latest_portfolio_date
    FROM users u
    LEFT JOIN creative_profiles cp ON u.id = cp.user_id
    LEFT JOIN creative_skills cs ON cp.id = cs.creative_profile_id
    LEFT JOIN skills s ON cs.skill_id = s.id
    LEFT JOIN portfolios p ON cp.id = p.creative_profile_id AND p.is_public = 1
    LEFT JOIN contracts c ON u.id = c.creative_user_id AND c.status = 'completed'
    LEFT JOIN proposals pr ON u.id = pr.creative_user_id AND pr.status = 'accepted'
    WHERE u.user_type = 'creative' AND u.is_active = 1
";

// Tambahkan kondisi filter jika ada
$conditions = [];
$params = [];

if (!empty($search_query)) {
    $conditions[] = "(u.full_name LIKE :search OR cp.tagline LIKE :search OR cp.bio LIKE :search OR s.name LIKE :search)";
    $params[':search'] = "%$search_query%";
}

if (!empty($category_filter)) {
    $conditions[] = "s.category = :category";
    $params[':category'] = $category_filter;
}

if (!empty($experience_filter)) {
    $conditions[] = "cp.experience_level = :experience";
    $params[':experience'] = $experience_filter;
}

if (!empty($rating_filter)) {
    $conditions[] = "cp.rating >= :rating";
    $params[':rating'] = $rating_filter;
}

if (!empty($availability_filter)) {
    $is_available = ($availability_filter === '1') ? 1 : 0;
    $conditions[] = "cp.is_available = :availability";
    $params[':availability'] = $is_available;
}

if (count($conditions) > 0) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Group dan order
$sql .= " GROUP BY u.id ORDER BY cp.rating DESC, cp.completed_projects DESC LIMIT 50";

// Eksekusi query
$creatives = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $creatives = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

// Ambil categories untuk filter kategori
$categories_sql = "SELECT DISTINCT category FROM skills WHERE is_active = 1 AND category IS NOT NULL ORDER BY category";
$categories = [];
try {
    $stmt = $conn->query($categories_sql);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

// Ambil portofolio terbaru untuk setiap kreator
$portfolios_by_creative = [];
if (count($creatives) > 0) {
    $creative_ids = array_column($creatives, 'id');
    $placeholders = implode(',', array_fill(0, count($creative_ids), '?'));
    
    $portfolio_sql = "
        SELECT 
            p.creative_profile_id,
            u.id as user_id,
            p.title,
            p.description,
            p.image_urls,
            p.project_url,
            p.project_date,
            p.created_at
        FROM portfolios p
        JOIN creative_profiles cp ON p.creative_profile_id = cp.id
        JOIN users u ON cp.user_id = u.id
        WHERE u.id IN ($placeholders) AND p.is_public = 1
        ORDER BY p.created_at DESC
        LIMIT 100
    ";
    
    try {
        $stmt = $conn->prepare($portfolio_sql);
        $stmt->execute($creative_ids);
        $portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($portfolios as $portfolio) {
            $user_id = $portfolio['user_id'];
            if (!isset($portfolios_by_creative[$user_id])) {
                $portfolios_by_creative[$user_id] = [];
            }
            if (count($portfolios_by_creative[$user_id]) < 3) {
                $portfolios_by_creative[$user_id][] = $portfolio;
            }
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Temukan Kreator - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
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
            --purple-color: #6f42c1;
            --purple-light: rgba(111, 66, 193, 0.1);
            --bg-light: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.12);
            --shadow-xl: 0 12px 40px rgba(0, 0, 0, 0.15);
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

        /* Hero Header */
        .creatives-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .creatives-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .creatives-hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .hero-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
            max-width: 600px;
        }

        /* Search Section */
        .search-section {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            animation: slideInLeft 0.6s ease;
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .search-box {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .search-box .form-control {
            padding-left: 3rem;
            border-radius: 50px;
            border: 2px solid var(--border-color);
            font-size: 1rem;
            height: 50px;
            transition: var(--transition);
        }

        .search-box .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(62, 127, 213, 0.25);
        }

        .search-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            z-index: 10;
        }

        .search-btn {
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(62, 127, 213, 0.3);
        }

        /* Filter Cards */
        .filter-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            margin-bottom: 1rem;
            transition: var(--transition);
            overflow: hidden;
        }

        .filter-card.active {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
        }

        .filter-card-header {
            padding: 1rem 1.25rem;
            cursor: pointer;
            background: var(--bg-light);
            transition: var(--transition);
        }

        .filter-card-header:hover {
            background: rgba(62, 127, 213, 0.05);
        }

        .filter-card-title {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .filter-card-indicator {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .filter-card-body {
            padding: 1.25rem;
            border-top: 1px solid var(--border-color);
        }

        .filter-options-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .filter-option-checkbox {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            border-radius: 8px;
            transition: var(--transition);
        }

        .filter-option-checkbox:hover {
            background: var(--bg-light);
        }

        .filter-option-checkbox input[type="radio"] {
            display: none;
        }

        .filter-option-checkbox input[type="radio"]:checked + .filter-option-label {
            color: var(--primary-color);
            font-weight: 600;
        }

        .filter-option-checkbox input[type="radio"]:checked + .filter-option-label::before {
            content: '✓';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            margin-right: 0.75rem;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        .filter-option-label {
            display: flex;
            align-items: center;
            width: 100%;
            cursor: pointer;
            color: var(--text-muted);
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .filter-option-label::before {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            transition: var(--transition);
        }

        .filter-option-text {
            flex: 1;
        }

        /* Active Filter Tags */
        .active-filter-tag {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            border: 1px solid transparent;
        }

        .remove-filter {
            color: inherit;
            text-decoration: none;
            opacity: 0.6;
            transition: var(--transition);
            margin-left: 0.5rem;
        }

        .remove-filter:hover {
            opacity: 1;
        }

        /* Color Variants */
        .bg-primary-light { background: var(--primary-light); }
        .bg-success-light { background: var(--success-light); }
        .bg-warning-light { background: var(--warning-light); }
        .bg-info-light { background: var(--info-light); }

        .text-primary { color: var(--primary-color); }
        .text-success { color: var(--success-color); }
        .text-warning { color: var(--warning-color); }
        .text-info { color: var(--info-color); }

        /* Filter Card Value */
        .filter-card-value .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
        }

        /* Sort Dropdown */
        .sort-dropdown .form-select {
            border-radius: 20px;
            border: 2px solid var(--border-color);
            padding: 0.5rem 2rem 0.5rem 1rem;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .sort-dropdown .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(62, 127, 213, 0.25);
        }

        /* Creatives Grid */
        .creatives-section {
            margin-bottom: 3rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            animation: fadeIn 0.6s ease;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title::before {
            content: '';
            width: 6px;
            height: 24px;
            background: var(--primary-color);
            border-radius: 3px;
        }

        .creatives-count {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .creatives-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .creatives-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (min-width: 769px) and (max-width: 992px) {
            .creatives-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Improved Creative Card */
        .creative-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: var(--transition);
            border: 2px solid transparent;
            animation: fadeInUp 0.6s ease backwards;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .creative-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }

        .creative-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .creative-card-header {
            padding: 1.5rem;
            position: relative;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        .creative-card-body {
            padding: 1.5rem;
            flex: 1;
        }

        .creative-card-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            background: rgba(248, 249, 250, 0.5);
        }

        .creative-avatar {
            position: relative;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
        }

        .creative-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }

        /* Availability Indicator */
        .availability-indicator {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .availability-indicator.available {
            background-color: #28a745;
        }

        .availability-indicator.busy {
            background-color: #6c757d;
        }

        .availability-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .status-dot.available {
            background-color: #28a745;
        }

        .status-dot.busy {
            background-color: #6c757d;
        }

        .creative-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .creative-tagline {
            color: var(--primary-color);
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        /* Rating Display */
        .rating-display {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: var(--warning-light);
            border-radius: 20px;
            color: var(--warning-color);
            font-weight: 600;
        }

        /* Stats Badge */
        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: var(--bg-light);
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .creative-bio {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Skills Section */
        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .skill-tag {
            padding: 0.3rem 0.75rem;
            background: var(--primary-light);
            color: var(--primary-color);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .skill-tag:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        /* Portfolio Grid */
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .portfolio-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: var(--transition);
        }

        .portfolio-item:hover {
            transform: scale(1.05);
            z-index: 2;
        }

        .portfolio-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .portfolio-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .portfolio-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: var(--transition);
        }

        .portfolio-item:hover .portfolio-overlay {
            opacity: 1;
        }

        .portfolio-overlay i {
            color: white;
            font-size: 1.25rem;
        }

        /* Enhanced Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }

        @media (max-width: 576px) {
            .action-buttons {
                flex-direction: column;
            }
        }

        .action-btn {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            text-align: center;
        }

        .action-btn.primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .action-btn.primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(62, 127, 213, 0.3);
        }

        .action-btn.secondary {
            background: transparent;
            border-color: var(--border-color);
            color: var(--text-muted);
        }

        .action-btn.secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Experience Level Tags */
        .experience-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .experience-beginner {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .experience-intermediate {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .experience-expert {
            background: rgba(62, 127, 213, 0.1);
            color: var(--primary-color);
        }

        /* Empty State */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            animation: fadeIn 0.6s ease;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
            color: var(--primary-color);
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-15px); }
            60% { transform: translateY(-7px); }
        }

        .empty-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
        }

        .empty-description {
            color: var(--text-muted);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Loading Skeletons */
        .loading-skeleton {
            animation: skeleton-loading 1.5s infinite;
        }

        @keyframes skeleton-loading {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }

        /* Notification Animation */
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Quick Filter Buttons */
        .quick-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .quick-filter-btn {
            padding: 0.5rem 1.25rem;
            border: 2px solid var(--border-color);
            border-radius: 20px;
            background: white;
            color: var(--text-muted);
            font-size: 0.9rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-filter-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: var(--primary-light);
        }

        .quick-filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Hero Header -->
            <div class="creatives-hero">
                <div class="creatives-hero-content">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="hero-title">
                                <i class="fas fa-search me-3"></i>Temukan Kreator
                            </h1>
                            <p class="hero-subtitle">
                                Temukan creative worker terbaik untuk proyek Anda. Filter berdasarkan skill, pengalaman, rating, dan ketersediaan.
                            </p>
                        </div>
                        <div class="col-lg-4 text-lg-end d-none d-lg-block">
                            <div class="position-relative" style="max-width: 300px; margin-left: auto;">
                                <div class="position-absolute top-0 start-0 w-100 h-100 bg-purple opacity-10 rounded-3"></div>
                                <div class="position-relative p-4 rounded-3">
                                    <h5 class="mb-2">🎯 Kreator Terverifikasi</h5>
                                    <h2 class="mb-0"><?php echo count($creatives); ?></h2>
                                    <small class="opacity-75">tersedia untuk proyek Anda</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-section">
                <form id="searchForm" method="GET" action="find-creatives.php">
                    <!-- Search Bar -->
                    <div class="row g-3 mb-4">
                        <div class="col-lg-10">
                            <div class="search-box">
                                <i class="fas fa-search search-icon"></i>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    name="search" 
                                    placeholder="Cari kreator berdasarkan nama, skill, atau tagline..."
                                    value="<?php echo htmlspecialchars($search_query); ?>"
                                >
                            </div>
                        </div>
                        <div class="col-lg-2">
                            <button type="submit" class="btn btn-primary w-100 search-btn">
                                <i class="fas fa-search me-2"></i>Cari
                            </button>
                        </div>
                    </div>

                    <!-- Quick Filters -->
                    <div class="quick-filters mb-4">
                        <?php 
                        $availability_labels = [
                            '1' => 'Tersedia',
                            '0' => 'Sibuk'
                        ];
                        ?>
                        <?php foreach ($availability_labels as $value => $label): ?>
                            <button type="button" 
                                    class="quick-filter-btn <?php echo $availability_filter === $value ? 'active' : ''; ?>"
                                    onclick="setAvailability('<?php echo $value; ?>')">
                                <?php if ($value === '1'): ?>
                                    <i class="fas fa-circle text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-circle text-secondary"></i>
                                <?php endif; ?>
                                <?php echo $label; ?>
                            </button>
                        <?php endforeach; ?>
                        
                        <?php 
                        $rating_options = [
                            '4.5' => '4.5+ Rating',
                            '4.0' => '4.0+ Rating',
                            '3.5' => '3.5+ Rating'
                        ];
                        ?>
                        <?php foreach ($rating_options as $value => $label): ?>
                            <button type="button" 
                                    class="quick-filter-btn <?php echo $rating_filter === $value ? 'active' : ''; ?>"
                                    onclick="setRating('<?php echo $value; ?>')">
                                <i class="fas fa-star text-warning"></i>
                                <?php echo $label; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Advanced Filters -->
                    <div class="row g-3">
                        <!-- Category Filter Card -->
                        <div class="col-md-6 col-lg-4">
                            <div class="filter-card <?php echo !empty($category_filter) ? 'active' : ''; ?>">
                                <div class="filter-card-header" data-bs-toggle="collapse" href="#categoryCollapse">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-filter me-2"></i>
                                            <span class="filter-card-title">Kategori Skill</span>
                                        </div>
                                        <div class="filter-card-indicator">
                                            <?php if (!empty($category_filter)): ?>
                                                <span class="badge bg-primary">✓</span>
                                            <?php else: ?>
                                                <i class="fas fa-chevron-down"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($category_filter)): ?>
                                        <div class="filter-card-value mt-2">
                                            <span class="badge bg-primary-light"><?php echo htmlspecialchars(ucfirst($category_filter)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="collapse <?php echo !empty($category_filter) ? 'show' : ''; ?>" id="categoryCollapse">
                                    <div class="filter-card-body">
                                        <div class="filter-options-grid">
                                            <div class="filter-option-checkbox">
                                                <input 
                                                    type="radio" 
                                                    name="category" 
                                                    id="category_all"
                                                    value=""
                                                    <?php echo empty($category_filter) ? 'checked' : ''; ?>
                                                    onchange="this.form.submit()"
                                                >
                                                <label for="category_all" class="filter-option-label">
                                                    <span class="filter-option-text">Semua Kategori</span>
                                                </label>
                                            </div>
                                            <?php foreach ($categories as $category): ?>
                                                <div class="filter-option-checkbox">
                                                    <input 
                                                        type="radio" 
                                                        name="category" 
                                                        id="category_<?php echo htmlspecialchars($category); ?>"
                                                        value="<?php echo htmlspecialchars($category); ?>"
                                                        <?php echo $category_filter === $category ? 'checked' : ''; ?>
                                                        onchange="this.form.submit()"
                                                    >
                                                    <label for="category_<?php echo htmlspecialchars($category); ?>" class="filter-option-label">
                                                        <span class="filter-option-text"><?php echo htmlspecialchars(ucfirst($category)); ?></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Experience Filter Card -->
                        <div class="col-md-6 col-lg-4">
                            <div class="filter-card <?php echo !empty($experience_filter) ? 'active' : ''; ?>">
                                <div class="filter-card-header" data-bs-toggle="collapse" href="#experienceCollapse">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-chart-line me-2"></i>
                                            <span class="filter-card-title">Tingkat Pengalaman</span>
                                        </div>
                                        <div class="filter-card-indicator">
                                            <?php if (!empty($experience_filter)): ?>
                                                <span class="badge bg-primary">✓</span>
                                            <?php else: ?>
                                                <i class="fas fa-chevron-down"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($experience_filter)): ?>
                                        <div class="filter-card-value mt-2">
                                            <span class="badge bg-warning-light">
                                                <?php echo isset($experience_labels[$experience_filter]) ? $experience_labels[$experience_filter] : ucfirst($experience_filter); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="collapse <?php echo !empty($experience_filter) ? 'show' : ''; ?>" id="experienceCollapse">
                                    <div class="filter-card-body">
                                        <div class="filter-options-grid">
                                            <div class="filter-option-checkbox">
                                                <input 
                                                    type="radio" 
                                                    name="experience" 
                                                    id="experience_all"
                                                    value=""
                                                    <?php echo empty($experience_filter) ? 'checked' : ''; ?>
                                                    onchange="this.form.submit()"
                                                >
                                                <label for="experience_all" class="filter-option-label">
                                                    <span class="filter-option-text">Semua Level</span>
                                                </label>
                                            </div>
                                            <?php foreach ($experience_labels as $value => $label): ?>
                                            <div class="filter-option-checkbox">
                                                <input 
                                                    type="radio" 
                                                    name="experience" 
                                                    id="experience_<?php echo $value; ?>"
                                                    value="<?php echo $value; ?>"
                                                    <?php echo $experience_filter === $value ? 'checked' : ''; ?>
                                                    onchange="this.form.submit()"
                                                >
                                                <label for="experience_<?php echo $value; ?>" class="filter-option-label">
                                                    <span class="filter-option-text"><?php echo $label; ?></span>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rating Filter Card -->
                        <div class="col-md-6 col-lg-4">
                            <div class="filter-card <?php echo !empty($rating_filter) ? 'active' : ''; ?>">
                                <div class="filter-card-header" data-bs-toggle="collapse" href="#ratingCollapse">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-star me-2"></i>
                                            <span class="filter-card-title">Minimum Rating</span>
                                        </div>
                                        <div class="filter-card-indicator">
                                            <?php if (!empty($rating_filter)): ?>
                                                <span class="badge bg-primary">✓</span>
                                            <?php else: ?>
                                                <i class="fas fa-chevron-down"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($rating_filter)): ?>
                                        <div class="filter-card-value mt-2">
                                            <span class="badge bg-success-light">
                                                <i class="fas fa-star text-warning me-1"></i><?php echo $rating_filter; ?>+
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="collapse <?php echo !empty($rating_filter) ? 'show' : ''; ?>" id="ratingCollapse">
                                    <div class="filter-card-body">
                                        <div class="filter-options-grid">
                                            <div class="filter-option-checkbox">
                                                <input 
                                                    type="radio" 
                                                    name="rating" 
                                                    id="rating_all"
                                                    value=""
                                                    <?php echo empty($rating_filter) ? 'checked' : ''; ?>
                                                    onchange="this.form.submit()"
                                                >
                                                <label for="rating_all" class="filter-option-label">
                                                    <span class="filter-option-text">Semua Rating</span>
                                                </label>
                                            </div>
                                            <?php 
                                            $ratings = [
                                                '4.5' => '4.5+ (Excellent)',
                                                '4.0' => '4.0+ (Very Good)', 
                                                '3.5' => '3.5+ (Good)',
                                                '3.0' => '3.0+ (Average)'
                                            ];
                                            foreach ($ratings as $value => $label):
                                            ?>
                                            <div class="filter-option-checkbox">
                                                <input 
                                                    type="radio" 
                                                    name="rating" 
                                                    id="rating_<?php echo str_replace('.', '_', $value); ?>"
                                                    value="<?php echo $value; ?>"
                                                    <?php echo $rating_filter === $value ? 'checked' : ''; ?>
                                                    onchange="this.form.submit()"
                                                >
                                                <label for="rating_<?php echo str_replace('.', '_', $value); ?>" class="filter-option-label">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <span class="filter-option-text"><?php echo $label; ?></span>
                                                        <div class="rating-stars">
                                                            <?php 
                                                            $starValue = floatval($value);
                                                            for ($i = 1; $i <= 5; $i++): 
                                                                if ($i <= floor($starValue)): ?>
                                                                    <i class="fas fa-star text-warning"></i>
                                                                <?php elseif ($i == ceil($starValue) && fmod($starValue, 1) >= 0.5): ?>
                                                                    <i class="fas fa-star-half-alt text-warning"></i>
                                                                <?php else: ?>
                                                                    <i class="far fa-star text-muted"></i>
                                                                <?php endif; ?>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Availability Filter (Hidden Input) -->
                    <input type="hidden" name="availability" id="availabilityInput" value="<?php echo htmlspecialchars($availability_filter); ?>">

                    <!-- Active Filters -->
                    <?php 
                    $active_filters = [];
                    if (!empty($search_query)) $active_filters[] = [
                        'name' => 'Pencarian', 
                        'value' => $search_query, 
                        'field' => 'search',
                        'color' => 'primary'
                    ];
                    if (!empty($category_filter)) $active_filters[] = [
                        'name' => 'Kategori', 
                        'value' => ucfirst($category_filter), 
                        'field' => 'category',
                        'color' => 'primary'
                    ];
                    if (!empty($experience_filter)) $active_filters[] = [
                        'name' => 'Pengalaman', 
                        'value' => isset($experience_labels[$experience_filter]) ? $experience_labels[$experience_filter] : ucfirst($experience_filter), 
                        'field' => 'experience',
                        'color' => 'warning'
                    ];
                    if (!empty($rating_filter)) $active_filters[] = [
                        'name' => 'Rating', 
                        'value' => $rating_filter . '+', 
                        'field' => 'rating',
                        'color' => 'success'
                    ];
                    if (!empty($availability_filter)) $active_filters[] = [
                        'name' => 'Status', 
                        'value' => $availability_filter == '1' ? 'Tersedia' : 'Sibuk', 
                        'field' => 'availability',
                        'color' => $availability_filter == '1' ? 'success' : 'secondary'
                    ];
                    ?>
                    
                    <?php if (count($active_filters) > 0): ?>
                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Filter Aktif:</h6>
                                <a href="find-creatives.php" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-times me-1"></i>Hapus Semua
                                </a>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($active_filters as $filter): ?>
                                    <div class="active-filter-tag bg-<?php echo $filter['color']; ?>-light text-<?php echo $filter['color']; ?>">
                                        <span>
                                            <strong><?php echo htmlspecialchars($filter['name']); ?>:</strong> 
                                            <?php echo htmlspecialchars($filter['value']); ?>
                                        </span>
                                        <a href="find-creatives.php?<?php 
                                            $params = $_GET;
                                            unset($params[$filter['field']]);
                                            echo http_build_query($params);
                                        ?>" class="remove-filter">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Creatives Section -->
            <div class="creatives-section">
                <div class="section-header">
                    <div class="d-flex align-items-center gap-3">
                        <h2 class="section-title">Daftar Kreator</h2>
                        <span class="creatives-count"><?php echo count($creatives); ?> Kreator</span>
                    </div>
                    <div class="sort-dropdown">
                        <select class="form-select form-select-sm" style="width: auto; border-radius: 20px;" id="sortSelect">
                            <option value="rating_desc">Rating Tertinggi</option>
                            <option value="projects_desc">Proyek Terbanyak</option>
                            <option value="rate_asc">Rate Terendah</option>
                            <option value="rate_desc">Rate Tertinggi</option>
                            <option value="newest">Terbaru</option>
                        </select>
                    </div>
                </div>

                <div class="creatives-grid" id="creativesContainer">
                    <?php if (count($creatives) > 0): ?>
                        <?php foreach ($creatives as $index => $creative): ?>
                            <?php 
                            // Parse skills
                            $skills_list = !empty($creative['skills']) ? explode(', ', $creative['skills']) : [];
                            $skills_list = array_slice($skills_list, 0, 5); // Limit to 5 skills
                            
                            // Get portfolios for this creative
                            $portfolios = $portfolios_by_creative[$creative['id']] ?? [];
                            
                            // Calculate experience tag
                            $experience_class = '';
                            $experience_label = '';
                            if (!empty($creative['experience_level'])) {
                                $experience_class = "experience-{$creative['experience_level']}";
                                $experience_label = isset($experience_labels[$creative['experience_level']]) ? 
                                                    $experience_labels[$creative['experience_level']] : 
                                                    ucfirst($creative['experience_level']);
                            }
                            
                            // Get rating stars
                            $rating = floatval($creative['rating'] ?? 0);
                            $full_stars = floor($rating);
                            $has_half_star = ($rating - $full_stars) >= 0.5;
                            $empty_stars = 5 - $full_stars - ($has_half_star ? 1 : 0);
                            
                            // Availability status
                            $is_available = (bool)($creative['is_available'] ?? false);
                            $availability_class = $is_available ? 'available' : 'busy';
                            $availability_text = $is_available ? 'Tersedia' : 'Sibuk';
                            ?>
                            
                            <div class="creative-card creative-item" 
                                 data-rating="<?php echo $rating; ?>"
                                 data-projects="<?php echo $creative['completed_projects'] ?? 0; ?>"
                                 data-rate="<?php echo $creative['hourly_rate'] ?? 0; ?>"
                                 data-date="<?php echo strtotime($creative['created_at']); ?>"
                                 style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                
                                <!-- Creative Header -->
                                <div class="creative-card-header">
                                    <div class="d-flex align-items-start gap-3">
                                        <!-- Avatar with Availability Indicator -->
                                        <div class="position-relative">
                                            <div class="creative-avatar">
                                                <?php if (!empty($creative['avatar_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($creative['avatar_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($creative['full_name']); ?>"
                                                         class="avatar-img">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder">
                                                        <?php echo strtoupper(substr($creative['full_name'], 0, 2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <!-- Availability Indicator -->
                                                <div class="availability-indicator <?php echo $availability_class; ?>"
                                                     title="<?php echo $availability_text; ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Info -->
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h3 class="creative-name mb-1"><?php echo htmlspecialchars($creative['full_name']); ?></h3>
                                                    <p class="creative-tagline mb-0"><?php echo htmlspecialchars($creative['tagline'] ?? 'Creative Worker'); ?></p>
                                                </div>
                                                <?php if (!empty($experience_class)): ?>
                                                    <span class="experience-tag <?php echo $experience_class; ?>">
                                                        <?php echo $experience_label; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Rating & Stats -->
                                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                                <div class="rating-display">
                                                    <i class="fas fa-star"></i>
                                                    <span><?php echo number_format($rating, 1); ?></span>
                                                    <span class="text-muted ms-1">(<?php echo $creative['completed_projects'] ?? 0; ?>)</span>
                                                </div>
                                                
                                                <?php if (!empty($creative['hourly_rate'])): ?>
                                                    <div class="stats-badge">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                        <span>Rp <?php echo number_format($creative['hourly_rate'], 0, ',', '.'); ?>/jam</span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Availability Status -->
                                                <div class="availability-status">
                                                    <span class="status-dot <?php echo $availability_class; ?>"></span>
                                                    <span><?php echo $availability_text; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Creative Body -->
                                <div class="creative-card-body">
                                    <!-- Bio -->
                                    <?php if (!empty($creative['bio'])): ?>
                                        <div class="creative-bio mb-3">
                                            <p class="mb-0"><?php echo htmlspecialchars(mb_strlen($creative['bio']) > 150 ? mb_substr($creative['bio'], 0, 150) . '...' : $creative['bio']); ?></p>
                                            <?php if (mb_strlen($creative['bio']) > 150): ?>
                                                <a href="view-creative-profile.php?id=<?php echo $creative['id']; ?>" class="text-primary small">
                                                    Baca selengkapnya
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Skills -->
                                    <?php if (count($skills_list) > 0): ?>
                                        <div class="mb-3">
                                            <h6 class="skills-label mb-2">Keahlian</h6>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($skills_list as $skill): ?>
                                                    <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count(explode(', ', $creative['skills'] ?? '')) > 5): ?>
                                                    <span class="skill-tag text-muted">
                                                        +<?php echo count(explode(', ', $creative['skills'])) - 5; ?> lainnya
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Portfolio -->
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Portofolio</h6>
                                            <?php if (!empty($portfolios)): ?>
                                                <span class="text-muted small"><?php echo count($portfolios); ?> karya</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="portfolio-grid">
                                            <?php if (!empty($portfolios)): ?>
                                                <?php foreach ($portfolios as $portfolio): ?>
                                                    <?php 
                                                    $image_urls = json_decode($portfolio['image_urls'] ?? '[]', true);
                                                    $first_image = !empty($image_urls) ? $image_urls[0] : null;
                                                    ?>
                                                    <div class="portfolio-item" 
                                                         onclick="showPortfolio(<?php echo htmlspecialchars(json_encode($portfolio)); ?>)">
                                                        <?php if ($first_image): ?>
                                                            <img src="<?php echo htmlspecialchars($first_image); ?>" 
                                                                 alt="<?php echo htmlspecialchars($portfolio['title']); ?>"
                                                                 loading="lazy">
                                                        <?php else: ?>
                                                            <div class="portfolio-placeholder">
                                                                <i class="fas fa-image"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="portfolio-overlay">
                                                            <i class="fas fa-expand"></i>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="col-12 text-center py-4">
                                                    <i class="fas fa-images fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted small mb-0">Belum ada portofolio</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Creative Footer -->
                                <div class="creative-card-footer">
                                    <div class="action-buttons">
                                        <a href="view-creative-profile.php?id=<?php echo $creative['id']; ?>" 
                                           class="action-btn primary">
                                            <i class="fas fa-eye me-1"></i>Lihat Profil
                                        </a>
                                        <a href="messages.php?to=<?php echo $creative['id']; ?>" 
                                           class="action-btn secondary">
                                            <i class="fas fa-comment me-1"></i>Kirim Pesan
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="empty-title">Tidak Ada Kreator Ditemukan</h3>
                            <p class="empty-description">
                                Maaf, tidak ada kreator yang sesuai dengan filter pencarian Anda. Coba gunakan filter yang berbeda atau bersihkan filter untuk melihat semua kreator.
                            </p>
                            <a href="find-creatives.php" class="btn btn-primary btn-lg px-5 rounded-pill">
                                <i class="fas fa-redo me-2"></i>Tampilkan Semua Kreator
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Portfolio Modal -->
    <div class="modal fade" id="portfolioModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Portofolio Detail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="portfolioCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner" id="carouselInner">
                            <!-- Carousel items will be added by JavaScript -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#portfolioCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#portfolioCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                    <div class="mt-3">
                        <h4 id="portfolioTitle"></h4>
                        <p id="portfolioDescription"></p>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted" id="portfolioDate"></small>
                            <a href="#" id="portfolioLink" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt me-1"></i>Lihat Proyek
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class EnhancedCreativesManager {
            constructor() {
                this.initFilters();
                this.initPortfolioViewer();
                this.initSorting();
                this.initSearch();
            }

            initFilters() {
                // Quick filter buttons
                window.setAvailability = function(status) {
                    document.getElementById('availabilityInput').value = status;
                    document.getElementById('searchForm').submit();
                };

                window.setRating = function(rating) {
                    const ratingInput = document.querySelector('input[name="rating"][value="' + rating + '"]');
                    if (ratingInput) {
                        ratingInput.checked = true;
                        document.getElementById('searchForm').submit();
                    }
                };

                // Clear all filters
                const clearAllBtn = document.querySelector('a[href="find-creatives.php"]');
                if (clearAllBtn) {
                    clearAllBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        window.location.href = 'find-creatives.php';
                    });
                }
            }

            initPortfolioViewer() {
                // Portfolio modal functionality
                window.showPortfolio = function(portfolio) {
                    const modal = new bootstrap.Modal(document.getElementById('portfolioModal'));
                    
                    // Populate modal
                    document.getElementById('portfolioTitle').textContent = portfolio.title;
                    document.getElementById('portfolioDescription').textContent = portfolio.description || 'Tidak ada deskripsi';
                    document.getElementById('portfolioDate').textContent = portfolio.project_date 
                        ? `Dibuat: ${new Date(portfolio.project_date).toLocaleDateString('id-ID')}`
                        : 'Tanggal tidak tersedia';
                    
                    const portfolioLink = document.getElementById('portfolioLink');
                    if (portfolio.project_url) {
                        portfolioLink.href = portfolio.project_url;
                        portfolioLink.style.display = 'inline-block';
                    } else {
                        portfolioLink.style.display = 'none';
                    }

                    // Carousel images
                    const carouselInner = document.getElementById('carouselInner');
                    carouselInner.innerHTML = '';
                    
                    try {
                        const images = JSON.parse(portfolio.image_urls || '[]');
                        if (images.length > 0) {
                            images.forEach((img, index) => {
                                const item = document.createElement('div');
                                item.className = `carousel-item ${index === 0 ? 'active' : ''}`;
                                item.innerHTML = `<img src="${img}" class="d-block w-100" alt="Portfolio Image" style="max-height: 400px; object-fit: contain;">`;
                                carouselInner.appendChild(item);
                            });
                        } else {
                            carouselInner.innerHTML = `
                                <div class="carousel-item active">
                                    <div class="text-center py-5">
                                        <i class="fas fa-image fa-4x text-muted mb-3"></i>
                                        <p class="text-muted">Tidak ada gambar tersedia</p>
                                    </div>
                                </div>
                            `;
                        }
                    } catch (e) {
                        console.error('Error parsing portfolio images:', e);
                    }

                    modal.show();
                };
            }

            initSorting() {
                const sortSelect = document.getElementById('sortSelect');
                if (sortSelect) {
                    sortSelect.addEventListener('change', (e) => {
                        this.sortCreatives(e.target.value);
                    });
                }
            }

            initSearch() {
                const searchInput = document.querySelector('[name="search"]');
                if (searchInput) {
                    // Debounce search
                    let timeout;
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => {
                            if (e.target.value.length >= 3 || e.target.value.length === 0) {
                                e.target.form.submit();
                            }
                        }, 500);
                    });
                }
            }

            sortCreatives(sortBy) {
                const container = document.getElementById('creativesContainer');
                const items = Array.from(container.querySelectorAll('.creative-card'));
                
                items.sort((a, b) => {
                    const aData = a.dataset;
                    const bData = b.dataset;
                    
                    switch(sortBy) {
                        case 'rating_desc':
                            return parseFloat(bData.rating) - parseFloat(aData.rating);
                        case 'projects_desc':
                            return parseInt(bData.projects) - parseInt(aData.projects);
                        case 'rate_asc':
                            return parseFloat(aData.rate) - parseFloat(bData.rate);
                        case 'rate_desc':
                            return parseFloat(bData.rate) - parseFloat(aData.rate);
                        case 'newest':
                            return parseInt(bData.date) - parseInt(aData.date);
                        default:
                            return 0;
                    }
                });

                // Clear and re-add with animation
                container.innerHTML = '';
                items.forEach((item, index) => {
                    item.style.animationDelay = `${index * 0.05}s`;
                    container.appendChild(item);
                });

                // Show notification
                this.showNotification('Kreator berhasil diurutkan', 'success');
            }

            showNotification(message, type = 'info') {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                notification.style.cssText = `
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    animation: slideInRight 0.3s ease;
                    max-width: 300px;
                `;
                
                notification.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(notification);
                
                // Auto remove after 3 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.classList.remove('show');
                        setTimeout(() => notification.remove(), 300);
                    }
                }, 3000);
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new EnhancedCreativesManager();
            
            // Initialize collapse state based on active filters
            const activeFilters = [
                'categoryCollapse',
                'experienceCollapse', 
                'ratingCollapse'
            ];
            
            activeFilters.forEach(collapseId => {
                const element = document.getElementById(collapseId);
                if (element) {
                    const filterName = collapseId.replace('Collapse', '');
                    const hasFilter = document.querySelector(`[name="${filterName}"]:checked`);
                    if (hasFilter && hasFilter.value !== '') {
                        const collapse = new bootstrap.Collapse(element, {
                            toggle: false
                        });
                        collapse.show();
                    }
                }
            });
        });
    </script>
</body>
</html>