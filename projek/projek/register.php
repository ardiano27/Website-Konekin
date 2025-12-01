<?php
session_start();
require_once 'config/Database.php';
require_once 'models/Users.php';

$message = '';
$message_type = '';

if ($_POST) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    $database = new Database();
    $db = $database->getConnection();
    $userModel = new User($db);
    
    try {
        // Validasi input
        if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
            throw new Exception("Semua field harus diisi!");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email tidak valid!");
        }
        
        if (strlen($password) < 6) {
            throw new Exception("Password minimal 6 karakter!");
        }
        
        if ($password !== $confirm_password) {
            throw new Exception("Password dan konfirmasi password tidak cocok!");
        }
        
        if (!in_array($role, ['umkm', 'creative_worker'])) {
            throw new Exception("Role tidak valid!");
        }
        
        // Cek apakah email sudah terdaftar
        $existing_user = $userModel->getByEmail($email);
        if ($existing_user) {
            throw new Exception("Email sudah terdaftar!");
        }
        
        // Buat user baru
        if ($userModel->create($name, $email, $role, $password)) {
            $message = "Registrasi berhasil! Silakan login.";
            $message_type = "success";
            
            // Reset form
            $_POST = [];
        } else {
            throw new Exception("Gagal melakukan registrasi. Silakan coba lagi.");
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .role-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .role-option:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .role-option.selected {
            border-color: #007bff;
            background-color: #e7f3ff;
        }
        .role-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card register-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2><i class="fas fa-users-cog text-primary me-2"></i>Konekin</h2>
                            <p class="text-muted">Buat akun baru untuk memulai</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="registerForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="form-text">Minimal 6 karakter</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Pilihan Role -->
                            <div class="mb-4">
                                <label class="form-label">Pilih Role <span class="text-danger">*</span></label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="role-option text-center" data-role="umkm">
                                            <div class="role-icon">🏪</div>
                                            <h5>UMKM</h5>
                                            <p class="small text-muted">Pemilik usaha yang membutuhkan jasa creative worker</p>
                                            <ul class="small text-start">
                                                <li>Upload proyek dan kebutuhan</li>
                                                <li>Temukan creative worker terbaik</li>
                                                <li>Kelola proyek Anda</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="role-option text-center" data-role="creative_worker">
                                            <div class="role-icon">🎨</div>
                                            <h5>Creative Worker</h5>
                                            <p class="small text-muted">Penyedia jasa kreatif dan profesional</p>
                                            <ul class="small text-start">
                                                <li>Upload portfolio dan sertifikat</li>
                                                <li>Temukan proyek menarik</li>
                                                <li>Bangun reputasi Anda</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="role" id="selectedRole" required>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agreeTerms" required>
                                <label class="form-check-label" for="agreeTerms">
                                    Saya menyetujui <a href="#" class="text-decoration-none">Syarat dan Ketentuan</a> 
                                    serta <a href="#" class="text-decoration-none">Kebijakan Privasi</a>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                            </button>

                            <div class="text-center mt-3">
                                <p class="text-muted">Sudah punya akun? 
                                    <a href="login.php" class="text-decoration-none">Login di sini</a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle role selection
        document.querySelectorAll('.role-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.role-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Set the hidden input value
                document.getElementById('selectedRole').value = this.getAttribute('data-role');
            });
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const selectedRole = document.getElementById('selectedRole').value;
            
            if (password.length < 6) {
                alert('Password minimal 6 karakter!');
                e.preventDefault();
                return;
            }
            
            if (password !== confirmPassword) {
                alert('Password dan konfirmasi password tidak cocok!');
                e.preventDefault();
                return;
            }
            
            if (!selectedRole) {
                alert('Silakan pilih role!');
                e.preventDefault();
                return;
            }
        });

        // Auto-select role if coming back with form data
        const previousRole = "<?php echo $_POST['role'] ?? ''; ?>";
        if (previousRole) {
            const roleOption = document.querySelector(`.role-option[data-role="${previousRole}"]`);
            if (roleOption) {
                roleOption.classList.add('selected');
                document.getElementById('selectedRole').value = previousRole;
            }
        }
    </script>
</body>
</html>
