<?php
session_start();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once 'includes/config/database-chart.php';
require_once __DIR__ . '/config/Database.php';

try {
    $database = new DatabaseConnection();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Koneksi database gagal");
    }
} catch (Exception $e) {
    die("Error koneksi database: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_user'])) {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'deactivate') {
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0, deactivated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET is_active = 1, deactivated_at = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
            }
            
            $_SESSION['success_message'] = "User berhasil " . ($action === 'activate' ? 'diaktifkan' : 'dinonaktifkan');
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Gagal mengubah status user: " . $e->getMessage();
        }
        header('Location: kelolauser.php');
        exit;
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_user_detail' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   cp.experience_level, cp.bio as creative_bio,
                   up.business_type, up.business_description
            FROM users u
            LEFT JOIN creative_profiles cp ON u.id = cp.user_id
            LEFT JOIN umkm_profiles up ON u.id = up.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo '<div class="alert alert-danger">User tidak ditemukan</div>';
            exit;
        }
        

        $skills = [];
        if ($user['user_type'] === 'creative') {
            $skill_tables = ['creative_skills', 'skills', 'user_skills'];
            foreach ($skill_tables as $table) {
                try {
                    $skillStmt = $pdo->prepare("
                        SELECT skill_name, category 
                        FROM $table 
                        WHERE user_id = ?
                    ");
                    $skillStmt->execute([$user_id]);
                    $skills = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($skills)) break;
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // Get user projects - Mencari di semua tabel yang berkaitan dengan user
        $projects = [];

        // 1. Cari proyek sebagai owner/creator/client di tabel projects
        $project_columns = ['owner_id', 'creator_id', 'client_id'];
        foreach ($project_columns as $column) {
            try {
                $projectStmt = $pdo->prepare("
                    SELECT p.* 
                    FROM projects p
                    WHERE p.$column = ?
                    ORDER BY p.created_at DESC 
                    LIMIT 5
                ");
                $projectStmt->execute([$user_id]);
                $found = $projectStmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($found)) {
                    $projects = array_merge($projects, $found);
                }
            } catch (Exception $e) {
                continue;
            }
        }

        // 2. Cari proyek yang diapply di tabel project_applications
        try {
            $applyStmt = $pdo->prepare("
                SELECT p.*, pa.status as application_status
                FROM project_applications pa
                JOIN projects p ON pa.project_id = p.id
                WHERE pa.user_id = ?
                ORDER BY pa.applied_at DESC
                LIMIT 5
            ");
            $applyStmt->execute([$user_id]);
            $applied = $applyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tambahkan marker bahwa ini adalah proyek yang diapply
            foreach ($applied as &$app) {
                $app['is_applied'] = true;
            }
            
            $projects = array_merge($projects, $applied);
        } catch (Exception $e) {
            // Table mungkin tidak ada, skip saja
        }

        // 3. Cari proyek yang dikerjakan di tabel project_members
        try {
            $memberStmt = $pdo->prepare("
                SELECT p.*, pm.role as member_role
                FROM project_members pm
                JOIN projects p ON pm.project_id = p.id
                WHERE pm.user_id = ?
                ORDER BY pm.joined_at DESC
                LIMIT 5
            ");
            $memberStmt->execute([$user_id]);
            $memberOf = $memberStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tambahkan marker bahwa ini adalah proyek sebagai member
            foreach ($memberOf as &$member) {
                $member['is_member'] = true;
            }
            
            $projects = array_merge($projects, $memberOf);
        } catch (Exception $e) {
            // Table mungkin tidak ada, skip saja
        }

        // Batasi hanya 5 proyek dan hilangkan duplikat
        $unique_projects = [];
        $project_ids = [];

        foreach ($projects as $project) {
            if (!in_array($project['id'], $project_ids) && count($unique_projects) < 5) {
                $project_ids[] = $project['id'];
                $unique_projects[] = $project;
            }
        }

        $projects = $unique_projects;
        
        ?>
        <div class="row">
            <div class="col-md-4 text-center">
                <img src="<?= htmlspecialchars($user['avatar_url'] ?: 'https://via.placeholder.com/100') ?>" 
                     class="user-detail-avatar" 
                     alt="Avatar"
                     onerror="this.src='https://via.placeholder.com/100'">
                
                <h4 class="mt-3"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'No Name') ?></h4>
                <p class="text-muted"><?= htmlspecialchars($user['email'] ?? 'No Email') ?></p>
                
                <div class="d-flex justify-content-center gap-2 mb-3">
                    <span class="badge <?= ($user['user_type'] ?? '') === 'creative' ? 'badge-creative' : 'badge-umkm' ?> status-badge">
                        <?= ($user['user_type'] ?? '') === 'creative' ? 'Creative Worker' : 'UMKM' ?>
                    </span>
                    <span class="badge <?= ($user['is_active'] ?? 0) ? 'bg-success' : 'bg-danger' ?> status-badge">
                        <?= ($user['is_active'] ?? 0) ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h6>Informasi Akun</h6>
                        <p class="mb-1"><small>Bergabung: <?= date('d M Y', strtotime($user['created_at'] ?? 'now')) ?></small></p>
                        <p class="mb-0"><small>Username: <?= htmlspecialchars($user['username'] ?? 'No Username') ?></small></p>
                    </div>
                </div>

                <!-- Tambah informasi status akun -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6>Status Akun</h6>
                        <?php if ($user['is_active']): ?>
                            <p class="text-success mb-1"><i class="fas fa-check-circle me-2"></i>Akun Aktif</p>
                            <p class="text-muted small mb-0">User dapat login ke sistem</p>
                        <?php else: ?>
                            <p class="text-danger mb-1"><i class="fas fa-ban me-2"></i>Akun Dinonaktifkan</p>
                            <p class="text-muted small mb-0">User tidak dapat login ke sistem</p>
                            <?php if (!empty($user['deactivated_at'])): ?>
                                <p class="text-muted small mb-0">Dinonaktifkan pada: <?= date('d M Y H:i', strtotime($user['deactivated_at'])) ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <?php if (($user['user_type'] ?? '') === 'creative'): ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6>Profile Creative</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Level Pengalaman:</strong> 
                                <span class="badge bg-info"><?= ucfirst($user['experience_level'] ?? 'Not set') ?></span>
                            </p>
                            <p><strong>Bio:</strong> <?= htmlspecialchars($user['creative_bio'] ?? 'Tidak ada bio') ?></p>
                            
                            <?php if (!empty($skills)): ?>
                                <h6>Skills:</h6>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($skills as $skill): ?>
                                        <span class="skill-badge"><?= htmlspecialchars($skill['skill_name'] ?? 'Unknown Skill') ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Tidak ada skills</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6>Profile UMKM</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Jenis Bisnis:</strong> 
                                <span class="badge bg-info"><?= ucfirst($user['business_type'] ?? 'Not set') ?></span>
                            </p>
                            <p><strong>Deskripsi Bisnis:</strong> <?= htmlspecialchars($user['business_description'] ?? 'Tidak ada deskripsi') ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Bagian Proyek Terkait (Updated) -->
                <div class="card">
                    <div class="card-header">
                        <h6>Proyek Terkait</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($projects)): ?>
                            <?php foreach ($projects as $project): ?>
                                <div class="project-card">
                                    <h6 class="mb-1"><?= htmlspecialchars($project['title'] ?? 'No Title') ?></h6>
                                    
                                    <!-- Tampilkan peran user dalam proyek -->
                                    <?php if (isset($project['is_applied'])): ?>
                                        <span class="badge bg-info badge-sm">Diapply</span>
                                    <?php elseif (isset($project['is_member'])): ?>
                                        <span class="badge bg-primary badge-sm"><?= htmlspecialchars($project['member_role'] ?? 'Member') ?></span>
                                    <?php elseif ($project['owner_id'] == $user_id): ?>
                                        <span class="badge bg-success badge-sm">Pemilik</span>
                                    <?php elseif ($project['creator_id'] == $user_id): ?>
                                        <span class="badge bg-warning badge-sm">Creator</span>
                                    <?php elseif ($project['client_id'] == $user_id): ?>
                                        <span class="badge bg-secondary badge-sm">Klien</span>
                                    <?php endif; ?>
                                    
                                    <p class="mb-1 text-muted small mt-2">Status Proyek: 
                                        <span class="badge <?= ($project['status'] ?? '') === 'completed' ? 'bg-success' : 
                                                            (($project['status'] ?? '') === 'in_progress' ? 'bg-warning' : 
                                                            (($project['status'] ?? '') === 'pending' ? 'bg-info' : 'bg-secondary')) ?>">
                                            <?= ucfirst(str_replace('_', ' ', $project['status'] ?? 'unknown')) ?>
                                        </span>
                                    </p>
                                    
                                    <?php if (isset($project['application_status'])): ?>
                                        <p class="mb-1 text-muted small">Status Apply: 
                                            <span class="badge <?= ($project['application_status'] ?? '') === 'accepted' ? 'bg-success' : 
                                                                (($project['application_status'] ?? '') === 'rejected' ? 'bg-danger' : 'bg-warning') ?>">
                                                <?= ucfirst($project['application_status'] ?? 'pending') ?>
                                            </span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <p class="mb-0 text-muted small">Dibuat: <?= date('d M Y', strtotime($project['created_at'] ?? 'now')) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Belum terlibat dalam proyek</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        exit;
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        exit;
    }
}

// Get filter parameters for main page
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build query
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

// Exclude current admin user
$sql .= " AND id != ?";
$params[] = $_SESSION['user_id'];

if (!empty($type_filter)) {
    $sql .= " AND user_type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $sql .= " AND is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $sql .= " AND is_active = 0";
    }
}

if (!empty($search)) {
    $sql .= " AND (";
    $sql .= "full_name LIKE ? OR ";
    $sql .= "email LIKE ? OR ";
    $sql .= "username LIKE ?";
    $sql .= ")";
    
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
    $error_message = "Gagal memuat data user: " . $e->getMessage();
}

// Count users by type
$count_sql = "SELECT user_type, COUNT(*) as count, 
                     SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
              FROM users 
              WHERE id != ? 
              GROUP BY user_type";
              
try {
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([$_SESSION['user_id']]);
    $user_counts = $count_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user_counts = [];
}

$total_users = 0;
$total_creative = 0;
$total_umkm = 0;
$total_active = 0;

foreach ($user_counts as $count) {
    $total_users += $count['count'];
    $total_active += $count['active_count'];
    if ($count['user_type'] === 'creative') {
        $total_creative = $count['count'];
    } elseif ($count['user_type'] === 'umkm') {
        $total_umkm = $count['count'];
    }
}

// Jika tidak ada user counts, hitung manual dari $users
if ($total_users === 0 && !empty($users)) {
    $total_users = count($users);
    $total_active = count(array_filter($users, function($user) {
        return ($user['is_active'] ?? 0) == 1;
    }));
    $total_creative = count(array_filter($users, function($user) {
        return ($user['user_type'] ?? '') === 'creative';
    }));
    $total_umkm = count(array_filter($users, function($user) {
        return ($user['user_type'] ?? '') === 'umkm';
    }));
}

// Pisahkan user berdasarkan tipe untuk pengelompokan
$creative_users = array_filter($users, function($user) {
    return ($user['user_type'] ?? '') === 'creative';
});

$umkm_users = array_filter($users, function($user) {
    return ($user['user_type'] ?? '') === 'umkm';
});
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        /* Navbar Styles */
        .konekin-navbar {
            background: linear-gradient(135deg, #3E7FD5 0%, #3E7FD5 100%);
            padding: 0.8rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand-konekin {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.5rem;
            margin-right: 3rem;
        }

        .navbar-brand-konekin i {
            margin-right: 10px;
            font-size: 1.8rem;
        }

        .navbar-nav-konekin {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-item-konekin {
            position: relative;
        }

        .nav-link-konekin {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.7rem 1.2rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .nav-link-konekin:hover,
        .nav-link-konekin.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .nav-link-konekin i {
            font-size: 1.1rem;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: 2rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            color: white;
        }

        .user-name {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-type {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            margin-left: 1rem;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .navbar-toggler-custom {
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
        }

        /* Mobile Styles */
        @media (max-width: 991.98px) {
            .konekin-navbar {
                padding: 0.8rem 1rem;
            }
            .navbar-brand-konekin {
                margin-right: 1rem;
            }
            .navbar-collapse-konekin {
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 1rem;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                z-index: 999;
            }

            .navbar-nav-konekin {
                flex-direction: column;
                gap: 0.5rem;
            }

            .nav-link-konekin {
                padding: 1rem;
                border-radius: 10px;
                justify-content: flex-start;
            }

            .user-section {
                margin-left: 0;
                margin-top: 1rem;
                justify-content: center;
                flex-direction: column;
                text-align: center;
            }

            .logout-btn {
                margin-left: 0;
                margin-top: 0.5rem;
                width: 100%;
            }
        }

        /* Badge untuk notifikasi */
        .nav-badge {
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -5px;
            right: -5px;
        }

        /* Custom Styles untuk konten */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .user-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .user-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .user-avatar-img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
        }
        
        .badge-creative {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .badge-umkm {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 20px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .form-control {
            padding-left: 40px;
            border-radius: 25px;
            border: 2px solid #e9ecef;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .filter-select {
            border-radius: 25px;
            border: 2px solid #e9ecef;
        }
        
        .action-btn {
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .section-title {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .section-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        /* Custom Modal */
        .custom-modal .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .custom-modal .modal-content {
            border-radius: 12px;
            border: none;
        }

        .user-detail-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
            margin: 0 auto 20px;
            display: block;
        }

        .skill-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin: 2px;
            display: inline-block;
        }
        
        .project-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid #667eea;
        }

        .confirm-modal .modal-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Tambahan untuk badge kecil */
        .badge-sm {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar-admin.php'; ?>

    <div class="container-fluid py-4">
        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold">Kelola Pengguna</h2>
                <p class="text-muted">Kelola semua user yang terdaftar di sistem</p>
            </div>
        </div>

        <!-- Statistik -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_users ?></div>
                    <div class="stat-label">Total Pengguna</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_active ?></div>
                    <div class="stat-label">Aktif</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_creative ?></div>
                    <div class="stat-label">Creative Worker</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_umkm ?></div>
                    <div class="stat-label">UMKM</div>
                </div>
            </div>
        </div>

        <!-- Filter dan Search -->
        <div class="card p-4 mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="search-box">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Cari user berdasarkan nama, email, atau username..." 
                               value="<?= htmlspecialchars($search) ?>"
                               id="searchInput">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-control filter-select">
                        <option value="">Semua Tipe</option>
                        <option value="creative" <?= $type_filter === 'creative' ? 'selected' : '' ?>>Creative Worker</option>
                        <option value="umkm" <?= $type_filter === 'umkm' ? 'selected' : '' ?>>UMKM</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control filter-select">
                        <option value="">Semua Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100" style="border-radius: 25px;">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar User dengan Pengelompokan -->
        <?php if (empty($users)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h5 class="text-muted">Tidak ada user ditemukan</h5>
                    <?php if (!empty($search) || !empty($type_filter) || !empty($status_filter)): ?>
                        <p class="text-muted">Coba ubah filter pencarian Anda</p>
                        <a href="kelolauser.php" class="btn btn-primary mt-2">Reset Filter</a>
                    <?php else: ?>
                        <p class="text-muted">Belum ada user yang terdaftar di sistem</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Section Creative Worker -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="section-header d-flex justify-content-between align-items-center">
                        <h4 class="section-title">
                            <i class="fas fa-palette me-2"></i>
                            Creative Worker
                        </h4>
                        <span class="section-count"><?= count($creative_users) ?> User</span>
                    </div>
                    
                    <?php if (!empty($creative_users)): ?>
                        <div class="row">
                            <?php foreach ($creative_users as $user): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="user-card p-4">
                                        <div class="d-flex align-items-start mb-3">
                                            <img src="<?= htmlspecialchars($user['avatar_url'] ?: 'https://via.placeholder.com/60') ?>" 
                                                 class="user-avatar-img me-3" 
                                                 alt="Avatar"
                                                 onerror="this.src='https://via.placeholder.com/60'">
                                            <div class="flex-grow-1">
                                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'No Name') ?></h6>
                                                <p class="text-muted mb-1 small"><?= htmlspecialchars($user['email'] ?? 'No Email') ?></p>
                                                <div class="d-flex gap-2">
                                                    <span class="badge badge-creative status-badge">
                                                        Creative Worker
                                                    </span>
                                                    <span class="badge <?= ($user['is_active'] ?? 0) ? 'bg-success' : 'bg-danger' ?> status-badge">
                                                        <?= ($user['is_active'] ?? 0) ? 'Aktif' : 'Nonaktif' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                Bergabung: <?= date('d M Y', strtotime($user['created_at'] ?? 'now')) ?>
                                            </small>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary action-btn view-user" 
                                                        data-user-id="<?= $user['id'] ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#userDetailModal">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($user['is_active'] ?? 0): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning action-btn toggle-user-btn"
                                                            data-user-id="<?= $user['id'] ?>"
                                                            data-user-name="<?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Unknown User') ?>"
                                                            data-action="deactivate">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success action-btn toggle-user-btn"
                                                            data-user-id="<?= $user['id'] ?>"
                                                            data-user-name="<?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Unknown User') ?>"
                                                            data-action="activate">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="empty-state">
                                <i class="fas fa-palette"></i>
                                <h5 class="text-muted">Tidak ada Creative Worker</h5>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                    <p class="text-muted">Coba ubah filter pencarian Anda</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section UMKM -->
            <div class="row">
                <div class="col-12">
                    <div class="section-header d-flex justify-content-between align-items-center">
                        <h4 class="section-title">
                            <i class="fas fa-store me-2"></i>
                            UMKM
                        </h4>
                        <span class="section-count"><?= count($umkm_users) ?> User</span>
                    </div>
                    
                    <?php if (!empty($umkm_users)): ?>
                        <div class="row">
                            <?php foreach ($umkm_users as $user): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="user-card p-4">
                                        <div class="d-flex align-items-start mb-3">
                                            <img src="<?= htmlspecialchars($user['avatar_url'] ?: 'https://via.placeholder.com/60') ?>" 
                                                 class="user-avatar-img me-3" 
                                                 alt="Avatar"
                                                 onerror="this.src='https://via.placeholder.com/60'">
                                            <div class="flex-grow-1">
                                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'No Name') ?></h6>
                                                <p class="text-muted mb-1 small"><?= htmlspecialchars($user['email'] ?? 'No Email') ?></p>
                                                <div class="d-flex gap-2">
                                                    <span class="badge badge-umkm status-badge">
                                                        UMKM
                                                    </span>
                                                    <span class="badge <?= ($user['is_active'] ?? 0) ? 'bg-success' : 'bg-danger' ?> status-badge">
                                                        <?= ($user['is_active'] ?? 0) ? 'Aktif' : 'Nonaktif' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                Bergabung: <?= date('d M Y', strtotime($user['created_at'] ?? 'now')) ?>
                                            </small>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary action-btn view-user" 
                                                        data-user-id="<?= $user['id'] ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#userDetailModal">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($user['is_active'] ?? 0): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning action-btn toggle-user-btn"
                                                            data-user-id="<?= $user['id'] ?>"
                                                            data-user-name="<?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Unknown User') ?>"
                                                            data-action="deactivate">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success action-btn toggle-user-btn"
                                                            data-user-id="<?= $user['id'] ?>"
                                                            data-user-name="<?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Unknown User') ?>"
                                                            data-action="activate">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="empty-state">
                                <i class="fas fa-store"></i>
                                <h5 class="text-muted">Tidak ada UMKM</h5>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                    <p class="text-muted">Coba ubah filter pencarian Anda</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Detail User -->
    <div class="modal fade custom-modal" id="userDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailContent">
                    <div class="text-center py-4">
                        <p class="text-muted">Pilih user untuk melihat detail</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Toggle User -->
    <div class="modal fade confirm-modal" id="confirmToggleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" id="toggleUserForm">
                        <input type="hidden" name="user_id" id="confirmUserId">
                        <input type="hidden" name="action" id="confirmAction">
                        <button type="submit" name="toggle_user" class="btn btn-primary">Ya, Lanjutkan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Load user detail via AJAX
    document.querySelectorAll('.view-user').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const modalContent = document.getElementById('userDetailContent');
            
            modalContent.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Memuat data user...</p>
                </div>
            `;
            
            // Load user detail from the same file using AJAX
            fetch(`kelolauser.php?ajax=get_user_detail&id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    modalContent.innerHTML = data;
                })
                .catch(error => {
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Gagal memuat data user: ${error.message}
                        </div>
                    `;
                });
        });
    });

    // Handle toggle user confirmation
    document.querySelectorAll('.toggle-user-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;
            const action = this.dataset.action;
            
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmToggleModal'));
            const confirmMessage = document.getElementById('confirmMessage');
            const confirmUserId = document.getElementById('confirmUserId');
            const confirmAction = document.getElementById('confirmAction');
            
            if (action === 'deactivate') {
                confirmMessage.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Konfirmasi Nonaktifkan User</strong><br>
                        Apakah Anda yakin ingin menonaktifkan user <strong>"${userName}"</strong>?
                        <br><br>
                        <small class="text-muted">User yang dinonaktifkan tidak dapat login ke sistem.</small>
                    </div>
                `;
            } else {
                confirmMessage.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Konfirmasi Aktifkan User</strong><br>
                        Apakah Anda yakin ingin mengaktifkan user <strong>"${userName}"</strong>?
                        <br><br>
                        <small class="text-muted">User yang diaktifkan dapat login kembali ke sistem.</small>
                    </div>
                `;
            }
            
            confirmUserId.value = userId;
            confirmAction.value = action;
            
            confirmModal.show();
        });
    });

    // Clear modal content when closed
    document.getElementById('userDetailModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('userDetailContent').innerHTML = `
            <div class="text-center py-4">
                <p class="text-muted">Pilih user untuk melihat detail</p>
            </div>
        `;
    });

    // Auto submit search on enter key
    document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            this.form.submit();
        }
    });
    </script>
</body>
</html>