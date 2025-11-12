<?php 
include "check_login.php";
require_once 'config/Database.php';

$database = new DatabaseConnection();
$conn = $database->getConnection();

$success_msg = '';
$error_msg = '';

// Ambil data profil user
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    // Ambil data user
    $user_sql = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $conn->prepare($user_sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ambil data profil spesifik berdasarkan tipe user
    if ($user_type === 'creative') {
        $profile_sql = "SELECT * FROM creative_profiles WHERE user_id = :user_id";
        $stmt = $conn->prepare($profile_sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ambil skills
        if ($profile) {
            $skills_sql = "
                SELECT s.id, s.name, cs.proficiency_level, cs.years_of_experience
                FROM creative_skills cs
                JOIN skills s ON cs.skill_id = s.id
                WHERE cs.creative_profile_id = :profile_id
            ";
            $stmt = $conn->prepare($skills_sql);
            $stmt->bindParam(':profile_id', $profile['id']);
            $stmt->execute();
            $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $skills = [];
        }
    } else {
        $profile_sql = "SELECT * FROM umkm_profiles WHERE user_id = :user_id";
        $stmt = $conn->prepare($profile_sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching profile: " . $e->getMessage());
}

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    
    try {
        // Update data user
        $update_user_sql = "UPDATE users SET full_name = :full_name, phone = :phone WHERE id = :user_id";
        $stmt = $conn->prepare($update_user_sql);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $success_msg = "Profile updated successfully!";
        } else {
            $error_msg = "Error updating profile.";
        }
        
        // Handle avatar crop data
        if (!empty($_POST['avatar_data'])) {
            $avatar_data = json_decode($_POST['avatar_data'], true);
            
            if ($avatar_data && isset($_POST['avatar_image'])) {
                $image_data = $_POST['avatar_image'];
                $image_data = str_replace('data:image/png;base64,', '', $image_data);
                $image_data = str_replace(' ', '+', $image_data);
                $image_data = base64_decode($image_data);
                
                $upload_dir = 'uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = 'avatar_' . $user_id . '_' . time() . '.png';
                $file_path = $upload_dir . $file_name;
                
                if (file_put_contents($file_path, $image_data)) {
                    // Update path avatar di database
                    $update_avatar_sql = "UPDATE users SET avatar_url = :avatar_url WHERE id = :user_id";
                    $stmt = $conn->prepare($update_avatar_sql);
                    $stmt->bindParam(':avatar_url', $file_path);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                    
                    $_SESSION['avatar_url'] = $file_path;
                    $success_msg .= " Profile photo updated!";
                } else {
                    $error_msg .= " Failed to upload profile photo.";
                }
            }
        }
        
        // Update profil spesifik berdasarkan tipe user
        if ($user_type === 'creative') {
            $tagline = trim($_POST['tagline'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            $hourly_rate = !empty($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : null;
            $experience_level = $_POST['experience_level'] ?? null;
            $location = trim($_POST['location'] ?? '');
            $website_url = trim($_POST['website_url'] ?? '');
            $linkedin_url = trim($_POST['linkedin_url'] ?? '');
            $instagram_url = trim($_POST['instagram_url'] ?? '');
            $is_available = isset($_POST['is_available']) ? 1 : 0;
            
            if ($profile) {
                $update_profile_sql = "
                    UPDATE creative_profiles 
                    SET tagline = :tagline, bio = :bio, hourly_rate = :hourly_rate, experience_level = :experience_level, 
                        location = :location, website_url = :website_url, linkedin_url = :linkedin_url, 
                        instagram_url = :instagram_url, is_available = :is_available
                    WHERE user_id = :user_id
                ";
            } else {
                $update_profile_sql = "
                    INSERT INTO creative_profiles 
                    (user_id, tagline, bio, hourly_rate, experience_level, location, website_url, linkedin_url, instagram_url, is_available)
                    VALUES (:user_id, :tagline, :bio, :hourly_rate, :experience_level, :location, :website_url, :linkedin_url, :instagram_url, :is_available)
                ";
            }
            
            $stmt = $conn->prepare($update_profile_sql);
            $stmt->bindParam(':tagline', $tagline);
            $stmt->bindParam(':bio', $bio);
            $stmt->bindParam(':hourly_rate', $hourly_rate);
            $stmt->bindParam(':experience_level', $experience_level);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':website_url', $website_url);
            $stmt->bindParam(':linkedin_url', $linkedin_url);
            $stmt->bindParam(':instagram_url', $instagram_url);
            $stmt->bindParam(':is_available', $is_available);
            $stmt->bindParam(':user_id', $user_id);
            
            if (!$stmt->execute()) {
                $error_msg .= " Failed to update creative profile.";
            }
            
            // Get profile ID for skills
            if (!$profile) {
                $profile_id = $conn->lastInsertId();
            } else {
                $profile_id = $profile['id'];
            }
            
            // Update skills
            if (isset($_POST['skills']) && is_array($_POST['skills'])) {
                // Hapus skills lama
                $delete_skills_sql = "DELETE FROM creative_skills WHERE creative_profile_id = :profile_id";
                $stmt = $conn->prepare($delete_skills_sql);
                $stmt->bindParam(':profile_id', $profile_id);
                $stmt->execute();
                
                // Tambah skills baru
                $insert_skill_sql = "
                    INSERT INTO creative_skills (creative_profile_id, skill_id, proficiency_level, years_of_experience)
                    VALUES (:profile_id, :skill_id, :proficiency, :years)
                ";
                $stmt = $conn->prepare($insert_skill_sql);
                
                foreach ($_POST['skills'] as $skill_id) {
                    $proficiency = $_POST['proficiency_' . $skill_id] ?? 'intermediate';
                    $years = intval($_POST['years_' . $skill_id] ?? 1);
                    
                    $stmt->bindParam(':profile_id', $profile_id);
                    $stmt->bindParam(':skill_id', $skill_id);
                    $stmt->bindParam(':proficiency', $proficiency);
                    $stmt->bindParam(':years', $years);
                    $stmt->execute();
                }
            }
        } else {
            // Update profil UMKM
            $business_name = trim($_POST['business_name'] ?? '');
            $business_type = $_POST['business_type'] ?? null;
            $business_description = trim($_POST['business_description'] ?? '');
            $business_size = $_POST['business_size'] ?? null;
            $established_year = !empty($_POST['established_year']) ? $_POST['established_year'] : null;
            $address = trim($_POST['address'] ?? '');
            $business_website = trim($_POST['business_website'] ?? '');
            $tax_id = trim($_POST['tax_id'] ?? '');
            
            if ($profile) {
                $update_profile_sql = "
                    UPDATE umkm_profiles 
                    SET business_name = :business_name, business_type = :business_type, business_description = :business_description, 
                        business_size = :business_size, established_year = :established_year, address = :address, 
                        business_website = :business_website, tax_id = :tax_id
                    WHERE user_id = :user_id
                ";
            } else {
                $update_profile_sql = "
                    INSERT INTO umkm_profiles 
                    (user_id, business_name, business_type, business_description, business_size, established_year, address, business_website, tax_id)
                    VALUES (:user_id, :business_name, :business_type, :business_description, :business_size, :established_year, :address, :business_website, :tax_id)
                ";
            }
            
            $stmt = $conn->prepare($update_profile_sql);
            $stmt->bindParam(':business_name', $business_name);
            $stmt->bindParam(':business_type', $business_type);
            $stmt->bindParam(':business_description', $business_description);
            $stmt->bindParam(':business_size', $business_size);
            $stmt->bindParam(':established_year', $established_year);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':business_website', $business_website);
            $stmt->bindParam(':tax_id', $tax_id);
            $stmt->bindParam(':user_id', $user_id);
            
            if (!$stmt->execute()) {
                $error_msg .= " Failed to update UMKM profile.";
            }
        }
        
        // Refresh data setelah update
        header("Location: profile.php?success=" . urlencode($success_msg) . "&error=" . urlencode($error_msg));
        exit;
        
    } catch (PDOException $e) {
        error_log("Error updating profile: " . $e->getMessage());
        $error_msg = "Database error: " . $e->getMessage();
    }
}

// Ambil daftar skills untuk creative worker
try {
    $all_skills_sql = "SELECT id, name, category FROM skills WHERE is_active = 1 ORDER BY name";
    $stmt = $conn->query($all_skills_sql);
    $all_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching skills: " . $e->getMessage());
    $all_skills = [];
}

// Ambil pesan sukses/error dari URL
if (isset($_GET['success'])) {
    $success_msg = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error_msg = $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="profile.css" rel="stylesheet">
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Profile Header - Minimalist Design -->
        <div class="profile-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="avatar-section">
                            <div class="avatar-container">
                                <img src="<?php echo !empty($user['avatar_url']) ? htmlspecialchars($user['avatar_url']) : 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=2596be&color=fff&size=150'; ?>" 
                                     alt="Avatar" class="avatar-img" id="avatarPreview">
                                <button type="button" class="avatar-edit-btn" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <h1 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                        <div class="profile-info">
                            <span class="profile-email"><?php echo htmlspecialchars($user['email']); ?></span>
                            <span class="profile-update">Updated 1 month ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="container profile-container">
            <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show custom-alert" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <span><?php echo $success_msg; ?></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show custom-alert" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span><?php echo $error_msg; ?></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="profile.php" enctype="multipart/form-data" id="profileForm">
                <input type="hidden" name="avatar_data" id="avatarData">
                <input type="hidden" name="avatar_image" id="avatarImage">
                
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Personal Information Card -->
                        <div class="profile-card mb-4">
                            <div class="card-header">
                                <h5 class="card-title">Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="full_name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                            <div class="form-text">Email cannot be changed</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Account Type</label>
                                            <input type="text" class="form-control" 
                                                   value="<?php echo $user_type === 'creative' ? 'Creative Worker' : 'UMKM/Business'; ?>" disabled>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($user_type === 'creative'): ?>
                            <!-- Creative Worker Profile -->
                            <div class="profile-card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title">Creative Profile</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="tagline" class="form-label">Professional Tagline</label>
                                                <input type="text" class="form-control" id="tagline" name="tagline" 
                                                       value="<?php echo htmlspecialchars($profile['tagline'] ?? ''); ?>" 
                                                       placeholder="e.g., Graphic Designer Specializing in Branding">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="bio" class="form-label">About Me</label>
                                                <textarea class="form-control" id="bio" name="bio" rows="4"
                                                          placeholder="Tell us about yourself and your experience"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="hourly_rate" class="form-label">Hourly Rate (Rp)</label>
                                                <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" 
                                                       value="<?php echo $profile['hourly_rate'] ?? ''; ?>" min="0" step="1000"
                                                       placeholder="0">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="experience_level" class="form-label">Experience Level</label>
                                                <select class="form-select" id="experience_level" name="experience_level">
                                                    <option value="">Select experience level</option>
                                                    <option value="beginner" <?php echo ($profile['experience_level'] ?? '') === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                                    <option value="intermediate" <?php echo ($profile['experience_level'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                    <option value="expert" <?php echo ($profile['experience_level'] ?? '') === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="location" class="form-label">Location</label>
                                                <input type="text" class="form-control" id="location" name="location" 
                                                       value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>"
                                                       placeholder="Your city">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="availability-toggle">
                                                <label class="form-label">Availability Status</label>
                                                <div class="toggle-switch">
                                                    <input type="checkbox" id="is_available" name="is_available" 
                                                           <?php echo ($profile['is_available'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label for="is_available" class="toggle-label">
                                                        <span class="toggle-handle"></span>
                                                        <span class="toggle-text">Available for new projects</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Skills Section -->
                            <div class="profile-card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title">Skills</h5>
                                </div>
                                <div class="card-body">
                                    <div class="skills-container">
                                        <label class="form-label mb-3">Select and configure your skill levels</label>
                                        <div class="row">
                                            <?php foreach ($all_skills as $skill): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="skill-item">
                                                        <div class="form-check">
                                                            <input class="form-check-input skill-checkbox" type="checkbox" 
                                                                   name="skills[]" value="<?php echo $skill['id']; ?>" 
                                                                   id="skill_<?php echo $skill['id']; ?>"
                                                                   <?php 
                                                                   $has_skill = false;
                                                                   $user_skill = [];
                                                                   if (isset($skills)) {
                                                                       foreach ($skills as $us) {
                                                                           if ($us['id'] == $skill['id']) {
                                                                               $has_skill = true;
                                                                               $user_skill = $us;
                                                                               break;
                                                                           }
                                                                       }
                                                                   }
                                                                   echo $has_skill ? 'checked' : '';
                                                                   ?>>
                                                            <label class="form-check-label skill-name" for="skill_<?php echo $skill['id']; ?>">
                                                                <?php echo htmlspecialchars($skill['name']); ?>
                                                                <span class="skill-category"><?php echo $skill['category']; ?></span>
                                                            </label>
                                                        </div>
                                                        <div class="skill-details mt-2" style="display: <?php echo $has_skill ? 'block' : 'none'; ?>;">
                                                            <div class="row g-2">
                                                                <div class="col-6">
                                                                    <select class="form-select form-select-sm" name="proficiency_<?php echo $skill['id']; ?>">
                                                                        <option value="basic" <?php echo ($user_skill['proficiency_level'] ?? '') === 'basic' ? 'selected' : ''; ?>>Basic</option>
                                                                        <option value="intermediate" <?php echo ($user_skill['proficiency_level'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                                        <option value="advanced" <?php echo ($user_skill['proficiency_level'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-6">
                                                                    <div class="input-group input-group-sm">
                                                                        <input type="number" class="form-control" 
                                                                               name="years_<?php echo $skill['id']; ?>" min="0" max="50" 
                                                                               value="<?php echo $user_skill['years_of_experience'] ?? 1; ?>" 
                                                                               placeholder="Years">
                                                                        <span class="input-group-text">yrs</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Social Media -->
                            <div class="profile-card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title">Social Media & Portfolio</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="website_url" class="form-label">
                                                    <i class="fas fa-globe me-2"></i>Website/Portfolio
                                                </label>
                                                <input type="url" class="form-control" id="website_url" name="website_url" 
                                                       value="<?php echo htmlspecialchars($profile['website_url'] ?? ''); ?>" 
                                                       placeholder="https://">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="linkedin_url" class="form-label">
                                                    <i class="fab fa-linkedin me-2"></i>LinkedIn
                                                </label>
                                                <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                                       value="<?php echo htmlspecialchars($profile['linkedin_url'] ?? ''); ?>" 
                                                       placeholder="https://linkedin.com/in/username">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="instagram_url" class="form-label">
                                                    <i class="fab fa-instagram me-2"></i>Instagram
                                                </label>
                                                <input type="url" class="form-control" id="instagram_url" name="instagram_url" 
                                                       value="<?php echo htmlspecialchars($profile['instagram_url'] ?? ''); ?>" 
                                                       placeholder="https://instagram.com/username">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php else: ?>
                            <!-- UMKM Profile -->
                            <div class="profile-card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title">Business Profile</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="business_name" class="form-label">Business Name</label>
                                                <input type="text" class="form-control" id="business_name" name="business_name" 
                                                       value="<?php echo htmlspecialchars($profile['business_name'] ?? ''); ?>" required
                                                       placeholder="Your business name">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="business_type" class="form-label">Business Type</label>
                                                <select class="form-select" id="business_type" name="business_type">
                                                    <option value="">Select business type</option>
                                                    <option value="food" <?php echo ($profile['business_type'] ?? '') === 'food' ? 'selected' : ''; ?>>Food & Beverage</option>
                                                    <option value="fashion" <?php echo ($profile['business_type'] ?? '') === 'fashion' ? 'selected' : ''; ?>>Fashion</option>
                                                    <option value="craft" <?php echo ($profile['business_type'] ?? '') === 'craft' ? 'selected' : ''; ?>>Craft</option>
                                                    <option value="service" <?php echo ($profile['business_type'] ?? '') === 'service' ? 'selected' : ''; ?>>Service</option>
                                                    <option value="retail" <?php echo ($profile['business_type'] ?? '') === 'retail' ? 'selected' : ''; ?>>Retail</option>
                                                    <option value="other" <?php echo ($profile['business_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="business_size" class="form-label">Business Size</label>
                                                <select class="form-select" id="business_size" name="business_size">
                                                    <option value="">Select business size</option>
                                                    <option value="micro" <?php echo ($profile['business_size'] ?? '') === 'micro' ? 'selected' : ''; ?>>Micro</option>
                                                    <option value="small" <?php echo ($profile['business_size'] ?? '') === 'small' ? 'selected' : ''; ?>>Small</option>
                                                    <option value="medium" <?php echo ($profile['business_size'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="business_description" class="form-label">Business Description</label>
                                                <textarea class="form-control" id="business_description" name="business_description" 
                                                          rows="4" placeholder="Describe your business"><?php echo htmlspecialchars($profile['business_description'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="established_year" class="form-label">Established Year</label>
                                                <input type="number" class="form-control" id="established_year" name="established_year" 
                                                       min="1900" max="<?php echo date('Y'); ?>" 
                                                       value="<?php echo $profile['established_year'] ?? ''; ?>"
                                                       placeholder="Year">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="tax_id" class="form-label">Tax ID (NPWP)</label>
                                                <input type="text" class="form-control" id="tax_id" name="tax_id" 
                                                       value="<?php echo htmlspecialchars($profile['tax_id'] ?? ''); ?>"
                                                       placeholder="Tax ID">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="address" class="form-label">Business Address</label>
                                                <textarea class="form-control" id="address" name="address" 
                                                          rows="3" placeholder="Business address"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="business_website" class="form-label">
                                                    <i class="fas fa-globe me-2"></i>Business Website
                                                </label>
                                                <input type="url" class="form-control" id="business_website" name="business_website" 
                                                       value="<?php echo htmlspecialchars($profile['business_website'] ?? ''); ?>" 
                                                       placeholder="https://">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Profile Completion Card -->
                        <div class="profile-card mb-4">
                            <div class="card-header">
                                <h6 class="card-title">Profile Completion</h6>
                            </div>
                            <div class="card-body">
                                <div class="completion-progress">
                                    <div class="progress-bar-container">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar" role="progressbar" style="width: 75%"></div>
                                        </div>
                                        <div class="progress-text">75% Complete</div>
                                    </div>
                                    <ul class="completion-checklist">
                                        <li class="completed">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <span>Basic Information</span>
                                        </li>
                                        <li class="<?php echo (!empty($profile['tagline']) || !empty($profile['business_name'])) ? 'completed' : ''; ?>">
                                            <i class="fas fa-<?php echo (!empty($profile['tagline']) || !empty($profile['business_name'])) ? 'check' : 'times'; ?>-circle <?php echo (!empty($profile['tagline']) || !empty($profile['business_name'])) ? 'text-success' : 'text-muted'; ?> me-2"></i>
                                            <span><?php echo $user_type === 'creative' ? 'Professional Tagline' : 'Business Name'; ?></span>
                                        </li>
                                        <li class="<?php echo (!empty($profile['bio']) || !empty($profile['business_description'])) ? 'completed' : ''; ?>">
                                            <i class="fas fa-<?php echo (!empty($profile['bio']) || !empty($profile['business_description'])) ? 'check' : 'times'; ?>-circle <?php echo (!empty($profile['bio']) || !empty($profile['business_description'])) ? 'text-success' : 'text-muted'; ?> me-2"></i>
                                            <span>Description</span>
                                        </li>
                                        <li class="<?php echo ($user_type === 'creative' && !empty($skills)) ? 'completed' : ''; ?>">
                                            <i class="fas fa-<?php echo ($user_type === 'creative' && !empty($skills)) ? 'check' : 'times'; ?>-circle <?php echo ($user_type === 'creative' && !empty($skills)) ? 'text-success' : 'text-muted'; ?> me-2"></i>
                                            <span>Skills</span>
                                        </li>
                                        <li class="<?php echo !empty($user['avatar_url']) ? 'completed' : ''; ?>">
                                            <i class="fas fa-<?php echo !empty($user['avatar_url']) ? 'check' : 'times'; ?>-circle <?php echo !empty($user['avatar_url']) ? 'text-success' : 'text-muted'; ?> me-2"></i>
                                            <span>Profile Photo</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="profile-card mb-4">
                            <div class="card-header">
                                <h6 class="card-title">Statistics</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($user_type === 'creative'): ?>
                                    <div class="stat-item-large">
                                        <div class="stat-icon">
                                            <i class="fas fa-briefcase"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-number"><?php echo $profile['completed_projects'] ?? 0; ?></div>
                                            <div class="stat-label">Completed Projects</div>
                                        </div>
                                    </div>
                                    <div class="stat-item-large">
                                        <div class="stat-icon">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-number"><?php echo number_format($profile['rating'] ?? 0, 1); ?></div>
                                            <div class="stat-label">Rating</div>
                                        </div>
                                    </div>
                                    <div class="stat-item-large">
                                        <div class="stat-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-number"><?php echo ($profile['is_available'] ?? 1) ? 'Available' : 'Busy'; ?></div>
                                            <div class="stat-label">Status</div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-store fa-3x text-muted mb-3"></i>
                                        <p class="text-muted mb-0">UMKM statistics will be displayed here</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Tips Card -->
                        <div class="profile-card">
                            <div class="card-header">
                                <h6 class="card-title">Profile Tips</h6>
                            </div>
                            <div class="card-body">
                                <div class="tips-list">
                                    <div class="tip-item">
                                        <div class="tip-icon">
                                            <i class="fas fa-camera"></i>
                                        </div>
                                        <div class="tip-content">
                                            <strong>Professional Profile Photo</strong>
                                            <p>Use a clear and professional photo to build trust</p>
                                        </div>
                                    </div>
                                    <div class="tip-item">
                                        <div class="tip-icon">
                                            <i class="fas fa-pen"></i>
                                        </div>
                                        <div class="tip-content">
                                            <strong>Compelling Description</strong>
                                            <p>Clearly explain your unique value and offerings</p>
                                        </div>
                                    </div>
                                    <div class="tip-item">
                                        <div class="tip-icon">
                                            <i class="fas fa-tags"></i>
                                        </div>
                                        <div class="tip-content">
                                            <strong>Relevant Skills</strong>
                                            <p>Select skills that match your abilities and experience</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Avatar Crop Modal -->
    <div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="avatarModalLabel">
                        <i class="fas fa-crop-alt me-2"></i>Edit Profile Photo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="crop-container">
                        <div class="crop-preview-container">
                            <div class="crop-preview">
                                <img id="cropImage" src="" alt="Preview" style="max-width: 100%;">
                            </div>
                        </div>
                        <div class="crop-controls mt-3">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <label for="avatarInput" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-folder-open me-1"></i>Choose Photo
                                    </label>
                                    <input type="file" id="avatarInput" accept="image/*" style="display: none;">
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="zoomIn">
                                            <i class="fas fa-search-plus"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="zoomOut">
                                            <i class="fas fa-search-minus"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="rotateLeft">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="rotateRight">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="cropSave">
                        <i class="fas fa-check me-1"></i>Save Photo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script src="profile.js"></script>
</body>
</html>