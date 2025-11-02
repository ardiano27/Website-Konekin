<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2A5EA8;
        }
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .card-choice {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .card-choice:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        
        .card-choice.selected {
            border-color: var(--primary-color);
            background-color: rgba(37, 150, 190, 0.05);
        }
        .navbar-brand {
            font-weight: bold;
            color: var(--primary-color) !important;
        }
        .choice-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-light">
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
            </div>
        </div>
    </nav>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="text-center mb-5">
                    <h2 class="h3 text-muted">Daftar Sebagai...</h2>
                </div>
                
                <form id="registerChoiceForm" action="register.php" method="GET">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card card-choice h-100" data-type="umkm">
                                <div class="card-body text-center p-4">
                                    <div class="choice-icon">
                                        <i class="fas fa-store"></i>
                                    </div>
                                    <h4 class="card-title">UMKM/Bisnis</h4>
                                    <div class="text-start mt-3">
                                        <p class="mb-2"><i class="fas fa-check text-success me-2"></i>Akses talent kreatif</p>
                                        <p class="mb-2"><i class="fas fa-check text-success me-2"></i>Digitalisasi Bisnis</p>
                                        <p class="mb-0"><i class="fas fa-check text-success me-2"></i>Scale up</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card card-choice h-100" data-type="creative">
                                <div class="card-body text-center p-4">
                                    <div class="choice-icon">
                                        <i class="fas fa-palette"></i>
                                    </div>
                                    <h4 class="card-title">Creative Worker</h4>
                                    <div class="text-start mt-3">
                                        <p class="mb-2"><i class="fas fa-check text-success me-2"></i>Temukan project</p>
                                        <p class="mb-2"><i class="fas fa-check text-success me-2"></i>Bangun portfolio</p>
                                        <p class="mb-0"><i class="fas fa-check text-success me-2"></i>Earn money</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="type" id="selectedType">

                    <div class="text-center mt-4">
                        <button type="submit" id="continueBtn" class="btn btn-primary btn-lg px-5" disabled>
                            Lanjutkan Pendaftaran
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <p>Sudah punya akun? <a href="login.php" class="text-primary">Log in</a></p>
                </div>  
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card-choice');
            const selectedTypeInput = document.getElementById('selectedType');
            const continueBtn = document.getElementById('continueBtn');
            
            cards.forEach(card => {
                card.addEventListener('click', function() {
                    cards.forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    const type = this.getAttribute('data-type');
                    selectedTypeInput.value = type;
                    continueBtn.disabled = false;
                    
                    if (type === 'umkm') {
                        continueBtn.innerHTML = 'Daftar sebagai UMKM';
                    } else {
                        continueBtn.innerHTML = 'Daftar sebagai Creative Worker';
                    }
                });
            });
        });
    </script>
</body>
</html>