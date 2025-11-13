<?php include "check_login.php"; 
if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

include_once "config/Database.php";
$database = new DatabaseConnection();
$conn = $database->getConnection();

$sql = "SELECT p.*, cp.tagline, cp.experience_level 
        FROM portfolios p 
        LEFT JOIN creative_profiles cp ON p.creative_profile_id = cp.id 
        WHERE cp.user_id = :user_id 
        ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$skills_sql = "SELECT s.name, cs.proficiency_level 
               FROM creative_skills cs 
               JOIN skills s ON cs.skill_id = s.id 
               JOIN creative_profiles cp ON cs.creative_profile_id = cp.id 
               WHERE cp.user_id = :user_id";
$skills_stmt = $conn->prepare($skills_sql);
$skills_stmt->bindParam(':user_id', $_SESSION['user_id']);
$skills_stmt->execute();
$skills = $skills_stmt->fetchAll(PDO::FETCH_ASSOC);

$profile_sql = "SELECT * FROM creative_profiles WHERE user_id = :user_id";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bindParam(':user_id', $_SESSION['user_id']);
$profile_stmt->execute();
$profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #549efeff;
            --primary-dark: #82a8db
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .main-content {
            padding: 10px 20px 0px;
            
            background-color: #f8f9fa;
        }
        .content-container {
            padding-bottom: 50px;
            padding-left: 10px;  /* Mengurangi padding kiri */
            padding-right: 10px; /* Mengurangi padding kanan */
        }   

/* FIX: Mengurangi max-width container atau mengatur padding container */
        .content-container .container {
            max-width: 100%; /* Memastikan container memenuhi area yang tersedia */
            padding-left: 0px; /* Mengurangi padding kiri container */
            padding-right: 0px; 
        }

        
        .portfolio-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .portfolio-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .portfolio-image {
            height: 200px;
            background-size: cover;
            background-position: center;
            background-color: #f8f9fa;
        }
        
        .skill-badge {
            background-color: var(--primary-color);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin: 2px;
        }
        
        .add-portfolio-btn {
            border: 2px dashed #dee2e6;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .add-portfolio-btn:hover {
            border-color: var(--primary-color);
            background-color: rgba(37, 150, 190, 0.05);
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            border-radius: 15px;
        }
        
        .experience-badge {
            background-color: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        /* FIX: Menambahkan padding untuk bagian bawah halaman */
        .content-container {
            padding-bottom: 50px; Memberikan ruang di bagian bawah
        }
        
        /* FIX: Memastikan konten utama memiliki margin bawah yang cukup */
        .anjay {
            margin-bottom: 30px;
        }
        
        /* FIX: Memberikan padding tambahan untuk area portfolio kosong */
        .empty-portfolio {
            padding: 60px 20px;
            background-color: white;
            border-radius: 15px;
            margin-top: 20px;
        }
        
        /* FIX: Memastikan card memiliki background putih */
        .card {
            background-color: white;
        }
        
        /* FIX: Container utama */
        .main-container {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <?php include "dashboard-sidebar.php"; ?>

    <!-- FIX: Menambahkan container utama -->
    <div class="main-container">
        <div class="main-content">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="display-5 fw-bold"><?php echo $_SESSION['full_name']; ?></h1>
                            <p class="lead mb-2"><?php echo $profile['tagline'] ?? 'Creative Worker'; ?></p>
                            <?php if ($profile['experience_level']): ?>
                                <span class="experience-badge">
                                    <?php 
                                    $experience_levels = [
                                        'beginner' => 'Pemula (0-2 tahun)',
                                        'intermediate' => 'Menengah (2-5 tahun)', 
                                        'expert' => 'Expert (5+ tahun)'
                                    ];
                                    echo $experience_levels[$profile['experience_level']] ?? $profile['experience_level'];
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="rating">
                                <span class="h3"><?php echo number_format($profile['rating'] ?? 0, 1); ?></span>
                                <i class="fas fa-star text-warning"></i>
                                <small class="d-block"><?php echo $profile['completed_projects'] ?? 0; ?> proyek selesai</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-container">
                <div class="container">
                    <div class="anjay">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                                <i class="fas fa-plus me-2"></i>Tambah Portfolio
                            </button>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Keahlian</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($skills)): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach($skills as $skill): ?>
                                            <span class="skill-badge">
                                                <?php echo $skill['name']; ?>
                                                <small>(<?php echo ucfirst($skill['proficiency_level']); ?>)</small>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Belum ada keahlian yang ditambahkan. 
                                        <a href="profile.php" class="text-primary">Tambahkan keahlian di profil</a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card portfolio-card add-portfolio-btn" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                                    <div class="card-body text-center text-muted">
                                        <i class="fas fa-plus-circle fa-3x mb-3"></i>
                                        <h5>Tambah Portfolio Baru</h5>
                                        <p class="mb-0">Tampilkan karya terbaik Anda</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Portfolio Items -->
                            <?php foreach($portfolios as $portfolio): 
                                $image_urls = json_decode($portfolio['image_urls'] ?? '[]', true);
                                $first_image = !empty($image_urls) ? $image_urls[0] : null;
                                $skills_used = json_decode($portfolio['skills_used'] ?? '[]', true);
                            ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card portfolio-card">
                                        <?php if ($first_image): ?>
                                            <div class="portfolio-image" style="background-image: url('<?php echo $first_image; ?>')"></div>
                                        <?php else: ?>
                                            <div class="portfolio-image d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-image fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo $portfolio['title']; ?></h5>
                                            <p class="card-text text-muted small">
                                                <?php echo strlen($portfolio['description']) > 100 ? 
                                                    substr($portfolio['description'], 0, 100) . '...' : 
                                                    $portfolio['description']; ?>
                                            </p>
                                            
                                            <?php if (!empty($skills_used)): ?>
                                                <div class="mb-3">
                                                    <?php foreach(array_slice($skills_used, 0, 3) as $skill): ?>
                                                        <span class="badge bg-light text-dark border"><?php echo $skill; ?></span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($skills_used) > 3): ?>
                                                        <span class="badge bg-light text-dark border">+<?php echo count($skills_used) - 3; ?> lebih</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <?php echo date('d M Y', strtotime($portfolio['project_date'] ?? $portfolio['created_at'])); ?>
                                                </small>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewPortfolioModal"
                                                            onclick="viewPortfolio(<?php echo htmlspecialchars(json_encode($portfolio)); ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editPortfolioModal"
                                                            onclick="editPortfolio(<?php echo htmlspecialchars(json_encode($portfolio)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="deletePortfolio(<?php echo $portfolio['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (empty($portfolios)): ?>
                        <!-- FIX: Menambahkan class empty-portfolio untuk padding yang lebih baik -->
                        <div class="text-center py-5 empty-portfolio">
                            <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">Belum ada portfolio</h4>
                            <p class="text-muted">Mulai dengan menambahkan portfolio pertama Anda untuk menarik perhatian UMKM.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                                <i class="fas fa-plus me-2"></i>Tambah Portfolio Pertama
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Portfolio -->
<div class="modal fade" id="addPortfolioModal" tabindex="-1" aria-labelledby="addPortfolioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="portfolio-action.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPortfolioModalLabel">Tambah Portfolio Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Judul Portfolio</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="project_url" class="form-label">URL Proyek (opsional)</label>
                        <input type="url" class="form-control" id="project_url" name="project_url" placeholder="https://...">
                    </div>
                    
                    <div class="mb-3">
                        <label for="project_date" class="form-label">Tanggal Proyek</label>
                        <input type="date" class="form-control" id="project_date" name="project_date">
                    </div>
                    
                    <div class="mb-3">
                        <label for="video_url" class="form-label">URL Video (opsional)</label>
                        <input type="url" class="form-control" id="video_url" name="video_url" placeholder="https://...">
                    </div>
                    
                    <div class="mb-3">
                        <label for="skills_used" class="form-label">Keahlian yang Digunakan</label>
                        <input type="text" class="form-control" id="skills_used" name="skills_used" placeholder="Pisahkan dengan koma, contoh: Web Design, UI/UX, Photography">
                        <div class="form-text">Pisahkan setiap keahlian dengan koma</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="images" class="form-label">Gambar Portfolio</label>
                        <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                        <div class="form-text">Pilih satu atau beberapa gambar</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_public" name="is_public" checked>
                        <label class="form-check-label" for="is_public">
                            Tampilkan portfolio secara publik
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Portfolio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Portfolio -->
<div class="modal fade" id="editPortfolioModal" tabindex="-1" aria-labelledby="editPortfolioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="portfolio-action.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPortfolioModalLabel">Edit Portfolio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="portfolio_id" id="edit_portfolio_id">
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Judul Portfolio</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_project_url" class="form-label">URL Proyek (opsional)</label>
                        <input type="url" class="form-control" id="edit_project_url" name="project_url" placeholder="https://...">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_project_date" class="form-label">Tanggal Proyek</label>
                        <input type="date" class="form-control" id="edit_project_date" name="project_date">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_video_url" class="form-label">URL Video (opsional)</label>
                        <input type="url" class="form-control" id="edit_video_url" name="video_url" placeholder="https://...">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_skills_used" class="form-label">Keahlian yang Digunakan</label>
                        <input type="text" class="form-control" id="edit_skills_used" name="skills_used" placeholder="Pisahkan dengan koma, contoh: Web Design, UI/UX, Photography">
                        <div class="form-text">Pisahkan setiap keahlian dengan koma</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_images" class="form-label">Gambar Tambahan</label>
                        <input type="file" class="form-control" id="edit_images" name="images[]" multiple accept="image/*">
                        <div class="form-text">Pilih gambar tambahan (gambar existing akan tetap tersimpan)</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit_is_public" name="is_public">
                        <label class="form-check-label" for="edit_is_public">
                            Tampilkan portfolio secara publik
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update Portfolio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal View Portfolio -->
<div class="modal fade" id="viewPortfolioModal" tabindex="-1" aria-labelledby="viewPortfolioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewPortfolioTitle">Detail Portfolio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="viewPortfolioContent">
                    <!-- Content akan diisi oleh JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewPortfolio(portfolio) {
            document.getElementById('viewPortfolioTitle').textContent = portfolio.title;
            
            const contentDiv = document.getElementById('viewPortfolioContent');
            const skillsUsed = JSON.parse(portfolio.skills_used || '[]');
            
            contentDiv.innerHTML = `
                <h6>Deskripsi</h6>
                <p>${portfolio.description}</p>
                
                ${skillsUsed.length > 0 ? `
                    <h6>Keahlian yang Digunakan</h6>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        ${skillsUsed.map(skill => `<span class="badge bg-primary">${skill}</span>`).join('')}
                    </div>
                ` : ''}
                
                <div class="row text-muted small">
                    <div class="col-md-6">
                        <strong>Tanggal Proyek:</strong> ${portfolio.project_date ? new Date(portfolio.project_date).toLocaleDateString('id-ID') : 'Tidak ditentukan'}
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong> ${portfolio.is_public ? 'Publik' : 'Privat'}
                    </div>
                </div>
            `;
        }
        
        function deletePortfolio(portfolioId) {
            if (confirm('Apakah Anda yakin ingin menghapus portfolio ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'portfolio-action.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'portfolio_id';
                idInput.value = portfolioId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
            function editPortfolio(portfolio) {
                document.getElementById('edit_portfolio_id').value = portfolio.id;
                document.getElementById('edit_title').value = portfolio.title;
                document.getElementById('edit_description').value = portfolio.description;
                document.getElementById('edit_project_url').value = portfolio.project_url || '';
                document.getElementById('edit_project_date').value = portfolio.project_date || '';
                document.getElementById('edit_video_url').value = portfolio.video_url || '';
                
                const skillsUsed = JSON.parse(portfolio.skills_used || '[]');
                document.getElementById('edit_skills_used').value = skillsUsed.join(', ');
                
                document.getElementById('edit_is_public').checked = portfolio.is_public == 1;
            }
        }
    </script>
    
</body>
</html>

    