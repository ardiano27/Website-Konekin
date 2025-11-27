<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config/Database.php';
require_once 'includes/config/database-charts.php';

$database = new Database();
$db = $database->getConnection();
$chartModel = new DashboardCharts($db);

$adminStats = $chartModel->getAdminStats();
$projectStatus = $chartModel->getProjectStatusStats();
$userDistribution = $chartModel->getUserDistribution();
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
        /* CSS sama seperti sebelumnya */
        .chart-container { height: 300px; }
        .stat-card { transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2"></i>Dashboard Admin
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Halo, Admin <?php echo $_SESSION['user_name']; ?></span>
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
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
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>Status Proyek (Semua)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="projectStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
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
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line me-2"></i>Pendapatan Bulanan</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyRevenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="fas fa-code me-2"></i>Skill Paling Diminati</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="topSkillsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const adminData = {
            projectStatus: <?php echo json_encode($projectStatus); ?>,
            userDistribution: <?php echo json_encode($userDistribution); ?>,
        };

        document.addEventListener('DOMContentLoaded', function() {
            new Chart(document.getElementById('projectStatusChart'), {
                type: 'bar',
                data: {
                    labels: adminData.projectStatus.map(item => item.status),
                    datasets: [{
                        label: 'Jumlah Proyek',
                        data: adminData.projectStatus.map(item => item.count),
                        backgroundColor: '#2596be'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            new Chart(document.getElementById('userDistributionChart'), {
                type: 'doughnut',
                data: {
                    labels: adminData.userDistribution.map(item => item.user_type),
                    datasets: [{
                        data: adminData.userDistribution.map(item => item.count),
                        backgroundColor: ['#2596be', '#28a745']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
const adminChartData = {
    projectStatus: <?php echo json_encode($projectStatus); ?>,
    userDistribution: <?php echo json_encode($userDistribution); ?>,
    monthlyRevenue: <?php echo json_encode($monthlyRevenue); ?>,
};
</script>
</body>
</html>