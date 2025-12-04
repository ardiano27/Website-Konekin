<?php
include "check_login.php";

$contract_id = $_GET['id'] ?? 0;

if (!$contract_id) {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Ambil data kontrak
$contract_sql = "
    SELECT 
        c.*,
        p.title as project_title,
        p.description as project_description,
        creative.full_name as creative_name,
        creative.email as creative_email,
        umkm.full_name as umkm_name,
        up.business_name as umkm_business_name
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    JOIN users creative ON c.creative_user_id = creative.id
    JOIN users umkm ON c.umkm_user_id = umkm.id
    LEFT JOIN umkm_profiles up ON umkm.id = up.user_id
    WHERE c.id = :contract_id 
    AND (c.creative_user_id = :user_id OR c.umkm_user_id = :user_id)
";

$contract_stmt = $conn->prepare($contract_sql);
$contract_stmt->execute([
    ':contract_id' => $contract_id,
    ':user_id' => $_SESSION['user_id']
]);

$contract = $contract_stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    $_SESSION['error_message'] = "Kontrak tidak ditemukan atau Anda tidak memiliki akses.";
    header("Location: dashboard.php");
    exit;
}

// Ambil progress updates
$progress_sql = "
    SELECT * FROM contract_progress 
    WHERE contract_id = :contract_id 
    ORDER BY created_at DESC
";
$progress_stmt = $conn->prepare($progress_sql);
$progress_stmt->execute([':contract_id' => $contract_id]);
$progress_updates = $progress_stmt->fetchAll(PDO::FETCH_ASSOC);

// Cek apakah sudah ada bukti pembayaran yang diupload
$evidence_sql = "SELECT * FROM payment_evidences WHERE contract_id = :contract_id ORDER BY created_at DESC LIMIT 1";
$evidence_stmt = $conn->prepare($evidence_sql);
$evidence_stmt->execute([':contract_id' => $contract_id]);
$payment_evidence = $evidence_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Kontrak - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .contract-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .payment-info {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 1.5rem;
            border-radius: 8px;
        }
        .progress-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
        }
        .payment-instruction {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .evidence-status {
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .modal-backdrop {
            z-index: 1040;
        }
        .modal {
            z-index: 1050;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Header Kontrak -->
            <div class="contract-header text-center">
                <div class="container">
                    <h1 class="display-5 fw-bold"><?php echo htmlspecialchars($contract['title']); ?></h1>
                    <p class="lead mb-3"><?php echo htmlspecialchars($contract['description']); ?></p>
                    <span class="badge bg-<?php echo match($contract['status']) {
                        'draft' => 'secondary', 'active' => 'success', 'completed' => 'primary', 'cancelled' => 'danger'
                    }; ?> fs-6">
                        <?php echo strtoupper($contract['status']); ?>
                    </span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Progress Pengerjaan</h6>
                                <span class="fw-bold"><?php echo $contract['progress_percentage']; ?>%</span>
                            </div>
                            <div class="progress" style="height: 15px;">
                                <div class="progress-bar bg-success" 
                                     style="width: <?php echo $contract['progress_percentage']; ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Informasi Pembayaran -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0"><i class="fas fa-credit-card me-2"></i>Informasi Pembayaran</h5>
                        </div>
                        <div class="card-body">
                            <div class="payment-info">
                                <h6 class="text-success">Transfer ke Rekening Konekin:</h6>
                                <div class="mb-3">
                                    <strong>Bank:</strong> BCA<br>
                                    <strong>No. Rekening:</strong> 123-456-7890<br>
                                    <strong>Atas Nama:</strong> PT KONEKIN INDONESIA
                                </div>
                                
                                <div class="alert alert-warning">
                                    <small>
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        <strong>WAJIB cantumkan Kode Proyek:</strong><br>
                                        <code class="fs-5"><?php echo $contract['project_code']; ?></code>
                                    </small>
                                </div>

                                <div class="mt-3">
                                    <strong>Total Pembayaran:</strong><br>
                                    <h4 class="text-primary">Rp <?php echo number_format($contract['agreed_budget'], 0, ',', '.'); ?></h4>
                                    
                                    <strong>Status Pembayaran:</strong>
                                    <span class="badge bg-<?php echo match($contract['payment_status']) {
                                        'pending' => 'warning', 'partial' => 'info', 'paid' => 'success'
                                    }; ?> ms-2">
                                        <?php echo strtoupper($contract['payment_status']); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Status Bukti Pembayaran -->
                            <?php if ($payment_evidence): ?>
                            <div class="alert alert-<?php echo match($payment_evidence['status']) {
                                'pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'
                            }; ?> mt-3">
                                <h6><i class="fas fa-receipt me-2"></i>Status Bukti Pembayaran</h6>
                                <p class="mb-1">
                                    <strong>Status:</strong> 
                                    <span class="badge bg-<?php echo match($payment_evidence['status']) {
                                        'pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'
                                    }; ?>">
                                        <?php echo strtoupper($payment_evidence['status']); ?>
                                    </span>
                                </p>
                                <p class="mb-1"><strong>Tanggal Upload:</strong> <?php echo date('d M Y H:i', strtotime($payment_evidence['created_at'])); ?></p>
                                
                                <?php if ($payment_evidence['status'] === 'rejected' && $payment_evidence['admin_notes']): ?>
                                    <p class="mb-1"><strong>Alasan Penolakan:</strong> <?php echo htmlspecialchars($payment_evidence['admin_notes']); ?></p>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#uploadPaymentModal">
                                            <i class="fas fa-upload me-1"></i>Upload Ulang Bukti
                                        </button>
                                    </div>
                                <?php elseif ($payment_evidence['status'] === 'verified'): ?>
                                    <p class="mb-0"><strong>Tanggal Verifikasi:</strong> <?php echo date('d M Y H:i', strtotime($payment_evidence['verified_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Instruksi Pembayaran untuk UMKM -->
                            <?php if ($_SESSION['user_type'] === 'umkm' && $contract['payment_status'] !== 'paid'): ?>
                                <div class="payment-instruction mt-3">
                                    <h6><i class="fas fa-info-circle me-2 text-warning"></i>Instruksi Pembayaran:</h6>
                                    <ol class="small mb-0">
                                        <li>Transfer ke rekening BCA di atas</li>
                                        <li><strong>Cantumkan Kode Proyek: <?php echo $contract['project_code']; ?></strong></li>
                                        <li>Transfer sesuai jumlah: Rp <?php echo number_format($contract['agreed_budget'], 0, ',', '.'); ?></li>
                                        <li>Upload bukti transfer di bawah ini</li>
                                        <li>Admin akan memverifikasi pembayaran dalam 1x24 jam</li>
                                        <li>Status akan otomatis berubah menjadi "PAID" setelah diverifikasi</li>
                                    </ol>
                                </div>
                                
                                <div class="mt-3">
                                    <div class="alert alert-info">
                                        <small>
                                            <i class="fas fa-headset me-1"></i>
                                            <strong>Butuh bantuan?</strong> Hubungi admin: 
                                            <a href="mailto:admin@konekin.com">admin@konekin.com</a> atau 
                                            <a href="https://wa.me/6281234567890">+62 812-3456-7890</a>
                                        </small>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Info untuk Creative Worker -->
                            <?php if ($_SESSION['user_type'] === 'creative'): ?>
                                <div class="mt-3">
                                    <div class="alert alert-<?php echo $contract['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <i class="fas fa-<?php echo $contract['payment_status'] === 'paid' ? 'check-circle' : 'clock'; ?> me-1"></i>
                                        <strong>Status Pembayaran: <?php echo strtoupper($contract['payment_status']); ?></strong>
                                        <?php if ($contract['payment_status'] === 'paid'): ?>
                                            <br><small>Pembayaran telah diverifikasi dan diterima.</small>
                                        <?php else: ?>
                                            <br><small>Menunggu pembayaran dari UMKM dan verifikasi admin.</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Informasi Proyek -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Proyek</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Creative Worker:</strong><br>
                                <?php echo htmlspecialchars($contract['creative_name']); ?>
                            </div>
                            <div class="mb-3">
                                <strong>UMKM:</strong><br>
                                <?php echo htmlspecialchars($contract['umkm_business_name'] ?? $contract['umkm_name']); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Timeline:</strong><br>
                                <?php echo $contract['timeline_days']; ?> hari
                            </div>
                            <div class="mb-3">
                                <strong>Tanggal Mulai:</strong><br>
                                <?php echo date('d M Y', strtotime($contract['start_date'])); ?>
                            </div>
                            <?php if ($contract['end_date']): ?>
                                <div class="mb-3">
                                    <strong>Tanggal Selesai:</strong><br>
                                    <?php echo date('d M Y', strtotime($contract['end_date'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Info Rekening Creative Worker (hanya untuk UMKM) -->
                            <?php if ($_SESSION['user_type'] === 'umkm' && !empty($contract['creative_bank_account'])): ?>
                                <div class="mt-4 pt-3 border-top">
                                    <h6><i class="fas fa-university me-2"></i>Rekening Creative Worker:</h6>
                                    <small class="text-muted">Rekening ini akan digunakan untuk transfer fee setelah proyek selesai</small>
                                    <div class="mt-2 p-2 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($contract['creative_bank_account'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Updates -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-tasks me-2"></i>Update Progress</h5>
                            <?php if ($_SESSION['user_type'] === 'creative' && $contract['status'] === 'active'): ?>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#progressModal">
                                    <i class="fas fa-plus me-1"></i>Update Progress
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (count($progress_updates) > 0): ?>
                                <?php foreach ($progress_updates as $update): ?>
                                    <div class="card progress-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">Progress: <?php echo $update['progress_percentage']; ?>%</h6>
                                                    <?php if ($update['description']): ?>
                                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($update['description'])); ?></p>
                                                    <?php endif; ?>
                                                    <small class="text-muted">
                                                        <?php echo date('d M Y H:i', strtotime($update['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-info">Update</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada update progress.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body text-center">
                            <div class="btn-group" role="group">
                                <a href="messages.php?user_id=<?php echo $_SESSION['user_type'] === 'umkm' ? $contract['creative_user_id'] : $contract['umkm_user_id']; ?>&project_id=<?php echo $contract['project_id']; ?>" 
                                class="btn btn-outline-primary">
                                    <i class="fas fa-envelope me-1"></i>Kirim Pesan
                                </a>
                                
                                <!-- PERUBAHAN DISINI -->
                                <?php if ($_SESSION['user_type'] === 'umkm'): ?>
                                    <a href="projects.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-1"></i>Kembali ke Proyek
                                    </a>
                                <?php else: ?>
                                    <a href="project-detail.php?id=<?php echo $contract['project_id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-1"></i>Kembali ke Proyek
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Tombol Upload Bukti Pembayaran -->
                                <?php if ($_SESSION['user_type'] === 'umkm' && $contract['status'] === 'active' && $contract['payment_status'] === 'pending'): ?>
                                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#uploadPaymentModal">
                                        <i class="fas fa-upload me-1"></i>Upload Bukti Bayar
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($_SESSION['user_type'] === 'umkm' && $contract['status'] === 'active' && $contract['progress_percentage'] >= 100): ?>
                                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#completeModal">
                                        <i class="fas fa-check-circle me-1"></i>Selesaikan Proyek
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ($_SESSION['user_type'] === 'umkm' && $contract['payment_status'] === 'pending'): ?>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Lakukan pembayaran dan upload bukti untuk melanjutkan proyek
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Upload Bukti Pembayaran -->
    <?php if ($_SESSION['user_type'] === 'umkm' && $contract['status'] === 'active' && $contract['payment_status'] === 'pending'): ?>
    <div class="modal fade" id="uploadPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Bukti Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="upload-payment-evidence.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                        
                        <div class="mb-3">
                            <label for="bank_name" class="form-label">Nama Bank Pengirim</label>
                            <input type="text" class="form-control" id="bank_name" name="bank_name" required placeholder="Contoh: BCA, Mandiri, BRI">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="account_number" class="form-label">Nomor Rekening Pengirim</label>
                                <input type="text" class="form-control" id="account_number" name="account_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="account_name" class="form-label">Nama Pemilik Rekening</label>
                                <input type="text" class="form-control" id="account_name" name="account_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="payment_amount" class="form-label">Jumlah Transfer</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                                           value="<?php echo $contract['agreed_budget']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="payment_date" class="form-label">Tanggal Transfer</label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="evidence_file" class="form-label">Bukti Transfer (Gambar/PDF)</label>
                            <input type="file" class="form-control" id="evidence_file" name="evidence_file" 
                                   accept=".jpg,.jpeg,.png,.pdf,.gif" required>
                            <small class="text-muted">Format: JPG, PNG, PDF, GIF. Maksimal 5MB</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Catatan (Opsional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Tambahkan catatan jika perlu..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Pastikan bukti transfer terlihat jelas:<br>
                                1. Nama bank pengirim<br>
                                2. Nomor rekening pengirim<br>
                                3. Nama penerima: PT KONEKIN INDONESIA<br>
                                4. Jumlah transfer<br>
                                5. Kode proyek: <strong><?php echo $contract['project_code']; ?></strong>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Upload Bukti</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Update Progress -->
    <?php if ($_SESSION['user_type'] === 'creative'): ?>
    <div class="modal fade" id="progressModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Progress</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="update-progress.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                        <div class="mb-3">
                            <label for="progress_percentage" class="form-label">Progress (%)</label>
                            <input type="range" class="form-range" id="progress_percentage" name="progress_percentage" 
                                   min="0" max="100" value="<?php echo $contract['progress_percentage']; ?>" 
                                   oninput="document.getElementById('progressValue').innerText = this.value + '%'">
                            <div class="text-center">
                                <span id="progressValue" class="fw-bold"><?php echo $contract['progress_percentage']; ?>%</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi Update (Opsional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Ceritakan perkembangan proyek..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update Progress</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal View Evidence -->
    <div class="modal fade" id="viewEvidenceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bukti Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="evidenceImage" src="" class="img-fluid" style="max-height: 500px; display: none;">
                    <iframe id="evidencePDF" src="" width="100%" height="500px" style="display:none;"></iframe>
                    <div id="evidenceLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Memuat bukti pembayaran...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
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
        // Fungsi untuk melihat bukti pembayaran
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
        
        // Validasi form upload bukti
        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.querySelector('form[action="upload-payment-evidence.php"]');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function(e) {
                    const fileInput = document.getElementById('evidence_file');
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    
                    if (fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        if (file.size > maxSize) {
                            e.preventDefault();
                            alert('Ukuran file terlalu besar. Maksimal 5MB.');
                            return false;
                        }
                    }
                    
                    // Validasi tanggal tidak boleh lebih dari hari ini
                    const paymentDate = document.getElementById('payment_date').value;
                    const today = new Date().toISOString().split('T')[0];
                    if (paymentDate > today) {
                        e.preventDefault();
                        alert('Tanggal transfer tidak boleh lebih dari hari ini.');
                        return false;
                    }
                });
            }
        });
        
        // Preview image sebelum upload
        document.getElementById('evidence_file')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Create preview
                    let preview = document.getElementById('filePreview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.id = 'filePreview';
                        preview.className = 'mt-2';
                        document.getElementById('evidence_file').parentNode.appendChild(preview);
                    }
                    preview.innerHTML = `
                        <div class="alert alert-info p-2">
                            <div class="d-flex align-items-center">
                                <img src="${e.target.result}" class="me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                <div>
                                    <strong>Preview:</strong><br>
                                    <small>${file.name} (${(file.size / 1024).toFixed(2)} KB)</small>
                                </div>
                            </div>
                        </div>
                    `;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>