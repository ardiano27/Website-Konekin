<?php
include "check_login.php";
require_once 'config/Database.php';

// Hanya creative worker yang bisa akses halaman ini
if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

$database = new DatabaseConnection();
$conn = $database->getConnection();

// Get project ID from URL
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($project_id === 0) {
    header("Location: find-projects.php");
    exit;
}

// Get project details
$sql = "
    SELECT 
        p.*,
        u.full_name as umkm_name,
        up.business_name,
        up.business_description,
        up.business_type,
        up.business_logo_url,
        up.business_website,
        COUNT(DISTINCT pr.id) as proposal_count,
        (SELECT COUNT(*) FROM proposals pr2 WHERE pr2.project_id = p.id AND pr2.creative_user_id = :user_id) as already_applied
    FROM projects p
    JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    LEFT JOIN proposals pr ON p.id = pr.project_id
    WHERE p.id = :project_id
    GROUP BY p.id
";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ':project_id' => $project_id,
    ':user_id' => $_SESSION['user_id']
]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: find-projects.php");
    exit;
}

// Handle proposal submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_proposal'])) {
    $cover_letter = $_POST['cover_letter'] ?? '';
    $proposed_budget = $_POST['proposed_budget'] ?? 0;
    $timeline_days = $_POST['timeline_days'] ?? 0;
    
    // Validate input
    $errors = [];
    
    if (empty($cover_letter)) {
        $errors[] = "Cover letter harus diisi";
    }
    
    if ($proposed_budget <= 0) {
        $errors[] = "Budget yang diusulkan harus lebih dari 0";
    }
    
    if ($timeline_days <= 0) {
        $errors[] = "Timeline harus lebih dari 0 hari";
    }
    
    // Check if already applied
    if ($project['already_applied'] > 0) {
        $errors[] = "Anda sudah mengajukan proposal untuk proyek ini";
    }
    
    if (empty($errors)) {
        try {
            // Generate UUID
            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            // Insert proposal
            $insert_sql = "
                INSERT INTO proposals (uuid, project_id, creative_user_id, cover_letter, proposed_budget, timeline_days, status, submitted_at)
                VALUES (:uuid, :project_id, :creative_user_id, :cover_letter, :proposed_budget, :timeline_days, 'submitted', NOW())
            ";
            
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->execute([
                ':uuid' => $uuid,
                ':project_id' => $project_id,
                ':creative_user_id' => $_SESSION['user_id'],
                ':cover_letter' => $cover_letter,
                ':proposed_budget' => $proposed_budget,
                ':timeline_days' => $timeline_days
            ]);
            
            $success = "Proposal berhasil diajukan!";
            
            // Refresh project data
            $stmt->execute([
                ':project_id' => $project_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $errors[] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['title']); ?> - Konekin</title>
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
            padding: 10px 10px 10px;
            margin-top: 0;
        }
        
        .project-header {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-light);
        }
        
        .project-category {
            display: inline-block;
            background-color: #e9f7fe;
            color: var(--primary-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .budget-badge {
            background-color: var(--primary-color);
            color: white;
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .umkm-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border-color);
        }
        
        .skill-tag {
            background-color: #f1f8ff;
            border: 1px solid #d0e7ff;
            color: var(--primary-color);
            border-radius: 20px;
            padding: 6px 15px;
            font-size: 0.85rem;
            margin-right: 8px;
            margin-bottom: 8px;
            display: inline-block;
            font-weight: 500;
        }
        
        .proposal-form {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow-light);
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 12px 15px;
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
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="find-projects.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Pencarian
                </a>
            </div>
            
            <!-- Project Header -->
            <div class="project-header">
                <div class="row">
                    <div class="col-md-8">
                        <span class="project-category">
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
                            <i class="<?php echo $icon; ?> me-1"></i>
                            <?php 
                            $category_names = [
                                'website' => 'Website',
                                'logo' => 'Logo',
                                'social_media' => 'Social Media',
                                'video' => 'Video',
                                'content' => 'Content',
                                'marketing' => 'Marketing',
                                'other' => 'Lainnya'
                            ];
                            echo isset($category_names[$project['category']]) ? $category_names[$project['category']] : 'Lainnya';
                            ?>
                        </span>
                        <h1 class="mb-3"><?php echo htmlspecialchars($project['title']); ?></h1>
                        <p class="lead text-muted"><?php echo htmlspecialchars($project['description']); ?></p>
                        
                        <!-- Required Skills -->
                        <?php if (!empty($project['required_skills'])): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3">Keterampilan yang Dibutuhkan:</h6>
                                <?php 
                                $skills = json_decode($project['required_skills'], true);
                                if (is_array($skills) && count($skills) > 0):
                                    foreach ($skills as $skill):
                                ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                <?php 
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4 text-end">
                        <div class="mb-4">
                            <span class="budget-badge">
                                <?php if ($project['budget_range_min'] && $project['budget_range_max']): ?>
                                    Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?> - 
                                    Rp <?php echo number_format($project['budget_range_max'], 0, ',', '.'); ?>
                                <?php elseif ($project['budget_range_min']): ?>
                                    Mulai Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?>
                                <?php else: ?>
                                    Harga negotiable
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="text-muted">
                            <p class="mb-1"><i class="fas fa-paper-plane me-2"></i><?php echo $project['proposal_count']; ?> proposal diajukan</p>
                            <?php if (!empty($project['deadline'])): ?>
                                <p class="mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    Deadline: <?php echo date('d M Y', strtotime($project['deadline'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- UMKM Info -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Tentang UMKM</h5>
                            <div class="d-flex align-items-center">
                                <?php if (!empty($project['business_logo_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($project['business_logo_url']); ?>" alt="Logo UMKM" class="umkm-avatar me-4">
                                <?php else: ?>
                                    <div class="umkm-avatar bg-light d-flex align-items-center justify-content-center me-4">
                                        <i class="fas fa-store fa-2x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <h4 class="mb-1"><?php echo htmlspecialchars($project['business_name'] ?? $project['umkm_name'] ?? 'Unknown'); ?></h4>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($project['business_description'] ?? 'Tidak ada deskripsi'); ?></p>
                                    <div class="d-flex gap-3">
                                        <?php if (!empty($project['business_website'])): ?>
                                            <a href="<?php echo htmlspecialchars($project['business_website']); ?>" target="_blank" class="text-primary">
                                                <i class="fas fa-globe me-1"></i>Website
                                            </a>
                                        <?php endif; ?>
                                        <span class="text-muted">
                                            <i class="fas fa-building me-1"></i>
                                            <?php 
                                            $business_types = [
                                                'food' => 'Makanan & Minuman',
                                                'fashion' => 'Fashion',
                                                'craft' => 'Kerajinan',
                                                'service' => 'Jasa',
                                                'retail' => 'Retail',
                                                'other' => 'Lainnya'
                                            ];
                                            echo isset($business_types[$project['business_type']]) ? $business_types[$project['business_type']] : 'UMKM';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Proposal Form -->
            <?php if ($project['already_applied'] > 0): ?>
                <div class="alert alert-success text-center">
                    <h4><i class="fas fa-check-circle me-2"></i>Proposal Sudah Diajukan</h4>
                    <p class="mb-0">Anda sudah mengajukan proposal untuk proyek ini. Lihat status proposal di <a href="my-proposals.php" class="alert-link">Proposal Saya</a>.</p>
                </div>
            <?php else: ?>
                <div class="proposal-form">
                    <h3 class="mb-4">Ajukan Proposal</h3>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="proposed_budget" class="form-label">Budget yang Diusulkan (Rp)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" class="form-control" id="proposed_budget" name="proposed_budget" 
                                               value="<?php echo $_POST['proposed_budget'] ?? ''; ?>" 
                                               min="10000" step="1000" required>
                                    </div>
                                    <small class="text-muted">Budget harus dalam rentang yang wajar</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="timeline_days" class="form-label">Timeline Pengerjaan (hari)</label>
                                    <input type="number" class="form-control" id="timeline_days" name="timeline_days" 
                                           value="<?php echo $_POST['timeline_days'] ?? ''; ?>" 
                                           min="1" max="365" required>
                                    <small class="text-muted">Estimasi waktu pengerjaan dalam hari</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="cover_letter" class="form-label">Cover Letter</label>
                            <textarea class="form-control" id="cover_letter" name="cover_letter" rows="8" 
                                      placeholder="Jelaskan mengapa Anda cocok untuk proyek ini, pengalaman relevan, dan pendekatan yang akan Anda gunakan..." 
                                      required><?php echo $_POST['cover_letter'] ?? ''; ?></textarea>
                            <small class="text-muted">Minimal 100 karakter. Jelaskan secara detail mengapa Anda adalah pilihan terbaik.</small>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="submit_proposal" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Ajukan Proposal
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counter for cover letter
        document.addEventListener('DOMContentLoaded', function() {
            const coverLetter = document.getElementById('cover_letter');
            const charCount = document.createElement('small');
            charCount.className = 'text-muted float-end';
            coverLetter.parentNode.appendChild(charCount);
            
            function updateCharCount() {
                const count = coverLetter.value.length;
                charCount.textContent = count + ' karakter';
                
                if (count < 100) {
                    charCount.className = 'text-danger float-end';
                } else {
                    charCount.className = 'text-success float-end';
                }
            }
            
            coverLetter.addEventListener('input', updateCharCount);
            updateCharCount();
        });
    </script>
</body>
</html>