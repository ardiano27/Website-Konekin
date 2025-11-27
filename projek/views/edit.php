<?php
    require_once 'config/Database.php';
    require_once 'models/Users.php';
    $database = new Database();
    $db = $database->getConnection();
    $userModel = new User($db);

    $user = null;
    if (isset($_GET['id'])) {
        $user = $userModel->getById($_GET['id']);
    }

    if (!$user) {
        header("Location: ../index.php?message=User+tidak+ditemukan&type=error");
        exit;
    }

    include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card glass-card">
            <div class="card-header bg-white">
                <h4 class="mb-0">
                    <i class="fas fa-edit me-2"></i>Edit User
                </h4>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['message'])): ?>
                    <div class="alert alert-<?php echo $_GET['type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="../actions/update.php">
                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Pilih Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">-- Pilih Role --</option>
                            <option value="creative_worker" <?php echo $user['role'] === 'creative_worker' ? 'selected' : ''; ?>>
                                🎨 Creative Worker
                            </option>
                            <option value="umkm" <?php echo $user['role'] === 'umkm' ? 'selected' : ''; ?>>
                                🏪 UMKM
                            </option>
                        </select>
                        <div class="form-text">
                            <small>
                                <strong>Creative Worker:</strong> Desainer, programmer, content creator, dll.<br>
                                <strong>UMKM:</strong> Usaha Mikro, Kecil, dan Menengah.
                            </small>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="../index.php" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-arrow-left me-1"></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>