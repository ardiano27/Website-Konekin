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

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $budget_type = $_POST['budget_type'] ?? '';
    $budget_min = floatval($_POST['budget_min'] ?? 0);
    $budget_max = floatval($_POST['budget_max'] ?? 0);
    $deadline = $_POST['deadline'] ?? '';
    $required_skills = $_POST['required_skills'] ?? [];
    
    // Validasi
    if (empty($title)) {
        $errors['title'] = 'Judul proyek harus diisi';
    }
    
    if (empty($description)) {
        $errors['description'] = 'Deskripsi proyek harus diisi';
    }
    
    if (empty($category)) {
        $errors['category'] = 'Kategori harus dipilih';
    }
    
    if (empty($budget_type)) {
        $errors['budget_type'] = 'Tipe budget harus dipilih';
    }
    
    if ($budget_type !== 'negotiable' && $budget_min <= 0) {
        $errors['budget_min'] = 'Budget minimum harus diisi';
    }
    
    if ($budget_type === 'fixed' && $budget_max < $budget_min) {
        $errors['budget_max'] = 'Budget maksimum harus lebih besar dari minimum';
    }
    
    if (empty($deadline)) {
        $errors['deadline'] = 'Deadline harus diisi';
    } elseif (strtotime($deadline) < strtotime('today')) {
        $errors['deadline'] = 'Deadline tidak boleh di masa lalu';
    }
    
    // Handle file uploads
    $attachment_urls = [];
    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $upload_dir = 'uploads/projects/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = time() . '_' . basename($_FILES['attachments']['name'][$key]);
                $file_path = $upload_dir . $file_name;
                
                // Validasi file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'video/mp4', 'video/avi', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $file_type = mime_content_type($tmp_name);
                
                if (in_array($file_type, $allowed_types)) {
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $attachment_urls[] = $file_path;
                    } else {
                        $errors['attachments'] = 'Gagal mengupload beberapa file';
                    }
                } else {
                    $errors['attachments'] = 'Tipe file tidak diizinkan. Hanya gambar, PDF, video, dan dokumen Word yang diizinkan';
                }
            }
        }
    }
    
    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        try {
            $uuid = uniqid();
            $required_skills_json = json_encode($required_skills);
            $attachment_urls_json = json_encode($attachment_urls);
            
            $sql = "INSERT INTO projects (uuid, umkm_user_id, title, description, category, budget_range_min, budget_range_max, budget_type, deadline, required_skills, attachment_urls, status) 
                    VALUES (:uuid, :umkm_user_id, :title, :description, :category, :budget_min, :budget_max, :budget_type, :deadline, :required_skills, :attachment_urls, 'open')";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':uuid' => $uuid,
                ':umkm_user_id' => $_SESSION['user_id'],
                ':title' => $title,
                ':description' => $description,
                ':category' => $category,
                ':budget_min' => $budget_min,
                ':budget_max' => $budget_max,
                ':budget_type' => $budget_type,
                ':deadline' => $deadline,
                ':required_skills' => $required_skills_json,
                ':attachment_urls' => $attachment_urls_json
            ]);
            
            $success = true;
            $_POST = []; // Clear form
            
        } catch (PDOException $e) {
            $errors['database'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Proyek Baru - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2596be;
            --primary-dark: #1e7a9c;
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
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-medium);
        }
        
        .form-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
        }
        
        .form-control, .form-select, .form-check-input {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 150, 190, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .skill-tag {
            background-color: #e9f7fe;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 20px;
            padding: 8px 16px;
            margin: 5px;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .skill-tag:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .skill-tag.selected {
            background-color: var(--primary-color);
            color: white;
        }
        
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 40px 20px;
            text-align: center;
            transition: all 0.3s;
            background-color: var(--light-bg);
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: var(--primary-color);
            background-color: #f0f8ff;
        }
        
        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background-color: #e6f3ff;
        }
        
        .file-preview {
            margin-top: 20px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            background-color: var(--light-bg);
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .file-info {
            display: flex;
            align-items: center;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
        }
        
        .success-alert {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 1px solid #c3e6cb;
            color: #155724;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .category-card {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            height: 100%;
        }
        
        .category-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
        }
        
        .category-card.selected {
            border-color: var(--primary-color);
            background-color: #f0f8ff;
        }
        
        .category-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .budget-option {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .budget-option:hover {
            border-color: var(--primary-color);
        }
        
        .budget-option.selected {
            border-color: var(--primary-color);
            background-color: #f0f8ff;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2"><i class="fas fa-plus-circle me-2"></i>BUAT PROYEK BARU</h1>
                        <p class="mb-0">Jelaskan kebutuhan proyek Anda untuk menarik creative worker terbaik</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="projects.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-1"></i>Kembali ke Proyek Saya
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="success-alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div>
                            <h4 class="mb-1">Proyek Berhasil Dibuat!</h4>
                            <p class="mb-0">Proyek Anda telah berhasil diposting dan sekarang dapat dilihat oleh creative worker.</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="find-projects.php" class="btn btn-success me-2">Lihat Proyek</a>
                        <a href="create-project.php" class="btn btn-outline-success">Buat Proyek Lain</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Project Form -->
            <form method="POST" action="create-project.php" enctype="multipart/form-data" id="projectForm">
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Basic Information Section -->
                        <div class="form-section">
                            <h4 class="mb-4"><i class="fas fa-info-circle me-2"></i>Informasi Dasar Proyek</h4>
                            
                            <div class="mb-4">
                                <label for="title" class="form-label">Judul Proyek <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                       id="title" name="title" placeholder="Contoh: Butuh Desain Logo untuk UMKM Makanan" 
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                <?php if (isset($errors['title'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['title']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label for="category" class="form-label">Kategori Proyek <span class="text-danger">*</span></label>
                                <div class="row g-3" id="categorySelection">
                                    <div class="col-md-4">
                                        <div class="category-card <?php echo ($_POST['category'] ?? '') === 'video' ? 'selected' : ''; ?>" data-category="video">
                                            <div class="category-icon">
                                                <i class="fas fa-video"></i>
                                            </div>
                                            <h6>Video</h6>
                                            <p class="small text-muted">Editing video, animasi, motion graphic</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="category-card <?php echo ($_POST['category'] ?? '') === 'website' ? 'selected' : ''; ?>" data-category="website">
                                            <div class="category-icon">
                                                <i class="fas fa-globe"></i>
                                            </div>
                                            <h6>Website</h6>
                                            <p class="small text-muted">Pembuatan website, web development</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="category-card <?php echo ($_POST['category'] ?? '') === 'logo' ? 'selected' : ''; ?>" data-category="logo">
                                            <div class="category-icon">
                                                <i class="fas fa-palette"></i>
                                            </div>
                                            <h6>Logo & Branding</h6>
                                            <p class="small text-muted">Desain logo, identitas merek</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="category-card <?php echo ($_POST['category'] ?? '') === 'social_media' ? 'selected' : ''; ?>" data-category="social_media">
                                            <div class="category-icon">
                                                <i class="fas fa-hashtag"></i>
                                            </div>
                                            <h6>Social Media</h6>
                                            <p class="small text-muted">Konten media sosial, marketing</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="category-card <?php echo ($_POST['category'] ?? '') === 'content' ? 'selected' : ''; ?>" data-category="content">
                                            <div class="category-icon">
                                                <i class="fas fa-file-alt"></i>
                                            </div>
                                            <h6>Konten</h6>
                                            <p class="small text-muted">Penulisan artikel, copywriting</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="category-card <?php echo ($_POST['category'] ?? '') === 'marketing' ? 'selected' : ''; ?>" data-category="marketing">
                                            <div class="category-icon">
                                                <i class="fas fa-bullhorn"></i>
                                            </div>
                                            <h6>Marketing</h6>
                                            <p class="small text-muted">Strategi pemasaran, iklan</p>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="category" id="categoryInput" value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>" required>
                                <?php if (isset($errors['category'])): ?>
                                    <div class="text-danger small mt-2"><?php echo $errors['category']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label">Deskripsi Lengkap Proyek <span class="text-danger">*</span></label>
                                <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                          id="description" name="description" rows="6" 
                                          placeholder="Jelaskan secara detail tentang proyek Anda, tujuan, dan hasil yang diharapkan..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                <?php if (isset($errors['description'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['description']; ?></div>
                                <?php endif; ?>
                                <div class="form-text">Semakin detail deskripsi Anda, semakin mudah creative worker memahami kebutuhan proyek.</div>
                            </div>
                        </div>
                        
                        <!-- Skills Required Section -->
                        <div class="form-section">
                            <h4 class="mb-4"><i class="fas fa-tools me-2"></i>Keahlian yang Dibutuhkan</h4>
                            <p class="text-muted mb-3">Pilih keahlian yang diperlukan untuk proyek ini (bisa pilih lebih dari satu):</p>
                            
                            <div class="mb-3">
                                <div class="row" id="skillsContainer">
                                    <?php
                                    $skillCategories = [
                                        'design' => ['Graphic Design', 'UI/UX Design', 'Logo Design', 'Adobe Illustrator', 'Adobe Photoshop', 'Figma', 'UI Design', 'UX Design', 'Brand Identity'],
                                        'development' => ['Web Development', 'WordPress', 'PHP', 'JavaScript', 'Laravel', 'Vue.js', 'React', 'MySQL'],
                                        'marketing' => ['Social Media Marketing', 'Digital Marketing', 'Social Media Management', 'SEO', 'Google Ads', 'Content Strategy'],
                                        'content' => ['Content Writing', 'Photography', 'Copywriting', 'Blog Writing', 'Technical Writing'],
                                        'video' => ['Video Editing', 'Adobe Premiere', 'After Effects', 'Cinematography', 'Photo Editing']
                                    ];
                                    
                                    $selectedSkills = $_POST['required_skills'] ?? [];
                                    
                                    foreach ($skillCategories as $category => $skills) {
                                        echo '<div class="col-md-6 mb-3">';
                                        echo '<h6 class="mb-2">' . ucfirst($category) . '</h6>';
                                        foreach ($skills as $skill) {
                                            $isSelected = in_array($skill, $selectedSkills) ? 'selected' : '';
                                            echo '<span class="skill-tag ' . $isSelected . '" data-skill="' . htmlspecialchars($skill) . '">';
                                            echo '<i class="fas fa-check me-1"></i>' . $skill;
                                            echo '</span>';
                                        }
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                                <input type="hidden" name="required_skills[]" id="selectedSkillsInput" value="<?php echo htmlspecialchars(implode(',', $selectedSkills)); ?>">
                            </div>
                        </div>
                       <!-- iki wok sing ndukur jier-->
                        <!-- File Attachments Section -->
                        <div class="form-section">
                            <h4 class="mb-4"><i class="fas fa-paperclip me-2"></i>Lampiran File</h4>
                            <p class="text-muted mb-3">Unggah file referensi, contoh, atau dokumen pendukung (maks. 5 file):</p>
                            
                            <div class="file-upload-area" id="fileUploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <h5>Seret file ke sini atau klik untuk mengunggah</h5>
                                <p class="text-muted">Format yang didukung: JPG, PNG, GIF, PDF, MP4, AVI, DOC, DOCX</p>
                                <p class="text-muted small">Maksimal 10MB per file</p>
                            </div>
                            <input type="file" name="attachments[]" id="fileInput" multiple style="display: none;" accept=".jpg,.jpeg,.png,.gif,.pdf,.mp4,.avi,.doc,.docx">
                            
                            <div class="file-preview" id="filePreview">
                                <!-- File preview akan muncul di sini -->
                            </div>
                            <?php if (isset($errors['attachments'])): ?>
                                <div class="text-danger small mt-2"><?php echo $errors['attachments']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Budget & Timeline Section -->
                        <div class="form-section">
                            <h4 class="mb-4"><i class="fas fa-money-bill-wave me-2"></i>Budget & Timeline</h4>
                            
                            <div class="mb-4">
                                <label class="form-label">Tipe Budget <span class="text-danger">*</span></label>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="budget-option <?php echo ($_POST['budget_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>" data-budget-type="fixed">
                                            <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                                            <h6>Fixed Price</h6>
                                            <p class="small text-muted">Budget tetap</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="budget-option <?php echo ($_POST['budget_type'] ?? '') === 'hourly' ? 'selected' : ''; ?>" data-budget-type="hourly">
                                            <i class="fas fa-clock fa-2x mb-2"></i>
                                            <h6>Hourly</h6>
                                            <p class="small text-muted">Dibayar per jam</p>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="budget-option <?php echo ($_POST['budget_type'] ?? '') === 'negotiable' ? 'selected' : ''; ?>" data-budget-type="negotiable">
                                            <i class="fas fa-handshake fa-2x mb-2"></i>
                                            <h6>Negotiable</h6>
                                            <p class="small text-muted">Budget bisa dinegosiasikan</p>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="budget_type" id="budgetTypeInput" value="<?php echo htmlspecialchars($_POST['budget_type'] ?? ''); ?>" required>
                                <?php if (isset($errors['budget_type'])): ?>
                                    <div class="text-danger small mt-2"><?php echo $errors['budget_type']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div id="budgetAmountSection" style="<?php echo ($_POST['budget_type'] ?? '') === 'negotiable' ? 'display: none;' : ''; ?>">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label for="budget_min" class="form-label">Budget Min (Rp)</label>
                                        <input type="number" class="form-control <?php echo isset($errors['budget_min']) ? 'is-invalid' : ''; ?>" 
                                               id="budget_min" name="budget_min" min="0" 
                                               value="<?php echo htmlspecialchars($_POST['budget_min'] ?? ''); ?>">
                                        <?php if (isset($errors['budget_min'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['budget_min']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6">
                                        <label for="budget_max" class="form-label">Budget Max (Rp)</label>
                                        <input type="number" class="form-control <?php echo isset($errors['budget_max']) ? 'is-invalid' : ''; ?>" 
                                               id="budget_max" name="budget_max" min="0" 
                                               value="<?php echo htmlspecialchars($_POST['budget_max'] ?? ''); ?>">
                                        <?php if (isset($errors['budget_max'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['budget_max']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label for="deadline" class="form-label">Deadline Proyek <span class="text-danger">*</span></label>
                                <input type="date" class="form-control <?php echo isset($errors['deadline']) ? 'is-invalid' : ''; ?>" 
                                       id="deadline" name="deadline" 
                                       value="<?php echo htmlspecialchars($_POST['deadline'] ?? ''); ?>" required>
                                <?php if (isset($errors['deadline'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['deadline']; ?></div>
                                <?php endif; ?>
                                <div class="form-text">Pilih tanggal deadline penyelesaian proyek.</div>
                            </div>
                        </div>
                        
                        <!-- Submit Section -->
                        <div class="form-section bg-light">
                            <h5 class="mb-3">Siap Memposting Proyek?</h5>
                            <p class="text-muted small mb-4">Pastikan semua informasi sudah benar sebelum memposting proyek.</p>
                            
                            <button type="submit" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-rocket me-2"></i>Posting Proyek
                            </button>
                            
                            <?php if (isset($errors['database'])): ?>
                                <div class="alert alert-danger mt-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $errors['database']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Category Selection
            const categoryCards = document.querySelectorAll('.category-card');
            const categoryInput = document.getElementById('categoryInput');
            
            categoryCards.forEach(card => {
                card.addEventListener('click', function() {
                    categoryCards.forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    categoryInput.value = this.getAttribute('data-category');
                });
            });
            
            // Budget Type Selection
            const budgetOptions = document.querySelectorAll('.budget-option');
            const budgetTypeInput = document.getElementById('budgetTypeInput');
            const budgetAmountSection = document.getElementById('budgetAmountSection');
            
            budgetOptions.forEach(option => {
                option.addEventListener('click', function() {
                    budgetOptions.forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    const budgetType = this.getAttribute('data-budget-type');
                    budgetTypeInput.value = budgetType;
                    
                    if (budgetType === 'negotiable') {
                        budgetAmountSection.style.display = 'none';
                        document.getElementById('budget_min').required = false;
                        document.getElementById('budget_max').required = false;
                    } else {
                        budgetAmountSection.style.display = 'block';
                        document.getElementById('budget_min').required = true;
                        document.getElementById('budget_max').required = true;
                    }
                });
            });
            
            // Skills Selection
            const skillTags = document.querySelectorAll('.skill-tag');
            const selectedSkillsInput = document.getElementById('selectedSkillsInput');
            let selectedSkills = selectedSkillsInput.value ? selectedSkillsInput.value.split(',') : [];
            
            skillTags.forEach(tag => {
                tag.addEventListener('click', function() {
                    const skill = this.getAttribute('data-skill');
                    
                    if (this.classList.contains('selected')) {
                        this.classList.remove('selected');
                        selectedSkills = selectedSkills.filter(s => s !== skill);
                    } else {
                        this.classList.add('selected');
                        selectedSkills.push(skill);
                    }
                    
                    selectedSkillsInput.value = selectedSkills.join(',');
                });
            });
            
            // File Upload Handling
            const fileUploadArea = document.getElementById('fileUploadArea');
            const fileInput = document.getElementById('fileInput');
            const filePreview = document.getElementById('filePreview');
            const maxFiles = 5;
            
            fileUploadArea.addEventListener('click', function() {
                fileInput.click();
            });
            
            fileUploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            fileUploadArea.addEventListener('dragleave', function() {
                this.classList.remove('dragover');
            });
            
            fileUploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                handleFiles(e.dataTransfer.files);
            });
            
            fileInput.addEventListener('change', function() {
                handleFiles(this.files);
            });
            
            function handleFiles(files) {
                const currentFiles = filePreview.children.length;
                
                for (let i = 0; i < files.length; i++) {
                    if (currentFiles + i >= maxFiles) {
                        alert(`Maksimal ${maxFiles} file yang dapat diunggah`);
                        break;
                    }
                    
                    const file = files[i];
                    addFilePreview(file);
                }
                
                // Reset file input to allow uploading same files again
                fileInput.value = '';
            }
            
            function addFilePreview(file) {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                const fileInfo = document.createElement('div');
                fileInfo.className = 'file-info';
                
                const fileIcon = document.createElement('div');
                fileIcon.className = 'file-icon';
                
                // Set icon based on file type
                if (file.type.startsWith('image/')) {
                    fileIcon.innerHTML = '<i class="fas fa-image"></i>';
                } else if (file.type === 'application/pdf') {
                    fileIcon.innerHTML = '<i class="fas fa-file-pdf"></i>';
                } else if (file.type.startsWith('video/')) {
                    fileIcon.innerHTML = '<i class="fas fa-video"></i>';
                } else if (file.type.includes('word') || file.type.includes('document')) {
                    fileIcon.innerHTML = '<i class="fas fa-file-word"></i>';
                } else {
                    fileIcon.innerHTML = '<i class="fas fa-file"></i>';
                }
                
                const fileName = document.createElement('div');
                fileName.innerHTML = `
                    <div class="fw-bold">${file.name}</div>
                    <div class="small text-muted">${formatFileSize(file.size)}</div>
                `;
                
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-sm btn-outline-danger';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.addEventListener('click', function() {
                    fileItem.remove();
                });
                
                fileInfo.appendChild(fileIcon);
                fileInfo.appendChild(fileName);
                fileItem.appendChild(fileInfo);
                fileItem.appendChild(removeBtn);
                filePreview.appendChild(fileItem);
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            // Set minimum date for deadline to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('deadline').min = today;
        });
    </script>
</body>
</html>