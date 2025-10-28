<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/Database.php';
require_once '../models/Dashboard.php';

$database = new Database();
$db = $database->getConnection();
$dashboard = new Dashboard($db);

$stats = $dashboard->getAdminStats();
$durationStats = $dashboard->getProjectDurationStats();
$popularCategories = $dashboard->getPopularCategories();
$monthlyActivity = $dashboard->getMonthlyActivity();
$recentNotifications = $dashboard->getRecentNotifications();

$categoryChartData = [];
foreach ($popularCategories as $category) {
    $categoryChartData[] = [
        'name' => str_replace('_', ' ', ucfirst($category['category'])),
        'y' => (int)$category['count']
    ];
}

$activityChartData = [];
foreach ($monthlyActivity as $activity) {
    $activityChartData[] = [
        'name' => $activity['month'],
        'y' => (int)$activity['user_count']
    ];
}

$disputeStats = [
    'open' => $stats['open_disputes'] ?? 0,
    'resolved' => ($stats['total_disputes'] ?? 0) - ($stats['open_disputes'] ?? 0)
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <style>
        :root {
            --primary-color: #2596be;
            --primary-dark: #1e7a9c;
        }
        .glass-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(10px); 
            border-radius: 15px; 
            border: 1px solid rgba(255, 255, 255, 0.2); 
        }
        .stats-card { 
            transition: transform 0.3s ease; 
        }
        .stats-card:hover { 
            transform: translateY(-5px); 
        }
        .chart-container { 
            height: 300px; 
            min-height: 300px; 
        }
        .notification-item { 
            border-left: 4px solid #007bff; 
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); min-height: 100vh;">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-cogs me-2"></i>Dashboard Admin</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Halo, <?php echo $_SESSION['user_name']; ?></span>
                <a href="../logout.php" class="btn btn-outline-light"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <h3><?php echo $stats['total_users'] ?? 0; ?></h3>
                        <p class="text-muted">Total Pengguna</p>
                        <small><?php echo $stats['creative_users'] ?? 0; ?> Creative | <?php echo $stats['umkm_users'] ?? 0; ?> UMKM</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-project-diagram fa-2x text-info mb-2"></i>
                        <h3><?php echo $stats['total_projects'] ?? 0; ?></h3>
                        <p class="text-muted">Total Proyek</p>
                        <small><?php echo $stats['active_projects'] ?? 0; ?> Aktif | <?php echo $stats['completed_projects'] ?? 0; ?> Selesai</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                        <h3>Rp <?php echo number_format($stats['total_transactions'] ?? 0, 0, ',', '.'); ?></h3>
                        <p class="text-muted">Total Transaksi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <h3><?php echo $stats['total_disputes'] ?? 0; ?></h3>
                        <p class="text-muted">Total Disputes</p>
                        <small><?php echo $stats['open_disputes'] ?? 0; ?> Terbuka</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-star fa-2x text-warning mb-2"></i>
                        <h3><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?>/5</h3>
                        <p class="text-muted">Rating Rata-rata</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-info mb-2"></i>
                        <h3><?php echo round($durationStats['avg_duration'] ?? 0); ?> hari</h3>
                        <p class="text-muted">Rata-rata Durasi Proyek</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                        <h3><?php echo count($monthlyActivity); ?></h3>
                        <p class="text-muted">Bulan Aktif</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card glass-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie me-2"></i>Kategori Proyek Populer</h5>
                    </div>
                    <div class="card-body">
                        <div id="categoryChart" class="chart-container"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card glass-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line me-2"></i>Tren Aktivasi Pengguna</h5>
                    </div>
                    <div class="card-body">
                        <div id="activityChart" class="chart-container"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Disputes Status -->
            <div class="col-md-6">
                <div class="card glass-card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Status Disputes</h5>
                    </div>
                    <div class="card-body">
                        <div id="disputeChart" class="chart-container"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card glass-card">
                    <div class="card-header">
                        <h5><i class="fas fa-bell me-2"></i>Notifikasi Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($recentNotifications)): ?>
                                <p class="text-muted text-center">Tidak ada notifikasi</p>
                            <?php else: ?>
                                <?php foreach ($recentNotifications as $notification): ?>
                                    <div class="notification-item p-3 mb-2 bg-light rounded">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo htmlspecialchars($notification['full_name']); ?></strong>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($notification['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small class="text-muted"><?php echo ucfirst($notification['type']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        Highcharts.chart('categoryChart', {
            chart: { type: 'pie' },
            title: { text: '' },
            series: [{
                name: 'Proyek',
                data: <?php echo json_encode($categoryChartData); ?>
            }]
        });

        Highcharts.chart('activityChart', {
            chart: { type: 'line' },
            title: { text: '' },
            xAxis: { type: 'category' },
            yAxis: { title: { text: 'Pengguna Baru' } },
            series: [{
                name: 'Pengguna',
                data: <?php echo json_encode($activityChartData); ?>
            }]
        });

        Highcharts.chart('disputeChart', {
            chart: { type: 'pie' },
            title: { text: '' },
            series: [{
                name: 'Disputes',
                data: [
                    { name: 'Terbuka', y: <?php echo $disputeStats['open']; ?>, color: '#ffc107' },
                    { name: 'Terselesaikan', y: <?php echo $disputeStats['resolved']; ?>, color: '#28a745' }
                ]
            }]
        });
    </script>
</body>
</html>