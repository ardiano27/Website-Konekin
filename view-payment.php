<?php
include "check_login.php";

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$project_id) {
    header("Location: projects.php");
    exit;
}

$sql = "
    SELECT 
        p.*,
        c.id as contract_id,
        c.title as contract_title,
        c.agreed_budget,
        c.payment_status,
        c.progress_percentage,
        c.start_date,
        c.end_date,
        c.project_code,
        c.creative_bank_account,
        u.full_name as creative_name,
        u.email as creative_email,
        cp.creative_bank_account as creative_bank_details,
        umkm_u.full_name as umkm_name,
        umkm_u.email as umkm_email,
        up.business_name,
        pe.bank_name,
        pe.account_number,
        pe.account_name,
        pe.payment_amount,
        pe.payment_date,
        pe.evidence_file,
        pe.status as payment_evidence_status,
        pe.verified_at,
        pl.notes as payment_log_notes,
        pl.created_at as payment_log_date
    FROM projects p
    LEFT JOIN contracts c ON p.id = c.project_id AND c.status IN ('active', 'completed')
    LEFT JOIN users u ON c.creative_user_id = u.id
    LEFT JOIN creative_profiles cp ON u.id = cp.user_id
    LEFT JOIN users umkm_u ON p.umkm_user_id = umkm_u.id
    LEFT JOIN umkm_profiles up ON umkm_u.id = up.user_id
    LEFT JOIN payment_evidences pe ON c.id = pe.contract_id AND pe.status = 'verified'
    LEFT JOIN payment_logs pl ON c.id = pl.contract_id AND pl.new_status = 'paid'
    WHERE p.id = :project_id 
      AND p.umkm_user_id = :user_id
    ORDER BY pl.created_at DESC
    LIMIT 1
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':project_id' => $project_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $project = null;
}

if (!$project) {
    header("Location: projects.php");
    exit;
}

function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function format_date($date) {
    if (!$date) return '-';
    return date('d M Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($project['title']); ?> - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2c5ea3;
            --primary-light: rgba(62, 127, 213, 0.1);
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --success-light: rgba(40, 167, 69, 0.1);
            --warning-color: #ffc107;
            --warning-light: rgba(255, 193, 7, 0.1);
            --danger-color: #dc3545;
            --danger-light: rgba(220, 53, 69, 0.1);
            --info-color: #17a2b8;
            --info-light: rgba(23, 162, 184, 0.1);
            --bg-light: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.12);
            --shadow-xl: 0 12px 40px rgba(0, 0, 0, 0.15);
            --border-radius: 16px;
            --border-radius-sm: 12px;
            --border-radius-lg: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        .main-content {
            padding: 20px;
            padding-top: 10px;
        }

        .invoice-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .invoice-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .invoice-header-content {
            position: relative;
            z-index: 2;
        }

        .invoice-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .invoice-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Invoice Card */
        .invoice-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .invoice-card-header {
            background: var(--primary-light);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .invoice-card-body {
            padding: 1.5rem;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            gap: 0.5rem;
        }

        .status-paid {
            background: var(--success-light);
            color: var(--success-color);
            border: 2px solid rgba(40, 167, 69, 0.2);
        }

        .status-pending {
            background: rgba(214, 137, 16, 0.1);
            color: #d68910;
            border: 2px solid rgba(214, 137, 16, 0.2);
        }

        .status-partial {
            background: rgba(41, 128, 185, 0.1);
            color: #2980b9;
            border: 2px solid rgba(41, 128, 185, 0.2);
        }

        /* Info Cards */
        .info-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-sm);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }

        .info-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-card-content {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Payment Details */
        .payment-details {
            background: var(--bg-light);
            border-radius: var(--border-radius-sm);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .payment-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .payment-label {
            color: var(--text-muted);
        }

        .payment-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        .payment-total {
            color: var(--primary-color);
        }

        /* Evidence Section */
        .evidence-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--border-color);
        }

        .evidence-image {
            max-width: 100%;
            border-radius: var(--border-radius-sm);
            border: 2px solid var(--border-color);
            margin-top: 1rem;
        }

        /* Actions */
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(62, 127, 213, 0.3);
            color: white;
        }

        .btn-outline-primary {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-color);
            border-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            border-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .invoice-header {
                padding: 1.5rem;
                text-align: center;
            }
            
            .invoice-title {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
            }
            
            .main-content {
                padding: 0;
            }
            
            .invoice-header {
                background: white !important;
                color: black !important;
                box-shadow: none;
            }
            
            .action-buttons,
            .btn-print {
                display: none !important;
            }
            
            .invoice-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="invoice-header">
                <div class="invoice-header-content">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="invoice-title">
                                <i class="fas fa-receipt me-2"></i>Invoice Pembayaran
                            </h1>
                            <p class="invoice-subtitle">
                                Detail pembayaran untuk proyek: <?php echo htmlspecialchars($project['title']); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="d-flex flex-column align-items-md-end">
                                <span class="status-badge status-<?php echo $project['payment_status'] ?? 'pending'; ?> mb-2">
                                    <i class="fas fa-<?php echo $project['payment_status'] == 'paid' ? 'check-circle' : 'clock'; ?>"></i>
                                    <?php echo strtoupper($project['payment_status'] ?? 'PENDING'); ?>
                                </span>
                                <span class="text-white-50">
                                    Invoice #<?php echo $project['project_code'] ?? 'N/A'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Invoice Details -->
                    <div class="invoice-card">
                        <div class="invoice-card-header">
                            <h3 class="mb-0">
                                <i class="fas fa-file-invoice-dollar me-2"></i>Detail Invoice
                            </h3>
                        </div>
                        <div class="invoice-card-body">
                            <!-- Project Info -->
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <div class="info-card">
                                        <div class="info-card-title">Dari</div>
                                        <div class="info-card-content">
                                            <?php echo htmlspecialchars($project['business_name'] ?? 'UMKM'); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($project['umkm_email'] ?? ''); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-card">
                                        <div class="info-card-title">Kepada</div>
                                        <div class="info-card-content">
                                            <?php echo htmlspecialchars($project['creative_name'] ?? 'Creative Worker'); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($project['creative_email'] ?? ''); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-4 mb-3">
                                    <div class="info-card">
                                        <div class="info-card-title">Proyek</div>
                                        <div class="info-card-content"><?php echo htmlspecialchars($project['title']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="info-card">
                                        <div class="info-card-title">Tanggal Mulai</div>
                                        <div class="info-card-content"><?php echo format_date($project['start_date']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="info-card">
                                        <div class="info-card-title">Tanggal Selesai</div>
                                        <div class="info-card-content"><?php echo format_date($project['end_date']); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-details">
                                <h5 class="mb-3">
                                    <i class="fas fa-money-bill-wave me-2"></i>Rincian Pembayaran
                                </h5>
                                
                                <div class="payment-row">
                                    <span class="payment-label">Nilai Kontrak</span>
                                    <span class="payment-value"><?php echo format_currency($project['agreed_budget']); ?></span>
                                </div>
                
                                <div class="payment-row">
                                    <span class="payment-label">Biaya Platform (10%)</span>
                                    <span class="payment-value"><?php echo format_currency($project['agreed_budget'] * 0.1); ?></span>
                                </div>
                                
                                <div class="payment-row">
                                    <span class="payment-label">Total Transfer</span>
                                    <span class="payment-value payment-total"><?php echo format_currency($project['agreed_budget'] * 0.9); ?></span>
                                </div>
                                
                                <?php if ($project['payment_amount']): ?>
                                <div class="payment-row">
                                    <span class="payment-label">Jumlah Dibayarkan</span>
                                    <span class="payment-value"><?php echo format_currency($project['payment_amount']); ?></span>
                                </div>
                                
                                <div class="payment-row">
                                    <span class="payment-label">Tanggal Pembayaran</span>
                                    <span class="payment-value"><?php echo format_date($project['payment_date']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="info-card">
                                <div class="info-card-title">
                                    <i class="fas fa-university me-2"></i>Rekening Tujuan
                                </div>
                                <div class="info-card-content">
                                    <?php if ($project['creative_bank_account'] || $project['creative_bank_details']): ?>
                                        <p>Silakan transfer ke rekening berikut:</p>
                                        <div class="bg-light p-3 rounded mt-2">
                                            <strong>Bank:</strong> <?php echo htmlspecialchars($project['creative_bank_details'] ?? 'Informasi Bank Belum Tersedia'); ?><br>
                                            <?php if ($project['bank_name']): ?>
                                            <strong>Nama Bank:</strong> <?php echo htmlspecialchars($project['bank_name']); ?><br>
                                            <strong>No. Rekening:</strong> <?php echo htmlspecialchars($project['account_number']); ?><br>
                                            <strong>Atas Nama:</strong> <?php echo htmlspecialchars($project['account_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">Informasi rekening belum tersedia. Silakan hubungi creative worker.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Evidence -->
                    <?php if ($project['evidence_file']): ?>
                    <div class="invoice-card mt-4">
                        <div class="invoice-card-header">
                            <h3 class="mb-0">
                                <i class="fas fa-file-image me-2"></i>Bukti Pembayaran
                            </h3>
                        </div>
                        <div class="invoice-card-body">
                            <div class="evidence-section">
                                <p>Bukti pembayaran telah diupload dan diverifikasi.</p>
                                
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Status:</strong> Telah Diverifikasi
                                    <?php if ($project['verified_at']): ?>
                                        <br><small class="text-muted">Diverifikasi pada: <?php echo date('d M Y H:i', strtotime($project['verified_at'])); ?></small>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="<?php echo htmlspecialchars($project['evidence_file']); ?>" 
                                   target="_blank" 
                                   class="btn btn-outline-primary mb-3">
                                    <i class="fas fa-external-link-alt me-2"></i>Lihat Bukti Transfer
                                </a>
                                
                                <?php if ($project['payment_log_notes']): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-sticky-note me-2"></i>
                                    <strong>Catatan Admin:</strong> <?php echo htmlspecialchars($project['payment_log_notes']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($project['payment_status'] == 'paid'): ?>
                    <div class="invoice-card mt-4">
                        <div class="invoice-card-header">
                            <h3 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Informasi Pembayaran
                            </h3>
                        </div>
                        <div class="invoice-card-body">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Status Pembayaran:</strong> LUNAS
                                <?php if ($project['payment_log_date']): ?>
                                    <br><small class="text-muted">Dibayar pada: <?php echo date('d M Y H:i', strtotime($project['payment_log_date'])); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <!-- Summary Card -->
                    <div class="invoice-card">
                        <div class="invoice-card-header">
                            <h3 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Ringkasan
                            </h3>
                        </div>
                        <div class="invoice-card-body">
                            <!-- Progress -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Progress Pengerjaan</span>
                                    <span class="fw-bold"><?php echo $project['progress_percentage'] ?? 0; ?>%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" 
                                         role="progressbar" 
                                         style="width: <?php echo $project['progress_percentage'] ?? 0; ?>%; background: linear-gradient(90deg, var(--primary-color), var(--info-color));"
                                         aria-valuenow="<?php echo $project['progress_percentage'] ?? 0; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                            </div>

                            <!-- Status Timeline -->
                            <div class="mb-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-history me-2"></i>Timeline Status
                                </h6>
                                <div class="timeline">
                                    <div class="timeline-item <?php echo $project['payment_status'] == 'paid' ? 'active' : ''; ?>">
                                        <div class="timeline-icon">
                                            <i class="fas <?php echo $project['payment_status'] == 'paid' ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6>Pembayaran Lunas</h6>
                                            <small class="text-muted">
                                                <?php echo $project['payment_status'] == 'paid' ? 'Selesai' : 'Menunggu'; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="timeline-item <?php echo $project['progress_percentage'] >= 100 ? 'active' : ''; ?>">
                                        <div class="timeline-icon">
                                            <i class="fas <?php echo $project['progress_percentage'] >= 100 ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6>Proyek Selesai</h6>
                                            <small class="text-muted">
                                                <?php echo $project['progress_percentage'] >= 100 ? 'Selesai' : 'Dalam Pengerjaan'; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="timeline-item <?php echo $project['payment_status'] == 'paid' ? 'active' : ''; ?>">
                                        <div class="timeline-icon">
                                            <i class="fas <?php echo $project['payment_status'] == 'paid' ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6>Review & Rating</h6>
                                            <small class="text-muted">
                                                <?php echo $project['payment_status'] == 'paid' ? 'Tersedia' : 'Belum Tersedia'; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="action-buttons">
                                <?php if ($project['payment_status'] != 'paid'): ?>
                                    <a href="contract-details.php?id=<?php echo $project['contract_id']; ?>" 
                                       class="btn btn-success w-100">
                                        <i class="fas fa-credit-card me-2"></i>Bayar Sekarang
                                    </a>
                                <?php endif; ?>
                                
                                <button onclick="window.print()" class="btn btn-outline-primary w-100 btn-print">
                                    <i class="fas fa-print me-2"></i>Cetak Invoice
                                </button>
                                
                                <a href="projects.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Proyek
                                </a>
                                
                                <?php if ($project['payment_status'] == 'paid' && $project['progress_percentage'] >= 100): ?>
                                    <a href="project-review.php?id=<?php echo $project['project_id']; ?>" 
                                       class="btn btn-warning w-100">
                                        <i class="fas fa-star me-2"></i>Beri Review
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Support Card -->
                    <div class="invoice-card mt-4">
                        <div class="invoice-card-header">
                            <h3 class="mb-0">
                                <i class="fas fa-headset me-2"></i>Butuh Bantuan?
                            </h3>
                        </div>
                        <div class="invoice-card-body">
                            <p class="text-muted">Jika ada kendala dengan pembayaran atau invoice, hubungi kami:</p>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-envelope text-primary me-2"></i>
                                    <small>support@konekin.com</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-phone text-primary me-2"></i>
                                    <small>021-12345678</small>
                                </li>
                                <li>
                                    <i class="fas fa-clock text-primary me-2"></i>
                                    <small>Senin - Jumat, 09:00 - 17:00 WIB</small>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Print functionality
        function printInvoice() {
            window.print();
        }

        // Copy invoice number
        function copyInvoiceNumber() {
            const invoiceNumber = "<?php echo $project['project_code'] ?? ''; ?>";
            if (invoiceNumber) {
                navigator.clipboard.writeText(invoiceNumber)
                    .then(() => {
                        alert('Nomor invoice berhasil disalin: ' + invoiceNumber);
                    })
                    .catch(err => {
                        console.error('Gagal menyalin: ', err);
                    });
            }
        }

        // Auto-refresh page if payment is pending
        <?php if ($project['payment_status'] == 'pending'): ?>
        setInterval(() => {
            // Check for payment status update every 30 seconds
            fetch('check-payment-status.php?project_id=<?php echo $project_id; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'paid') {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
        }, 30000);
        <?php endif; ?>

        // Add timeline styles
        document.addEventListener('DOMContentLoaded', function() {
            const style = document.createElement('style');
            style.textContent = `
                .timeline {
                    position: relative;
                    padding-left: 20px;
                }
                .timeline::before {
                    content: '';
                    position: absolute;
                    left: 7px;
                    top: 0;
                    bottom: 0;
                    width: 2px;
                    background: var(--border-color);
                }
                .timeline-item {
                    position: relative;
                    margin-bottom: 20px;
                }
                .timeline-icon {
                    position: absolute;
                    left: -20px;
                    top: 0;
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    background: white;
                    border: 2px solid var(--border-color);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .timeline-item.active .timeline-icon {
                    border-color: var(--primary-color);
                    background: var(--primary-color);
                }
                .timeline-icon i {
                    font-size: 8px;
                    color: var(--border-color);
                }
                .timeline-item.active .timeline-icon i {
                    color: white;
                }
                .timeline-content {
                    padding-left: 10px;
                }
                .timeline-content h6 {
                    font-size: 0.875rem;
                    margin-bottom: 2px;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>