<?php
// request-revision.php
include "check_login.php";

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$contract_id = $_GET['contract_id'] ?? 0;
$work_submission_id = $_GET['submission_id'] ?? 0;

// Validasi dan ambil data
$sql = "SELECT ws.*, c.*, u.full_name as creative_name 
        FROM work_submissions ws
        JOIN contracts c ON ws.contract_id = c.id
        JOIN users u ON c.creative_user_id = u.id
        WHERE ws.id = :submission_id 
        AND c.umkm_user_id = :user_id
        AND ws.status = 'submitted'";

$work_data = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':submission_id' => $work_submission_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $work_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

if (!$work_data) {
    header("Location: projects.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $deadline = $_POST['deadline'] ?? null;
    $priority = $_POST['priority'] ?? 'medium';
    
    // Hitung revision number
    $revision_sql = "SELECT COALESCE(MAX(revision_number), 0) + 1 as next_revision 
                     FROM revisions 
                     WHERE contract_id = :contract_id";
    $stmt = $conn->prepare($revision_sql);
    $stmt->execute([':contract_id' => $contract_id]);
    $next_revision = $stmt->fetchColumn();
    
    // Upload attachments jika ada
    $attachment_urls = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $upload_dir = 'uploads/revisions/' . $contract_id . '/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = time() . '_' . basename($_FILES['attachments']['name'][$i]);
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $file_path)) {
                    $attachment_urls[] = $file_path;
                }
            }
        }
    }
    
    try {
        $conn->beginTransaction();
        
        // Insert revision request
        $insert_sql = "INSERT INTO revisions 
                      (contract_id, work_submission_id, requested_by_user_id, 
                       requested_to_user_id, revision_number, title, description, 
                       attachment_urls, deadline, priority, status, created_at)
                      VALUES (:contract_id, :submission_id, :requested_by, 
                              :requested_to, :revision_number, :title, :description,
                              :attachments, :deadline, :priority, 'pending', NOW())";
        
        $stmt = $conn->prepare($insert_sql);
        $stmt->execute([
            ':contract_id' => $contract_id,
            ':submission_id' => $work_submission_id,
            ':requested_by' => $_SESSION['user_id'],
            ':requested_to' => $work_data['creative_user_id'],
            ':revision_number' => $next_revision,
            ':title' => $title,
            ':description' => $description,
            ':attachments' => !empty($attachment_urls) ? json_encode($attachment_urls) : null,
            ':deadline' => $deadline,
            ':priority' => $priority
        ]);
        
        $revision_id = $conn->lastInsertId();
        
        // Update work submission status
        $update_sql = "UPDATE work_submissions SET status = 'revised' WHERE id = :submission_id";
        $stmt = $conn->prepare($update_sql);
        $stmt->execute([':submission_id' => $work_submission_id]);
        
        // Create notification for creative worker
        $notification_sql = "INSERT INTO notifications 
                            (user_id, title, message, notification_type, 
                             related_entity_type, related_entity_id, created_at)
                            VALUES (:user_id, 'Permintaan Revisi', 
                                    'UMKM meminta revisi untuk proyek \"" . $work_data['title'] . "\". 
                                     Silakan lihat detail revisi.', 'project', 
                                    'revision', :revision_id, NOW())";
        
        $stmt = $conn->prepare($notification_sql);
        $stmt->execute([
            ':user_id' => $work_data['creative_user_id'],
            ':revision_id' => $revision_id
        ]);
        
        $conn->commit();
        
        $_SESSION['success'] = "Permintaan revisi berhasil dikirim!";
        header("Location: project-progress.php?id=" . $work_data['project_id']);
        exit;
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Gagal mengirim permintaan revisi: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Revisi - Konekin</title>
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
            background-color: #f5f7fa;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-medium);
        }
        
        .revision-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }
        
        .project-info-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-color);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(84, 158, 254, 0.15);
        }
        
        .form-control::placeholder {
            color: #adb5bd;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(84, 158, 254, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(84, 158, 254, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: var(--border-radius);
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: var(--primary-color);
            background-color: rgba(84, 158, 254, 0.05);
        }
        
        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background-color: rgba(84, 158, 254, 0.1);
        }
        
        .upload-icon {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .file-preview {
            margin-top: 20px;
        }
        
        .preview-item {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 10px;
            border: 1px solid var(--border-color);
        }
        
        .preview-icon {
            color: var(--primary-color);
            margin-right: 10px;
            font-size: 20px;
        }
        
        .preview-name {
            flex-grow: 1;
            margin: 0;
            font-size: 14px;
        }
        
        .preview-size {
            color: var(--text-muted);
            font-size: 12px;
        }
        
        .remove-file {
            color: #dc3545;
            background: none;
            border: none;
            padding: 0;
            margin-left: 10px;
            cursor: pointer;
        }
        
        .alert {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-light);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .badge-priority {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .badge-priority-low {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-priority-medium {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-priority-high {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 10px;
            align-items: flex-start;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-muted);
            min-width: 120px;
            margin-right: 10px;
        }
        
        .info-value {
            color: var(--text-dark);
            flex-grow: 1;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .page-header {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .revision-card {
                padding: 20px;
            }
            
            .info-item {
                flex-direction: column;
            }
            
            .info-label {
                margin-bottom: 5px;
                min-width: auto;
            }
        }
        
        /* Animation for form submission */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .revision-card {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="container">
                <!-- Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="mb-2"><i class="fas fa-edit me-2"></i>Permintaan Revisi</h1>
                            <p class="mb-0">Berikan detail revisi yang dibutuhkan untuk hasil pekerjaan</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="project-progress.php?id=<?php echo $work_data['project_id']; ?>" class="btn btn-light">
                                <i class="fas fa-arrow-left me-1"></i>Kembali ke Progress
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Project Info -->
                <div class="project-info-box">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Proyek:</span>
                                <span class="info-value fw-bold"><?php echo htmlspecialchars($work_data['title']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Creative Worker:</span>
                                <span class="info-value"><?php echo htmlspecialchars($work_data['creative_name']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Submission:</span>
                                <span class="info-value"><?php echo htmlspecialchars($work_data['work_title']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Tanggal Submit:</span>
                                <span class="info-value"><?php echo date('d M Y H:i', strtotime($work_data['submitted_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $_SESSION['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <!-- Revision Form -->
                <div class="revision-card">
                    <form method="POST" enctype="multipart/form-data" id="revisionForm">
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <h4 class="mb-0">Form Permintaan Revisi</h4>
                                <p class="text-muted mb-0">Isi detail revisi dengan jelas dan lengkap</p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <?php 
                                $revision_sql = "SELECT COALESCE(MAX(revision_number), 0) + 1 as next_revision 
                                                 FROM revisions 
                                                 WHERE contract_id = :contract_id";
                                $stmt = $conn->prepare($revision_sql);
                                $stmt->execute([':contract_id' => $contract_id]);
                                $next_revision = $stmt->fetchColumn();
                                ?>
                                <span class="badge bg-secondary">Revisi #<?php echo $next_revision; ?></span>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Judul Revisi -->
                                <div class="mb-4">
                                    <label class="form-label">Judul Revisi *</label>
                                    <input type="text" name="title" class="form-control" required 
                                           placeholder="Contoh: Perbaikan warna logo, Tambahkan animasi, dll"
                                           maxlength="200">
                                    <div class="form-text">Buat judul yang spesifik dan jelas</div>
                                </div>
                                
                                <!-- Deskripsi Detail -->
                                <div class="mb-4">
                                    <label class="form-label">Deskripsi Detail Revisi *</label>
                                    <textarea name="description" class="form-control" rows="6" required
                                              placeholder="Jelaskan secara detail apa yang perlu direvisi. Sertakan poin-poin spesifik...
Contoh:
1. Warna logo terlalu terang, mohon diubah ke warna yang lebih gelap
2. Font pada header kurang jelas, gunakan font yang lebih readable
3. Tambahkan animasi pada bagian hero section
4. Perbaiki layout pada mobile view"></textarea>
                                    <div class="form-text">Gunakan poin-poin untuk memudahkan creative worker memahami permintaan revisi</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Deadline Revisi -->
                                <div class="mb-4">
                                    <label class="form-label">Deadline Revisi (Opsional)</label>
                                    <input type="date" name="deadline" class="form-control" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                           max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                                    <div class="form-text">Tentukan tanggal deadline jika diperlukan</div>
                                </div>
                                
                                <!-- Prioritas -->
                                <div class="mb-4">
                                    <label class="form-label">Prioritas</label>
                                    <select name="priority" class="form-select">
                                        <option value="low">Rendah</option>
                                        <option value="medium" selected>Sedang</option>
                                        <option value="high">Tinggi</option>
                                    </select>
                                    <div class="form-text">
                                        <span class="badge badge-priority badge-priority-low me-1">Rendah</span>
                                        <span class="badge badge-priority badge-priority-medium me-1">Sedang</span>
                                        <span class="badge badge-priority badge-priority-high">Tinggi</span>
                                    </div>
                                </div>
                                
                                <!-- Tips Section -->
                                <div class="card border-0 bg-light mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-lightbulb text-warning me-2"></i>Tips Revisi Efektif
                                        </h6>
                                        <ul class="small mb-0">
                                            <li>Berikan contoh/referensi visual</li>
                                            <li>Sebutkan bagian yang spesifik</li>
                                            <li>Gunakan bahasa yang jelas</li>
                                            <li>Berikan alasan perubahan</li>
                                            <li>Prioritaskan perubahan penting</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- File Attachments -->
                        <div class="mb-4">
                            <label class="form-label">Lampiran Referensi (Opsional)</label>
                            <div class="file-upload-area" id="fileDropArea">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <h5>Seret file ke sini atau klik untuk upload</h5>
                                <p class="text-muted mb-3">Maksimal 10 file, ukuran maksimal 5MB per file</p>
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-folder-open me-2"></i>Pilih File
                                </button>
                                <input type="file" id="fileInput" name="attachments[]" multiple class="d-none"
                                       accept="image/*,.pdf,.doc,.docx,.psd,.ai,.zip,.rar,.ppt,.pptx,.xls,.xlsx"
                                       onchange="handleFiles(this.files)">
                            </div>
                            
                            <div id="filePreview" class="file-preview"></div>
                            
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Unggah referensi gambar, dokumen, atau contoh yang diinginkan. Format yang didukung: JPG, PNG, PDF, DOC, PSD, AI, ZIP
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <div>
                                <small class="text-muted">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    Pastikan informasi revisi sudah lengkap dan jelas sebelum dikirim
                                </small>
                            </div>
                            <div>
                                <a href="project-progress.php?id=<?php echo $work_data['project_id']; ?>" 
                                   class="btn btn-secondary me-2">
                                    <i class="fas fa-times me-1"></i>Batal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i>Kirim Permintaan Revisi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload drag and drop functionality
        const fileDropArea = document.getElementById('fileDropArea');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        let uploadedFiles = [];
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            fileDropArea.classList.add('dragover');
        }
        
        function unhighlight() {
            fileDropArea.classList.remove('dragover');
        }
        
        // Handle dropped files
        fileDropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }
        
        // Handle selected files
        function handleFiles(files) {
            const maxFiles = 10;
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip', 'application/x-rar-compressed',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];
            
            // Check file count
            if (uploadedFiles.length + files.length > maxFiles) {
                alert(`Maksimal ${maxFiles} file yang dapat diupload.`);
                return;
            }
            
            for (let file of files) {
                // Check file size
                if (file.size > maxSize) {
                    alert(`File "${file.name}" terlalu besar. Maksimal 5MB per file.`);
                    continue;
                }
                
                // Check file type
                if (!allowedTypes.includes(file.type) && !file.name.match(/\.(psd|ai)$/i)) {
                    alert(`File "${file.name}" tidak didukung. Hanya file gambar, dokumen, dan arsip yang diizinkan.`);
                    continue;
                }
                
                uploadedFiles.push(file);
                addFilePreview(file);
            }
            
            // Update file input
            updateFileInput();
        }
        
        // Add file preview
        function addFilePreview(file) {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            
            const fileIcon = getFileIcon(file);
            
            previewItem.innerHTML = `
                <div class="preview-icon">
                    <i class="${fileIcon}"></i>
                </div>
                <div class="flex-grow-1">
                    <p class="preview-name mb-1">${file.name}</p>
                    <small class="preview-size">${formatFileSize(file.size)}</small>
                </div>
                <button type="button" class="remove-file" onclick="removeFile('${file.name}')">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            filePreview.appendChild(previewItem);
        }
        
        // Get file icon based on type
        function getFileIcon(file) {
            if (file.type.startsWith('image/')) return 'fas fa-image';
            if (file.type === 'application/pdf') return 'fas fa-file-pdf';
            if (file.type.includes('word')) return 'fas fa-file-word';
            if (file.type.includes('powerpoint')) return 'fas fa-file-powerpoint';
            if (file.type.includes('excel')) return 'fas fa-file-excel';
            if (file.type.includes('zip') || file.type.includes('rar')) return 'fas fa-file-archive';
            if (file.name.match(/\.psd$/i)) return 'fas fa-file-image';
            if (file.name.match(/\.ai$/i)) return 'fas fa-file-alt';
            return 'fas fa-file';
        }
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Remove file
        function removeFile(fileName) {
            uploadedFiles = uploadedFiles.filter(file => file.name !== fileName);
            updateFilePreview();
            updateFileInput();
        }
        
        // Update file preview display
        function updateFilePreview() {
            filePreview.innerHTML = '';
            uploadedFiles.forEach(file => addFilePreview(file));
        }
        
        // Update file input
        function updateFileInput() {
            const dataTransfer = new DataTransfer();
            uploadedFiles.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
        }
        
        // Form validation
        document.getElementById('revisionForm').addEventListener('submit', function(e) {
            const title = document.querySelector('input[name="title"]').value.trim();
            const description = document.querySelector('textarea[name="description"]').value.trim();
            
            if (!title || !description) {
                e.preventDefault();
                alert('Judul dan deskripsi revisi harus diisi!');
                return;
            }
            
            // Optional: Add confirmation dialog
            if (!confirm('Apakah Anda yakin ingin mengirim permintaan revisi ini?')) {
                e.preventDefault();
            }
        });
        
        // Auto-resize textarea
        const textarea = document.querySelector('textarea[name="description"]');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Initialize textarea height
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    </script>
</body>
</html>