<?php 
include "config/Database.php";
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30); 
session_start();

$database = new DatabaseConnection();
$conn = $database->getConnection();

if(isset($_POST["login"])){
    $email = $_POST["email"];
    $password = $_POST["password"];

    try {
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0){
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $user['password_hash'])){
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_uuid'] = $user['uuid'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                
                // Redirect berdasarkan user_type
                if($user['user_type'] === 'umkm'){
                    header("Location: dashboard-umkm.php");
                } else if($user['user_type'] === 'admin') {
                    header("Location: dashboard-admin.php");
                } else {
                    header("Location: dashboard-creative.php");
                }
                exit;
            } else {
                $error = "Email atau password salah";
            }
        } else {
            $error = "Email atau password salah";
        }
    } catch(PDOException $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Tampilkan pesan sukses registrasi
$success_message = "";
if(isset($_GET['status']) && $_GET['status'] == 'registered'){
    $user_type = isset($_GET['type']) ? $_GET['type'] : 'user';
    $success_message = "Pendaftaran berhasil! Silakan login dengan akun Anda.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Konekin</title>
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
        
        .login-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            
            <div class="col-md-5">
                
                <div class="text-center mb-4">
                    <h1 class="h2 fw-bold text-primary">Konekin</h1>
                    <p class="text-muted">Masuk ke akun Anda</p>
                </div>

                <div class="login-card">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?= $success_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="login" class="btn btn-primary w-100 btn-lg">Masuk Sekarang</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="#" class="text-primary">Lupa password?</a>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <p>Belum punya akun? <a href="register-choice.php" class="text-primary">Daftar sekarang</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>