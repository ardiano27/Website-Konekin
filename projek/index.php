    <?php
    // index.php
    require_once 'config/Database.php';
    require_once 'models/Users.php';

    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    $message = '';
    $current_user = null;

    if ($_POST) {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'create':
                    $name = $_POST['name'] ?? '';
                    $email = $_POST['email'] ?? '';
                    $role = $_POST['role'] ?? 'user'; 
                    
                    if ($name && $email) {
                        if ($user->create($name, $email, $role)) {
                            $message = "User berhasil dibuat";
                        } else {
                            $message = "gagal membuat user!";
                        }
                    } else {
                        $message = "Name dan email harus diisi";
                    }
                    break; 
                    
                case 'update':
                    $id = $_POST['id'] ?? '';
                    $name = $_POST['name'] ?? '';
                    $email = $_POST['email'] ?? '';
                    $role = $_POST['role'] ?? '';
                    
                    if ($id && $name && $email) {
                        if ($user->update($id, $name, $email, $role)) {
                            $message = "User berhasil diupdate";
                        } else {
                            $message = "gagal update user!";
                        }
                    } else {
                        $message = "All fields are required for update!";
                    }
                    break;
                    
                case 'delete':
                    $id = $_POST['id'] ?? '';
                    if ($id) {
                        if ($user->delete($id)) {
                            $message = "User berhasil didelete";
                        } else {
                            $message = "gagal delete user";
                        }
                    }
                    break;
                    
                case 'edit':
                    $id = $_POST['id'] ?? '';
                    $users = $user->read();
                    foreach ($users as $u) {
                        if ($u['id'] == $id) {
                            $current_user = $u;
                            break;
                        }
                    }
                    break;
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }

    $users = $user->read();
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management CRUD</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .actions { white-space: nowrap; }
    </style>
</head>
<body>
    <div class="container">
        <h1>User Management</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h2><?php echo $current_user ? 'Edit User' : 'Create New User'; ?></h2>
            <form method="POST">
                <?php if ($current_user): ?>
                    <input type="hidden" name="id" value="<?php echo $current_user['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" 
                           value="<?php echo $current_user ? htmlspecialchars($current_user['name']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo $current_user ? htmlspecialchars($current_user['email']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="role">Role:</label>
                    <input type="text" id="role" name="role" 
                           value="<?php echo $current_user ? htmlspecialchars($current_user['role']) : 'user'; ?>" 
                           required>
                </div>
                
                <button type="submit" name="action" value="<?php echo $current_user ? 'update' : 'create'; ?>">
                    <?php echo $current_user ? 'Update User' : 'Create User'; ?>
                </button>
                
                <?php if ($current_user): ?>
                    <button type="button" onclick="window.location.href='index.php'">Cancel</button>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Users List -->
        <h2>Users List</h2>
        <?php if (empty($users)): ?>
            <p>No users found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user_item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user_item['id']); ?></td>
                        <td><?php echo htmlspecialchars($user_item['name']); ?></td>
                        <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                        <td><?php echo htmlspecialchars($user_item['role']); ?></td>
                        <td class="actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?php echo $user_item['id']; ?>">
                                <button type="submit" name="action" value="edit">Edit</button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                <input type="hidden" name="id" value="<?php echo $user_item['id']; ?>">
                                <button type="submit" name="action" value="delete" style="background-color: #dc3545;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>