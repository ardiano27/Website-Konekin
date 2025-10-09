<?php
include "config/Database.php";
session_start();


if (!isset($_GET['type']) || !in_array($_GET['type'], ['umkm', 'creative'])) {
    header("Location: register-choice.php");
    exit;
}

$user_type = $_GET['type'];

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = htmlspecialchars($_POST['full_name']);
    $email = htmlspecialchars($_POST['email']);
    $phone = htmlspecialchars($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = "Password dan konfirmasi password tidak cocok";
    } elseif (strlen($password) < 6) {
        $error = "Password harus minimal 6 karakter";
    } else {
        $uuid = generateUUID();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $sql = "INSERT INTO users (uuid, email, password_hash, user_type, full_name, phone) 
                    VALUES (:uuid, :email, :password_hash, :user_type, :full_name, :phone)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':uuid', $uuid);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':user_type', $user_type);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':phone', $phone);
            
            if ($stmt->execute()) {
                $user_id = $conn->lastInsertId();

                if ($user_type === 'umkm') {
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
                    $tagline = htmlspecialchars($_POST['tagline']);
                    $experience_level = $_POST['experience_level'];
                    
                    $profile_sql = "INSERT INTO creative_profiles (user_id, tagline, experience_level) 
                                   VALUES (:user_id, :tagline, :experience_level)";
                    $profile_stmt = $conn->prepare($profile_sql);
                    $profile_stmt->bindParam(':user_id', $user_id);
                    $profile_stmt->bindParam(':tagline', $tagline);
                    $profile_stmt->bindParam(':experience_level', $experience_level);
                    $profile_stmt->execute();
                }
                
                header("Location: login.php?status=registered&type=" . $user_type);
                exit;
            }
        } catch(PDOException $e) {
            $error = "Gagal mendaftar: " . $e->getMessage();
        }
    }
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2596be;
            --primary-dark: #1e7a9c;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .navbar-brand {
            font-weight: bold;
            color: var(--primary-color) !important;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">Konekin</a>
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
            <div class="col-md-6">
                <div class="text-center mb-4">
                    <h1 class="h3 fw-bold text-primary">Sign up to <?php echo $user_type === 'umkm' ? 'hire talent' : 'find projects'; ?></h1>
                    <p class="text-muted">Daftar sebagai <?php echo $user_type === 'umkm' ? 'UMKM/Bisnis' : 'Creative Worker'; ?></p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <!-- Informasi Dasar -->
                    <div class="form-section">
                        <h5 class="mb-3">Informasi Dasar</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Nama Lengkap *</label>
                                <input type="text" name="full_name" id="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Nomor Telepon *</label>
                                <input type="tel" name="phone" id="phone" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                    </div>

                    <!-- Informasi Spesifik -->
                    <div class="form-section">
                        <h5 class="mb-3">Informasi <?php echo $user_type === 'umkm' ? 'Bisnis' : 'Profesi'; ?></h5>
                        
                        <?php if ($user_type === 'umkm'): ?>
                            <div class="mb-3">
                                <label for="business_name" class="form-label">Nama Bisnis *</label>
                                <input type="text" name="business_name" id="business_name" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="business_type" class="form-label">Jenis Bisnis *</label>
                                <select name="business_type" id="business_type" class="form-select" required>
                                    <option value="">Pilih Jenis Bisnis</option>
                                    <option value="food">Makanan & Minuman</option>
                                    <option value="fashion">Fashion</option>
                                    <option value="craft">Kerajinan</option>
                                    <option value="service">Jasa</option>
                                    <option value="retail">Retail</option>
                                    <option value="other">Lainnya</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label for="tagline" class="form-label">Tagline Profesi</label>
                                <input type="text" name="tagline" id="tagline" class="form-control" placeholder="Contoh: Graphic Designer Specialist">
                            </div>
                            
                            <div class="mb-3">
                                <label for="experience_level" class="form-label">Tingkat Pengalaman *</label>
                                <select name="experience_level" id="experience_level" class="form-select" required>
                                    <option value="">Pilih Tingkat Pengalaman</option>
                                    <option value="beginner">Pemula (0-2 tahun)</option>
                                    <option value="intermediate">Menengah (2-5 tahun)</option>
                                    <option value="expert">Expert (5+ tahun)</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Password -->
                    <div class="form-section">
                        <h5 class="mb-3">Keamanan</h5>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" name="password" id="password" class="form-control" 
                                   placeholder="Minimal 6 karakter" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Konfirmasi Password *</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="agree_terms" required>
                            <label class="form-check-label" for="agree_terms">
                                Ya, saya memahami dan menyetujui 
                                <a href="#" class="text-primary">Syarat & Ketentuan</a> 
                                dan 
                                <a href="#" class="text-primary">Kebijakan Privasi</a> Konekin
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 btn-lg">Buat Akun</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <p>Sudah punya akun? <a href="login.php" class="text-primary">Log in</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>