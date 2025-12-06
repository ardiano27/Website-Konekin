<?php
include "check_login.php";

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit();
}

require_once "config/Database.php";

$success_message = "";
$error_message = "";

// Data untuk dropdown
$business_types = [
    '' => 'Pilih Jenis Bisnis',
    'food' => 'Makanan & Minuman',
    'fashion' => 'Fashion',
    'craft' => 'Kerajinan',
    'service' => 'Jasa',
    'retail' => 'Ritel',
    'other' => 'Lainnya'
];

$business_sizes = [
    '' => 'Pilih Ukuran Bisnis',
    'micro' => 'Mikro (< 5 karyawan)',
    'small' => 'Kecil (5-19 karyawan)',
    'medium' => 'Menengah (20-99 karyawan)'
];

// Ambil data profil
try {
    $user_id = $_SESSION['user_id'];
    
    // Query untuk data profil UMKM
    $profile_query = "SELECT up.*, u.full_name, u.email, u.phone, u.avatar_url, u.background_url 
                      FROM umkm_profiles up 
                      JOIN users u ON up.user_id = u.id 
                      WHERE up.user_id = ?";
    $stmt = $conn->prepare($profile_query);
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        $insert_profile = "INSERT INTO umkm_profiles (user_id, business_name) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_profile);
        $business_name = explode(' ', $_SESSION['full_name'])[0] . "'s Business";
        $stmt->execute([$user_id, $business_name]);
        
        $stmt = $conn->prepare($profile_query);
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Handle avatar upload via base64
    if (isset($_POST['avatar_data'])) {
        header('Content-Type: application/json');
        
        $avatar_data = $_POST['avatar_data'];
        
        // Remove the data:image/png;base64, part
        $avatar_data = str_replace('data:image/png;base64,', '', $avatar_data);
        $avatar_data = str_replace(' ', '+', $avatar_data);
        
        // Decode base64
        $avatar_data = base64_decode($avatar_data);
        
        // Generate unique filename
        $avatar_filename = 'avatar_' . $user_id . '_' . time() . '.png';
        $avatar_path = 'assets/uploads/avatars/' . $avatar_filename;
        
        // Create directory if not exists
        if (!file_exists('assets/uploads/avatars')) {
            mkdir('assets/uploads/avatars', 0755, true);
        }
        
        // Save the image
        if (file_put_contents($avatar_path, $avatar_data)) {
            // Delete old avatar if exists
            if (!empty($profile['avatar_url']) && file_exists($profile['avatar_url'])) {
                unlink($profile['avatar_url']);
            }
            
            // Update database
            $update_avatar = "UPDATE users SET avatar_url = ? WHERE id = ?";
            $stmt = $conn->prepare($update_avatar);
            $stmt->execute([$avatar_path, $user_id]);
            
            echo json_encode(['success' => true, 'avatar_url' => $avatar_path]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Gagal menyimpan avatar']);
        }
        exit;
    }

    // Handle background upload via base64
    if (isset($_POST['background_data'])) {
        header('Content-Type: application/json');
        
        $background_data = $_POST['background_data'];
        
        // Remove the data:image/png;base64, part
        $background_data = str_replace('data:image/png;base64,', '', $background_data);
        $background_data = str_replace(' ', '+', $background_data);
        
        // Decode base64
        $background_data = base64_decode($background_data);
        
        // Generate unique filename
        $background_filename = 'background_' . $user_id . '_' . time() . '.png';
        $background_path = 'assets/uploads/backgrounds/' . $background_filename;
        
        // Create directory if not exists
        if (!file_exists('assets/uploads/backgrounds')) {
            mkdir('assets/uploads/backgrounds', 0755, true);
        }
        
        // Save the image
        if (file_put_contents($background_path, $background_data)) {
            // Delete old background if exists
            if (!empty($profile['background_url']) && file_exists($profile['background_url'])) {
                unlink($profile['background_url']);
            }
            
            // Update database
            $update_background = "UPDATE users SET background_url = ? WHERE id = ?";
            $stmt = $conn->prepare($update_background);
            $stmt->execute([$background_path, $user_id]);
            
            echo json_encode(['success' => true, 'background_url' => $background_path]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Gagal menyimpan background']);
        }
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'])) {
        $business_name = $_POST['business_name'] ?? '';
        $business_type = $_POST['business_type'] ?? '';
        $business_description = $_POST['business_description'] ?? '';
        $business_size = $_POST['business_size'] ?? '';
        $established_year = $_POST['established_year'] ?? '';
        $address = $_POST['address'] ?? '';
        $business_website = $_POST['business_website'] ?? '';
        $whatsapp = $_POST['whatsapp'] ?? '';
        $instagram = $_POST['instagram'] ?? '';
        $tokopedia = $_POST['tokopedia'] ?? '';
        $shopee = $_POST['shopee'] ?? '';
        
        // Update user data
        $full_name = $_POST['full_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        // Validasi
        if (empty($full_name)) {
            $error_message = "Nama lengkap wajib diisi";
        } elseif (empty($business_name)) {
            $error_message = "Nama bisnis wajib diisi";
        } else {
            try {
                $conn->beginTransaction();
                
                // Update users table
                $update_user_query = "UPDATE users SET full_name = ?, phone = ? WHERE id = ?";
                $stmt = $conn->prepare($update_user_query);
                $stmt->execute([$full_name, $phone, $user_id]);
                
                // Update umkm_profiles table (NPWP dihapus sesuai permintaan)
                $update_profile_query = "UPDATE umkm_profiles SET 
                                        business_name = ?, 
                                        business_type = ?, 
                                        business_description = ?, 
                                        business_size = ?, 
                                        established_year = ?, 
                                        address = ?, 
                                        business_website = ?, 
                                        whatsapp = ?,
                                        instagram = ?,
                                        tokopedia = ?,
                                        shopee = ?
                                        WHERE user_id = ?";
                
                $stmt = $conn->prepare($update_profile_query);
                $stmt->execute([
                    $business_name,
                    $business_type,
                    $business_description,
                    $business_size,
                    $established_year,
                    $address,
                    $business_website,
                    $whatsapp,
                    $instagram,
                    $tokopedia,
                    $shopee,
                    $user_id
                ]);
                
                $conn->commit();
                $success_message = "Profil berhasil diperbarui!";
                
                // Refresh profile data
                $stmt = $conn->prepare($profile_query);
                $stmt->execute([$user_id]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update session
                $_SESSION['full_name'] = $full_name;
                
            } catch (PDOException $e) {
                $conn->rollBack();
                $error_message = "Error: " . $e->getMessage();
            }
        }
    }

} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil UMKM - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #10B981;
            --primary-dark: #059669;
            --secondary-color: #F59E0B;
            --dark-color: #1F2937;
            --gray-color: #6B7280;
            --gray-light: #F3F4F6;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
        }
        
        .edit-profile-wrapper {
            min-height: 100vh;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .profile-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-size: cover;
            background-position: center;
            z-index: 1;
        }
        
        .profile-background-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.9) 0%, rgba(5, 150, 105, 0.9) 100%);
            z-index: 2;
        }
        
        .profile-header-content {
            position: relative;
            z-index: 3;
        }
        
        .avatar-container-edit {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 1rem;
        }
        
        .avatar-img-edit {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 4px solid white;
            border-radius: 50%;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        
        .avatar-placeholder-edit {
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            border: 4px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .avatar-upload-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            border: 2px solid white;
            transition: all 0.3s ease;
        }
        
        .avatar-upload-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }
        
        .background-upload-btn {
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 3;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .background-upload-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            color: white;
        }
        
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section-title {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-light);
            display: flex;
            align-items: center;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #D1D5DB;
            padding: 0.75rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .social-input-group .input-group-text {
            background: var(--gray-light);
            border: 1px solid #D1D5DB;
            border-right: none;
            color: var(--gray-color);
        }
        
        .required::after {
            content: " *";
            color: #EF4444;
        }
        
        .cropper-container {
            max-height: 400px;
            overflow: hidden;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .preview-container {
            margin-top: 1rem;
        }
        
        .preview-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }
        
        .preview-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #ddd;
            margin: 0 auto;
        }
        
        .preview-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php include "dashboard-sidebar.php"; ?>
    
    <div class="main-content edit-profile-wrapper">
        <!-- Header dengan background -->
        <div class="profile-header">
            <div class="profile-background" id="profile-background" 
                 style="background-image: url('<?php echo !empty($profile['background_url']) ? htmlspecialchars($profile['background_url']) : 'assets/images/default-background.jpg'; ?>');"></div>
            <div class="profile-background-overlay"></div>
            
            <div class="container profile-header-content">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2">Edit Profil UMKM</h1>
                        <p class="mb-0">Lengkapi informasi bisnis Anda untuk menarik lebih banyak kreator</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="umkm-profile.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-2"></i>Kembali ke Profil
                        </a>
                    </div>
                </div>
                
                <!-- Avatar Section - DIPERBAIKI: Selalu ada elemen img dengan ID current-avatar -->
                <div class="text-center mt-4">
                    <div class="avatar-container-edit">
                        <!-- Elemen img selalu ada, hanya display yang diatur -->
                        <img id="current-avatar" 
                             src="<?php echo !empty($profile['avatar_url']) ? htmlspecialchars($profile['avatar_url']) : ''; ?>" 
                             alt="Foto Profil" class="avatar-img-edit" 
                             style="<?php echo empty($profile['avatar_url']) ? 'display: none;' : ''; ?>">
                        <!-- Placeholder hanya muncul jika tidak ada avatar -->
                        <div id="avatar-placeholder" class="avatar-placeholder-edit" 
                             style="<?php echo !empty($profile['avatar_url']) ? 'display: none;' : ''; ?>">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="avatar-upload-btn" data-bs-toggle="modal" data-bs-target="#avatarModal">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <button class="background-upload-btn" data-bs-toggle="modal" data-bs-target="#backgroundModal">
                <i class="fas fa-camera me-1"></i> Ubah Background
            </button>
        </div>

        <!-- Notifications -->
        <div class="container">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Form Edit Profil -->
        <div class="container">
            <form method="POST" action="edit-profile-umkm.php" id="profile-form">
                <!-- Informasi Dasar -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user me-2"></i>Informasi Dasar
                    </h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label required">Nama Lengkap Pemilik</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="business_name" class="form-label required">Nama Bisnis</label>
                            <input type="text" class="form-control" id="business_name" name="business_name" 
                                   value="<?php echo htmlspecialchars($profile['business_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label required">Email</label>
                            <input type="email" class="form-control" id="email" 
                                   value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" disabled>
                            <small class="text-muted">Email tidak dapat diubah</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label required">Nomor Telepon</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Detail Bisnis -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-store me-2"></i>Detail Bisnis
                    </h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="business_type" class="form-label required">Jenis Bisnis</label>
                            <select class="form-select" id="business_type" name="business_type" required>
                                <?php foreach ($business_types as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" 
                                        <?php echo ($profile['business_type'] ?? '') == $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="business_size" class="form-label">Ukuran Bisnis</label>
                            <select class="form-select" id="business_size" name="business_size">
                                <?php foreach ($business_sizes as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" 
                                        <?php echo ($profile['business_size'] ?? '') == $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="established_year" class="form-label">Tahun Berdiri</label>
                            <input type="number" class="form-control" id="established_year" name="established_year" 
                                   min="1900" max="<?php echo date('Y'); ?>" 
                                   value="<?php echo htmlspecialchars($profile['established_year'] ?? ''); ?>"
                                   style="max-width: 200px;">
                        </div>
                        
                        <!-- NPWP DIHAPUS SESUAI PERMINTAAN -->
                        
                        <div class="col-12 mb-3">
                            <label for="business_description" class="form-label">Deskripsi Bisnis</label>
                            <textarea class="form-control" id="business_description" name="business_description" 
                                      rows="5" placeholder="Ceritakan tentang bisnis Anda..."><?php echo htmlspecialchars($profile['business_description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">Alamat Lengkap</label>
                            <textarea class="form-control" id="address" name="address" 
                                      rows="3" placeholder="Alamat lengkap bisnis..."><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Media Sosial & Marketplace -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-share-alt me-2"></i>Media Sosial & Marketplace
                    </h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="business_website" class="form-label">Website Bisnis</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-globe"></i>
                                </span>
                                <input type="url" class="form-control" id="business_website" name="business_website" 
                                       placeholder="https://example.com" 
                                       value="<?php echo htmlspecialchars($profile['business_website'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="whatsapp" class="form-label">WhatsApp Business</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fab fa-whatsapp"></i>
                                </span>
                                <input type="text" class="form-control" id="whatsapp" name="whatsapp" 
                                       placeholder="6281234567890" 
                                       value="<?php echo htmlspecialchars($profile['whatsapp'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="instagram" class="form-label">Instagram</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fab fa-instagram"></i>
                                </span>
                                <input type="text" class="form-control" id="instagram" name="instagram" 
                                       placeholder="username" 
                                       value="<?php echo htmlspecialchars($profile['instagram'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="tokopedia" class="form-label">Tokopedia</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-shopping-cart"></i>
                                </span>
                                <input type="url" class="form-control" id="tokopedia" name="tokopedia" 
                                       placeholder="https://tokopedia.com/toko-anda" 
                                       value="<?php echo htmlspecialchars($profile['tokopedia'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="shopee" class="form-label">Shopee</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-store"></i>
                                </span>
                                <input type="url" class="form-control" id="shopee" name="shopee" 
                                       placeholder="https://shopee.co.id/toko-anda" 
                                       value="<?php echo htmlspecialchars($profile['shopee'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <div class="form-section">
                    <div class="row">
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                        <div class="col-md-6">
                            <a href="umkm-profile.php" class="btn btn-outline-secondary btn-lg w-100">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal untuk Upload Avatar -->
    <div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="avatarModalLabel">
                        <i class="fas fa-camera me-2"></i>Ubah Foto Profil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="avatar-upload" class="form-label">Pilih Gambar</label>
                        <input class="form-control" type="file" id="avatar-upload" accept="image/*">
                        <div class="form-text">Format: JPG, PNG, GIF. Maksimal: 5MB</div>
                    </div>
                    
                    <div class="cropper-container">
                        <img id="avatar-cropper" style="max-width: 100%; display: none;">
                    </div>
                    
                    <div class="preview-container">
                        <div class="preview-label">Pratinjau Foto Profil</div>
                        <div class="preview-avatar">
                            <img id="avatar-preview" style="max-width: 100%;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="button" class="btn btn-primary" id="save-avatar">
                        <i class="fas fa-save me-1"></i>Simpan Foto
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal untuk Upload Background -->
    <div class="modal fade" id="backgroundModal" tabindex="-1" aria-labelledby="backgroundModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="backgroundModalLabel">
                        <i class="fas fa-image me-2"></i>Ubah Background Profil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="background-upload" class="form-label">Pilih Gambar Background</label>
                        <input class="form-control" type="file" id="background-upload" accept="image/*">
                        <div class="form-text">Format: JPG, PNG. Maksimal: 10MB. Rekomendasi: 1920x1080px</div>
                    </div>
                    
                    <div class="preview-container mt-3">
                        <div class="preview-label">Pratinjau Background</div>
                        <div style="width: 100%; height: 200px; overflow: hidden; border-radius: 8px; border: 2px solid #ddd; background: #f8f9fa;">
                            <img id="background-preview" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="button" class="btn btn-primary" id="save-background">
                        <i class="fas fa-save me-1"></i>Simpan Background
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
        // Variabel global untuk cropper
        let avatarCropper = null;
        let backgroundCropper = null;

        // Initialize avatar cropper
        document.getElementById('avatar-upload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const image = document.getElementById('avatar-cropper');
                image.src = e.target.result;
                image.style.display = 'block';

                // Destroy previous cropper if exists
                if (avatarCropper) {
                    avatarCropper.destroy();
                }

                // Initialize cropper
                avatarCropper = new Cropper(image, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 0.8,
                    responsive: true,
                    rotatable: false,
                    scalable: false,
                    zoomable: true,
                    movable: true,
                    minCropBoxWidth: 100,
                    minCropBoxHeight: 100,
                    crop: function(event) {
                        // Update preview
                        const canvas = avatarCropper.getCroppedCanvas({
                            width: 150,
                            height: 150
                        });
                        document.getElementById('avatar-preview').src = canvas.toDataURL();
                    }
                });

                // Trigger initial crop for preview
                setTimeout(() => {
                    const canvas = avatarCropper.getCroppedCanvas({
                        width: 150,
                        height: 150
                    });
                    document.getElementById('avatar-preview').src = canvas.toDataURL();
                }, 100);
            };
            reader.readAsDataURL(file);
        });

        // Save avatar - DIPERBAIKI: Menangani elemen yang mungkin null
        document.getElementById('save-avatar').addEventListener('click', function() {
            if (!avatarCropper) {
                alert('Silakan pilih gambar terlebih dahulu');
                return;
            }

            const canvas = avatarCropper.getCroppedCanvas({
                width: 300,
                height: 300
            });

            const avatarData = canvas.toDataURL('image/png');

            // Show loading
            const saveBtn = document.getElementById('save-avatar');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...';
            saveBtn.disabled = true;

            // Send to server
            fetch('edit-profile-umkm.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'avatar_data=' + encodeURIComponent(avatarData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // PERBAIKAN: Pastikan elemen ada sebelum mengatur src
                    const currentAvatar = document.getElementById('current-avatar');
                    const avatarPlaceholder = document.getElementById('avatar-placeholder');
                    
                    if (currentAvatar) {
                        currentAvatar.src = data.avatar_url + '?t=' + new Date().getTime();
                        currentAvatar.style.display = 'block';
                    }
                    
                    if (avatarPlaceholder) {
                        avatarPlaceholder.style.display = 'none';
                    }
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('avatarModal'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Show success message
                    showNotification('Foto profil berhasil diperbarui!', 'success');
                } else {
                    showNotification('Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Error: ' + error.message, 'error');
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        });

        // Handle background upload
        document.getElementById('background-upload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('background-preview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        });

        // Save background
        document.getElementById('save-background').addEventListener('click', function() {
            const backgroundInput = document.getElementById('background-upload');
            if (!backgroundInput.files[0]) {
                alert('Silakan pilih gambar background terlebih dahulu');
                return;
            }

            const file = backgroundInput.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    // Create canvas to resize if needed
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // Set canvas dimensions (max width 1920px)
                    let width = img.width;
                    let height = img.height;
                    const maxWidth = 1920;
                    
                    if (width > maxWidth) {
                        height = (height * maxWidth) / width;
                        width = maxWidth;
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    // Draw resized image
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    const backgroundData = canvas.toDataURL('image/png');

                    // Show loading
                    const saveBtn = document.getElementById('save-background');
                    const originalText = saveBtn.innerHTML;
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...';
                    saveBtn.disabled = true;

                    // Send to server
                    fetch('edit-profile-umkm.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'background_data=' + encodeURIComponent(backgroundData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update background image
                            const profileBackground = document.getElementById('profile-background');
                            if (profileBackground) {
                                profileBackground.style.backgroundImage = 
                                    'url("' + data.background_url + '?t=' + new Date().getTime() + '")';
                            }
                            
                            // Close modal
                            const modal = bootstrap.Modal.getInstance(document.getElementById('backgroundModal'));
                            if (modal) {
                                modal.hide();
                            }
                            
                            // Show success message
                            showNotification('Background profil berhasil diperbarui!', 'success');
                        } else {
                            showNotification('Error: ' + data.error, 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Error: ' + error.message, 'error');
                    })
                    .finally(() => {
                        saveBtn.innerHTML = originalText;
                        saveBtn.disabled = false;
                    });
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });

        // Format nomor telepon
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.startsWith('0')) {
                    value = '62' + value.substring(1);
                }
                e.target.value = value;
            });
        }

        // Format WhatsApp
        const whatsappInput = document.getElementById('whatsapp');
        if (whatsappInput) {
            whatsappInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.startsWith('0')) {
                    value = '62' + value.substring(1);
                }
                e.target.value = value;
            });
        }

        // Format Instagram (remove @)
        const instagramInput = document.getElementById('instagram');
        if (instagramInput) {
            instagramInput.addEventListener('input', function(e) {
                let value = e.target.value.replace('@', '');
                e.target.value = value;
            });
        }

        // Validasi form
        const profileForm = document.getElementById('profile-form');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                        
                        if (!field.nextElementSibling?.classList.contains('invalid-feedback')) {
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'invalid-feedback';
                            errorDiv.textContent = 'Field ini wajib diisi';
                            field.parentNode.appendChild(errorDiv);
                        }
                    } else {
                        field.classList.remove('is-invalid');
                        const errorDiv = field.parentNode.querySelector('.invalid-feedback');
                        if (errorDiv) errorDiv.remove();
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    const firstError = this.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                    showNotification('Harap isi semua field yang wajib diisi', 'error');
                }
            });
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const alertClass = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            };
            
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass[type]} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Initialize modals
        document.addEventListener('DOMContentLoaded', function() {
            const avatarModal = document.getElementById('avatarModal');
            const backgroundModal = document.getElementById('backgroundModal');
            
            // Reset cropper when modal is closed
            if (avatarModal) {
                avatarModal.addEventListener('hidden.bs.modal', function () {
                    if (avatarCropper) {
                        avatarCropper.destroy();
                        avatarCropper = null;
                    }
                    const avatarCropperImg = document.getElementById('avatar-cropper');
                    if (avatarCropperImg) {
                        avatarCropperImg.style.display = 'none';
                    }
                    const avatarUpload = document.getElementById('avatar-upload');
                    if (avatarUpload) avatarUpload.value = '';
                    const avatarPreview = document.getElementById('avatar-preview');
                    if (avatarPreview) avatarPreview.src = '';
                });
            }
            
            if (backgroundModal) {
                backgroundModal.addEventListener('hidden.bs.modal', function () {
                    const backgroundUpload = document.getElementById('background-upload');
                    if (backgroundUpload) backgroundUpload.value = '';
                    const backgroundPreview = document.getElementById('background-preview');
                    if (backgroundPreview) backgroundPreview.src = '';
                });
            }
        });
    </script>
</body>
</html>