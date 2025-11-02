<?php include "check_login.php"; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
     <?php include "dashboard-sidebar.php"; ?>
    <div class="main-content">
        <div class="row justify-content-center">
            <div class="col-md-10">
               
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Profil Saya</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong>Nama Lengkap:</strong> <?php echo $_SESSION['full_name']; ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Email:</strong> <?php echo $_SESSION['email']; ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Tipe Akun:</strong> 
                                    <?php echo $_SESSION['user_type'] === 'umkm' ? 'UMKM/Bisnis' : 'Creative Worker'; ?>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <?php if ($userType === 'creative' && $stats && $stats['badge'] !== 'none'): ?>
                                    <div class="badge <?php echo $stats['badge']; ?>">
                                        <?php echo $stats['badge'] === 'rated_talent' ? '⭐ Rated Talent' : '🚀 Rising Talent'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($userType === 'creative'): ?>
                    
                    <?php if ($stats): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Job Success Score</h5>
                        </div>
                        <div class="card-body">
                            <div class="success-score">
                                <div class="score-number"><?php echo $stats['success_score']; ?>%</div>
                                <div>Success Rate</div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <div style="font-size: 24px; font-weight: bold;"><?php echo $stats['total_projects']; ?></div>
                                        <div style="font-size: 14px; color: #666;">Total Projek</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <div style="font-size: 24px; font-weight: bold;"><?php echo $stats['completed_projects']; ?></div>
                                        <div style="font-size: 14px; color: #666;">Projek Selesai</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <div style="font-size: 24px; font-weight: bold;"><?php echo $ratingAvg['count']; ?></div>
                                        <div style="font-size: 14px; color: #666;">Review</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Rating & Review</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <div style="font-size: 48px; font-weight: bold; color: #FFD700;">
                                    <?php echo $ratingAvg['overall']; ?>
                                </div>
                                <div class="stars" style="font-size: 24px;">
                                    <?php echo str_repeat('★', round($ratingAvg['overall'])); ?>
                                </div>
                                <div style="color: #666;"><?php echo $ratingAvg['count']; ?> reviews</div>
                            </div>
                            
                            <div class="rating-summary">
                                <div class="rating-item">
                                    <span>Kualitas Pekerjaan</span>
                                    <span class="stars">
                                        <?php echo str_repeat('★', round($ratingAvg['quality'])); ?>
                                        <small>(<?php echo $ratingAvg['quality']; ?>)</small>
                                    </span>
                                </div>
                                <div class="rating-item">
                                    <span>Komunikasi</span>
                                    <span class="stars">
                                        <?php echo str_repeat('★', round($ratingAvg['communication'])); ?>
                                        <small>(<?php echo $ratingAvg['communication']; ?>)</small>
                                    </span>
                                </div>
                                <div class="rating-item">
                                    <span>Kerja Sama</span>
                                    <span class="stars">
                                        <?php echo str_repeat('★', round($ratingAvg['collaboration'])); ?>
                                        <small>(<?php echo $ratingAvg['collaboration']; ?>)</small>
                                    </span>
                                </div>
                                <div class="rating-item">
                                    <span>Ketepatan Waktu</span>
                                    <span class="stars">
                                        <?php echo str_repeat('★', round($ratingAvg['timeliness'])); ?>
                                        <small>(<?php echo $ratingAvg['timeliness']; ?>)</small>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5>Reviews dari Klien</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reviews)): ?>
                                <?php foreach($reviews as $review): ?>
                                    <?php $reviewer = $reviewModel->getUser($review['umkm_id']); ?>
                                    <div class="review-item">
                                        <div class="review-header">
                                            <div class="reviewer-avatar">
                                                <?php echo strtoupper(substr($reviewer['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($reviewer['name']); ?></strong>
                                                <div style="color: #666; font-size: 12px;">
                                                    <?php echo date('d M Y', strtotime($review['created_at'])); ?>
                                                </div>
                                            </div>
                                            <div style="margin-left: auto; color: #FFD700;">
                                                <?php echo str_repeat('★', $review['rating']); ?>
                                            </div>
                                        </div>
                                        
                                        <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                        
                                        <div class="private-feedback">
                                            <strong>📝 Feedback Pribadi:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($review['private_feedback'])); ?></p>
                                        </div>
                                        
                                        <div style="margin-top: 10px; display: flex; gap: 15px; font-size: 12px;">
                                            <span>Kualitas: <?php echo str_repeat('★', $review['quality']); ?></span>
                                            <span>Komunikasi: <?php echo str_repeat('★', $review['communication']); ?></span>
                                            <span>Kerja Sama: <?php echo str_repeat('★', $review['collaboration']); ?></span>
                                            <span>Waktu: <?php echo str_repeat('★', $review['timeliness']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted">Belum ada reviews untuk Anda.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <p>Anda login sebagai UMKM/Bisnis</p>
                            <a href="review-form.php" class="btn btn-success">Beri Review Creative Worker</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <a href="dashboard.php" class="btn btn-primary">Kembali ke Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>