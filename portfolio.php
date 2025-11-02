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
            --primary-color: #2596be;
            --primary-dark: #1e7a9c;
        }
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        }
        
        .experience-badge {
            background-color: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include "dashboard-sidebar.php"; ?>

    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Portfolio Saya</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                <i class="fas fa-plus me-2"></i>Tambah Portfolio
            </button>
        </div>

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

        <?php if (empty($portfolios)): ?>
            <div class="text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Belum ada portfolio</h4>
                <p class="text-muted">Mulai dengan menambahkan portfolio pertama Anda untuk menarik perhatian UMKM.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                    <i class="fas fa-plus me-2"></i>Tambah Portfolio Pertama
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Portfolio Modal -->
    <div class="modal fade" id="addPortfolioModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Portfolio Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addPortfolioForm" action="portfolio-action.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Judul Portfolio *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="project_url" class="form-label">URL Proyek (opsional)</label>
                                <input type="url" class="form-control" id="project_url" name="project_url">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="project_date" class="form-label">Tanggal Proyek</label>
                                <input type="date" class="form-control" id="project_date" name="project_date">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="skills_used" class="form-label">Keahlian yang Digunakan (pisahkan dengan koma)</label>
                            <input type="text" class="form-control" id="skills_used" name="skills_used" 
                                   placeholder="Contoh: Graphic Design, UI/UX, Branding">
                        </div>
                        
                        <div class="mb-3">
                            <label for="images" class="form-label">Gambar Portfolio</label>
                            <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                            <div class="form-text">Pilih satu atau beberapa gambar untuk portfolio Anda</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="video_url" class="form-label">URL Video (opsional)</label>
                            <input type="url" class="form-control" id="video_url" name="video_url" 
                                   placeholder="https://youtube.com/...">
                        </div>
                        
                        <div class="form-check">
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

    <!-- View Portfolio Modal -->
    <div class="modal fade" id="viewPortfolioModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPortfolioTitle">Portfolio Detail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewPortfolioCarousel" class="carousel slide mb-3" data-bs-ride="carousel">
                        <div class="carousel-inner" id="viewPortfolioImages">
                            <!-- Images will be loaded here -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#viewPortfolioCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#viewPortfolioCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </button>
                    </div>
                    
                    <div id="viewPortfolioContent">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Portfolio Modal -->
    <div class="modal fade" id="editPortfolioModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Portfolio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editPortfolioForm" action="portfolio-action.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="edit_portfolio_id" name="portfolio_id">
                        
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Judul Portfolio *</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Deskripsi *</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_project_url" class="form-label">URL Proyek (opsional)</label>
                                <input type="url" class="form-control" id="edit_project_url" name="project_url">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_project_date" class="form-label">Tanggal Proyek</label>
                                <input type="date" class="form-control" id="edit_project_date" name="project_date">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_skills_used" class="form-label">Keahlian yang Digunakan</label>
                            <input type="text" class="form-control" id="edit_skills_used" name="skills_used">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Gambar Saat Ini</label>
                            <div id="currentImages" class="d-flex flex-wrap gap-2 mb-2">
                                <!-- Current images will be loaded here -->
                            </div>
                            <label for="edit_images" class="form-label">Tambah Gambar Baru (opsional)</label>
                            <input type="file" class="form-control" id="edit_images" name="images[]" multiple accept="image/*">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_video_url" class="form-label">URL Video (opsional)</label>
                            <input type="url" class="form-control" id="edit_video_url" name="video_url">
                        </div>
                        
                        <div class="form-check">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewPortfolio(portfolio) {
            // Set title
            document.getElementById('viewPortfolioTitle').textContent = portfolio.title;
            
            // Handle images
            const carouselInner = document.getElementById('viewPortfolioImages');
            carouselInner.innerHTML = '';
            
            const imageUrls = JSON.parse(portfolio.image_urls || '[]');
            if (imageUrls.length > 0) {
                imageUrls.forEach((url, index) => {
                    const carouselItem = document.createElement('div');
                    carouselItem.className = `carousel-item ${index === 0 ? 'active' : ''}`;
                    carouselItem.innerHTML = `
                        <img src="${url}" class="d-block w-100" style="max-height: 400px; object-fit: contain;" alt="${portfolio.title}">
                    `;
                    carouselInner.appendChild(carouselItem);
                });
            } else {
                carouselInner.innerHTML = `
                    <div class="carousel-item active">
                        <div class="d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                            <i class="fas fa-image fa-3x text-muted"></i>
                        </div>
                    </div>
                `;
            }
            
            // Set content
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
                
                ${portfolio.project_url ? `
                    <h6>Link Proyek</h6>
                    <p><a href="${portfolio.project_url}" target="_blank">${portfolio.project_url}</a></p>
                ` : ''}
                
                ${portfolio.video_url ? `
                    <h6>Video</h6>
                    <p><a href="${portfolio.video_url}" target="_blank">Lihat Video</a></p>
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
        
        function editPortfolio(portfolio) {
            document.getElementById('edit_portfolio_id').value = portfolio.id;
            document.getElementById('edit_title').value = portfolio.title;
            document.getElementById('edit_description').value = portfolio.description;
            document.getElementById('edit_project_url').value = portfolio.project_url || '';
            document.getElementById('edit_project_date').value = portfolio.project_date || '';
            document.getElementById('edit_video_url').value = portfolio.video_url || '';
            document.getElementById('edit_is_public').checked = portfolio.is_public == 1;
            
            // Set skills used
            const skillsUsed = JSON.parse(portfolio.skills_used || '[]');
            document.getElementById('edit_skills_used').value = skillsUsed.join(', ');
            
            // Show current images
            const currentImagesDiv = document.getElementById('currentImages');
            const imageUrls = JSON.parse(portfolio.image_urls || '[]');
            currentImagesDiv.innerHTML = '';
            
            if (imageUrls.length > 0) {
                imageUrls.forEach(url => {
                    const imgWrapper = document.createElement('div');
                    imgWrapper.className = 'position-relative';
                    imgWrapper.innerHTML = `
                        <img src="${url}" class="rounded" style="width: 80px; height: 60px; object-fit: cover;" alt="Current image">
                    `;
                    currentImagesDiv.appendChild(imgWrapper);
                });
            } else {
                currentImagesDiv.innerHTML = '<p class="text-muted">Tidak ada gambar</p>';
            }
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
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>