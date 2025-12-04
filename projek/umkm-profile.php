<?php
include "check_login.php";

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit();
}

require_once "config/Database.php";

try {
    $user_id = $_SESSION['user_id'];
    
    // Query untuk data profil UMKM
    $profile_query = "SELECT up.*, u.full_name, u.email, u.phone, u.avatar_url, u.background_url 
                      FROM umkm_profiles up 
                      JOIN users u ON up.user_id = u.id 
                      WHERE up.user_id = ?";
    $stmt = $conn->prepare($profile_query);
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        $insert_profile = "INSERT INTO umkm_profiles (user_id, business_name) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_profile);
        $business_name = explode(' ', $_SESSION['full_name'])[0] . "'s Business";
        $stmt->execute([$user_id, $business_name]);
        
        $stmt = $conn->prepare($profile_query);
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Query untuk statistik UMKM
    $stats_query = "SELECT 
                    COUNT(DISTINCT p.id) as total_projects,
                    COUNT(DISTINCT c.id) as completed_contracts,
                    COUNT(DISTINCT pr.id) as total_proposals_received,
                    COUNT(DISTINCT r.id) as total_reviews,
                    COALESCE(AVG(r.rating), 0) as avg_rating
                    FROM umkm_profiles up
                    LEFT JOIN projects p ON p.umkm_user_id = up.user_id
                    LEFT JOIN contracts c ON c.umkm_user_id = up.user_id AND c.status = 'completed'
                    LEFT JOIN proposals pr ON pr.project_id = p.id
                    LEFT JOIN reviews r ON r.reviewer_id = up.user_id
                    WHERE up.user_id = ?
                    GROUP BY up.user_id";
    $stmt = $conn->prepare($stats_query);
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Query untuk proyek aktif
    $active_projects_query = "SELECT p.*, 
                             COUNT(DISTINCT pr.id) as proposal_count
                             FROM projects p
                             LEFT JOIN proposals pr ON pr.project_id = p.id
                             WHERE p.umkm_user_id = ? 
                             AND p.status IN ('open', 'in_progress')
                             GROUP BY p.id
                             ORDER BY p.created_at DESC
                             LIMIT 4";
    $stmt = $conn->prepare($active_projects_query);
    $stmt->execute([$user_id]);
    $active_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk kontrak terbaru
    $recent_contracts_query = "SELECT c.*, 
                              u.full_name as creative_name,
                              u.avatar_url as creative_avatar,
                              p.title as project_title
                              FROM contracts c
                              JOIN users u ON c.creative_user_id = u.id
                              JOIN projects p ON c.project_id = p.id
                              WHERE c.umkm_user_id = ?
                              ORDER BY c.created_at DESC
                              LIMIT 3";
    $stmt = $conn->prepare($recent_contracts_query);
    $stmt->execute([$user_id]);
    $recent_contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Data jenis bisnis untuk display
    $business_types = [
        'food' => 'Makanan & Minuman',
        'fashion' => 'Fashion',
        'craft' => 'Kerajinan',
        'service' => 'Jasa',
        'retail' => 'Ritel',
        'other' => 'Lainnya'
    ];

    // Data ukuran bisnis
    $business_sizes = [
        'micro' => 'Mikro (< 5 karyawan)',
        'small' => 'Kecil (5-19 karyawan)',
        'medium' => 'Menengah (20-99 karyawan)'
    ];

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
    <title>Profil UMKM - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== VARIABLES ===== */
        :root {
            --primary-color: #10B981;
            --primary-dark: #059669;
            --primary-light: #A7F3D0;
            --secondary-color: #F59E0B;
            --success-color: #28a745;
            --info-color: #3B82F6;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --dark-color: #1F2937;
            --gray-color: #6B7280;
            --gray-light: #F3F4F6;
            --light-color: #F9FAFB;
            --white-color: #ffffff;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .umkm-profile-wrapper {
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
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.9) 0%, rgba(5, 150, 105, 0.9) 100%);
            z-index: 1;
        }

        .profile-header-content {
            position: relative;
            z-index: 2;
            color: var(--white-color);
        }

        /* Foto profil bulat */
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
            background: var(--white-color);
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

        .business-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .business-tagline {
            font-size: 1.25rem;
            opacity: 0.9;
            margin: 0;
        }

        .business-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
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
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        }

        .stat-card-info .stat-icon {
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
        }

        .stat-card-warning .stat-icon {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
        }

        .stat-card-purple .stat-icon {
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
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

        /* ===== RATING STARS ===== */
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

        /* ===== BUSINESS INFO CARDS ===== */
        .info-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--gray-light);
            border-radius: 12px;
            border-left: 3px solid var(--primary-color);
            transition: all 0.2s ease;
            height: 100%;
        }

        .info-card:hover {
            background: #E5E7EB;
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

        /* ===== PROJECT CARDS ===== */
        .project-card {
            border-radius: 12px;
            overflow: hidden;
            background: var(--white-color);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-light);
            height: 100%;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .project-content {
            padding: 1.5rem;
        }

        .project-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark-color);
        }

        .project-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
        }

        /* ===== CONTRACT CARDS ===== */
        .contract-card {
            background: var(--gray-light);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
        }

        .contract-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active { background: #D1FAE5; color: #065F46; }
        .status-completed { background: #DBEAFE; color: #1E40AF; }
        .status-draft { background: #F3F4F6; color: #4B5563; }

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
            background: var(--gray-light);
            transition: all 0.2s ease;
        }

        .contact-item:hover {
            background: #E5E7EB;
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
            .business-name {
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
            
            .business-name {
                font-size: 1.8rem;
            }
            
            .business-tagline {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .business-name {
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
    
    <div class="main-content umkm-profile-wrapper">
        <!-- Header Profil -->
        <div class="profile-header-wrapper" style="<?php echo !empty($profile['background_url']) ? 'background-image: url(\'' . htmlspecialchars($profile['background_url']) . '\'); background-size: cover;' : ''; ?>">
            <div class="profile-header-overlay"></div>
            <div class="container">
                <div class="profile-header-content">
                    <div class="row align-items-center">
                        <!-- Foto Profil Bulat -->
                        <div class="col-xl-2 col-lg-3 col-md-4">
                            <div class="profile-avatar-container animate__animated animate__fadeIn">
                                <?php if (!empty($profile['avatar_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($profile['avatar_url']); ?>" 
                                         alt="Foto Profil" class="profile-avatar-img">
                                <?php elseif (!empty($profile['business_logo_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($profile['business_logo_url']); ?>" 
                                         alt="Logo Bisnis" class="profile-avatar-img">
                                <?php else: ?>
                                    <div class="profile-avatar-placeholder">
                                        <i class="fas fa-store"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Informasi Bisnis -->
                        <div class="col-xl-10 col-lg-9 col-md-8">
                            <div class="profile-info animate__animated animate__fadeInUp">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h1 class="business-name mb-2"><?php echo htmlspecialchars($profile['business_name']); ?></h1>
                                        <p class="business-tagline mb-3">
                                            <?php echo htmlspecialchars($profile['business_description'] ?? 'Bisnis UMKM Berkualitas'); ?>
                                        </p>
                                        <div class="d-flex gap-2 align-items-center">
                                            <?php if (!empty($profile['business_type'])): ?>
                                                <span class="business-badge">
                                                    <i class="fas fa-tag me-1"></i>
                                                    <?php echo $business_types[$profile['business_type']] ?? $profile['business_type']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['business_size'])): ?>
                                                <span class="business-badge">
                                                    <i class="fas fa-chart-bar me-1"></i>
                                                    <?php echo $business_sizes[$profile['business_size']] ?? $profile['business_size']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['established_year'])): ?>
                                                <span class="business-badge">
                                                    <i class="fas fa-calendar-alt me-1"></i>
                                                    Berdiri <?php echo $profile['established_year']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="edit-profile-umkm.php" class="btn btn-edit-profile">
                                            <i class="fas fa-edit me-2"></i>Edit Profil
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Rating dan Kontak -->
                                <div class="profile-meta mb-3">
                                    <div class="rating-display mb-2">
                                        <div class="stars">
                                            <?php
                                            $rating = $stats['avg_rating'] ?? 0;
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
                                        <span class="reviews-count">(<?php echo $stats['total_reviews'] ?? 0; ?> ulasan)</span>
                                    </div>
                                    
                                    <div class="meta-items">
                                        <?php if (!empty($profile['address'])): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($profile['address']); ?>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($profile['email'])): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($profile['email']); ?>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($profile['phone'])): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($profile['phone']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Social Links -->
                                <div class="profile-social-links">
                                    <?php if (!empty($profile['business_website'])): ?>
                                        <a href="<?php echo htmlspecialchars($profile['business_website']); ?>" 
                                           target="_blank" class="social-link" data-bs-toggle="tooltip" title="Website">
                                            <i class="fas fa-globe"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['whatsapp'])): ?>
                                        <a href="https://wa.me/<?php echo htmlspecialchars($profile['whatsapp']); ?>" 
                                           target="_blank" class="social-link" data-bs-toggle="tooltip" title="WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['instagram'])): ?>
                                        <a href="https://instagram.com/<?php echo htmlspecialchars($profile['instagram']); ?>" 
                                           target="_blank" class="social-link" data-bs-toggle="tooltip" title="Instagram">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['tokopedia'])): ?>
                                        <a href="<?php echo htmlspecialchars($profile['tokopedia']); ?>" 
                                           target="_blank" class="social-link" data-bs-toggle="tooltip" title="Tokopedia">
                                            <i class="fas fa-shopping-cart"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['shopee'])): ?>
                                        <a href="<?php echo htmlspecialchars($profile['shopee']); ?>" 
                                           target="_blank" class="social-link" data-bs-toggle="tooltip" title="Shopee">
                                            <i class="fas fa-store"></i>
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
                    <div class="stat-card stat-card-success animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo $stats['total_projects'] ?? 0; ?></h3>
                            <p class="stat-label">Total Proyek</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-info animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo $stats['completed_contracts'] ?? 0; ?></h3>
                            <p class="stat-label">Kontrak Selesai</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-warning animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></h3>
                            <p class="stat-label">Rating Rata-rata</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-purple animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo $stats['total_proposals_received'] ?? 0; ?></h3>
                            <p class="stat-label">Proposal Diterima</p>
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
                    <!-- Tentang Bisnis -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-info-circle me-2"></i>Tentang Bisnis
                            </h3>
                            <button class="section-edit-btn" onclick="editBusinessInfo()">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($profile['business_description'])): ?>
                                <p class="business-description"><?php echo nl2br(htmlspecialchars($profile['business_description'])); ?></p>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    <p>Belum ada deskripsi bisnis.</p>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editBusinessInfo()">
                                        Tambahkan Sekarang
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Detail Bisnis -->
                            <div class="row mt-4">
                                <?php if (!empty($profile['business_type'])): ?>
                                <div class="col-6 mb-3">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-tag"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Jenis Bisnis</h6>
                                            <p class="mb-0 text-muted">
                                                <?php echo $business_types[$profile['business_type']] ?? $profile['business_type']; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['business_size'])): ?>
                                <div class="col-6 mb-3">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Ukuran Bisnis</h6>
                                            <p class="mb-0 text-muted">
                                                <?php echo $business_sizes[$profile['business_size']] ?? $profile['business_size']; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['established_year'])): ?>
                                <div class="col-6 mb-3">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Tahun Berdiri</h6>
                                            <p class="mb-0 text-muted">
                                                <?php echo $profile['established_year']; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['tax_id'])): ?>
                                <div class="col-6 mb-3">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-file-invoice"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">NPWP</h6>
                                            <p class="mb-0 text-muted"><?php echo $profile['tax_id']; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
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
                                <?php if (!empty($profile['email'])): ?>
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">Email Bisnis</span>
                                        <a href="mailto:<?php echo htmlspecialchars($profile['email']); ?>" class="contact-value">
                                            <?php echo htmlspecialchars($profile['email']); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
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
                                
                                <?php if (!empty($profile['address'])): ?>
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">Alamat</span>
                                        <span class="contact-value">
                                            <?php echo htmlspecialchars($profile['address']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">Pemilik</span>
                                        <span class="contact-value">
                                            <?php echo htmlspecialchars($profile['full_name']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Konten Utama -->
                <div class="col-xl-8 col-lg-7">
                    <!-- Proyek Aktif -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-tasks me-2"></i>Proyek Aktif
                            </h3>
                            <div class="d-flex gap-2">
                                <a href="create-project.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus me-1"></i>Buat Proyek
                                </a>
                                <a href="projects.php" class="btn btn-sm btn-primary">
                                    Lihat Semua
                                </a>
                            </div>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($active_projects)): ?>
                                <div class="row g-3">
                                    <?php foreach ($active_projects as $project): 
                                        $status_colors = [
                                            'open' => 'bg-primary',
                                            'in_progress' => 'bg-warning',
                                            'completed' => 'bg-success',
                                            'cancelled' => 'bg-danger'
                                        ];
                                        $status_text = [
                                            'open' => 'Terbuka',
                                            'in_progress' => 'Dalam Pengerjaan',
                                            'completed' => 'Selesai',
                                            'cancelled' => 'Dibatalkan'
                                        ];
                                    ?>
                                        <div class="col-md-6">
                                            <div class="project-card">
                                                <div class="project-content">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <h5 class="project-title mb-2"><?php echo htmlspecialchars($project['title']); ?></h5>
                                                        <span class="badge <?php echo $status_colors[$project['status']] ?? 'bg-secondary'; ?>">
                                                            <?php echo $status_text[$project['status']] ?? $project['status']; ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-muted mb-3">
                                                        <?php echo strlen($project['description']) > 120 ? 
                                                            substr($project['description'], 0, 120) . '...' : 
                                                            $project['description']; ?>
                                                    </p>
                                                    <div class="project-meta">
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="far fa-calendar me-1"></i>
                                                                <?php echo date('d M Y', strtotime($project['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <div>
                                                            <span class="badge bg-light text-dark">
                                                                <i class="fas fa-paper-plane me-1"></i>
                                                                <?php echo $project['proposal_count'] ?? 0; ?> Proposal
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3">
                                                        <a href="project-detail.php?id=<?php echo $project['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary w-100">
                                                            <i class="fas fa-eye me-1"></i>Lihat Detail
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-tasks"></i>
                                    <h5 class="mt-3">Belum ada proyek aktif</h5>
                                    <p class="mb-4">Mulai buat proyek pertama Anda untuk menemukan kreator terbaik.</p>
                                    <a href="create-project.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Buat Proyek Pertama
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kontrak Terbaru -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-file-contract me-2"></i>Kontrak Terbaru
                            </h3>
                            <a href="contracts.php" class="btn btn-sm btn-primary">
                                Lihat Semua
                            </a>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($recent_contracts)): ?>
                                <div class="contracts-list">
                                    <?php foreach ($recent_contracts as $contract): 
                                        $status_colors = [
                                            'draft' => 'status-draft',
                                            'active' => 'status-active',
                                            'completed' => 'status-completed',
                                            'cancelled' => 'bg-danger text-white'
                                        ];
                                    ?>
                                        <div class="contract-card">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($contract['title']); ?></h6>
                                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($contract['project_title']); ?></p>
                                                </div>
                                                <span class="contract-status <?php echo $status_colors[$contract['status']] ?? 'status-draft'; ?>">
                                                    <?php echo ucfirst($contract['status']); ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($contract['creative_avatar'])): ?>
                                                        <img src="<?php echo htmlspecialchars($contract['creative_avatar']); ?>" 
                                                             class="rounded-circle me-2" width="24" height="24" alt="Creative">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" 
                                                             style="width: 24px; height: 24px;">
                                                            <i class="fas fa-user fa-sm"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($contract['creative_name']); ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <span class="badge bg-light text-dark">
                                                        Rp <?php echo number_format($contract['agreed_budget'], 0, ',', '.'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <?php if ($contract['timeline_days']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Timeline: <?php echo $contract['timeline_days']; ?> hari
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state py-4">
                                    <i class="fas fa-file-contract"></i>
                                    <p class="mb-4">Belum ada kontrak yang dibuat.</p>
                                </div>
                            <?php endif; ?>
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
                                    <a href="create-project.php" class="d-flex align-items-center p-3 bg-light rounded text-decoration-none text-dark hover-lift">
                                        <div class="bg-primary text-white rounded p-3 me-3">
                                            <i class="fas fa-plus-circle"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Buat Proyek Baru</h6>
                                            <p class="text-muted mb-0">Cari kreator untuk proyek Anda</p>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="edit-profile-umkm.php" class="d-flex align-items-center p-3 bg-light rounded text-decoration-none text-dark hover-lift">
                                        <div class="bg-primary text-white rounded p-3 me-3">
                                            <i class="fas fa-edit"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Edit Profil Bisnis</h6>
                                            <p class="text-muted mb-0">Perbarui informasi bisnis</p>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="contracts.php" class="d-flex align-items-center p-3 bg-light rounded text-decoration-none text-dark hover-lift">
                                        <div class="bg-primary text-white rounded p-3 me-3">
                                            <i class="fas fa-file-contract"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Kelola Kontrak</h6>
                                            <p class="text-muted mb-0">Lihat semua kontrak aktif</p>
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
                                            <p class="text-muted mb-0">Lihat pesan dari kreator</p>
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
        // Fungsi untuk edit profil bisnis
        function editBusinessInfo() {
            window.location.href = 'edit-profile-umkm.php';
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Add hover effects (tanpa real-time update)
            const hoverElements = document.querySelectorAll('.hover-lift');
            hoverElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                    this.style.transition = 'all 0.3s ease';
                });
                
                element.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });

        // Share business profile
        function shareBusiness() {
            if (navigator.share) {
                navigator.share({
                    title: 'Profil UMKM - Konekin',
                    text: 'Lihat profil bisnis kami di Konekin',
                    url: window.location.href,
                })
                .then(() => console.log('Berhasil membagikan'))
                .catch((error) => console.log('Error sharing:', error));
            } else {
                const text = "Lihat profil bisnis kami di Konekin: " + window.location.href;
                navigator.clipboard.writeText(text).then(() => {
                    showNotification('Link profil telah disalin ke clipboard!', 'success');
                });
            }
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const alertClass = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            };

            const notification = document.createElement('div');
            notification.className = `alert ${alertClass[type]} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>