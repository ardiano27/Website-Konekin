<?php
include "check_login.php";
require_once 'config/Database.php';

// Validasi akses hanya untuk Creative
if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

$database = new DatabaseConnection();
$conn = $database->getConnection();

// Query Utama (Kode SQL tidak diubah)
$sql = "
    SELECT 
        p.*,
        pr.title as project_title,
        pr.description as project_description,
        pr.budget_range_min,
        pr.budget_range_max,
        pr.category as project_category,
        pr.deadline as project_deadline,
        u.full_name as umkm_name,
        up.business_name,
        up.business_logo_url,
        (SELECT COUNT(*) FROM proposals p2 WHERE p2.project_id = pr.id) as total_competitors
    FROM proposals p
    JOIN projects pr ON p.project_id = pr.id
    JOIN users u ON pr.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    WHERE p.creative_user_id = :user_id
    ORDER BY p.submitted_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung Statistik (Kode tidak diubah)
$stats = [
    'total' => count($proposals),
    'waiting' => 0,
    'accepted' => 0,
    'rejected' => 0,
    'potential_income' => 0
];

foreach ($proposals as $prop) {
    if (in_array($prop['status'], ['submitted', 'viewed'])) $stats['waiting']++;
    if ($prop['status'] == 'accepted') {
        $stats['accepted']++;
        $stats['potential_income'] += $prop['proposed_budget'];
    }
    if ($prop['status'] == 'rejected' || $prop['status'] == 'cancelled') $stats['rejected']++;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Saya - Konekin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/my-proposals.css">
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid p-0">
            
            <div class="header-section mb-4">
                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
                    <div>
                        <h1 class="page-title">Proposal Saya</h1>
                        <p class="text-muted mb-0">Pantau status penawaran dan kelola proyek Anda di sini.</p>
                    </div>
                    <a href="find-projects.php" class="btn btn-glow">
                        <i class="fas fa-plus me-2"></i>Cari Proyek Baru
                    </a>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="icon-box bg-blue-soft">
                                <i class="fas fa-folder-open"></i>
                            </div>
                            <div>
                                <h5><?php echo $stats['total']; ?></h5>
                                <span>Total Proposal</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="icon-box bg-warning-soft">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div>
                                <h5><?php echo $stats['waiting']; ?></h5>
                                <span>Menunggu</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="icon-box bg-success-soft">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <h5><?php echo $stats['accepted']; ?></h5>
                                <span>Diterima</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="icon-box bg-purple-soft">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div>
                                <h5><?php echo ($stats['potential_income'] > 0) ? number_format($stats['potential_income'] / 1000, 0) . 'k' : '0'; ?></h5>
                                <span>Potensi Pendapatan</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="toolbar-section">
                    <div class="row align-items-center g-3">
                        <div class="col-md-7">
                            <div class="nav nav-pills custom-pills" id="statusFilters">
                                <button class="nav-link active" data-filter="all">Semua</button>
                                <button class="nav-link" data-filter="submitted">Menunggu</button>
                                <button class="nav-link" data-filter="accepted">Diterima</button>
                                <button class="nav-link" data-filter="rejected">Ditolak</button>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" class="form-control" placeholder="Cari nama proyek atau klien...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4 pb-4" id="proposalGrid">
                <?php if (count($proposals) > 0): ?>
                    <?php foreach ($proposals as $proposal): ?>
                        <?php 
                            $status = $proposal['status'];
                            
                            // Logika Warna Badge & Border Accent
                            $badgeClass = match($status) {
                                'accepted' => 'bg-success-subtle text-success',
                                'rejected', 'cancelled' => 'bg-danger-subtle text-danger',
                                'viewed' => 'bg-info-subtle text-info',
                                default => 'bg-warning-subtle text-warning'
                            };

                            // Class khusus untuk border atas kartu
                            $borderClass = match($status) {
                                'accepted' => 'card-border-accepted',
                                'rejected', 'cancelled' => 'card-border-rejected',
                                'viewed' => 'card-border-viewed',
                                default => 'card-border-submitted'
                            };
                            
                            $statusLabel = match($status) {
                                'submitted' => 'Menunggu Respon',
                                'viewed' => 'Dilihat Klien',
                                'accepted' => 'Diterima',
                                'rejected' => 'Ditolak',
                                'cancelled' => 'Dibatalkan',
                                default => ucfirst($status)
                            };

                            $catIcon = match($proposal['project_category']) {
                                'website', 'development' => 'fa-code',
                                'logo', 'design' => 'fa-pen-nib',
                                'video' => 'fa-video',
                                'marketing' => 'fa-bullhorn',
                                default => 'fa-layer-group'
                            };
                        ?>

                        <div class="col-md-6 col-lg-4 proposal-item" 
                             data-status="<?php echo ($status == 'viewed') ? 'submitted' : $status; ?>"
                             data-title="<?php echo strtolower($proposal['project_title'] . ' ' . $proposal['business_name']); ?>">
                            
                            <div class="card proposal-card <?php echo $borderClass; ?>">
                                <div class="card-header-custom">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span class="badge rounded-pill <?php echo $badgeClass; ?> status-pill">
                                            <i class="fas fa-circle me-1 small-dot"></i> <?php echo $statusLabel; ?>
                                        </span>
                                        <div class="category-icon" title="<?php echo ucfirst($proposal['project_category']); ?>">
                                            <i class="fas <?php echo $catIcon; ?>"></i>
                                        </div>
                                    </div>
                                    <h5 class="card-title text-truncate-2" title="<?php echo htmlspecialchars($proposal['project_title']); ?>">
                                        <?php echo htmlspecialchars($proposal['project_title']); ?>
                                    </h5>
                                </div>

                                <div class="card-body pt-0 flex-grow-1">
                                    <div class="client-info d-flex align-items-center mb-3">
                                        <?php if (!empty($proposal['business_logo_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($proposal['business_logo_url']); ?>" alt="Logo" class="avatar-sm">
                                        <?php else: ?>
                                            <div class="avatar-sm avatar-placeholder bg-light text-primary border">
                                                <?php echo substr($proposal['business_name'] ?? 'U', 0, 1); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="ms-3">
                                            <p class="mb-0 fw-bold text-dark small text-truncate" style="max-width: 180px;">
                                                <?php echo htmlspecialchars($proposal['business_name'] ?? $proposal['umkm_name']); ?>
                                            </p>
                                            <small class="text-muted" style="font-size: 0.75rem;">
                                                <i class="fas fa-clock me-1"></i> Diajukan: <?php echo date('d M', strtotime($proposal['submitted_at'])); ?>
                                            </small>
                                        </div>
                                    </div>

                                    <div class="info-grid">
                                        <div class="info-item">
                                            <small>Budget Anda</small>
                                            <span class="fw-bold text-primary">
                                                Rp <?php echo number_format($proposal['proposed_budget'], 0, ',', '.'); ?>
                                            </span>
                                        </div>
                                        <div class="info-item text-end">
                                            <small>Estimasi</small>
                                            <span class="fw-bold text-dark">
                                                <?php echo $proposal['timeline_days']; ?> Hari
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer bg-transparent border-0 pt-3">
                                    <div class="d-grid gap-2">
                                        <a href="view-proposal.php?id=<?php echo $proposal['id']; ?>" class="btn btn-outline-primary btn-sm rounded-3 py-2">
                                            Lihat Detail
                                        </a>
                                        <?php if ($status === 'submitted'): ?>
                                            <a href="edit-proposal.php?id=<?php echo $proposal['id']; ?>" class="btn btn-light btn-sm text-muted rounded-3 hover-danger py-2">
                                                <i class="fas fa-edit me-1"></i> Edit Proposal
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                
                <div id="noResults" class="col-12 text-center py-5 d-none">
                    <img src="assets/img/empty-box.svg" alt="Kosong" style="width: 120px; opacity: 0.6; margin-bottom: 20px;" onerror="this.src='https://cdn-icons-png.flaticon.com/512/7486/7486754.png'">
                    <h5 class="fw-bold text-dark">Tidak ada proposal ditemukan</h5>
                    <p class="text-muted">Coba ubah filter status atau kata kunci pencarian Anda.</p>
                </div>

                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state-wrapper">
                            <div class="empty-content text-center">
                                <div class="icon-circle mb-4">
                                    <i class="fas fa-rocket fa-4x text-primary"></i>
                                </div>
                                <h3 class="fw-bold mb-3">Mulai Perjalanan Karirmu!</h3>
                                <p class="text-muted mb-4" style="max-width: 500px; margin: 0 auto;">
                                    Anda belum mengajukan proposal apapun. <br>Temukan proyek menarik dan ajukan penawaran terbaik Anda sekarang.
                                </p>
                                <a href="find-projects.php" class="btn btn-primary btn-lg px-5 shadow-sm rounded-pill">
                                    Cari Proyek Sekarang
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/my-proposals.js"></script>
</body>
</html>