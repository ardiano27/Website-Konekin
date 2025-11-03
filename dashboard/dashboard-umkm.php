<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'umkm') {
    header("Location: login.php");
    exit();
}

require_once 'config/Database.php';
require_once 'models/Users.php';
require_once 'models/project.php';
require_once 'models/portofolio.php';
require_once 'includes/config/database-charts.php';

$database = new Database();
$db = $database->getConnection();
$userModel = new User($db);
$projectModel = new Project($db);
$chartModel = new DashboardCharts($db);

$user_id = $_SESSION['user_id'];
$creative_workers = $userModel->getByRole('creative_worker');
$my_projects = $projectModel->getByUser($user_id);

try {
    $umkmStats = $chartModel->getUMKMStats($user_id);
    $projectStatus = $chartModel->getUMKMProjectStatus($user_id);
    $projectBudgets = $chartModel->getUMKMBudgets($user_id); 
} catch (Exception $e) {
    $umkmStats = [
        'total_projects' => count($my_projects),
        'total_creative' => count($creative_workers),
        'active_contracts' => 0,
        'total_spent' => 0
    ];
    $projectStatus = [];
    $projectBudgets = [];
}
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card glass-card stats-card text-center">
            <div class="card-body">
                <i class="fas fa-project-diagram fa-2x text-primary mb-2"></i>
                <h3><?php echo $umkmStats['total_projects'] ?? 0; ?></h3>
                <p class="text-muted">Total Proyek Saya</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card glass-card stats-card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-info mb-2"></i>
                <h3><?php echo $umkmStats['total_creative'] ?? 0; ?></h3>
                <p class="text-muted">Creative Workers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card glass-card stats-card text-center">
            <div class="card-body">
                <i class="fas fa-file-contract fa-2x text-success mb-2"></i>
                <h3><?php echo $umkmStats['active_contracts'] ?? 0; ?></h3>
                <p class="text-muted">Kontrak Aktif</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card glass-card stats-card text-center">
            <div class="card-body">
                <i class="fas fa-money-bill-wave fa-2x text-warning mb-2"></i>
                <h3>Rp <?php echo number_format($umkmStats['total_spent'] ?? 0, 0, ',', '.'); ?></h3>
                <p class="text-muted">Total Dibayarkan</p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
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
    <div class="col-md-6">
        <div class="card glass-card">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2"></i>Budget Proyek</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="projectBudgetChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card glass-card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Timeline Kontrak</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="contractTimelineChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card glass-card">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2"></i>Pengeluaran per Kategori</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="spendingByCategoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/chart-config.js"></script>
<script src="assets/js/charts-umkm.js"></script>
<script>
const umkmChartData = {
    projectStatus: <?php echo json_encode($projectStatus); ?>,
    projectBudgets: <?php echo json_encode($projectBudgets); ?>,
    contractTimeline: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
        data: [2, 3, 5, 4, 6, 8]
    },
    spendingByCategory: {
        labels: ['Design', 'Development', 'Marketing', 'Content'],
        data: [45, 30, 15, 10]
    }
};
</script>