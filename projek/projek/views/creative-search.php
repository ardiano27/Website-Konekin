<?php
session_start();
include "../config/Database.php";
include "../models/Review.php";
include "../models/Users.php";

$db = new DatabaseConnection();
$reviewModel = new Review($db);
$userModel = new Users($db);

$searchQuery = $_GET['q'] ?? '';
$minRating = $_GET['min_rating'] ?? '';
$skills = $_GET['skills'] ?? [];
$hourlyRate = $_GET['hourly_rate'] ?? '';
$experienceLevel = $_GET['experience_level'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 12;

$allSkills = $db->query("SELECT * FROM skills WHERE is_active = 1 ORDER BY name");

$creatives = $userModel->searchCreatives([
    'search_query' => $searchQuery,
    'min_rating' => $minRating,
    'skills' => $skills,
    'hourly_rate' => $hourlyRate,
    'experience_level' => $experienceLevel,
    'page' => $page,
    'limit' => $limit
]);

$totalResults = $userModel->getSearchCount([
    'search_query' => $searchQuery,
    'min_rating' => $minRating,
    'skills' => $skills,
    'hourly_rate' => $hourlyRate,
    'experience_level' => $experienceLevel
]);

$totalPages = ceil($totalResults / $limit);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari Creative Worker - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../../assets/css/search.css" rel="stylesheet">
    <style>
        .search-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            position: sticky;
            top: 20px;
        }
        
        .filter-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .creative-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            height: 100%;
        }
        
        .creative-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .creative-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
        }
        
        .rating-stars {
            color: #ffc107;
        }
        
        .skill-tag {
            display: inline-block;
            background: #e9ecef;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin: 0.1rem;
        }
        
        .price-badge {
            background: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .search-result-item {
            transition: transform 0.2s ease;
        }
        
        .results-count {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .active-filters {
            background: #e7f3ff;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .filter-badge {
            background: #007bff;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            display: inline-flex;
            align-items: center;
        }
        
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 8px;
            height: 150px;
            margin-bottom: 1rem;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <div class="search-hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="text-center mb-4">Temukan Creative Worker Terbaik</h1>
                    <form id="searchForm" method="GET" action="">
                        <div class="input-group input-group-lg">
                            <input type="text" name="q" class="form-control" 
                                   placeholder="Cari berdasarkan keahlian, nama, atau tagline..." 
                                   value="<?= htmlspecialchars($searchQuery) ?>">
                            <button class="btn btn-light" type="submit">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-3">
                <div class="filter-card">
                    <h5 class="mb-4">🔍 Filter Pencarian</h5>
                    
                    <div class="filter-section">
                        <label class="form-label fw-bold">⭐ Rating Minimal</label>
                        <div class="rating-filter">
                            <?php for($i=5; $i>=1; $i--): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="min_rating" 
                                           id="rating_<?= $i ?>" value="<?= $i ?>"
                                           <?= $minRating == $i ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="rating_<?= $i ?>">
                                        <?= str_repeat('★', $i) . str_repeat('☆', 5-$i) ?>
                                        <small class="text-muted">(<?= $i ?>.0+)</small>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="filter-section">
                        <label class="form-label fw-bold">🛠️ Keahlian</label>
                        <div class="skills-filter" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach($allSkills as $skill): ?>
                                <div class="form-check">
                                    <input class="form-check-input skill-checkbox" type="checkbox" 
                                           name="skills[]" value="<?= $skill['id'] ?>" 
                                           id="skill_<?= $skill['id'] ?>"
                                           <?= in_array($skill['id'], $skills) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="skill_<?= $skill['id'] ?>">
                                        <?= htmlspecialchars($skill['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="filter-section">
                        <label class="form-label fw-bold">💰 Tarif Per Jam</label>
                        <select class="form-select" name="hourly_rate">
                            <option value="">Semua Tarif</option>
                            <option value="0-50000" <?= $hourlyRate == '0-50000' ? 'selected' : '' ?>>≤ Rp 50.000</option>
                            <option value="50000-100000" <?= $hourlyRate == '50000-100000' ? 'selected' : '' ?>>Rp 50.000 - 100.000</option>
                            <option value="100000-200000" <?= $hourlyRate == '100000-200000' ? 'selected' : '' ?>>Rp 100.000 - 200.000</option>
                            <option value="200000-999999999" <?= $hourlyRate == '200000-999999999' ? 'selected' : '' ?>>≥ Rp 200.000</option>
                        </select>
                    </div>
                    
                    <div class="filter-section">
                        <label class="form-label fw-bold">🎯 Tingkat Pengalaman</label>
                        <select class="form-select" name="experience_level">
                            <option value="">Semua Level</option>
                            <option value="beginner" <?= $experienceLevel == 'beginner' ? 'selected' : '' ?>>Pemula</option>
                            <option value="intermediate" <?= $experienceLevel == 'intermediate' ? 'selected' : '' ?>>Menengah</option>
                            <option value="expert" <?= $experienceLevel == 'expert' ? 'selected' : '' ?>>Expert</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" form="searchForm" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Terapkan Filter
                        </button>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="fas fa-refresh me-2"></i>Reset Filter
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <div class="search-header mb-4">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-1">Creative Worker Tersedia</h4>
                            <p class="results-count mb-0">
                                Menampilkan <strong><?= number_format($totalResults) ?></strong> hasil
                                <?php if ($searchQuery): ?>
                                    untuk "<strong><?= htmlspecialchars($searchQuery) ?></strong>"
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-auto">
                            <select class="form-select" id="sort-results" name="sort">
                                <option value="rating_desc">Rating Tertinggi</option>
                                <option value="projects_desc">Proyek Terbanyak</option>
                                <option value="rate_asc">Tarif Terendah</option>
                                <option value="rate_desc">Tarif Tertinggi</option>
                                <option value="name_asc">Nama A-Z</option>
                            </select>
                        </div>
                    </div>
                </div>

                <?php if ($minRating || $skills || $hourlyRate || $experienceLevel): ?>
                <div class="active-filters">
                    <strong>Filter Aktif:</strong>
                    <?php if ($minRating): ?>
                        <span class="filter-badge">
                            Rating ≥ <?= $minRating ?> <i class="fas fa-times ms-1" onclick="removeFilter('min_rating')"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($skills): ?>
                        <?php foreach($skills as $skillId): 
                            $skillName = $allSkills[array_search($skillId, array_column($allSkills, 'id'))]['name'] ?? '';
                        ?>
                            <span class="filter-badge">
                                <?= $skillName ?> <i class="fas fa-times ms-1" onclick="removeSkillFilter(<?= $skillId ?>)"></i>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if ($hourlyRate): ?>
                        <span class="filter-badge">
                            <?= 
                                $hourlyRate == '0-50000' ? '≤ Rp 50k' :
                                ($hourlyRate == '50000-100000' ? 'Rp 50k-100k' :
                                ($hourlyRate == '100000-200000' ? 'Rp 100k-200k' : '≥ Rp 200k'))
                            ?> 
                            <i class="fas fa-times ms-1" onclick="removeFilter('hourly_rate')"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($experienceLevel): ?>
                        <span class="filter-badge">
                            <?= 
                                $experienceLevel == 'beginner' ? 'Pemula' :
                                ($experienceLevel == 'intermediate' ? 'Menengah' : 'Expert')
                            ?>
                            <i class="fas fa-times ms-1" onclick="removeFilter('experience_level')"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div id="search-results">
                    <?php if (empty($creatives)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Tidak ada creative worker yang ditemukan</h5>
                            <p class="text-muted">Coba ubah kata kunci atau filter pencarian Anda</p>
                            <a href="?" class="btn btn-primary">Reset Pencarian</a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach($creatives as $creative): 
                                $avgRating = $reviewModel->calculateRatingAverage($creative['id']);
                            ?>
                            <div class="col-md-6 col-lg-4 search-result-item">
                                <div class="card creative-card">
                                    <div class="card-body">
                                        <div class="text-center mb-3">
                                            <img src="<?= $creative['avatar_url'] ?: '../../assets/images/default-avatar.jpg' ?>" 
                                                 alt="<?= htmlspecialchars($creative['full_name']) ?>" 
                                                 class="creative-avatar mb-2">
                                            <h5 class="card-title mb-1"><?= htmlspecialchars($creative['full_name']) ?></h5>
                                            <p class="text-muted small mb-2"><?= htmlspecialchars($creative['tagline'] ?? 'Creative Worker') ?></p>
                                        </div>
                                        
                                        <div class="text-center mb-3">
                                            <div class="rating-stars mb-1">
                                                <?= str_repeat('★', floor($avgRating['overall'])) ?><?= str_repeat('☆', 5 - floor($avgRating['overall'])) ?>
                                            </div>
                                            <small class="text-muted">
                                                <strong><?= number_format($avgRating['overall'], 1) ?></strong> 
                                                (<?= $avgRating['count'] ?> review)
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted d-block mb-1"><strong>Keahlian:</strong></small>
                                            <div class="skills-container">
                                                <?php 
                                                $creativeSkills = array_slice($creative['skills'] ?? [], 0, 3);
                                                foreach($creativeSkills as $skill): 
                                                ?>
                                                    <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($creative['skills'] ?? []) > 3): ?>
                                                    <span class="skill-tag">+<?= count($creative['skills']) - 3 ?> lagi</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="creative-info mb-3">
                                            <div class="row text-center small">
                                                <div class="col-6">
                                                    <div class="text-muted">Proyek</div>
                                                    <strong><?= $creative['completed_projects'] ?? 0 ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-muted">Pengalaman</div>
                                                    <strong><?= 
                                                        $creative['experience_level'] == 'beginner' ? 'Pemula' :
                                                        ($creative['experience_level'] == 'intermediate' ? 'Menengah' : 'Expert')
                                                    ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="price-badge">
                                                Rp <?= number_format($creative['hourly_rate'] ?? 0, 0, ',', '.') ?>/jam
                                            </div>
                                            <a href="../profile/creative-profile.php?id=<?= $creative['id'] ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                Lihat Profil
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Search results pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i> Sebelumnya
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    Selanjutnya <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function removeFilter(filterName) {
        const url = new URL(window.location.href);
        url.searchParams.delete(filterName);
        window.location.href = url.toString();
    }
    
    function removeSkillFilter(skillId) {
        const url = new URL(window.location.href);
        const skills = url.searchParams.getAll('skills[]');
        const newSkills = skills.filter(id => id != skillId);
        
        url.searchParams.delete('skills[]');
        newSkills.forEach(skill => url.searchParams.append('skills[]', skill));
        
        window.location.href = url.toString();
    }
    
    document.getElementById('sort-results')?.addEventListener('change', function() {
        document.getElementById('searchForm').submit();
    });
    
    document.querySelectorAll('.filter-card input, .filter-card select').forEach(element => {
        element.addEventListener('change', function() {
            setTimeout(() => {
                document.getElementById('searchForm').submit();
            }, 500);
        });
    });
    </script>
</body>
</html>