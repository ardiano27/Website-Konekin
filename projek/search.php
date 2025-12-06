<?php
// search.php
session_start();
include "check_login.php";
require_once 'config/Database.php';

$database = new DatabaseConnection();
$conn = $database->getConnection();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$results = [];
$result_count = 0;

if (!empty($query)) {
    try {
        // Simpan riwayat pencarian
        if (!isset($_SESSION['search_history'])) {
            $_SESSION['search_history'] = [];
        }
        
        // Tambahkan ke riwayat (maksimal 10)
        $search_entry = [
            'query' => $query,
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $search_type
        ];
        
        // Hapus duplikat
        $_SESSION['search_history'] = array_filter($_SESSION['search_history'], 
            function($item) use ($search_entry) {
                return $item['query'] !== $search_entry['query'];
            }
        );
        
        array_unshift($_SESSION['search_history'], $search_entry);
        $_SESSION['search_history'] = array_slice($_SESSION['search_history'], 0, 10);

        // Search logic berdasarkan user type dan tipe pencarian
        if ($_SESSION['user_type'] === 'creative') {
            // Creative mencari proyek
            $search_sql = "
                SELECT 
                    p.*,
                    u.full_name as umkm_name,
                    u.avatar_url as umkm_avatar,
                    up.business_name,
                    up.business_logo_url,
                    (SELECT COUNT(*) FROM proposals pr WHERE pr.project_id = p.id) as total_proposals,
                    (SELECT COUNT(*) FROM proposals pr2 WHERE pr2.project_id = p.id AND pr2.creative_user_id = :user_id) as already_applied
                FROM projects p
                JOIN users u ON p.umkm_user_id = u.id
                LEFT JOIN umkm_profiles up ON u.id = up.user_id
                WHERE p.status = 'open'
                AND (p.title LIKE :query OR p.description LIKE :query OR p.category LIKE :query)
                ORDER BY p.created_at DESC
                LIMIT 50
            ";
            
            $stmt = $conn->prepare($search_sql);
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':query' => "%$query%"
            ]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result_count = count($results);
            
        } elseif ($_SESSION['user_type'] === 'umkm') {
            // UMKM mencari creative
            $search_sql = "
                SELECT 
                    u.id, u.full_name, u.avatar_url, u.email,
                    cp.tagline, cp.bio, cp.experience_level, 
                    cp.rating, cp.completed_projects,
                    cp.location,
                    GROUP_CONCAT(DISTINCT s.name) as skills
                FROM users u
                JOIN creative_profiles cp ON u.id = cp.user_id
                LEFT JOIN creative_skills cs ON cp.id = cs.creative_profile_id
                LEFT JOIN skills s ON cs.skill_id = s.id
                WHERE u.user_type = 'creative' 
                AND u.is_active = 1
                AND (u.full_name LIKE :query 
                     OR cp.tagline LIKE :query 
                     OR cp.bio LIKE :query
                     OR s.name LIKE :query)
                GROUP BY u.id
                ORDER BY cp.rating DESC, cp.completed_projects DESC
                LIMIT 50
            ";
            
            $stmt = $conn->prepare($search_sql);
            $stmt->execute([':query' => "%$query%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result_count = count($results);
            
        } else {
            // Admin mencari semua
            $search_sql = "
                (SELECT 'project' as type, p.id, p.title as name, p.description, p.created_at
                 FROM projects p
                 WHERE p.title LIKE :query OR p.description LIKE :query
                 LIMIT 20)
                UNION
                (SELECT 'user' as type, u.id, u.full_name as name, u.email as description, u.created_at
                 FROM users u
                 WHERE u.full_name LIKE :query OR u.email LIKE :query
                 LIMIT 20)
                UNION
                (SELECT 'creative' as type, u.id, u.full_name as name, cp.tagline as description, u.created_at
                 FROM users u
                 JOIN creative_profiles cp ON u.id = cp.user_id
                 WHERE u.user_type = 'creative' 
                 AND (u.full_name LIKE :query OR cp.tagline LIKE :query)
                 LIMIT 20)
                ORDER BY created_at DESC
            ";
            
            $stmt = $conn->prepare($search_sql);
            $stmt->execute([':query' => "%$query%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result_count = count($results);
        }
        
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        $results = [];
    }
}

// Ambil rekomendasi berdasarkan user type
$recommendations = [];
try {
    if ($_SESSION['user_type'] === 'creative') {
        $rec_sql = "
            SELECT p.*, u.full_name as umkm_name
            FROM projects p
            JOIN users u ON p.umkm_user_id = u.id
            WHERE p.status = 'open'
            ORDER BY p.created_at DESC
            LIMIT 6
        ";
        $stmt = $conn->prepare($rec_sql);
        $stmt->execute();
        $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($_SESSION['user_type'] === 'umkm') {
        $rec_sql = "
            SELECT u.id, u.full_name, u.avatar_url, cp.tagline, cp.rating
            FROM users u
            JOIN creative_profiles cp ON u.id = cp.user_id
            WHERE u.user_type = 'creative' 
            AND cp.rating >= 4.0
            ORDER BY cp.rating DESC
            LIMIT 6
        ";
        $stmt = $conn->prepare($rec_sql);
        $stmt->execute();
        $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Recommendation error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Pencarian - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2c5ea3;
            --primary-light: rgba(62, 127, 213, 0.1);
            --bg-light: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
        }
        
        body {
            background-color: var(--bg-light);
            font-family: 'Inter', sans-serif;
        }
        
        .search-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .search-box-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-input-main {
            border-radius: 50px;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
            border: 3px solid transparent;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .search-input-main:focus {
            border-color: white;
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.2);
        }
        
        .result-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }
        
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        
        .search-history {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .history-item {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .history-item:hover {
            background: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="search-header">
            <div class="container">
                <div class="search-box-container">
                    <form action="search.php" method="GET" class="mb-4">
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control search-input-main" 
                                   name="q" 
                                   value="<?php echo htmlspecialchars($query); ?>"
                                   placeholder="<?php 
                                        if ($_SESSION['user_type'] === 'creative') {
                                            echo 'Cari proyek... (contoh: desain logo, website, video editing)';
                                        } elseif ($_SESSION['user_type'] === 'umkm') {
                                            echo 'Cari kreator... (contoh: graphic designer, web developer)';
                                        } else {
                                            echo 'Cari...';
                                        }
                                   ?>"
                                   autofocus>
                            <button class="btn btn-light" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    
                    <?php if (!empty($query)): ?>
                        <div class="text-center">
                            <h4 class="mb-2">Hasil Pencarian untuk "<?php echo htmlspecialchars($query); ?>"</h4>
                            <p class="mb-0">Ditemukan <?php echo $result_count; ?> hasil</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="container">
            <?php if (!empty($query)): ?>
                
                <?php if ($result_count > 0): ?>
                    
                    <?php if ($_SESSION['user_type'] === 'creative'): ?>
                        <!-- Tampilan untuk Creative (Proyek) -->
                        <div class="row">
                            <?php foreach ($results as $project): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="result-card">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <span class="badge bg-primary">
                                                <?php echo ucfirst(str_replace('_', ' ', $project['category'] ?? 'other')); ?>
                                            </span>
                                            <span class="badge bg-<?php echo $project['status'] == 'open' ? 'success' : 'secondary'; ?>">
                                                <?php echo $project['status'] == 'open' ? 'Open' : ucfirst($project['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <h5 class="mb-2">
                                            <a href="view-project.php?id=<?php echo $project['id']; ?>" class="text-dark text-decoration-none">
                                                <?php echo htmlspecialchars($project['title']); ?>
                                            </a>
                                        </h5>
                                        
                                        <p class="text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            <?php echo htmlspecialchars(substr($project['description'], 0, 150)); ?>...
                                        </p>
                                        
                                        <div class="d-flex align-items-center mb-3">
                                            <?php if (!empty($project['business_logo_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($project['business_logo_url']); ?>" 
                                                     alt="Logo" 
                                                     class="rounded-circle me-2"
                                                     style="width: 30px; height: 30px;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2"
                                                     style="width: 30px; height: 30px; font-size: 0.8rem;">
                                                    <?php echo strtoupper(substr($project['umkm_name'] ?? 'U', 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <small class="d-block fw-bold"><?php echo htmlspecialchars($project['business_name'] ?? $project['umkm_name']); ?></small>
                                                <small class="text-muted">UMKM</small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <small class="text-muted">Budget:</small>
                                                <div class="fw-bold text-success">
                                                    <?php 
                                                    if ($project['budget_range_min']) {
                                                        echo "Rp " . number_format($project['budget_range_min'], 0, ',', '.');
                                                        if ($project['budget_range_max'] && $project['budget_range_max'] > $project['budget_range_min']) {
                                                            echo " - Rp " . number_format($project['budget_range_max'], 0, ',', '.');
                                                        }
                                                    } else {
                                                        echo "Negotiable";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted">Deadline:</small>
                                                <div class="fw-bold">
                                                    <?php echo !empty($project['deadline']) ? date('d M Y', strtotime($project['deadline'])) : '-'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($project['already_applied'] > 0): ?>
                                            <div class="mt-3">
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i>Sudah Diajukan
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                    <?php elseif ($_SESSION['user_type'] === 'umkm'): ?>
                        <!-- Tampilan untuk UMKM (Creative Workers) -->
                        <div class="row">
                            <?php foreach ($results as $creative): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="result-card">
                                        <div class="d-flex align-items-start mb-3">
                                            <?php if (!empty($creative['avatar_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($creative['avatar_url']); ?>" 
                                                     alt="Avatar" 
                                                     class="rounded-circle me-3"
                                                     style="width: 60px; height: 60px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3"
                                                     style="width: 60px; height: 60px; font-size: 1.5rem;">
                                                    <?php echo strtoupper(substr($creative['full_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="flex-grow-1">
                                                <h5 class="mb-1"><?php echo htmlspecialchars($creative['full_name']); ?></h5>
                                                <p class="text-primary mb-1 fw-bold"><?php echo htmlspecialchars($creative['tagline'] ?? 'Creative Worker'); ?></p>
                                                
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="text-warning">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?php echo $i <= floor($creative['rating']) ? '' : '-empty'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <span class="ms-2">(<?php echo $creative['rating']; ?>)</span>
                                                    <span class="ms-2 text-muted">•</span>
                                                    <span class="ms-2 text-muted"><?php echo $creative['completed_projects']; ?> proyek</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <p class="small text-muted mb-3">
                                            <?php echo htmlspecialchars(substr($creative['bio'] ?? 'Tidak ada deskripsi', 0, 120)); ?>...
                                        </p>
                                        
                                        <?php if (!empty($creative['skills'])): ?>
                                            <div class="mb-3">
                                                <small class="text-muted d-block mb-1">Keahlian:</small>
                                                <div>
                                                    <?php 
                                                    $skills = explode(',', $creative['skills']);
                                                    foreach (array_slice($skills, 0, 4) as $skill): 
                                                    ?>
                                                        <span class="badge bg-light text-dark border me-1 mb-1">
                                                            <?php echo trim($skill); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($skills) > 4): ?>
                                                        <span class="badge bg-light text-dark border">
                                                            +<?php echo count($skills) - 4; ?> lagi
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($creative['experience_level'] ?? 'intermediate'); ?>
                                            </span>
                                            <a href="view-creative-profile.php?id=<?php echo $creative['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                Lihat Profil
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                    <?php else: ?>
                        <!-- Tampilan untuk Admin (Semua) -->
                        <div class="row">
                            <?php foreach ($results as $item): ?>
                                <div class="col-12 mb-3">
                                    <div class="result-card">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-<?php 
                                                    echo $item['type'] == 'project' ? 'warning' : 
                                                           ($item['type'] == 'user' ? 'info' : 'success'); 
                                                ?> me-2">
                                                    <?php echo ucfirst($item['type']); ?>
                                                </span>
                                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                <?php if (!empty($item['description'])): ?>
                                                    <span class="text-muted ms-2"><?php echo htmlspecialchars(substr($item['description'], 0, 100)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <small class="text-muted">
                                                    <?php echo date('d M Y', strtotime($item['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Tidak ada hasil -->
                    <div class="empty-state">
                        <i class="fas fa-search fa-4x mb-3" style="color: #e0e0e0;"></i>
                        <h4 class="mb-2">Tidak ada hasil ditemukan</h4>
                        <p class="text-muted mb-4">Coba dengan kata kunci yang berbeda atau lihat rekomendasi di bawah</p>
                        
                        <!-- Riwayat Pencarian -->
                        <?php if (!empty($_SESSION['search_history'])): ?>
                            <div class="search-history">
                                <h6 class="mb-3">Riwayat Pencarian</h6>
                                <div>
                                    <?php foreach ($_SESSION['search_history'] as $history): ?>
                                        <a href="search.php?q=<?php echo urlencode($history['query']); ?>" 
                                           class="history-item">
                                           <?php echo htmlspecialchars($history['query']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Tampilan awal (belum ada pencarian) -->
                <div class="text-center py-5">
                    <i class="fas fa-search fa-5x mb-4" style="color: #e0e0e0;"></i>
                    <h3 class="mb-3">Mulai Pencarian Anda</h3>
                    <p class="text-muted mb-5">
                        <?php
                        if ($_SESSION['user_type'] === 'creative') {
                            echo 'Cari proyek yang sesuai dengan keahlian Anda';
                        } elseif ($_SESSION['user_type'] === 'umkm') {
                            echo 'Cari kreator terbaik untuk proyek Anda';
                        } else {
                            echo 'Cari proyek, kreator, atau pengguna';
                        }
                        ?>
                    </p>
                    
                    <!-- Rekomendasi -->
                    <?php if (!empty($recommendations)): ?>
                        <h5 class="mb-4"><?php echo $_SESSION['user_type'] === 'creative' ? 'Proyek Terbaru' : 'Kreator Terbaik'; ?></h5>
                        <div class="row">
                            <?php foreach ($recommendations as $rec): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="result-card">
                                        <?php if ($_SESSION['user_type'] === 'creative'): ?>
                                            <h6><?php echo htmlspecialchars($rec['title']); ?></h6>
                                            <small class="text-muted d-block mb-2">
                                                Oleh: <?php echo htmlspecialchars($rec['umkm_name']); ?>
                                            </small>
                                            <a href="view-project.php?id=<?php echo $rec['id']; ?>" 
                                               class="btn btn-sm btn-primary">Lihat Detail</a>
                                        <?php else: ?>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($rec['avatar_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($rec['avatar_url']); ?>" 
                                                         class="rounded-circle me-3"
                                                         style="width: 50px; height: 50px;">
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($rec['full_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($rec['tagline']); ?></small>
                                                </div>
                                            </div>
                                            <a href="view-creative-profile.php?id=<?php echo $rec['id']; ?>" 
                                               class="btn btn-sm btn-primary mt-3">Lihat Profil</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script untuk menandai teks pencarian di hasil
        document.addEventListener('DOMContentLoaded', function() {
            const query = "<?php echo addslashes($query); ?>";
            if (query) {
                // Highlight text in results
                const elements = document.querySelectorAll('.result-card');
                elements.forEach(el => {
                    const html = el.innerHTML;
                    const regex = new RegExp(`(${query})`, 'gi');
                    el.innerHTML = html.replace(regex, '<mark>$1</mark>');
                });
            }
        });
    </script>
</body>
</html>