<?php
session_start();
require_once '../config/Security.php';
require_once '../config/Database.php';
require_once '../models/Dashboard.php';
require_once '../models/DataFallback.php';

Security::validateSession();
if (!Security::checkUserRole('umkm')) {
    header("Location: ../login.php");
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $dashboard = new Dashboard($db);
    
    $user_id = (int)$_SESSION['user_id'];
    
    $stats = $dashboard->getUMKMStats($user_id);
    $statusCounts = $dashboard->getProjectStatusCount($user_id) ?: DataFallback::getProjectStatusCount();
    $categoryStats = $dashboard->getProjectCategoryStats($user_id) ?: DataFallback::getCategoryStats();
    $monthlyTrend = $dashboard->getMonthlyProjectTrend($user_id) ?: DataFallback::getMonthlyTrend();
    
    $statusChartData = [];
    foreach ($statusCounts as $status) {
        $statusChartData[] = [
            'name' => Security::sanitizeOutput(ucfirst($status['status'])), 
            'y' => (int)($status['count'] ?? 0)
        ];
    }
    
    $categoryChartData = [];
    foreach ($categoryStats as $category) {
        $categoryChartData[] = [
            'name' => Security::sanitizeOutput(str_replace('_', ' ', ucfirst($category['category']))), 
            'y' => (int)($category['count'] ?? 0)
        ];
    }
    
    $trendChartData = [];
    foreach ($monthlyTrend as $trend) {
        $trendChartData[] = [
            'name' => Security::sanitizeOutput($trend['month']), 
            'y' => (int)($trend['count'] ?? 0)
        ];
    }
    
    if (empty($statusChartData)) $statusChartData = DataFallback::getEmptyChartData();
    if (empty($categoryChartData)) $categoryChartData = DataFallback::getEmptyChartData();
    if (empty($trendChartData)) $trendChartData = DataFallback::getEmptyChartData();
    
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $stats = DataFallback::getUMKMStats();
    $statusChartData = $categoryChartData = $trendChartData = DataFallback::getEmptyChartData();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard UMKM - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <style>
        :root {
            --primary-color: #2596be;
            --primary-dark: #1e7a9c;
        }
        .glass-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 15px; border: 1px solid rgba(255,255,255,0.2); }
        .stats-card { transition: transform 0.3s ease; }
        .stats-card:hover { transform: translateY(-5px); }
        .chart-container { height: 300px; min-height: 300px; }
        .progress { height: 20px; }
    </style>
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-store me-2"></i>Dashboard UMKM</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Halo, <?php echo Security::sanitizeOutput($_SESSION['user_name'] ?? 'User'); ?></span>
                <a href="../logout.php" class="btn btn-outline-light"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-project-diagram fa-2x text-primary mb-2"></i>
                        <h3><?php echo (int)($stats['total_projects'] ?? 0); ?></h3>
                        <p class="text-muted">Total Proyek</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card glass-card">
                    <div class="card-header"><h5><i class="fas fa-chart-pie me-2"></i>Status Proyek</h5></div>
                    <div class="card-body"><div id="statusChart" class="chart-container"></div></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        Highcharts.chart('statusChart', { 
            chart: { type: 'pie' }, 
            title: { text: '' }, 
            series: [{ 
                name: 'Proyek', 
                data: <?php echo json_encode($statusChartData); ?> 
            }] 
        });
        <!-- Chart lainnya -->
    </script>
</body>
</html>