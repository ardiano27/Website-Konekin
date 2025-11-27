<?php
include "check_login.php";
require_once 'config/Database.php';

$database = new DatabaseConnection();
$conn = $database->getConnection();

// Get creative worker ID from URL
$creative_id = isset($_GET['creative_id']) ? intval($_GET['creative_id']) : 0;

if ($creative_id === 0) {
    header("Location: find-projects.php");
    exit;
}

// Get creative worker details
$creative_sql = "
    SELECT 
        u.*,
        cp.*,
        COUNT(DISTINCT p.id) as portfolio_count,
        AVG(r.rating) as average_rating,
        COUNT(DISTINCT r.id) as review_count
    FROM users u
    LEFT JOIN creative_profiles cp ON u.id = cp.user_id
    LEFT JOIN portfolios p ON cp.id = p.creative_profile_id AND p.is_public = 1
    LEFT JOIN reviews r ON u.id = r.reviewed_id AND r.review_type = 'umkm_to_creative'
    WHERE u.id = :creative_id AND u.user_type = 'creative'
    GROUP BY u.id
";

$creative_stmt = $conn->prepare($creative_sql);
$creative_stmt->execute([':creative_id' => $creative_id]);
$creative = $creative_stmt->fetch(PDO::FETCH_ASSOC);

if (!$creative) {
    header("Location: find-projects.php");
    exit;
}

// Get creative skills
$skills_sql = "
    SELECT s.name, s.category, cs.proficiency_level, cs.years_of_experience
    FROM creative_skills cs
    JOIN skills s ON cs.skill_id = s.id
    WHERE cs.creative_profile_id = :profile_id
    ORDER BY cs.proficiency_level DESC, cs.years_of_experience DESC
";

$skills_stmt = $conn->prepare($skills_sql);
$skills_stmt->execute([':profile_id' => $creative['id']]);
$skills = $skills_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get portfolio items
$portfolio_sql = "
    SELECT *
    FROM portfolios
    WHERE creative_profile_id = :profile_id AND is_public = 1
    ORDER BY project_date DESC, created_at DESC
";

$portfolio_stmt = $conn->prepare($portfolio_sql);
$portfolio_stmt->execute([':profile_id' => $creative['id']]);
$portfolio_items = $portfolio_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent reviews
$reviews_sql = "
    SELECT r.*, u.full_name as reviewer_name, up.business_name
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    WHERE r.reviewed_id = :creative_id AND r.review_type = 'umkm_to_creative' AND r.is_public = 1
    ORDER BY r.created_at DESC
    LIMIT 5
";

$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->execute([':creative_id' => $creative_id]);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio - <?php echo htmlspecialchars($creative['full_name']); ?> - Konekin</title>
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
        
        .main-content {
            padding: 10px 10px 10px;
            margin-top: 0;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-medium);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .rating-stars {
            color: #FFC300;
            font-size: 1.2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .portfolio-card {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            background: white;
            box-shadow: var(--shadow-light);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        
        .portfolio-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }
        
        .portfolio-image {
            height: 200px;
            background-size: cover;
            background-position: center;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }
        
        .skill-tag {
            background-color: #f1f8ff;
            border: 1px solid #d0e7ff;
            color: var(--primary-color);
            border-radius: 20px;
            padding: 6px 15px;
            font-size: 0.85rem;
            margin-right: 8px;
            margin-bottom: 8px;
            display: inline-block;
            font-weight: 500;
        }
        
        .proficiency-badge {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 12px;
        }
        
        .proficiency-basic { background-color: #e3f2fd; color: #1976d2; }
        .proficiency-intermediate { background-color: #fff3e0; color: #f57c00; }
        .proficiency-advanced { background-color: #e8f5e8; color: #2e7d32; }
        
        .review-card {
            border-left: 4px solid var(--primary-color);
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--shadow-light);
        }
        
        .section-title {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 25px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .availability-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .available { background-color: #e8f5e8; color: #2e7d32; }
        .not-available { background-color: #ffebee; color: #c62828; }
        
        .experience-level {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .level-beginner { background-color: #e3f2fd; color: #1976d2; }
        .level-intermediate { background-color: #fff3e0; color: #f57c00; }
        .level-expert { background-color: #e8f5e8; color: #2e7d32; }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
            </div>
            
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <div class="profile-avatar me-4">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <h1 class="mb-2"><?php echo htmlspecialchars($creative['full_name']); ?></h1>
                                <?php if (!empty($creative['tagline'])): ?>
                                    <p class="lead mb-3"><?php echo htmlspecialchars($creative['tagline']); ?></p>
                                <?php endif; ?>
                                
                                <div class="d-flex flex-wrap align-items-center gap-3">
                                    <?php if ($creative['average_rating'] > 0): ?>
                                        <div class="rating-stars">
                                            <?php 
                                            $rating = round($creative['average_rating']);
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <i class="fas fa-star<?php echo $i <= $rating ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="ms-2"><?php echo number_format($creative['average_rating'], 1); ?> (<?php echo $creative['review_count']; ?> review)</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-white-50"><i class="fas fa-star me-1"></i>Belum ada rating</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($creative['experience_level'])): ?>
                                        <span class="experience-level level-<?php echo $creative['experience_level']; ?>">
                                            <?php 
                                            $level_names = [
                                                'beginner' => 'Pemula',
                                                'intermediate' => 'Menengah', 
                                                'expert' => 'Expert'
                                            ];
                                            echo $level_names[$creative['experience_level']] ?? $creative['experience_level'];
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="availability-badge <?php echo $creative['is_available'] ? 'available' : 'not-available'; ?>">
                                        <i class="fas fa-circle me-1"></i>
                                        <?php echo $creative['is_available'] ? 'Tersedia' : 'Tidak Tersedia'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-grid gap-2">
                            <a href="messages.php?user_id=<?php echo $creative_id; ?>" class="btn btn-light">
                                <i class="fas fa-envelope me-2"></i>Kirim Pesan
                            </a>
                            <?php if ($_SESSION['user_type'] === 'umkm'): ?>
                                <a href="find-projects.php" class="btn btn-outline-light">
                                    <i class="fas fa-briefcase me-2"></i>Ajukan Proyek
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Section -->
            <div class="row mb-5">
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $creative['portfolio_count']; ?></div>
                        <div class="stats-label">Proyek di Portfolio</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $creative['completed_projects'] ?? 0; ?></div>
                        <div class="stats-label">Proyek Selesai</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($skills); ?></div>
                        <div class="stats-label">Keterampilan</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $creative['review_count'] ?? 0; ?></div>
                        <div class="stats-label">Ulasan</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Left Column - About & Skills -->
                <div class="col-lg-4 mb-4">
                    <!-- About Section -->
                    <?php if (!empty($creative['bio'])): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="section-title">Tentang Saya</h5>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($creative['bio'])); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Contact & Info -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="section-title">Informasi</h5>
                            <div class="mb-3">
                                <strong><i class="fas fa-map-marker-alt me-2 text-primary"></i>Lokasi:</strong><br>
                                <span class="text-muted"><?php echo !empty($creative['location']) ? htmlspecialchars($creative['location']) : 'Tidak ditentukan'; ?></span>
                            </div>
                            
                            <?php if (!empty($creative['hourly_rate'])): ?>
                                <div class="mb-3">
                                    <strong><i class="fas fa-money-bill-wave me-2 text-primary"></i>Rate Per Jam:</strong><br>
                                    <span class="text-muted">Rp <?php echo number_format($creative['hourly_rate'], 0, ',', '.'); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Social Links -->
                            <div class="mb-3">
                                <strong><i class="fas fa-share-alt me-2 text-primary"></i>Tautan:</strong><br>
                                <div class="mt-2">
                                    <?php if (!empty($creative['website_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($creative['website_url']); ?>" target="_blank" class="btn btn-outline-primary btn-sm me-2 mb-2">
                                            <i class="fas fa-globe"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($creative['linkedin_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($creative['linkedin_url']); ?>" target="_blank" class="btn btn-outline-primary btn-sm me-2 mb-2">
                                            <i class="fab fa-linkedin"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($creative['instagram_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($creative['instagram_url']); ?>" target="_blank" class="btn btn-outline-primary btn-sm me-2 mb-2">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Skills Section -->
                    <?php if (count($skills) > 0): ?>
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title">Keterampilan</h5>
                                <?php foreach ($skills as $skill): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold"><?php echo htmlspecialchars($skill['name']); ?></span>
                                            <span class="proficiency-badge proficiency-<?php echo $skill['proficiency_level']; ?>">
                                                <?php 
                                                $proficiency_names = [
                                                    'basic' => 'Dasar',
                                                    'intermediate' => 'Menengah',
                                                    'advanced' => 'Lanjutan'
                                                ];
                                                echo $proficiency_names[$skill['proficiency_level']] ?? $skill['proficiency_level'];
                                                ?>
                                            </span>
                                        </div>
                                        <?php if ($skill['years_of_experience']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $skill['years_of_experience']; ?> tahun pengalaman
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Column - Portfolio & Reviews -->
                <div class="col-lg-8">
                    <!-- Portfolio Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="section-title">Portfolio</h5>
                            
                            <?php if (count($portfolio_items) > 0): ?>
                                <div class="row">
                                    <?php foreach ($portfolio_items as $item): ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="portfolio-card">
                                                <!-- Portfolio Image/Video -->
                                                <div class="portfolio-image">
                                                    <?php 
                                                    $image_urls = json_decode($item['image_urls'] ?? '[]', true);
                                                    if (is_array($image_urls) && count($image_urls) > 0): 
                                                    ?>
                                                        <img src="<?php echo htmlspecialchars($image_urls[0]); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                    <?php elseif (!empty($item['video_url'])): ?>
                                                        <i class="fas fa-video fa-3x"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-image fa-3x"></i>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h6>
                                                    
                                                    <?php if (!empty($item['description'])): ?>
                                                        <p class="card-text text-muted small">
                                                            <?php 
                                                            $description = $item['description'];
                                                            echo htmlspecialchars(mb_strlen($description) > 100 ? mb_substr($description, 0, 100) . '...' : $description); 
                                                            ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Skills Used -->
                                                    <?php 
                                                    $skills_used = json_decode($item['skills_used'] ?? '[]', true);
                                                    if (is_array($skills_used) && count($skills_used) > 0):
                                                    ?>
                                                        <div class="mb-2">
                                                            <?php foreach (array_slice($skills_used, 0, 3) as $skill): ?>
                                                                <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                                            <?php endforeach; ?>
                                                            <?php if (count($skills_used) > 3): ?>
                                                                <span class="skill-tag">+<?php echo count($skills_used) - 3; ?> lainnya</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Project Links -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <?php if (!empty($item['project_url'])): ?>
                                                            <a href="<?php echo htmlspecialchars($item['project_url']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                                <i class="fas fa-external-link-alt me-1"></i>Lihat
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($item['project_date'])): ?>
                                                            <small class="text-muted">
                                                                <?php echo date('M Y', strtotime($item['project_date'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada portfolio yang ditampilkan</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Reviews Section -->
                    <?php if (count($reviews) > 0): ?>
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title">Ulasan Terbaru</h5>
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($review['business_name'] ?? $review['reviewer_name']); ?></h6>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('d M Y', strtotime($review['created_at'])); ?>
                                            </small>
                                        </div>
                                        <?php if (!empty($review['review_text'])): ?>
                                            <p class="mb-0">"<?php echo htmlspecialchars($review['review_text']); ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Portfolio image hover effect
        document.addEventListener('DOMContentLoaded', function() {
            const portfolioCards = document.querySelectorAll('.portfolio-card');
            portfolioCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>