<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Handle update payment status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $contract_id = $_POST['contract_id'];
    $payment_status = $_POST['payment_status'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    try {
        // Mulai transaksi
        $conn->beginTransaction();
        
        // 1. Update status pembayaran
        $update_sql = "UPDATE contracts SET payment_status = :status, updated_at = NOW() WHERE id = :contract_id";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([
            ':status' => $payment_status,
            ':contract_id' => $contract_id
        ]);
        
        // 2. Log pembaruan
        $log_sql = "INSERT INTO payment_logs (contract_id, admin_id, old_status, new_status, notes) 
                   VALUES (:contract_id, :admin_id, :old_status, :new_status, :notes)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':contract_id' => $contract_id,
            ':admin_id' => $_SESSION['user_id'],
            ':old_status' => $_POST['old_status'],
            ':new_status' => $payment_status,
            ':notes' => $admin_notes
        ]);
        
        // 3. Ambil data kontrak
        $contract_sql = "SELECT creative_user_id, umkm_user_id, title FROM contracts WHERE id = :contract_id";
        $contract_stmt = $conn->prepare($contract_sql);
        $contract_stmt->execute([':contract_id' => $contract_id]);
        $contract = $contract_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($contract) {
            require_once 'notificationsmanager.php';
            $notificationManager = new NotificationManager();
            
            $message = "Status pembayaran untuk proyek '{$contract['title']}' diubah menjadi " . strtoupper($payment_status);
            
            $notificationManager->createNotification(
                $contract['creative_user_id'],
                "Update Pembayaran",
                $message,
                'payment',
                'contract',
                $contract_id
            );
            
            $notificationManager->createNotification(
                $contract['umkm_user_id'],
                "Update Pembayaran",
                $message,
                'payment',
                'contract',
                $contract_id
            );
        }
        
        // Commit transaksi
        $conn->commit();
        
        $_SESSION['success_message'] = "Status pembayaran berhasil diupdate! Notifikasi telah dikirim ke kedua pihak.";
    } catch (Exception $e) {
        // Rollback jika ada error
        $conn->rollBack();
        $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
    }
    
    header("Location: admin-payments.php");
    exit;
}

// Handle filter
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = ["c.id IS NOT NULL"];
$params = [];

if ($filter_status !== 'all') {
    $where_conditions[] = "c.payment_status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($search)) {
    $where_conditions[] = "(c.project_code LIKE :search OR c.title LIKE :search OR creative.full_name LIKE :search OR umkm.full_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

// Get payments data with payment evidences
$payments_sql = "
    SELECT 
        c.id,
        c.uuid,
        c.project_code,
        c.title,
        c.agreed_budget,
        c.payment_status,
        c.progress_percentage,
        c.created_at,
        c.updated_at,
        creative.full_name as creative_name,
        creative.email as creative_email,
        umkm.full_name as umkm_name,
        umkm.email as umkm_email,
        up.business_name as umkm_business,
        cp.creative_bank_account,
        pe.id as evidence_id,
        pe.bank_name,
        pe.account_number,
        pe.account_name,
        pe.payment_amount,
        pe.payment_date,
        pe.evidence_file,
        pe.status as evidence_status,
        pe.notes as evidence_notes,
        pe.admin_notes,
        pe.created_at as evidence_created_at,
        pe.verified_at,
        verifier.full_name as verifier_name
    FROM contracts c
    JOIN users creative ON c.creative_user_id = creative.id
    JOIN users umkm ON c.umkm_user_id = umkm.id
    LEFT JOIN umkm_profiles up ON umkm.id = up.user_id
    LEFT JOIN creative_profiles cp ON creative.id = cp.user_id
    LEFT JOIN payment_evidences pe ON c.id = pe.contract_id
    LEFT JOIN users verifier ON pe.verified_by = verifier.id
    WHERE $where_clause
    ORDER BY c.created_at DESC
";

$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->execute($params);
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial,
        SUM(agreed_budget) as total_value,
        SUM(CASE WHEN payment_status = 'paid' THEN agreed_budget ELSE 0 END) as paid_value
    FROM contracts
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pembayaran - Konekin Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Navbar Styles */
        /* Navbar Styles */
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

        .nav-link-konekin i {
            font-size: 1.1rem;
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
            transform: translateY(-1px);
        }

        .navbar-toggler-custom {
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
        }

        /* Mobile Styles */
        @media (max-width: 991.98px) {
            .konekin-navbar {
                padding: 0.8rem 1rem;
            }
            .navbar-brand-konekin {
                margin-right: 1rem;
            }
            .navbar-collapse-konekin {
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 1rem;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                z-index: 999;
            }

            .navbar-nav-konekin {
                flex-direction: column;
                gap: 0.5rem;
            }

            .nav-link-konekin {
                padding: 1rem;
                border-radius: 10px;
                justify-content: flex-start;
            }

            .user-section {
                margin-left: 0;
                margin-top: 1rem;
                justify-content: center;
                flex-direction: column;
                text-align: center;
            }

            .logout-btn {
                margin-left: 0;
                margin-top: 0.5rem;
                width: 100%;
            }
        }

        /* Badge untuk notifikasi */
        .nav-badge {
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -5px;
            right: -5px;
        }
        .payment-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .payment-card.pending { border-left-color: #ffc107; }
        .payment-card.paid { border-left-color: #28a745; }
        .payment-card.partial { border-left-color: #17a2b8; }
        
        .stat-card { transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        
        .badge-pending { background-color: #ffc107; }
        .badge-paid { background-color: #28a745; }
        .badge-partial { background-color: #17a2b8; }
        
        .search-box {
            max-width: 400px;
        }
        
        .progress-sm {
            height: 8px;
        }
        
        .evidence-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
        }
        
        .evidence-preview {
            max-width: 200px;
            max-height: 150px;
            object-fit: contain;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 5px;
            background: white;
        }
        
        .evidence-info {
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
     <?php include 'navbar-admin.php'; ?>


    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-credit-card me-2"></i>Kelola Pembayaran</h2>
                <p class="text-muted">Kelola dan verifikasi pembayaran dari UMKM</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-receipt fa-2x text-primary mb-2"></i>
                        <h3><?php echo $stats['total']; ?></h3>
                        <p class="text-muted mb-0">Total Transaksi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p class="text-muted mb-0">Menunggu Pembayaran</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h3><?php echo $stats['paid']; ?></h3>
                        <p class="text-muted mb-0">Pembayaran Selesai</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-info mb-2"></i>
                        <h3>Rp <?php echo number_format($stats['paid_value'], 0, ',', '.'); ?></h3>
                        <p class="text-muted mb-0">Total Terbayar</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="status" class="form-label">Filter Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="partial" <?php echo $filter_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="search" class="form-label">Pencarian</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Cari kode proyek, judul, atau nama..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payments List -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Daftar Transaksi</h5>
            </div>
            <div class="card-body">
                <?php if (count($payments) > 0): ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="card payment-card <?php echo $payment['payment_status']; ?> mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($payment['title']); ?></h6>
                                        <small class="text-muted">Kode: <?php echo $payment['project_code']; ?></small>
                                        <div class="mt-2">
                                            <span class="badge badge-<?php echo $payment['payment_status']; ?>">
                                                <?php echo strtoupper($payment['payment_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <strong>Rp <?php echo number_format($payment['agreed_budget'], 0, ',', '.'); ?></strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">UMKM:</small><br>
                                        <strong><?php echo htmlspecialchars($payment['umkm_business'] ?? $payment['umkm_name']); ?></strong>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted">Creative:</small><br>
                                        <strong><?php echo htmlspecialchars($payment['creative_name']); ?></strong>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#detailModal<?php echo $payment['id']; ?>">
                                                <i class="fas fa-eye me-1"></i>Detail
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateModal<?php echo $payment['id']; ?>">
                                                <i class="fas fa-edit me-1"></i>Update
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <small class="text-muted">Progress Pengerjaan:</small>
                                        <div class="progress progress-sm mt-1">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $payment['progress_percentage']; ?>%">
                                                <?php echo $payment['progress_percentage']; ?>%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Bukti Pembayaran -->
                                <?php if (!empty($payment['evidence_file'])): ?>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="alert alert-info p-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <small class="text-muted">Bukti Pembayaran:</small>
                                                    <div class="d-flex align-items-center gap-2 mt-1">
                                                        <i class="fas fa-receipt"></i>
                                                        <span class="evidence-info">
                                                            <?php echo htmlspecialchars($payment['bank_name']); ?> - 
                                                            <?php echo htmlspecialchars($payment['account_number']); ?> 
                                                            (<?php echo htmlspecialchars($payment['account_name']); ?>)
                                                        </span>
                                                        <span class="badge bg-<?php echo $payment['evidence_status'] === 'verified' ? 'success' : ($payment['evidence_status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                            <?php echo strtoupper($payment['evidence_status'] ?? 'pending'); ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($payment['evidence_created_at']): ?>
                                                    <small class="text-muted d-block">
                                                        Upload: <?php echo date('d M Y H:i', strtotime($payment['evidence_created_at'])); ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewEvidence('<?php echo $payment['evidence_file']; ?>', '<?php echo $payment['project_code']; ?>')">
                                                        <i class="fas fa-eye me-1"></i>Lihat Bukti
                                                    </button>
                                                    <?php if (($payment['evidence_status'] === 'pending' || empty($payment['evidence_status'])) && $payment['payment_status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#verifyModal<?php echo $payment['id']; ?>">
                                                            <i class="fas fa-check me-1"></i>Verifikasi
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Detail Modal -->
                        <div class="modal fade" id="detailModal<?php echo $payment['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Detail Transaksi</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Informasi Proyek</h6>
                                                <p><strong>Judul:</strong> <?php echo htmlspecialchars($payment['title']); ?></p>
                                                <p><strong>Kode Proyek:</strong> <?php echo $payment['project_code']; ?></p>
                                                <p><strong>Budget:</strong> Rp <?php echo number_format($payment['agreed_budget'], 0, ',', '.'); ?></p>
                                                <p><strong>Progress:</strong> <?php echo $payment['progress_percentage']; ?>%</p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Informasi Pembayaran</h6>
                                                <p><strong>Status:</strong> 
                                                    <span class="badge badge-<?php echo $payment['payment_status']; ?>">
                                                        <?php echo strtoupper($payment['payment_status']); ?>
                                                    </span>
                                                </p>
                                                <p><strong>Dibuat:</strong> <?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></p>
                                                <p><strong>Diupdate:</strong> <?php echo date('d M Y H:i', strtotime($payment['updated_at'])); ?></p>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Informasi UMKM</h6>
                                                <p><strong>Nama:</strong> <?php echo htmlspecialchars($payment['umkm_name']); ?></p>
                                                <p><strong>Bisnis:</strong> <?php echo htmlspecialchars($payment['umkm_business'] ?? '-'); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['umkm_email']); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Informasi Creative Worker</h6>
                                                <p><strong>Nama:</strong> <?php echo htmlspecialchars($payment['creative_name']); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['creative_email']); ?></p>
                                                <p><strong>Rekening:</strong> 
                                                    <?php echo $payment['creative_bank_account'] ? nl2br(htmlspecialchars($payment['creative_bank_account'])) : '-'; ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($payment['evidence_file'])): ?>
                                        <hr>
                                        <div class="row">
                                            <div class="col-12">
                                                <h6>Informasi Bukti Pembayaran</h6>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>Bank Pengirim:</strong> <?php echo htmlspecialchars($payment['bank_name']); ?></p>
                                                        <p><strong>No. Rekening:</strong> <?php echo htmlspecialchars($payment['account_number']); ?></p>
                                                        <p><strong>Nama Pemilik:</strong> <?php echo htmlspecialchars($payment['account_name']); ?></p>
                                                        <p><strong>Jumlah Transfer:</strong> Rp <?php echo number_format($payment['payment_amount'], 0, ',', '.'); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Tanggal Transfer:</strong> <?php echo date('d M Y', strtotime($payment['payment_date'])); ?></p>
                                                        <p><strong>Status:</strong> 
                                                            <span class="badge bg-<?php echo $payment['evidence_status'] === 'verified' ? 'success' : ($payment['evidence_status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                                <?php echo strtoupper($payment['evidence_status'] ?? 'pending'); ?>
                                                            </span>
                                                        </p>
                                                        <?php if ($payment['verified_at']): ?>
                                                        <p><strong>Tanggal Verifikasi:</strong> <?php echo date('d M Y H:i', strtotime($payment['verified_at'])); ?></p>
                                                        <?php endif; ?>
                                                        <?php if ($payment['verifier_name']): ?>
                                                        <p><strong>Diverifikasi oleh:</strong> <?php echo htmlspecialchars($payment['verifier_name']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if ($payment['admin_notes']): ?>
                                                <div class="alert alert-warning mt-2">
                                                    <strong>Catatan Admin:</strong><br>
                                                    <?php echo htmlspecialchars($payment['admin_notes']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Update Payment Status Modal -->
                        <div class="modal fade" id="updateModal<?php echo $payment['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Status Pembayaran</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="contract_id" value="<?php echo $payment['id']; ?>">
                                            <input type="hidden" name="old_status" value="<?php echo $payment['payment_status']; ?>">
                                            
                                            <div class="mb-3">
                                                <label for="payment_status<?php echo $payment['id']; ?>" class="form-label">Status Pembayaran</label>
                                                <select class="form-select" id="payment_status<?php echo $payment['id']; ?>" name="payment_status" required>
                                                    <option value="pending" <?php echo $payment['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="paid" <?php echo $payment['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                    <option value="partial" <?php echo $payment['payment_status'] === 'partial' ? 'selected' : ''; ?>>Partial</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="admin_notes<?php echo $payment['id']; ?>" class="form-label">Catatan Admin (Opsional)</label>
                                                <textarea class="form-control" id="admin_notes<?php echo $payment['id']; ?>" name="admin_notes" rows="3" placeholder="Tambahkan catatan untuk perubahan status..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" name="update_payment" class="btn btn-primary">Update Status</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Verify Evidence Modal -->
                        <?php if (($payment['evidence_status'] === 'pending' || empty($payment['evidence_status'])) && $payment['payment_status'] === 'pending'): ?>
                        <div class="modal fade" id="verifyModal<?php echo $payment['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Verifikasi Bukti Pembayaran</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="verify-payment-evidence.php" method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="evidence_id" value="<?php echo $payment['evidence_id']; ?>">
                                            <input type="hidden" name="contract_id" value="<?php echo $payment['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Status Verifikasi</label>
                                                <div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="status" id="verify<?php echo $payment['id']; ?>" value="verified" checked>
                                                        <label class="form-check-label" for="verify<?php echo $payment['id']; ?>">Verifikasi</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="status" id="reject<?php echo $payment['id']; ?>" value="rejected">
                                                        <label class="form-check-label" for="reject<?php echo $payment['id']; ?>">Tolak</label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="admin_notes" class="form-label">Catatan Admin</label>
                                                <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                                          placeholder="Berikan catatan untuk verifikasi ini..."><?php echo htmlspecialchars($payment['admin_notes'] ?? ''); ?></textarea>
                                            </div>
                                            
                                            <div class="alert alert-info">
                                                <small>
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <strong>Catatan:</strong><br>
                                                    - Jika diverifikasi, status pembayaran akan otomatis berubah menjadi "PAID"<br>
                                                    - Jika ditolak, UMKM dapat mengupload bukti baru
                                                </small>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Verifikasi</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-credit-card fa-4x text-muted mb-3"></i>
                        <h5>Tidak ada transaksi ditemukan</h5>
                        <p class="text-muted">Tidak ada data transaksi yang sesuai dengan filter yang dipilih.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal untuk melihat bukti -->
    <div class="modal fade" id="viewEvidenceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bukti Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="evidenceLoading" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Memuat bukti pembayaran...</p>
                    </div>
                    <img id="evidenceImage" src="" class="img-fluid" style="max-height: 500px; display: none;">
                    <iframe id="evidencePDF" src="" width="100%" height="500px" style="display:none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages Toast -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1055">
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <strong class="me-auto"><i class="fas fa-check-circle me-1"></i> Sukses</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?php echo $_SESSION['success_message']; ?>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['success_message']); endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1055">
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-danger text-white">
                <strong class="me-auto"><i class="fas fa-exclamation-circle me-1"></i> Error</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?php echo $_SESSION['error_message']; ?>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['error_message']); endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto refresh every 30 seconds untuk update real-time
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        
        // Function to view evidence
        function viewEvidence(filePath, projectCode) {
            const extension = filePath.split('.').pop().toLowerCase();
            const imageElement = document.getElementById('evidenceImage');
            const pdfElement = document.getElementById('evidencePDF');
            const loadingElement = document.getElementById('evidenceLoading');
            
            // Reset display
            imageElement.style.display = 'none';
            pdfElement.style.display = 'none';
            loadingElement.style.display = 'block';
            
            const modal = new bootstrap.Modal(document.getElementById('viewEvidenceModal'));
            modal.show();
            
            // Set timeout untuk simulasi loading
            setTimeout(function() {
                if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
                    imageElement.src = filePath;
                    imageElement.style.display = 'block';
                    pdfElement.style.display = 'none';
                } else if (extension === 'pdf') {
                    pdfElement.src = filePath + '#view=FitH';
                    pdfElement.style.display = 'block';
                    imageElement.style.display = 'none';
                }
                loadingElement.style.display = 'none';
            }, 500);
        }
        
        // Auto-hide toast messages
        document.addEventListener('DOMContentLoaded', function() {
            var toastElList = [].slice.call(document.querySelectorAll('.toast'));
            var toastList = toastElList.map(function(toastEl) {
                return new bootstrap.Toast(toastEl, { delay: 5000 });
            });
            toastList.forEach(toast => toast.show());
        });
        
        // Validate verification form
        document.addEventListener('DOMContentLoaded', function() {
            const verifyForms = document.querySelectorAll('form[action="verify-payment-evidence.php"]');
            verifyForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const adminNotes = this.querySelector('textarea[name="admin_notes"]');
                    const rejectRadio = this.querySelector('input[value="rejected"]');
                    
                    if (rejectRadio && rejectRadio.checked && (!adminNotes.value || adminNotes.value.trim() === '')) {
                        e.preventDefault();
                        alert('Harap berikan catatan alasan penolakan.');
                        adminNotes.focus();
                        return false;
                    }
                });
            });
        });
        
        // Preview image in modals
        document.addEventListener('DOMContentLoaded', function() {
            // Add preview for evidence file in verify modal
            const verifyModals = document.querySelectorAll('[id^="verifyModal"]');
            verifyModals.forEach(modal => {
                const modalElement = new bootstrap.Modal(modal);
                modal.addEventListener('show.bs.modal', function() {
                    const contractId = this.id.replace('verifyModal', '');
                    const filePath = document.querySelector(`#detailModal${contractId} .evidence-preview`)?.src;
                    if (filePath) {
                        const previewContainer = this.querySelector('.evidence-preview-container');
                        if (previewContainer) {
                            previewContainer.innerHTML = `
                                <div class="text-center mb-3">
                                    <img src="${filePath}" class="evidence-preview" alt="Preview Bukti">
                                    <p class="text-muted mt-1">Preview Bukti Pembayaran</p>
                                </div>
                            `;
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>