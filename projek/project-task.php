<?php
session_start();
require_once 'config/Database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new DatabaseConnection();
$db = $database->getConnection();

// Get project ID from URL
$project_id = $_GET['project_id'] ?? 0;

// Get project details
$project_query = "SELECT p.*, u.full_name as umkm_name, up.business_name 
                  FROM projects p 
                  JOIN users u ON p.umkm_user_id = u.id 
                  LEFT JOIN umkm_profiles up ON u.id = up.user_id 
                  WHERE p.id = :project_id";
$stmt = $db->prepare($project_query);
$stmt->bindParam(':project_id', $project_id);
$stmt->execute();
$project = $stmt->fetch(PDO::FETCH_ASSOC);

// Get project tasks
$tasks_query = "SELECT * FROM project_tasks WHERE project_id = :project_id ORDER BY task_order ASC";
$stmt = $db->prepare($tasks_query);
$stmt->bindParam(':project_id', $project_id);
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get contract details
$contract_query = "SELECT * FROM contracts WHERE project_id = :project_id AND status = 'active'";
$stmt = $db->prepare($contract_query);
$stmt->bindParam(':project_id', $project_id);
$stmt->execute();
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate overall progress
$total_tasks = count($tasks);
$completed_tasks = 0;
$total_progress = 0;

foreach ($tasks as $task) {
    if ($task['status'] == 'completed') {
        $completed_tasks++;
    }
    $total_progress += $task['progress_percentage'];
}

$overall_progress = $total_tasks > 0 ? ($total_progress / $total_tasks) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Proyek - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/project-tasks.css" rel="stylesheet">
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Project Header -->
            <div class="project-header mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="<?php echo $_SESSION['user_type'] === 'umkm' ? 'dashboard-umkm.php' : 'dashboard-creative.php'; ?>">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="projects.php">Proyek</a></li>
                                <li class="breadcrumb-item active">Progress Proyek</li>
                            </ol>
                        </nav>
                        <h1 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h1>
                        <p class="project-description text-muted"><?php echo htmlspecialchars($project['description']); ?></p>
                        
                        <div class="project-meta">
                            <span class="badge bg-primary me-2"><?php echo $project['category']; ?></span>
                            <span class="text-muted">
                                <i class="fas fa-user me-1"></i>
                                <?php echo $_SESSION['user_type'] === 'umkm' ? 'Creative Worker: ' . $contract['creative_user_id'] : 'UMKM: ' . htmlspecialchars($project['business_name'] ?? $project['umkm_name']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="progress-card">
                            <h5>Progress Keseluruhan</h5>
                            <div class="progress overall-progress">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $overall_progress; ?>%" 
                                     aria-valuenow="<?php echo $overall_progress; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($overall_progress); ?>%
                                </div>
                            </div>
                            <small class="text-muted"><?php echo $completed_tasks; ?> dari <?php echo $total_tasks; ?> tugas selesai</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tasks Section -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="tasks-section">
                        <div class="section-header d-flex justify-content-between align-items-center mb-4">
                            <h3>Detail Tugas</h3>
                            <?php if ($_SESSION['user_type'] === 'creative' && $contract): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#finalSubmissionModal">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Final Project
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="tasks-list">
                            <?php if (empty($tasks)): ?>
                                <div class="empty-state text-center py-5">
                                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                    <h5>Belum ada tugas</h5>
                                    <p class="text-muted">UMKM belum menambahkan detail tugas untuk proyek ini.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasks as $task): ?>
                                    <div class="task-card card mb-3">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <div class="task-header d-flex align-items-center mb-2">
                                                        <span class="task-order badge bg-secondary me-3"><?php echo $task['task_order']; ?></span>
                                                        <h5 class="task-title mb-0"><?php echo htmlspecialchars($task['task_name']); ?></h5>
                                                    </div>
                                                    <p class="task-description text-muted mb-2"><?php echo htmlspecialchars($task['task_description']); ?></p>
                                                    
                                                    <?php if (!empty($task['attachment_url'])): ?>
                                                        <div class="task-attachment mb-2">
                                                            <small class="text-success">
                                                                <i class="fas fa-paperclip me-1"></i>
                                                                File terlampir: 
                                                                <a href="<?php echo $task['attachment_url']; ?>" target="_blank" class="text-decoration-none">
                                                                    Lihat File
                                                                </a>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($task['feedback'])): ?>
                                                        <div class="task-feedback alert alert-warning py-2">
                                                            <small>
                                                                <strong><i class="fas fa-exclamation-circle me-1"></i>Feedback UMKM:</strong>
                                                                <?php echo htmlspecialchars($task['feedback']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <div class="task-progress text-end">
                                                        <div class="progress mb-2">
                                                            <div class="progress-bar" role="progressbar" 
                                                                 style="width: <?php echo $task['progress_percentage']; ?>%" 
                                                                 aria-valuenow="<?php echo $task['progress_percentage']; ?>" 
                                                                 aria-valuemin="0" aria-valuemax="100">
                                                                <?php echo $task['progress_percentage']; ?>%
                                                            </div>
                                                        </div>
                                                        
                                                        <span class="badge task-status status-<?php echo $task['status']; ?>">
                                                            <?php 
                                                            $statusText = [
                                                                'pending' => 'Menunggu',
                                                                'in_progress' => 'Dalam Pengerjaan',
                                                                'completed' => 'Selesai',
                                                                'revision' => 'Revisi'
                                                            ];
                                                            echo $statusText[$task['status']];
                                                            ?>
                                                        </span>
                                                        
                                                        <?php if ($_SESSION['user_type'] === 'creative'): ?>
                                                            <div class="task-actions mt-2">
                                                                <button class="btn btn-sm btn-outline-primary update-task-btn" 
                                                                        data-task-id="<?php echo $task['id']; ?>"
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#updateTaskModal">
                                                                    <i class="fas fa-edit me-1"></i>Update
                                                                </button>
                                                            </div>
                                                        <?php elseif ($_SESSION['user_type'] === 'umkm' && $task['status'] === 'completed'): ?>
                                                            <div class="task-actions mt-2">
                                                                <button class="btn btn-sm btn-outline-warning request-revision-btn" 
                                                                        data-task-id="<?php echo $task['id']; ?>"
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#revisionModal">
                                                                    <i class="fas fa-redo me-1"></i>Minta Revisi
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="project-sidebar">
                        <!-- Timeline -->
                        <div class="sidebar-card card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Timeline</h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline-item">
                                    <small class="text-muted">Mulai Proyek</small>
                                    <div class="fw-bold"><?php echo date('d M Y', strtotime($contract['start_date'] ?? $project['created_at'])); ?></div>
                                </div>
                                <div class="timeline-item mt-2">
                                    <small class="text-muted">Deadline</small>
                                    <div class="fw-bold"><?php echo date('d M Y', strtotime($contract['end_date'] ?? $project['deadline'])); ?></div>
                                </div>
                                <div class="timeline-item mt-2">
                                    <small class="text-muted">Sisa Waktu</small>
                                    <div class="fw-bold text-primary">
                                        <?php
                                        $deadline = new DateTime($contract['end_date'] ?? $project['deadline']);
                                        $today = new DateTime();
                                        $interval = $today->diff($deadline);
                                        echo $interval->format('%a hari');
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Log -->
                        <div class="sidebar-card card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Aktivitas Terbaru</h5>
                            </div>
                            <div class="card-body">
                                <div class="activity-list">
                                    <?php
                                    // Get recent activities
                                    $activity_query = "SELECT * FROM project_activities 
                                                     WHERE project_id = :project_id 
                                                     ORDER BY created_at DESC 
                                                     LIMIT 5";
                                    $stmt = $db->prepare($activity_query);
                                    $stmt->bindParam(':project_id', $project_id);
                                    $stmt->execute();
                                    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (empty($activities)): ?>
                                        <div class="text-center text-muted py-3">
                                            <small>Belum ada aktivitas</small>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($activities as $activity): ?>
                                            <div class="activity-item mb-3">
                                                <div class="activity-text small">
                                                    <?php echo htmlspecialchars($activity['activity_text']); ?>
                                                </div>
                                                <div class="activity-time text-muted">
                                                    <small><?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <?php if ($_SESSION['user_type'] === 'creative'): ?>
        <!-- Update Task Modal -->
        <div class="modal fade" id="updateTaskModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Progress Tugas</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="updateTaskForm" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="task_id" id="updateTaskId">
                            <div class="mb-3">
                                <label class="form-label">Progress (%)</label>
                                <input type="range" class="form-range" name="progress" id="progressSlider" min="0" max="100" step="10">
                                <div class="text-center">
                                    <span class="progress-value h5" id="progressValue">0%</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Upload File (Opsional)</label>
                                <input type="file" class="form-control" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.zip,.psd,.ai">
                                <small class="text-muted">Format: JPG, PNG, PDF, ZIP, PSD, AI (Max: 10MB)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Keterangan</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Tambahkan catatan tentang progress yang sudah dibuat..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Progress</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Final Submission Modal -->
        <div class="modal fade" id="finalSubmissionModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Submit Final Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="finalSubmissionForm" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Pastikan semua tugas sudah diselesaikan sebelum submit final project.
                            </div>
                            <div class="mb-3">
                                <label class="form-label">File Final Project</label>
                                <input type="file" class="form-control" name="final_file" required accept=".jpg,.jpeg,.png,.pdf,.zip,.psd,.ai">
                                <small class="text-muted">Upload semua file final project dalam format ZIP atau file individual</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catatan Final</label>
                                <textarea class="form-control" name="final_notes" rows="4" placeholder="Tambahkan catatan tentang project final..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success">Submit Final Project</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($_SESSION['user_type'] === 'umkm'): ?>
        <!-- Revision Modal -->
        <div class="modal fade" id="revisionModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Minta Revisi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="revisionForm">
                        <div class="modal-body">
                            <input type="hidden" name="task_id" id="revisionTaskId">
                            <div class="mb-3">
                                <label class="form-label">Detail Revisi</label>
                                <textarea class="form-control" name="feedback" rows="4" required placeholder="Jelaskan secara detail bagian mana yang perlu direvisi..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Prioritas</label>
                                <select class="form-select" name="priority">
                                    <option value="low">Rendah</option>
                                    <option value="medium">Sedang</option>
                                    <option value="high">Tinggi</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning">Minta Revisi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/project-tasks.js"></script>
</body>
</html>