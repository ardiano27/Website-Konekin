<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'creative_worker') {
    header("Location: projek/login.php");
    exit();
}

require_once '/xampp/htdocs/belajarphp/konekinTugas/projek/config/Database.php';
require_once '/xampp/htdocs/belajarphp/konekinTugas/projek/models/Users.php';
require_once '/xampp/htdocs/belajarphp/konekinTugas/projek/models/project.php';
require_once '/xampp/htdocs/belajarphp/konekinTugas/projek/models/portofolio.php';
require_once '/xampp/htdocs/belajarphp/konekinTugas/projek/models/certificate.php';

$database = new Database();
$db = $database->getConnection();
$userModel = new User($db);
$projectModel = new Project($db);
$portfolioModel = new Portfolio($db);
$certificateModel = new Certificate($db);

$user_id = $_SESSION['user_id'];
$open_projects = $projectModel->getAllOpen();
$my_portfolios = $portfolioModel->getByUser($user_id);
$my_certificates = $certificateModel->getByUser($user_id);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Creative Worker - Konekin</title>
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
                <i class="fas fa-palette me-2"></i>Dashboard Creative Worker
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
            <div class="col-md-3">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-briefcase fa-2x text-primary mb-2"></i>
                        <h3><?php echo count($open_projects); ?></h3>
                        <p class="text-muted">Proyek Tersedia</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-images fa-2x text-info mb-2"></i>
                        <h3><?php echo count($my_portfolios); ?></h3>
                        <p class="text-muted">Portfolio</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card glass-card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-award fa-2x text-warning mb-2"></i>
                        <h3><?php echo count($my_certificates); ?></h3>
                        <p class="text-muted">Sertifikat</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
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
            <!-- Edit Profile & Uploads -->
            <div class="col-lg-6 mb-4">
                <!-- Edit Profile -->
                <div class="card glass-card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-user-edit me-2"></i>Edit Profil</h5>
                    </div>
                    <div class="card-body">
                        <form action="actions/update_profile.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" name="name" class="form-control" value="<?php echo $_SESSION['user_name']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Telepon</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea name="address" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Bio</label>
                                <textarea name="bio" class="form-control" rows="3" placeholder="Ceritakan tentang diri dan keahlian Anda"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Skills (pisahkan dengan koma)</label>
                                <input type="text" name="skills" class="form-control" placeholder="Contoh: Web Design, Graphic Design, Photography">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Update Profil
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Upload Portfolio -->
                <div class="card glass-card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-images me-2"></i>Upload Portfolio</h5>
                    </div>
                    <div class="card-body">
                        <form action="actions/models/portofolio.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Judul Portfolio</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deskripsi</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="category" class="form-select" required>
                                    <option value="design">Design</option>
                                    <option value="development">Development</option>
                                    <option value="photography">Photography</option>
                                    <option value="video">Video</option>
                                    <option value="other">Lainnya</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">File Portfolio</label>
                                <input type="file" name="portfolio_file" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-upload me-2"></i>Upload Portfolio
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Upload Certificate -->
                <div class="card glass-card">
                    <div class="card-header">
                        <h5><i class="fas fa-award me-2"></i>Upload Sertifikat</h5>
                    </div>
                    <div class="card-body">
                        <form action="actions/certificate.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Judul Sertifikat</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Penerbit</label>
                                <input type="text" name="issuer" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal Diterbitkan</label>
                                <input type="date" name="issue_date" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">File Sertifikat</label>
                                <input type="file" name="certificate_file" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL Credential (opsional)</label>
                                <input type="url" name="credential_url" class="form-control" placeholder="https://">
                            </div>
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-upload me-2"></i>Upload Sertifikat
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Available Projects & My Uploads -->
            <div class="col-lg-6">
                <!-- Available Projects -->
                <div class="card glass-card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-briefcase me-2"></i>Proyek Tersedia</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($open_projects)): ?>
                            <p class="text-muted">Belum ada proyek tersedia.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($open_projects as $project): ?>
                                <div class="list-group-item">
                                    <h6><?php echo htmlspecialchars($project['title']); ?></h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($project['description']); ?></p>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Oleh: <?php echo htmlspecialchars($project['user_name']); ?></small>
                                        <small class="text-muted">Budget: Rp <?php echo number_format($project['budget'], 0, ',', '.'); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Portfolio -->
                <div class="card glass-card">
                    <div class="card-header">
                        <h5><i class="fas fa-images me-2"></i>Portfolio Saya</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($my_portfolios)): ?>
                            <p class="text-muted">Belum ada portfolio. Upload portfolio pertama Anda!</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($my_portfolios as $portfolio): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6><?php echo htmlspecialchars($portfolio['title']); ?></h6>
                                            <p class="small text-muted"><?php echo htmlspecialchars($portfolio['category']); ?></p>
                                            <a href="uploads/portfolios/<?php echo $portfolio['file_path']; ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                Lihat
                                            </a>
                                        </div>
                                    </div>
                                </div>
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