<?php
include "config/Database.php";
session_start();

// Validasi Tipe Akun
if (!isset($_GET['type']) || !in_array($_GET['type'], ['umkm', 'creative'])) {
    header("Location: register-choice.php");
    exit;
}

$user_type = $_GET['type'];
$page_title = ($user_type === 'umkm') ? 'Registrasi UMKM' : 'Registrasi Creative';

// Inisialisasi Database Connection
$database = new DatabaseConnection();
$conn = $database->getConnection();

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize dan ambil data POST
    $full_name = htmlspecialchars($_POST['full_name']);
    $email = htmlspecialchars($_POST['email']);
    $phone = htmlspecialchars($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi Sederhana
    if ($password !== $confirm_password) {
        $error = "Password dan konfirmasi password tidak cocok.";
    } elseif (strlen($password) < 6) {
        $error = "Password harus minimal 6 karakter.";
    } else {
        // Cek Email Unik
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt_check->bindParam(':email', $email);
        $stmt_check->execute();
        
        if ($stmt_check->rowCount() > 0) {
            $error = "Email sudah terdaftar. Silakan gunakan email lain.";
        } else {
            $uuid = generateUUID(); // Memanggil fungsi yang diperbaiki
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                // *** 1. MULAI TRANSAKSI ***
                $conn->beginTransaction();

                // 2. Insert User Utama
                $sql = "INSERT INTO users (uuid, email, password_hash, user_type, full_name, phone) 
                        VALUES (:uuid, :email, :password_hash, :user_type, :full_name, :phone)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':uuid', $uuid);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password_hash', $password_hash);
                $stmt->bindParam(':user_type', $user_type);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':phone', $phone);
                $stmt->execute();
                
                // Mendapatkan ID user yang baru di-insert
                $user_id = $conn->lastInsertId();

                // 3. Insert Profil Spesifik
                if ($user_type === 'umkm') {
                    // Logika UMKM
                    $business_name = htmlspecialchars($_POST['business_name']);
                    $business_type = $_POST['business_type'];
                    
                    $profile_sql = "INSERT INTO umkm_profiles (user_id, business_name, business_type) 
                                   VALUES (:user_id, :business_name, :business_type)";
                    $profile_stmt = $conn->prepare($profile_sql);
                    $profile_stmt->bindParam(':user_id', $user_id);
                    $profile_stmt->bindParam(':business_name', $business_name);
                    $profile_stmt->bindParam(':business_type', $business_type);
                    $profile_stmt->execute();

                } else {
                    // Logika Creative Worker (dengan data Bank)
                    $tagline = htmlspecialchars($_POST['tagline']);
                    $experience_level = $_POST['experience_level'];
                    $bank_name = htmlspecialchars($_POST['bank_name']);
                    $bank_account_number = htmlspecialchars($_POST['bank_account_number']);
                    
                    $profile_sql = "INSERT INTO creative_profiles (user_id, tagline, experience_level, bank_name, bank_account_number) 
                                   VALUES (:user_id, :tagline, :experience_level, :bank_name, :bank_account_number)";
                    
                    $profile_stmt = $conn->prepare($profile_sql);
                    $profile_stmt->bindParam(':user_id', $user_id);
                    $profile_stmt->bindParam(':tagline', $tagline);
                    $profile_stmt->bindParam(':experience_level', $experience_level);
                    $profile_stmt->bindParam(':bank_name', $bank_name);
                    $profile_stmt->bindParam(':bank_account_number', $bank_account_number);
                    $profile_stmt->execute();
                }
                
                // *** 4. COMMIT: Selesaikan transaksi jika semua berhasil ***
                $conn->commit();
                
                header("Location: login.php?status=registered&type=" . $user_type);
                exit;

            } catch(PDOException $e) {
                // ROLLBACK: Batalkan jika ada error
                $conn->rollBack();
                $error = "Terjadi kesalahan sistem: " . $e->getMessage();
            }
        }
    }
}

// =================================================================
// *** FUNGSI INI SUDAH DIPERBAIKI (KUNCI SOLUSI ERROR ARGUMENT) ***
// =================================================================
function generateUUID() {
    // String format ini HANYA memiliki 8 penanda (%04x)
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, // Penanda Versi 4
        mt_rand(0, 0x3fff) | 0x8000, // Penanda Variasi (10xx)
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    // Total: 8 penanda format dan 8 nilai yang diberikan. TIDAK AKAN ERROR.
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Konekin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563EB;
            --primary-dark: #1E40AF;
            --secondary-color: #64748B;
            --bg-color: #F8FAFC;
            --card-bg: #FFFFFF;
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
        }

        /* Stepper */
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
        .step.completed {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            cursor: pointer;
        }
        .step.active {
            background: white;
            border-color: var(--primary-color);
            color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
        }
        .step.active .step-label { color: var(--primary-color); }

        /* Form Card */
        .form-card {
            background: var(--card-bg);
            border-radius: 24px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .input-group-text {
            background: #F8FAFC;
            border-color: #E2E8F0;
            border-radius: 12px 0 0 12px;
            color: #64748B;
        }
        .form-control { border-radius: 0 12px 12px 0; }
        
        .section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #94A3B8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
        }
        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E2E8F0;
            margin-left: 1rem;
        }

        .btn-submit {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            border-radius: 50px;
            font-weight: 600;
            width: 100%;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4);
        }
        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-link me-2"></i>Konekin
            </a>
            <div class="ms-auto">
                <a href="login.php" class="btn btn-outline-primary rounded-pill px-4 btn-sm fw-bold">Masuk</a>
            </div>
        </div>
    </nav>

    <div class="container py-5 mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <div class="step-indicator">
                    <div class="step-progress-bar"></div>
                    <a href="register-choice.php" class="text-decoration-none">
                        <div class="step completed">
                            <i class="fas fa-check small"></i>
                            <span class="step-label">Tipe Akun</span>
                        </div>
                    </a>
                    <div class="step active">
                        2
                        <span class="step-label">Data Diri</span>
                    </div>
                    <div class="step">
                        3
                        <span class="step-label">Selesai</span>
                    </div>
                </div>

                <div class="form-card p-4 p-md-5">
                    <div class="text-center mb-4">
                        <?php if($user_type === 'umkm'): ?>
                            <div class="badge bg-primary bg-opacity-10 text-primary mb-2 px-3 py-2 rounded-pill">
                                <i class="fas fa-store me-1"></i> Pendaftaran UMKM
                            </div>
                        <?php else: ?>
                            <div class="badge bg-purple bg-opacity-10 text-purple mb-2 px-3 py-2 rounded-pill" style="color: #8B5CF6; background: rgba(139, 92, 246, 0.1);">
                                <i class="fas fa-paint-brush me-1"></i> Pendaftaran Creative
                            </div>
                        <?php endif; ?>
                        <h2 class="fw-bold">Lengkapi Profil Anda</h2>
                        <p class="text-muted small">Isi data di bawah ini untuk memulai perjalanan Anda di Konekin.</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center rounded-3 mb-4" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div><?= $error; ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        
                        <div class="section-title">Informasi Dasar</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Nama Lengkap</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="full_name" class="form-control" placeholder="Nama sesuai identitas" required value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Nomor WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" name="phone" class="form-control" placeholder="08xxxxxxxxxx" required value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary">Alamat Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="contoh@email.com" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                                </div>
                            </div>
                        </div>

                        <div class="section-title">Detail <?= $user_type === 'umkm' ? 'Bisnis' : 'Profesi' ?></div>
                        
                        <div class="row g-3 mb-4">
                            <?php if ($user_type === 'umkm'): ?>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Nama Bisnis / Toko</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-store"></i></span>
                                        <input type="text" name="business_name" class="form-control" placeholder="Nama brand bisnis" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Kategori Bisnis</label>
                                    <select name="business_type" class="form-select" required>
                                        <option value="" selected disabled>Pilih Kategori...</option>
                                        <option value="food">Makanan & Minuman</option>
                                        <option value="fashion">Fashion & Busana</option>
                                        <option value="craft">Kerajinan Tangan</option>
                                        <option value="service">Jasa / Layanan</option>
                                        <option value="retail">Retail / Toko Kelontong</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Tagline / Profesi Utama</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" name="tagline" class="form-control" placeholder="Cth: Graphic Designer" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Pengalaman</label>
                                    <select name="experience_level" class="form-select" required>
                                        <option value="" selected disabled>Pilih Level...</option>
                                        <option value="beginner">Pemula (0-2 Tahun)</option>
                                        <option value="intermediate">Menengah (2-5 Tahun)</option>
                                        <option value="expert">Ahli (>5 Tahun)</option>
                                    </select>
                                </div>

                                <div class="col-12 mt-4">
                                    <div class="p-3 bg-light rounded-3 border border-light">
                                        <h6 class="fw-bold mb-3 text-secondary"><i class="fas fa-wallet me-2"></i>Informasi Pembayaran (Wajib Diisi)</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-secondary">Nama Bank / E-Wallet</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-university"></i></span>
                                                    <select name="bank_name" class="form-select" required>
                                                        <option value="" selected disabled>Pilih Bank...</option>
                                                        <option value="BCA">BCA</option>
                                                        <option value="BRI">BRI</option>
                                                        <option value="BNI">BNI</option>
                                                        <option value="Mandiri">Mandiri</option>
                                                        <option value="Jago">Bank Jago</option>
                                                        <option value="Jenius">Jenius (BTPN)</option>
                                                        <option value="GoPay">GoPay</option>
                                                        <option value="OVO">OVO</option>
                                                        <option value="Dana">DANA</option>
                                                        <option value="Lainnya">Lainnya</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-secondary">Nomor Rekening</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-credit-card"></i></span>
                                                    <input type="number" name="bank_account_number" class="form-control" placeholder="Contoh: 1234567890" required>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <small class="text-muted fst-italic">*Nomor ini akan digunakan untuk pencairan dana hasil proyek Anda.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="section-title">Keamanan</div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Min. 6 karakter" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePass('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Konfirmasi Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Ulangi password" required>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-5">
                            <button type="submit" class="btn-submit">
                                Buat Akun & Lanjutkan <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                            <a href="register-choice.php" class="btn btn-link text-decoration-none text-muted text-center mt-2">
                                <i class="fas fa-arrow-left me-1"></i> Kembali ke Pilihan Akun
                            </a>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePass(id) {
            const input = document.getElementById(id);
            const icon = event.currentTarget.querySelector('i');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>