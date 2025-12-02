<?php
include "check_login.php";

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: find-creatives.php");
    exit;
}

$creative_id = intval($_GET['id']);

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// --- BACKEND LOGIC (Tetap dipertahankan karena sudah benar) ---

// Get creative profile
$sql = "
    SELECT 
        u.*,
        cp.*,
        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as skills
    FROM users u
    LEFT JOIN creative_profiles cp ON u.id = cp.user_id
    LEFT JOIN creative_skills cs ON cp.id = cs.creative_profile_id
    LEFT JOIN skills s ON cs.skill_id = s.id
    WHERE u.id = ? AND u.user_type = 'creative' AND u.is_active = 1
    GROUP BY u.id
";

$stmt = $conn->prepare($sql);
$stmt->execute([$creative_id]);
$creative = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$creative) {
    header("Location: find-creatives.php");
    exit;
}

// Get portfolio items
$portfolio_sql = "
    SELECT * FROM portfolios 
    WHERE creative_profile_id = ? AND is_public = 1 
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($portfolio_sql);
$stmt->execute([$creative['id']]);
$portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get reviews
$reviews_sql = "
    SELECT 
        r.*,
        u.full_name as reviewer_name,
        u.avatar_url as reviewer_avatar,
        p.title as project_title
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    JOIN contracts c ON r.contract_id = c.id
    JOIN projects p ON c.project_id = p.id
    WHERE r.reviewed_id = ? AND r.is_public = 1
    ORDER BY r.created_at DESC
    LIMIT 10
";
$stmt = $conn->prepare($reviews_sql);
$stmt->execute([$creative_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate rating stats
$rating_stats = [
    'total_reviews' => count($reviews),
    'avg_rating' => round($creative['rating'] ?? 0, 1),
    'rating_distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]
];

foreach ($reviews as $review) {
    if (isset($rating_stats['rating_distribution'][$review['rating']])) {
        $rating_stats['rating_distribution'][$review['rating']]++;
    }
}

// Get similar creators
$similar_sql = "
    SELECT DISTINCT u.id, u.full_name, u.avatar_url, cp.tagline, cp.rating
    FROM users u
    JOIN creative_profiles cp ON u.id = cp.user_id
    JOIN creative_skills cs ON cp.id = cs.creative_profile_id
    JOIN skills s ON cs.skill_id = s.id
    WHERE u.id != ? AND u.user_type = 'creative' AND u.is_active = 1
    AND s.name IN (
        SELECT s2.name 
        FROM creative_skills cs2
        JOIN skills s2 ON cs2.skill_id = s2.id
        WHERE cs2.creative_profile_id = ?
    )
    ORDER BY cp.rating DESC
    LIMIT 3
";
$stmt = $conn->prepare($similar_sql);
$stmt->execute([$creative_id, $creative['id']]);
$similar_creators = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($creative['full_name']); ?> - Profil Kreator | Konekin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    
    <style>
        :root {
            /* Modern Indigo-Violet Palette */
            --primary-600: #4F46E5; /* Indigo */
            --primary-500: #6366F1;
            --primary-50: #EEF2FF;
            
            --secondary-600: #7C3AED; /* Violet */
            --secondary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            
            --success-500: #10B981;
            --success-50: #ECFDF5;
            
            --warning-500: #F59E0B;
            
            --gray-900: #111827;
            --gray-600: #4B5563;
            --gray-500: #6B7280;
            --gray-50: #F9FAFB;
            
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            
            --border-radius: 16px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-600);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--gray-900);
            letter-spacing: -0.025em;
        }

        /* --- Header Section with Glassmorphism --- */
        .profile-hero {
            background: var(--secondary-gradient);
            padding: 8rem 0 5rem 0; /* Extra padding top for navbar space */
            position: relative;
            overflow: hidden;
            color: white;
            margin-bottom: 3rem;
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.08'%3E%3Cpath d='M50 50c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10c0 5.523-4.477 10-10 10s-10-4.477-10-10 4.477-10 10-10zM10 10c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10c0 5.523-4.477 10-10 10S0 25.523 0 20s4.477-10 10-10zm10 8c4.418 0 8-3.582 8-8s-3.582-8-8-8-8 3.582-8 8 3.582 8 8 8zm40 40c4.418 0 8-3.582 8-8s-3.582-8-8-8-8 3.582-8 8 3.582 8 8 8z' /%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .avatar-container {
            position: relative;
            width: 160px;
            height: 160px;
            margin: 0 auto;
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        /* Pulse Animation for Availability */
        .status-indicator {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .status-available { background: var(--success-500); }
        .status-busy { background: var(--gray-500); }

        .status-pulse::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: inherit;
            animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
            z-index: -1;
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.9); opacity: 1; }
            100% { transform: scale(2); opacity: 0; }
        }

        /* --- Cards & Components --- */
        .modern-card {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .modern-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
        }

        .card-header-clean {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-50);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-body-clean {
            padding: 1.5rem;
        }

        /* --- Badges & Tags --- */
        .skill-tag {
            background: var(--primary-50);
            color: var(--primary-600);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            border: 1px solid rgba(79, 70, 229, 0.1);
        }

        .skill-tag:hover {
            background: var(--primary-600);
            color: white;
            transform: scale(1.05);
        }

        /* --- Portfolio Grid --- */
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .portfolio-card {
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }

        .portfolio-img-wrapper {
            position: relative;
            padding-top: 66.67%; /* 3:2 Aspect Ratio */
            overflow: hidden;
        }

        .portfolio-img-wrapper img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .portfolio-card:hover .portfolio-img-wrapper img {
            transform: scale(1.1);
        }

        .portfolio-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 1.5rem;
            color: white;
        }

        .portfolio-card:hover .portfolio-overlay {
            opacity: 1;
        }

        /* --- Buttons --- */
        .btn-modern-primary {
            background: var(--secondary-gradient);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
            transition: all 0.3s ease;
        }

        .btn-modern-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
            color: white;
        }

        .btn-modern-outline {
            background: white;
            border: 2px solid var(--gray-200);
            color: var(--gray-600);
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-modern-outline:hover {
            border-color: var(--primary-600);
            color: var(--primary-600);
        }

        .btn-save-toggle.saved {
            background: #FCE7F3; /* Pink 100 */
            color: #EC4899; /* Pink 500 */
            border-color: #FBCFE8;
        }

        .btn-save-toggle.saved i {
            font-weight: 900;
            animation: heartBeat 1s;
        }

        /* --- Sticky Sidebar --- */
        .sticky-sidebar {
            position: sticky;
            top: 2rem;
            z-index: 10;
        }

        /* --- Modal Lightbox --- */
        .modal-fullscreen-img {
            max-height: 90vh;
            object-fit: contain;
        }

        /* --- Reviews --- */
        .review-item {
            border-bottom: 1px solid var(--gray-50);
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .review-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>

    <div class="profile-hero">
        <div class="container hero-content">
            <a href="find-creatives.php" class="text-white text-decoration-none mb-4 d-inline-block opacity-75 hover-opacity-100">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>

            <div class="row align-items-center text-center text-md-start">
                <div class="col-md-auto mb-4 mb-md-0">
                    <div class="avatar-container animate__animated animate__zoomIn">
                        <img src="<?php echo htmlspecialchars($creative['avatar_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($creative['full_name']) . '&background=random&color=fff&size=200'); ?>" 
                             alt="<?php echo htmlspecialchars($creative['full_name']); ?>"
                             class="profile-avatar">
                        
                        <div class="status-indicator <?php echo $creative['is_available'] ? 'status-available status-pulse' : 'status-busy'; ?>" 
                             title="<?php echo $creative['is_available'] ? 'Tersedia' : 'Sibuk'; ?>"
                             data-bs-toggle="tooltip">
                             <?php if($creative['is_available']): ?>
                                <i class="fas fa-check text-white" style="font-size: 12px;"></i>
                             <?php else: ?>
                                <i class="fas fa-times text-white" style="font-size: 12px;"></i>
                             <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md ms-md-4 animate__animated animate__fadeInRight">
                    <h1 class="display-4 fw-bold text-white mb-2"><?php echo htmlspecialchars($creative['full_name']); ?></h1>
                    <p class="h5 text-white opacity-90 mb-3 fw-light">
                        <?php echo htmlspecialchars($creative['tagline'] ?? 'Creative Professional'); ?>
                    </p>
                    
                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-md-start text-white opacity-90">
                        <?php if (!empty($creative['location'])): ?>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-map-marker-alt me-2 text-warning"></i>
                                <span><?php echo htmlspecialchars($creative['location']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex align-items-center">
                            <i class="fas fa-star me-2 text-warning"></i>
                            <span class="fw-bold"><?php echo number_format($rating_stats['avg_rating'], 1); ?></span>
                            <span class="ms-1">/ 5.0</span>
                        </div>

                        <div class="d-flex align-items-center">
                            <i class="fas fa-briefcase me-2 text-warning"></i>
                            <span><?php echo $creative['completed_projects'] ?? 0; ?> Proyek Selesai</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-auto mt-4 mt-md-0 animate__animated animate__fadeInLeft">
                    <div class="d-flex flex-column gap-3">
                        <a href="messages.php?to=<?php echo $creative['id']; ?>" 
                           class="btn btn-light rounded-pill px-4 fw-bold text-primary shadow-sm">
                            <i class="fas fa-paper-plane me-2"></i>Hire Me
                        </a>
                        <button onclick="toggleSave(<?php echo $creative['id']; ?>)" 
                                id="saveBtnHero"
                                class="btn btn-outline-light rounded-pill px-4 fw-bold">
                            <i class="far fa-heart me-2"></i>Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5" style="margin-top: -3rem;">
        <div class="row g-4">
            <div class="col-lg-8 animate__animated animate__fadeInUp">
                
                <div class="modern-card">
                    <div class="card-header-clean">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-user-circle me-2 text-primary"></i>Tentang Kreator</h5>
                    </div>
                    <div class="card-body-clean">
                        <?php if (!empty($creative['bio'])): ?>
                            <div class="lh-lg text-muted">
                                <?php echo nl2br(htmlspecialchars($creative['bio'])); ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted fst-italic py-3">Belum ada deskripsi.</p>
                        <?php endif; ?>

                        <?php if (!empty($creative['skills'])): ?>
                        <div class="mt-4 pt-4 border-top">
                            <h6 class="fw-bold mb-3 text-uppercase small text-muted ls-1">Keahlian</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php 
                                $skills = explode(', ', $creative['skills']);
                                foreach ($skills as $skill): 
                                ?>
                                    <span class="skill-tag">
                                        <?php echo htmlspecialchars(trim($skill)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-3 mt-5">
                    <h4 class="fw-bold mb-0">Portofolio Unggulan</h4>
                    <span class="badge bg-primary rounded-pill"><?php echo count($portfolios); ?></span>
                </div>

                <?php if (!empty($portfolios)): ?>
                    <div class="portfolio-grid mb-5">
                        <?php foreach ($portfolios as $portfolio): 
                            $image_urls = json_decode($portfolio['image_urls'] ?? '[]', true);
                            $first_image = !empty($image_urls) ? $image_urls[0] : null;
                            $desc_short = !empty($portfolio['description']) ? mb_substr($portfolio['description'], 0, 100) . '...' : '';
                        ?>
                        <div class="portfolio-card shadow-sm" 
                             onclick="openPortfolioModal('<?php echo htmlspecialchars(addslashes($portfolio['title'])); ?>', '<?php echo htmlspecialchars($first_image); ?>', '<?php echo htmlspecialchars(addslashes($portfolio['description'])); ?>', '<?php echo $portfolio['project_url']; ?>')">
                            <div class="portfolio-img-wrapper">
                                <?php if ($first_image): ?>
                                    <img src="<?php echo htmlspecialchars($first_image); ?>" alt="Project">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="portfolio-overlay">
                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($portfolio['title']); ?></h6>
                                    <small class="opacity-75">Klik untuk detail</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 modern-card">
                        <img src="assets/img/empty-portfolio.svg" width="120" class="mb-3 opacity-50" alt="No Data">
                        <h6 class="text-muted">Belum ada portofolio yang diunggah</h6>
                    </div>
                <?php endif; ?>

                <div class="modern-card mt-5">
                    <div class="card-header-clean">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold">Ulasan Klien</h5>
                            <span class="badge bg-warning text-dark rounded-pill"><?php echo number_format($rating_stats['avg_rating'], 1); ?></span>
                        </div>
                    </div>
                    <div class="card-body-clean">
                        <?php if (!empty($reviews)): ?>
                            <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($review['reviewer_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($review['reviewer_name'])); ?>" 
                                             class="rounded-circle me-3" width="40" height="40">
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($review['reviewer_name']); ?></h6>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($review['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    <div class="text-warning small">
                                        <?php for($i=1; $i<=5; $i++) echo ($i <= $review['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                    </div>
                                </div>
                                <div class="ps-5">
                                    <?php if($review['project_title']): ?>
                                        <div class="badge bg-light text-primary mb-2 border border-primary-subtle">
                                            Proyek: <?php echo htmlspecialchars($review['project_title']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <p class="mb-0 text-muted fst-italic">"<?php echo htmlspecialchars($review['review_text']); ?>"</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-muted mb-0">Belum ada ulasan.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="col-lg-4">
                <div class="sticky-sidebar">
                    <div class="modern-card animate__animated animate__fadeInUp animate__delay-1s">
                        <div class="card-body-clean">
                            <h6 class="text-uppercase text-muted fw-bold small mb-3">Tarif & Layanan</h6>
                            
                            <?php if (!empty($creative['hourly_rate'])): ?>
                                <div class="d-flex align-items-end mb-4">
                                    <h2 class="fw-bold text-primary mb-0">Rp <?php echo number_format($creative['hourly_rate'], 0, ',', '.'); ?></h2>
                                    <span class="text-muted ms-2 mb-2">/ jam</span>
                                </div>
                            <?php else: ?>
                                <h4 class="fw-bold text-success mb-4">Negotiable</h4>
                            <?php endif; ?>

                            <div class="d-grid gap-3">
                                <a href="create-project.php?creative_id=<?php echo $creative['id']; ?>" class="btn btn-modern-primary btn-lg">
                                    Ajak Kerjasama
                                </a>
                                <button onclick="toggleSave(<?php echo $creative['id']; ?>)" 
                                        id="saveBtnSidebar"
                                        class="btn btn-modern-outline btn-lg btn-save-toggle">
                                    <i class="far fa-heart me-2"></i>Simpan Profil
                                </button>
                                <a href="messages.php?to=<?php echo $creative['id']; ?>" class="btn btn-light text-primary fw-bold">
                                    <i class="fas fa-comment-alt me-2"></i>Chat Kreator
                                </a>
                            </div>

                            <div class="mt-4 pt-3 border-top">
                                <h6 class="text-muted small fw-bold mb-3">Social Media</h6>
                                <div class="d-flex justify-content-center gap-3">
                                    <?php if($creative['website_url']): ?>
                                        <a href="<?php echo htmlspecialchars($creative['website_url']); ?>" target="_blank" class="text-secondary fs-5"><i class="fas fa-globe"></i></a>
                                    <?php endif; ?>
                                    <?php if($creative['instagram_url']): ?>
                                        <a href="<?php echo htmlspecialchars($creative['instagram_url']); ?>" target="_blank" class="text-danger fs-5"><i class="fab fa-instagram"></i></a>
                                    <?php endif; ?>
                                    <?php if($creative['linkedin_url']): ?>
                                        <a href="<?php echo htmlspecialchars($creative['linkedin_url']); ?>" target="_blank" class="text-primary fs-5"><i class="fab fa-linkedin"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modern-card animate__animated animate__fadeInUp animate__delay-1s">
                        <div class="card-body-clean">
                            <h6 class="text-uppercase text-muted fw-bold small mb-3">Informasi Profesional</h6>
                            <ul class="list-unstyled mb-0">
                                <li class="d-flex align-items-center mb-3">
                                    <div class="bg-light rounded-circle p-2 me-3 text-primary">
                                        <i class="fas fa-layer-group"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">Level</small>
                                        <span class="fw-bold text-capitalize"><?php echo htmlspecialchars($creative['experience_level']); ?></span>
                                    </div>
                                </li>
                                <li class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-3 text-success">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">Bergabung</small>
                                        <span class="fw-bold"><?php echo date('F Y', strtotime($creative['created_at'])); ?></span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <?php if (!empty($similar_creators)): ?>
                    <div class="modern-card animate__animated animate__fadeInUp animate__delay-2s">
                        <div class="card-header-clean py-3">
                            <h6 class="mb-0 fw-bold">Mungkin Anda Suka</h6>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($similar_creators as $similar): ?>
                            <a href="view-creative-profile.php?id=<?php echo $similar['id']; ?>" class="list-group-item list-group-item-action d-flex align-items-center py-3 border-0">
                                <img src="<?php echo htmlspecialchars($similar['avatar_url'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($similar['full_name'])); ?>" class="rounded-circle me-3" width="40" height="40">
                                <div class="flex-grow-1 overflow-hidden">
                                    <h6 class="mb-0 text-truncate fw-bold"><?php echo htmlspecialchars($similar['full_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($similar['tagline'] ?? 'Creative'); ?></small>
                                </div>
                                <div class="text-warning small ms-2">
                                    <i class="fas fa-star"></i> <?php echo number_format($similar['rating'], 1); ?>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="portfolioModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 bg-transparent shadow-none">
                <div class="modal-body p-0 position-relative text-center">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3" data-bs-dismiss="modal" aria-label="Close"></button>
                    <img src="" id="modalImage" class="img-fluid rounded-3 shadow-lg modal-fullscreen-img bg-black" alt="">
                    <div class="bg-white rounded-bottom-3 p-4 text-start mx-auto mt-n2 position-relative" style="max-width: 100%;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="fw-bold mb-2" id="modalTitle">Project Title</h4>
                                <p class="text-muted mb-3" id="modalDesc">Description goes here...</p>
                            </div>
                            <a href="#" id="modalLink" target="_blank" class="btn btn-primary rounded-pill px-4">
                                <i class="fas fa-external-link-alt me-2"></i>Lihat Web
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="liveToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i> <span id="toastMessage">Action successful!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Portfolio Modal Logic
        function openPortfolioModal(title, imageSrc, description, url) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('modalDesc').textContent = description;
            
            const linkBtn = document.getElementById('modalLink');
            if(url) {
                linkBtn.href = url;
                linkBtn.style.display = 'inline-block';
            } else {
                linkBtn.style.display = 'none';
            }

            var myModal = new bootstrap.Modal(document.getElementById('portfolioModal'));
            myModal.show();
        }

        // Save Creative Logic with better feedback
        function toggleSave(creativeId) {
            const btnSidebar = document.getElementById('saveBtnSidebar');
            const btnHero = document.getElementById('saveBtnHero');
            const toastEl = document.getElementById('liveToast');
            const toast = new bootstrap.Toast(toastEl);

            // Optimistic UI Update
            const isSaved = btnSidebar.classList.contains('saved');
            const newState = !isSaved;

            // Function to update button UI
            const updateButtons = (active) => {
                const text = active ? '<i class="fas fa-heart me-2"></i>Disimpan' : '<i class="far fa-heart me-2"></i>Simpan Profil';
                const heroText = active ? '<i class="fas fa-heart me-2"></i>' : '<i class="far fa-heart me-2"></i>Simpan';
                
                if(btnSidebar) {
                    btnSidebar.innerHTML = text;
                    if(active) btnSidebar.classList.add('saved');
                    else btnSidebar.classList.remove('saved');
                }

                if(btnHero) {
                    btnHero.innerHTML = heroText;
                    if(active) {
                        btnHero.classList.remove('btn-outline-light');
                        btnHero.classList.add('btn-light', 'text-danger');
                    } else {
                        btnHero.classList.add('btn-outline-light');
                        btnHero.classList.remove('btn-light', 'text-danger');
                    }
                }
            };

            // Call API
            fetch('save-creative.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `creative_id=${creativeId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateButtons(newState); // Assuming simple toggle logic in backend, or check data.is_saved
                    
                    document.getElementById('toastMessage').textContent = newState ? 'Berhasil disimpan ke favorit!' : 'Dihapus dari favorit.';
                    toast.show();
                } else {
                    // Revert if error
                    updateButtons(isSaved);
                    alert('Gagal menyimpan: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                updateButtons(isSaved);
            });
        }
    </script>
</body>
</html>