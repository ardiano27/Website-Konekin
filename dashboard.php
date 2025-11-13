<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
error_log("Session data: " . print_r($_SESSION, true));

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Pastikan user_role ada di session
if (!isset($_SESSION['user_role'])) {
    // Default ke creative_worker jika tidak ada role
    $_SESSION['user_role'] = 'creative_worker';
}

require_once 'config/Database.php';

$chartModel = null;
if (file_exists('includes/config/database-charts.php')) {
    require_once 'includes/config/database-charts.php';
    $database = new Database();
    $db = $database->getConnection();
    $chartModel = new DashboardCharts($db);
} else {
    // Fallback class jika file tidak ada
    class SimpleDashboardCharts {
        public function getAdminStats() {
            return [
                'total_projects' => 15, 
                'total_users' => 8, 
                'total_revenue' => 12500000, 
                'avg_rating' => 4.5
            ];  
        }
        public function getProjectStatusStats() {
            return [
                ['status' => 'open', 'count' => 5],
                ['status' => 'in_progress', 'count' => 7],
                ['status' => 'completed', 'count' => 3]
            ];
        }
        public function getUserDistribution() {
            return [
                ['user_type' => 'creative', 'count' => 5],
                ['user_type' => 'umkm', 'count' => 3]
            ];
        }
        public function getUMKMStats($user_id) {
            return [
                'total_projects' => 3, 
                'total_creative' => 5, 
                'active_contracts' => 2, 
                'total_spent' => 4500000
            ];
        }
        public function getUMKMProjectStatus($user_id) {
            return [
                ['status' => 'open', 'count' => 1],
                ['status' => 'in_progress', 'count' => 1],
                ['status' => 'completed', 'count' => 1]
            ];
        }
        public function getCWStats($user_id) {
            return [
                'available_projects' => 12, 
                'total_portfolios' => 3, 
                'total_contracts' => 4, 
                'avg_rating' => 4.7
            ];
        }
        public function getCWProjectCategories() {
            return [
                ['category' => 'website', 'count' => 5],
                ['category' => 'logo', 'count' => 3],
                ['category' => 'social_media', 'count' => 4]
            ];
        }
    }
    $chartModel = new SimpleDashboardCharts();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role']; 

// Load data berdasarkan role
if ($user_role === 'admin') {
    $stats = $chartModel->getAdminStats();
    $projectStatus = $chartModel->getProjectStatusStats();
    $userDistribution = $chartModel->getUserDistribution();
} elseif ($user_role === 'umkm') {
    $stats = $chartModel->getUMKMStats($user_id);
    $projectStatus = $chartModel->getUMKMProjectStatus($user_id);
} else {
    // Default ke creative worker
    $stats = $chartModel->getCWStats($user_id);
    $projectCategories = $chartModel->getCWProjectCategories();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2596be;
            --primary-dark: #1e7a9c;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        .sidebar {
            background-color: var(--primary-color);
            color: white;
            min-height: 100vh;
            position: fixed;
            width: 250px;
            z-index: 1000;
        }
        
        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 5px;
        }
        
        .sidebar .nav-link:hover {
            background-color: var(--primary-dark);
        }
        
        .sidebar .nav-link.active {
            background-color: var(--primary-dark);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
        }
        
        .stat-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .card-body {
            padding: 25px;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

    <?php 
        include 'dashboard-sidebar.php';
    ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dashboard <?php echo ucfirst(str_replace('_', ' ', $user_role)); ?></h2>
            <div class="d-flex align-items-center">
                <span class="me-3">Halo, <?php echo $_SESSION['full_name'] ?? 'User'; ?>!</span>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i> Profile
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>Edit Profile</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <?php if ($user_role === 'admin'): ?>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card text-center">
                        <div class="card-body">
                            <i class="fas fa-project-diagram stat-icon text-primary mb-2"></i>
                            <h3><?php echo $stats['total_projects'] ?? 0; ?></h3>
                            <p class="text-muted">Total Proyek</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card text-center">
                        <div class="card-body">
                            <i class="fas fa-users stat-icon text-info mb-2"></i>
                            <h3><?php echo $stats['total_users'] ?? 0; ?></h3>
                            <p class="text-muted">Total Pengguna</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card text-center">
                        <div class="card-body">
                            <i class="fas fa-money-bill-wave stat-icon text-success mb-2"></i>
                            <h3>Rp <?php echo number_format($stats['total_revenue'] ?? 0, 0, ',', '.'); ?></h3>
                            <p class="text-muted">Total Pendapatan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card text-center">
                        <div class="card-body">
                            <i class="fas fa-star stat-icon text-warning mb-2"></i>
                            <h3><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></h3>
                            <p class="text-muted">Rating Rata-rata</p>
                        </div>
                    </div>
                </div>

            <?php elseif ($user_role === 'umkm'): ?>
                <div class="col-md-3 mb-3">
                    <div class="card glass-card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-project-diagram fa-2x text-primary mb-2"></i>
                            <h3><?php echo $stats['total_projects'] ?? 0; ?></h3>
                            <p class="text-muted">Total Proyek Saya</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card glass-card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-users fa-2x text-info mb-2"></i>
                            <h3><?php echo $stats['total_creative'] ?? 0; ?></h3>
                            <p class="text-muted">Creative Workers</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card glass-card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-file-contract fa-2x text-success mb-2"></i>
                            <h3><?php echo $stats['active_contracts'] ?? 0; ?></h3>
                            <p class="text-muted">Kontrak Aktif</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card glass-card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-money-bill-wave fa-2x text-warning mb-2"></i>
                            <h3>Rp <?php echo number_format($stats['total_spent'] ?? 0, 0, ',', '.'); ?></h3>
                            <p class="text-muted">Total Dibayarkan</p>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="col-md-3 mb-3">
                    <div class="card glass-card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-briefcase fa-2x text-primary mb-2"></i>
                            <h3><?php echo $stats['available_projects'] ?? 0; ?></h3>
                            <p class="text-muted">Proyek Tersedia</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card glass-card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-images fa-2x text-info mb-2"></i>
                            <h3><?php echo $stats['total_portfolios'] ?? 0; ?></h3>
                            <p class="text-muted">Portfolio</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card glass-card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-file-contract fa-2x text-success mb-2"></i>
                            <h3><?php echo $stats['total_contracts'] ?? 0; ?></h3>
                            <p class="text-muted">Total Kontrak</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card glass-card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-star fa-2x text-warning mb-2"></i>
                            <h3><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></h3>
                            <p class="text-muted">Rating Saya</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <?php if ($user_role === 'admin'): ?>
                <div class="col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar me-2"></i>Status Proyek</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="projectStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie me-2"></i>Distribusi Pengguna</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="userDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($user_role === 'umkm'): ?>
                <div class="col-md-6 mb-3">
                    <div class="card glass-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie me-2"></i>Status Proyek Saya</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="myProjectsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card glass-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line me-2"></i>Budget Proyek</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="projectBudgetChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="col-md-6 mb-3">
                    <div class="card glass-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar me-2"></i>Kategori Proyek Tersedia</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="projectCategoriesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card glass-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line me-2"></i>Perkembangan Portfolio</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="portfolioGrowthChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Welcome Card -->
        <div class="row">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-body text-center">
                        <h3>Selamat datang di Konekin!</h3>
                        <p class="mb-0">Platform terbaik untuk menghubungkan UMKM dengan Creative Worker</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const chartData = {
            <?php if ($user_role === 'admin'): ?>
                projectStatus: <?php echo json_encode($projectStatus ?? []); ?>,
                userDistribution: <?php echo json_encode($userDistribution ?? []); ?>
            <?php elseif ($user_role === 'umkm'): ?>
                projectStatus: <?php echo json_encode($projectStatus ?? []); ?>
            <?php else: ?>
                projectCategories: <?php echo json_encode($projectCategories ?? []); ?>
            <?php endif; ?>
        };

        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($user_role === 'admin'): ?>
                // Admin Charts
                if (document.getElementById('projectStatusChart') && chartData.projectStatus.length > 0) {
                    new Chart(document.getElementById('projectStatusChart'), {
                        type: 'bar',
                        data: {
                            labels: chartData.projectStatus.map(item => {
                                const statusMap = {
                                    'open': 'Open',
                                    'in_progress': 'In Progress', 
                                    'completed': 'Completed',
                                    'cancelled': 'Cancelled'
                                };
                                return statusMap[item.status] || item.status;
                            }),
                            datasets: [{
                                label: 'Jumlah Proyek',
                                data: chartData.projectStatus.map(item => item.count),
                                backgroundColor: '#2596be'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                }

                if (document.getElementById('userDistributionChart') && chartData.userDistribution.length > 0) {
                    new Chart(document.getElementById('userDistributionChart'), {
                        type: 'doughnut',
                        data: {
                            labels: chartData.userDistribution.map(item => {
                                return item.user_type === 'creative' ? 'Creative Worker' : 'UMKM';
                            }),
                            datasets: [{
                                data: chartData.userDistribution.map(item => item.count),
                                backgroundColor: ['#2596be', '#28a745']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                }

            <?php elseif ($user_role === 'umkm'): ?>
                // UMKM Charts
                if (document.getElementById('myProjectsChart') && chartData.projectStatus && chartData.projectStatus.length > 0) {
                    new Chart(document.getElementById('myProjectsChart'), {
                        type: 'pie',
                        data: {
                            labels: chartData.projectStatus.map(item => {
                                const statusMap = {
                                    'open': 'Open',
                                    'in_progress': 'Dalam Pengerjaan', 
                                    'completed': 'Selesai',
                                    'cancelled': 'Dibatalkan'
                                };
                                return statusMap[item.status] || item.status;
                            }),
                            datasets: [{
                                data: chartData.projectStatus.map(item => item.count),
                                backgroundColor: ['#2596be', '#17a2b8', '#28a745', '#dc3545']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                }

                if (document.getElementById('projectBudgetChart')) {
                    new Chart(document.getElementById('projectBudgetChart'), {
                        type: 'bar',
                        data: {
                            labels: ['Website Toko', 'Logo Brand', 'Social Media', 'Video Promo'],
                            datasets: [{
                                label: 'Budget (Rp)',
                                data: [5000000, 1500000, 3000000, 7000000],
                                backgroundColor: '#2596be'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return 'Rp ' + (value / 1000000).toFixed(1) + ' jt';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

            <?php else: ?>
                // Creative Worker Charts
                if (document.getElementById('projectCategoriesChart') && chartData.projectCategories && chartData.projectCategories.length > 0) {
                    new Chart(document.getElementById('projectCategoriesChart'), {
                        type: 'bar',
                        data: {
                            labels: chartData.projectCategories.map(item => {
                                const categoryMap = {
                                    'website': 'Website',
                                    'logo': 'Logo Design',
                                    'social_media': 'Social Media',
                                    'video': 'Video Production',
                                    'content': 'Content Writing',
                                    'marketing': 'Digital Marketing'
                                };
                                return categoryMap[item.category] || item.category;
                            }),
                            datasets: [{
                                label: 'Proyek Tersedia',
                                data: chartData.projectCategories.map(item => item.count),
                                backgroundColor: '#2596be'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y'
                        }
                    });
                }

                if (document.getElementById('portfolioGrowthChart')) {
                    new Chart(document.getElementById('portfolioGrowthChart'), {
                        type: 'line',
                        data: {
                            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
                            datasets: [{
                                label: 'Total Portfolio',
                                data: [1, 2, 3, 5, 7, 10],
                                borderColor: '#2596be',
                                backgroundColor: '#2596be20',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                }
            <?php endif; ?>
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>