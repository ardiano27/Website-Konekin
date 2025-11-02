<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'creative' && $_SESSION['user_type'] !== 'creative')) {
    header("Location: ../login.php");
    exit();
}
if (empty($_SESSION['user_name'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/Database.php';
$database = new Database();
$db = $database->getConnection();

$stats = [
    'total_contracts' => 8,
    'total_income' => 18500000,
    'avg_rating' => 4.7
];

$milestoneProgress = [
    'total_milestones' => 15,
    'completed_milestones' => 11
];

$contractStatus = [
    ['status' => 'active', 'count' => 3],
    ['status' => 'completed', 'count' => 4],
    ['status' => 'cancelled', 'count' => 1]
];

$topSkills = [
    ['name' => 'Web Development', 'count' => 8],
    ['name' => 'UI/UX Design', 'count' => 6],
    ['name' => 'Graphic Design', 'count' => 5],
    ['name' => 'Content Writing', 'count' => 3]
];

$incomeTrend = [
    ['month' => '2024-09', 'amount' => 3500000],
    ['month' => '2024-10', 'amount' => 4200000],
    ['month' => '2024-11', 'amount' => 5100000],
    ['month' => '2024-12', 'amount' => 5700000]
];

$milestonePercent = $milestoneProgress['total_milestones'] > 0 ? 
    ($milestoneProgress['completed_milestones'] / $milestoneProgress['total_milestones']) * 100 : 0;

$contractChartData = [];
foreach ($contractStatus as $contract) {
    $contractChartData[] = ['name' => ucfirst($contract['status']), 'y' => (int)$contract['count']];
}

$skillChartData = [];
foreach ($topSkills as $skill) {
    $skillChartData[] = ['name' => $skill['name'], 'y' => (int)$skill['count']];
}

$incomeChartData = [];
foreach ($incomeTrend as $income) {
    $incomeChartData[] = ['name' => $income['month'], 'y' => (float)$income['amount']];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Creative Worker - Konekin</title>
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
<body style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); min-height: 100vh;">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-palette me-2"></i>Dashboard Creative Worker</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Halo, <?php echo $_SESSION['user_name'] ?? 'User'; ?></span>
                <a href="../logout.php" class="btn btn-outline-light"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-3"><div class="card glass-card stats-card text-center"><div class="card-body"><i class="fas fa-briefcase fa-2x text-primary mb-2"></i><h3><?php echo $stats['total_contracts']; ?></h3><p class="text-muted">Proyek Dikerjakan</p></div></div></div>
            <div class="col-md-3"><div class="card glass-card stats-card text-center"><div class="card-body"><i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i><h3>Rp <?php echo number_format($stats['total_income'], 0, ',', '.'); ?></h3><p class="text-muted">Total Pendapatan</p></div></div></div>
            <div class="col-md-3"><div class="card glass-card stats-card text-center"><div class="card-body"><i class="fas fa-star fa-2x text-warning mb-2"></i><h3><?php echo number_format($stats['avg_rating'], 1); ?>/5</h3><p class="text-muted">Rating Rata-rata</p></div></div></div>
            <div class="col-md-3"><div class="card glass-card stats-card text-center"><div class="card-body"><i class="fas fa-tasks fa-2x text-info mb-2"></i><h3><?php echo round($milestonePercent); ?>%</h3><p class="text-muted">Milestone Selesai</p></div></div></div>
        </div>

        <div class="row mb-4">
            <div class="col-12"><div class="card glass-card"><div class="card-body"><h6>Progress Milestone</h6><div class="progress"><div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $milestonePercent; ?>%"><?php echo round($milestonePercent); ?>%</div></div><small class="text-muted"><?php echo $milestoneProgress['completed_milestones']; ?> dari <?php echo $milestoneProgress['total_milestones']; ?> milestone selesai</small></div></div></div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6"><div class="card glass-card"><div class="card-header"><h5><i class="fas fa-chart-pie me-2"></i>Status Kontrak</h5></div><div class="card-body"><div id="contractChart" class="chart-container"></div></div></div></div>
            <div class="col-md-6"><div class="card glass-card"><div class="card-header"><h5><i class="fas fa-chart-bar me-2"></i>Skill Teratas</h5></div><div class="card-body"><div id="skillChart" class="chart-container"></div></div></div></div>
        </div>

        <div class="row">
            <div class="col-12"><div class="card glass-card"><div class="card-header"><h5><i class="fas fa-chart-line me-2"></i>Tren Pendapatan</h5></div><div class="card-body"><div id="incomeChart" class="chart-container"></div></div></div></div>
        </div>
    </div>

    <script>
        Highcharts.chart('contractChart', { chart: { type: 'pie' }, title: { text: '' }, series: [{ name: 'Kontrak', data: <?php echo json_encode($contractChartData); ?> }] });
        Highcharts.chart('skillChart', { chart: { type: 'bar' }, title: { text: '' }, xAxis: { type: 'category' }, yAxis: { title: { text: 'Penggunaan' } }, series: [{ name: 'Skill', data: <?php echo json_encode($skillChartData); ?> }] });
        Highcharts.chart('incomeChart', { chart: { type: 'line' }, title: { text: '' }, xAxis: { type: 'category' }, yAxis: { title: { text: 'Pendapatan (Rp)' } }, series: [{ name: 'Pendapatan', data: <?php echo json_encode($incomeChartData); ?> }] });
    </script>
</body>
</html>