    <?php
    // Mulai session dan set variabel user (simulasi)
    session_start();
    if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
    }

    require_once 'config/Database.php';
    $database = new DatabaseConnection();
    $conn = $database->getConnection();

    $user_id = 1; // ID user dari session, disini disimulasikan

    try {
        // Jumlah proposal yang dikirim
        $query_proposals = "SELECT COUNT(*) as total FROM proposals WHERE creative_user_id = :user_id";
        $stmt_proposals = $conn->prepare($query_proposals);
        $stmt_proposals->bindParam(':user_id', $user_id);
        $stmt_proposals->execute();
        $proposals_count = $stmt_proposals->fetch(PDO::FETCH_ASSOC)['total'];

        // Jumlah proyek aktif (kontrak dengan status active)
        $query_active = "SELECT COUNT(*) as total FROM contracts WHERE creative_user_id = :user_id AND status = 'active'";
        $stmt_active = $conn->prepare($query_active);
        $stmt_active->bindParam(':user_id', $user_id);
        $stmt_active->execute();
        $active_count = $stmt_active->fetch(PDO::FETCH_ASSOC)['total'];

        // Jumlah proyek selesai
        $query_completed = "SELECT COUNT(*) as total FROM contracts WHERE creative_user_id = :user_id AND status = 'completed'";
        $stmt_completed = $conn->prepare($query_completed);
        $stmt_completed->bindParam(':user_id', $user_id);
        $stmt_completed->execute();
        $completed_count = $stmt_completed->fetch(PDO::FETCH_ASSOC)['total'];

        // Rating dari creative profile
        $query_rating = "SELECT rating FROM creative_profiles WHERE user_id = :user_id";
        $stmt_rating = $conn->prepare($query_rating);
        $stmt_rating->bindParam(':user_id', $user_id);
        $stmt_rating->execute();
        $rating = $stmt_rating->fetch(PDO::FETCH_ASSOC)['rating'] ?? 0;

        // Proyek aktif dengan detail
        $query_active_projects = "
            SELECT c.*, p.title, p.description, p.budget_range_max as budget
            FROM contracts c 
            JOIN projects p ON c.project_id = p.id 
            WHERE c.creative_user_id = :user_id AND c.status = 'active'
            ORDER BY c.created_at DESC 
            LIMIT 3
        ";
        $stmt_active_projects = $conn->prepare($query_active_projects);
        $stmt_active_projects->bindParam(':user_id', $user_id);
        $stmt_active_projects->execute();
        $active_projects = $stmt_active_projects->fetchAll(PDO::FETCH_ASSOC);

        // Rekomendasi proyek (proyek yang membutuhkan skill creative worker ini)
        $query_recommended = "
            SELECT p.*, u.business_name 
            FROM projects p 
            JOIN umkm_profiles u ON p.umkm_user_id = u.user_id 
            WHERE p.status = 'open' 
            ORDER BY p.created_at DESC 
            LIMIT 2
        ";
        $stmt_recommended = $conn->prepare($query_recommended);
        $stmt_recommended->execute();
        $recommended_projects = $stmt_recommended->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Handle error, untuk sementara kita set nilai default
        $proposals_count = 12;
        $active_count = 3;
        $completed_count = 8;
        $rating = 4.8;
        $active_projects = [];
        $recommended_projects = [];
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Konekin - Dashboard Creative Worker</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            .dashboard-container {
                padding: 2rem;
                background-color: #f8f9fa;
                min-height: calc(100vh - 70px);
            }

            .welcome-card {
                background: #549efeff;
                color: white;
                border-radius: 15px;
                padding: 2rem;
                margin-bottom: 2rem;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }

            .stat-card {
                background: white;
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
                transition: transform 0.3s ease;
                height: 100%;
            }

            .stat-card:hover {
                transform: translateY(-5px);
            }

            .stat-icon {
                width: 60px;
                height: 60px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }

            .stat-icon.blue {
                background: rgba(62, 127, 213, 0.1);
                color: #3E7FD5;
            }

            .stat-icon.green {
                background: rgba(40, 167, 69, 0.1);
                color: #28a745;
            }

            .stat-icon.orange {
                background: rgba(255, 193, 7, 0.1);
                color: #ffc107;
            }

            .stat-icon.purple {
                background: rgba(111, 66, 193, 0.1);
                color: #6f42c1;
            }

            .stat-value {
                font-size: 1.8rem;
                font-weight: 700;
                margin-bottom: 0.25rem;
            }

            .stat-label {
                color: #6c757d;
                font-size: 0.9rem;
            }

            .section-title {
                font-weight: 600;
                margin-bottom: 1.5rem;
                color: #343a40;
                font-size: 1.25rem;
            }

            .project-card {
                background: white;
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
                margin-bottom: 1.5rem;
                transition: transform 0.3s ease;
            }

            .project-card:hover {
                transform: translateY(-3px);
            }

            .project-title {
                font-weight: 600;
                margin-bottom: 0.5rem;
                color: #343a40;
            }

            .project-budget {
                font-weight: 700;
                color: #3E7FD5;
                font-size: 1.1rem;
            }

            .project-status {
                display: inline-block;
                padding: 0.25rem 0.75rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
            }

            .status-pending {
                background: rgba(255, 193, 7, 0.1);
                color: #ffc107;
            }

            .status-active {
                background: rgba(40, 167, 69, 0.1);
                color: #28a745;
            }

            .status-completed {
                background: rgba(108, 117, 125, 0.1);
                color: #6c757d;
            }

            .quick-action-card {
                background: white;
                border-radius: 12px;
                padding: 1.5rem;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
                text-align: center;
                transition: all 0.3s ease;
                cursor: pointer;
                height: 100%;
            }

            .quick-action-card:hover {
                background: #3E7FD5;
                color: white;
                transform: translateY(-5px);
            }

            .quick-action-card:hover .action-icon {
                background: rgba(255, 255, 255, 0.2);
                color: white;
            }

            .action-icon {
                width: 70px;
                height: 70px;
                border-radius: 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.8rem;
                margin: 0 auto 1rem;
                background: rgba(62, 127, 213, 0.1);
                color: #3E7FD5;
                transition: all 0.3s ease;
            }

            .action-title {
                font-weight: 600;
                margin-bottom: 0.5rem;
            }

            .action-description {
                font-size: 0.85rem;
                opacity: 0.8;
            }

            /* Responsive Styles */
            @media (max-width: 991.98px) {
                .dashboard-container {
                    padding: 1rem;
                }
            }
        </style>
    </head>
    <body>
        <!-- Include Navbar -->
        <?php include 'dashboard-sidebar.php'; ?>

        <!-- Dashboard Content -->
        <div class="dashboard-container">
            <!-- Welcome Section -->
            <div class="welcome-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>Selamat datang kembali, <?php echo $_SESSION['full_name']; ?>!</h2>
                        <p class="mb-0">Ada 5 proyek baru yang sesuai dengan keahlian Anda. Mari mulai bekerja dan kembangkan portofolio Anda!</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="find-projects.php" class="btn btn-light mt-2">Jelajahi Proyek Baru</a>
                    </div>
                </div>
            </div>

            <!-- Stats Section -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-value"><?php echo $proposals_count; ?></div>
                        <div class="stat-label">Proposal Dikirim</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo $completed_count; ?></div>
                        <div class="stat-label">Proyek Selesai</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?php echo $active_count; ?></div>
                        <div class="stat-label">Proyek Berjalan</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($rating, 1); ?></div>
                        <div class="stat-label">Rating Rata-rata</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Active Projects Section -->
                <div class="col-lg-8 mb-4">
                    <h3 class="section-title">Proyek Berjalan</h3>
                    
                    <?php if (count($active_projects) > 0): ?>
                        <?php foreach ($active_projects as $project): ?>
                            <div class="project-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h4 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h4>
                                        <p class="mb-2"><?php echo htmlspecialchars($project['description'] ?? 'Tidak ada deskripsi'); ?></p>
                                        <div class="d-flex align-items-center">
                                            <span class="project-budget">Rp <?php echo number_format($project['agreed_budget'] ?? $project['budget'], 0, ',', '.'); ?></span>
                                            <span class="project-status status-active ms-3">Aktif</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="mb-2">
                                            <small class="text-muted">Deadline: <?php echo date('d M Y', strtotime($project['end_date'] ?? '+30 days')); ?></small>
                                        </div>
                                        <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-primary btn-sm">Lihat Detail</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="project-card text-center py-4">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Belum ada proyek berjalan</h5>
                            <p class="text-muted">Mulai kirim proposal untuk mendapatkan proyek pertama Anda!</p>
                            <a href="find-projects.php" class="btn btn-primary">Cari Proyek</a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="my-proposals.php" class="btn btn-outline-primary">Lihat Semua Proyek</a>
                    </div>
                </div>
                
                <!-- Quick Actions & Recommendations -->
                <div class="col-lg-4">
                    <h3 class="section-title">Aksi Cepat</h3>
                    
                    <div class="row">
                        <div class="col-md-6 col-lg-12 mb-3">
                            <div class="quick-action-card" onclick="window.location.href='find-projects.php'">
                                <div class="action-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h5 class="action-title">Cari Proyek</h5>
                                <p class="action-description">Temukan proyek yang sesuai dengan keahlian Anda</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-12 mb-3">
                            <div class="quick-action-card" onclick="window.location.href='portfolio.php'">
                                <div class="action-icon">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                                <h5 class="action-title">Update Portfolio</h5>
                                <p class="action-description">Tampilkan karya terbaru Anda</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-12 mb-3">
                            <div class="quick-action-card" onclick="window.location.href='messages.php'">
                                <div class="action-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <h5 class="action-title">Pesan</h5>
                                <p class="action-description">3 pesan belum dibaca</p>
                            </div>
                        </div>
                    </div>
                    
                    <h3 class="section-title mt-4">Rekomendasi Proyek</h3>
                    
                    <?php if (count($recommended_projects) > 0): ?>
                        <?php foreach ($recommended_projects as $project): ?>
                            <div class="project-card">
                                <h5 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h5>
                                <p class="mb-2"><?php echo htmlspecialchars($project['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="project-budget">Rp <?php echo number_format($project['budget_range_max'], 0, ',', '.'); ?></span>
                                    <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary btn-sm">Lihat</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="project-card text-center py-3">
                            <i class="fas fa-search fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">Tidak ada rekomendasi proyek saat ini</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>