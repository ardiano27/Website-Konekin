<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config/Database.php';

$database = new DatabaseConnection();
$db = $database->getConnection();

// Get filter parameters
$report_type = $_GET['report_type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? 'all';

// Function to get overview report
function getOverviewReport($db, $date_from, $date_to) {
    $data = [];
    
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as total, user_type FROM users WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY user_type");
    $stmt->execute([$date_from, $date_to]);
    $data['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total projects
    $stmt = $db->prepare("SELECT COUNT(*) as total, status FROM projects WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status");
    $stmt->execute([$date_from, $date_to]);
    $data['projects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total revenue
    $stmt = $db->prepare("SELECT SUM(agreed_budget) as total_revenue FROM contracts WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status = 'paid'");
    $stmt->execute([$date_from, $date_to]);
    $data['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $data;
}

// Function to get project report
function getProjectReport($db, $date_from, $date_to, $status_filter) {
    $sql = "SELECT p.*, u.full_name as umkm_name, up.business_name,
            (SELECT COUNT(*) FROM proposals WHERE project_id = p.id) as proposal_count
            FROM projects p
            LEFT JOIN users u ON p.umkm_user_id = u.id
            LEFT JOIN umkm_profiles up ON u.id = up.user_id
            WHERE DATE(p.created_at) BETWEEN ? AND ?";
    
    $params = [$date_from, $date_to];
    
    if ($status_filter !== 'all') {
        $sql .= " AND p.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get payment report
function getPaymentReport($db, $date_from, $date_to) {
    $sql = "SELECT c.*, p.title as project_title, 
            u1.full_name as creative_name, u2.full_name as umkm_name,
            pe.payment_amount, pe.payment_date, pe.status as evidence_status
            FROM contracts c
            LEFT JOIN projects p ON c.project_id = p.id
            LEFT JOIN users u1 ON c.creative_user_id = u1.id
            LEFT JOIN users u2 ON c.umkm_user_id = u2.id
            LEFT JOIN payment_evidences pe ON c.id = pe.contract_id
            WHERE DATE(c.created_at) BETWEEN ? AND ?
            ORDER BY c.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$date_from, $date_to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get user activity report
function getUserActivityReport($db, $date_from, $date_to) {
    $sql = "SELECT u.id, u.full_name, u.email, u.user_type, u.created_at,
            (SELECT COUNT(*) FROM projects WHERE umkm_user_id = u.id) as total_projects,
            (SELECT COUNT(*) FROM proposals WHERE creative_user_id = u.id) as total_proposals,
            (SELECT COUNT(*) FROM contracts WHERE creative_user_id = u.id OR umkm_user_id = u.id) as total_contracts
            FROM users u
            WHERE DATE(u.created_at) BETWEEN ? AND ?
            ORDER BY u.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$date_from, $date_to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get report data based on type
$reportData = [];
switch ($report_type) {
    case 'projects':
        $reportData = getProjectReport($db, $date_from, $date_to, $status_filter);
        break;
    case 'payments':
        $reportData = getPaymentReport($db, $date_from, $date_to);
        break;
    case 'users':
        $reportData = getUserActivityReport($db, $date_from, $date_to);
        break;
    default:
        $reportData = getOverviewReport($db, $date_from, $date_to);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Admin - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Navbar Styles - Same as dashboard-admin.php */
        .konekin-navbar {
            background: linear-gradient(135deg, #3E7FD5 0%, #3E7FD5 100%);
            padding: 0.8rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand-konekin {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.5rem;
            margin-right: 3rem;
        }

        .navbar-brand-konekin i {
            margin-right: 10px;
            font-size: 1.8rem;
        }

        .navbar-nav-konekin {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-item-konekin {
            position: relative;
        }

        .nav-link-konekin {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.7rem 1.2rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .nav-link-konekin:hover,
        .nav-link-konekin.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: 2rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            color: white;
        }

        .user-name {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-type {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            margin-left: 1rem;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Report Styles */
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .filter-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 1rem;
        }

        .stat-box h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }

        .stat-box p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }

        .table-responsive {
            margin-top: 1rem;
        }

        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .konekin-navbar {
                display: none !important;
            }
            
            .report-card {
                box-shadow: none;
                page-break-inside: avoid;
            }
            
            .filter-card {
                display: none;
            }
            
            body {
                background: white;
            }
            
            .report-header {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        .btn-export {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="konekin-navbar navbar navbar-expand-lg no-print">
        <div class="container-fluid">
            <a class="navbar-brand-konekin" href="dashboard-admin.php">
                <i class="fas fa-handshake"></i>
                Konekin Admin
            </a>

            <button class="navbar-toggler-custom d-lg-none" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarContent">
                <i class="fas fa-bars"></i>
            </button>

            <div class="collapse navbar-collapse justify-content-between" id="navbarContent">
                <ul class="navbar-nav-konekin">
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="dashboard-admin.php">
                            <i class="fas fa-home me-1"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="admin-users.php">
                            <i class="fas fa-users me-1"></i>
                            <span>Kelola Pengguna</span>
                        </a>
                    </li>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="admin-projects.php">
                            <i class="fas fa-project-diagram me-1"></i>
                            <span>Kelola Proyek</span>
                        </a>
                    </li>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin active" href="admin-reports.php">
                            <i class="fas fa-chart-bar me-1"></i>
                            <span>Laporan</span>
                        </a>
                    </li>
                    <li class="nav-item-konekin">
                        <a class="nav-link-konekin" href="admin-settings.php">
                            <i class="fas fa-cog me-1"></i>
                            <span>Pengaturan</span>
                        </a>
                    </li>
                </ul>

                <div class="user-section">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <a href="admin-profile.php" style="text-decoration: none; color: inherit;">
                            <h6 class="user-name"><?php echo $_SESSION['full_name'] ?? 'Admin'; ?></h6>
                        </a>
                        <small class="user-type">Administrator</small>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Report Header -->
        <div class="report-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-chart-line me-2"></i>Laporan Sistem</h2>
                    <p class="mb-0">Periode: <?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?></p>
                </div>
                <div class="no-print">
                    <button onclick="window.print()" class="btn btn-export me-2">
                        <i class="fas fa-print me-2"></i>Cetak Laporan
                    </button>
                    <button onclick="exportToExcel()" class="btn btn-export">
                        <i class="fas fa-file-excel me-2"></i>Export Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card no-print">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Jenis Laporan</label>
                    <select name="report_type" class="form-select" onchange="this.form.submit()">
                        <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                        <option value="projects" <?php echo $report_type === 'projects' ? 'selected' : ''; ?>>Laporan Proyek</option>
                        <option value="payments" <?php echo $report_type === 'payments' ? 'selected' : ''; ?>>Laporan Pembayaran</option>
                        <option value="users" <?php echo $report_type === 'users' ? 'selected' : ''; ?>>Laporan Pengguna</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <?php if ($report_type === 'projects'): ?>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all">Semua Status</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Report Content -->
        <div class="report-card">
            <?php if ($report_type === 'overview'): ?>
                <!-- Overview Report -->
                <h4 class="mb-4"><i class="fas fa-chart-pie me-2"></i>Laporan Overview</h4>
                
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h3><?php echo array_sum(array_column($reportData['users'], 'total')); ?></h3>
                            <p>Total Pengguna Baru</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?php echo array_sum(array_column($reportData['projects'], 'total')); ?></h3>
                            <p>Total Proyek</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3>Rp <?php echo number_format($reportData['revenue']['total_revenue'] ?? 0, 0, ',', '.'); ?></h3>
                            <p>Total Pendapatan</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h5>Distribusi Pengguna</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Tipe Pengguna</th>
                                    <th>Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['users'] as $user): ?>
                                <tr>
                                    <td><?php echo ucfirst($user['user_type']); ?></td>
                                    <td><?php echo $user['total']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Status Proyek</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['projects'] as $project): ?>
                                <tr>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?></td>
                                    <td><?php echo $project['total']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type === 'projects'): ?>
                <!-- Projects Report -->
                <h4 class="mb-4"><i class="fas fa-project-diagram me-2"></i>Laporan Proyek</h4>
                <div class="table-responsive">
                    <table class="table table-hover" id="reportTable">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Judul Proyek</th>
                                <th>UMKM</th>
                                <th>Kategori</th>
                                <th>Budget</th>
                                <th>Proposal</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $project): ?>
                            <tr>
                                <td>#<?php echo $project['id']; ?></td>
                                <td><?php echo htmlspecialchars($project['title']); ?></td>
                                <td><?php echo htmlspecialchars($project['business_name'] ?? $project['umkm_name']); ?></td>
                                <td><span class="badge bg-info"><?php echo ucfirst($project['category']); ?></span></td>
                                <td>Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?> - <?php echo number_format($project['budget_range_max'], 0, ',', '.'); ?></td>
                                <td><?php echo $project['proposal_count']; ?> proposal</td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'draft' => 'secondary',
                                        'open' => 'primary',
                                        'in_progress' => 'warning',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass[$project['status']]; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($project['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($report_type === 'payments'): ?>
                <!-- Payments Report -->
                <h4 class="mb-4"><i class="fas fa-money-bill-wave me-2"></i>Laporan Pembayaran</h4>
                <div class="table-responsive">
                    <table class="table table-hover" id="reportTable">
                        <thead class="table-light">
                            <tr>
                                <th>Kontrak ID</th>
                                <th>Proyek</th>
                                <th>Kreator</th>
                                <th>UMKM</th>
                                <th>Budget</th>
                                <th>Status Pembayaran</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalRevenue = 0;
                            foreach ($reportData as $payment): 
                                if ($payment['payment_status'] === 'paid') {
                                    $totalRevenue += $payment['agreed_budget'];
                                }
                            ?>
                            <tr>
                                <td>#<?php echo $payment['id']; ?></td>
                                <td><?php echo htmlspecialchars($payment['project_title']); ?></td>
                                <td><?php echo htmlspecialchars($payment['creative_name']); ?></td>
                                <td><?php echo htmlspecialchars($payment['umkm_name']); ?></td>
                                <td>Rp <?php echo number_format($payment['agreed_budget'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php
                                    $paymentClass = [
                                        'pending' => 'warning',
                                        'partial' => 'info',
                                        'paid' => 'success'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $paymentClass[$payment['payment_status']]; ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($payment['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <th colspan="4" class="text-end">Total Pendapatan (Paid):</th>
                                <th colspan="3">Rp <?php echo number_format($totalRevenue, 0, ',', '.'); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            <?php elseif ($report_type === 'users'): ?>
                <!-- Users Report -->
                <h4 class="mb-4"><i class="fas fa-users me-2"></i>Laporan Aktivitas Pengguna</h4>
                <div class="table-responsive">
                    <table class="table table-hover" id="reportTable">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Tipe</th>
                                <th>Total Proyek</th>
                                <th>Total Proposal</th>
                                <th>Total Kontrak</th>
                                <th>Terdaftar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $user): ?>
                            <tr>
                                <td>#<?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['user_type'] === 'creative' ? 'primary' : 'success'; ?>">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['total_projects']; ?></td>
                                <td><?php echo $user['total_proposals']; ?></td>
                                <td><?php echo $user['total_contracts']; ?></td>
                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Report Footer -->
        <div class="report-card">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0"><strong>Digenerate pada:</strong> <?php echo date('d F Y H:i:s'); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0"><strong>Oleh:</strong> <?php echo $_SESSION['full_name']; ?> (Admin)</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToExcel() {
            const table = document.getElementById('reportTable');
            if (!table) {
                alert('Tidak ada data untuk diexport');
                return;
            }
            
            let html = table.outerHTML;
            const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'laporan_konekin_' + new Date().getTime() + '.xls';
            link.click();
        }
    </script>
</body>
</html>