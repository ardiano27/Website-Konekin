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

$database = new Database();
$db = $database->getConnection();
$userModel = new User($db);
$projectModel = new Project($db);
$portfolioModel = new Portfolio($db);

$user_id = $_SESSION['user_id'];
$creative_workers = $userModel->getByRole('creative_worker');
$my_projects = $projectModel->getByUser($user_id);
$featured_portfolios = $portfolioModel->getFeatured();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard UMKM - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
    </style>
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-store me-2"></i>Dashboard UMKM
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Halo, <?php echo $_SESSION['user_name']; ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-project-diagram fa-2x text-primary mb-2"></i>
                        <h3><?php echo count($my_projects); ?></h3>
                        <p class="text-muted">Total Proyek</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-info mb-2"></i>
                        <h3><?php echo count($creative_workers); ?></h3>
                        <p class="text-muted">Creative Workers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-success mb-2"></i>
                        <h3><?php echo date('H:i'); ?></h3>
                        <p class="text-muted">Waktu Sekarang</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Upload Project -->
            <div class="col-lg-6 mb-4">
                <div class="card glass-card">
                    <div class="card-header">
                        <h5><i class="fas fa-upload me-2"></i>Upload Proyek Baru</h5>
                    </div>
                    <div class="card-body">
                        <form action="actions/upload_project.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Judul Proyek</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deskripsi Kebutuhan</label>
                                <textarea name="description" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="category" class="form-select" required>
                                    <option value="">Pilih Kategori</option>
                                    <option value="design">Design</option>
                                    <option value="development">Development</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="content">Content Creation</option>
                                    <option value="other">Lainnya</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Budget (Rp)</label>
                                <input type="number" name="budget" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deadline</label>
                                <input type="date" name="deadline" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">File Pendukung (opsional)</label>
                                <input type="file" name="project_file" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-cloud-upload-alt me-2"></i>Upload Proyek
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- My Projects & Creative Workers -->
            <div class="col-lg-6">
                <!-- My Projects -->
                <div class="card glass-card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Proyek Saya</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($my_projects)): ?>
                            <p class="text-muted">Belum ada proyek. Upload proyek pertama Anda!</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($my_projects as $project): ?>
                                <div class="list-group-item">
                                    <h6><?php echo htmlspecialchars($project['title']); ?></h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($project['description']); ?></p>
                                    <small class="text-muted">
                                        Status: <span class="badge bg-info"><?php echo $project['status']; ?></span>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Creative Workers -->
                <div class="card glass-card">
                    <div class="card-header">
                        <h5><i class="fas fa-users me-2"></i>Creative Workers</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($creative_workers)): ?>
                            <p class="text-muted">Belum ada creative worker terdaftar.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($creative_workers as $worker): ?>
                                <a href="profile_creative.php?id=<?php echo $worker['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center" 
                                             style="width: 40px; height: 40px;">
                                            <?php echo strtoupper(substr($worker['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($worker['name']); ?></h6>
                                            <small class="text-muted"><?php echo $worker['skills'] ?? 'No skills listed'; ?></small>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>