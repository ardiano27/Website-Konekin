<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konekin - Kolaborasi UMKM & Kreator Profesional</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        /* --- VARS & GLOBAL --- */
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #60a5fa;
            --secondary-color: #475569;
            --accent-color: #f59e0b;
            --dark-color: #1e293b;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --umkm-color: #1e40af;
            --creative-color: #7c3aed;
            --success-color: #10b981;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-color);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }

        /* --- NAVBAR --- */
        .navbar {
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            padding: 1rem 0;
            transition: all 0.3s;
        }
        .navbar-brand {
            font-weight: 800;
            color: var(--primary-color) !important;
            font-size: 1.8rem;
            letter-spacing: -0.5px;
        }
        .navbar-brand i {
            background: linear-gradient(135deg, var(--primary-color), var(--creative-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav-link {
            font-weight: 600;
            color: var(--dark-color) !important;
            margin-left: 1.2rem;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .nav-link:hover {
            color: var(--primary-color) !important;
        }
        .btn-login-nav {
            background: transparent;
            color: var(--primary-color) !important;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 700;
            margin-right: 10px;
            transition: all 0.3s;
        }
        .btn-login-nav:hover {
            background: var(--primary-color);
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .btn-signup-nav {
            background: var(--primary-color);
            color: white !important;
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            transition: all 0.3s;
            border: 2px solid var(--primary-color);
        }
        .btn-signup-nav:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3);
        }

        /* --- HERO SECTION --- */
        .hero-section {
            position: relative;
            padding: 160px 0 100px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            overflow: hidden;
            min-height: 85vh;
            display: flex;
            align-items: center;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0) 70%);
            border-radius: 50%;
            z-index: 1;
        }
        .hero-content h1 {
            font-weight: 800;
            font-size: 3.2rem;
            line-height: 1.15;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--creative-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-content p {
            font-size: 1.2rem;
            color: var(--secondary-color);
            margin-bottom: 2.5rem;
            font-weight: 400;
            line-height: 1.6;
        }
        .btn-cta {
            padding: 0.9rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 12px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
        }
        .btn-cta-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }
        .btn-cta-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.3);
        }
        .hero-image-container {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hero-image {
            max-width: 100%;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            transform: perspective(1000px) rotateY(-10deg);
            transition: all 0.5s ease;
            border: 8px solid white;
        }
        .hero-image:hover {
            transform: perspective(1000px) rotateY(0deg);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        /* --- FEATURED PROFILES SECTION --- */
        .profiles-section {
            padding: 100px 0;
            background: white;
        }
        .section-title {
            text-align: center;
            margin-bottom: 60px;
            position: relative;
        }
        .section-title h6 {
            color: var(--primary-color);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }
        .section-title h2 {
            font-weight: 800;
            font-size: 2.8rem;
            color: var(--dark-color);
        }
        .profile-card {
            background: var(--card-bg);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            border: none;
            height: 100%;
            margin-bottom: 30px;
            position: relative;
            cursor: pointer;
        }
        .profile-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }
        .profile-card-header {
            height: 160px;
            position: relative;
            overflow: hidden;
        }
        .umkm-profile .profile-card-header {
            background: linear-gradient(135deg, var(--umkm-color), #3b82f6);
        }
        .creative-profile .profile-card-header {
            background: linear-gradient(135deg, var(--creative-color), #8b5cf6);
        }
        .profile-card-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 5px solid white;
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            object-fit: cover;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .profile-card-body {
            padding: 60px 25px 30px;
            text-align: center;
        }
        .profile-card-name {
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        .profile-card-title {
            color: var(--secondary-color);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        .profile-card-desc {
            color: var(--secondary-color);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .profile-card-stats {
            display: flex;
            justify-content: space-around;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            margin-top: 15px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        .stat-label {
            font-size: 0.8rem;
            color: var(--secondary-color);
            margin-top: 5px;
        }
        .profile-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.9);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .umkm-profile .profile-badge {
            color: var(--umkm-color);
        }
        .creative-profile .profile-badge {
            color: var(--creative-color);
        }

        /* --- HOW IT WORKS SECTION --- */
        .how-it-works {
            padding: 100px 0;
            background: #f8fafc;
        }
        .step-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            height: 100%;
            border-top: 5px solid var(--primary-color);
        }
        .step-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        .step-number {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto 25px;
        }
        .step-title {
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        .step-desc {
            color: var(--secondary-color);
            line-height: 1.6;
        }

        /* --- MODAL STYLES --- */
        .modal-content {
            border-radius: 20px;
            border: none;
            overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--creative-color));
            color: white;
            border-bottom: none;
            padding: 30px;
        }
        .modal-body {
            padding: 30px;
            text-align: center;
        }
        .modal-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        .btn-modal {
            padding: 10px 30px;
            font-weight: 600;
            border-radius: 10px;
            margin: 0 10px;
        }
        .btn-modal-login {
            background: var(--primary-color);
            color: white;
            border: none;
        }
        .btn-modal-login:hover {
            background: var(--primary-dark);
        }
        .btn-modal-signup {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        .btn-modal-signup:hover {
            background: var(--primary-color);
            color: white;
        }

        /* --- FOOTER --- */
        .footer {
            background: #1e293b;
            color: rgba(255,255,255,0.7);
            padding: 80px 0 30px;
            position: relative;
        }
        .footer-brand {
            color: white;
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            display: block;
            text-decoration: none;
        }
        .footer-title {
            color: white;
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .footer ul { list-style: none; padding: 0; }
        .footer ul li { margin-bottom: 1rem; }
        .footer ul li a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
        }
        .footer ul li a:hover {
            color: var(--accent-color);
            padding-left: 8px;
        }
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            color: white;
            border-radius: 50%;
            margin-right: 10px;
            transition: all 0.3s;
            text-decoration: none;
        }
        .social-links a:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }
        .footer-bottom {
            margin-top: 70px;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            font-size: 0.9rem;
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2.5rem;
            }
            .section-title h2 {
                font-size: 2.2rem;
            }
            .hero-image {
                margin-top: 40px;
                transform: none;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-handshake me-2"></i>Konekin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">Cara Kerja</a></li>
                    <li class="nav-item"><a class="nav-link" href="#profiles">Profil</a></li>
                    <li class="nav-item"><a class="nav-link" href="#tentang">Tentang Kami</a></li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-login-nav" href="login.php">
                            Masuk
                        </a>
                        <a class="btn btn-signup-nav" href="register-choice.php">
                            Daftar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero-section">
        <div class="container position-relative z-2">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right" data-aos-duration="1000">
                    <div class="hero-content">
                        <h1>Bangun Ekonomi Kreatif Melalui Kolaborasi Nyata</h1>
                        <p>Platform ekosistem yang mempertemukan visi bisnis UMKM dengan keahlian para kreator profesional Indonesia untuk menciptakan dampak luar biasa.</p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="#profiles" class="btn btn-cta btn-cta-primary shadow-lg">
                                Jelajahi Peluang <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                            <a href="#how-it-works" class="btn btn-cta btn-outline-primary shadow-sm">
                                <i class="fas fa-play-circle me-2"></i>Cara Kerja
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                    <div class="hero-image-container">
                        <!-- Menggunakan gambar dari file ideas creative think.png -->
                        <img src="assets/images/ekonomi kreatif design.jpg" alt="Creative Ideas" class="hero-image">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section id="profiles" class="profiles-section">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h6>Temukan Talenta Terbaik</h6>
                <h2>Profil Unggulan Konekin</h2>
                <p class="text-muted" style="font-size: 1.1rem;">Bergabunglah dengan komunitas profesional kami untuk kolaborasi yang lebih baik.</p>
            </div>
            
            <div class="row g-4">
                <!-- UMKM Profile Card -->
                <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="profile-card umkm-profile" data-bs-toggle="modal" data-bs-target="#loginModal">
                        <div class="profile-card-header">
                            <span class="profile-badge">UMKM</span>
                        </div>
                        <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" 
                             alt="UMKM Profile" class="profile-card-img">
                        <div class="profile-card-body">
                            <h4 class="profile-card-name">Berkah Jaya Batik</h4>
                            <p class="profile-card-title">Bisnis Fashion & Kerajinan</p>
                            <p class="profile-card-desc">Produsen batik tradisional dengan 10 tahun pengalaman, mencari kreator untuk pengembangan branding digital.</p>
                            <div class="profile-card-stats">
                                <div class="stat-item">
                                    <div class="stat-value">24</div>
                                    <div class="stat-label">Proyek</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">4.8</div>
                                    <div class="stat-label">Rating</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">15</div>
                                    <div class="stat-label">Kreator</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Creative Profile Card -->
                <div class="col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="profile-card creative-profile" data-bs-toggle="modal" data-bs-target="#loginModal">
                        <div class="profile-card-header">
                            <span class="profile-badge">Kreator</span>
                        </div>
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" 
                             alt="Creative Profile" class="profile-card-img">
                        <div class="profile-card-body">
                            <h4 class="profile-card-name">Ahmad Digital</h4>
                            <p class="profile-card-title">UI/UX Designer & Developer</p>
                            <p class="profile-card-desc">Spesialis dalam desain digital untuk UMKM, telah menyelesaikan 50+ proyek dengan kepuasan klien 98%.</p>
                            <div class="profile-card-stats">
                                <div class="stat-item">
                                    <div class="stat-value">57</div>
                                    <div class="stat-label">Proyek</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">4.9</div>
                                    <div class="stat-label">Rating</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">Rp 2,5jt</div>
                                    <div class="stat-label">/proyek</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-5">
                <div class="col-12 text-center" data-aos="fade-up" data-aos-delay="300">
                    <p class="text-muted">Ingin melihat lebih banyak profil? Bergabunglah dengan komunitas kami!</p>
                    <a href="register-choice.php" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-user-plus me-2"></i>Bergabung Sekarang
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h6>Cara Kerja Platform</h6>
                <h2>Kolaborasi dalam 4 Langkah Mudah</h2>
                <p class="text-muted" style="font-size: 1.1rem;">Temukan cara Konekin menghubungkan UMKM dengan kreator profesional.</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4 class="step-title">Daftar Akun</h4>
                        <p class="step-desc">Pilih peran Anda sebagai UMKM atau Kreator. Lengkapi profil untuk mendapatkan rekomendasi terbaik.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4 class="step-title">Temukan Partner</h4>
                        <p class="step-desc">Cari dan temukan UMKM atau kreator yang sesuai dengan kebutuhan dan keahlian Anda.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4 class="step-title">Mulai Kolaborasi</h4>
                        <p class="step-desc">Komunikasikan kebutuhan, buat perjanjian, dan mulai bekerja sama dalam platform yang aman.</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <h4 class="step-title">Hasil & Ulasan</h4>
                        <p class="step-desc">Selesaikan proyek, berikan ulasan, dan bangun portofolio yang semakin kuat.</p>
                    </div>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-12 text-center">
                    <div class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-4" data-aos="fade-up" data-aos-delay="500">
                        <a href="register-choice.php" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-rocket me-2"></i>Mulai Sekarang
                        </a>
                        <a href="login.php" class="btn btn-outline-primary btn-lg px-5">
                            <i class="fas fa-sign-in-alt me-2"></i>Masuk Akun
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Required Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">Akses Diperlukan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h4 class="mb-3">Anda perlu masuk terlebih dahulu</h4>
                    <p class="text-muted mb-4">Untuk melihat detail profil dan memulai kolaborasi, silakan masuk atau daftar akun Konekin.</p>
                    <div class="d-flex justify-content-center">
                        <a href="login.php" class="btn btn-modal btn-modal-login">Masuk</a>
                        <a href="register-choice.php" class="btn btn-modal btn-modal-signup">Daftar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer" id="tentang">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5">
                    <a href="#" class="footer-brand">
                        <i class="fas fa-handshake me-2"></i>Konekin
                    </a>
                    <p class="mb-4" style="line-height: 1.7;">Platform kolaborasi digital yang didedikasikan untuk memberdayakan UMKM Indonesia melalui sinergi dengan talenta kreatif nasional. Kami percaya pada kekuatan kolaborasi untuk pertumbuhan ekonomi.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <h5 class="footer-title">Platform</h5>
                    <ul>
                        <li><a href="index.php">Beranda</a></li>
                        <li><a href="#how-it-works">Cara Kerja</a></li>
                        <li><a href="#profiles">Profil</a></li>
                        <li><a href="#tentang">Tentang Kami</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <h5 class="footer-title">Dukungan</h5>
                    <ul>
                        <li><a href="#">Pusat Bantuan</a></li>
                        <li><a href="#">Syarat & Ketentuan</a></li>
                        <li><a href="#">Kebijakan Privasi</a></li>
                        <li><a href="#">Hubungi Kami</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h5 class="footer-title">Kontak</h5>
                    <ul style="font-size: 0.95rem;">
                        <li><i class="fas fa-map-marker-alt me-2 text-primary"></i> Jakarta, Indonesia</li>
                        <li><i class="fas fa-envelope me-2 text-primary"></i> hello@konekin.id</li>
                        <li><i class="fas fa-phone-alt me-2 text-primary"></i> +62 21 5555 0100</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">&copy; 2025 Konekin. Hak Cipta Dilindungi. Dibuat untuk memajukan ekonomi kreatif.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS Animation
        AOS.init({
            duration: 800,
            once: true,
            offset: 100,
            easing: 'ease-out-cubic'
        });

        // Navbar Background Change on Scroll
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                document.querySelector('.navbar').style.background = 'rgba(255, 255, 255, 0.98)';
                document.querySelector('.navbar').style.boxShadow = '0 4px 20px rgba(0,0,0,0.08)';
            } else {
                document.querySelector('.navbar').style.background = 'rgba(255, 255, 255, 0.95)';
                document.querySelector('.navbar').style.boxShadow = '0 2px 15px rgba(0,0,0,0.05)';
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if(targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>