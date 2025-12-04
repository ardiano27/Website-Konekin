<?php
require_once "config/Database.php"; 

$database = new DatabaseConnection();
$conn = $database->getConnection();

session_start();

$full_name = 'Pengguna';
$user_avatar = '';

if (isset($_SESSION['user_id'])) {
    $full_name = $_SESSION['full_name'];
    $user_id = $_SESSION['user_id'];

    try {
        // Query untuk mengambil URL avatar dari tabel users
        $sql = "SELECT avatar_url FROM users WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_data && !empty($user_data['avatar_url'])) {
            $user_avatar = $user_data['avatar_url'];
        }
    } catch (PDOException $e) {
        // Handle error database jika diperlukan, tapi jangan tampilkan ke user
        // error_log("Database Error on fetching avatar: " . $e->getMessage());
    }

} elseif (!isset($_POST['confirm_logout'])) {
    // Jika tidak ada sesi dan bukan konfirmasi logout, redirect ke login
    header("Location: login.php");
    exit;
}

// 2. Logika Penghancuran Sesi (Logout)
if (isset($_POST['confirm_logout']) && $_POST['confirm_logout'] === 'true') {
    // Hapus semua data session
    $_SESSION = array();
    
    // Hapus cookie session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Hancurkan session
    session_destroy();
    
    // Redirect ke halaman login dengan status logout
    header("Location: login.php?status=logged_out");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Keluar - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Mengambil warna dari login.php */
            --primary-color: #3E7FD5;
            --primary-dark: #2A5EA8;
            --primary-light: #5C9DFF;
            --danger-color: #DC3545;
            --danger-light: #FFEBEB;
            --danger-border: #FFC9C9;
            --light-bg: #F5F9FF;
            --text-main: #333;
            --text-muted: #6C757D;
            --border-radius: 16px;
            --box-shadow: 0 20px 60px rgba(62, 127, 213, 0.15);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            /* Pattern subtle tetap di body */
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="30" fill="%233E7FD5" opacity="0.03"/></svg>');
            background-size: 100px;
        }
        
        .logout-container {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        /* Header dengan Gradient bersih (tanpa bubble-bubble) */
        .logout-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            padding: 3rem 2rem;
            text-align: center;
            color: white;
            position: relative;
        }

        /* --- Perubahan Utama: Avatar Pengguna --- */
        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            border: 3px solid white; /* Border lebih tebal agar profesional */
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 2;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden; /* Penting untuk menampung gambar */
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Memastikan gambar terisi penuh dan terlihat bagus */
        }

        .icon-default {
            font-size: 2.5rem;
            color: white;
            opacity: 0.8;
        }

        .logout-container:hover .icon-wrapper {
            transform: scale(1.05);
        }
        /* ------------------------------------- */

        .logout-header h2 {
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .user-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            font-weight: 500;
            position: relative;
            z-index: 2;
        }

        .logout-body {
            padding: 2.5rem 2rem;
            text-align: center;
        }

        /* Red Warning Box */
        .warning-box {
            background-color: var(--danger-light);
            border: 1px solid var(--danger-border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .warning-icon {
            color: var(--danger-color);
            font-size: 2rem;
            margin-bottom: 0.5rem;
            animation: pulseWarning 2s infinite;
        }

        .warning-title {
            color: #C02B36; 
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .warning-text {
            color: #9B1C26;
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
        }

        /* Buttons */
        .btn-group-custom {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-custom {
            flex: 1;
            padding: 0.9rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-cancel {
            background-color: white;
            border: 1.5px solid #E0E0E0;
            color: var(--text-muted);
        }

        .btn-cancel:hover {
            background-color: #f8f9fa;
            border-color: #d1d1d1;
            color: var(--text-main);
            transform: translateY(-2px);
        }

        .btn-logout {
            /* Tombol logout menggunakan gradient merah untuk menegaskan bahaya */
            background: linear-gradient(135deg, #FF6B6B, #EE5253);
            color: white;
            box-shadow: 0 4px 15px rgba(238, 82, 83, 0.4);
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #EE5253, #D63031);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(238, 82, 83, 0.6);
        }

        /* Animations */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulseWarning {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.9; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Loading Overlay */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: var(--border-radius);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--light-bg);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 576px) {
            .logout-container {
                width: 90%;
            }
            .btn-group-custom {
                flex-direction: column;
            }
            .logout-header {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>

    <div class="logout-container">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
            <h5 style="color: var(--primary-color); font-weight: 600;">Sedang Keluar...</h5>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Menghapus sesi Anda</p>
        </div>

        <div class="logout-header">
            <div class="icon-wrapper">
                <?php if (!empty($user_avatar)): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="Foto Profil" class="profile-avatar">
                <?php else: ?>
                    <i class="fas fa-user-circle icon-default"></i>
                <?php endif; ?>
            </div>
            
            <h2>Sampai Jumpa Lagi!</h2>
            <div class="user-badge">
                <i class="fas fa-user-tag me-1"></i> 
                <?php echo htmlspecialchars($full_name); ?>
            </div>
        </div>

        <div class="logout-body">
            <div class="warning-box">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h4 class="warning-title">Konfirmasi Keluar</h4>
                <p class="warning-text">
                    Anda akan mengakhiri sesi yang sedang aktif. <br>
                    Apakah Anda yakin ingin logout sekarang?
                </p>
            </div>

            <form method="POST" action="" id="logoutForm">
                <input type="hidden" name="confirm_logout" value="true">
                
                <div class="btn-group-custom">
                    <button type="button" class="btn-custom btn-cancel" onclick="goBack()">
                        <i class="fas fa-arrow-left"></i> Batal
                    </button>
                    
                    <button type="submit" class="btn-custom btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Ya, Logout
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk kembali ke halaman sebelumnya atau dashboard
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = 'index.php'; // Ganti ke halaman default jika tidak ada history
            }
        }

        // Interactivity Form Submit
        document.getElementById('logoutForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Tampilkan loading overlay
            const overlay = document.getElementById('loadingOverlay');
            overlay.style.display = 'flex';

            // Animasi sedikit delay 1 detik (1000ms) agar user melihat proses
            setTimeout(() => {
                this.submit();
            }, 1000);
        });
    </script>
</body>
</html>