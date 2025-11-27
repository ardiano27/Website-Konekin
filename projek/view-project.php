<?php
include "check_login.php";

// Hanya UMKM yang bisa akses halaman ini
if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

$project_id = $_GET['id'] ?? 0;

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Ambil detail proyek
$project_sql = "
    SELECT 
        p.*,
        u.full_name as umkm_name,
        up.business_name,
        up.business_logo_url,
        COUNT(DISTINCT pr.id) as proposal_count,
        COUNT(DISTINCT c.id) as contract_count
    FROM projects p
    JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    LEFT JOIN proposals pr ON p.id = pr.project_id
    LEFT JOIN contracts c ON p.id = c.project_id AND c.status = 'active'
    WHERE p.id = :id AND p.umkm_user_id = :user_id
    GROUP BY p.id
";

$project_stmt = $conn->prepare($project_sql);
$project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
$project = $project_stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: projects.php");
    exit;
}

// Handle form edit
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_project'])) {
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
    
    // Update project
    if (empty($errors)) {
        try {
            $required_skills_json = json_encode($required_skills);
            
            $update_sql = "
                UPDATE projects 
                SET title = :title, 
                    description = :description, 
                    category = :category, 
                    budget_range_min = :budget_min, 
                    budget_range_max = :budget_max, 
                    budget_type = :budget_type, 
                    deadline = :deadline, 
                    required_skills = :required_skills,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND umkm_user_id = :user_id
            ";
            
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':category' => $category,
                ':budget_min' => $budget_min,
                ':budget_max' => $budget_max,
                ':budget_type' => $budget_type,
                ':deadline' => $deadline,
                ':required_skills' => $required_skills_json,
                ':id' => $project_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $success = true;
            
            // Refresh project data
            $project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
            $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $errors['database'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_files'])) {
    $attachment_urls = json_decode($project['attachment_urls'] ?? '[]', true) ?: [];
    
    if (isset($_FILES['new_attachments']) && !empty($_FILES['new_attachments']['name'][0])) {
        $upload_dir = 'uploads/projects/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['new_attachments']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['new_attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = time() . '_' . basename($_FILES['new_attachments']['name'][$key]);
                $file_path = $upload_dir . $file_name;
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'video/mp4', 'video/avi', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $file_type = mime_content_type($tmp_name);
                
                if (in_array($file_type, $allowed_types)) {
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $attachment_urls[] = $file_path;
                    } else {
                        $errors['attachments'] = 'Gagal mengupload beberapa file';
                    }
                } else {
                    $errors['attachments'] = 'Tipe file tidak diizinkan';
                }
            }
        }
        
        // Update attachment URLs in database
        if (empty($errors['attachments'])) {
            $attachment_urls_json = json_encode($attachment_urls);
            $update_attachments_sql = "UPDATE projects SET attachment_urls = :attachments, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $update_attachments_stmt = $conn->prepare($update_attachments_sql);
            $update_attachments_stmt->execute([':attachments' => $attachment_urls_json, ':id' => $project_id]);
            
            // Refresh project data
            $project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
            $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $file_index = $_POST['file_index'] ?? null;
    if ($file_index !== null) {
        $attachment_urls = json_decode($project['attachment_urls'] ?? '[]', true) ?: [];
        
        if (isset($attachment_urls[$file_index])) {
            // Delete physical file
            $file_path = $attachment_urls[$file_index];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Remove from array
            unset($attachment_urls[$file_index]);
            $attachment_urls = array_values($attachment_urls); // Reindex array
            
            // Update database
            $attachment_urls_json = json_encode($attachment_urls);
            $update_attachments_sql = "UPDATE projects SET attachment_urls = :attachments, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $update_attachments_stmt = $conn->prepare($update_attachments_sql);
            $update_attachments_stmt->execute([':attachments' => $attachment_urls_json, ':id' => $project_id]);
            
            // Refresh project data
            $project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
            $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Parse project data
$required_skills = json_decode($project['required_skills'] ?? '[]', true) ?: [];
$attachment_urls = json_decode($project['attachment_urls'] ?? '[]', true) ?: [];

// Calculate time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'baru saja';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "{$minutes} menit yang lalu";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "{$hours} jam yang lalu";
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return "{$days} hari yang lalu";
    } else {
        return date('d M Y', $time);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Proyek - Konekin</title>
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
        
        .detail-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-open { background-color: #d4edda; color: #155724; }
        .status-in_progress { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d1ecf1; color: #0c5460; }
        .status-draft { background-color: #e2e3e5; color: #383d41; }
        
        .skill-tag {
            background-color: #e9f7fe;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 20px;
            padding: 6px 12px;
            margin: 4px;
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .file-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .file-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-light);
        }
        
        .file-icon {
            width: 50px;
            height: 50px;
            background-color: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .edit-badge {
            background-color: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 10px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .edit-section {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid var(--accent-color);
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
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <?php 
                                $category_icons = [
                                    'website' => 'fas fa-globe',
                                    'logo' => 'fas fa-palette',
                                    'social_media' => 'fas fa-hashtag',
                                    'video' => 'fas fa-video',
                                    'content' => 'fas fa-file-alt',
                                    'marketing' => 'fas fa-bullhorn',
                                    'other' => 'fas fa-ellipsis-h'
                                ];
                                $icon = isset($category_icons[$project['category']]) ? $category_icons[$project['category']] : 'fas fa-folder';
                                ?>
                                <i class="<?php echo $icon; ?> fa-2x text-white"></i>
                            </div>
                            <div>
                                <h1 class="mb-2"><?php echo htmlspecialchars($project['title']); ?></h1>
                                <div class="d-flex align-items-center">
                                    <span class="status-badge status-<?php echo $project['status']; ?> me-3">
                                        <?php 
                                        $status_names = [
                                            'draft' => 'Draft',
                                            'open' => 'Open',
                                            'in_progress' => 'Sedang Berjalan',
                                            'completed' => 'Selesai'
                                        ];
                                        echo $status_names[$project['status']] ?? $project['status'];
                                        ?>
                                    </span>
                                    <?php if ($project['updated_at'] !== $project['created_at']): ?>
                                        <small class="edit-badge">
                                            <i class="fas fa-edit me-1"></i>
                                            diedit <?php echo timeAgo($project['updated_at']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="projects.php" class="btn btn-light me-2">
                            <i class="fas fa-arrow-left me-1"></i>Kembali
                        </a>
                        <a href="project-proposals.php?id=<?php echo $project['id']; ?>" class="btn btn-success">
                            <i class="fas fa-file-alt me-1"></i>Lihat Proposal
                            <?php if ($project['proposal_count'] > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $project['proposal_count']; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Berhasil!</strong> Proyek telah diperbarui.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- Project Details -->
                    <div class="detail-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4><i class="fas fa-info-circle me-2"></i>Detail Proyek</h4>
                            <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#editForm">
                                <i class="fas fa-edit me-1"></i>Edit Proyek
                            </button>
                        </div>
                        
                        <!-- Edit Form (Collapsed) -->
                        <div class="collapse mb-4" id="editForm">
                            <div class="edit-section">
                                <h5 class="mb-3">Edit Proyek</h5>
                                <form method="POST">
                                    <input type="hidden" name="edit_project" value="1">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Judul Proyek</label>
                                            <input type="text" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                                   name="title" value="<?php echo htmlspecialchars($project['title']); ?>" required>
                                            <?php if (isset($errors['title'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['title']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Kategori</label>
                                            <select class="form-select <?php echo isset($errors['category']) ? 'is-invalid' : ''; ?>" name="category" required>
                                                <option value="">Pilih Kategori</option>
                                                <option value="website" <?php echo $project['category'] === 'website' ? 'selected' : ''; ?>>Website</option>
                                                <option value="logo" <?php echo $project['category'] === 'logo' ? 'selected' : ''; ?>>Logo & Branding</option>
                                                <option value="social_media" <?php echo $project['category'] === 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                                                <option value="video" <?php echo $project['category'] === 'video' ? 'selected' : ''; ?>>Video</option>
                                                <option value="content" <?php echo $project['category'] === 'content' ? 'selected' : ''; ?>>Konten</option>
                                                <option value="marketing" <?php echo $project['category'] === 'marketing' ? 'selected' : ''; ?>>Marketing</option>
                                                <option value="other" <?php echo $project['category'] === 'other' ? 'selected' : ''; ?>>Lainnya</option>
                                            </select>
                                            <?php if (isset($errors['category'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['category']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Deskripsi</label>
                                        <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                                  name="description" rows="4" required><?php echo htmlspecialchars($project['description']); ?></textarea>
                                        <?php if (isset($errors['description'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['description']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Tipe Budget</label>
                                            <select class="form-select" name="budget_type" required>
                                                <option value="fixed" <?php echo $project['budget_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                                                <option value="hourly" <?php echo $project['budget_type'] === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                                <option value="negotiable" <?php echo $project['budget_type'] === 'negotiable' ? 'selected' : ''; ?>>Negotiable</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Budget Min (Rp)</label>
                                            <input type="number" class="form-control" name="budget_min" 
                                                   value="<?php echo $project['budget_range_min']; ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Budget Max (Rp)</label>
                                            <input type="number" class="form-control" name="budget_max" 
                                                   value="<?php echo $project['budget_range_max']; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Deadline</label>
                                        <input type="date" class="form-control" name="deadline" 
                                               value="<?php echo $project['deadline']; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Keahlian yang Dibutuhkan</label>
                                        <div>
                                            <?php
                                            $all_skills = ['Graphic Design', 'Web Development', 'Social Media Marketing', 'Content Writing', 'Video Editing', 'UI/UX Design', 'Digital Marketing', 'Photography', 'Logo Design', 'WordPress', 'Adobe Illustrator', 'Adobe Photoshop', 'Figma', 'PHP', 'JavaScript', 'Laravel', 'Vue.js', 'React', 'MySQL', 'SEO', 'Google Ads', 'Copywriting', 'Adobe Premiere', 'After Effects'];
                                            
                                            foreach ($all_skills as $skill) {
                                                $checked = in_array($skill, $required_skills) ? 'checked' : '';
                                                echo '
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="required_skills[]" value="' . htmlspecialchars($skill) . '" ' . $checked . '>
                                                    <label class="form-check-label">' . $skill . '</label>
                                                </div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#editForm">Batal</button>
                                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Display Project Details -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Deskripsi Proyek</h6>
                            <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Informasi Budget</h6>
                                <p>
                                    <strong>Tipe:</strong> 
                                    <?php 
                                    $budget_types = [
                                        'fixed' => 'Fixed Price',
                                        'hourly' => 'Hourly',
                                        'negotiable' => 'Negotiable'
                                    ];
                                    echo $budget_types[$project['budget_type']] ?? $project['budget_type'];
                                    ?>
                                </p>
                                <?php if ($project['budget_range_min'] || $project['budget_range_max']): ?>
                                    <p>
                                        <strong>Range Budget:</strong>
                                        <?php if ($project['budget_range_min'] && $project['budget_range_max']): ?>
                                            Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?> - 
                                            Rp <?php echo number_format($project['budget_range_max'], 0, ',', '.'); ?>
                                        <?php elseif ($project['budget_range_min']): ?>
                                            Mulai Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?>
                                        <?php else: ?>
                                            Harga negotiable
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Timeline</h6>
                                <p><strong>Deadline:</strong> 
                                    <?php 
                                    try {
                                        $deadline = new DateTime($project['deadline']);
                                        echo $deadline->format('d M Y');
                                    } catch (Exception $e) {
                                        echo "Belum ditentukan";
                                    }
                                    ?>
                                </p>
                                <p><strong>Dibuat:</strong> 
                                    <?php 
                                    try {
                                        $created = new DateTime($project['created_at']);
                                        echo $created->format('d M Y');
                                    } catch (Exception $e) {
                                        echo "Tanggal tidak valid";
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if (!empty($required_skills)): ?>
                            <div class="mt-4">
                                <h6 class="text-muted mb-2">Keahlian yang Dibutuhkan</h6>
                                <div>
                                    <?php foreach ($required_skills as $skill): ?>
                                        <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- File Attachments -->
                    <div class="detail-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4><i class="fas fa-paperclip me-2"></i>File Lampiran</h4>
                            <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="fas fa-plus me-1"></i>Tambah File
                            </button>
                        </div>
                        
                        <?php if (!empty($attachment_urls)): ?>
                            <div class="row">
                                <?php foreach ($attachment_urls as $index => $file_url): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="file-card">
                                            <div class="d-flex align-items-center">
                                                <div class="file-icon">
                                                    <?php
                                                    $extension = pathinfo($file_url, PATHINFO_EXTENSION);
                                                    $file_icons = [
                                                        'jpg' => 'fas fa-image',
                                                        'jpeg' => 'fas fa-image',
                                                        'png' => 'fas fa-image',
                                                        'gif' => 'fas fa-image',
                                                        'pdf' => 'fas fa-file-pdf',
                                                        'mp4' => 'fas fa-video',
                                                        'avi' => 'fas fa-video',
                                                        'doc' => 'fas fa-file-word',
                                                        'docx' => 'fas fa-file-word'
                                                    ];
                                                    $file_icon = $file_icons[strtolower($extension)] ?? 'fas fa-file';
                                                    ?>
                                                    <i class="<?php echo $file_icon; ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo basename($file_url); ?></h6>
                                                    <small class="text-muted">
                                                        <?php
                                                        if (file_exists($file_url)) {
                                                            $file_size = filesize($file_url);
                                                            if ($file_size < 1024) {
                                                                echo $file_size . ' bytes';
                                                            } elseif ($file_size < 1048576) {
                                                                echo round($file_size / 1024, 2) . ' KB';
                                                            } else {
                                                                echo round($file_size / 1048576, 2) . ' MB';
                                                            }
                                                        } else {
                                                            echo 'File tidak ditemukan';
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                                <div class="ms-3">
                                                    <a href="<?php echo $file_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary me-1">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="delete_file" value="1">
                                                        <input type="hidden" name="file_index" value="<?php echo $index; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin ingin menghapus file ini?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-upload fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada file yang diupload</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Project Stats -->
                    <div class="stats-card">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h3 class="mb-1"><?php echo $project['proposal_count']; ?></h3>
                                <small>Total Proposal</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h3 class="mb-1"><?php echo $project['contract_count']; ?></h3>
                                <small>Kontrak Aktif</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Project Info -->
                    <div class="detail-card">
                        <h5 class="mb-3">Informasi Proyek</h5>
                        
                        <div class="mb-3">
                            <small class="text-muted">Status</small>
                            <div>
                                <span class="status-badge status-<?php echo $project['status']; ?>">
                                    <?php echo $status_names[$project['status']] ?? $project['status']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">Kategori</small>
                            <div class="fw-bold">
                                <?php 
                                $category_names = [
                                    'website' => 'Website',
                                    'logo' => 'Logo & Branding',
                                    'social_media' => 'Social Media',
                                    'video' => 'Video',
                                    'content' => 'Konten',
                                    'marketing' => 'Marketing',
                                    'other' => 'Lainnya'
                                ];
                                echo $category_names[$project['category']] ?? 'Lainnya';
                                ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">Dibuat</small>
                            <div class="fw-bold">
                                <?php 
                                try {
                                    $created = new DateTime($project['created_at']);
                                    echo $created->format('d M Y H:i');
                                } catch (Exception $e) {
                                    echo "Tanggal tidak valid";
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">Terakhir Diupdate</small>
                            <div class="fw-bold">
                                <?php 
                                try {
                                    $updated = new DateTime($project['updated_at']);
                                    echo $updated->format('d M Y H:i');
                                } catch (Exception $e) {
                                    echo "Tanggal tidak valid";
                                }
                                ?>
                            </div>
                        </div>
                        
                        <?php if ($project['business_name']): ?>
                            <div class="mb-3">
                                <small class="text-muted">UMKM</small>
                                <div class="fw-bold"><?php echo htmlspecialchars($project['business_name']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="detail-card">
                        <h5 class="mb-3">Aksi Cepat</h5>
                        <div class="d-grid gap-2">
                            <a href="project-proposals.php?id=<?php echo $project['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-file-alt me-2"></i>Kelola Proposal
                            </a>
                            <?php if ($project['status'] === 'draft'): ?>
                                <a href="publish-project.php?id=<?php echo $project['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-rocket me-2"></i>Publish Proyek
                                </a>
                            <?php endif; ?>
                            <?php if ($project['status'] === 'open'): ?>
                                <a href="close-project.php?id=<?php echo $project['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-lock me-2"></i>Tutup Proyek
                                </a>
                            <?php endif; ?>
                            <a href="delete-project.php?id=<?php echo $project['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus proyek ini?')">
                                <i class="fas fa-trash me-2"></i>Hapus Proyek
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload File Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_files" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Pilih File</label>
                            <input type="file" class="form-control" name="new_attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.mp4,.avi,.doc,.docx">
                            <div class="form-text">Maksimal 5 file, masing-masing maksimal 10MB</div>
                        </div>
                        <?php if (isset($errors['attachments'])): ?>
                            <div class="alert alert-danger"><?php echo $errors['attachments']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide success alert after 5 seconds
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.classList.remove('show');
                }, 5000);
            }
            
            // Show edit timestamp with animation
            const editBadge = document.querySelector('.edit-badge');
            if (editBadge) {
                editBadge.style.opacity = '0';
                editBadge.style.transform = 'translateX(-10px)';
                
                setTimeout(() => {
                    editBadge.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    editBadge.style.opacity = '1';
                    editBadge.style.transform = 'translateX(0)';
                }, 500);
            }
        });
    </script>
</body>
</html>