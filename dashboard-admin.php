<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config/Database.php';
require_once 'includes/config/database-chart.php';

$database = new DatabaseConnection();
$db = $database->getConnection();
$chartModel = new DashboardCharts($db);

$adminStats = $chartModel->getAdminStats();
$projectStatus = $chartModel->getProjectStatusStats();
$userDistribution = $chartModel->getUserDistribution();

// Query data tambahan untuk chart baru
$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalProjects = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$totalCreatives = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'creative'")->fetchColumn();
$totalUMKM = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'umkm'")->fetchColumn();

// Data untuk chart skills
$skillsData = [
    'design' => $db->query("SELECT COUNT(*) FROM skills WHERE category = 'design'")->fetchColumn(),
    'development' => $db->query("SELECT COUNT(*) FROM skills WHERE category = 'development'")->fetchColumn(),
    'marketing' => $db->query("SELECT COUNT(*) FROM skills WHERE category = 'marketing'")->fetchColumn(),
    'content' => $db->query("SELECT COUNT(*) FROM skills WHERE category = 'content'")->fetchColumn(),
    'video' => $db->query("SELECT COUNT(*) FROM skills WHERE category = 'video'")->fetchColumn(),
    'other' => $db->query("SELECT COUNT(*) FROM skills WHERE category = 'other'")->fetchColumn()
];

// Data untuk chart experience level
$experienceData = [
    'beginner' => $db->query("SELECT COUNT(*) FROM creative_profiles WHERE experience_level = 'beginner'")->fetchColumn(),
    'intermediate' => $db->query("SELECT COUNT(*) FROM creative_profiles WHERE experience_level = 'intermediate'")->fetchColumn(),
    'expert' => $db->query("SELECT COUNT(*) FROM creative_profiles WHERE experience_level = 'expert'")->fetchColumn()
];

// Data untuk chart business type
$businessData = [
    'food' => $db->query("SELECT COUNT(*) FROM umkm_profiles WHERE business_type = 'food'")->fetchColumn(),
    'fashion' => $db->query("SELECT COUNT(*) FROM umkm_profiles WHERE business_type = 'fashion'")->fetchColumn(),
    'craft' => $db->query("SELECT COUNT(*) FROM umkm_profiles WHERE business_type = 'craft'")->fetchColumn(),
    'service' => $db->query("SELECT COUNT(*) FROM umkm_profiles WHERE business_type = 'service'")->fetchColumn(),
    'retail' => $db->query("SELECT COUNT(*) FROM umkm_profiles WHERE business_type = 'retail'")->fetchColumn(),
    'other' => $db->query("SELECT COUNT(*) FROM umkm_profiles WHERE business_type = 'other'")->fetchColumn()
];

// Data untuk chart project status
$projectStatusData = [
    'draft' => $db->query("SELECT COUNT(*) FROM projects WHERE status = 'draft'")->fetchColumn(),
    'open' => $db->query("SELECT COUNT(*) FROM projects WHERE status = 'open'")->fetchColumn(),
    'in_progress' => $db->query("SELECT COUNT(*) FROM projects WHERE status = 'in_progress'")->fetchColumn(),
    'completed' => $db->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn(),
    'cancelled' => $db->query("SELECT COUNT(*) FROM projects WHERE status = 'cancelled'")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <script src="assets/js/chart-config.js"></script>
    <script src="assets/js/charts-admin.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container { height: 300px; }
        .stat-card { transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        .chart-wrapper {
            position: relative;
            height: 100%;
            width: 100%;
        }
        
        /* Navbar Styles */
        .konekin-navbar {
            background: linear-gradient(135deg, #3E7FD5 0%, #3E7FD5 100%);
            padding: 0.8rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand-konekin {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.5rem;
            margin-right: 3rem;
        }

        .navbar-brand-konekin i {
            margin-right: 10px;
            font-size: 1.8rem;
        }

        .navbar-nav-konekin {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-item-konekin {
            position: relative;
        }

        .nav-link-konekin {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.7rem 1.2rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .nav-link-konekin:hover,
        .nav-link-konekin.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .nav-link-konekin i {
            font-size: 1.1rem;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: 2rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            color: white;
        }

        .user-name {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-type {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            margin-left: 1rem;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .navbar-toggler-custom {
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
        }

        /* Mobile Styles */
        @media (max-width: 991.98px) {
            .konekin-navbar {
                padding: 0.8rem 1rem;
            }
            .navbar-brand-konekin {
                margin-right: 1rem;
            }
            .navbar-collapse-konekin {
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 1rem;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                z-index: 999;
            }

            .navbar-nav-konekin {
                flex-direction: column;
                gap: 0.5rem;
            }

            .nav-link-konekin {
                padding: 1rem;
                border-radius: 10px;
                justify-content: flex-start;
            }

            .user-section {
                margin-left: 0;
                margin-top: 1rem;
                justify-content: center;
                flex-direction: column;
                text-align: center;
            }

            .logout-btn {
                margin-left: 0;
                margin-top: 0.5rem;
                width: 100%;
            }
        }

        /* Badge untuk notifikasi */
        .nav-badge {
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -5px;
            right: -5px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="konekin-navbar navbar navbar-expand-lg">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand-konekin" href="dashboard-admin.php">
                <i class="fas fa-handshake"></i>
                Konekin Admin
            </a>

            <!-- Toggler untuk mobile -->
            <button class="navbar-toggler-custom d-lg-none" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarContent" aria-controls="navbarContent" 
                    aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Navbar Content -->
            <div class="collapse navbar-collapse justify-content-between" id="navbarContent">
                <!-- Menu Navigasi -->
                <ul class="navbar-nav-konekin">
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin active" href="dashboard-admin.php">
                            <i class="fas fa-home me-1"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="admin-users.php">
                            <i class="fas fa-users me-1"></i>
                            <span>Kelola Pengguna</span>
                        </a>
                    </li>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="admin-projects.php">
                            <i class="fas fa-project-diagram me-1"></i>
                            <span>Kelola Proyek</span>
                        </a>
                    </li>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="admin-reports.php">
                            <i class="fas fa-chart-bar me-1"></i>
                            <span>Laporan</span>
                        </a>
                    </li>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="admin-settings.php">
                            <i class="fas fa-cog me-1"></i>
                            <span>Pengaturan</span>
                        </a>
                    </li>
                </ul>

                <!-- User Section -->
                <div class="user-section">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <a href="admin-profile.php" style="text-decoration: none; color: inherit;">
                            <h6 class="user-name"><?php echo $_SESSION['full_name'] ?? 'Admin'; ?></h6>
                        </a>
                        <small class="user-type">Administrator</small>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <i class="fas fa-project-diagram fa-2x text-primary mb-2"></i>
                        <h3><?php echo $adminStats['total_projects']; ?></h3>
                        <p class="text-muted">Total Proyek</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-info mb-2"></i>
                        <h3><?php echo $adminStats['total_users']; ?></h3>
                        <p class="text-muted">Total Pengguna</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                        <h3>Rp <?php echo number_format($adminStats['total_revenue'], 0, ',', '.'); ?></h3>
                        <p class="text-muted">Total Pendapatan</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <i class="fas fa-star fa-2x text-warning mb-2"></i>
                        <h3><?php echo number_format($adminStats['avg_rating'], 1); ?></h3>
                        <p class="text-muted">Rating Rata-rata</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Chart 1: Distribusi Jenis Pengguna -->
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="fas fa-users me-2"></i>Distribusi Jenis Pengguna</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="userTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart 2: Kategori Skill Terpopuler -->
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="fas fa-code me-2"></i>Kategori Skill Terpopuler</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="skillsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Chart 3: Level Pengalaman Kreator -->
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="fas fa-star me-2"></i>Level Pengalaman Kreator</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="experienceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart 4: Jenis Bisnis UMKM -->
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="fas fa-store me-2"></i>Jenis Bisnis UMKM</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="businessTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Chart 5: Status Proyek -->
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="fas fa-tasks me-2"></i>Status Proyek</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="projectStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart 6: Trend Pendaftaran Pengguna -->
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line me-2"></i>Trend Pendaftaran Pengguna</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="registrationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Data untuk chart
        const chartData = {
            userType: {
                creative: <?php echo $totalCreatives; ?>,
                umkm: <?php echo $totalUMKM; ?>
            },
            skills: <?php echo json_encode($skillsData); ?>,
            experience: <?php echo json_encode($experienceData); ?>,
            business: <?php echo json_encode($businessData); ?>,
            projectStatus: <?php echo json_encode($projectStatusData); ?>
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Chart 1: Distribusi Jenis Pengguna
            new Chart(document.getElementById('userTypeChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Kreator', 'UMKM'],
                    datasets: [{
                        data: [chartData.userType.creative, chartData.userType.umkm],
                        backgroundColor: ['#FF6B6B', '#4ECDC4'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Chart 2: Kategori Skill Terpopuler
            new Chart(document.getElementById('skillsChart'), {
                type: 'bar',
                data: {
                    labels: ['Design', 'Development', 'Marketing', 'Content', 'Video', 'Other'],
                    datasets: [{
                        label: 'Jumlah Skill',
                        data: [
                            chartData.skills.design,
                            chartData.skills.development,
                            chartData.skills.marketing,
                            chartData.skills.content,
                            chartData.skills.video,
                            chartData.skills.other
                        ],
                        backgroundColor: '#667eea',
                        borderColor: '#764ba2',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Chart 3: Level Pengalaman Kreator
            new Chart(document.getElementById('experienceChart'), {
                type: 'pie',
                data: {
                    labels: ['Beginner', 'Intermediate', 'Expert'],
                    datasets: [{
                        data: [
                            chartData.experience.beginner,
                            chartData.experience.intermediate,
                            chartData.experience.expert
                        ],
                        backgroundColor: ['#FFD93D', '#6BCF7F', '#4D96FF'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Chart 4: Jenis Bisnis UMKM
            new Chart(document.getElementById('businessTypeChart'), {
                type: 'polarArea',
                data: {
                    labels: ['Food', 'Fashion', 'Craft', 'Service', 'Retail', 'Other'],
                    datasets: [{
                        data: [
                            chartData.business.food,
                            chartData.business.fashion,
                            chartData.business.craft,
                            chartData.business.service,
                            chartData.business.retail,
                            chartData.business.other
                        ],
                        backgroundColor: [
                            '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Chart 5: Status Proyek
            new Chart(document.getElementById('projectStatusChart'), {
                type: 'line',
                data: {
                    labels: ['Draft', 'Open', 'In Progress', 'Completed', 'Cancelled'],
                    datasets: [{
                        label: 'Status Proyek',
                        data: [
                            chartData.projectStatus.draft,
                            chartData.projectStatus.open,
                            chartData.projectStatus.in_progress,
                            chartData.projectStatus.completed,
                            chartData.projectStatus.cancelled
                        ],
                        backgroundColor: 'rgba(102, 126, 234, 0.2)',
                        borderColor: '#667eea',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Chart 6: Trend Pendaftaran Pengguna (data statis seperti contoh)
            new Chart(document.getElementById('registrationChart'), {
                type: 'line',
                data: {
                    labels: ['Oct 06', 'Oct 07', 'Oct 10', 'Oct 28', 'Oct 30'],
                    datasets: [{
                        label: 'Pendaftaran Harian',
                        data: [1, 2, 1, 1, 1],
                        backgroundColor: 'rgba(118, 75, 162, 0.2)',
                        borderColor: '#764ba2',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>