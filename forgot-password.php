<?php 
include "config/Database.php";
session_start();

$database = new DatabaseConnection();
$conn = $database->getConnection();

$step = isset($_GET['step']) ? $_GET['step'] : 1;
$error = "";
$success = "";

// Step 1: Verifikasi Email dan Nomor Telepon
if(isset($_POST["verify_user"])){
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    
    try {
        $sql = "SELECT id, full_name FROM users WHERE email = :email AND phone = :phone";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->execute();
        
        if($stmt->rowCount() > 0){
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate OTP (6 digit)
            $otp = rand(100000, 999999);
            
            // Simpan OTP ke session dengan waktu expired
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_email'] = $email;
            $_SESSION['otp_expired'] = time() + (5 * 60); // 5 menit
            
            // Di sini normalnya kirim OTP via SMS/WhatsApp
            // Untuk demo, kita tampilkan di halaman
            $_SESSION['demo_otp'] = $otp; // Untuk demo saja
            
            header("Location: forgot-password.php?step=2");
            exit;
        } else {
            $error = "Email atau nomor telepon tidak ditemukan";
        }
    } catch(PDOException $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Step 2: Verifikasi OTP
if(isset($_POST["verify_otp"])){
    $otp = trim($_POST["otp"]);
    
    if(!isset($_SESSION['reset_otp']) || !isset($_SESSION['otp_expired'])){
        $error = "Sesi telah berakhir. Silakan ulangi dari awal.";
        $step = 1;
    } else if(time() > $_SESSION['otp_expired']){
        $error = "Kode OTP telah kedaluwarsa. Silakan minta kode baru.";
        unset($_SESSION['reset_otp']);
        unset($_SESSION['otp_expired']);
        $step = 1;
    } else if($otp != $_SESSION['reset_otp']){
        $error = "Kode OTP tidak valid";
    } else {
        // OTP benar, lanjut ke step 3
        $_SESSION['otp_verified'] = true;
        header("Location: forgot-password.php?step=3");
        exit;
    }
}

// Step 3: Reset Password
if(isset($_POST["reset_password"])){
    if(!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']){
        $error = "Verifikasi tidak valid. Silakan ulangi dari awal.";
        $step = 1;
    } else {
        $new_password = $_POST["new_password"];
        $confirm_password = $_POST["confirm_password"];
        
        if($new_password !== $confirm_password){
            $error = "Password tidak cocok";
        } else if(strlen($new_password) < 6){
            $error = "Password minimal 6 karakter";
        } else {
            try {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $sql = "UPDATE users SET password_hash = :password_hash WHERE id = :user_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':password_hash', $password_hash);
                $stmt->bindParam(':user_id', $_SESSION['reset_user_id']);
                $stmt->execute();
                
                // Bersihkan session
                unset($_SESSION['reset_otp']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['otp_expired']);
                unset($_SESSION['otp_verified']);
                unset($_SESSION['demo_otp']);
                
                header("Location: login.php?status=password_reset");
                exit;
            } catch(PDOException $e) {
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }
}

// Resend OTP
if(isset($_POST["resend_otp"])){
    if(isset($_SESSION['reset_email'])){
        $otp = rand(100000, 999999);
        $_SESSION['reset_otp'] = $otp;
        $_SESSION['otp_expired'] = time() + (5 * 60);
        $_SESSION['demo_otp'] = $otp;
        $success = "Kode OTP baru telah dikirim";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Konekin</title>
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
            background-color: #f8f9fa;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .reset-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: bold;
            color: var(--primary-color) !important;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            z-index: 0;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #6c757d;
            position: relative;
            z-index: 1;
        }
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .otp-input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 5px;
        }
        .demo-otp-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .text-primary {
            color: var(--primary-color) !important;
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
                        <a class="nav-link" href="index.php#how-it-works">Cara Kerja</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#features">Keunggulan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#about">Tentang Kami</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="text-center mb-4">
                    <h1 class="h2 fw-bold text-primary">Reset Password</h1>
                    <p class="text-muted">Ikuti langkah-langkah berikut untuk mereset password Anda</p>
                </div>

                <div class="reset-card">
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                            1
                        </div>
                        <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                            2
                        </div>
                        <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                            3
                        </div>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?= $success; ?>
                        </div>
                    <?php endif; ?>

                    <?php if($step == 1): ?>
                        <!-- Step 1: Verifikasi Email dan Nomor Telepon -->
                        <h5 class="mb-3">Verifikasi Akun</h5>
                        <p class="text-muted small">Masukkan email dan nomor telepon yang terdaftar</p>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email
                                </label>
                                <input type="email" name="email" id="email" class="form-control" required 
                                       placeholder="contoh@email.com">
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">
                                    <i class="fas fa-phone me-2"></i>Nomor Telepon
                                </label>
                                <input type="tel" name="phone" id="phone" class="form-control" required 
                                       placeholder="08xxxxxxxxxx" pattern="[0-9]{10,13}">
                                <small class="text-muted">Format: 08xxxxxxxxxx</small>
                            </div>
                            
                            <button type="submit" name="verify_user" class="btn btn-primary w-100 btn-lg">
                                <i class="fas fa-arrow-right me-2"></i>Lanjutkan
                            </button>
                        </form>

                    <?php elseif($step == 2): ?>
                        <!-- Step 2: Verifikasi OTP -->
                        <h5 class="mb-3">Verifikasi Kode OTP</h5>
                        <p class="text-muted small">Kode OTP telah dikirim ke nomor telepon Anda</p>
                        
                        <?php if(isset($_SESSION['demo_otp'])): ?>
                            <div class="demo-otp-box">
                                <strong><i class="fas fa-info-circle me-2"></i>Demo Mode:</strong> 
                                Kode OTP Anda: <strong><?= $_SESSION['demo_otp']; ?></strong>
                                <br><small class="text-muted">Dalam mode produksi, kode akan dikirim via SMS</small>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="otp" class="form-label text-center d-block">
                                    <i class="fas fa-key me-2"></i>Masukkan Kode OTP (6 digit)
                                </label>
                                <div class="d-flex justify-content-center">
                                    <input type="text" name="otp" id="otp" class="form-control text-center" 
                                           required pattern="[0-9]{6}" maxlength="6" 
                                           style="max-width: 200px; font-size: 1.5rem; letter-spacing: 10px;"
                                           placeholder="000000">
                                </div>
                                <small class="text-muted d-block text-center mt-2">
                                    Kode berlaku selama 5 menit
                                </small>
                            </div>
                            
                            <button type="submit" name="verify_otp" class="btn btn-primary w-100 btn-lg">
                                <i class="fas fa-check me-2"></i>Verifikasi
                            </button>
                        </form>

                        <form method="POST" action="" class="mt-3">
                            <div class="text-center">
                                <button type="submit" name="resend_otp" class="btn btn-link text-primary">
                                    <i class="fas fa-redo me-2"></i>Kirim Ulang Kode
                                </button>
                            </div>
                        </form>

                    <?php elseif($step == 3): ?>
                        <!-- Step 3: Reset Password -->
                        <h5 class="mb-3">Buat Password Baru</h5>
                        <p class="text-muted small">Masukkan password baru untuk akun Anda</p>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password Baru
                                </label>
                                <input type="password" name="new_password" id="new_password" 
                                       class="form-control" required minlength="6"
                                       placeholder="Minimal 6 karakter">
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Konfirmasi Password
                                </label>
                                <input type="password" name="confirm_password" id="confirm_password" 
                                       class="form-control" required minlength="6"
                                       placeholder="Masukkan password yang sama">
                            </div>

                            <div class="alert alert-info">
                                <small>
                                    <i class="fas fa-info-circle me-2"></i>
                                    Password minimal 6 karakter dan harus sama dengan konfirmasi
                                </small>
                            </div>
                            
                            <button type="submit" name="reset_password" class="btn btn-primary w-100 btn-lg">
                                <i class="fas fa-check-circle me-2"></i>Reset Password
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-3">
                        <a href="login.php" class="text-primary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali ke Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus OTP input
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otp');
            if(otpInput) {
                otpInput.focus();
                
                // Only allow numbers
                otpInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }

            // Password match validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if(confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    if(this.value !== newPassword.value) {
                        this.setCustomValidity('Password tidak cocok');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });
    </script>
</body>
</html>