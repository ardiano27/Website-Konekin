<?php
include "check_login.php";

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$errors = [];
$success_msg = '';

// --- BACKEND LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitasi Input
    $title          = trim(htmlspecialchars($_POST['title'] ?? ''));
    $description    = trim(htmlspecialchars($_POST['description'] ?? ''));
    $category       = $_POST['category'] ?? '';
    $budget_type    = $_POST['budget_type'] ?? '';
    // Mengambil nilai budget dan menghapus karakter non-angka jika ada
    $budget_min     = (float) str_replace(['.', ','], '', $_POST['budget_min'] ?? '0');
    $budget_max     = (float) str_replace(['.', ','], '', $_POST['budget_max'] ?? '0');
    $deadline       = $_POST['deadline'] ?? '';
    // Decode required skills (dikirim sebagai string comma-separated dari JS, atau array)
    $raw_skills     = $_POST['required_skills'] ?? [];
    $required_skills = is_array($raw_skills) ? $raw_skills : explode(',', $raw_skills);
    $required_skills = array_filter($required_skills); // Hapus elemen kosong

    // 2. Validasi Server-Side
    if (empty($title)) $errors['title'] = 'Judul proyek wajib diisi.';
    if (empty($description)) $errors['description'] = 'Deskripsi detail proyek wajib diisi.';
    if (empty($category)) $errors['category'] = 'Silakan pilih kategori proyek.';
    if (empty($deadline)) $errors['deadline'] = 'Tentukan batas waktu (deadline).';
    if (strtotime($deadline) < strtotime(date('Y-m-d'))) $errors['deadline'] = 'Deadline tidak boleh tanggal yang sudah lewat.';

    // Validasi Budget
    if ($budget_type !== 'negotiable') {
        if ($budget_min <= 0) $errors['budget_min'] = 'Budget minimum harus lebih dari 0.';
        if ($budget_type === 'fixed' && $budget_max < $budget_min) {
            $errors['budget_max'] = 'Budget maksimum tidak boleh lebih kecil dari minimum.';
        }
    }

    // 3. Handle File Uploads (Multiple)
    $attachment_urls = [];
    $upload_dir = 'assets/uploads/projects/'; // Menggunakan struktur folder assets
    
    // Buat folder jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $total_files = count($_FILES['attachments']['name']);
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/jpg', 
            'application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'video/mp4', 'video/quicktime', 'video/x-msvideo' // Video support
        ];
        
        for ($i = 0; $i < $total_files; $i++) {
            $file_name  = $_FILES['attachments']['name'][$i];
            $file_tmp   = $_FILES['attachments']['tmp_name'][$i];
            $file_size  = $_FILES['attachments']['size'][$i];
            $file_type  = mime_content_type($file_tmp);
            $file_error = $_FILES['attachments']['error'][$i];

            if ($file_error === UPLOAD_ERR_OK) {
                if (in_array($file_type, $allowed_types)) {
                    if ($file_size <= 10 * 1024 * 1024) { // Max 10MB per file
                        // Rename file agar unik: time_random_filename
                        $new_file_name = time() . '_' . uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $file_name);
                        $destination = $upload_dir . $new_file_name;

                        if (move_uploaded_file($file_tmp, $destination)) {
                            $attachment_urls[] = $destination;
                        } else {
                            $errors['attachments'] = "Gagal mengunggah file: $file_name";
                        }
                    } else {
                        $errors['attachments'] = "File $file_name terlalu besar (Maks 10MB).";
                    }
                } else {
                    $errors['attachments'] = "Format file $file_name tidak didukung.";
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $uuid = uniqid(); // Generate UUID sederhana
            
            $sql = "INSERT INTO projects (
                        uuid, umkm_user_id, title, description, category, 
                        budget_range_min, budget_range_max, budget_type, 
                        deadline, required_skills, attachment_urls, status, created_at
                    ) VALUES (
                        :uuid, :umkm_uid, :title, :desc, :cat, 
                        :b_min, :b_max, :b_type, 
                        :deadline, :skills, :att, 'open', NOW()
                    )";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':uuid'         => $uuid,
                ':umkm_uid'     => $_SESSION['user_id'],
                ':title'        => $title,
                ':desc'         => $description,
                ':cat'          => $category,
                ':b_min'        => $budget_min,
                ':b_max'        => $budget_max,
                ':b_type'       => $budget_type,
                ':deadline'     => $deadline,
                ':skills'       => json_encode($required_skills), 
                ':att'          => json_encode($attachment_urls)  
            ]);

            $success_msg = "Proyek berhasil diterbitkan! Para creative worker akan segera melihatnya.";
            // Reset POST data agar form bersih
            $_POST = []; 
        } catch (PDOException $e) {
            $errors['database'] = "Terjadi kesalahan sistem: " . $e->getMessage();
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2A5EA8;
            --secondary-color: #FF7E5F;
            --bg-light: #F8F9FA;
            --border-radius: 12px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6;
            color: #333;
        }

        /* Layout Adjustment for Sidebar */
        .main-content {
            padding: 20px;
            /* Asumsi sidebar width sekitar 250px, sesuaikan jika perlu */
            /* margin-left: 250px; di handle oleh layout sidebar biasanya */ 
        }

        .card-form {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 30px;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Category Cards Styling */
        .category-radio {
            display: none;
        }
        
        .category-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
        }

        .category-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
            background-color: #fbfdff;
        }

        .category-radio:checked + .category-card {
            border-color: var(--primary-color);
            background-color: #eef6ff;
            box-shadow: 0 4px 10px rgba(62, 127, 213, 0.2);
        }

        .category-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        /* Drag & Drop Upload Zone */
        .upload-zone {
            border: 2px dashed #cbd5e0;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            background-color: #fafbfc;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .upload-zone.dragover {
            border-color: var(--primary-color);
            background-color: #eef6ff;
        }

        .upload-zone:hover {
            border-color: var(--primary-color);
        }

        .upload-icon {
            font-size: 3rem;
            color: #a0aec0;
            margin-bottom: 15px;
        }

        /* File Previews */
        .file-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .file-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-preview-item .file-type-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
        }

        /* Skill Tags */
        .skill-badge {
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        .skill-badge:hover {
            opacity: 0.8;
        }
        .skill-badge.active {
            background-color: var(--primary-color) !important;
            color: white !important;
            border-color: var(--primary-color) !important;
        }

        /* Budget Inputs */
        .input-group-text {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        .alert-floating {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    <?php include 'dashboard-sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show alert-floating" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($errors['database'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $errors['database']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <form action="create-project.php" method="POST" enctype="multipart/form-data" id="createProjectForm">
                        
                        <div class="card card-form mb-5">
                            <div class="form-header">
                                <h2 class="mb-1"><i class="fas fa-rocket me-2"></i>Buat Proyek Baru</h2>
                                <p class="mb-0 opacity-75">Jelaskan kebutuhan bisnis Anda untuk menemukan talenta terbaik.</p>
                            </div>
                            
                            <div class="card-body p-4 p-md-5">
                                
                                <div class="mb-5">
                                    <div class="form-section-title">
                                        <i class="fas fa-file-alt"></i> Detail Proyek
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label fw-bold">Judul Proyek <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-lg <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                               id="title" name="title" 
                                               placeholder="Contoh: Pembuatan Video Pendek untuk Instagram Reels Produk Kopi"
                                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                                        <?php if(isset($errors['title'])): ?><div class="invalid-feedback"><?php echo $errors['title']; ?></div><?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Kategori Proyek <span class="text-danger">*</span></label>
                                        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3">
                                            <div class="col">
                                                <input type="radio" name="category" id="cat_video" value="video" class="category-radio" <?php echo (($_POST['category'] ?? '') === 'video') ? 'checked' : ''; ?>>
                                                <label for="cat_video" class="category-card w-100 d-block">
                                                    <i class="fas fa-video"></i>
                                                    <div class="small fw-bold">Video & Animasi</div>
                                                </label>
                                            </div>
                                            <div class="col">
                                                <input type="radio" name="category" id="cat_website" value="website" class="category-radio" <?php echo (($_POST['category'] ?? '') === 'website') ? 'checked' : ''; ?>>
                                                <label for="cat_website" class="category-card w-100 d-block">
                                                    <i class="fas fa-laptop-code"></i>
                                                    <div class="small fw-bold">Website & IT</div>
                                                </label>
                                            </div>
                                            <div class="col">
                                                <input type="radio" name="category" id="cat_logo" value="logo" class="category-radio" <?php echo (($_POST['category'] ?? '') === 'logo') ? 'checked' : ''; ?>>
                                                <label for="cat_logo" class="category-card w-100 d-block">
                                                    <i class="fas fa-paint-brush"></i>
                                                    <div class="small fw-bold">Desain & Logo</div>
                                                </label>
                                            </div>
                                            <div class="col">
                                                <input type="radio" name="category" id="cat_socmed" value="social_media" class="category-radio" <?php echo (($_POST['category'] ?? '') === 'social_media') ? 'checked' : ''; ?>>
                                                <label for="cat_socmed" class="category-card w-100 d-block">
                                                    <i class="fas fa-hashtag"></i>
                                                    <div class="small fw-bold">Social Media</div>
                                                </label>
                                            </div>
                                            <div class="col">
                                                <input type="radio" name="category" id="cat_content" value="content" class="category-radio" <?php echo (($_POST['category'] ?? '') === 'content') ? 'checked' : ''; ?>>
                                                <label for="cat_content" class="category-card w-100 d-block">
                                                    <i class="fas fa-pen-nib"></i>
                                                    <div class="small fw-bold">Penulisan</div>
                                                </label>
                                            </div>
                                            <div class="col">
                                                <input type="radio" name="category" id="cat_marketing" value="marketing" class="category-radio" <?php echo (($_POST['category'] ?? '') === 'marketing') ? 'checked' : ''; ?>>
                                                <label for="cat_marketing" class="category-card w-100 d-block">
                                                    <i class="fas fa-bullhorn"></i>
                                                    <div class="small fw-bold">Marketing</div>
                                                </label>
                                            </div>
                                        </div>
                                        <?php if(isset($errors['category'])): ?><div class="text-danger small mt-1"><?php echo $errors['category']; ?></div><?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label fw-bold">Deskripsi Lengkap <span class="text-danger">*</span></label>
                                        <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                                  id="description" name="description" rows="6" 
                                                  placeholder="Jelaskan tujuan proyek, target audiens, gaya yang diinginkan, dan deliverables..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                        <div class="form-text text-muted" id="desc-hint">Ceritakan detail proyek Anda sejelas mungkin.</div>
                                    </div>
                                </div>

                                <div class="mb-5">
                                    <div class="form-section-title">
                                        <i class="fas fa-cloud-upload-alt"></i> File Pendukung & Referensi
                                    </div>
                                    
                                    <div class="alert alert-info py-2 small">
                                        <i class="fas fa-info-circle me-1"></i> 
                                        <span id="upload-hint-text">Upload brief, referensi gaya, atau aset yang Anda miliki.</span>
                                    </div>

                                    <div class="upload-zone" id="dropZone">
                                        <div class="upload-icon">
                                            <i class="fas fa-images"></i>
                                        </div>
                                        <h5 class="fw-bold">Seret & Lepas file di sini</h5>
                                        <p class="text-muted mb-3">atau klik untuk menjelajah file komputer Anda</p>
                                        <p class="small text-muted mb-0">Support: JPG, PNG, PDF, DOCX, MP4 (Max 10MB/file)</p>
                                        
                                        <input type="file" name="attachments[]" id="fileInput" multiple class="d-none" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.mp4,.mov">
                                    </div>
                                    <?php if(isset($errors['attachments'])): ?><div class="text-danger small mt-2"><?php echo $errors['attachments']; ?></div><?php endif; ?>

                                    <div class="file-preview-container" id="previewContainer">
                                        </div>
                                </div>

                                <div class="mb-5">
                                    <div class="form-section-title">
                                        <i class="fas fa-tools"></i> Keahlian yang Dibutuhkan
                                    </div>
                                    <p class="small text-muted">Klik untuk memilih keahlian yang relevan dengan proyek ini.</p>
                                    
                                    <div class="d-flex flex-wrap gap-2 mb-3" id="skillsContainer">
                                        <span class="badge bg-light text-dark border p-2 skill-badge" data-value="Video Editing">Video Editing</span>
                                        <span class="badge bg-light text-dark border p-2 skill-badge" data-value="Motion Graphics">Motion Graphics</span>
                                        <span class="badge bg-light text-dark border p-2 skill-badge" data-value="Adobe Premiere">Adobe Premiere</span>
                                        <span class="badge bg-light text-dark border p-2 skill-badge" data-value="Graphic Design">Graphic Design</span>
                                        <span class="badge bg-light text-dark border p-2 skill-badge" data-value="Copywriting">Copywriting</span>
                                        <span class="badge bg-light text-dark border p-2 skill-badge" data-value="Voice Over">Voice Over</span>
                                        <span class="badge bg-light text-dark border p-2 skill-badge" data-value="Social Media Management">Social Media Management</span>
                                    </div>
                                    <input type="hidden" name="required_skills" id="requiredSkillsInput" value="<?php echo htmlspecialchars(implode(',', $_POST['required_skills'] ?? [])); ?>">
                                </div>

                                <div class="mb-4">
                                    <div class="form-section-title">
                                        <i class="fas fa-wallet"></i> Budget & Deadline
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Tipe Budget</label>
                                            <select class="form-select mb-3" name="budget_type" id="budgetType">
                                                <option value="fixed" <?php echo (($_POST['budget_type'] ?? '') === 'fixed') ? 'selected' : ''; ?>>Fixed Price (Harga Tetap)</option>
                                                <option value="hourly" <?php echo (($_POST['budget_type'] ?? '') === 'hourly') ? 'selected' : ''; ?>>Hourly Rate (Per Jam)</option>
                                                <option value="negotiable" <?php echo (($_POST['budget_type'] ?? '') === 'negotiable') ? 'selected' : ''; ?>>Negotiable (Nego)</option>
                                            </select>

                                            <div id="budgetInputs">
                                                <label class="form-label small text-muted">Kisaran Budget (IDR)</label>
                                                <div class="input-group mb-2">
                                                    <span class="input-group-text">Min</span>
                                                    <input type="number" class="form-control" name="budget_min" placeholder="0" value="<?php echo htmlspecialchars($_POST['budget_min'] ?? ''); ?>">
                                                </div>
                                                <div class="input-group">
                                                    <span class="input-group-text">Max</span>
                                                    <input type="number" class="form-control" name="budget_max" placeholder="0" value="<?php echo htmlspecialchars($_POST['budget_max'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Deadline Proyek <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control <?php echo isset($errors['deadline']) ? 'is-invalid' : ''; ?>" 
                                                   name="deadline" 
                                                   min="<?php echo date('Y-m-d'); ?>"
                                                   value="<?php echo htmlspecialchars($_POST['deadline'] ?? ''); ?>">
                                            <div class="form-text">Kapan proyek ini harus selesai?</div>
                                            <?php if(isset($errors['deadline'])): ?><div class="invalid-feedback"><?php echo $errors['deadline']; ?></div><?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="d-flex justify-content-end gap-3">
                                    <a href="dashboard.php" class="btn btn-light px-4">Batal</a>
                                    <button type="submit" class="btn btn-primary px-5 py-2 fw-bold">
                                        <i class="fas fa-paper-plane me-2"></i> Terbitkan Proyek
                                    </button>
                                </div>

                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- 1. HANDLING FILE UPLOAD UI ---
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');
            const previewContainer = document.getElementById('previewContainer');
            
            dropZone.addEventListener('click', () => fileInput.click());

            // Drag effects
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.remove('dragover');
                }, false);
            });

            // Handle File Drop
            dropZone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files; // Assign dropped files to input
                handleFiles(files);
            });

            // Handle Input Change
            fileInput.addEventListener('change', function() {
                handleFiles(this.files);
            });

            function handleFiles(files) {
                previewContainer.innerHTML = ''; // Clear previous previews
                if (files.length > 0) {
                    Array.from(files).forEach(file => {
                        const reader = new FileReader();
                        const div = document.createElement('div');
                        div.className = 'file-preview-item';
                        
                        reader.onload = function(e) {
                            if (file.type.startsWith('image/')) {
                                div.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                            } else if (file.type.startsWith('video/')) {
                                div.innerHTML = `<i class="fas fa-file-video file-type-icon"></i>`;
                            } else if (file.type === 'application/pdf') {
                                div.innerHTML = `<i class="fas fa-file-pdf file-type-icon text-danger"></i>`;
                            } else {
                                div.innerHTML = `<i class="fas fa-file-alt file-type-icon text-secondary"></i>`;
                            }
                        }
                        reader.readAsDataURL(file);
                        previewContainer.appendChild(div);
                    });
                }
            }

            // --- 2. DYNAMIC HINTS FOR VIDEO CATEGORY ---
            const catRadios = document.querySelectorAll('input[name="category"]');
            const uploadHint = document.getElementById('upload-hint-text');
            const descHint = document.getElementById('desc-hint');

            catRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'video') {
                        uploadHint.innerHTML = "<strong>Tips Video:</strong> Upload Storyboard, Script, atau contoh video referensi (Moodboard) agar editor paham visi Anda.";
                        descHint.innerText = "Sebutkan durasi video, platform (IG/TikTok/Youtube), dan referensi visual.";
                    } else if (this.value === 'website') {
                        uploadHint.innerHTML = "Upload sitemap, wireframe, atau referensi desain website yang disukai.";
                        descHint.innerText = "Jelaskan fitur utama website, jumlah halaman, dan tech stack jika ada.";
                    } else {
                        uploadHint.innerText = "Upload brief, referensi gaya, atau aset yang Anda miliki.";
                        descHint.innerText = "Ceritakan detail proyek Anda sejelas mungkin.";
                    }
                });
            });

            // --- 3. SKILL TAGS INTERACTION ---
            const skillBadges = document.querySelectorAll('.skill-badge');
            const skillsInput = document.getElementById('requiredSkillsInput');
            let selectedSkills = skillsInput.value ? skillsInput.value.split(',') : [];

            // Set initial active state
            skillBadges.forEach(badge => {
                if (selectedSkills.includes(badge.getAttribute('data-value'))) {
                    badge.classList.add('active');
                }

                badge.addEventListener('click', function() {
                    const val = this.getAttribute('data-value');
                    if (this.classList.contains('active')) {
                        this.classList.remove('active');
                        selectedSkills = selectedSkills.filter(s => s !== val);
                    } else {
                        this.classList.add('active');
                        if (!selectedSkills.includes(val)) selectedSkills.push(val);
                    }
                    skillsInput.value = selectedSkills.join(',');
                });
            });

            // --- 4. BUDGET TOGGLE ---
            const budgetType = document.getElementById('budgetType');
            const budgetInputs = document.getElementById('budgetInputs');

            function toggleBudget() {
                if (budgetType.value === 'negotiable') {
                    budgetInputs.style.opacity = '0.5';
                    budgetInputs.querySelectorAll('input').forEach(input => input.disabled = true);
                } else {
                    budgetInputs.style.opacity = '1';
                    budgetInputs.querySelectorAll('input').forEach(input => input.disabled = false);
                }
            }
            
            budgetType.addEventListener('change', toggleBudget);
            toggleBudget(); // Run on init
        });
    </script>
</body>
</html>