<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Peran - Konekin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Professional Palette */
            --primary-color: #2563EB; /* Royal Blue */
            --primary-dark: #1E40AF;
            --secondary-color: #64748B;
            --bg-color: #F8FAFC;
            --card-bg: #FFFFFF;
            
            /* Gradients for Identity */
            --umkm-gradient: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            --creative-gradient: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%);
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.05) 0px, transparent 50%);
            min-height: 100vh;
            color: #1E293B;
        }

        /* Navbar Styling */
        .navbar {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 800;
            color: var(--primary-color) !important;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        /* Step Wizard */
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 3rem;
            position: relative;
            z-index: 1;
        }
        
        .step-progress-bar {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 3px;
            background: #E2E8F0;
            z-index: -1;
        }

        .step {
            background: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #94A3B8;
            border: 2px solid #E2E8F0;
            margin: 0 35px;
            position: relative;
            transition: all 0.3s ease;
        }

        .step.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
        }

        .step-label {
            position: absolute;
            top: 50px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
            color: #64748B;
        }
        
        .step.active .step-label {
            color: var(--primary-color);
        }

        /* Selection Cards */
        .choice-card {
            background: var(--card-bg);
            border-radius: 24px;
            border: 2px solid transparent;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            height: 100%;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        .choice-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Image Handling */
        .card-img-wrapper {
            height: 220px;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .card-img-wrapper::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 50%;
            background: linear-gradient(to top, rgba(255,255,255,1), transparent);
        }

        .choice-image {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Kunci agar gambar rapi mengisi area */
            transition: transform 0.5s ease;
        }

        .choice-card:hover .choice-image {
            transform: scale(1.05);
        }

        /* Icon Styling */
        .choice-icon-wrapper {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
            margin: -32px auto 1rem;
            position: relative;
            z-index: 2;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        /* Content Styling */
        .card-body {
            padding: 0 2rem 2rem;
            text-align: center;
        }

        .role-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #0F172A;
        }

        .role-desc {
            font-size: 0.9rem;
            color: #64748B;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        /* List Styling */
        .feature-list {
            text-align: left;
            background: #F8FAFC;
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 1rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
            color: #334155;
            font-weight: 500;
        }

        .feature-item:last-child {
            margin-bottom: 0;
        }

        .feature-item i {
            margin-right: 10px;
            font-size: 1rem;
        }

        /* Selected State */
        .check-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 32px;
            height: 32px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 10;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        /* Specific Theme: UMKM */
        .choice-card[data-type="umkm"] .choice-icon-wrapper {
            background: var(--umkm-gradient);
        }
        .choice-card[data-type="umkm"] .feature-item i {
            color: #2563EB;
        }
        .choice-card[data-type="umkm"].selected {
            border-color: #2563EB;
            background: rgba(37, 99, 235, 0.02);
        }

        /* Specific Theme: Creative */
        .choice-card[data-type="creative"] .choice-icon-wrapper {
            background: var(--creative-gradient);
        }
        .choice-card[data-type="creative"] .feature-item i {
            color: #8B5CF6;
        }
        .choice-card[data-type="creative"].selected {
            border-color: #8B5CF6;
            background: rgba(139, 92, 246, 0.02);
        }
        .choice-card[data-type="creative"] .check-indicator {
            background: #8B5CF6;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        /* Active State Universal */
        .choice-card.selected .check-indicator {
            opacity: 1;
            transform: scale(1);
        }

        /* Button Styling */
        .btn-continue {
            background: var(--primary-color);
            color: white;
            padding: 1rem 3rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4);
        }

        .btn-continue:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(37, 99, 235, 0.5);
        }

        .btn-continue:disabled {
            background: #CBD5E1;
            box-shadow: none;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .step-indicator { margin-bottom: 2rem; }
            .step { margin: 0 20px; }
            .step-progress-bar { width: 140px; }
            .card-img-wrapper { height: 160px; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-link me-2"></i>Konekin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto small fw-medium">
                    <li class="nav-item"><a class="nav-link text-secondary" href="#">Cara Kerja</a></li>
                    <li class="nav-item"><a class="nav-link text-secondary" href="#">Keunggulan</a></li>
                    <li class="nav-item"><a class="nav-link text-secondary" href="#">Bantuan</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5 mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <div class="step-indicator">
                    <div class="step-progress-bar"></div>
                    <div class="step active">
                        1
                        <span class="step-label">Tipe Akun</span>
                    </div>
                    <div class="step">
                        2
                        <span class="step-label">Data Diri</span>
                    </div>
                    <div class="step">
                        3
                        <span class="step-label">Selesai</span>
                    </div>
                </div>

                <div class="text-center mb-5">
                    <h2 class="fw-bold mb-3" style="color: #0F172A;">Bergabung sebagai Partner</h2>
                    <p class="text-muted" style="max-width: 600px; margin: 0 auto;">
                        Pilih peran yang paling sesuai dengan kebutuhan Anda. Akses fitur yang dipersonalisasi untuk mendukung kesuksesan proyek Anda.
                    </p>
                </div>

                <form id="registerChoiceForm" action="register.php" method="GET">
                    <div class="row g-4 justify-content-center">
                        
                        <div class="col-md-5">
                            <div class="choice-card h-100" data-type="umkm" onclick="selectCard(this, 'umkm')">
                                <div class="check-indicator"><i class="fas fa-check"></i></div>
                                
                                <div class="card-img-wrapper">
                                    <img src="assets/images/umkm.jpg" alt="Pemilik Bisnis UMKM" class="choice-image">
                                </div>
                                
                                <div class="choice-icon-wrapper">
                                    <i class="fas fa-store"></i>
                                </div>
                                
                                <div class="card-body">
                                    <h3 class="role-title">UMKM / Bisnis</h3>
                                    <p class="role-desc">Saya ingin mencari talenta profesional untuk mengerjakan proyek bisnis.</p>
                                    
                                    <div class="feature-list">
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i> Posting lowongan proyek gratis
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i> Akses ribuan freelancer terverifikasi
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i> Pembayaran aman & transparan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div class="choice-card h-100" data-type="creative" onclick="selectCard(this, 'creative')">
                                <div class="check-indicator"><i class="fas fa-check"></i></div>
                                
                                <div class="card-img-wrapper">
                                    <img src="assets/images/creeative worker le.jpeg" alt="Creative Worker" class="choice-image">
                                </div>
                                
                                <div class="choice-icon-wrapper">
                                    <i class="fas fa-paint-brush"></i>
                                </div>
                                
                                <div class="card-body">
                                    <h3 class="role-title">Creative Worker</h3>
                                    <p class="role-desc">Saya ingin menawarkan jasa keahlian dan mendapatkan penghasilan.</p>
                                    
                                    <div class="feature-list">
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i> Bangun portofolio online profesional
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i> Akses ke berbagai proyek menarik
                                        </div>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i> Perlindungan pembayaran kerja
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <input type="hidden" name="type" id="selectedType">

                    <div class="text-center mt-5">
                        <button type="submit" id="continueBtn" class="btn-continue" disabled>
                            Pilih Tipe Akun Dulu
                        </button>
                        <div class="mt-4">
                            <p class="small text-muted">Sudah punya akun? <a href="login.php" class="text-decoration-none fw-bold" style="color: var(--primary-color);">Masuk disini</a></p>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectCard(element, type) {
            // 1. Reset visual states
            document.querySelectorAll('.choice-card').forEach(card => {
                card.classList.remove('selected');
            });

            // 2. Set active state
            element.classList.add('selected');

            // 3. Update hidden input
            document.getElementById('selectedType').value = type;

            // 4. Enable button with animation and update text
            const btn = document.getElementById('continueBtn');
            btn.disabled = false;
            
            if (type === 'umkm') {
                btn.innerHTML = 'Lanjut sebagai <strong>UMKM</strong> <i class="fas fa-arrow-right ms-2"></i>';
            } else {
                btn.innerHTML = 'Lanjut sebagai <strong>Creative</strong> <i class="fas fa-arrow-right ms-2"></i>';
            }
        }
        
        // Prevent form submission if empty (double check safety)
        document.getElementById('registerChoiceForm').addEventListener('submit', function(e) {
            if (!document.getElementById('selectedType').value) {
                e.preventDefault();
                alert('Mohon pilih salah satu tipe akun untuk melanjutkan.');
            }
        });
    </script>
</body>
</html>