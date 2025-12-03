<?php include "check_login.php"; ?>
<?php

if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit();
}

require_once "config/Database.php";

try {

    $user_id = $_SESSION['user_id'];
    $profile_query = "SELECT cp.*, u.full_name, u.email, u.phone, u.avatar_url 
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

    $skills_query = "SELECT cs.*, s.name, s.category 
                     FROM creative_skills cs 
                     JOIN skills s ON cs.skill_id = s.id 
                     WHERE cs.creative_profile_id = ?";
    $stmt = $conn->prepare($skills_query);
    $stmt->execute([$profile['id']]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $portfolio_query = "SELECT * FROM portfolios 
                        WHERE creative_profile_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 3";
    $stmt = $conn->prepare($portfolio_query);
    $stmt->execute([$profile['id']]);
    $portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats_query = "SELECT 
                    COUNT(DISTINCT p.id) as total_projects,
                    COUNT(DISTINCT r.id) as total_reviews,
                    COALESCE(AVG(r.rating), 0) as avg_rating
                    FROM creative_profiles cp
                    LEFT JOIN proposals pr ON pr.creative_user_id = cp.user_id
                    LEFT JOIN projects p ON pr.project_id = p.id AND p.status = 'completed'
                    LEFT JOIN contracts c ON c.proposal_id = pr.id AND c.status = 'completed'
                    LEFT JOIN reviews r ON r.contract_id = c.id AND r.reviewed_id = cp.user_id
                    WHERE cp.user_id = ?";
    $stmt = $conn->prepare($stats_query);
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <link href="assets/css/creative-profile.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include "dashboard-sidebar.php"; ?>
    <div class="main-content">
        <div class="profile-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <div class="avatar-container">
                            <?php if (!empty($profile['avatar_url'])): ?>
                                <img src="<?php echo htmlspecialchars($profile['avatar_url']); ?>" 
                                     alt="Avatar" class="avatar-img rounded-circle">
                            <?php else: ?>
                                <div class="avatar-placeholder rounded-circle">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <h1 class="display-5 mb-2"><?php echo htmlspecialchars($profile['full_name']); ?></h1>
                        <p class="lead mb-2"><?php echo htmlspecialchars($profile['tagline'] ?? 'Creative Worker'); ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($profile['location'] ?? 'Lokasi belum diatur'); ?>
                            </span>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-star me-1"></i>
                                <?php echo number_format($stats['avg_rating'], 1); ?> Rating
                            </span>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-check-circle me-1"></i>
                                <?php echo $stats['total_projects']; ?> Proyek Selesai
                            </span>
                            <a href="edit-profile.php" class="btn btn-light btn-sm text-primary">
                                <i class="fas fa-edit me-1"></i>Edit Profil
                            </a>
                        </div>
                        <div class="rating-stars mb-2">
                            <?php
                            $rating = $stats['avg_rating'];
                            $fullStars = floor($rating);
                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                            $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
                            
                            for ($i = 0; $i < $fullStars; $i++) {
                                echo '<i class="fas fa-star"></i> ';
                            }
                            if ($hasHalfStar) {
                                echo '<i class="fas fa-star-half-alt"></i> ';
                            }
                            for ($i = 0; $i < $emptyStars; $i++) {
                                echo '<i class="far fa-star"></i> ';
                            }
                            ?>
                            <span class="ms-1">(<?php echo $stats['total_reviews']; ?> ulasan)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
                            
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="stats-card">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-chart-line me-2 text-primary"></i>Statistik
                        </h5>   
                        <div class="row text-center">
                            <div class="col-4">
                                <h4 class="text-primary mb-1"><?php echo $stats['total_projects']; ?></h4>
                                <small class="text-muted">Proyek</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-success mb-1"><?php echo number_format($stats['avg_rating'], 1); ?></h4>
                                <small class="text-muted">Rating</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-info mb-1"><?php echo count($portfolios); ?></h4>
                                <small class="text-muted">Portofolio</small>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-envelope me-2 text-primary"></i>Informasi Kontak
                        </h5>
                        <div class="mb-2">
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($profile['email']); ?>
                        </div>
                        <?php if (!empty($profile['phone'])): ?>
                        <div class="mb-2">
                            <strong>Telepon:</strong><br>
                            <?php echo htmlspecialchars($profile['phone']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($profile['website_url'])): ?>
                        <div class="mb-2">
                            <strong>Website:</strong><br>
                            <a href="<?php echo htmlspecialchars($profile['website_url']); ?>" target="_blank">
                                <?php echo htmlspecialchars($profile['website_url']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

    
                    <div class="stats-card">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-share-alt me-2 text-primary"></i>Media Sosial
                        </h5>
                        <div class="d-flex gap-3">
                            <?php if (!empty($profile['linkedin_url'])): ?>
                                <a href="<?php echo htmlspecialchars($profile['linkedin_url']); ?>" 
                                target="_blank" class="text-decoration-none">
                                    <i class="fab fa-linkedin fa-2x text-primary"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($profile['instagram_url'])): ?>
                                <a href="<?php echo htmlspecialchars($profile['instagram_url']); ?>" 
                                target="_blank" class="text-decoration-none">
                                    <i class="fab fa-instagram fa-2x text-danger"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="stats-card position-relative">
                        <!-- <button class="btn btn-primary btn-sm btn-edit" onclick="editProfile()">
                            <i class="fas fa-edit me-1"></i>Edit
                        </button> -->
                        <h5 class="card-title mb-3">
                            <i class="fas fa-user me-2 text-primary"></i>Tentang Saya
                        </h5>
                        <?php if (!empty($profile['bio'])): ?>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
                        <?php else: ?>
                            <p class="card-text text-muted">Belum ada deskripsi tentang diri Anda. 
                                <a href="#" onclick="editProfile()">Tambahkan sekarang</a>.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="stats-card position-relative">

                        <h5 class="card-title mb-3">
                            <i class="fas fa-tools me-2 text-primary"></i>Skills & Keahlian
                        </h5>
                        <?php if (!empty($skills)): ?>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($skills as $skill): ?>
                                    <div class="skill-badge">
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                        <small class="text-muted">
                                            (<?php 
                                            $level = $skill['proficiency_level'] ?? 'basic';
                                            echo $level === 'basic' ? 'Pemula' : 
                                                 ($level === 'intermediate' ? 'Menengah' : 'Ahli'); 
                                            ?>)
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="card-text text-muted">Belum ada skills yang ditambahkan. 
                                <a href="#" onclick="editSkills()">Tambahkan sekarang</a>.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="stats-card position-relative">
                        <button class="btn btn-primary btn-sm btn-edit" onclick="window.location.href='portfolio.php'">
                            <i class="fas fa-edit me-1"></i>Kelola
                        </button>
                        <h5 class="card-title mb-3">
                            <i class="fas fa-images me-2 text-primary"></i>Portofolio Terbaru
                        </h5>
                        <?php if (!empty($portfolios)): ?>
                            <div class="row">
                                <?php foreach ($portfolios as $portfolio): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="portfolio-item">
                                            <?php 
                                            $image_urls = json_decode($portfolio['image_urls'] ?? '[]', true);
                                            $first_image = !empty($image_urls) ? $image_urls[0] : null;
                                            ?>
                                            <?php if ($first_image): ?>
                                                <img src="<?php echo htmlspecialchars($first_image); ?>" 
                                                     alt="<?php echo htmlspecialchars($portfolio['title']); ?>" 
                                                     class="portfolio-image">
                                            <?php elseif (!empty($portfolio['video_url'])): ?>
                                                <div class="portfolio-image bg-dark d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-play-circle text-white fa-3x"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="portfolio-image bg-light d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-image text-muted fa-3x"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="p-3">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($portfolio['title']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M Y', strtotime($portfolio['project_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="portfolio.php" class="btn btn-outline-primary">Lihat Semua Portofolio</a>
                            </div>
                        <?php else: ?>
                            <p class="card-text text-muted">Belum ada portofolio yang ditambahkan. 
                                <a href="portfolio.php">Tambahkan sekarang</a>.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="stats-card position-relative">
                        <button class="btn btn-primary btn-sm btn-edit" onclick="editProfile()">
                            <i class="fas fa-edit me-1"></i>Edit
                        </button>
                        <h5 class="card-title mb-3">
                            <i class="fas fa-briefcase me-2 text-primary"></i>Informasi Profesional
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <strong>Tingkat Pengalaman:</strong><br>
                                <?php 
                                $experience = $profile['experience_level'] ?? 'beginner';
                                echo $experience === 'beginner' ? 'Pemula' : 
                                     ($experience === 'intermediate' ? 'Menengah' : 'Ahli'); 
                                ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong>Tarif Per Jam:</strong><br>
                                <?php if (!empty($profile['hourly_rate'])): ?>
                                    Rp <?php echo number_format($profile['hourly_rate'], 0, ',', '.'); ?>/jam
                                <?php else: ?>
                                    Belum diatur
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong>Status Ketersediaan:</strong><br>
                                <span class="badge <?php echo $profile['is_available'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $profile['is_available'] ? 'Tersedia' : 'Tidak Tersedia'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editProfile() {
            window.location.href = 'edit-profile.php';
        }

        function editSkills() {
            window.location.href = 'edit-skills.php';
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>