<?php
include "check_login.php";
require_once 'config/Database.php';

// Hanya creative worker yang bisa akses
if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

$database = new DatabaseConnection();
$conn = $database->getConnection();

// Handle unsave project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsave'])) {
    $project_id = intval($_POST['project_id']);
    
    $delete_sql = "DELETE FROM saved_projects WHERE user_id = :user_id AND project_id = :project_id";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':project_id' => $project_id
    ]);
    
    header("Location: saved-projects.php");
    exit;
}

// Ambil proyek yang disimpan
$sql = "
    SELECT 
        p.*, 
        u.full_name as umkm_name,
        up.business_name,
        up.business_logo_url,
        up.business_type,
        COUNT(DISTINCT pr.id) as proposal_count,
        (SELECT COUNT(*) FROM proposals pr2 WHERE pr2.project_id = p.id AND pr2.creative_user_id = :user_id) as already_applied,
        sp.saved_at
    FROM saved_projects sp
    JOIN projects p ON sp.project_id = p.id
    JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    LEFT JOIN proposals pr ON p.id = pr.project_id
    WHERE sp.user_id = :user_id AND p.status = 'open'
    GROUP BY p.id 
    ORDER BY sp.saved_at DESC
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $saved_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $saved_projects = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyek Disimpan - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #549efeff;
            --primary-light: #82a8db;
            --primary-dark: #3498db;
            --secondary: #7209b7;
            --accent: #f72585;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border: #dee2e6;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            --hover-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        .main-content {
            padding-horizontal: 20px;
            padding-top: 10px;
        }
        
        /* Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 1.1rem;
        }
        
        /* Results Count */
        .results-count {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
        }
        
        /* Project Cards */
        .project-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            transition: all 0.3s;
        }
        
        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
        }
        
        .saved-badge {
            background: linear-gradient(to right, var(--primary), var(--primary-light));
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .project-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--dark);
        }
        
        .project-description {
            color: var(--gray);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 18px;
        }
        
        .budget-info {
            background: rgba(67, 97, 238, 0.1);
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .saved-date {
            font-size: 0.85rem;
            color: var(--gray);
            display: flex;
            align-items: center;
        }
        
        .saved-date i {
            margin-right: 5px;
        }
        
        .skill-tag {
            background: rgba(67, 97, 238, 0.08);
            border: 1px solid rgba(67, 97, 238, 0.2);
            color: var(--primary);
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.8rem;
            margin-right: 8px;
            margin-bottom: 8px;
            display: inline-block;
        }
        
        .apply-button {
            background: linear-gradient(to right, var(--accent), #ff6b9d);
            border: none;
            color: white;
            font-weight: 600;
            border-radius: 10px;
            padding: 12px 20px;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: block;
            width: 100%;
        }
        
        .apply-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(247, 37, 133, 0.3);
            color: white;
        }
        
        .btn-outline-primary {
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 500;
            width: 100%;
        }
        
        .btn-danger {
            border-radius: 10px;
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--primary);
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .empty-state p {
            font-size: 1rem;
            margin-bottom: 25px;
        }
        
        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 10px;
            }
            
            .project-card {
                padding: 20px;
            }
        }
        
        /* Loading Animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
        }
        
        .custom-toast {
            min-width: 300px;
            background: white;
            border-left: 4px solid var(--primary);
            box-shadow: var(--hover-shadow);
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
                        <h1 class="mb-2"><i class="fas fa-bookmark me-2"></i>Proyek Disimpan</h1>
                        <p class="mb-0">Proyek yang telah Anda simpan untuk diajukan nanti</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="find-projects.php" class="btn btn-light">
                            <i class="fas fa-search me-1"></i>Cari Proyek Baru
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Daftar Proyek Disimpan -->
            <div class="row">
                <div class="col-12">
                    <div class="results-count fade-in">
                        <i class="fas fa-bookmark me-2 text-primary"></i>
                        <?php echo count($saved_projects); ?> Proyek Disimpan
                    </div>
                    
                    <?php if (count($saved_projects) > 0): ?>
                        <?php foreach ($saved_projects as $index => $project): ?>
                            <div class="project-card fade-in" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <span class="saved-badge mb-2">
                                                    <i class="fas fa-bookmark me-1"></i>Disimpan
                                                </span>
                                                <h4 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h4>
                                            </div>
                                            <form method="POST" action="" class="unsave-form">
                                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                                <input type="hidden" name="unsave" value="1">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-trash-alt me-1"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <p class="project-description">
                                            <?php 
                                            $description = $project['description'] ?? '';
                                            echo htmlspecialchars(mb_strlen($description) > 200 ? mb_substr($description, 0, 200) . '...' : $description); 
                                            ?>
                                        </p>
                                        
                                        <!-- Skills -->
                                        <?php if (!empty($project['required_skills'])): ?>
                                            <div class="mb-3">
                                                <?php 
                                                $skills = json_decode($project['required_skills'], true);
                                                if (is_array($skills) && count($skills) > 0):
                                                    foreach (array_slice($skills, 0, 5) as $skill):
                                                ?>
                                                    <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                                <?php 
                                                    endforeach;
                                                    if (count($skills) > 5): 
                                                ?>
                                                    <span class="skill-tag">+<?php echo count($skills) - 5; ?> lainnya</span>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex align-items-center text-muted">
                                            <div class="saved-date">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                Disimpan pada: <?php echo date('d M Y', strtotime($project['saved_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="d-flex flex-column h-100 justify-content-between">
                                            <!-- Budget -->
                                            <?php if ($project['budget_range_min'] || $project['budget_range_max']): ?>
                                                <div class="mb-3">
                                                    <div class="budget-info">
                                                        <?php if ($project['budget_range_min'] && $project['budget_range_max']): ?>
                                                            Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?> - 
                                                            Rp <?php echo number_format($project['budget_range_max'], 0, ',', '.'); ?>
                                                        <?php elseif ($project['budget_range_min']): ?>
                                                            Mulai Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?>
                                                        <?php else: ?>
                                                            Harga negotiable
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Deadline -->
                                            <?php if (!empty($project['deadline'])): ?>
                                                <div class="mb-3">
                                                    <?php 
                                                    try {
                                                        $deadline = new DateTime($project['deadline']);
                                                        $now = new DateTime();
                                                        $interval = $now->diff($deadline);
                                                        if ($deadline > $now) {
                                                            echo '<div class="text-warning"><i class="fas fa-clock me-1"></i>' . $interval->days . " hari lagi</div>";
                                                        } else {
                                                            echo '<div class="text-danger"><i class="fas fa-clock me-1"></i>Tenggat waktu terlewat</div>';
                                                        }
                                                    } catch (Exception $e) {
                                                        echo '<div class="text-muted"><i class="fas fa-clock me-1"></i>Tanggal tidak valid</div>';
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Proposal Count -->
                                            <div class="mb-3 text-muted">
                                                <i class="fas fa-paper-plane me-1"></i>
                                                <?php echo $project['proposal_count'] ?? 0; ?> proposal
                                            </div>
                                            
                                            <!-- Action Button -->
                                            <div class="mt-auto">
                                                <?php if (($project['already_applied'] ?? 0) > 0): ?>
                                                    <div class="alert alert-success py-2 mb-2 text-center">
                                                        <i class="fas fa-check me-1"></i>Sudah Diajukan
                                                    </div>
                                                    <a href="view-project1.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary">
                                                        Lihat Detail
                                                    </a>
                                                <?php else: ?>
                                                    <a href="view-project1.php?id=<?php echo $project['id']; ?>" class="apply-button">
                                                        Ajukan Proposal <i class="fas fa-arrow-right ms-2"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state fade-in">
                            <i class="fas fa-bookmark"></i>
                            <h3 class="mt-3">Belum ada proyek yang disimpan</h3>
                            <p class="mb-4">Simpan proyek yang menarik untuk diajukan nanti</p>
                            <a href="find-projects.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Cari Proyek
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to project items
            const items = document.querySelectorAll('.fade-in');
            items.forEach((item, index) => {
                setTimeout(() => {
                    item.classList.add('visible');
                }, index * 100);
            });
            
            // AJAX untuk unsave project
            const unsaveForms = document.querySelectorAll('.unsave-form');
            unsaveForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const button = this.querySelector('button');
                    const originalText = button.innerHTML;
                    const projectCard = this.closest('.project-card');
                    
                    // Tampilkan loading
                    button.innerHTML = '<span class="loading-spinner" style="width: 12px; height: 12px; border-width: 2px;"></span>';
                    button.disabled = true;
                    
                    fetch('saved-projects.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(() => {
                        // Remove project card with animation
                        projectCard.style.transition = 'all 0.3s';
                        projectCard.style.opacity = '0';
                        projectCard.style.transform = 'translateX(100px)';
                        
                        setTimeout(() => {
                            projectCard.remove();
                            
                            // Update results count
                            const resultsCount = document.querySelector('.results-count');
                            if (resultsCount) {
                                const currentCount = parseInt(resultsCount.textContent.match(/\d+/)[0]);
                                const newCount = currentCount - 1;
                                resultsCount.innerHTML = `<i class="fas fa-bookmark me-2 text-primary"></i>${newCount} Proyek Disimpan`;
                                
                                if (newCount === 0) {
                                    // Show empty state
                                    const emptyState = document.createElement('div');
                                    emptyState.className = 'empty-state fade-in visible';
                                    emptyState.innerHTML = `
                                        <i class="fas fa-bookmark"></i>
                                        <h3 class="mt-3">Belum ada proyek yang disimpan</h3>
                                        <p class="mb-4">Simpan proyek yang menarik untuk diajukan nanti</p>
                                        <a href="find-projects.php" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>Cari Proyek
                                        </a>
                                    `;
                                    
                                    document.querySelector('.row > .col-12').appendChild(emptyState);
                                }
                            }
                            
                            showToast('Proyek dihapus dari daftar disimpan', 'info');
                        }, 300);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        button.innerHTML = originalText;
                        button.disabled = false;
                        showToast('Terjadi kesalahan', 'danger');
                    });
                });
            });
            
            // Toast notification function
            function showToast(message, type = 'info') {
                const toastContainer = document.getElementById('toastContainer');
                
                // Create toast element
                const toast = document.createElement('div');
                toast.className = `alert alert-${type === 'success' ? 'success' : type === 'danger' ? 'danger' : 'info'} alert-dismissible fade show custom-toast`;
                toast.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
                        <div>${message}</div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                `;
                
                toastContainer.appendChild(toast);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>