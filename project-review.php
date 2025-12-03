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

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

// Validasi akses dan status proyek
$sql = "
    SELECT 
        p.id as project_id,
        p.title as project_title,
        p.description as project_description,
        c.id as contract_id,
        c.title as contract_title,
        c.creative_user_id,
        c.umkm_user_id,
        c.status as contract_status,
        c.progress_percentage,
        c.payment_status,
        u.full_name as creative_name,
        u.avatar_url as creative_avatar,
        cp.tagline as creative_tagline,
        cp.rating as creative_rating,
        cp.completed_projects as creative_completed,
        r.id as existing_review_id,
        r.rating as existing_rating,
        r.review_text as existing_review,
        r.created_at as review_created_at 
    FROM projects p
    JOIN contracts c ON p.id = c.project_id
    JOIN users u ON c.creative_user_id = u.id
    LEFT JOIN creative_profiles cp ON c.creative_user_id = cp.user_id
    LEFT JOIN reviews r ON c.id = r.contract_id AND r.review_type = 'umkm_to_creative'
    WHERE p.id = :project_id 
        AND p.umkm_user_id = :user_id
        AND c.status = 'completed'
    ORDER BY c.created_at DESC
    LIMIT 1
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':project_id' => $project_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $project = $result;
        $contract = $result;
        $contract_id = $result['contract_id']; // Ambil contract_id dari hasil query
        $has_reviewed = !empty($result['existing_review_id']);
    }
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

// Redirect jika tidak ada akses
if (!$project || !$contract) {
    header("Location: projects.php");
    exit;
}

// Proses form review
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$has_reviewed) {
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    // Validasi
    if ($rating < 1 || $rating > 5) {
        $error = 'Rating harus antara 1-5 bintang';
    } elseif (strlen($review_text) < 10) {
        $error = 'Review harus minimal 10 karakter';
    } else {
        try {
            // Mulai transaksi
            $conn->beginTransaction();
            
            // Insert review
            $review_sql = "
                INSERT INTO reviews (
                    contract_id,
                    reviewer_id,
                    reviewed_id,
                    rating,
                    review_text,
                    review_type,
                    is_public,
                    created_at
                ) VALUES (
                    :contract_id,
                    :reviewer_id,
                    :reviewed_id,
                    :rating,
                    :review_text,
                    'umkm_to_creative',
                    :is_public,
                    NOW()
                )
            ";
            
            $stmt = $conn->prepare($review_sql);
            $stmt->execute([
                ':contract_id' => $contract_id,
                ':reviewer_id' => $_SESSION['user_id'],
                ':reviewed_id' => $contract['creative_user_id'],
                ':rating' => $rating,
                ':review_text' => $review_text,
                ':is_public' => $is_public
            ]);
            
            // Update rating creative worker
            // 1. Hitung rata-rata rating baru
            $rating_sql = "
                SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                FROM reviews 
                WHERE reviewed_id = :creative_id 
                    AND review_type = 'umkm_to_creative'
                    AND is_public = 1
            ";
            
            $stmt = $conn->prepare($rating_sql);
            $stmt->execute([':creative_id' => $contract['creative_user_id']]);
            $rating_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 2. Update creative profile
            $update_creative_sql = "
                UPDATE creative_profiles 
                SET rating = :rating,
                    completed_projects = completed_projects + 1
                WHERE user_id = :creative_id
            ";
            
            $stmt = $conn->prepare($update_creative_sql);
            $stmt->execute([
                ':rating' => round($rating_data['avg_rating'], 2),
                ':creative_id' => $contract['creative_user_id']
            ]);
            
            // Commit transaksi
            $conn->commit();
            
            $success = 'Review berhasil dikirim!';
            $has_reviewed = true;
            
            // Redirect setelah 3 detik
            header("Refresh: 3; URL=projects.php");
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beri Review - <?php echo htmlspecialchars($project['project_title']); ?> - Konekin</title>
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
            --border-radius: 12px;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: var(--text-dark);
            padding-top: 20px;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        .review-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .review-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-color);
        }
        
        .project-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .creative-card {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            background-color: white;
        }
        
        .rating-stars {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
        }
        
        .star {
            font-size: 2.5rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s, transform 0.2s;
        }
        
        .star:hover,
        .star.active {
            color: #FFC300;
            transform: scale(1.1);
        }
        
        .rating-label {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
        }
        
        .rating-description {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-align: center;
            margin-top: 10px;
        }
        
        textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        .char-counter {
            text-align: right;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 5px;
        }
        
        .alert {
            border-radius: var(--border-radius);
        }
        
        .thank-you-message {
            text-align: center;
            padding: 40px;
        }
        
        .thank-you-message i {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .review-preview {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 20px;
        }
        
        .review-preview .stars {
            color: #FFC300;
            font-size: 1.5rem;
        }
        
        .avatar-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-right: 20px;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    <div class="main-content">
        <div class="container">
            <div class="review-container">
                <?php if ($has_reviewed && empty($success)): ?>
                    <!-- Sudah memberikan review -->
                    <div class="review-card">
                        <div class="thank-you-message">
                            <i class="fas fa-check-circle"></i>
                            <h3>Anda Sudah Memberikan Review</h3>
                            <p class="text-muted">Terima kasih sudah memberikan review untuk proyek ini.</p>
                            
                            <div class="review-preview">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $project['existing_rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ms-2 fw-bold"><?php echo $project['existing_rating']; ?>/5</span>
                                    </div>
                                </div>
                                <p><?php echo nl2br(htmlspecialchars($project['existing_review'])); ?></p>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php 
                                   try {
                                    $review_date = new DateTime($project['review_created_at']);  // Changed from 'created_at' to 'review_created_at'
                                       echo 'Direview pada ' . $review_date->format('d M Y');
                                    } catch (Exception $e) {
                                        echo "Tanggal tidak valid";
                                    }  
                                    ?>
                                </small>
                            </div>
                            
                            <div class="mt-4">
                                <a href="projects.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali
                                </a>

                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($success): ?>
                    <!-- Berhasil memberikan review -->
                    <div class="review-card">
                        <div class="thank-you-message">
                            <i class="fas fa-check-circle"></i>
                            <h3>Review Berhasil Dikirim!</h3>
                            <p class="text-muted">Terima kasih atas review Anda. Review Anda membantu creative worker lain dan komunitas kami.</p>
                            <div class="mt-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Mengalihkan ke halaman proyek...</p>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Form review -->
                    <div class="project-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h1 class="h3 mb-2">
                                    <i class="fas fa-star me-2"></i>Beri Review
                                </h1>
                                <h2 class="h5 mb-0"><?php echo htmlspecialchars($project['project_title']); ?></h2>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success bg-opacity-75">Proyek Selesai</span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <div class="review-card">
                        <!-- Creative Worker Info -->
                        <div class="creative-card">
                            <div class="d-flex align-items-center">
                                <div class="avatar-placeholder">
                                    <?php 
                                    $initial = strtoupper(substr($project['creative_name'], 0, 1));
                                    echo $initial;
                                    ?>
                                </div>
                                <div>
                                    <h4 class="mb-1"><?php echo htmlspecialchars($project['creative_name']); ?></h4>
                                    <?php if ($project['creative_tagline']): ?>
                                        <p class="text-muted mb-1"><?php echo htmlspecialchars($project['creative_tagline']); ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex align-items-center">
                                        <div class="text-warning">
                                            <?php 
                                            $rating = $project['creative_rating'] ?? 0;
                                            for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= floor($rating) ? '' : ($i <= $rating + 0.5 ? '-half-alt' : ''); ?>"></i>
                                            <?php endfor; ?>
                                            <span class="ms-2"><?php echo number_format($rating, 1); ?></span>
                                        </div>
                                        <span class="mx-2">•</span>
                                        <span class="text-muted">
                                            <i class="fas fa-briefcase me-1"></i>
                                            <?php echo $project['creative_completed']; ?> proyek selesai
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" id="reviewForm">
                            <!-- Rating Section -->
                            <div class="mb-4">
                                <h5 class="mb-3">Bagaimana pengalaman Anda bekerja dengan <?php echo htmlspecialchars($project['creative_name']); ?>?</h5>
                                
                                <div class="rating-stars" id="ratingStars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star star" data-value="<?php echo $i; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                
                                <div class="rating-label">
                                    <span>Tidak Puas</span>
                                    <span>Sangat Puas</span>
                                </div>
                                
                                <div class="rating-description">
                                    <span id="ratingText">Pilih rating dengan mengklik bintang</span>
                                </div>
                                
                                <input type="hidden" name="rating" id="ratingValue" value="0" required>
                            </div>
                            
                            <!-- Review Text -->
                            <div class="mb-4">
                                <label for="review_text" class="form-label fw-bold">
                                    Ceritakan pengalaman Anda
                                </label>
                                <small class="text-muted d-block mb-2">
                                    Bagikan detail tentang:
                                    <ul>
                                        <li>Kualitas pekerjaan yang diterima</li>
                                        <li>Komunikasi selama proyek</li>
                                        <li>Ketepatan waktu penyelesaian</li>
                                        <li>Apakah Anda akan merekomendasikan creative worker ini?</li>
                                    </ul>
                                </small>
                                <textarea 
                                    class="form-control" 
                                    id="review_text" 
                                    name="review_text" 
                                    rows="5" 
                                    placeholder="Contoh: Sangat puas dengan hasilnya! Creative worker ini sangat profesional, komunikatif, dan mengerti kebutuhan saya. Hasil akhir melebihi ekspektasi dan selesai tepat waktu."
                                    required
                                ></textarea>
                                <div class="char-counter">
                                    <span id="charCount">0</span> / 500 karakter
                                </div>
                            </div>
                            
                            <!-- Privacy Settings -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_public" name="is_public" checked>
                                    <label class="form-check-label" for="is_public">
                                        Tampilkan review secara publik
                                        <small class="text-muted d-block">
                                            Review Anda akan ditampilkan di profil <?php echo htmlspecialchars($project['creative_name']); ?> 
                                            dan membantu UMKM lain dalam memilih creative worker.
                                        </small>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="d-flex justify-content-between pt-3 border-top">
                                <a href="projects.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali
                                </a>
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-paper-plane me-2"></i>Kirim Review
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Penting!</h6>
                        <ul class="mb-0">
                            <li>Review Anda sangat berharga untuk komunitas creative worker dan UMKM</li>
                            <li>Pastikan review Anda objektif dan berdasarkan fakta</li>
                            <li>Review tidak dapat diubah setelah dikirim</li>
                            <li>Administrator dapat menghapus review yang tidak pantas</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Rating stars functionality
            const stars = document.querySelectorAll('.star');
            const ratingValue = document.getElementById('ratingValue');
            const ratingText = document.getElementById('ratingText');
            
            const ratingDescriptions = {
                1: 'Sangat Tidak Puas - Pengalaman buruk',
                2: 'Tidak Puas - Ada banyak masalah',
                3: 'Cukup - Sesuai ekspektasi',
                4: 'Puas - Melebihi ekspektasi',
                5: 'Sangat Puas - Luar biasa!'
            };
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const value = parseInt(this.getAttribute('data-value'));
                    ratingValue.value = value;
                    
                    // Update stars
                    stars.forEach((s, index) => {
                        if (index < value) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                    
                    // Update description
                    ratingText.textContent = ratingDescriptions[value];
                });
                
                // Hover effect
                star.addEventListener('mouseover', function() {
                    const value = parseInt(this.getAttribute('data-value'));
                    stars.forEach((s, index) => {
                        if (index < value) {
                            s.style.color = '#FFC300';
                        }
                    });
                });
                
                star.addEventListener('mouseout', function() {
                    const currentValue = parseInt(ratingValue.value);
                    stars.forEach((s, index) => {
                        if (index >= currentValue) {
                            s.style.color = '';
                        }
                    });
                });
            });
            
            // Character counter for review text
            const reviewText = document.getElementById('review_text');
            const charCount = document.getElementById('charCount');
            
            reviewText.addEventListener('input', function() {
                const length = this.value.length;
                charCount.textContent = length;
                
                if (length > 500) {
                    this.value = this.value.substring(0, 500);
                    charCount.textContent = 500;
                    charCount.style.color = '#dc3545';
                } else if (length > 450) {
                    charCount.style.color = '#ffc107';
                } else {
                    charCount.style.color = '';
                }
            });
            
            // Form validation
            const form = document.getElementById('reviewForm');
            form.addEventListener('submit', function(e) {
                if (parseInt(ratingValue.value) === 0) {
                    e.preventDefault();
                    alert('Silakan berikan rating dengan memilih bintang');
                    return false;
                }
                
                if (reviewText.value.trim().length < 10) {
                    e.preventDefault();
                    alert('Review harus minimal 10 karakter');
                    return false;
                }
                
                // Confirm submission
                if (!confirm('Apakah Anda yakin ingin mengirim review? Review tidak dapat diubah setelah dikirim.')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>