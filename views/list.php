<?php
require_once 'config/Database.php';
require_once 'models/Users.php';
$database = new Database();
$db = $database->getConnection();
$userModel = new User($db);
$users = $userModel->read();

// Function untuk convert role ke label yang lebih bagus
function getRoleLabel($role) {
    switch ($role) {
        case 'creative_worker':
            return ['label' => '🎨 Creative Worker', 'class' => 'info'];
        case 'umkm':
            return ['label' => '🏪 UMKM', 'class' => 'warning'];
        default:
            return ['label' => $role, 'class' => 'secondary'];
    }
}
?>

<?php include 'header.php'; ?>

<div class="card glass-card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h4 class="mb-0">
            <i class="fas fa-list me-2"></i>Daftar Users
        </h4>
        <a href="create.php" class="btn btn-success">
            <i class="fas fa-plus me-1"></i>Tambah User
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Belum ada users</h5>
                <p class="text-muted">Silakan tambahkan user pertama Anda</p>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Tambah User Pertama
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $index => $user_item): 
                            $roleInfo = getRoleLabel($user_item['role']);
                        ?>
                        <tr>
                            <td><strong><?php echo $index + 1; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <?php echo strtoupper(substr($user_item['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($user_item['name']); ?></div>
                                        <small class="text-muted">ID: <?php echo $user_item['id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $roleInfo['class']; ?>">
                                    <?php echo $roleInfo['label']; ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php 
                                    if (isset($user_item['created_at'])) {
                                        echo date('d M Y', strtotime($user_item['created_at']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </small>
                            </td>
                            <td class="action-buttons">
                                <a href="edit.php?id=<?php echo $user_item['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="../actions/delete.php" class="d-inline">
                                    <input type="hidden" name="id" value="<?php echo $user_item['id']; ?>">
                                    <button type="submit" 
                                            class="btn btn-outline-danger btn-sm" 
                                            title="Hapus"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus user <?php echo htmlspecialchars($user_item['name']); ?>?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>