<?php
// submit-work.php
include "check_login.php";

if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$contract_id = $_GET['contract_id'] ?? 0;

// Ambil data contract dan project
$sql = "
    SELECT 
        p.*,
        c.id as contract_id,
        c.title as contract_title,
        c.agreed_budget,
        c.progress_percentage,
        c.status as contract_status,
        c.start_date,
        c.end_date,
        c.payment_status,
        u.full_name as umkm_name,
        u.email as umkm_email,
        up.business_name
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    JOIN users u ON c.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    WHERE c.id = :contract_id 
    AND c.creative_user_id = :user_id
    AND c.status = 'active'
";

$project = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':contract_id' => $contract_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

if (!$project) {
    header("Location: all-projects.php");
    exit;
}

// Hanya validasi pembayaran harus lunas
if ($project['payment_status'] !== 'paid') {
    $_SESSION['error_message'] = "Pembayaran harus sudah lunas sebelum submit hasil akhir";
    header("Location: project-detail.php?id=" . $project['id']);
    exit;
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $work_title = $_POST['work_title'] ?? '';
    $work_description = $_POST['work_description'] ?? '';
    $work_url = $_POST['work_url'] ?? '';
    $delivery_notes = $_POST['delivery_notes'] ?? '';
    $final_files = $_FILES['final_files'] ?? [];
    $preview_images = $_FILES['preview_images'] ?? [];
    
    // Validasi
    if (empty($work_title)) {
        $error_message = "Judul hasil kerja tidak boleh kosong";
    } elseif (empty($final_files['name'][0])) {
        $error_message = "Harap unggah setidaknya satu file hasil akhir";
    } else {
        try {
            $conn->beginTransaction();
            
            // Upload final files
            $final_file_urls = [];
            $upload_dir = 'uploads/final-work/' . $contract_id . '/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Upload files utama
            for ($i = 0; $i < count($final_files['name']); $i++) {
                if ($final_files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . uniqid() . '_' . basename($final_files['name'][$i]);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($final_files['tmp_name'][$i], $file_path)) {
                        $final_file_urls[] = [
                            'url' => $file_path,
                            'name' => $final_files['name'][$i],
                            'size' => $final_files['size'][$i],
                            'type' => $final_files['type'][$i]
                        ];
                    }
                }
            }
            
            // Upload preview images
            $preview_image_urls = [];
            for ($i = 0; $i < count($preview_images['name']); $i++) {
                if ($preview_images['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . uniqid() . '_preview_' . basename($preview_images['name'][$i]);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($preview_images['tmp_name'][$i], $file_path)) {
                        $preview_image_urls[] = $file_path;
                    }
                }
            }
            
            // PERUBAHAN PENTING: Hanya update progress ke 100% TIDAK update status contract dan project
            if ($project['progress_percentage'] < 100) {
                // Insert progress update otomatis menjadi 100%
                $progress_sql = "
                    INSERT INTO contract_progress 
                    (contract_id, creative_user_id, progress_percentage, description, attachment_urls, created_at)
                    VALUES (:contract_id, :user_id, 100, :description, NULL, NOW())
                ";
                
                $stmt = $conn->prepare($progress_sql);
                $stmt->execute([
                    ':contract_id' => $contract_id,
                    ':user_id' => $_SESSION['user_id'],
                    ':description' => 'Progress menjadi 100% saat submit hasil akhir'
                ]);
                
                // Update contract progress hanya menjadi 100%, TIDAK ubah status
                $update_progress_sql = "
                    UPDATE contracts 
                    SET progress_percentage = 100,
                        updated_at = NOW()
                    WHERE id = :contract_id
                ";
                
                $stmt = $conn->prepare($update_progress_sql);
                $stmt->execute([':contract_id' => $contract_id]);
            }
            
            // Insert work submission
            $insert_sql = "
                INSERT INTO work_submissions 
                (contract_id, creative_user_id, work_title, work_description, work_url, 
                 final_files, preview_images, delivery_notes, status, submitted_at)
                VALUES (:contract_id, :user_id, :work_title, :work_description, :work_url, 
                        :final_files, :preview_images, :delivery_notes, 'submitted', NOW())
            ";
            
            $stmt = $conn->prepare($insert_sql);
            $stmt->execute([
                ':contract_id' => $contract_id,
                ':user_id' => $_SESSION['user_id'],
                ':work_title' => $work_title,
                ':work_description' => $work_description,
                ':work_url' => $work_url,
                ':final_files' => json_encode($final_file_urls),
                ':preview_images' => json_encode($preview_image_urls),
                ':delivery_notes' => $delivery_notes
            ]);
            
            $submission_id = $conn->lastInsertId();
            
            // PERUBAHAN PENTING: TIDAK update contract status menjadi 'completed'
            // Contract tetap status 'active' sampai UMKM menerima hasil
            
            // PERUBAHAN PENTING: TIDAK update project status menjadi 'completed'
            // Project tetap status 'in_progress' sampai UMKM menerima hasil
            
            // Create notification for UMKM tentang progress otomatis 100%
            if ($project['progress_percentage'] < 100) {
                $progress_notif_sql = "
                    INSERT INTO notifications 
                    (user_id, title, message, notification_type, related_entity_type, related_entity_id, created_at)
                    VALUES (:umkm_id, :title, :message, 'project', 'contract', :contract_id, NOW())
                ";
                
                $stmt = $conn->prepare($progress_notif_sql);
                $stmt->execute([
                    ':umkm_id' => $project['umkm_user_id'],
                    ':title' => 'Progress Proyek 100%',
                    ':message' => 'Progress proyek \"' . $project['title'] . '\" menjadi 100% saat creative worker submit hasil akhir.',
                    ':contract_id' => $contract_id
                ]);
            }
            
            // Create notification for UMKM tentang submission hasil
            $notification_sql = "
                INSERT INTO notifications 
                (user_id, title, message, notification_type, related_entity_type, related_entity_id, created_at)
                VALUES (:umkm_id, :title, :message, 'project', 'contract', :contract_id, NOW())
            ";
            
            $stmt = $conn->prepare($notification_sql);
            $stmt->execute([
                ':umkm_id' => $project['umkm_user_id'],
                ':title' => 'Hasil Proyek Dikirim',
                ':message' => 'Creative ' . $_SESSION['full_name'] . ' telah mengirimkan hasil akhir proyek \"' . $project['title'] . '\". Silakan review dan konfirmasi penerimaan.',
                ':contract_id' => $contract_id
            ]);
            
            // Send email notification to UMKM
            // (Kode untuk mengirim email bisa ditambahkan di sini)
            
            $conn->commit();
            
            $success_message = "Hasil kerja berhasil disubmit! Progress menjadi 100%. Menunggu review dari UMKM.";
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = "Terjadi kesalahan: " . $e->getMessage();
            error_log("Error submitting work: " . $e->getMessage());
        }
    }
}

// Cek apakah sudah ada submission sebelumnya
$check_submission_sql = "
    SELECT * FROM work_submissions 
    WHERE contract_id = :contract_id AND status != 'cancelled'
    ORDER BY submitted_at DESC LIMIT 1
";

$existing_submission = [];
try {
    $stmt = $conn->prepare($check_submission_sql);
    $stmt->execute([':contract_id' => $contract_id]);
    $existing_submission = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Hasil Akhir - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #549efeff;
            --primary-dark: #82a8db;
            --accent-color: #FFC300;
            --light-bg: #f8f9fa;
            --text-dark: #343a40;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow-light: 0 2px 10px rgba(0,0,0,0.08);
            --shadow-medium: 0 5px 15px rgba(0,0,0,0.1);
            --border-radius: 12px;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: var(--text-dark);
        }
        
        .main-content {
            padding: 10px 0px 0px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-medium);
        }
        
        .detail-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-light);
        }
        
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 3rem;
            text-align: center;
            background: var(--light-bg);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .file-upload-area:hover, .file-upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(84, 158, 254, 0.05);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .file-list {
            margin-top: 1rem;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(84, 158, 254, 0.1);
            border-radius: 8px;
            color: var(--primary-color);
        }
        
        .file-size {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .remove-file {
            color: #dc3545;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .remove-file:hover {
            background: rgba(220, 53, 69, 0.1);
        }
        
        .preview-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin: 0.5rem;
            border: 2px solid var(--border-color);
            transition: transform 0.3s;
        }
        
        .preview-image:hover {
            transform: scale(1.05);
            border-color: var(--primary-color);
        }
        
        .progress-indicator {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-bg);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        
        .step-number.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .step-number.completed {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .step-label.active {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .progress-line {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            height: 2px;
            background: var(--border-color);
            z-index: 0;
        }
        
        .progress-fill {
            position: absolute;
            top: 20px;
            left: 20px;
            height: 2px;
            background: var(--primary-color);
            z-index: 0;
            transition: width 0.3s;
        }
        
        .info-box {
            background: rgba(84, 158, 254, 0.05);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1.5rem;
        }
        
        .warning-box {
            background: rgba(255, 193, 7, 0.1);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border-left: 4px solid #ffc107;
            margin-bottom: 1.5rem;
        }
        
        .progress-bar-custom {
            height: 10px;
            border-radius: 5px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .progress-fill-custom {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            border-radius: 5px;
            transition: width 0.5s ease;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-submitted {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-accepted {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2"><i class="fas fa-paper-plane me-2"></i>Submit Hasil Akhir</h1>
                        <p class="mb-0">Kirim hasil akhir proyek <?php echo htmlspecialchars($project['title']); ?></p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-light me-2">
                            <i class="fas fa-arrow-left me-1"></i>Kembali ke Detail
                        </a>
                        <a href="all-projects.php" class="btn btn-light">
                            <i class="fas fa-list me-1"></i>Semua Proyek
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Progress Indicator -->
            <div class="detail-card">
                <div class="progress-indicator">
                    <div class="progress-line"></div>
                    <?php
                    $progress_width = $project['payment_status'] === 'paid' ? '66%' : '33%';
                    if ($project['payment_status'] === 'paid' && $project['progress_percentage'] >= 100) {
                        $progress_width = '100%';
                    } elseif ($project['payment_status'] === 'paid') {
                        $progress_width = '66%';
                    }
                    ?>
                    <div class="progress-fill" style="width: <?php echo $progress_width; ?>"></div>
                    
                    <div class="progress-step">
                        <div class="step-number completed">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="step-label">Proyek Aktif</div>
                    </div>
                    
                    <div class="progress-step">
                        <div class="step-number <?php echo $project['payment_status'] === 'paid' ? 'completed' : ''; ?>">
                            <?php echo $project['payment_status'] === 'paid' ? '<i class="fas fa-check"></i>' : '2'; ?>
                        </div>
                        <div class="step-label <?php echo $project['payment_status'] === 'paid' ? 'active' : ''; ?>">
                            Pembayaran <?php echo $project['payment_status'] === 'paid' ? 'Lunas' : 'Pending'; ?>
                        </div>
                    </div>
                    
                    <div class="progress-step">
                        <div class="step-number active">
                            3
                        </div>
                        <div class="step-label active">Submit Hasil</div>
                    </div>
                    
                    <div class="progress-step">
                        <div class="step-number">
                            4
                        </div>
                        <div class="step-label">Review UMKM</div>
                    </div>
                </div>
                
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-3">Submit Hasil Akhir</h4>
                        <p class="text-muted mb-0">
                            <?php if ($project['progress_percentage'] < 100): ?>
                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                Progress saat ini <?php echo $project['progress_percentage']; ?>%. Progress akan menjadi 100% saat Anda submit hasil akhir.
                            <?php else: ?>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Progress sudah mencapai 100%. Anda dapat melanjutkan submit hasil akhir.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="d-flex flex-column">
                            <?php if ($project['payment_status'] === 'paid'): ?>
                                <span class="badge bg-success fs-6 mb-2">
                                    <i class="fas fa-check-circle me-1"></i>Pembayaran Lunas
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning fs-6 mb-2">
                                    <i class="fas fa-clock me-1"></i>Menunggu Pembayaran
                                </span>
                            <?php endif; ?>
                            <div class="mb-2">
                                <small class="text-muted">Progress Saat Ini:</small>
                                <div class="progress-bar-custom mt-1">
                                    <div class="progress-fill-custom" style="width: <?php echo $project['progress_percentage']; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $project['progress_percentage']; ?>%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($existing_submission): ?>
                <div class="alert alert-info">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle fa-2x me-3"></i>
                        <div>
                            <h5 class="mb-1">Hasil Telah Dikirim Sebelumnya</h5>
                            <p class="mb-0">
                                Anda sudah mengirimkan hasil pada 
                                <?php echo date('d M Y H:i', strtotime($existing_submission['submitted_at'])); ?>. 
                                Status: 
                                <span class="badge status-<?php echo $existing_submission['status']; ?>">
                                    <?php 
                                    $status_labels = [
                                        'submitted' => 'Menunggu Review',
                                        'reviewed' => 'Dalam Review',
                                        'accepted' => 'Diterima',
                                        'rejected' => 'Ditolak',
                                        'revised' => 'Perlu Revisi'
                                    ];
                                    echo $status_labels[$existing_submission['status']] ?? ucfirst($existing_submission['status']);
                                    ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div>
                            <h5 class="mb-1">Submit Berhasil!</h5>
                            <p class="mb-0"><?php echo $success_message; ?></p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-success me-2">
                            <i class="fas fa-eye me-1"></i>Lihat Detail Proyek
                        </a>
                        <a href="all-projects.php" class="btn btn-success">
                            <i class="fas fa-list me-1"></i>Kembali ke Proyek
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($project['payment_status'] !== 'paid'): ?>
                    <div class="alert alert-warning">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                            <div>
                                <h5 class="mb-1">Pembayaran Belum Lunas</h5>
                                <p class="mb-0">Anda dapat mengisi form submit hasil, namun proses submit hanya akan berhasil jika pembayaran sudah lunas.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($project['progress_percentage'] < 100): ?>
                    <div class="warning-box">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-info-circle text-warning me-2 mt-1"></i>
                            <div>
                                <h6 class="mb-2">Perhatian: Progress Belum 100%</h6>
                                <p class="mb-2">Progress saat ini hanya <?php echo $project['progress_percentage']; ?>%.</p>
                                <p class="mb-0">
                                    <strong>Dengan submit hasil akhir:</strong>
                                    <ul class="mb-0">
                                        <li>Progress akan otomatis menjadi 100%</li>
                                        <li>UMKM akan diberitahu bahwa hasil telah dikirim</li>
                                        <li>Hasil Anda akan menunggu review dari UMKM</li>
                                        <li>UMKM dapat menerima, meminta revisi, atau menolak hasil</li>
                                        <li>Proyek akan selesai hanya setelah UMKM menerima hasil</li>
                                    </ul>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Main Form -->
                    <div class="col-lg-8">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-card">
                            <h4 class="mb-3">Form Submit Hasil Kerja</h4>
                            
                            <div class="info-box">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-lightbulb text-warning me-2 mt-1"></i>
                                    <div>
                                        <h6 class="mb-2">Tips Submit Hasil:</h6>
                                        <ul class="mb-0">
                                            <li>Sertakan semua file hasil kerja dalam format yang sesuai</li>
                                            <li>Unggah screenshot/preview untuk memudahkan review</li>
                                            <li>Berikan penjelasan lengkap tentang hasil kerja</li>
                                            <li>Pastikan semua file dapat diakses dan terbuka dengan baik</li>
                                            <li>Setelah submit, Anda masih bisa mengirim revisi jika diminta UMKM</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" id="submitWorkForm">
                                <div class="mb-4">
                                    <label for="work_title" class="form-label fw-semibold">Judul Hasil Kerja <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="work_title" 
                                           name="work_title" 
                                           value="<?php echo htmlspecialchars($project['title']); ?> - Final Result"
                                           placeholder="Contoh: Website UMKM XYZ - Final Version"
                                           required>
                                    <div class="form-text">Berikan judul yang jelas dan deskriptif</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="work_description" class="form-label fw-semibold">Deskripsi Hasil Kerja <span class="text-danger">*</span></label>
                                    <textarea class="form-control" 
                                              id="work_description" 
                                              name="work_description" 
                                              rows="5" 
                                              placeholder="Jelaskan secara detail hasil kerja yang telah diselesaikan, fitur-fitur yang ada, dan hal-hal penting lainnya..."
                                              required><?php echo htmlspecialchars($project['description']); ?> - Hasil akhir telah selesai sesuai permintaan.</textarea>
                                    <div class="form-text">Deskripsikan apa saja yang telah Anda kerjakan dan hasilnya</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="work_url" class="form-label fw-semibold">URL Hasil Kerja (Opsional)</label>
                                    <input type="url" 
                                           class="form-control" 
                                           id="work_url" 
                                           name="work_url" 
                                           placeholder="https://example.com/project-result"
                                           value="<?php echo htmlspecialchars($_POST['work_url'] ?? ''); ?>">
                                    <div class="form-text">Jika hasil kerja berupa website atau online portfolio</div>
                                </div>
                                
                                <!-- Final Files Upload -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">File Hasil Akhir <span class="text-danger">*</span></label>
                                    <div class="file-upload-area" id="finalFilesDropzone">
                                        <div class="upload-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <h5>Seret & Lepas File</h5>
                                        <p class="text-muted">atau klik untuk memilih file</p>
                                        <p class="small text-muted">Maksimal 10 file, 50MB per file</p>
                                        <input type="file" 
                                               class="form-control d-none" 
                                               id="final_files" 
                                               name="final_files[]" 
                                               multiple
                                               accept=".zip,.rar,.7z,.pdf,.doc,.docx,.xls,.xlsx,.psd,.ai,.fig,.sketch,.jpg,.jpeg,.png,.gif,.mp4,.mov,.avi,.html,.css,.js">
                                        <button type="button" class="btn btn-outline-primary mt-2" onclick="document.getElementById('final_files').click()">
                                            <i class="fas fa-folder-open me-2"></i>Pilih File
                                        </button>
                                    </div>
                                    
                                    <div id="finalFilesList" class="file-list"></div>
                                    <div id="finalFilesError" class="text-danger small mt-2"></div>
                                </div>
                                
                                <!-- Preview Images Upload -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Gambar Preview (Opsional)</label>
                                    <div class="file-upload-area" id="previewImagesDropzone">
                                        <div class="upload-icon">
                                            <i class="fas fa-images"></i>
                                        </div>
                                        <h5>Seret & Lepas Gambar</h5>
                                        <p class="text-muted">atau klik untuk memilih gambar</p>
                                        <p class="small text-muted">Maksimal 5 gambar, 10MB per gambar</p>
                                        <input type="file" 
                                               class="form-control d-none" 
                                               id="preview_images" 
                                               name="preview_images[]" 
                                               multiple
                                               accept="image/*">
                                        <button type="button" class="btn btn-outline-secondary mt-2" onclick="document.getElementById('preview_images').click()">
                                            <i class="fas fa-image me-2"></i>Pilih Gambar
                                        </button>
                                    </div>
                                    
                                    <div id="previewImagesList" class="d-flex flex-wrap mt-3"></div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="delivery_notes" class="form-label fw-semibold">Catatan Pengiriman</label>
                                    <textarea class="form-control" 
                                              id="delivery_notes" 
                                              name="delivery_notes" 
                                              rows="3" 
                                              placeholder="Tambahkan catatan khusus untuk UMKM, seperti cara mengakses file, password (jika ada), atau instruksi khusus lainnya..."></textarea>
                                    <div class="form-text">Catatan ini hanya untuk UMKM</div>
                                </div>
                                
                                <div class="info-box">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-exclamation-triangle text-warning me-2 mt-1"></i>
                                        <div>
                                            <h6 class="mb-2">Alur Setelah Submit:</h6>
                                            <ul class="mb-0">
                                                <li><strong>Progress menjadi 100%</strong> - proyek masih aktif</li>
                                                <li><strong>UMKM mereview hasil</strong> - dalam 7 hari kerja</li>
                                                <li><strong>Hasil diterima</strong> - proyek selesai, rating bertambah</li>
                                                <li><strong>Revisi diminta</strong> - Anda bisa kirim revisi</li>
                                                <li><strong>Hasil ditolak</strong> - admin akan meninjau kasus</li>
                                                <li>Proyek hanya selesai setelah UMKM menerima hasil</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($project['progress_percentage'] < 100): ?>
                                    <div class="warning-box mb-4">
                                        <div class="d-flex align-items-start">
                                            <i class="fas fa-exclamation-circle text-warning me-2 mt-1"></i>
                                            <div>
                                                <h6 class="mb-2">Konfirmasi Submit</h6>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="confirmProgress" required>
                                                    <label class="form-check-label" for="confirmProgress">
                                                        Saya mengerti bahwa progress akan menjadi 100% dan hasil akan menunggu review dari UMKM. 
                                                        Proyek hanya akan selesai setelah UMKM menerima hasil.
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Batal
                                    </a>
                                    <button type="submit" class="btn btn-success btn-lg px-4" id="submitButton"
                                            <?php echo $project['payment_status'] !== 'paid' ? 'disabled' : ''; ?>>
                                        <i class="fas fa-paper-plane me-2"></i>
                                        Submit Hasil Kerja
                                    </button>
                                </div>
                                
                                <?php if ($project['payment_status'] !== 'paid'): ?>
                                    <div class="alert alert-warning mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Tombol submit akan aktif setelah pembayaran lunas.
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Project Summary -->
                        <div class="detail-card">
                            <h5 class="mb-3">Ringkasan Proyek</h5>
                            <div class="mb-3">
                                <label class="form-label text-muted">Proyek</label>
                                <p class="fw-semibold"><?php echo htmlspecialchars($project['title']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">UMKM</label>
                                <p class="fw-semibold"><?php echo htmlspecialchars($project['business_name']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Budget</label>
                                <p class="fw-semibold">Rp <?php echo number_format($project['agreed_budget'], 0, ',', '.'); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Progress Saat Ini</label>
                                <div class="d-flex align-items-center">
                                    <div class="progress-bar-custom flex-grow-1 me-2">
                                        <div class="progress-fill-custom" style="width: <?php echo $project['progress_percentage']; ?>%"></div>
                                    </div>
                                    <span class="fw-semibold"><?php echo $project['progress_percentage']; ?>%</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Status Kontrak</label>
                                <p>
                                    <span class="badge bg-primary">AKTIF</span>
                                    <small class="text-muted d-block mt-1">
                                        Status akan berubah menjadi "SELESAI" hanya setelah UMKM menerima hasil
                                    </small>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Status Pembayaran</label>
                                <p>
                                    <span class="badge bg-<?php echo $project['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo strtoupper($project['payment_status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- What Happens Next -->
                        <div class="detail-card">
                            <h5 class="mb-3"><i class="fas fa-forward me-2 text-primary"></i>Setelah Submit</h5>
                            <div class="timeline">
                                <div class="timeline-item mb-3">
                                    <div class="d-flex">
                                        <div class="timeline-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 30px; height: 30px; font-size: 12px;">
                                            1
                                        </div>
                                        <div class="ms-3">
                                            <h6 class="mb-1">UMKM Review</h6>
                                            <p class="small text-muted mb-0">UMKM akan mereview hasil dalam 7 hari kerja</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="timeline-item mb-3">
                                    <div class="d-flex">
                                        <div class="timeline-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 30px; height: 30px; font-size: 12px;">
                                            2
                                        </div>
                                        <div class="ms-3">
                                            <h6 class="mb-1">Hasil Diterima</h6>
                                            <p class="small text-muted mb-0">Proyek selesai, rating Anda bertambah</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="timeline-item mb-3">
                                    <div class="d-flex">
                                        <div class="timeline-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 30px; height: 30px; font-size: 12px;">
                                            3
                                        </div>
                                        <div class="ms-3">
                                            <h6 class="mb-1">Revisi Diminta</h6>
                                            <p class="small text-muted mb-0">UMKM dapat meminta revisi, Anda bisa kirim ulang</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="timeline-item">
                                    <div class="d-flex">
                                        <div class="timeline-icon bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 30px; height: 30px; font-size: 12px;">
                                            4
                                        </div>
                                        <div class="ms-3">
                                            <h6 class="mb-1">Hasil Ditolak</h6>
                                            <p class="small text-muted mb-0">Admin akan meninjau kasus penolakan</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Info -->
                        <div class="detail-card">
                            <h5 class="mb-3">Kontak UMKM</h5>
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-user-circle fa-2x text-muted me-3"></i>
                                <div>
                                    <p class="fw-semibold mb-0"><?php echo htmlspecialchars($project['umkm_name']); ?></p>
                                    <small class="text-muted"><?php echo htmlspecialchars($project['business_name']); ?></small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-envelope text-muted me-3"></i>
                                <small><?php echo htmlspecialchars($project['umkm_email']); ?></small>
                            </div>
                            <a href="messages.php?user_id=<?php echo $project['umkm_user_id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-comments me-2"></i>Chat UMKM
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload functionality
        const finalFiles = [];
        const previewImages = [];
        
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        
        function getFileIcon(fileType) {
            if (fileType.startsWith('image/')) return 'fas fa-file-image text-success';
            if (fileType.includes('pdf')) return 'fas fa-file-pdf text-danger';
            if (fileType.includes('zip') || fileType.includes('rar') || fileType.includes('7z')) 
                return 'fas fa-file-archive text-warning';
            if (fileType.includes('video')) return 'fas fa-file-video text-primary';
            if (fileType.includes('text') || fileType.includes('html') || fileType.includes('css') || fileType.includes('javascript'))
                return 'fas fa-file-code text-info';
            return 'fas fa-file text-secondary';
        }
        
        // Handle final files
        const finalFilesInput = document.getElementById('final_files');
        const finalFilesList = document.getElementById('finalFilesList');
        const finalFilesDropzone = document.getElementById('finalFilesDropzone');
        const finalFilesError = document.getElementById('finalFilesError');
        
        finalFilesInput.addEventListener('change', handleFinalFiles);
        
        // Drag and drop for final files
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            finalFilesDropzone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            finalFilesDropzone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            finalFilesDropzone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            finalFilesDropzone.classList.add('dragover');
        }
        
        function unhighlight() {
            finalFilesDropzone.classList.remove('dragover');
        }
        
        finalFilesDropzone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFinalFiles({ target: { files } });
        }
        
        function handleFinalFiles(e) {
            const files = Array.from(e.target.files);
            let hasError = false;
            
            // Clear previous errors
            finalFilesError.textContent = '';
            
            // Validate files
            files.forEach(file => {
                if (file.size > 50 * 1024 * 1024) { // 50MB
                    finalFilesError.textContent += `File "${file.name}" melebihi batas 50MB\n`;
                    hasError = true;
                }
            });
            
            if (finalFiles.length + files.length > 10) {
                finalFilesError.textContent += 'Maksimal 10 file yang dapat diunggah\n';
                hasError = true;
            }
            
            if (!hasError) {
                files.forEach(file => {
                    finalFiles.push(file);
                });
                updateFinalFilesList();
            }
        }
        
        function updateFinalFilesList() {
            finalFilesList.innerHTML = '';
            
            if (finalFiles.length === 0) {
                finalFilesList.innerHTML = '<p class="text-muted text-center">Belum ada file yang dipilih</p>';
                return;
            }
            
            finalFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <div class="file-info">
                        <div class="file-icon">
                            <i class="${getFileIcon(file.type)}"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">${file.name}</div>
                            <div class="file-size">${formatBytes(file.size)}</div>
                        </div>
                    </div>
                    <div class="remove-file" onclick="removeFinalFile(${index})">
                        <i class="fas fa-times"></i>
                    </div>
                `;
                finalFilesList.appendChild(fileItem);
            });
        }
        
        function removeFinalFile(index) {
            finalFiles.splice(index, 1);
            updateFinalFilesList();
        }
        
        // Handle preview images
        const previewImagesInput = document.getElementById('preview_images');
        const previewImagesList = document.getElementById('previewImagesList');
        const previewImagesDropzone = document.getElementById('previewImagesDropzone');
        
        previewImagesInput.addEventListener('change', handlePreviewImages);
        
        // Drag and drop for preview images
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            previewImagesDropzone.addEventListener(eventName, preventDefaults, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            previewImagesDropzone.addEventListener(eventName, highlightPreview, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            previewImagesDropzone.addEventListener(eventName, unhighlightPreview, false);
        });
        
        function highlightPreview() {
            previewImagesDropzone.classList.add('dragover');
        }
        
        function unhighlightPreview() {
            previewImagesDropzone.classList.remove('dragover');
        }
        
        previewImagesDropzone.addEventListener('drop', handlePreviewDrop, false);
        
        function handlePreviewDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handlePreviewImages({ target: { files } });
        }
        
        function handlePreviewImages(e) {
            const files = Array.from(e.target.files);
            
            files.forEach(file => {
                if (file.size <= 10 * 1024 * 1024 && previewImages.length < 5) { // 10MB, max 5 images
                    previewImages.push(file);
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-image';
                        img.title = file.name;
                        previewImagesList.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Form validation
        document.getElementById('submitWorkForm').addEventListener('submit', function(e) {
            const submitButton = document.getElementById('submitButton');
            const workTitle = document.getElementById('work_title').value.trim();
            
            <?php if ($project['progress_percentage'] < 100): ?>
                const confirmProgress = document.getElementById('confirmProgress');
                if (!confirmProgress.checked) {
                    e.preventDefault();
                    alert('Harap centang konfirmasi bahwa Anda mengerti alur setelah submit');
                    return false;
                }
            <?php endif; ?>
            
            if (finalFiles.length === 0) {
                e.preventDefault();
                finalFilesError.textContent = 'Harap unggah setidaknya satu file hasil kerja';
                return false;
            }
            
            if (!workTitle) {
                e.preventDefault();
                alert('Judul hasil kerja tidak boleh kosong');
                return false;
            }
            
            // Disable submit button to prevent double submission
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mengirim...';
            
            // Create FormData and append files
            const formData = new FormData(this);
            
            // Append final files
            finalFiles.forEach(file => {
                formData.append('final_files[]', file);
            });
            
            // Append preview images
            previewImages.forEach(image => {
                formData.append('preview_images[]', image);
            });
            
            return true;
        });
        
        // Initialize
        updateFinalFilesList();
    </script>
</body>
</html>