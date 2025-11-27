<?php
include "check_login.php";

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

$project_id = $_GET['id'] ?? 0;

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$project_sql = "SELECT * FROM projects WHERE id = :id AND umkm_user_id = :user_id";
$project_stmt = $conn->prepare($project_sql);
$project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
$project = $project_stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: projects.php");
    exit;
}

$proposals_sql = "
    SELECT 
        pr.*,
        u.full_name as creative_name,
        u.email as creative_email,
        cp.tagline as creative_tagline,
        cp.rating as creative_rating,
        cp.completed_projects as creative_completed_projects
    FROM proposals pr
    JOIN users u ON pr.creative_user_id = u.id
    LEFT JOIN creative_profiles cp ON u.id = cp.user_id
    WHERE pr.project_id = :project_id
    ORDER BY pr.submitted_at DESC
";

$proposals_stmt = $conn->prepare($proposals_sql);
$proposals_stmt->execute([':project_id' => $project_id]);
$proposals = $proposals_stmt->fetchAll(PDO::FETCH_ASSOC);

// Update status proposal menjadi 'viewed' (jika ada proposal baru)
if (count($proposals) > 0) {
    $update_sql = "UPDATE proposals SET status = 'viewed' WHERE project_id = :project_id AND status = 'submitted'";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([':project_id' => $project_id]);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Proyek - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .proposal-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .proposal-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .creative-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #2596be;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        .rating-stars {
            color: #FFC300;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-file-alt me-2"></i>Proposal untuk: <?php echo htmlspecialchars($project['title']); ?></h2>
                    <p class="text-muted"><?php echo count($proposals); ?> proposal diterima</p>
                </div>
                <a href="projects.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i>Kembali ke Proyek
                </a>
            </div>

            <?php if (count($proposals) > 0): ?>
                <?php foreach ($proposals as $proposal): ?>
                    <div class="card proposal-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2 text-center">
                                    <div class="creative-avatar mx-auto mb-3">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <h6><?php echo htmlspecialchars($proposal['creative_name']); ?></h6>
                                    <?php if ($proposal['creative_rating'] > 0): ?>
                                        <div class="rating-stars">
                                            <?php 
                                            $rating = round($proposal['creative_rating']);
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <i class="fas fa-star<?php echo $i <= $rating ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                            <small class="text-muted">(<?php echo $proposal['creative_rating']; ?>)</small>
                                        </div>
                                    <?php else: ?>
                                        <small class="text-muted">Belum ada rating</small>
                                    <?php endif; ?>
                                    <small class="text-muted"><?php echo $proposal['creative_completed_projects']; ?> proyek selesai</small>
                                </div>
                                <div class="col-md-8">
                                    <h5>Cover Letter</h5>
                                    <p><?php echo nl2br(htmlspecialchars($proposal['cover_letter'])); ?></p>
                                    
                                    <?php if ($proposal['proposed_budget']): ?>
                                        <div class="mb-2">
                                            <strong>Budget yang Diajukan:</strong> 
                                            Rp <?php echo number_format($proposal['proposed_budget'], 0, ',', '.'); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($proposal['timeline_days']): ?>
                                        <div class="mb-2">
                                            <strong>Estimasi Waktu:</strong> 
                                            <?php echo $proposal['timeline_days']; ?> hari
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($proposal['timeline_description']): ?>
                                        <div class="mb-2">
                                            <strong>Rencana Pengerjaan:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($proposal['timeline_description'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#acceptModal<?php echo $proposal['id']; ?>">
                                            <i class="fas fa-check me-1"></i>Terima
                                        </button>
                                        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $proposal['id']; ?>">
                                            <i class="fas fa-times me-1"></i>Tolak
                                        </button>
                                        <a href="view-portfolio.php?creative_id=<?php echo $proposal['creative_user_id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>Portfolio
                                        </a>
                                        <a href="messages.php?user_id=<?php echo $proposal['creative_user_id']; ?>&project_id=<?php echo $project_id; ?>" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-envelope me-1"></i>Chat
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            Diajukan pada: <?php echo date('d M Y H:i', strtotime($proposal['submitted_at'])); ?>
                        </div>
                    </div>

                    <div class="modal fade" id="acceptModal<?php echo $proposal['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Terima Proposal</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Apakah Anda yakin ingin menerima proposal dari <strong><?php echo htmlspecialchars($proposal['creative_name']); ?></strong>?</p>
                                    <p>Proposal ini akan dipilih untuk mengerjakan proyek "<?php echo htmlspecialchars($project['title']); ?>".</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <a href="accept-proposal.php?proposal_id=<?php echo $proposal['id']; ?>" class="btn btn-success">Ya, Terima Proposal</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reject Modal -->
                    <div class="modal fade" id="rejectModal<?php echo $proposal['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Tolak Proposal</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Apakah Anda yakin ingin menolak proposal dari <strong><?php echo htmlspecialchars($proposal['creative_name']); ?></strong>?</p>
                                    <div class="mb-3">
                                        <label for="rejectReason<?php echo $proposal['id']; ?>" class="form-label">Alasan penolakan (opsional):</label>
                                        <textarea class="form-control" id="rejectReason<?php echo $proposal['id']; ?>" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="button" class="btn btn-danger" onclick="rejectProposal(<?php echo $proposal['id']; ?>)">Ya, Tolak Proposal</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                    <h4>Belum Ada Proposal</h4>
                    <p class="text-muted">Proyek Anda belum menerima proposal dari creative worker.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function rejectProposal(proposalId) {
            const reason = document.getElementById('rejectReason' + proposalId).value;
            window.location.href = 'reject-proposal.php?proposal_id=' + proposalId + '&reason=' + encodeURIComponent(reason);
        }
    </script>
</body>
</html>