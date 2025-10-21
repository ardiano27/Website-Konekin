<?php
session_start();
// if (isset($_SESSION['user_id'])) {
//     if ($_SESSION['user_type'] === 'umkm') {
//         header("Location: dashboard-umkm.php");
//     } else {
//         header("Location: dashboard-creative.php");
//     }
//     exit;
// }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konekin - Platform Penghubung Creative Worker & UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2596be;
            --primary-dark: #1e7a9c;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .hero-section {
            background: linear-gradient(rgba(37, 150, 190, 0.9), rgba(37, 150, 190, 0.8)), url('https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80');
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
            background-color: var(--light-bg);
            padding: 80px 0;
        }
        
        .card-hover {
            transition: transform 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-10px);
        }
        
        .navbar-brand {
            font-weight: bold;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i></i>Konekin
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
                            <i></i>Dashboard
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

    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Tingkatkan bisnis Anda dengan talenta kreatif terbaik</h1>
            <p class="lead mb-5">Platform penghubung antara creative worker dengan UMKM untuk digitalisasi bisnis dan perluasan potensi lapangan kerja</p>
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="row justify-content-center">
                    <div class="col-md-3 mb-3">
                        <a href="register-choice.php?type=umkm" class="btn btn-light btn-lg w-100">Mulai sebagai UMKM</a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="register-choice.php?type=creative" class="btn btn-outline-light btn-lg w-100">Jadi Creative Worker</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row justify-content-center">
                    <div class="col-md-4 mb-3">
                        <a href="dashboard.php" class="btn btn-light btn-lg w-100">
                            <i></i>Pergi ke Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container">
            <div class="text-center mb-4">
                <h2>Cari talents kreatif untuk meningkatkan bisnis anda</h2>
            </div>
            <div class="search-box">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Cari creative worker berdasarkan keahlian...">
                    <button class="btn text-white" type="button">Search</button>
                </div>
            </div>
        </div>
    </section>


    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Keunggulan Platform Kami</h2>
                <p class="lead">Mengapa memilih CreativeHub untuk kebutuhan digitalisasi bisnis Anda</p>
            </div>
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm card-hover h-100">
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
                    <div class="card border-0 shadow-sm card-hover h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h5 class="card-title">Project Management</h5>
                            <p class="card-text">Kelola proyek dengan mudah melalui dashboard terintegrasi untuk memantau perkembangan pekerjaan.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm card-hover h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <h5 class="card-title">User Friendly</h5>
                            <p class="card-text">Antarmuka yang intuitif dan mudah digunakan, bahkan untuk pengguna yang kurang familiar dengan teknologi.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 shadow-sm card-hover h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
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

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-5 bg-white">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Cara Kerja</h2>
                <p class="lead">Langkah-langkah mudah untuk memulai di Konekin</p>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <span class="h4 mb-0">1</span>
                        </div>
                        <h5>Daftar Akun</h5>
                        <p>Buat akun sebagai UMKM atau Creative Worker sesuai kebutuhan Anda.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <span class="h4 mb-0">2</span>
                        </div>
                        <h5>Cari atau Tawarkan Jasa</h5>
                        <p>UMKM dapat mencari talenta kreatif, sementara creative worker dapat menawarkan jasanya.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <span class="h4 mb-0">3</span>
                        </div>
                        <h5>Mulai Bekerja Sama</h5>
                        <p>Terhubung, diskusikan proyek, dan mulai bekerja sama untuk mencapai tujuan bisnis.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section text-center">
        <div class="container">
            <h2 class="mb-4">Siap untuk meningkatkan bisnis Anda?</h2>
            <p class="lead mb-5">Bergabunglah dengan ratusan UMKM dan creative worker yang telah merasakan manfaat platform kami.</p>
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="register-choice.php" class="btn btn-primary btn-lg px-5">Daftar Sekarang</a>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-tachometer-alt me-2"></i>Pergi ke Dashboard
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Konekin</h5>
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
                        <li>Email: info@konekin.id</li>
                        <li>Telepon: (021) 1234-5678</li>
                        <li>Alamat: Jakarta, Indonesia</li>
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