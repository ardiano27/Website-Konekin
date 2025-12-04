
<?php
include "check_login.php";

// Hanya UMKM yang bisa akses halaman ini
if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Validasi parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID proyek tidak valid.";
    header("Location: projects.php");
    exit;
}

$project_id = intval($_GET['id']);

// Ambil informasi proyek dan kontrak
$sql = "
    SELECT 
        p.id,
        p.title as project_title,
        p.umkm_user_id,
        c.id as contract_id,
        c.title as contract_title,
        c.creative_user_id,
        c.status as contract_status,
        c.payment_status,
        ws.id as submission_id,
        ws.work_title,
        ws.work_description,
        ws.final_files,
        ws.preview_images,
        ws.delivery_notes,
        ws.status as submission_status,
        ws.submitted_at,
        u.full_name as creative_name,
        u.avatar_url as creative_avatar,
        cp.tagline as creative_tagline
    FROM projects p
    INNER JOIN contracts c ON p.id = c.project_id
    LEFT JOIN work_submissions ws ON c.id = ws.contract_id
    INNER JOIN users u ON c.creative_user_id = u.id
    LEFT JOIN creative_profiles cp ON u.id = cp.user_id
    WHERE p.id = :project_id 
    AND p.umkm_user_id = :user_id
    AND c.status IN ('active', 'completed')
    AND ws.status IN ('submitted', 'reviewed', 'accepted')
    ORDER BY ws.submitted_at DESC
    LIMIT 1
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':project_id' => $project_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        $_SESSION['error'] = "Hasil pekerjaan tidak ditemukan atau belum tersedia.";
        header("Location: projects.php");
        exit;
    }
    
    // Cek apakah sudah ada hasil yang di-submit
    if (empty($submission['submission_id'])) {
        $_SESSION['error'] = "Hasil pekerjaan belum dikirim oleh creative worker.";
        header("Location: projects.php");
        exit;
    }
    
    // Decode file URLs dari JSON
    $final_files = json_decode($submission['final_files'] ?? '[]', true);
    $preview_images = json_decode($submission['preview_images'] ?? '[]', true);
    
    if (empty($final_files)) {
        $_SESSION['error'] = "Tidak ada file yang tersedia untuk didownload.";
        header("Location: projects.php");
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $_SESSION['error'] = "Terjadi kesalahan saat mengambil data.";
    header("Location: projects.php");
    exit;
}

// Helper function untuk format bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Handle download request
if (isset($_GET['download'])) {
    $file_index = intval($_GET['download']);
    
    if (isset($final_files[$file_index])) {
        $file_data = $final_files[$file_index];
        $file_path = is_array($file_data) ? ($file_data['url'] ?? $file_data) : $file_data;
        $file_name = is_array($file_data) ? ($file_data['name'] ?? basename($file_path)) : basename($file_path);
        
        // Cek apakah file ada
        if (file_exists($file_path)) {
            // Log download activity
            $log_sql = "INSERT INTO download_logs (project_id, contract_id, umkm_user_id, creative_user_id, file_name, downloaded_at) 
                       VALUES (:project_id, :contract_id, :umkm_user_id, :creative_user_id, :file_name, NOW())";
            try {
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->execute([
                    ':project_id' => $project_id,
                    ':contract_id' => $submission['contract_id'],
                    ':umkm_user_id' => $_SESSION['user_id'],
                    ':creative_user_id' => $submission['creative_user_id'],
                    ':file_name' => $file_name
                ]);
            } catch (Exception $e) {
                error_log("Download log error: " . $e->getMessage());
            }
            
            // Download file
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            $_SESSION['error'] = "File tidak ditemukan di server.";
            header("Location: download-deliverables.php?id=" . $project_id);
            exit;
        }
    } else {
        $_SESSION['error'] = "File tidak valid.";
        header("Location: download-deliverables.php?id=" . $project_id);
        exit;
    }
}

// Handle download all files as ZIP (with fallback if ZipArchive not available)
if (isset($_GET['download_all'])) {
    if (class_exists('ZipArchive')) {
        // Create temporary ZIP file using ZipArchive
        $zip = new ZipArchive();
        $zip_filename = 'deliverables_' . $submission['contract_id'] . '_' . time() . '.zip';
        $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
        
        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
            $files_added = 0;
            
            foreach ($final_files as $index => $file_data) {
                $file_path = is_array($file_data) ? ($file_data['url'] ?? '') : $file_data;
                $file_name = is_array($file_data) ? ($file_data['name'] ?? basename($file_path)) : basename($file_path);
                
                if (file_exists($file_path)) {
                    $zip->addFile($file_path, $file_name);
                    $files_added++;
                }
            }
            
            $zip->close();
            
            if ($files_added > 0) {
                // Log download all activity
                $log_sql = "INSERT INTO download_logs (project_id, contract_id, umkm_user_id, creative_user_id, file_name, is_bulk_download, downloaded_at) 
                           VALUES (:project_id, :contract_id, :umkm_user_id, :creative_user_id, 'ALL_FILES.zip', 1, NOW())";
                try {
                    $log_stmt = $conn->prepare($log_sql);
                    $log_stmt->execute([
                        ':project_id' => $project_id,
                        ':contract_id' => $submission['contract_id'],
                        ':umkm_user_id' => $_SESSION['user_id'],
                        ':creative_user_id' => $submission['creative_user_id']
                    ]);
                } catch (Exception $e) {
                    error_log("Download log error: " . $e->getMessage());
                }
                
                // Download ZIP
                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($zip_path));
                readfile($zip_path);
                
                // Clean up temporary file
                unlink($zip_path);
                exit;
            } else {
                $_SESSION['error'] = "Tidak ada file yang valid untuk di-download.";
                header("Location: download-deliverables.php?id=" . $project_id);
                exit;
            }
        } else {
            $_SESSION['error'] = "Gagal membuat file ZIP.";
            header("Location: download-deliverables.php?id=" . $project_id);
            exit;
        }
    } else {
        // Fallback: Create ZIP using exec() if available
        $_SESSION['error'] = "Fitur ZIP tidak tersedia di server. Silakan download file satu per satu.";
        header("Location: download-deliverables.php?id=" . $project_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Hasil Pekerjaan - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2c5ea3;
            --primary-light: rgba(62, 127, 213, 0.1);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --bg-light: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --border-radius: 16px;
            --border-radius-sm: 12px;
            --border-radius-lg: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        .main-content {
            padding-horizontal: 40px;
            padding-top: 10px;
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 2.5rem 2rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header-title {
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .header-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .back-button {
            color: white;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .back-button:hover {
            color: rgba(255, 255, 255, 0.8);
        }

        .content-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .project-info {
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .project-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .project-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .creative-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .creative-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .creative-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid white;
            object-fit: cover;
        }

        .creative-details {
            flex: 1;
        }

        .creative-name {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .creative-tagline {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .submission-info {
            margin-bottom: 2rem;
        }

        .submission-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .submission-description {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: var(--border-radius-sm);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .files-section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-light);
            color: var(--text-dark);
        }

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .file-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: 1.5rem;
            transition: var(--transition);
            position: relative;
        }

        .file-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(62, 127, 213, 0.1);
        }

        .file-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .file-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            word-break: break-word;
        }

        .file-size {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .download-btn {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
            display: block;
        }

        .download-btn:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(62, 127, 213, 0.3);
        }

        .preview-section {
            margin-bottom: 3rem;
        }

        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .preview-item {
            border-radius: var(--border-radius-sm);
            overflow: hidden;
            border: 2px solid var(--border-color);
            transition: var(--transition);
        }

        .preview-item:hover {
            border-color: var(--primary-color);
            transform: scale(1.05);
        }

        .preview-img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            cursor: pointer;
        }

        .bulk-actions {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .bulk-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .bulk-btn {
            background: white;
            color: #f5576c;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .bulk-btn:hover {
            background: rgba(255, 255, 255, 0.9);
            color: #f5576c;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(245, 87, 108, 0.3);
        }

        .notes-section {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .notes-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .notes-content {
            background: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: var(--border-radius-sm);
            line-height: 1.6;
            backdrop-filter: blur(10px);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--primary-color);
            opacity: 0.3;
            margin-bottom: 1.5rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .empty-description {
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .info-alert {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border: none;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
                text-align: center;
            }
            
            .header-title {
                font-size: 1.75rem;
            }
            
            .files-grid {
                grid-template-columns: 1fr;
            }
            
            .creative-info {
                flex-direction: column;
                text-align: center;
            }
            
            .project-meta {
                justify-content: center;
            }
        }

        /* Image Modal */
        .modal-image {
            max-width: 100%;
            max-height: 80vh;
            border-radius: 8px;
        }
        
        .zip-warning {
            background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
            color: #d32f2f;
            border-radius: var(--border-radius-sm);
            padding: 1rem;
            margin-top: 1rem;
            text-align: center;
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
                        <a href="projects.php" class="back-button mb-3 d-inline-block">
                            <i class="fas fa-arrow-left"></i> Kembali ke Proyek
                        </a>
                        <h1 class="header-title">
                            <i class="fas fa-download me-3"></i>Download Hasil Pekerjaan
                        </h1>
                        <p class="header-subtitle">
                            Download file hasil pekerjaan dari creative worker untuk proyek Anda
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="status-badge d-inline-block px-3 py-2 rounded-pill bg-white text-dark">
                            <i class="fas fa-<?php echo $submission['submission_status'] === 'accepted' ? 'check-circle text-success' : 'clock text-warning'; ?> me-2"></i>
                            Status: <?php echo strtoupper($submission['submission_status']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- Project Info -->
            <div class="content-card">
                <div class="project-info">
                    <h2 class="project-title"><?php echo htmlspecialchars($submission['project_title']); ?></h2>
                    <div class="project-meta">
                        <div class="meta-item">
                            <i class="fas fa-file-contract"></i>
                            <span>Kontrak: <?php echo htmlspecialchars($submission['contract_title']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Dikirim: <?php echo date('d M Y H:i', strtotime($submission['submitted_at'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Status Kontrak: <?php echo strtoupper($submission['contract_status']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Creative Worker Info -->
                <div class="creative-card">
                    <div class="creative-info">
                        <?php if ($submission['creative_avatar']): ?>
                            <img src="<?php echo htmlspecialchars($submission['creative_avatar']); ?>" 
                                 alt="Avatar" class="creative-avatar">
                        <?php else: ?>
                            <div class="creative-avatar d-flex align-items-center justify-content-center bg-white">
                                <i class="fas fa-user text-primary fa-2x"></i>
                            </div>
                        <?php endif; ?>
                        <div class="creative-details">
                            <h3 class="creative-name"><?php echo htmlspecialchars($submission['creative_name']); ?></h3>
                            <?php if ($submission['creative_tagline']): ?>
                                <p class="creative-tagline"><?php echo htmlspecialchars($submission['creative_tagline']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Submission Info -->
                <div class="submission-info">
                    <h3 class="submission-title">
                        <i class="fas fa-paper-plane me-2"></i>
                        <?php echo htmlspecialchars($submission['work_title']); ?>
                    </h3>
                    
                    <?php if ($submission['work_description']): ?>
                        <div class="submission-description">
                            <?php echo nl2br(htmlspecialchars($submission['work_description'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Download All Button -->
                <div class="bulk-actions">
                    <h4 class="bulk-title">Download Semua File</h4>
                    <p class="mb-4">Unduh semua file hasil pekerjaan (<?php echo count($final_files); ?> file)</p>
                    <?php if (class_exists('ZipArchive')): ?>
                        <a href="?id=<?php echo $project_id; ?>&download_all=1" class="bulk-btn" 
                           onclick="return confirm('Download semua file sebagai ZIP?')">
                            <i class="fas fa-file-archive"></i>
                            Download Semua sebagai ZIP
                        </a>
                    <?php else: ?>
                        <div class="zip-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Fitur ZIP tidak tersedia.</strong> Silakan download file satu per satu.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Files Section -->
                <div class="files-section">
                    <h3 class="section-title">
                        <i class="fas fa-file-download me-2"></i>
                        File Hasil Pekerjaan (<?php echo count($final_files); ?>)
                    </h3>
                    
                    <?php if (!empty($final_files)): ?>
                        <div class="files-grid">
                            <?php foreach ($final_files as $index => $file_data): ?>
                                <?php 
                                $file_path = is_array($file_data) ? ($file_data['url'] ?? '') : $file_data;
                                $file_name = is_array($file_data) ? ($file_data['name'] ?? basename($file_path)) : basename($file_path);
                                $file_size = is_array($file_data) ? ($file_data['size'] ?? @filesize($file_path)) : @filesize($file_path);
                                $file_type = is_array($file_data) ? ($file_data['type'] ?? 'Unknown') : 'Unknown';
                                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                
                                // Determine icon based on file type
                                $icon = 'fa-file';
                                if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'])) {
                                    $icon = 'fa-file-image';
                                } elseif (in_array($file_ext, ['mp4', 'avi', 'mov', 'wmv', 'flv'])) {
                                    $icon = 'fa-file-video';
                                } elseif (in_array($file_ext, ['zip', 'rar', '7z'])) {
                                    $icon = 'fa-file-archive';
                                } elseif (in_array($file_ext, ['pdf'])) {
                                    $icon = 'fa-file-pdf';
                                } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                    $icon = 'fa-file-word';
                                } elseif (in_array($file_ext, ['xls', 'xlsx'])) {
                                    $icon = 'fa-file-excel';
                                } elseif (in_array($file_ext, ['psd', 'ai', 'eps'])) {
                                    $icon = 'fa-file-image';
                                }
                                ?>
                                
                                <div class="file-card">
                                    <div class="file-icon">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <h4 class="file-name" title="<?php echo htmlspecialchars($file_name); ?>">
                                        <?php echo htmlspecialchars(strlen($file_name) > 30 ? substr($file_name, 0, 30) . '...' : $file_name); ?>
                                    </h4>
                                    <div class="file-size">
                                        <i class="fas fa-hdd me-1"></i>
                                        <?php echo $file_size ? formatBytes($file_size) : 'Unknown size'; ?>
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo strtoupper($file_ext); ?>
                                    </div>
                                    <a href="?id=<?php echo $project_id; ?>&download=<?php echo $index; ?>" 
                                       class="download-btn" 
                                       onclick="return confirm('Download file <?php echo htmlspecialchars($file_name); ?>?')">
                                        <i class="fas fa-download me-2"></i>Download
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-file-exclamation"></i>
                            </div>
                            <h4 class="empty-title">Tidak Ada File</h4>
                            <p class="empty-description">
                                Belum ada file yang diupload oleh creative worker.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Preview Images -->
                <?php if (!empty($preview_images)): ?>
                    <div class="preview-section">
                        <h3 class="section-title">
                            <i class="fas fa-images me-2"></i>
                            Preview Hasil (<?php echo count($preview_images); ?>)
                        </h3>
                        <div class="preview-grid">
                            <?php foreach ($preview_images as $preview): ?>
                                <div class="preview-item">
                                    <img src="<?php echo htmlspecialchars($preview); ?>" 
                                         alt="Preview" 
                                         class="preview-img"
                                         data-bs-toggle="modal" 
                                         data-bs-target="#imageModal"
                                         onclick="showImage('<?php echo htmlspecialchars($preview); ?>')">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Delivery Notes -->
                <?php if ($submission['delivery_notes']): ?>
                    <div class="notes-section">
                        <h4 class="notes-title">
                            <i class="fas fa-sticky-note me-2"></i>
                            Catatan Pengiriman dari Creative Worker
                        </h4>
                        <div class="notes-content">
                            <?php echo nl2br(htmlspecialchars($submission['delivery_notes'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Important Notes -->
                <div class="info-alert">
                    <h5><i class="fas fa-info-circle me-2"></i>Informasi Penting</h5>
                    <ul class="mb-0">
                        <li>Pastikan Anda telah mengecek semua file sebelum memberikan review</li>
                        <li>File yang sudah didownload tidak dapat dikembalikan</li>
                        <li>Simpan file dengan aman untuk referensi di masa depan</li>
                        <li>Jika ada masalah dengan file, hubungi creative worker melalui sistem chat</li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-3 mt-4 flex-wrap">
                    <a href="project-review.php?id=<?php echo $project_id; ?>" class="btn btn-warning btn-lg px-4">
                        <i class="fas fa-star me-2"></i>Berikan Review
                    </a>
                    <a href="view-project.php?id=<?php echo $project_id; ?>" class="btn btn-info btn-lg px-4">
                        <i class="fas fa-eye me-2"></i>Lihat Detail Proyek
                    </a>
                    <a href="messages.php?project_id=<?php echo $project_id; ?>" class="btn btn-success btn-lg px-4">
                        <i class="fas fa-comments me-2"></i>Chat Creative Worker
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" class="modal-image" src="" alt="Preview">
                </div>
                <div class="modal-footer">
                    <a href="#" id="downloadImage" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showImage(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('downloadImage').href = src;
            document.getElementById('downloadImage').download = src.split('/').pop();
        }
        
        // Format bytes for display
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        
        // Update file sizes display
        document.querySelectorAll('.file-size').forEach(el => {
            const sizeText = el.textContent;
            if (sizeText.includes('Unknown')) return;
            
            const sizeMatch = sizeText.match(/(\d+(\.\d+)?)\s*([KMGT]?B)/i);
            if (sizeMatch) {
                const size = parseFloat(sizeMatch[1]);
                const unit = sizeMatch[3].toUpperCase();
                const bytes = size * {
                    'B': 1,
                    'KB': 1024,
                    'MB': 1024 * 1024,
                    'GB': 1024 * 1024 * 1024,
                    'TB': 1024 * 1024 * 1024 * 1024
                }[unit];
                
                if (!isNaN(bytes)) {
                    el.innerHTML = el.innerHTML.replace(sizeMatch[0], formatBytes(bytes));
                }
            }
        });
    </script>
</body>
</html>
