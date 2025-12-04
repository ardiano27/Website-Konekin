<?php 
include "check_login.php";

if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit();
}

require_once "config/Database.php";

try {
    $user_id = $_SESSION['user_id'];
    
    // Query untuk data profil kreatif
    $profile_query = "SELECT cp.*, u.full_name, u.email, u.phone, u.avatar_url, u.background_url 
                      FROM creative_profiles cp 
                      JOIN users u ON cp.user_id = u.id 
                      WHERE cp.user_id = ?";
    $stmt = $conn->prepare($profile_query);
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        $insert_profile = "INSERT INTO creative_profiles (user_id) VALUES (?)";
        $stmt = $conn->prepare($insert_profile);
        $stmt->execute([$user_id]);
        
        $stmt = $conn->prepare($profile_query);
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Query untuk skills
    $skills_query = "SELECT cs.*, s.name, s.category 
                     FROM creative_skills cs 
                     JOIN skills s ON cs.skill_id = s.id 
                     WHERE cs.creative_profile_id = ? 
                     ORDER BY cs.proficiency_level DESC";
    $stmt = $conn->prepare($skills_query);
    $stmt->execute([$profile['id']]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk portfolio
    $portfolio_query = "SELECT * FROM portfolios 
                        WHERE creative_profile_id = ? 
                        AND is_public = 1
                        ORDER BY created_at DESC 
                        LIMIT 6";
    $stmt = $conn->prepare($portfolio_query);
    $stmt->execute([$profile['id']]);
    $portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk statistik
    $stats_query = "SELECT 
                    COUNT(DISTINCT p.id) as total_projects,
                    COUNT(DISTINCT r.id) as total_reviews,
                    COALESCE(AVG(r.rating), 0) as avg_rating,
                    COUNT(DISTINCT pr.id) as proposals_submitted
                    FROM creative_profiles cp
                    LEFT JOIN proposals pr ON pr.creative_user_id = cp.user_id
                    LEFT JOIN projects p ON pr.project_id = p.id AND p.status = 'completed'
                    LEFT JOIN contracts c ON c.proposal_id = pr.id AND c.status = 'completed'
                    LEFT JOIN reviews r ON r.contract_id = c.id AND r.reviewed_id = cp.user_id
                    WHERE cp.user_id = ?";
    $stmt = $conn->prepare($stats_query);
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Query untuk proyek aktif
    $active_projects_query = "SELECT COUNT(DISTINCT c.id) as active_projects
                             FROM contracts c
                             JOIN proposals p ON c.proposal_id = p.id
                             WHERE p.creative_user_id = ? 
                             AND c.status = 'active'";
    $stmt = $conn->prepare($active_projects_query);
    $stmt->execute([$user_id]);
    $active_projects = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Creative - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* ===== VARIABLES ===== */
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2E68B3;
            --primary-light: #82a8db;
            --secondary-color: #ff9500;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --gray-light: #e9ecef;
            --light-color: #f8f9fa;
            --white-color: #ffffff;
        }

        /* ===== BASE STYLES ===== */
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .creative-profile-wrapper {
            min-height: 100vh;
        }

        /* ===== PROFILE HEADER ===== */
        .profile-header-wrapper {
            position: relative;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            padding: 3rem 0;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .profile-header-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(62, 127, 213, 0.9) 0%, rgba(30, 60, 114, 0.9) 100%);
            z-index: 1;
        }

        .profile-header-content {
            position: relative;
            z-index: 2;
            color: var(--white-color);
        }

        .profile-avatar-container {
            position: relative;
            width: 160px;
            height: 160px;
            margin: 0 auto;
        }

        .profile-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 4px solid var(--white-color);
            border-radius: 50%;
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }

        .profile-avatar-placeholder {
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            border: 4px solid var(--white-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--white-color);
            backdrop-filter: blur(10px);
        }

        .avatar-status {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--white-color);
        }

        .avatar-status.available {
            background-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.3);
        }

        .avatar-status.busy {
            background-color: var(--danger-color);
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.3);
        }

        .profile-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .profile-tagline {
            font-size: 1.25rem;
            opacity: 0.9;
            margin: 0;
        }

        .btn-edit-profile {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .btn-edit-profile:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            color: white;
        }

        .rating-display {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stars {
            color: #ffc107;
            font-size: 1.1rem;
        }

        .rating-value {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .reviews-count {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .meta-items {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .profile-social-links {
            display: flex;
            gap: 15px;
            margin-top: 1.5rem;
        }

        .social-link {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .social-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            color: white;
        }

        /* ===== STAT CARDS ===== */
        .stat-card {
            background: var(--white-color);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            height: 100%;
            border-left: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .stat-card-success .stat-icon {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }

        .stat-card-info .stat-icon {
            background: linear-gradient(135deg, #17a2b8 0%, #117a8b 100%);
        }

        .stat-card-warning .stat-icon {
            background: linear-gradient(135deg, #ff9500 0%, #ff6b00 100%);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
        }

        .stat-label {
            color: var(--gray-color);
            margin: 0;
            font-size: 0.9rem;
        }

        /* ===== PROFILE SECTIONS ===== */
        .profile-section {
            background: var(--white-color);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .profile-section:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-light);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }

        .section-edit-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--gray-light);
            background: transparent;
            color: var(--gray-color);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .section-edit-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* ===== SKILLS SECTION ===== */
        .skills-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .skill-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .skill-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .skill-name {
            font-weight: 500;
            color: var(--dark-color);
        }

        .skill-level {
            font-size: 0.8rem;
            color: var(--gray-color);
            background: var(--gray-light);
            padding: 2px 8px;
            border-radius: 10px;
        }

        .skill-progress {
            height: 8px;
            background: var(--gray-light);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: 4px;
            transition: width 1s ease-in-out;
        }

        /* ===== PORTFOLIO SECTION ===== */
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .portfolio-card {
            border-radius: 12px;
            overflow: hidden;
            background: var(--white-color);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-light);
        }

        .portfolio-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .portfolio-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .portfolio-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .portfolio-card:hover .portfolio-image img {
            transform: scale(1.05);
        }

        .portfolio-content {
            padding: 1.5rem;
        }

        .portfolio-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark-color);
        }

        .portfolio-desc {
            font-size: 0.9rem;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .portfolio-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }

        .skill-tag {
            font-size: 0.75rem;
            background: var(--gray-light);
            color: var(--gray-color);
            padding: 4px 10px;
            border-radius: 12px;
        }

        /* ===== EMPTY STATES ===== */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-color);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-light);
        }

        .empty-state p {
            margin-bottom: 1.5rem;
        }

        /* ===== CONTACT INFO ===== */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 12px;
            border-radius: 12px;
            background: var(--light-color);
            transition: all 0.2s ease;
        }

        .contact-item:hover {
            background: var(--gray-light);
        }

        .contact-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .contact-details {
            flex: 1;
        }

        .contact-label {
            font-size: 0.8rem;
            color: var(--gray-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 2px;
        }

        .contact-value {
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 500;
            display: block;
        }

        .contact-value:hover {
            color: var(--primary-color);
        }

        /* ===== INFO CARDS ===== */
        .info-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--light-color);
            border-radius: 12px;
            border-left: 3px solid var(--primary-color);
            transition: all 0.2s ease;
        }

        .info-card:hover {
            background: var(--gray-light);
            transform: translateX(5px);
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        /* ===== ANIMATIONS ===== */
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

        .animate-section {
            animation: fadeInUp 0.6s ease-out;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .profile-name {
                font-size: 2rem;
            }
            
            .profile-avatar-container {
                width: 140px;
                height: 140px;
                margin-bottom: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .profile-header-wrapper {
                padding: 2rem 0;
            }
            
            .profile-name {
                font-size: 1.8rem;
            }
            
            .profile-tagline {
                font-size: 1.1rem;
            }
            
            .portfolio-grid {
                grid-template-columns: 1fr;
            }
            
            .meta-items {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .profile-name {
                font-size: 1.5rem;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include "dashboard-sidebar.php"; ?>
    
    <div class="main-content creative-profile-wrapper">
        <!-- Header Profil -->
        <div class="profile-header-wrapper" style="<?php echo !empty($profile['background_url']) ? 'background-image: url(\'' . htmlspecialchars($profile['background_url']) . '\'); background-size: cover;' : ''; ?>">
            <div class="profile-header-overlay"></div>
            <div class="container">
                <div class="profile-header-content">
                    <div class="row align-items-center">
                        <!-- Avatar -->
                        <div class="col-xl-2 col-lg-3 col-md-4">
                            <div class="profile-avatar-container animate__animated animate__fadeIn">
                                <?php if (!empty($profile['avatar_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($profile['avatar_url']); ?>" 
                                         alt="Avatar" class="profile-avatar-img">
                                    <div class="avatar-status <?php echo $profile['is_available'] ? 'available' : 'busy'; ?>"
                                         data-bs-toggle="tooltip" 
                                         title="<?php echo $profile['is_available'] ? 'Tersedia untuk proyek baru' : 'Sedang sibuk'; ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="profile-avatar-placeholder">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Informasi Profil -->
                        <div class="col-xl-10 col-lg-9 col-md-8">
                            <div class="profile-info animate__animated animate__fadeInUp">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h1 class="profile-name mb-2"><?php echo htmlspecialchars($profile['full_name']); ?></h1>
                                        <p class="profile-tagline mb-3"><?php echo htmlspecialchars($profile['tagline'] ?? 'Creative Professional'); ?></p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="edit-profile.php" class="btn btn-edit-profile">
                                            <i class="fas fa-edit me-2"></i>Edit Profil
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Rating dan Stats -->
                                <div class="profile-meta mb-3">
                                    <div class="rating-display mb-2">
                                        <div class="stars">
                                            <?php
                                            $rating = $stats['avg_rating'];
                                            $fullStars = floor($rating);
                                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                            $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
                                            
                                            for ($i = 0; $i < $fullStars; $i++) {
                                                echo '<i class="fas fa-star"></i>';
                                            }
                                            if ($hasHalfStar) {
                                                echo '<i class="fas fa-star-half-alt"></i>';
                                            }
                                            for ($i = 0; $i < $emptyStars; $i++) {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                            ?>
                                        </div>
                                        <span class="rating-value ms-2"><?php echo number_format($rating, 1); ?></span>
                                        <span class="reviews-count">(<?php echo $stats['total_reviews']; ?> ulasan)</span>
                                    </div>
                                    
                                    <div class="meta-items">
                                        <span class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($profile['location'] ?? 'Lokasi belum diatur'); ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-briefcase"></i>
                                            <?php echo $stats['total_projects']; ?> Proyek
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <?php echo $profile['experience_level'] === 'beginner' ? 'Pemula' : 
                                                   ($profile['experience_level'] === 'intermediate' ? 'Menengah' : 'Expert'); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Social Links -->
                                <div class="profile-social-links">
                                    <?php if (!empty($profile['website_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($profile['website_url']); ?>" 
                                           target="_blank" class="social-link" data-bs-toggle="tooltip" title="Website">
                                            <i class="fas fa-globe"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['linkedin_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($profile['linkedin_url']); ?>" 
                                           target="_blank" class="social-link" data-bs-toggle="tooltip" title="LinkedIn">
                                            <i class="fab fa-linkedin"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['instagram_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($profile['instagram_url']); ?>" 
                                           target="_blank" class="social-link" data-bs-toggle="tooltip" title="Instagram">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($profile['email']); ?>" 
                                           class="social-link" data-bs-toggle="tooltip" title="Email">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="container mt-4">
            <div class="row g-4">
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-primary animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo $stats['total_projects']; ?></h3>
                            <p class="stat-label">Proyek Selesai</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-success animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo number_format($stats['avg_rating'], 1); ?></h3>
                            <p class="stat-label">Rating Rata-rata</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-info animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo $active_projects['active_projects'] ?? 0; ?></h3>
                            <p class="stat-label">Proyek Aktif</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-warning animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo $stats['proposals_submitted']; ?></h3>
                            <p class="stat-label">Proposal Dikirim</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Konten Utama -->
        <div class="container mt-5">
            <div class="row g-4">
                <!-- Sidebar Kiri -->
                <div class="col-xl-4 col-lg-5">
                    <!-- Tentang Saya -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-user-circle me-2"></i>Tentang Saya
                            </h3>
                            <button class="section-edit-btn" onclick="editProfile()">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($profile['bio'])): ?>
                                <p class="profile-bio"><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    <p>Belum ada deskripsi tentang diri Anda.</p>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editProfile()">
                                        Tambahkan Sekarang
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Informasi Kontak -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-address-card me-2"></i>Informasi Kontak
                            </h3>
                        </div>
                        <div class="section-content">
                            <div class="contact-info">
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">Email</span>
                                        <a href="mailto:<?php echo htmlspecialchars($profile['email']); ?>" class="contact-value">
                                            <?php echo htmlspecialchars($profile['email']); ?>
                                        </a>
                                    </div>
                                </div>
                                
                                <?php if (!empty($profile['phone'])): ?>
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">Telepon</span>
                                        <a href="tel:<?php echo htmlspecialchars($profile['phone']); ?>" class="contact-value">
                                            <?php echo htmlspecialchars($profile['phone']); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['hourly_rate'])): ?>
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">Tarif Per Jam</span>
                                        <span class="contact-value">
                                            Rp <?php echo number_format($profile['hourly_rate'], 0, ',', '.'); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">Status</span>
                                        <span class="contact-value badge <?php echo $profile['is_available'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $profile['is_available'] ? 'Tersedia' : 'Tidak Tersedia'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Skills -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-tools me-2"></i>Keahlian
                            </h3>
                            <button class="section-edit-btn" onclick="editSkills()">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($skills)): ?>
                                <div class="skills-container">
                                    <?php foreach ($skills as $skill): 
                                        $proficiency = $skill['proficiency_level'] ?? 'basic';
                                        $width = $proficiency === 'basic' ? '60%' : 
                                                ($proficiency === 'intermediate' ? '80%' : '95%');
                                    ?>
                                        <div class="skill-item">
                                            <div class="skill-info">
                                                <span class="skill-name"><?php echo htmlspecialchars($skill['name']); ?></span>
                                                <span class="skill-level"><?php 
                                                    echo $proficiency === 'basic' ? 'Pemula' : 
                                                           ($proficiency === 'intermediate' ? 'Menengah' : 'Ahli'); 
                                                ?></span>
                                            </div>
                                            <div class="skill-progress">
                                                <div class="progress-bar" style="width: <?php echo $width; ?>"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-tools"></i>
                                    <p>Belum ada keahlian yang ditambahkan.</p>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editSkills()">
                                        Tambahkan Keahlian
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Konten Utama -->
                <div class="col-xl-8 col-lg-7">
                    <!-- Portfolio -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-images me-2"></i>Portfolio
                            </h3>
                            <div class="d-flex gap-2">
                                <a href="portfolio.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus me-1"></i>Tambah
                                </a>
                                <a href="portfolio.php" class="btn btn-sm btn-primary">
                                    Lihat Semua
                                </a>
                            </div>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($portfolios)): ?>
                                <div class="portfolio-grid">
                                    <?php foreach ($portfolios as $portfolio): 
                                        $image_urls = json_decode($portfolio['image_urls'] ?? '[]', true);
                                        $first_image = !empty($image_urls) ? $image_urls[0] : null;
                                        $skills_used = json_decode($portfolio['skills_used'] ?? '[]', true);
                                    ?>
                                        <div class="portfolio-card">
                                            <div class="portfolio-image">
                                                <?php if ($first_image): ?>
                                                    <img src="<?php echo htmlspecialchars($first_image); ?>" 
                                                         alt="<?php echo htmlspecialchars($portfolio['title']); ?>"
                                                         class="img-fluid">
                                                <?php elseif (!empty($portfolio['video_url'])): ?>
                                                    <div class="d-flex align-items-center justify-content-center bg-dark text-white h-100">
                                                        <i class="fas fa-play-circle fa-3x"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center bg-light text-muted h-100">
                                                        <i class="fas fa-image fa-3x"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="portfolio-content">
                                                <h5 class="portfolio-title"><?php echo htmlspecialchars($portfolio['title']); ?></h5>
                                                <p class="portfolio-desc text-muted">
                                                    <?php echo strlen($portfolio['description'] ?? '') > 100 ? 
                                                          substr($portfolio['description'], 0, 100) . '...' : 
                                                          ($portfolio['description'] ?? 'Tidak ada deskripsi'); ?>
                                                </p>
                                                <?php if (!empty($skills_used)): ?>
                                                    <div class="portfolio-skills">
                                                        <?php foreach (array_slice($skills_used, 0, 3) as $skill): ?>
                                                            <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($skills_used) > 3): ?>
                                                            <span class="skill-tag">+<?php echo count($skills_used) - 3; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <span class="text-muted">
                                                        <i class="far fa-calendar"></i>
                                                        <?php echo date('M Y', strtotime($portfolio['project_date'])); ?>
                                                    </span>
                                                    <?php if (!empty($portfolio['project_url'])): ?>
                                                        <a href="<?php echo htmlspecialchars($portfolio['project_url']); ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-external-link-alt me-1"></i>Live Demo
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-images"></i>
                                    <h5 class="mt-3">Belum ada portfolio</h5>
                                    <p class="mb-4">Tunjukkan karya terbaik Anda untuk menarik lebih banyak klien.</p>
                                    <a href="portfolio.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Tambah Portfolio Pertama
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Informasi Profesional -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-briefcase me-2"></i>Informasi Profesional
                            </h3>
                            <button class="section-edit-btn" onclick="editProfile()">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                        <div class="section-content">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Pengalaman</h6>
                                            <p class="mb-0 text-muted">
                                                <?php 
                                                $experience = $profile['experience_level'] ?? 'beginner';
                                                echo $experience === 'beginner' ? 'Pemula (0-2 tahun)' : 
                                                     ($experience === 'intermediate' ? 'Menengah (2-5 tahun)' : 'Expert (5+ tahun)'); 
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Tarif Per Jam</h6>
                                            <p class="mb-0 text-muted">
                                                <?php if (!empty($profile['hourly_rate'])): ?>
                                                    Rp <?php echo number_format($profile['hourly_rate'], 0, ',', '.'); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Belum diatur</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Ketersediaan</h6>
                                            <p class="mb-0">
                                                <span class="badge <?php echo $profile['is_available'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $profile['is_available'] ? 'Tersedia untuk proyek baru' : 'Tidak menerima proyek baru'; ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-tasks"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Proyek Selesai</h6>
                                            <p class="mb-0 text-muted"><?php echo $stats['total_projects']; ?> proyek berhasil</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-bolt me-2"></i>Aksi Cepat
                            </h3>
                        </div>
                        <div class="section-content">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <a href="projects.php" class="d-flex align-items-center p-3 bg-light rounded text-decoration-none text-dark hover-lift">
                                        <div class="bg-primary text-white rounded p-3 me-3">
                                            <i class="fas fa-search"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Cari Proyek</h6>
                                            <p class="text-muted mb-0">Temukan proyek yang cocok</p>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="edit-profile.php" class="d-flex align-items-center p-3 bg-light rounded text-decoration-none text-dark hover-lift">
                                        <div class="bg-primary text-white rounded p-3 me-3">
                                            <i class="fas fa-user-edit"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Edit Profil</h6>
                                            <p class="text-muted mb-0">Perbarui informasi Anda</p>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="portfolio.php" class="d-flex align-items-center p-3 bg-light rounded text-decoration-none text-dark hover-lift">
                                        <div class="bg-primary text-white rounded p-3 me-3">
                                            <i class="fas fa-plus-circle"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Tambah Portfolio</h6>
                                            <p class="text-muted mb-0">Tampilkan karya terbaru</p>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="messages.php" class="d-flex align-items-center p-3 bg-light rounded text-decoration-none text-dark hover-lift">
                                        <div class="bg-primary text-white rounded p-3 me-3">
                                            <i class="fas fa-comments"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Pesan</h6>
                                            <p class="text-muted mb-0">Lihat pesan terbaru</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk edit profil
        function editProfile() {
            window.location.href = 'edit-profile.php';
        }

        // Fungsi untuk edit skills
        function editSkills() {
            window.location.href = 'edit-skills.php';
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Animate progress bars
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });

            // Add hover-lift effect
            const hoverLiftElements = document.querySelectorAll('.hover-lift');
            hoverLiftElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                    this.style.transition = 'all 0.3s ease';
                });
                
                element.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Animate stat counters
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(value => {
                const target = parseInt(value.textContent);
                let current = 0;
                const increment = target / 30;
                
                const updateCounter = () => {
                    current += increment;
                    if (current < target) {
                        value.textContent = Math.floor(current);
                        setTimeout(updateCounter, 50);
                    } else {
                        value.textContent = target;
                    }
                };
                
                // Start animation when element is in viewport
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            updateCounter();
                            observer.unobserve(entry.target);
                        }
                    });
                });
                
                observer.observe(value);
            });
        });

        // Share profile function
        function shareProfile() {
            if (navigator.share) {
                navigator.share({
                    title: 'Profil Kreatif - Konekin',
                    text: 'Lihat profil kreatif saya di Konekin',
                    url: window.location.href,
                })
                .then(() => console.log('Berhasil membagikan'))
                .catch((error) => console.log('Error sharing:', error));
            } else {
                // Fallback for browsers that don't support Web Share API
                const text = "Lihat profil kreatif saya di Konekin: " + window.location.href;
                navigator.clipboard.writeText(text).then(() => {
                    alert('Link profil telah disalin ke clipboard!');
                });
            }
        }

        // Print profile function
        function printProfile() {
            window.print();
        }

        // Export profile as PDF (placeholder)
        function exportProfilePDF() {
            alert('Fitur export PDF akan segera hadir!');
        }

        // Toggle availability status
        function toggleAvailability() {
            const badge = document.querySelector('.availability-badge');
            if (badge) {
                const isAvailable = badge.classList.contains('bg-success');
                
                // In real implementation, this would be an AJAX call
                if (isAvailable) {
                    badge.classList.remove('bg-success');
                    badge.classList.add('bg-secondary');
                    badge.textContent = 'Tidak Tersedia';
                } else {
                    badge.classList.remove('bg-secondary');
                    badge.classList.add('bg-success');
                    badge.textContent = 'Tersedia';
                }
                
                // Show notification
                showNotification('Status ketersediaan berhasil diubah!');
            }
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>