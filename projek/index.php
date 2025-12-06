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
            background-color: #f8fafc;
        }
        
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background: white;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(62, 127, 213, 0.3);
        }

        /* --- UPDATED INTERACTIVE SECTION STYLE (START) --- */
        .interactive-section {
            position: relative;
            padding: 100px 0;
            overflow: hidden;
            background: radial-gradient(circle at center, #f8fafc 0%, #edf2f7 100%);
        }

        .interaction-container {
            position: relative;
            height: 500px;
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }

        /* SVG Connector Lines Layer */
        .connector-svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .connector-path {
            fill: none;
            stroke: var(--primary-light);
            stroke-width: 2;
            stroke-dasharray: 10;
            animation: dashFlow 30s linear infinite;
            opacity: 0.4;
        }

        @keyframes dashFlow {
            to { stroke-dashoffset: -1000; }
        }

        /* Character Avatars */
        .character-wrapper {
            position: absolute;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }

        .character-wrapper:hover {
            transform: translateY(-10px) scale(1.05);
            z-index: 10;
        }

        .character-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 4px solid white;
            position: relative;
        }

        /* Status Indicator Dot */
        .character-avatar::after {
            content: '';
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
            background: #48BB78;
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .character-label {
            margin-top: 15px;
            font-weight: 600;
            color: var(--neutral-dark);
            background: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        /* Positioning Avatars */
        .pos-umkm { top: 50px; left: 50px; }
        .pos-creative { top: 50px; right: 50px; }
        .pos-deal { bottom: 50px; left: 50%; transform: translateX(-50%); }

        /* Chat Bubbles (Glassmorphism) */
        .chat-bubble {
            position: absolute;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            padding: 20px 25px;
            max-width: 280px;
            box-shadow: 0 10px 40px rgba(62, 127, 213, 0.15);
            z-index: 5;
            opacity: 0;
            animation: popIn 0.5s ease forwards;
        }

        .chat-bubble p {
            color: var(--neutral-dark);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .chat-bubble small {
            display: block;
            margin-top: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary-color);
        }

        /* Bubble Positions & Animations */
        .bubble-1 {
            top: 60px; left: 170px;
            border-left: 4px solid var(--secondary-color);
            animation-delay: 0.5s;
        }
        .bubble-1:before { /* Arrow */
            content: ''; position: absolute; left: -10px; top: 20px;
            border-top: 10px solid transparent; border-bottom: 10px solid transparent; 
            border-right: 10px solid rgba(255, 255, 255, 0.9);
        }

        .bubble-2 {
            top: 120px; right: 170px;
            border-right: 4px solid var(--accent-color);
            animation-delay: 1.5s;
            text-align: right;
        }
        .bubble-2:before {
            content: ''; position: absolute; right: -10px; top: 20px;
            border-top: 10px solid transparent; border-bottom: 10px solid transparent; 
            border-left: 10px solid rgba(255, 255, 255, 0.9);
        }

        .bubble-3 {
            bottom: 160px; left: 50%;
            transform: translateX(-50%) scale(0.9);
            border-bottom: 4px solid var(--success-color);
            text-align: center;
            animation-delay: 2.5s;
            animation-name: popInCenter;
        }

        @keyframes popIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes popInCenter {
            from { opacity: 0; transform: translateX(-50%) translateY(20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        /* Responsive Design for Interactive Section */
        @media (max-width: 992px) {
            .interaction-container { height: auto; padding: 20px; }
            .connector-svg { display: none; }
            
            .character-wrapper {
                position: relative;
                top: auto !important;
                left: auto !important;
                right: auto !important;
                bottom: auto !important;
                transform: none !important;
                flex-direction: row;
                margin-bottom: 20px;
                width: 100%;
                cursor: default;
            }
            
            .character-avatar {
                width: 60px; height: 60px; font-size: 24px;
                margin-right: 15px; flex-shrink: 0;
            }
            
            .character-label { margin: 0; }
            
            .pos-creative { flex-direction: row-reverse; }
            .pos-creative .character-avatar { margin-right: 0; margin-left: 15px; }
            
            .chat-bubble {
                position: relative;
                top: auto !important;
                left: auto !important;
                right: auto !important;
                bottom: auto !important;
                transform: none !important;
                margin: 0 0 30px 0;
                max-width: 100%;
                width: 100%;
                animation: popIn 0.5s ease forwards !important;
            }
            
            .bubble-1 { margin-left: 30px; }
            .bubble-2 { margin-right: 30px; text-align: left; }
            .bubble-3 { margin-top: 20px; text-align: center; }
            .bubble-3:before { display: none; } /* Hide arrow for deal bubble if any */
            
            .pos-deal { justify-content: center; margin-top: 40px; }
        }
       
        /* Enhanced Features */
        .feature-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.4s ease;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
        }
        
        .feature-icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon-wrapper {
            transform: rotate(15deg) scale(1.1);
        }
        
        /* Stats Counter */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-10px);
        }
        
        .counter {
            font-size: 48px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        /* Enhanced Hero */
        .hero-section {
            background: linear-gradient(135deg, rgba(62, 127, 213, 0.9), rgba(42, 94, 168, 0.95)), 
                        url('https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 150px 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section:before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(to bottom, transparent, #f8fafc);
        }
        
        /* Floating Elements */
        .floating-element {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            animation: floatAround 20s linear infinite;
        }
        
        @keyframes floatAround {
            0% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(50px, 50px) rotate(90deg); }
            50% { transform: translate(0, 100px) rotate(180deg); }
            75% { transform: translate(-50px, 50px) rotate(270deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }
        
        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 700;
            line-height: 1.2;
        }
        
        .display-4 {
            font-size: 3.5rem;
            font-weight: 800;
        }
        
        .lead {
            font-size: 1.25rem;
            font-weight: 400;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .display-4 {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-handshake me-2"></i>Konekin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">Cara Kerja</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Keunggulan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#interactive">Interaksi</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">Tentang</a></li>
                </ul>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="user-menu d-flex align-items-center gap-3">
                        <span class="user-welcome fw-medium">Halo, <?php echo $_SESSION['full_name']; ?>!</span>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <div class="d-flex gap-2">
                        <a href="login.php" class="btn btn-outline-primary px-4">Log in</a>
                        <a href="register-choice.php" class="btn btn-primary px-4">Sign Up</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Bangun Bisnis Digital Anda Bersama Talent Terbaik</h1>
                    <p class="lead mb-5">Platform penghubung UMKM dengan creative worker untuk transformasi digital dan perluasan lapangan kerja.</p>
                    
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="row g-3 mb-5">
                            <div class="col-md-6">
                                <a href="register-choice.php?type=umkm" class="btn btn-light btn-lg w-100 py-3 fw-bold d-flex align-items-center justify-content-center">
                                    <i class="fas fa-store me-3"></i>Mulai sebagai UMKM
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="register-choice.php?type=creative" class="btn btn-outline-light btn-lg w-100 py-3 fw-bold d-flex align-items-center justify-content-center">
                                    <i class="fas fa-palette me-3"></i>Jadi Creative Worker
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-light btn-lg px-5 py-3 fw-bold">
                            <i class="fas fa-rocket me-2"></i>Lanjutkan ke Dashboard
                        </a>
                    <?php endif; ?>
                    
                    <div class="d-flex align-items-center gap-4 mt-4">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-check-circle text-success fa-lg"></i>
                            </div>
                            <div>
                                <p class="mb-0 fw-medium">100% Payment Protection</p>
                                <small class="text-light">Transaksi aman terjamin</small>
                            </div>
                        </div>
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-check-circle text-success fa-lg"></i>
                            </div>
                            <div>
                                <p class="mb-0 fw-medium">Quality Guarantee</p>
                                <small class="text-light">Hasil kerja berkualitas</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative">
                        <div class="floating-element" style="width: 100px; height: 100px; background: var(--primary-light); top: -20px; left: 50px;"></div>
                        <div class="floating-element" style="width: 150px; height: 150px; background: var(--accent-color); bottom: 50px; right: 30px; animation-delay: -5s;"></div>
                        <div class="floating-element" style="width: 80px; height: 80px; background: var(--secondary-color); top: 100px; right: 100px; animation-delay: -10s;"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="interactive" class="interactive-section">
        <div class="container">
            <div class="text-center mb-5">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill mb-3">Workflow</span>
                <h2 class="fw-bold mb-3">Kolaborasi yang Seamless</h2>
                <p class="lead text-muted">Dari diskusi hingga transaksi, semua terjadi dalam satu platform.</p>
            </div>
            
            <div class="interaction-container">
                <svg class="connector-svg" viewBox="0 0 1000 500" preserveAspectRatio="none">
                    <path class="connector-path" d="M 100,100 Q 250,100 300,150 T 500,250" />
                    <path class="connector-path" d="M 900,100 Q 750,100 700,150 T 500,250" />
                    <path class="connector-path" d="M 500,250 L 500,400" />
                </svg>

                <div class="character-wrapper pos-umkm">
                    <div class="character-avatar" style="background: linear-gradient(135deg, #FF7E5F, #feb47b);">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="character-label">UMKM Owner</div>
                </div>

                <div class="chat-bubble bubble-1">
                    <p class="mb-0 fw-medium">"Halo, saya butuh redesign logo untuk brand kopi saya agar lebih kekinian."</p>
                    <small><i class="fas fa-paper-plane me-1"></i> Request Project</small>
                </div>

                <div class="character-wrapper pos-creative">
                    <div class="character-avatar" style="background: linear-gradient(135deg, #4ECDC4, #556270);">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <div class="character-label">Creative Worker</div>
                </div>

                <div class="chat-bubble bubble-2">
                    <p class="mb-0 fw-medium">"Siap! Saya punya pengalaman di F&B. Saya kirimkan portofolio dan penawarannya ya."</p>
                    <small><i class="fas fa-reply me-1"></i> Proposal Sent</small>
                </div>

                <div class="chat-bubble bubble-3">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                        <i class="fas fa-handshake text-success fa-2x"></i>
                        <span class="h5 mb-0 fw-bold text-dark">DEAL!</span>
                    </div>
                    <p class="mb-0 text-muted">Project dimulai dengan sistem Escrow.</p>
                </div>

                <div class="character-wrapper pos-deal">
                    <div class="character-avatar" style="background: linear-gradient(135deg, #48BB78, #38A169); width: 80px; height: 80px; font-size: 30px;">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="character-label">Project Start</div>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-lg-8 mx-auto text-center">
                    <p class="text-muted mb-4">Fitur chat terintegrasi memudahkan negosiasi harga dan revisi tanpa harus pindah aplikasi.</p>
                    <a href="register-choice.php" class="btn btn-primary btn-lg px-5 rounded-pill shadow-sm">
                        Mulai Kolaborasi Sekarang
                    </a>
                </div>
            </div>
        </div>
    </section>
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="mb-3">Keunggulan Platform Konekin</h2>
                <p class="lead text-muted">Solusi lengkap untuk digitalisasi bisnis UMKM</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card bg-white shadow-sm p-4">
                        <div class="feature-icon-wrapper" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));">
                            <i class="fas fa-robot fa-2x text-white"></i>
                        </div>
                        <h5 class="fw-bold mb-3">AI Matching System</h5>
                        <p class="text-muted">Sistem kecerdasan buatan yang mencocokkan UMKM dengan creative worker paling sesuai berdasarkan skill dan kebutuhan.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card bg-white shadow-sm p-4">
                        <div class="feature-icon-wrapper" style="background: linear-gradient(135deg, var(--secondary-color), #E86A4A);">
                            <i class="fas fa-comments-dollar fa-2x text-white"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Smart Negotiation</h5>
                        <p class="text-muted">Fitur negosiasi terintegrasi dengan rekomendasi harga berdasarkan kompleksitas project dan tingkat skill.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card bg-white shadow-sm p-4">
                        <div class="feature-icon-wrapper" style="background: linear-gradient(135deg, var(--accent-color), #3ABAB3);">
                            <i class="fas fa-shield-check fa-2x text-white"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Escrow Payment</h5>
                        <p class="text-muted">Sistem pembayaran aman dengan escrow. Dana hanya diteruskan setelah pekerjaan disetujui.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card bg-white shadow-sm p-4">
                        <div class="feature-icon-wrapper" style="background: linear-gradient(135deg, var(--success-color), #38A169);">
                            <i class="fas fa-chart-line fa-2x text-white"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Progress Tracking</h5>
                        <p class="text-muted">Pantau perkembangan project secara real-time dengan milestone tracking dan notifikasi otomatis.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="stats" class="py-5 bg-white">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="counter" data-count="500">500+</div>
                        <h6>UMKM Terdaftar</h6>
                        <p class="text-muted mb-0">Dari berbagai sektor bisnis</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="counter" data-count="800">800+</div>
                        <h6>Creative Worker</h6>
                        <p class="text-muted mb-0">Talent berpengalaman</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="counter" data-count="700">700+</div>
                        <h6>Project Selesai</h6>
                        <p class="text-muted mb-0">Kolaborasi sukses</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="counter" data-count="88">88%</div>
                        <h6>Kepuasan Klien</h6>
                        <p class="text-muted mb-0">Rating positif</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="py-5" style="background: linear-gradient(135deg, rgba(78, 205, 196, 0.1), rgba(62, 127, 213, 0.1));">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="mb-3">Cara Kerja yang Sederhana</h2>
                <p class="lead text-muted">Hanya 3 langkah untuk memulai kolaborasi</p>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="text-center px-4">
                        <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                            <span class="h2 text-white mb-0">1</span>
                        </div>
                        <h5 class="fw-bold mb-3">Daftar & Buat Profil</h5>
                        <p class="text-muted">Buat akun sebagai UMKM atau Creative Worker. Lengkapi profil untuk mendapatkan rekomendasi terbaik.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="text-center px-4">
                        <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                            <span class="h2 text-white mb-0">2</span>
                        </div>
                        <h5 class="fw-bold mb-3">Temukan & Hubungi</h5>
                        <p class="text-muted">Cari partner yang sesuai, diskusikan project melalui chat, dan sepakati scope kerja.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="text-center px-4">
                        <div class="rounded-circle bg-accent d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px; background-color: var(--accent-color);">
                            <span class="h2 text-white mb-0">3</span>
                        </div>
                        <h5 class="fw-bold mb-3">Kolaborasi & Bayar</h5>
                        <p class="text-muted">Mulai bekerja sama, pantau progress, dan lakukan pembayaran aman setelah project selesai.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section py-5 bg-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="mb-3">Siap Transformasi Bisnis Anda?</h2>
                    <p class="lead mb-0">Bergabunglah dengan komunitas UMKM dan creative worker yang sudah berkembang bersama kami.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="register-choice.php" class="btn btn-light btn-lg px-5">
                            <i class="fas fa-rocket me-2"></i>Daftar Sekarang
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-light btn-lg px-5">
                            <i class="fas fa-plus-circle me-2"></i>Buat Project Baru
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer-section text-white py-5" style="background: linear-gradient(to right, var(--neutral-dark), #1A202C);">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-3"><i class="fas fa-handshake me-2"></i>Konekin</h5>
                    <p>Platform penghubung UMKM dengan creative worker untuk transformasi digital dan perluasan lapangan kerja di Indonesia.</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h6 class="mb-3">Perusahaan</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#about" class="text-white-50 text-decoration-none">Tentang Kami</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Karir</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Blog</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Press Kit</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h6 class="mb-3">Dukungan</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Pusat Bantuan</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Keamanan</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">FAQ</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Kontak</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4 mb-4">
                    <h6 class="mb-3">Hubungi Kami</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i>hello@konekin.id</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i>(021) 1234-5678</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i>Jember, Jawa Timur</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-white-20">
            <div class="text-center pt-3">
                <p class="mb-0">&copy; 2025 Konekin. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Counter Animation
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('.counter');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                let current = 0;
                const increment = target / 100;
                
                const updateCounter = () => {
                    if (current < target) {
                        current += increment;
                        counter.textContent = Math.ceil(current) + (counter.textContent.includes('+') ? '+' : 
                                               counter.textContent.includes('%') ? '%' : '');
                        setTimeout(updateCounter, 20);
                    }
                };
                
                // Start counter when in viewport
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            updateCounter();
                            observer.unobserve(entry.target);
                        }
                    });
                });
                
                observer.observe(counter);
            });

            // Smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 70,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>