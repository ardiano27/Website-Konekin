<?php
// File: messages.php

session_start();

// Regenerate session ID untuk security
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Pastikan user login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$current_user_id = (int) $_SESSION['user_id'];

// Koneksi DB dan navbar
require_once __DIR__ . '/config/Database.php';
$db = new DatabaseConnection();
$pdo = $db->getConnection();

// Error handling untuk DB connection
if (!$pdo) {
    die("Database connection failed. Please try again later.");
}

require_once __DIR__ . '/views/navbar.php';

// Ambil daftar user lain dengan last message info
try {
    // Whitelist kolom yang diizinkan untuk display name
    $allowedDisplayColumns = ['full_name', 'name', 'username', 'email', 'display_name', 'first_name'];
    
    $colsStmt = $pdo->query("SHOW COLUMNS FROM `users`");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $displayCol = 'id'; // default
    foreach ($allowedDisplayColumns as $candidate) {
        if (in_array($candidate, $cols)) {
            $displayCol = $candidate;
            break;
        }
    }

    // Query untuk mengambil user yang memiliki interaksi dengan user saat ini
    // DAN mengambil pesan terakhir untuk setiap percakapan
    $sql = "
        SELECT 
            u.id, 
            u.$displayCol AS display_name, 
            u.avatar_url AS profile_picture_url,
            last_msg.message_text AS last_message,
            last_msg.created_at AS last_message_time,
            last_msg.sender_id AS last_message_sender,
            SUM(CASE WHEN m.receiver_id = :current_user_id AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
        FROM users u
        LEFT JOIN (
            SELECT 
                m1.sender_id,
                m1.receiver_id, 
                m1.message_text,
                m1.created_at,
                m1.id
            FROM messages m1
            INNER JOIN (
                SELECT 
                    GREATEST(sender_id, receiver_id) AS user1,
                    LEAST(sender_id, receiver_id) AS user2,
                    MAX(created_at) AS max_time
                FROM messages 
                WHERE :current_user_id IN (sender_id, receiver_id)
                GROUP BY GREATEST(sender_id, receiver_id), LEAST(sender_id, receiver_id)
            ) m2 ON (
                (m1.sender_id = m2.user1 AND m1.receiver_id = m2.user2) OR 
                (m1.sender_id = m2.user2 AND m1.receiver_id = m2.user1)
            ) AND m1.created_at = m2.max_time
        ) last_msg ON (
            (last_msg.sender_id = u.id AND last_msg.receiver_id = :current_user_id) OR 
            (last_msg.sender_id = :current_user_id AND last_msg.receiver_id = u.id)
        )
        LEFT JOIN messages m ON (
            (m.sender_id = u.id AND m.receiver_id = :current_user_id) OR 
            (m.sender_id = :current_user_id AND m.receiver_id = u.id)
        )
        WHERE u.id != :current_user_id
        AND (
            -- User terlibat di proposals (sebagai creative_user_id)
            u.id IN (SELECT creative_user_id FROM proposals p 
                     JOIN projects pr ON p.project_id = pr.id 
                     WHERE pr.umkm_user_id = :current_user_id)
            OR
            -- User terlibat di projects (sebagai umkm_user_id)
            u.id IN (SELECT umkm_user_id FROM projects pr
                     JOIN proposals p ON pr.id = p.project_id
                     WHERE p.creative_user_id = :current_user_id)
            -- Atau berdasarkan pesan yang sudah ada
            OR
            u.id IN (SELECT DISTINCT sender_id FROM messages WHERE receiver_id = :current_user_id)
            OR
            u.id IN (SELECT DISTINCT receiver_id FROM messages WHERE sender_id = :current_user_id)
        )
        GROUP BY u.id, u.$displayCol, u.avatar_url, last_msg.message_text, last_msg.created_at, last_msg.sender_id
        ORDER BY last_msg.created_at DESC, u.$displayCol ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':current_user_id', $current_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log error
    error_log("Database Error in messages.php: " . $e->getMessage());
    $users = [];
    $error_message = "Terjadi kesalahan database. Silakan coba lagi.";
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konekin Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/messages.css">
    <style>
        /* Additional inline styles for wallpaper functionality */
        .wallpaper-default { background-color: white; }
        .wallpaper-gradient { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; 
        }
        .wallpaper-pattern { 
            background-color: #f0f0f0;
            background-image: radial-gradient(#cccccc 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .wallpaper-dark { 
            background-color: #1a1a1a !important;
            color: white;
        }
        
        /* Settings modal styles */
        .settings-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .settings-content {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .wallpaper-option {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            margin: 5px;
            cursor: pointer;
            border: 2px solid transparent;
            background-size: cover;
        }
        
        .wallpaper-option.active {
            border-color: #0095f6;
        }
    </style>
</head>
<body>

<!-- Hidden input untuk current user ID -->
<input type="hidden" id="currentUserId" value="<?php echo $current_user_id; ?>">

<div class="container-fluid chat-app">
    <div class="row h-100">
        <!-- Sidebar -->
        <div class="col-md-4 col-lg-3 chat-sidebar p-0">
            <div class="p-3 chat-sidebar-header">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="m-0">Chats</h3>
                    <button class="btn btn-outline-secondary btn-sm" id="settingsBtn" title="Pengaturan">
                        <i class="bi bi-gear-fill"></i>
                    </button>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="userSearch" class="form-control border-start-0" placeholder="Cari user...">
                </div>
            </div>
            <div id="userList" class="user-list">
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <div class="user-item" data-user-id="<?php echo htmlspecialchars($user['id']); ?>">
                            <div class="position-relative">
                                <img src="<?php echo htmlspecialchars($user['profile_picture_url'] ?? 'assets/images/default-avatar.png'); ?>" 
                                     alt="Avatar" class="profile-avatar">
                                <?php if ($user['unread_count'] > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $user['unread_count']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="user-info flex-grow-1">
                                <p class="user-name"><?php echo htmlspecialchars($user['display_name']); ?></p>
                                <p class="user-last-message">
                                    <?php 
                                    if (!empty($user['last_message'])) {
                                        $lastMessage = $user['last_message'];
                                        $isSender = $user['last_message_sender'] == $current_user_id;
                                        $prefix = $isSender ? 'You: ' : '';
                                        echo $prefix . (strlen($lastMessage) > 30 ? substr($lastMessage, 0, 30) . '...' : $lastMessage);
                                    } else {
                                        echo 'No messages yet';
                                    }
                                    ?>
                                </p>
                            </div>
                            <?php if (!empty($user['last_message_time'])): ?>
                                <div class="user-time">
                                    <?php 
                                    $time = strtotime($user['last_message_time']);
                                    $now = time();
                                    $diff = $now - $time;
                                    
                                    if ($diff < 60 * 60 * 24) {
                                        // Today
                                        echo date('H:i', $time);
                                    } else if ($diff < 60 * 60 * 24 * 7) {
                                        // Within a week
                                        echo date('D', $time);
                                    } else {
                                        // Older
                                        echo date('d/m', $time);
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center p-4 text-muted">
                        <i class="bi bi-chat-left-dots" style="font-size: 3rem;"></i>
                        <p class="mt-2">Belum ada interaksi chat.</p>
                        <p class="small">Mulai dengan mencari UMKM atau Creative Worker di halaman Explore.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="col-md-8 col-lg-9 chat-main-col p-0">
            <div id="chatMain" class="chat-main">
                
                <!-- Chat Header -->
                <div id="chatHeader" class="chat-header p-3 border-bottom" style="display: none;">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-sm d-md-none me-2" id="backToSidebar">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <img id="headerAvatar" src="assets/images/default-avatar.png" alt="Avatar" class="profile-avatar-sm me-3">
                        <div class="flex-grow-1">
                            <h5 id="headerName" class="m-0">Pilih Pengguna</h5>
                            <small id="headerStatus" class="text-muted">Online</small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="chatOptions" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" id="changeWallpaperBtn">Ganti Wallpaper</a></li>
                                <li><a class="dropdown-item" href="#">Info Kontak</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#">Blokir</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Chat Messages Area -->
                <div id="chatMessages" class="chat-messages">
                    <!-- Default State (when no chat selected) -->
                    <div id="chatDefaultState" class="d-flex flex-column justify-content-center align-items-center h-100 text-center">
                        <i class="bi bi-chat-dots-fill text-primary mb-3" style="font-size: 4rem;"></i>
                        <h5 class="mt-3">Ayo Mulai Chatting</h5>
                        <p class="text-muted">Pilih salah satu pengguna dari daftar di sebelah kiri untuk memulai percakapan.</p>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="loadingIndicator" style="text-align: center; padding: 20px; display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Memuat pesan...</p>
                    </div>
                </div>
                
                <!-- Chat Input Area -->
                <div id="chatInputArea" class="chat-input-area border-top p-3" style="display: none;">
                    <div class="d-flex align-items-end">
                        <!-- Attachment Button -->
                        <button type="button" class="btn btn-outline-secondary me-2 attachment-btn" id="fileAttach" title="Lampirkan File">
                            <i class="bi bi-paperclip"></i>
                        </button>
                        <input type="file" id="fileInput" multiple style="display: none;" 
                               accept="image/*, application/pdf, text/plain, 
                                       application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, 
                                       application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, 
                                       application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation,
                                       application/zip, application/x-rar-compressed">
                        
                        <!-- Message Input -->
                        <div class="flex-grow-1 position-relative">
                            <textarea id="chatText" class="form-control me-2" rows="1" placeholder="Tulis pesan..." style="resize: none;"></textarea>
                            <div class="position-absolute bottom-0 end-0 p-2">
                                <button class="btn btn-sm btn-outline-secondary" id="emojiBtn" type="button">
                                    <i class="bi bi-emoji-smile"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Send Button -->
                        <button id="chatSend" class="btn btn-primary ms-2" title="Kirim Pesan">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                    
                    <!-- File Preview Area -->
                    <div id="filePreview" class="mt-2" style="display: none;">
                        <div class="d-flex align-items-center bg-light rounded p-2">
                            <i class="bi bi-file-earmark me-2"></i>
                            <span id="fileName" class="flex-grow-1"></span>
                            <button class="btn btn-sm btn-outline-danger" id="removeFile">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Connection Status Indicator -->
<div id="connectionStatus" class="connection-status" style="display: none;">
    <span id="statusText"></span>
</div>

<!-- Settings Modal -->
<div id="settingsModal" class="settings-modal">
    <div class="settings-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">Pengaturan Chat</h5>
            <button type="button" class="btn-close" id="closeSettings"></button>
        </div>
        
        <div class="mb-4">
            <h6>Wallpaper Chat</h6>
            <div class="d-flex flex-wrap justify-content-center">
                <div class="wallpaper-option active" data-wallpaper="default" style="background-color: white; border: 1px solid #ddd;"></div>
                <div class="wallpaper-option" data-wallpaper="gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                <div class="wallpaper-option" data-wallpaper="pattern" style="background-color: #f0f0f0; background-image: radial-gradient(#cccccc 1px, transparent 1px); background-size: 20px 20px;"></div>
                <div class="wallpaper-option" data-wallpaper="dark" style="background-color: #1a1a1a;"></div>
            </div>
        </div>
        
        <div class="d-grid gap-2">
            <button class="btn btn-primary" id="saveSettings">Simpan</button>
            <button class="btn btn-outline-secondary" id="cancelSettings">Batal</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/messages.js"></script>
<script>
// Additional JavaScript for enhanced functionality
document.addEventListener('DOMContentLoaded', function() {
    // Settings Modal functionality
    const settingsModal = document.getElementById('settingsModal');
    const settingsBtn = document.getElementById('settingsBtn');
    const closeSettings = document.getElementById('closeSettings');
    const cancelSettings = document.getElementById('cancelSettings');
    const saveSettings = document.getElementById('saveSettings');
    const changeWallpaperBtn = document.getElementById('changeWallpaperBtn');
    const wallpaperOptions = document.querySelectorAll('.wallpaper-option');
    
    let selectedWallpaper = localStorage.getItem('chatWallpaper') || 'default';
    
    // Open settings modal
    function openSettingsModal() {
        settingsModal.style.display = 'flex';
        // Set active wallpaper option
        wallpaperOptions.forEach(option => {
            if (option.dataset.wallpaper === selectedWallpaper) {
                option.classList.add('active');
            } else {
                option.classList.remove('active');
            }
        });
    }
    
    // Close settings modal
    function closeSettingsModal() {
        settingsModal.style.display = 'none';
    }
    
    // Event listeners for settings
    if (settingsBtn) settingsBtn.addEventListener('click', openSettingsModal);
    if (closeSettings) closeSettings.addEventListener('click', closeSettingsModal);
    if (cancelSettings) cancelSettings.addEventListener('click', closeSettingsModal);
    if (changeWallpaperBtn) changeWallpaperBtn.addEventListener('click', openSettingsModal);
    
    // Wallpaper selection
    wallpaperOptions.forEach(option => {
        option.addEventListener('click', function() {
            wallpaperOptions.forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
            selectedWallpaper = this.dataset.wallpaper;
        });
    });
    
    // Save settings
    if (saveSettings) {
        saveSettings.addEventListener('click', function() {
            localStorage.setItem('chatWallpaper', selectedWallpaper);
            // Apply the wallpaper
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                // Remove all wallpaper classes
                chatMessages.classList.remove('wallpaper-default', 'wallpaper-gradient', 'wallpaper-pattern', 'wallpaper-dark');
                // Add selected wallpaper class
                chatMessages.classList.add(`wallpaper-${selectedWallpaper}`);
            }
            closeSettingsModal();
        });
    }
    
    // Back to sidebar on mobile
    const backToSidebar = document.getElementById('backToSidebar');
    if (backToSidebar) {
        backToSidebar.addEventListener('click', function() {
            document.querySelector('.chat-sidebar').classList.remove('d-none');
            document.querySelector('.chat-main-col').classList.add('d-none', 'd-md-block');
        });
    }
    
    // Mobile responsive behavior
    function handleMobileView() {
        if (window.innerWidth < 768) {
            const sidebar = document.querySelector('.chat-sidebar');
            const mainCol = document.querySelector('.chat-main-col');
            
            if (sidebar && mainCol) {
                // Show sidebar by default on mobile
                sidebar.classList.remove('d-none');
                mainCol.classList.add('d-none');
                
                // When a user is selected, hide sidebar and show main
                document.querySelectorAll('.user-item').forEach(item => {
                    item.addEventListener('click', function() {
                        sidebar.classList.add('d-none');
                        mainCol.classList.remove('d-none');
                    });
                });
            }
        }
    }
    
    // Initial call and resize listener
    handleMobileView();
    window.addEventListener('resize', handleMobileView);
    
    // Apply saved wallpaper on load
    const savedWallpaper = localStorage.getItem('chatWallpaper') || 'default';
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.classList.add(`wallpaper-${savedWallpaper}`);
    }
});
</script>
</body>
</html>