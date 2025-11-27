<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'creative_worker') {
    header("Location: login.php");
    exit();
}

require_once 'config/Database.php';
require_once 'models/Users.php';
require_once 'models/project.php';
require_once 'models/portofolio.php';
require_once 'models/certificate.php';
require_once 'includes/config/database-charts.php'; 

$database = new Database();
$db = $database->getConnection();
$userModel = new User($db);
$projectModel = new Project($db);
$portfolioModel = new Portfolio($db);
$certificateModel = new Certificate($db);
$chartModel = new DashboardCharts($db); 

$user_id = $_SESSION['user_id'];
$open_projects = $projectModel->getAllOpen();
$my_portfolios = $portfolioModel->getByUser($user_id);
$my_certificates = $certificateModel->getByUser($user_id);

try {
    $cwStats = $chartModel->getCWStats($user_id);
    $projectCategories = $chartModel->getCWProjectCategories();
    $portfolioGrowth = $chartModel->getPortfolioGrowth($user_id); 
} catch (Exception $e) {
    $cwStats = [
        'available_projects' => count($open_projects),
        'total_portfolios' => count($my_portfolios),
        'total_contracts' => 0,
        'avg_rating' => 0.0
    ];
    $projectCategories = [];
    $portfolioGrowth = [];
}
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card glass-card stats-card text-center">
            <div class="card-body">
                <i class="fas fa-briefcase fa-2x text-primary mb-2"></i>
                <h3><?php echo $cwStats['available_projects'] ?? 0; ?></h3>
                <p class="text-muted">Proyek Tersedia</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card glass-card stats-card text-center">
            <div class="card-body">
                <i class="fas fa-images fa-2x text-info mb-2"></i>
                <h3><?php echo $cwStats['total_portfolios'] ?? 0; ?></h3>
                <p class="text-muted">Portfolio</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card glass-card stats-card text-center">
            <div class="card-body">
                <i class="fas fa-file-contract fa-2x text-success mb-2"></i>
                <h3><?php echo $cwStats['total_contracts'] ?? 0; ?></h3>
                <p class="text-muted">Total Kontrak</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card glass-card stats-card text-center">
            <div class="card-body">
                <i class="fas fa-star fa-2x text-warning mb-2"></i>
                <h3><?php echo number_format($cwStats['avg_rating'] ?? 0, 1); ?></h3>
                <p class="text-muted">Rating Saya</p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
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
    <div class="col-md-6">
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
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card glass-card">
            <div class="card-header">
                <h5><i class="fas fa-money-bill-wave me-2"></i>Pendapatan Bulanan</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="earningsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card glass-card">
            <div class="card-header">
                <h5><i class="fas fa-chart-radar me-2"></i>Skill Matrix</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="skillsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/chart-config.js"></script>
<script src="assets/js/charts-cw.js"></script>
<script>
const cwChartData = {
    projectCategories: <?php echo json_encode($projectCategories); ?>,
    portfolioGrowth: <?php echo json_encode($portfolioGrowth); ?>,
    earnings: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
        data: [5000000, 7500000, 6200000, 8900000, 11000000, 9500000]
    },
    skills: {
        labels: ['Web Development', 'UI/UX Design', 'Graphic Design', 'Content Writing', 'Video Editing', 'Digital Marketing'],
        data: [85, 90, 75, 70, 65, 60]
    }
};
</script>