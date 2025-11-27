<?php
session_start();
include "../config/Database.php";
include "../models/Review.php";
include "../models/Users.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'umkm') {
    header("Location: ../auth/login.php");
    exit;
}

$db = new Database();
$reviewModel = new Review($db);
$userModel = new Users($db);

$contractId = $_GET['contract_id'] ?? null;
$creativeId = $_GET['creative_id'] ?? null;

if (!$contractId || !$creativeId) {
    die("<div class='alert alert-danger'>Data kontrak tidak valid. Pastikan Anda mengakses dari halaman kontrak yang benar.</div>");
}

$creative = $userModel->getUserById($creativeId);
if (!$creative) {
    die("<div class='alert alert-danger'>Creative worker tidak ditemukan.</div>");
}

$existingReview = $reviewModel->getReviewByContract($contractId);
if ($existingReview) {
    header("Location: review-already-submitted.php");
    exit;
}

$contract = $db->query("SELECT * FROM contracts WHERE id = ?", [$contractId]);
if (empty($contract)) {
    die("<div class='alert alert-danger'>Kontrak tidak ditemukan.</div>");
}
$contract = $contract[0];

if ($contract['umkm_user_id'] != $_SESSION['user_id']) {
    die("<div class='alert alert-danger'>Anda tidak memiliki akses untuk memberikan review pada kontrak ini.</div>");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beri Review Creative Worker - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .rating-category-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .rating-category-card:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0,123,255,0.1);
            background: white;
        }
        
        .category-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .category-icon {
            font-size: 24px;
            margin-right: 12px;
            width: 40px;
            text-align: center;
        }
        
        .stars-rating {
            direction: rtl;
            display: inline-block;
            text-align: center;
            width: 100%;
        }
        
        .stars-rating input {
            display: none;
        }
        
        .stars-rating label {
            color: #ddd;
            font-size: 32px;
            padding: 0 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .stars-rating input:checked ~ label,
        .stars-rating label:hover,
        .stars-rating label:hover ~ label {
            color: #FFD700;
            text-shadow: 0 2px 4px rgba(255,215,0,0.3);
        }
        
        .stars-rating label:hover {
            transform: scale(1.2);
        }
        
        .overall-rating-display {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #dee2e6;
        }
        
        .overall-stars label {
            cursor: default;
            font-size: 36px;
        }
        
        .rating-text {
            display: block;
            margin-top: 15px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .rating-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .creative-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .recommendation-options .form-check-label {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin: 5px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
        }
        
        .recommendation-options .form-check-input:checked + .form-check-label {
            border-color: #28a745;
            background-color: #d4edda;
        }
        
        .recommendation-options .form-check-input:checked + .text-success {
            border-color: #28a745;
            background-color: #d4edda;
        }
        
        .recommendation-options .form-check-input:checked + .text-danger {
            border-color: #dc3545;
            background-color: #f8d7da;
        }
        
        .char-count {
            font-size: 0.85rem;
            text-align: right;
        }
        
        .char-count.warning {
            color: #ffc107;
            font-weight: 600;
        }
        
        .char-count.danger {
            color: #dc3545;
            font-weight: 700;
        }
        
        .project-details {
            background: #e7f3ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h4 class="mb-1"><i class="fas fa-star me-2"></i>Beri Review Creative Worker</h4>
                                <p class="mb-0 opacity-75">Bantu creative worker berkembang dengan feedback yang konstruktif dan jujur</p>
                            </div>
                            <div class="col-auto">
                                <img src="<?= htmlspecialchars($creative['avatar_url'] ?? '../assets/images/default-avatar.jpg') ?>" 
                                     alt="Avatar" class="rounded-circle" width="60" height="60" style="object-fit: cover;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if (isset($_SESSION['review_success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= $_SESSION['review_success']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['review_success']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['review_error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= $_SESSION['review_error']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['review_error']); ?>
                        <?php endif; ?>

                        <div class="project-details">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong><i class="fas fa-user me-2"></i>Creative Worker:</strong>
                                    <span class="ms-2"><?= htmlspecialchars($creative['full_name']) ?></span>
                                </div>
                                <div class="col-md-6">
                                    <strong><i class="fas fa-project-diagram me-2"></i>Proyek:</strong>
                                    <span class="ms-2"><?= htmlspecialchars($contract['title']) ?></span>
                                </div>
                            </div>
                        </div>

                        <form id="reviewForm" method="POST" action="../../controllers/ReviewController.php">
                            <input type="hidden" name="action" value="submit_review">
                            <input type="hidden" name="contract_id" value="<?= htmlspecialchars($contractId) ?>">
                            <input type="hidden" name="creative_id" value="<?= htmlspecialchars($creativeId) ?>">
                            
                            <div class="mb-5">
                                <h5 class="mb-3">📊 Rating Berdasarkan Kategori</h5>
                                <p class="text-muted mb-4">Berikan penilaian jujur untuk setiap aspek pekerjaan creative worker</p>
                                
                                <div class="rating-categories">
                                    <?php
                                    $categories = [
                                        'quality' => [
                                            'label' => 'Kualitas Pekerjaan', 
                                            'icon' => '🎯', 
                                            'desc' => 'Seberapa baik kualitas hasil pekerjaan yang diselesaikan?'
                                        ],
                                        'communication' => [
                                            'label' => 'Komunikasi', 
                                            'icon' => '💬', 
                                            'desc' => 'Seberapa jelas dan responsif komunikasi selama pengerjaan proyek?'
                                        ],
                                        'collaboration' => [
                                            'label' => 'Kerja Sama', 
                                            'icon' => '🤝', 
                                            'desc' => 'Seberapa mudah creative worker diajak berkolaborasi dan menerima masukan?'
                                        ],
                                        'timeliness' => [
                                            'label' => 'Ketepatan Waktu', 
                                            'icon' => '⏰', 
                                            'desc' => 'Seberapa tepat waktu creative worker dalam menyelesaikan pekerjaan?'
                                        ],
                                        'professionalism' => [
                                            'label' => 'Profesionalisme', 
                                            'icon' => '💼', 
                                            'desc' => 'Seberapa profesional sikap, perilaku, dan etos kerja creative worker?'
                                        ]
                                    ];
                                    
                                    foreach ($categories as $key => $category): 
                                    ?>
                                    <div class="rating-category-card">
                                        <div class="category-header">
                                            <span class="category-icon"><?= $category['icon'] ?></span>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 fw-bold"><?= $category['label'] ?></h6>
                                                <small class="text-muted"><?= $category['desc'] ?></small>
                                            </div>
                                        </div>
                                        <div class="stars-rating" data-category="<?= $key ?>">
                                            <?php for($i=5; $i>=1; $i--): ?>
                                                <input type="radio" id="<?= $key ?>_<?= $i ?>" 
                                                       name="category_ratings[<?= $key ?>]" value="<?= $i ?>" required>
                                                <label for="<?= $key ?>_<?= $i ?>" title="<?= $i ?> bintang - <?= 
                                                    $i == 1 ? 'Sangat Buruk' : 
                                                    ($i == 2 ? 'Buruk' : 
                                                    ($i == 3 ? 'Cukup' : 
                                                    ($i == 4 ? 'Baik' : 'Sangat Baik')))
                                                ?>">★</label>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="rating-labels">
                                            <span>Tidak Puas</span>
                                            <span>Sangat Puas</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="overall-rating-section mb-5">
                                <h5 class="mb-3">⭐ Rating Keseluruhan</h5>
                                <div class="overall-rating-display">
                                    <div class="stars overall-stars mb-3">
                                        <?php for($i=5; $i>=1; $i--): ?>
                                            <input type="radio" id="overall_<?= $i ?>" name="overall_rating" value="<?= $i ?>" readonly>
                                            <label for="overall_<?= $i ?>">★</label>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="rating-text text-muted" id="ratingText">
                                        <i class="fas fa-info-circle me-2"></i>Rating akan terisi otomatis berdasarkan penilaian kategori di atas
                                    </span>
                                </div>
                            </div>
                            
                            <div class="row mb-5">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-comment me-2"></i>Review Publik
                                        </label>
                                        <small class="text-muted d-block mb-2">
                                            Ulasan ini akan ditampilkan di profil creative worker dan dapat dilihat oleh semua orang
                                        </small>
                                        <textarea name="public_review" class="form-control" rows="5" 
                                                  placeholder="Bagikan pengalaman positif bekerja dengan creative worker ini. Ceritakan kelebihan, hasil kerja yang memuaskan, atau hal-hal yang membuat Anda impressed. Feedback yang baik akan membantu mereka berkembang dan menarik lebih banyak klien...
                                                  
Contoh: 'Hasil desain logo sangat kreatif dan sesuai brief, komunikasi lancar, dan selalu tepat waktu.'"
                                                  maxlength="500" required></textarea>
                                        <div class="char-count text-muted" id="publicCharCount">0/500 karakter</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-lock me-2"></i>Feedback Pribadi
                                        </label>
                                        <small class="text-muted d-block mb-2">
                                            Saran konstruktif ini hanya untuk creative worker, tidak akan ditampilkan secara publik
                                        </small>
                                        <textarea name="private_feedback" class="form-control" rows="5"
                                                  placeholder="Berikan saran konstruktif untuk pengembangan skill dan profesionalisme. Fokus pada hal yang bisa ditingkatkan.

Contoh: 'Untuk proyek berikutnya, coba lebih responsif dalam merespons chat di jam kerja' atau 'Perhatikan konsistensi typography pada bagian footer'"
                                                  maxlength="300"></textarea>
                                        <div class="char-count text-muted" id="privateCharCount">0/300 karakter</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-5">
                                <h5 class="mb-3">🤝 Rekomendasi</h5>
                                <label class="form-label fw-bold">Apakah Anda akan merekomendasikan creative worker ini kepada UMKM lain?</label>
                                <div class="recommendation-options mt-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="would_recommend" id="recommend_yes" value="1" required>
                                        <label class="form-check-label text-success fw-bold" for="recommend_yes">
                                            <i class="fas fa-thumbs-up me-2"></i>
                                            Ya, sangat merekomendasikan
                                            <small class="d-block text-muted fw-normal mt-1">
                                                Hasil kerja memuaskan, profesional, dan komunikasi lancar. Saya akan gunakan jasa mereka lagi.
                                            </small>
                                        </label>
                                    </div>
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="radio" name="would_recommend" id="recommend_no" value="0" required>
                                        <label class="form-check-label text-danger fw-bold" for="recommend_no">
                                            <i class="fas fa-thumbs-down me-2"></i>
                                            Tidak merekomendasikan
                                            <small class="d-block text-muted fw-normal mt-1">
                                                Ada beberapa hal yang perlu diperbaiki sebelum saya bisa merekomendasikan ke orang lain.
                                            </small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-3">
                                <button type="submit" class="btn submit-btn text-white py-3">
                                    <i class="fas fa-paper-plane me-2"></i>Kirim Review
                                </button>
                                <a href="../contracts/view-contract.php?id=<?= $contractId ?>" class="btn btn-outline-secondary py-3">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Detail Kontrak
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Review System JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        const reviewSystem = {
            init() {
                this.bindEvents();
                this.updateAllCharCounts();
            },
            
            bindEvents() {
                document.querySelectorAll('.stars-rating input').forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        this.calculateOverallRating();
                        this.updateRatingText();
                    });
                });
                
                document.querySelectorAll('textarea[maxlength]').forEach(textarea => {
                    textarea.addEventListener('input', this.updateCharCount.bind(this));
                });
                
                document.getElementById('reviewForm').addEventListener('submit', this.validateForm.bind(this));
            },
            
            calculateOverallRating() {
                const categories = document.querySelectorAll('.rating-category-card');
                let total = 0;
                let count = 0;
                
                categories.forEach(category => {
                    const selectedRating = category.querySelector('input:checked');
                    if (selectedRating) {
                        total += parseInt(selectedRating.value);
                        count++;
                    }
                });
                
                if (count > 0) {
                    const overallRating = Math.round(total / count);
                    this.setOverallRating(overallRating);
                }
            },
            
            setOverallRating(rating) {
                document.querySelectorAll('input[name="overall_rating"]').forEach(input => {
                    input.checked = false;
                });
                
                const overallInput = document.querySelector(`input[name="overall_rating"][value="${rating}"]`);
                if (overallInput) {
                    overallInput.checked = true;
                }
            },
            
            updateRatingText() {
                const rating = document.querySelector('input[name="overall_rating"]:checked');
                const ratingText = document.getElementById('ratingText');
                
                if (rating) {
                    const texts = {
                        1: 'Buruk - Perlu banyak perbaikan dan pengembangan',
                        2: 'Cukup - Ada beberapa kekurangan yang perlu diperbaiki', 
                        3: 'Baik - Memenuhi ekspektasi dan standar',
                        4: 'Sangat Baik - Melebihi ekspektasi dengan hasil kerja berkualitas',
                        5: 'Luar Biasa - Hasil kerja exceptional dan sangat profesional'
                    };
                    const ratingClass = this.getRatingClass(rating.value);
                    ratingText.textContent = texts[rating.value] || '';
                    ratingText.className = `rating-text ${ratingClass}`;
                }
            },
            
            getRatingClass(rating) {
                const classes = {
                    1: 'text-danger',
                    2: 'text-warning',
                    3: 'text-info',
                    4: 'text-primary', 
                    5: 'text-success'
                };
                return classes[rating] || 'text-muted';
            },
            
            updateCharCount(e) {
                const textarea = e.target;
                const maxLength = parseInt(textarea.getAttribute('maxlength'));
                const currentLength = textarea.value.length;
                const charCountId = textarea.name === 'public_review' ? 'publicCharCount' : 'privateCharCount';
                const charCountElement = document.getElementById(charCountId);
                
                if (charCountElement) {
                    charCountElement.textContent = `${currentLength}/${maxLength} karakter`;
                    
                    charCountElement.className = 'char-count';
                    if (currentLength > maxLength * 0.9) {
                        charCountElement.classList.add('danger');
                    } else if (currentLength > maxLength * 0.75) {
                        charCountElement.classList.add('warning');
                    } else if (currentLength > 0) {
                        charCountElement.classList.add('text-success');
                    }
                }
            },
            
            updateAllCharCounts() {
                document.querySelectorAll('textarea[maxlength]').forEach(textarea => {
                    this.updateCharCount({target: textarea});
                });
            },
            
            validateForm(e) {
                const categoryRatings = document.querySelectorAll('.stars-rating input:checked');
                if (categoryRatings.length < 5) {
                    e.preventDefault();
                    alert('Harap berikan rating untuk semua kategori sebelum mengirim review.');
                    return false;
                }
                
                const recommendation = document.querySelector('input[name="would_recommend"]:checked');
                if (!recommendation) {
                    e.preventDefault();
                    alert('Harap pilih apakah Anda akan merekomendasikan creative worker ini.');
                    return false;
                }
                
                return true;
            }
        };
        
        reviewSystem.init();
    });

    document.querySelectorAll('.stars-rating label').forEach(label => {
        label.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.3)';
        });
        
        label.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>