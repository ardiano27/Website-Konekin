<?php 
include "config/Database.php";
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30); 
session_start();

$database = new DatabaseConnection();
$conn = $database->getConnection();

// Inisialisasi variabel error
$error = "";

// Proses login
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
            --primary-light: #5C9DFF;
            --primary-dark: #2A5EA8;
            --secondary-color: #6C757D;
            --light-bg: #F8F9FA;
            --light-gray: #E9ECEF;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(62, 127, 213, 0.15);
            --box-shadow-hover: 0 15px 40px rgba(62, 127, 213, 0.25);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F5F9FF;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #333;
        }
        
        .login-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="none"/><circle cx="50" cy="50" r="30" fill="white" opacity="0.05"/></svg>');
            background-size: 300px;
            opacity: 0.5;
        }
        
        .login-left-content {
            position: relative;
            z-index: 1;
            max-width: 480px;
            margin: 0 auto;
        }
        
        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 2.5rem;
        }
        
        .logo-icon {
            width: 48px;
            height: 48px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .welcome-title {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .welcome-subtitle {
            font-size: 1.2rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .features-list {
            list-style: none;
            padding: 0;
            margin-top: 2.5rem;
        }
        
        .features-list li {
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            font-size: 1.05rem;
        }
        
        .features-list i {
            background-color: rgba(255, 255, 255, 0.2);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .login-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-color: white;
        }
        
        .login-form-container {
            max-width: 420px;
            width: 100%;
            padding: 2rem;
        }
        
        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }
        
        .form-subtitle {
            color: var(--secondary-color);
            margin-bottom: 2rem;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #444;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .form-control {
            padding: 0.85rem 1rem 0.85rem 3rem;
            border-radius: var(--border-radius);
            border: 1.5px solid var(--light-gray);
            font-size: 1rem;
            transition: all 0.3s ease;
            height: auto;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(62, 127, 213, 0.25);
        }
        
        .form-control.password {
            padding-right: 3.5rem;
        }
        
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--secondary-color);
            cursor: pointer;
            font-size: 1.2rem;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.8rem;
        }
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            border-radius: 4px;
            border: 2px solid #ced4da;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            font-size: 0.95rem;
            color: #555;
            cursor: pointer;
        }
        
        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .btn-login {
            background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--box-shadow);
            margin-bottom: 1.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-hover);
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 1.8rem 0;
            color: var(--secondary-color);
            font-size: 0.95rem;
        }
        
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background-color: var(--light-gray);
        }
        
        .divider span {
            padding: 0 1rem;
        }
        
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.9rem;
            border-radius: var(--border-radius);
            border: 1.5px solid var(--light-gray);
            background-color: white;
            font-weight: 600;
            color: #444;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 1.8rem;
        }
        
        .btn-google:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
            transform: translateY(-2px);
        }
        
        .btn-google i {
            color: #DB4437;
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .signup-link {
            text-align: center;
            color: var(--secondary-color);
            font-size: 1rem;
        }
        
        .signup-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .signup-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: var(--border-radius);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: none;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.15);
            color: #155724;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.15);
            color: #721c24;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-left {
                padding: 2rem;
            }
            
            .login-left-content {
                max-width: 100%;
            }
            
            .welcome-title {
                font-size: 2.2rem;
            }
            
            .login-form-container {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .login-left, .login-right {
                padding: 1.5rem;
            }
            
            .welcome-title {
                font-size: 1.8rem;
            }
            
            .form-title {
                font-size: 1.7rem;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .forgot-password {
                margin-top: 0.8rem;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-form-container, .login-left-content {
            animation: fadeIn 0.8s ease-out;
        }
        
        /* Interactive effects */
        .input-with-icon:hover i {
            transform: translateY(-50%) scale(1.1);
            transition: transform 0.2s;
        }
        
        .btn-login:active {
            transform: translateY(0);
            transition: transform 0.1s;
        }
        
        .form-check-input {
            transition: all 0.2s;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Panel (Welcome Section) -->
        <div class="login-left">
            <div class="login-left-content">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-link fa-lg"></i>
                    </div>
                    <div class="logo-text">Konekin</div>
                </div>
                
                <h1 class="welcome-title">WELCOME BACK</h1>
                <p class="welcome-subtitle">Login untuk mengakses akun Anda dan melanjutkan perjalanan kolaborasi kreatif bersama UMKM Indonesia.</p>
                
                <ul class="features-list">
                    <li>
                        <i class="fas fa-check"></i>
                        <span>Temukan talenta kreatif terbaik untuk bisnis Anda</span>
                    </li>
                    <li>
                        <i class="fas fa-check"></i>
                        <span>Kolaborasi dengan UMKM seluruh Indonesia</span>
                    </li>
                    <li>
                        <i class="fas fa-check"></i>
                        <span>Kelola proyek dengan sistem kontrak terpercaya</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Right Panel (Login Form) -->
        <div class="login-right">
            <div class="login-form-container">
                <h2 class="form-title">Masuk ke Akun</h2>
                <p class="form-subtitle">Silakan masukkan detail akun Anda untuk melanjutkan</p>
                
                <!-- Messages from PHP -->
                <div id="message-container">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" class="form-control password" placeholder="********" required>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rememberMe">
                            <label class="form-check-label" for="rememberMe">Remember me</label>
                        </div>
                        <a href="#" class="forgot-password">Forgot password?</a>
                    </div>
                    
                    <button type="submit" name="login" class="btn-login">Sign in</button>
                </form>
                
                <div class="divider">
                    <span>Or continue with</span>
                </div>
                
                <button type="button" class="btn-google">
                    <i class="fab fa-google"></i>
                    <span>Sign in with Google</span>
                </button>
                
                <p class="signup-link">
                    Don't have an account?
                    <a href="register-choice.php">Sign up to free!</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icon
            const eyeIcon = this.querySelector('i');
            if (type === 'password') {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        });
        
        // Form validation and interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add interactive effects to form inputs
            const formInputs = document.querySelectorAll('.form-control');
            formInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('i').style.color = 'var(--primary-dark)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.querySelector('i').style.color = 'var(--primary-color)';
                });
            });
            
            // Remember me functionality
            const rememberMeCheckbox = document.getElementById('rememberMe');
            const emailInput = document.getElementById('email');
            
            // Check if there's saved email in localStorage
            const savedEmail = localStorage.getItem('rememberedEmail');
            if (savedEmail) {
                emailInput.value = savedEmail;
                rememberMeCheckbox.checked = true;
            }
            
            // Save email when form is submitted if "Remember me" is checked
            const loginForm = document.getElementById('loginForm');
            loginForm.addEventListener('submit', function() {
                if (rememberMeCheckbox.checked) {
                    localStorage.setItem('rememberedEmail', emailInput.value);
                } else {
                    localStorage.removeItem('rememberedEmail');
                }
            });
            
            // Auto-hide success message after 5 seconds
            const successMessage = document.querySelector('.alert-success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.transition = 'opacity 0.5s ease';
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            
            // Forgot password button functionality
            const forgotPasswordLink = document.querySelector('.forgot-password');
            forgotPasswordLink.addEventListener('click', function(e) {
                e.preventDefault();
                const email = emailInput.value;
                if (email) {
                    alert('Link reset password akan dikirim ke: ' + email);
                    // In a real application, you would make an AJAX request here
                } else {
                    alert('Silakan masukkan email Anda terlebih dahulu');
                    emailInput.focus();
                }
            });
            
            // Google sign-in button functionality
            const googleSignInBtn = document.querySelector('.btn-google');
            googleSignInBtn.addEventListener('click', function() {
                alert('Fitur login dengan Google akan segera tersedia!');
                // In a real application, you would implement OAuth2 flow here
            });
        });
    </script>
</body>
</html>