<?php include "check_login.php"; 
if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

include_once "config/Database.php";
$database = new DatabaseConnection();
$conn = $database->getConnection();

$sql = "SELECT p.*, cp.tagline, cp.experience_level, cp.rating, cp.completed_projects
        FROM portfolios p 
        LEFT JOIN creative_profiles cp ON p.creative_profile_id = cp.id 
        WHERE cp.user_id = :user_id 
        ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$skills_sql = "SELECT s.name, cs.proficiency_level 
               FROM creative_skills cs 
               JOIN skills s ON cs.skill_id = s.id 
               JOIN creative_profiles cp ON cs.creative_profile_id = cp.id 
               WHERE cp.user_id = :user_id";
$skills_stmt = $conn->prepare($skills_sql);
$skills_stmt->bindParam(':user_id', $_SESSION['user_id']);
$skills_stmt->execute();
$skills = $skills_stmt->fetchAll(PDO::FETCH_ASSOC);

$profile_sql = "SELECT * FROM creative_profiles WHERE user_id = :user_id";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bindParam(':user_id', $_SESSION['user_id']);
$profile_stmt->execute();
$profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);

$user_sql = "SELECT full_name, avatar_url FROM users WHERE id = :user_id";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #549efeff;
            --primary-dark: #82a8db;
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
            padding-horizontal: 20px;
            padding-top: 10px;
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Hero Header */
        .portfolio-hero {
            background: linear-gradient(135deg, #549efeff 0%, #82a8db 100%);
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

        .portfolio-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .portfolio-hero-content {
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

        /* Profile Header */
        .profile-header-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            animation: slideInLeft 0.6s ease;
            border: 2px solid transparent;
            transition: var(--transition);
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .profile-header-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-right: 1.5rem;
        }

        .profile-avatar img {
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
            font-size: 2rem;
            font-weight: bold;
        }

        .experience-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-right: 0.75rem;
        }

        .experience-beginner {
            background: var(--success-light);
            color: var(--success-color);
        }

        .experience-intermediate {
            background: var(--warning-light);
            color: var(--warning-color);
        }

        .experience-expert {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .rating-display {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: var(--warning-light);
            border-radius: 50px;
            color: var(--warning-color);
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* Stats Cards */
        .stats-section {
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-sm);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary-color);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Skills Section */
        .skills-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            animation: slideInRight 0.6s ease;
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .skill-tag {
            padding: 0.5rem 1rem;
            background: var(--primary-light);
            color: var(--primary-color);
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .skill-tag:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(62, 127, 213, 0.3);
        }

        /* Portfolio Grid */
        .portfolio-section {
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

        .portfolio-count {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .portfolio-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Portfolio Card */
        .portfolio-card {
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

        .portfolio-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }

        .portfolio-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .portfolio-image {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
            overflow: hidden;
        }

        .portfolio-image::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.1) 100%);
        }

        .image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }

        .portfolio-card-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .portfolio-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }

        .portfolio-description {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1rem;
            flex: 1;
            font-size: 0.9rem;
        }

        .portfolio-skills {
            margin-bottom: 1rem;
        }

        .skill-mini {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--bg-light);
            color: var(--text-muted);
            border-radius: 15px;
            font-size: 0.75rem;
            margin: 0.15rem;
        }

        .portfolio-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }

        .portfolio-date {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .portfolio-status {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .status-public {
            background: var(--success-light);
            color: var(--success-color);
        }

        .status-private {
            background: var(--secondary-light);
            color: var(--secondary-color);
        }

        /* Portfolio Actions */
        .portfolio-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            opacity: 0;
            transition: var(--transition);
            display: flex;
            gap: 0.5rem;
            z-index: 2;
        }

        .portfolio-card:hover .portfolio-actions {
            opacity: 1;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: white;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            cursor: pointer;
        }

        .action-btn:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-md);
        }

        .action-btn.view { color: var(--primary-color); }
        .action-btn.edit { color: var(--warning-color); }
        .action-btn.delete { color: var(--danger-color); }

        /* Add Portfolio Card */
        .add-portfolio-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 2px dashed var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            cursor: pointer;
            transition: var(--transition);
            animation: fadeInUp 0.6s ease backwards;
        }

        .add-portfolio-card:hover {
            border-color: var(--primary-color);
            background: var(--primary-light);
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .add-portfolio-content {
            text-align: center;
            padding: 2rem;
        }

        .add-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--primary-color);
            font-size: 2rem;
            transition: var(--transition);
        }

        .add-portfolio-card:hover .add-icon {
            background: var(--primary-color);
            color: white;
            transform: rotate(90deg);
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

        /* CTA Button */
        .cta-button {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(62, 127, 213, 0.3);
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(62, 127, 213, 0.4);
            color: white;
        }

        /* Enhanced Modal Styles */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            padding: 1.5rem 2rem;
        }

        .modal-title {
            font-weight: 700;
        }

        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .form-control, .form-select {
            border-radius: var(--border-radius-sm);
            border: 2px solid var(--border-color);
            padding: 0.75rem 1rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(62, 127, 213, 0.25);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.5rem 2rem;
        }

        /* Floating Action Button */
        .floating-action-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            z-index: 1000;
            text-decoration: none;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .floating-action-btn:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: var(--shadow-xl);
            color: white;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .portfolio-hero {
                padding: 2rem 1.5rem;
                text-align: center;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                margin-right: 1rem;
                margin-bottom: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .section-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .portfolio-grid {
                grid-template-columns: 1fr;
            }
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

        /* View Portfolio Modal Styles */
        .portfolio-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.75rem;
            margin: 1rem 0;
        }

        .portfolio-image-thumb {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .portfolio-image-thumb:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        .skills-used-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <?php include "dashboard-sidebar.php"; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Hero Header -->
            <div class="portfolio-hero">
                <div class="portfolio-hero-content">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="hero-title">
                                <i class="fas fa-images me-3"></i>Portfolio Saya
                            </h1>
                            <p class="hero-subtitle">
                                Tampilkan karya terbaik Anda kepada UMKM. Portfolio yang menarik akan meningkatkan peluang Anda mendapatkan proyek.
                            </p>
                            <button class="cta-button" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                                <i class="fas fa-plus-circle me-2"></i>Tambah Portfolio Baru
                            </button>
                        </div>
                        <div class="col-lg-4 text-lg-end d-none d-lg-block">
                            <div class="position-relative" style="max-width: 300px; margin-left: auto;">
                                <div class="position-absolute top-0 start-0 w-100 h-100 bg-purple opacity-10 rounded-3"></div>
                                <div class="position-relative p-4 rounded-3">
                                    <h5 class="mb-2">🎨 Karya Terbaik</h5>
                                    <h2 class="mb-0"><?php echo count($portfolios); ?></h2>
                                    <small class="opacity-75">portfolio ditampilkan</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Header -->
            <div class="profile-header-card">
                <div class="row align-items-center">
                    <div class="col-md-auto">
                        <div class="profile-avatar">
                            <?php if (!empty($user['avatar_url'])): ?>
                                <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($user['full_name']); ?>">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="d-flex flex-column">
                            <h2 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($profile['tagline'] ?? 'Creative Worker'); ?></p>
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <?php if ($profile['experience_level']): ?>
                                    <?php 
                                    $experience_labels = [
                                        'beginner' => 'Pemula',
                                        'intermediate' => 'Menengah',
                                        'expert' => 'Expert'
                                    ];
                                    $experience_class = 'experience-' . $profile['experience_level'];
                                    ?>
                                    <span class="experience-badge <?php echo $experience_class; ?>">
                                        <i class="fas fa-chart-line me-2"></i>
                                        <?php echo $experience_labels[$profile['experience_level']] ?? ucfirst($profile['experience_level']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($profile['rating']): ?>
                                    <div class="rating-display">
                                        <i class="fas fa-star"></i>
                                        <span><?php echo number_format($profile['rating'], 1); ?></span>
                                        <span class="text-white opacity-75">(<?php echo $profile['completed_projects'] ?? 0; ?>)</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-auto">
                        <div class="d-flex flex-column align-items-end">
                            <div class="mb-2">
                                <a href="edit-profile.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-edit me-1"></i>Edit Profil
                                </a>
                            </div>
                            <small class="text-muted">Member sejak <?php echo date('M Y', strtotime($_SESSION['created_at'] ?? 'now')); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="stats-section">
                <div class="row mb-3">
                    <div class="col-12">
                        <h3 class="section-title mb-0">📊 Ringkasan Portfolio</h3>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($portfolios); ?></div>
                        <div class="stat-label">Total Portfolio</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $profile['completed_projects'] ?? 0; ?></div>
                        <div class="stat-label">Proyek Selesai</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($profile['rating'] ?? 0, 1); ?></div>
                        <div class="stat-label">Rating</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($skills); ?></div>
                        <div class="stat-label">Keahlian</div>
                    </div>
                </div>
            </div>

            <!-- Skills Section -->
            <div class="skills-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">💼 Keahlian Saya</h4>
                    <a href="profile.php#skills" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus me-1"></i>Tambah Keahlian
                    </a>
                </div>
                
                <?php if (!empty($skills)): ?>
                    <div class="skills-list">
                        <?php foreach($skills as $skill): ?>
                            <span class="skill-tag">
                                <?php echo htmlspecialchars($skill['name']); ?>
                                <small class="ms-1 opacity-75">(<?php echo ucfirst($skill['proficiency_level']); ?>)</small>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Belum ada keahlian yang ditambahkan.</p>
                        <a href="profile.php#skills" class="btn btn-sm btn-primary mt-2">
                            <i class="fas fa-plus me-1"></i>Tambah Keahlian
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Portfolio Section -->
            <div class="portfolio-section">
                <div class="section-header">
                    <div class="d-flex align-items-center gap-3">
                        <h2 class="section-title">Karya Portfolio</h2>
                        <span class="portfolio-count"><?php echo count($portfolios); ?> Portfolio</span>
                    </div>
                    <div class="sort-dropdown">
                        <select class="form-select form-select-sm" style="width: auto; border-radius: 20px;">
                            <option value="newest">Terbaru</option>
                            <option value="oldest">Terlama</option>
                            <option value="public">Publik</option>
                            <option value="private">Privat</option>
                        </select>
                    </div>
                </div>

                <div class="portfolio-grid" id="portfolioContainer">
                    <!-- Add Portfolio Card -->
                    <div class="add-portfolio-card" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                        <div class="add-portfolio-content">
                            <div class="add-icon">
                                <i class="fas fa-plus"></i>
                            </div>
                            <h4>Tambah Portfolio Baru</h4>
                            <p class="text-muted">Tampilkan karya terbaik Anda untuk menarik perhatian UMKM</p>
                        </div>
                    </div>

                    <!-- Portfolio Items -->
                    <?php if (count($portfolios) > 0): ?>
                        <?php foreach($portfolios as $index => $portfolio): 
                            $image_urls = json_decode($portfolio['image_urls'] ?? '[]', true);
                            $first_image = !empty($image_urls) ? $image_urls[0] : null;
                            $skills_used = json_decode($portfolio['skills_used'] ?? '[]', true);
                        ?>
                            <div class="portfolio-card" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                <!-- Portfolio Actions -->
                                <div class="portfolio-actions">
                                    <button class="action-btn view" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#viewPortfolioModal"
                                            onclick="viewPortfolio(<?php echo htmlspecialchars(json_encode($portfolio)); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editPortfolioModal"
                                            onclick="editPortfolio(<?php echo htmlspecialchars(json_encode($portfolio)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" 
                                            onclick="deletePortfolio(<?php echo $portfolio['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                
                                <!-- Portfolio Image -->
                                <div class="portfolio-image" style="background-image: url('<?php echo $first_image ? htmlspecialchars($first_image) : ''; ?>')">
                                    <?php if (!$first_image): ?>
                                        <div class="image-placeholder">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Portfolio Content -->
                                <div class="portfolio-card-body">
                                    <h3 class="portfolio-title" title="<?php echo htmlspecialchars($portfolio['title']); ?>">
                                        <?php echo htmlspecialchars($portfolio['title']); ?>
                                    </h3>
                                    
                                    <p class="portfolio-description">
                                        <?php echo htmlspecialchars(mb_strlen($portfolio['description']) > 120 ? mb_substr($portfolio['description'], 0, 120) . '...' : $portfolio['description']); ?>
                                    </p>
                                    
                                    <!-- Skills Used -->
                                    <?php if (!empty($skills_used)): ?>
                                        <div class="portfolio-skills">
                                            <?php foreach(array_slice($skills_used, 0, 3) as $skill): ?>
                                                <span class="skill-mini"><?php echo htmlspecialchars($skill); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($skills_used) > 3): ?>
                                                <span class="skill-mini">+<?php echo count($skills_used) - 3; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Portfolio Meta -->
                                    <div class="portfolio-meta">
                                        <div class="portfolio-date">
                                            <i class="far fa-calendar me-1"></i>
                                            <?php echo date('d M Y', strtotime($portfolio['project_date'] ?? $portfolio['created_at'])); ?>
                                        </div>
                                        <div class="portfolio-status <?php echo $portfolio['is_public'] ? 'status-public' : 'status-private'; ?>">
                                            <?php echo $portfolio['is_public'] ? 'Publik' : 'Privat'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (empty($portfolios)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-images"></i>
                        </div>
                        <h3 class="empty-title">Portfolio Masih Kosong</h3>
                        <p class="empty-description">
                            Portfolio adalah cara terbaik untuk menunjukkan keahlian Anda kepada UMKM. 
                            Mulai dengan menambahkan portfolio pertama Anda untuk meningkatkan peluang mendapatkan proyek.
                        </p>
                        <button class="cta-button" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                            <i class="fas fa-plus-circle me-2"></i>Tambah Portfolio Pertama
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Floating Action Button (Mobile) -->
    <button class="floating-action-btn d-md-none" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Modal Tambah Portfolio -->
    <div class="modal fade" id="addPortfolioModal" tabindex="-1" aria-labelledby="addPortfolioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="portfolio-action.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPortfolioModalLabel">
                            <i class="fas fa-plus-circle me-2"></i>Tambah Portfolio Baru
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Judul Portfolio</label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       placeholder="Contoh: Website E-commerce Toko Baju">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="project_date" class="form-label">Tanggal Proyek</label>
                                <input type="date" class="form-control" id="project_date" name="project_date">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required 
                                      placeholder="Jelaskan detail proyek, tantangan yang dihadapi, dan solusi yang diberikan..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="project_url" class="form-label">URL Proyek</label>
                                <input type="url" class="form-control" id="project_url" name="project_url" 
                                       placeholder="https://contoh-proyek.com">
                                <div class="form-text">Link langsung ke proyek (opsional)</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="video_url" class="form-label">URL Video</label>
                                <input type="url" class="form-control" id="video_url" name="video_url" 
                                       placeholder="https://youtube.com/watch?v=...">
                                <div class="form-text">Link video showcase (opsional)</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="skills_used" class="form-label">Keahlian yang Digunakan</label>
                            <input type="text" class="form-control" id="skills_used" name="skills_used" 
                                   placeholder="Web Design, UI/UX, React, Photography">
                            <div class="form-text">Pisahkan setiap keahlian dengan koma</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="images" class="form-label">Gambar Portfolio</label>
                            <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                            <div class="form-text">Unggah gambar karya Anda (maks. 5MB per gambar)</div>
                            
                            <div class="image-preview mt-2 d-none" id="imagePreview">
                                <div class="row g-2" id="previewContainer"></div>
                            </div>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="is_public" name="is_public" checked>
                            <label class="form-check-label" for="is_public">
                                Tampilkan portfolio secara publik
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan Portfolio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Portfolio -->
    <div class="modal fade" id="editPortfolioModal" tabindex="-1" aria-labelledby="editPortfolioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="portfolio-action.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPortfolioModalLabel">
                            <i class="fas fa-edit me-2"></i>Edit Portfolio
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="portfolio_id" id="edit_portfolio_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_title" class="form-label">Judul Portfolio</label>
                                <input type="text" class="form-control" id="edit_title" name="title" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_project_date" class="form-label">Tanggal Proyek</label>
                                <input type="date" class="form-control" id="edit_project_date" name="project_date">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_project_url" class="form-label">URL Proyek</label>
                                <input type="url" class="form-control" id="edit_project_url" name="project_url">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_video_url" class="form-label">URL Video</label>
                                <input type="url" class="form-control" id="edit_video_url" name="video_url">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_skills_used" class="form-label">Keahlian yang Digunakan</label>
                            <input type="text" class="form-control" id="edit_skills_used" name="skills_used">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_images" class="form-label">Gambar Tambahan</label>
                            <input type="file" class="form-control" id="edit_images" name="images[]" multiple accept="image/*">
                            <div class="form-text">Pilih gambar tambahan (gambar existing akan tetap tersimpan)</div>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="edit_is_public" name="is_public">
                            <label class="form-check-label" for="edit_is_public">
                                Tampilkan portfolio secara publik
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-2"></i>Update Portfolio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal View Portfolio -->
    <div class="modal fade" id="viewPortfolioModal" tabindex="-1" aria-labelledby="viewPortfolioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPortfolioTitle">Detail Portfolio</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="viewPortfolioContent">
                        <!-- Content will be loaded by JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    class PortfolioManager {
        constructor() {
            this.initEventListeners();
            this.initImagePreview();
            this.setupAnimations();
        }

        initEventListeners() {
            // Sort dropdown
            const sortSelect = document.querySelector('.sort-dropdown select');
            if (sortSelect) {
                sortSelect.addEventListener('change', (e) => this.sortPortfolios(e.target.value));
            }

            // Add portfolio card click
            document.querySelectorAll('.add-portfolio-card, .cta-button').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.showModal('addPortfolioModal');
                });
            });
        }

        initImagePreview() {
            const imageInput = document.getElementById('images');
            if (imageInput) {
                imageInput.addEventListener('change', (e) => this.previewImages(e.target.files));
            }
        }

        setupAnimations() {
            // Animate portfolio cards on scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.portfolio-card').forEach(card => {
                observer.observe(card);
            });
        }

        previewImages(files) {
            const previewContainer = document.getElementById('previewContainer');
            const imagePreview = document.getElementById('imagePreview');
            
            previewContainer.innerHTML = '';
            
            if (files.length > 0) {
                imagePreview.classList.remove('d-none');
                
                Array.from(files).slice(0, 5).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const col = document.createElement('div');
                            col.className = 'col-4 col-md-3';
                            col.innerHTML = `
                                <div class="position-relative">
                                    <img src="${e.target.result}" class="img-fluid rounded" alt="Preview" style="height: 100px; object-fit: cover;">
                                    <small class="text-muted d-block mt-1">${file.name.substring(0, 15)}...</small>
                                </div>
                            `;
                            previewContainer.appendChild(col);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            } else {
                imagePreview.classList.add('d-none');
            }
        }

        sortPortfolios(sortBy) {
            const container = document.getElementById('portfolioContainer');
            const items = Array.from(container.querySelectorAll('.portfolio-card:not(.add-portfolio-card)'));
            
            items.sort((a, b) => {
                const aDate = new Date(a.querySelector('.portfolio-date').textContent.replace(/[^\d]/g, ''));
                const bDate = new Date(b.querySelector('.portfolio-date').textContent.replace(/[^\d]/g, ''));
                
                switch(sortBy) {
                    case 'newest':
                        return bDate - aDate;
                    case 'oldest':
                        return aDate - bDate;
                    case 'public':
                        const aPublic = a.querySelector('.portfolio-status').textContent === 'Publik';
                        const bPublic = b.querySelector('.portfolio-status').textContent === 'Publik';
                        return bPublic - aPublic;
                    case 'private':
                        const aPrivate = a.querySelector('.portfolio-status').textContent === 'Privat';
                        const bPrivate = b.querySelector('.portfolio-status').textContent === 'Privat';
                        return bPrivate - aPrivate;
                    default:
                        return 0;
                }
            });

            // Clear and re-add with animation
            items.forEach(item => {
                container.appendChild(item);
            });

            this.showNotification('Portfolio berhasil diurutkan', 'success');
        }

        showModal(modalId) {
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            modal.show();
        }

        showNotification(message, type = 'info') {
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

    // Portfolio functions
    function viewPortfolio(portfolio) {
        document.getElementById('viewPortfolioTitle').textContent = portfolio.title;
        
        const contentDiv = document.getElementById('viewPortfolioContent');
        const skillsUsed = JSON.parse(portfolio.skills_used || '[]');
        const imageUrls = JSON.parse(portfolio.image_urls || '[]');
        
        let imagesHtml = '';
        if (imageUrls.length > 0) {
            imagesHtml = `
                <div class="portfolio-images-grid">
                    ${imageUrls.map((img, index) => `
                        <div>
                            <img src="${img}" class="portfolio-image-thumb" alt="Portfolio image ${index + 1}" 
                                 onclick="openLightbox('${img}')">
                        </div>
                    `).join('')}
                </div>
            `;
        }
        
        contentDiv.innerHTML = `
            <div class="mb-4">
                <h6 class="text-muted mb-2">Deskripsi</h6>
                <p class="mb-0">${portfolio.description || 'Tidak ada deskripsi'}</p>
            </div>
            
            ${imagesHtml}
            
            ${portfolio.project_url ? `
                <div class="mb-3">
                    <h6 class="text-muted mb-2">URL Proyek</h6>
                    <p class="mb-0">
                        <a href="${portfolio.project_url}" target="_blank" class="text-primary">
                            <i class="fas fa-external-link-alt me-1"></i>${portfolio.project_url}
                        </a>
                    </p>
                </div>
            ` : ''}
            
            ${portfolio.video_url ? `
                <div class="mb-3">
                    <h6 class="text-muted mb-2">URL Video</h6>
                    <p class="mb-0">
                        <a href="${portfolio.video_url}" target="_blank" class="text-primary">
                            <i class="fab fa-youtube me-1"></i>${portfolio.video_url}
                        </a>
                    </p>
                </div>
            ` : ''}
            
            ${skillsUsed.length > 0 ? `
                <div class="mb-3">
                    <h6 class="text-muted mb-2">Keahlian yang Digunakan</h6>
                    <div class="skills-used-list">
                        ${skillsUsed.map(skill => `<span class="badge bg-primary">${skill}</span>`).join('')}
                    </div>
                </div>
            ` : ''}
            
            <div class="row text-muted small">
                <div class="col-md-6">
                    <i class="far fa-calendar me-1"></i>
                    <strong>Tanggal Proyek:</strong> ${portfolio.project_date ? new Date(portfolio.project_date).toLocaleDateString('id-ID') : 'Tidak ditentukan'}
                </div>
                <div class="col-md-6">
                    <i class="fas fa-eye me-1"></i>
                    <strong>Status:</strong> ${portfolio.is_public ? 'Publik' : 'Privat'}
                </div>
            </div>
        `;
    }
    
    function editPortfolio(portfolio) {
        document.getElementById('edit_portfolio_id').value = portfolio.id;
        document.getElementById('edit_title').value = portfolio.title;
        document.getElementById('edit_description').value = portfolio.description;
        document.getElementById('edit_project_url').value = portfolio.project_url || '';
        document.getElementById('edit_project_date').value = portfolio.project_date || '';
        document.getElementById('edit_video_url').value = portfolio.video_url || '';
        
        const skillsUsed = JSON.parse(portfolio.skills_used || '[]');
        document.getElementById('edit_skills_used').value = skillsUsed.join(', ');
        
        document.getElementById('edit_is_public').checked = portfolio.is_public == 1;
    }
    
    function deletePortfolio(portfolioId) {
        if (confirm('Apakah Anda yakin ingin menghapus portfolio ini?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'portfolio-action.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'portfolio_id';
            idInput.value = portfolioId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function openLightbox(imageUrl) {
        // Create lightbox
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            cursor: pointer;
        `;
        
        lightbox.innerHTML = `
            <div style="max-width: 90vw; max-height: 90vh;">
                <img src="${imageUrl}" style="max-width: 100%; max-height: 90vh; object-fit: contain;">
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; color: white; width: 40px; height: 40px; border-radius: 50%;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(lightbox);
        
        // Close on click outside image
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                lightbox.remove();
            }
        });
    }

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        const portfolioManager = new PortfolioManager();
        
        // Add animations to CSS
        if (!document.querySelector('style#dynamic-animations')) {
            const style = document.createElement('style');
            style.id = 'dynamic-animations';
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                
                .portfolio-card {
                    animation: fadeInUp 0.6s ease backwards paused;
                }
            `;
            document.head.appendChild(style);
        }
    });
    </script>
</body>
</html>