<?php include "check_login.php"; ?>
<?php
if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit();
}

require_once "config/Database.php";

// Inisialisasi variabel
$errors = [];
$success = false;

// Ambil data profil saat ini
try {
    $user_id = $_SESSION['user_id'];
    $profile_query = "SELECT cp.*, u.full_name, u.email, u.phone, u.avatar_url, u.background_url 
                      FROM creative_profiles cp 
                      JOIN users u ON cp.user_id = u.id 
                      WHERE cp.user_id = ?";
    $stmt = $conn->prepare($profile_query);
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        $insert_profile = "INSERT INTO creative_profiles (user_id) VALUES (?)";
        $stmt = $conn->prepare($insert_profile);
        $stmt->execute([$user_id]);
        
        $stmt = $conn->prepare($profile_query);
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $errors[] = "Error: " . $e->getMessage();
}

// Proses form ketika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'])) {
    // Validasi dan sanitasi input
    $full_name = trim($_POST['full_name'] ?? '');
    $tagline = trim($_POST['tagline'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $website_url = trim($_POST['website_url'] ?? '');
    $linkedin_url = trim($_POST['linkedin_url'] ?? '');
    $instagram_url = trim($_POST['instagram_url'] ?? '');
    $hourly_rate = !empty($_POST['hourly_rate']) ? $_POST['hourly_rate'] : null;
    $experience_level = $_POST['experience_level'] ?? 'beginner';
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    // Validasi input
    if (empty($full_name)) {
        $errors[] = "Nama lengkap wajib diisi";
    }
    
    if (empty($email)) {
        $errors[] = "Email wajib diisi";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }
    
    if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{10,20}$/', $phone)) {
        $errors[] = "Format nomor telepon tidak valid";
    }
    
    if (!empty($hourly_rate) && (!is_numeric($hourly_rate) || $hourly_rate < 0)) {
        $errors[] = "Tarif per jam harus berupa angka positif";
    }
    
    // Jika tidak ada error, proses update
    if (empty($errors)) {
        try {
            // Mulai transaksi
            $conn->beginTransaction();
            
            // Update tabel users
            $update_user = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = $conn->prepare($update_user);
            $stmt->execute([$full_name, $email, $phone, $user_id]);
            
            // Update tabel creative_profiles
            $update_profile = "UPDATE creative_profiles 
                              SET tagline = ?, bio = ?, hourly_rate = ?, experience_level = ?, 
                                  location = ?, website_url = ?, linkedin_url = ?, instagram_url = ?, 
                                  is_available = ?
                              WHERE user_id = ?";
            $stmt = $conn->prepare($update_profile);
            $stmt->execute([
                $tagline, $bio, $hourly_rate, $experience_level, 
                $location, $website_url, $linkedin_url, $instagram_url, 
                $is_available, $user_id
            ]);
            
            // Commit transaksi
            $conn->commit();
            
            $success = true;
            
            // Update session full_name jika berubah
            $_SESSION['full_name'] = $full_name;
            
            // Refresh data profil
            $stmt = $conn->prepare($profile_query);
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Error saat menyimpan perubahan: " . $e->getMessage();
        }
    }
}

// Handle avatar upload
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
        // Update database
        try {
            $update_avatar = "UPDATE users SET avatar_url = ? WHERE id = ?";
            $stmt = $conn->prepare($update_avatar);
            $stmt->execute([$avatar_path, $user_id]);
            
            echo json_encode(['success' => true, 'avatar_url' => $avatar_path]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save avatar']);
    }
    exit;
}

// Handle background upload
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
        // Update database
        try {
            $update_background = "UPDATE users SET background_url = ? WHERE id = ?";
            $stmt = $conn->prepare($update_background);
            $stmt->execute([$background_path, $user_id]);
            
            echo json_encode(['success' => true, 'background_url' => $background_path]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save background']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <link href="assets/css/edit-profile.css" rel="stylesheet">
</head>
<body>
    <?php include "dashboard-sidebar.php"; ?>
    
    <div class="main-content">
        <!-- Header dengan background yang bisa diubah -->
        <div class="profile-header-edit">
            <div class="profile-background" id="profile-background" style="background-image: url('<?php echo !empty($profile['background_url']) ? htmlspecialchars($profile['background_url']) : 'assets/images/default-background.jpg'; ?>');"></div>
            <div class="profile-background-overlay"></div>
            
            <div class="container">
                <div class="profile-avatar-section">
                    <div class="avatar-container-edit">
                        <?php if (!empty($profile['avatar_url'])): ?>
                            <img src="<?php echo htmlspecialchars($profile['avatar_url']); ?>" 
                                 alt="Avatar" class="avatar-img-edit" id="current-avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder-edit">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
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
        
        <div class="container">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Profil berhasil diperbarui!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo $error; ?></div>
                    <?php endforeach; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="edit-profile.php" id="profile-form">
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Informasi Dasar -->
                        <div class="edit-form-container">
                            <h3 class="section-title">
                                <i class="fas fa-user me-2"></i>Informasi Dasar
                            </h3>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">Nama Lengkap *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="tagline" class="form-label">Tagline Profesional</label>
                                    <input type="text" class="form-control" id="tagline" name="tagline" 
                                           value="<?php echo htmlspecialchars($profile['tagline'] ?? ''); ?>" 
                                           placeholder="Contoh: UI/UX Designer & Frontend Developer">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Nomor Telepon</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" 
                                       placeholder="Contoh: 081234567890">
                            </div>
                            
                            <div class="mb-3">
                                <label for="bio" class="form-label">Tentang Saya</label>
                                <textarea class="form-control" id="bio" name="bio" rows="5" 
                                          placeholder="Ceritakan tentang diri Anda, keahlian, dan pengalaman..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                                <div class="form-text">Jelaskan pengalaman, keahlian, dan apa yang membuat Anda unik.</div>
                            </div>
                        </div>
                        
                        <!-- Informasi Profesional -->
                        <div class="edit-form-container">
                            <h3 class="section-title">
                                <i class="fas fa-briefcase me-2"></i>Informasi Profesional
                            </h3>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="experience_level" class="form-label">Tingkat Pengalaman</label>
                                    <select class="form-select" id="experience_level" name="experience_level">
                                        <option value="beginner" <?php echo ($profile['experience_level'] ?? 'beginner') === 'beginner' ? 'selected' : ''; ?>>Pemula (0-2 tahun)</option>
                                        <option value="intermediate" <?php echo ($profile['experience_level'] ?? 'beginner') === 'intermediate' ? 'selected' : ''; ?>>Menengah (2-5 tahun)</option>
                                        <option value="expert" <?php echo ($profile['experience_level'] ?? 'beginner') === 'expert' ? 'selected' : ''; ?>>Ahli (5+ tahun)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="hourly_rate" class="form-label">Tarif Per Jam (Rp)</label>
                                    <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" 
                                           value="<?php echo $profile['hourly_rate'] ?? ''; ?>" 
                                           placeholder="Contoh: 50000" min="0" step="1000">
                                    <div class="form-text">Biarkan kosong jika ingin dinegosiasikan</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label">Lokasi</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>" 
                                       placeholder="Contoh: Jakarta, Indonesia">
                            </div>
                            
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_available" name="is_available" 
                                       <?php echo ($profile['is_available'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_available">
                                    Saya tersedia untuk proyek baru
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Informasi Kontak & Sosial Media -->
                        <div class="edit-form-container">
                            <h3 class="section-title">
                                <i class="fas fa-link me-2"></i>Tautan & Media Sosial
                            </h3>
                            
                            <div class="mb-3">
                                <label for="website_url" class="form-label">
                                    <i class="fas fa-globe me-1"></i>Website/Portfolio
                                </label>
                                <input type="url" class="form-control" id="website_url" name="website_url" 
                                       value="<?php echo htmlspecialchars($profile['website_url'] ?? ''); ?>" 
                                       placeholder="https://website-anda.com">
                            </div>
                            
                            <div class="mb-3">
                                <label for="linkedin_url" class="form-label">
                                    <i class="fab fa-linkedin me-1"></i>LinkedIn
                                </label>
                                <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                       value="<?php echo htmlspecialchars($profile['linkedin_url'] ?? ''); ?>" 
                                       placeholder="https://linkedin.com/in/username">
                            </div>
                            
                            <div class="mb-3">
                                <label for="instagram_url" class="form-label">
                                    <i class="fab fa-instagram me-1"></i>Instagram
                                </label>
                                <input type="url" class="form-control" id="instagram_url" name="instagram_url" 
                                       value="<?php echo htmlspecialchars($profile['instagram_url'] ?? ''); ?>" 
                                       placeholder="https://instagram.com/username">
                            </div>
                        </div>
                        
                        <!-- Status Ketersediaan -->
                        <div class="edit-form-container">
                            <h3 class="section-title">
                                <i class="fas fa-info-circle me-2"></i>Status Profil
                            </h3>
                            
                            <div class="mb-3">
                                <label class="form-label">Ketersediaan</label>
                                <div>
                                    <span class="availability-badge <?php echo ($profile['is_available'] ?? 1) ? 'available' : 'not-available'; ?>">
                                        <i class="fas fa-<?php echo ($profile['is_available'] ?? 1) ? 'check' : 'times'; ?>-circle me-1"></i>
                                        <?php echo ($profile['is_available'] ?? 1) ? 'Tersedia' : 'Tidak Tersedia'; ?>
                                    </span>
                                </div>
                                <small class="text-muted">Ubah status ketersediaan di bagian Informasi Profesional</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Foto Profil</label>
                                <div>
                                    <span class="availability-badge available">
                                        <i class="fas fa-check-circle me-1"></i>
                                        <?php echo !empty($profile['avatar_url']) ? 'Sudah diatur' : 'Belum diatur'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Background Profil</label>
                                <div>
                                    <span class="availability-badge <?php echo !empty($profile['background_url']) ? 'available' : 'not-available'; ?>">
                                        <i class="fas fa-<?php echo !empty($profile['background_url']) ? 'check' : 'times'; ?>-circle me-1"></i>
                                        <?php echo !empty($profile['background_url']) ? 'Sudah diatur' : 'Belum diatur'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tombol Aksi -->
                        <div class="edit-form-container">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Simpan Perubahan
                                </button>
                                <a href="creative-profile.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Batal
                                </a>
                            </div>
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
    <script src="assets/js/edit-profile.js"></script>
</body>
</html>