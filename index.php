<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konekin - Platform Penghubung Creative Worker & UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2A5EA8;
            --primary-light: #6CA1E8;
            --secondary-color: #FF7E5F;
            --accent-color: #4ECDC4;
            --neutral-dark: #2D3748;
            --neutral-light: #f8f9fa;
            --success-color: #48BB78;
            --warning-color: #ED8936;
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 55px; 
        }
        
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #E86A4A;
            border-color: #E86A4A;
            color: white;
        }
        
        .btn-accent {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }
        
        .btn-accent:hover {
            background-color: #3ABAB3;
            border-color: #3ABAB3;
            color: white;
        }
        
        .hero-section {
            background: linear-gradient(rgba(62, 127, 213,0.8), rgba(42, 94, 168,0.9)), url('https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .feature-icon i {
            font-size: 30px;
            color: white;
        }
        
        .search-box {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-box input {
            border-radius: 50px 0 0 50px;
            padding: 15px 20px;
            border: none;
        }
        
        .search-box button {
            border-radius: 0 50px 50px 0;
            padding: 15px 30px;
            background-color: var(--primary-color);
            border: none;
        }
        
        .cta-section {
            background-color: var(--neutral-light);
            padding: 80px 0;
        }
        
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        
        .navbar-brand {
            font-weight: 600;
            color: var(--primary-color) !important;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-welcome {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        /* Warna untuk card features */
        .feature-card-1 {
            border-top: 4px solid var(--primary-color);
        }
        
        .feature-card-2 {
            border-top: 4px solid var(--secondary-color);
        }
        
        .feature-card-3 {
            border-top: 4px solid var(--accent-color);
        }
        
        .feature-card-4 {
            border-top: 4px solid var(--success-color);
        }
        
        /* Warna untuk step numbers */
        .step-1 {
            background-color: var(--primary-color) !important;
        }
        
        .step-2 {
            background-color: var(--secondary-color) !important;
        }
        
        .step-3 {
            background-color: var(--accent-color) !important;
        }
        
        /* Footer styling */
        .footer-section {
            background: linear-gradient(to right, var(--neutral-dark), #1A202C);
        }
        
        /* Typography improvements */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
        }
        
        .lead {
            font-weight: 400;
        }
        
        /* Custom section backgrounds */
        .section-light {
            background-color: var(--neutral-light);
        }
        
        .section-accent {
            background-color: rgba(78, 205, 196, 0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-link me-2"></i>Konekin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">Cara Kerja</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Keunggulan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">Tentang Kami</a>
                    </li>
                </ul>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="user-menu">
                        <span class="user-welcome">Halo, <?php echo $_SESSION['full_name']; ?>!</span>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                        <a href="logout.php" class="btn btn-primary">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </div>
                <?php else: ?>
                    <div class="d-flex">
                        <a href="login.php" class="btn btn-outline-primary me-2">Log in</a>
                        <a href="register-choice.php" class="btn btn-primary">Sign Up</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Tingkatkan bisnis Anda dengan talenta kreatif terbaik</h1>
                    <p class="lead mb-5">Platform penghubung antara creative worker dengan UMKM untuk digitalisasi bisnis dan perluasan potensi lapangan kerja</p>
                    
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <a href="register-choice.php?type=umkm" class="btn btn-light btn-lg w-100 py-3 fw-bold">Mulai sebagai UMKM</a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="register-choice.php?type=creative" class="btn btn-outline-light btn-lg w-100 py-3 fw-bold">Jadi Creative Worker</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="dashboard.php" class="btn btn-light btn-lg w-100 py-3 fw-bold">
                                    <i class="fas fa-tachometer-alt me-2"></i>Pergi ke Dashboard
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="search-box">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Cari creative worker berdasarkan keahlian...">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="py-5 section-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Keunggulan Platform Kami</h2>
                <p class="lead">Mengapa memilih Konekin untuk kebutuhan digitalisasi bisnis Anda</p>
            </div>
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm card-hover h-100 feature-card-1">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5 class="card-title">Matching System</h5>
                            <p class="card-text">Sistem pencocokan cerdas yang menghubungkan UMKM dengan creative worker yang tepat sesuai kebutuhan.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm card-hover h-100 feature-card-2">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon" style="background-color: var(--secondary-color);">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h5 class="card-title">Project Management</h5>
                            <p class="card-text">Kelola proyek dengan mudah melalui dashboard terintegrasi untuk memantau perkembangan pekerjaan.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm card-hover h-100 feature-card-3">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon" style="background-color: var(--accent-color);">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <h5 class="card-title">User Friendly</h5>
                            <p class="card-text">Antarmuka yang intuitif dan mudah digunakan, bahkan untuk pengguna yang kurang familiar dengan teknologi.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm card-hover h-100 feature-card-4">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon" style="background-color: var(--success-color);">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h5 class="card-title">Secure</h5>
                            <p class="card-text">Keamanan data terjamin dengan sistem enkripsi dan perlindungan privasi yang ketat.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="py-5 section-accent">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Cara Kerja</h2>
                <p class="lead">Langkah-langkah mudah untuk memulai di Konekin</p>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 step-1" style="width: 80px; height: 80px;">
                            <span class="h4 mb-0">1</span>
                        </div>
                        <h5>Daftar Akun</h5>
                        <p>Buat akun sebagai UMKM atau Creative Worker sesuai kebutuhan Anda.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 step-2" style="width: 80px; height: 80px;">
                            <span class="h4 mb-0">2</span>
                        </div>
                        <h5>Cari atau Tawarkan Jasa</h5>
                        <p>UMKM dapat mencari talenta kreatif, sementara creative worker dapat menawarkan jasanya.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 step-3" style="width: 80px; height: 80px;">
                            <span class="h4 mb-0">3</span>
                        </div>
                        <h5>Mulai Bekerja Sama</h5>
                        <p>Terhubung, diskusikan proyek, dan mulai bekerja sama untuk mencapai tujuan bisnis.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section text-center">
        <div class="container">
            <h2 class="mb-4">Siap untuk meningkatkan bisnis Anda?</h2>
            <p class="lead mb-5">Bergabunglah dengan ratusan UMKM dan creative worker yang telah merasakan manfaat platform kami.</p>
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="register-choice.php" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-tachometer-alt me-2"></i>Pergi ke Dashboard
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-section text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-link me-2"></i>Konekin</h5>
                    <p>Platform penghubung antara creative worker dengan UMKM untuk digitalisasi bisnis dan perluasan potensi lapangan kerja.</p>
                </div>
                <div class="col-md-3">
                    <h5>Tautan Cepat</h5>
                    <ul class="list-unstyled">
                        <li><a href="#how-it-works" class="text-white">Cara Kerja</a></li>
                        <li><a href="#features" class="text-white">Keunggulan</a></li>
                        <li><a href="#about" class="text-white">Tentang Kami</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Kontak</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i>info@konekin.id</li>
                        <li><i class="fas fa-phone me-2"></i>(021) 1234-5678</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i>Jember, Indonesia</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; 2023 Konekin. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>