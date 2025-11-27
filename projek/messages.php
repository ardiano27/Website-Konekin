
<?php
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

// Ambil daftar user lain dengan prepared statement yang lebih aman
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

    // Build query dengan whitelist validation
    $selectParts = ["`id`"];
    
    // Validate display column is in actual columns
    if (in_array($displayCol, $cols)) {
        $selectParts[] = "`{$displayCol}` AS `name`";
    } else {
        $selectParts[] = "`id` AS `name`";
    }
    
    $selectParts[] = in_array('avatar_url', $cols) ? "`avatar_url`" : "'' AS `avatar_url`";
    $selectParts[] = in_array('role', $cols) ? "`role`" : "'' AS `role`";
    $selectParts[] = in_array('user_type', $cols) ? "`user_type`" : "'' AS `user_type`";

    $select = implode(', ', $selectParts);
    $sql = "SELECT {$select} FROM `users` WHERE `id` <> :me ORDER BY `{$displayCol}` LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':me' => $current_user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hitung statistik yang benar
    $total_users = count($users);
    $total_cw = 0;
    $total_umkm = 0;
    foreach ($users as $u) {
        $role = $u['role'] ?? '';
        $user_type = $u['user_type'] ?? '';
        
        if ($role === 'creative_worker' || $user_type === 'creative') {
            $total_cw++;
        } else if ($role === 'umkm' || $user_type === 'umkm') {
            $total_umkm++;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $error_message = "Gagal memuat data user. Silakan refresh halaman.";
    $users = [];
    $total_users = $total_cw = $total_umkm = 0;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages – Konekin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <style>
        body { 
            background-color:#f9fafb; 
            font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            
        }
        .card { 
            border:none; 
            border-radius:12px; 
            box-shadow:0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
        }
        .card:hover {
            transform: translateY(-1px);
        }
        
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed: 72px;
        }

        .main-with-sidebar {
            padding: 20px;
  
            min-height: 100vh;
            transition: all 0.3s ease;  
        }

        .sidebar.collapsed ~ .main-with-sidebar {
            margin-left: var(--sidebar-collapsed);
            width: calc(100% - var(--sidebar-collapsed));
        }

        .chat-container {
            display: flex;
            height: calc(100vh - 140px);
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .users-sidebar {
            width: 350px;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fafafa;
        }

        .chat-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            background: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #fafafa;
            display: flex;
            flex-direction: column;
        }

        .chat-input-container {
            padding: 16px 20px;
            border-top: 1px solid #e5e7eb;
            background: white;
        }

        .users-list-container {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }

        .users-list-container::-webkit-scrollbar {
            width: 4px;
        }

        .users-list-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .users-list-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            background: white;
        }

        .search-box {
            position: relative;
        }

        .search-box .form-control {
            padding-left: 40px;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            height: 40px;
            font-size: 0.9rem;
            background: #f8f9fa;
        }

        .search-box .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 0.9rem;
        }

        .user-item {
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            border-radius: 8px;
            margin-bottom: 4px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: transparent;
        }

        .user-item:hover { 
            background: #f8f9fa;
        }

        .user-item.active {
            background: #549efeff;
            color: white;
        }

        .user-item.active .user-name,
        .user-item.active .user-role {
            color: white;
        }

        .user-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .user-item:hover .user-avatar,
        .user-item.active .user-avatar {
            border-color: rgba(255,255,255,0.8);
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 2px;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.8rem;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10b981;
            border: 2px solid white;
            margin-left: auto;
        }

        .no-users {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .no-users i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .message-bubble {
            max-width: 65%;
            padding: 8px 16px;
            border-radius: 18px;
            margin-bottom: 8px;
            word-wrap: break-word;
            position: relative;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-sent {
            background: #549efeff;
            color: white;
            margin-left: auto;
            margin-right: 0;
            border-bottom-right-radius: 4px;
        }

        .message-received {
            background: white;
            margin-left: 0;
            margin-right: auto;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .message-time {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.8);
            margin-top: 4px;
            text-align: right;
        }

        .message-received .message-time {
            text-align: left;
            color: #9ca3af;
        }

        .connection-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 0.9rem;
            z-index: 1000;
            display: none;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .connection-status.online {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .connection-status.offline {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .file-upload-btn {
            background: transparent;
            border: none;
            color: #6b7280;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-btn:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .send-btn {
            background: #549efeff;
            border: none;
            color: white;
            border-radius: 8px;
            padding: 8px 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .send-btn:hover {
            background: #2474dbff;
            transform: scale(1.05);
        }

        .file-preview {
            max-width: 200px;
            margin: 8px 0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .file-message {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px;
            margin: 8px 0;
            max-width: 280px;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-icon {
            font-size: 1.5rem;
            color: #667eea;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            margin-bottom: 4px;
            word-break: break-all;
            font-size: 0.9rem;
        }

        .file-size {
            font-size: 0.75rem;
            color: #666;
        }

        .download-btn {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.7rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .download-btn:hover {
            background: #5a6fd8;
            color: white;
        }

        .image-message {
            max-width: 250px;
            border-radius: 12px;
            margin: 8px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .image-message:hover {
            transform: scale(1.02);
        }

        .upload-progress {
            width: 100%;
            height: 3px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
        }

        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        /* Typing indicator */
        .typing-indicator {
            display: inline-flex;
            align-items: center;
            background: #f1f3f4;
            padding: 8px 12px;
            border-radius: 18px;
            margin-bottom: 8px;
            max-width: fit-content;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #667eea;
            margin: 0 1px;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typing {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }

        .modal-image {
            max-width: 100%;
            max-height: 80vh;
            border-radius: 12px;
        }

        .empty-chat-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6b7280;
            text-align: center;
        }

        .empty-chat-state i {
            font-size: 4rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .chat-input-area {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            background: white;
            border-radius: 24px;
            padding: 8px 8px 8px 16px;
            border: 1px solid #e5e7eb;
        }

        .chat-input-area:focus-within {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }

        .chat-textarea {
            border: none;
            resize: none;
            flex: 1;
            outline: none;
            font-size: 0.95rem;
            line-height: 1.4;
            max-height: 120px;
        }

        .chat-textarea::placeholder {
            color: #9ca3af;
        }

        .action-buttons {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .main-with-sidebar {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
            
            .chat-container {
                height: calc(100vh - 120px);
                flex-direction: column;
            }
            
            .users-sidebar {
                width: 100%;
                height: 40%;
                border-right: none;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .message-bubble {
                max-width: 85%;
            }
            
            .image-message {
                max-width: 200px;
            }
        }

        /* Message status indicators */
        .message-status {
            font-size: 0.6rem;
            margin-left: 4px;
            opacity: 0.7;
        }

        /* Timestamp separators */
        .timestamp-separator {
            text-align: center;
            margin: 16px 0;
            position: relative;
        }

        .timestamp-separator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
            z-index: 1;
        }

        .timestamp-text {
            background: #f3f4f6;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            color: #6b7280;
            display: inline-block;
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
  <!-- Navbar -->
  <?php include "dashboard-sidebar.php"; ?>
<div id="connectionStatus" class="connection-status">
    <i class="fas fa-circle"></i> <span id="statusText">Connecting...</span>
</div>


<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview Gambar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="modal-image" alt="Preview">
            </div>
        </div>
    </div>
</div>

<main class="main-with-sidebar">
    <div class="container-fluid">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div><?= htmlspecialchars($error_message) ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="chat-container">
            <!-- Sidebar kiri: daftar percakapan -->
            <div class="users-sidebar">
                <div class="sidebar-header">
                    <h5 class="mb-3 fw-bold text-dark">Pesan</h5>
                    <div class="search-box">
                        <input type="text" id="userSearch" placeholder="Cari..." class="form-control">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                </div>

                <div class="users-list-container">
                    <ul id="userList" class="list-unstyled mb-0">
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $u): ?>
                                <li class="user-item" 
                                    data-user-id="<?= htmlspecialchars($u['id']) ?>"
                                    data-user-name="<?= htmlspecialchars($u['name']) ?>"
                                    data-user-role="<?= htmlspecialchars($u['role'] ?? $u['user_type'] ?? '') ?>">
                                    <div class="position-relative">
                                        <img src="<?= htmlspecialchars($u['avatar_url'] ?: 'https://via.placeholder.com/56') ?>" 
                                             class="user-avatar" 
                                             alt="avatar"
                                             onerror="this.src='https://via.placeholder.com/56'">
                                        <div class="user-status"></div>
                                    </div>
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($u['name']) ?></div>
                                        <div class="user-role">
                                            <?= htmlspecialchars($u['role'] ?? $u['user_type'] ?? 'User') ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-users">
                                <i class="fas fa-users"></i>
                                <p class="mb-0">Tidak ada user lain</p>
                            </div>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Area kanan: chat -->
            <div class="chat-area">
                <div class="chat-header">
                    <div id="currentChatUser" class="d-flex align-items-center gap-3">
                        <div id="chatWithLabel" class="fs-5 fw-semibold text-dark">
                            <i class="fas fa-comments me-2"></i>Pilih percakapan
                        </div>
                    </div>
                </div>

                <div id="chatMessages" class="chat-messages">
                    <div class="empty-chat-state">
                        <i class="fas fa-comment-dots"></i>
                        <h5 class="mb-2">Pilih percakapan</h5>
                        <p class="text-muted">Pilih dari daftar untuk memulai percakapan</p>
                    </div>
                </div>

                <div id="chatStatus" class="alert alert-warning mx-3 mb-3" style="display:none"></div>
                
                <div class="chat-input-container">
                    <div class="chat-input-area">
                        <textarea id="chatText" class="chat-textarea" rows="1" placeholder="Ketik pesan..." maxlength="5000"></textarea>
                        <div class="action-buttons">
                            <button type="button" class="file-upload-btn">
                                <i class="fas fa-paperclip"></i>
                                <input type="file" id="fileInput" style="display: none;" multiple accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar">
                            </button>
                            <button id="chatSend" class="send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                    <div id="filePreview" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="sidebar.js"></script>

<script>
console.log('Chat script loaded');

document.addEventListener('DOMContentLoaded', function () {
  console.log('DOM loaded, initializing chat...');
  
  const currentUserId = <?= (int)$current_user_id ?>;
  let pollingInterval = null;
  let currentChatUser = null;
  let pollMultiplier = 1;
  let lastActivityTime = Date.now();
  let isOnline = navigator.onLine;
  
  const FETCH_URL = 'fetch_message.php';
  const SEND_URL = 'send_message.php';
  const UPLOAD_URL = 'upload_message.php';
  const BASE_POLL_INTERVAL = 5000;

  const lastMessageIds = {};
  const historyKeyPrefix = 'chat_history_' + currentUserId + '_';
  const renderedMessageIds = new Set();
  let selectedFiles = [];

  console.log('Current user ID:', currentUserId);

  // === SEARCH FUNCTIONALITY ===
  function initializeSearch() {
    const searchInput = document.getElementById('userSearch');
    const userItems = document.querySelectorAll('#userList .user-item');
    const noUsersElement = document.querySelector('.no-users');
    
    console.log('Initializing search with', userItems.length, 'users');
    
    searchInput.addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase().trim();
      console.log('Searching for:', searchTerm);
      
      let visibleCount = 0;
      
      userItems.forEach(item => {
        const userName = item.dataset.userName.toLowerCase();
        const userRole = item.dataset.userRole.toLowerCase();
        
        console.log('Checking user:', userName, 'role:', userRole);
        
        if (userName.includes(searchTerm) || userRole.includes(searchTerm) || searchTerm === '') {
          item.style.display = 'flex';
          visibleCount++;
          console.log('User visible:', userName);
        } else {
          item.style.display = 'none';
          console.log('User hidden:', userName);
        }
      });
      
      console.log('Visible users:', visibleCount);
      
      // Handle no results message
      const userList = document.getElementById('userList');
      let noResultsMsg = userList.querySelector('.no-users-found');
      
      if (visibleCount === 0 && searchTerm !== '') {
        if (!noResultsMsg) {
          noResultsMsg = document.createElement('div');
          noResultsMsg.className = 'no-users-found';
          noResultsMsg.innerHTML = `
            <div class="text-center py-4">
              <i class="fas fa-search fa-2x mb-3 text-muted"></i>
              <p class="text-muted mb-0">Tidak ada user yang cocok dengan "<strong>${searchTerm}</strong>"</p>
            </div>
          `;
          userList.appendChild(noResultsMsg);
        }
      } else if (noResultsMsg) {
        noResultsMsg.remove();
      }
      
      // Show/hide original "no users" message
      if (noUsersElement) {
        if (visibleCount === 0 && searchTerm === '') {
          noUsersElement.style.display = 'block';
        } else {
          noUsersElement.style.display = 'none';
        }
      }
    });
    
    // Clear search when page loads
    searchInput.value = '';
  }

  // === FILE UPLOAD FUNCTIONALITY ===
  function initializeFileUpload() {
    const fileInput = document.getElementById('fileInput');
    const fileUploadBtn = document.querySelector('.file-upload-btn');
    const filePreview = document.getElementById('filePreview');

    fileUploadBtn.addEventListener('click', function() {
      fileInput.click();
    });

    fileInput.addEventListener('change', function(e) {
      selectedFiles = Array.from(e.target.files);
      displayFilePreview();
    });

    function displayFilePreview() {
      filePreview.innerHTML = '';
      
      selectedFiles.forEach((file, index) => {
        const fileElement = document.createElement('div');
        fileElement.className = 'file-message mb-2';
        
        if (file.type.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = function(e) {
            fileElement.innerHTML = `
              <div class="file-info">
                <i class="fas fa-image file-icon"></i>
                <div class="file-details">
                  <div class="file-name">${file.name}</div>
                  <div class="file-size">${formatFileSize(file.size)}</div>
                </div>
                <button type="button" class="btn btn-sm btn-danger remove-file" data-index="${index}">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <img src="${e.target.result}" class="file-preview mt-2" alt="Preview">
            `;
          };
          reader.readAsDataURL(file);
        } else {
          fileElement.innerHTML = `
            <div class="file-info">
              <i class="fas fa-file file-icon"></i>
              <div class="file-details">
                <div class="file-name">${file.name}</div>
                <div class="file-size">${formatFileSize(file.size)}</div>
              </div>
              <button type="button" class="btn btn-sm btn-danger remove-file" data-index="${index}">
                <i class="fas fa-times"></i>
              </button>
            </div>
          `;
        }
        
        filePreview.appendChild(fileElement);
      });

      // Add remove file functionality
      document.querySelectorAll('.remove-file').forEach(btn => {
        btn.addEventListener('click', function() {
          const index = parseInt(this.dataset.index);
          selectedFiles.splice(index, 1);
          displayFilePreview();
        });
      });
    }

    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
  }

  // === AUTO-RESIZE TEXTAREA ===
  function initializeAutoResize() {
    const textarea = document.getElementById('chatText');
    
    textarea.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
  }

  // === SEND MESSAGE WITH FILES ===
  async function sendMessage() {
    resetActivity();
    
    const receiver = sessionStorage.getItem('receiver_id');
    const textEl = document.getElementById('chatText');
    const msg = textEl.value.trim();
    
    if (!receiver) {
      alert('Pilih user terlebih dahulu');
      return;
    }
    
    if (!msg && selectedFiles.length === 0) return;

    if (!isOnline) {
      alert('Anda sedang offline. Periksa koneksi internet Anda.');
      return;
    }

    try {
      // If there are files, upload them first
      let fileUrls = [];
      if (selectedFiles.length > 0) {
        fileUrls = await uploadFiles(selectedFiles);
      }

      // Send message with file URLs
      const fd = new FormData();
      fd.append('receiver_id', receiver);
      fd.append('message_text', msg);
      if (fileUrls.length > 0) {
        fd.append('attachment_urls', JSON.stringify(fileUrls));
      }

      const tempId = 'temp_' + Date.now();
      const tempMessage = {
        id: tempId,
        sender_id: currentUserId,
        receiver_id: receiver,
        message_text: msg,
        attachment_urls: fileUrls,
        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
      };
      
      renderMessage(tempMessage, true);
      textEl.value = '';
      textEl.style.height = 'auto';
      selectedFiles = [];
      document.getElementById('filePreview').innerHTML = '';

      const response = await fetch(SEND_URL, { 
        method: 'POST', 
        body: fd, 
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      
      const data = await response.json();
      
      if (data.error) {
        alert(data.error);
        renderedMessageIds.delete(tempId);
        const tempEl = document.querySelector(`[data-mid="${tempId}"]`);
        if (tempEl) tempEl.remove();
        return;
      }

      renderedMessageIds.delete(tempId);
      const tempEl = document.querySelector(`[data-mid="${tempId}"]`);
      if (tempEl) tempEl.remove();
      
      const realMessage = data.message || tempMessage;
      renderMessage(realMessage, true);
      
      const saved = getSavedHistory(receiver);
      saved.push(realMessage);
      saveHistory(receiver, saved);
      updateLastMessageId(receiver, [realMessage]);
      
    } catch (err) {
      console.error('Send error:', err);
      alert('Gagal mengirim pesan. Coba lagi.');
    }
  }

  async function uploadFiles(files) {
    const fileUrls = [];
    
    for (const file of files) {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('type', 'message_attachment');

      try {
        const response = await fetch(UPLOAD_URL, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (result.success) {
          fileUrls.push(result.fileUrl);
        } else {
          console.error('Upload failed:', result.error);
        }
      } catch (error) {
        console.error('Upload error:', error);
      }
    }
    
    return fileUrls;
  }

  // === RENDER MESSAGE WITH FILES ===
  function renderMessage(message, append = true) {
    const chatBox = document.getElementById('chatMessages');
    if (!chatBox) return;

    const mid = generateMessageId(message);
    if (renderedMessageIds.has(mid)) return;

    renderedMessageIds.add(mid);
    
    const isMe = parseInt(message.sender_id, 10) === currentUserId;
    const messageDiv = document.createElement('div');
    messageDiv.className = 'd-flex mb-2 ' + (isMe ? 'justify-content-end' : 'justify-content-start');
    messageDiv.setAttribute('data-mid', mid);

    const bubbleClass = isMe ? 'message-bubble message-sent' : 'message-bubble message-received';
    
    let attachmentsHtml = '';
    if (message.attachment_urls && message.attachment_urls.length > 0) {
      message.attachment_urls.forEach(url => {
        if (url.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
          // Image file
          attachmentsHtml += `
            <div class="mb-2">
              <img src="${url}" class="image-message" alt="Attachment" onclick="openImageModal('${url}')">
            </div>
          `;
        } else {
          // Other file types
          const fileName = url.split('/').pop();
          attachmentsHtml += `
            <div class="file-message mb-2">
              <div class="file-info">
                <i class="fas fa-file file-icon"></i>
                <div class="file-details">
                  <div class="file-name">${fileName}</div>
                  <div class="file-size">File</div>
                </div>
                <a href="${url}" class="download-btn" download>
                  <i class="fas fa-download"></i>
                </a>
              </div>
            </div>
          `;
        }
      });
    }
    
    messageDiv.innerHTML = `
      <div class="${bubbleClass}">
        ${attachmentsHtml}
        ${message.message_text ? `<div style="white-space:pre-wrap">${escapeHtml(message.message_text || '')}</div>` : ''}
        <div class="message-time">${escapeHtml(message.created_at || '')}</div>
      </div>
    `;

    if (append) {
      chatBox.appendChild(messageDiv);
    } else {
      chatBox.insertBefore(messageDiv, chatBox.firstChild);
    }
    
    chatBox.scrollTop = chatBox.scrollHeight;
  }

  // === IMAGE MODAL ===
  window.openImageModal = function(imageUrl) {
    const modalImage = document.getElementById('modalImage');
    modalImage.src = imageUrl;
    const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
    imageModal.show();
  }

  // === UTILITY FUNCTIONS ===
  function getSavedHistory(otherUserId) {
    try {
      const raw = localStorage.getItem(historyKeyPrefix + otherUserId);
      return raw ? JSON.parse(raw) : [];
    } catch (e) { 
      console.error('getSavedHistory error:', e);
      return []; 
    }
  }

  function saveHistory(otherUserId, messages) {
    try {
      const limited = messages.slice(-100);
      localStorage.setItem(historyKeyPrefix + otherUserId, JSON.stringify(limited));
    } catch (e) { 
      console.error('saveHistory error:', e);
      if (e.name === 'QuotaExceededError') {
        localStorage.removeItem(historyKeyPrefix + otherUserId);
      }
    }
  }

  function clearChatUI() {
    const chatBox = document.getElementById('chatMessages');
    if (chatBox) chatBox.innerHTML = '';
    renderedMessageIds.clear();
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function generateMessageId(message) {
    return String(message.id || message.message_id || message.uuid || 'temp_' + Date.now() + '_' + Math.random());
  }

  function renderMessages(messages, clearFirst = false) {
    const chatBox = document.getElementById('chatMessages');
    if (!chatBox) return;

    if (clearFirst) {
      chatBox.innerHTML = '';
      renderedMessageIds.clear();
    }

    messages.forEach(msg => renderMessage(msg, true));
  }

  function updateLastMessageId(otherUserId, messages) {
    if (!Array.isArray(messages) || messages.length === 0) return;
    
    let maxId = lastMessageIds[otherUserId] || 0;
    messages.forEach(m => {
      const id = Number(m.id || 0);
      if (id > maxId) maxId = id;
    });
    lastMessageIds[otherUserId] = maxId;
  }

  function resetActivity() {
    lastActivityTime = Date.now();
    pollMultiplier = 1;
  }

  // === LOAD MESSAGES ===
  function loadMessages(otherUserId, isInitial = false) {
    if (!otherUserId || !isOnline) return;

    const sinceParam = (!isInitial && lastMessageIds[otherUserId]) 
      ? '&since_id=' + lastMessageIds[otherUserId] 
      : '';

    const url = FETCH_URL + '?other_user=' + otherUserId + sinceParam;

    fetch(url, { 
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => {
      if (!res.ok) throw new Error('Fetch failed ' + res.status);
      return res.json();
    })
    .then(data => {
      const messages = data.messages || [];
      
      if (isInitial) {
        const saved = getSavedHistory(otherUserId);
        const allMessages = [...saved, ...messages];
        
        const uniqueMap = new Map();
        allMessages.forEach(m => {
          const id = generateMessageId(m);
          uniqueMap.set(id, m);
        });
        
        const merged = Array.from(uniqueMap.values())
          .sort((a, b) => (Number(a.id || 0) - Number(b.id || 0)));
        
        renderMessages(merged, true);
        saveHistory(otherUserId, merged);
        updateLastMessageId(otherUserId, merged);
      } else {
        if (messages.length > 0) {
          messages.forEach(msg => renderMessage(msg, true));
          
          const saved = getSavedHistory(otherUserId);
          const combined = [...saved, ...messages];
          
          const uniqueMap = new Map();
          combined.forEach(m => {
            const id = generateMessageId(m);
            uniqueMap.set(id, m);
          });
          
          const merged = Array.from(uniqueMap.values())
            .sort((a, b) => (Number(a.id || 0) - Number(b.id || 0)));
          
          saveHistory(otherUserId, merged);
          updateLastMessageId(otherUserId, messages);
        }
      }

      const status = document.getElementById('chatStatus');
      if (status) {
        status.style.display = 'none';
        status.textContent = '';
      }
    })
    .catch(err => {
      console.error('loadMessages error:', err);
      const status = document.getElementById('chatStatus');
      if (status) {
        status.style.display = 'block';
        status.textContent = 'Gagal memuat pesan: ' + err.message;
      }
    });
  }

  // === POLLING ===
  function startPolling(userId) {
    if (pollingInterval) {
      clearInterval(pollingInterval);
    }
    
    currentChatUser = userId;
    pollMultiplier = 1;
    lastActivityTime = Date.now();
    
    loadMessages(userId, true);
    
    pollingInterval = setInterval(() => {
      if (currentChatUser === userId && isOnline) {
        const idleTime = Date.now() - lastActivityTime;
        
        if (idleTime > 300000) {
          pollMultiplier = 3;
        } else if (idleTime > 60000) {
          pollMultiplier = 2;
        } else {
          pollMultiplier = 1;
        }
        
        loadMessages(userId, false);
      }
    }, BASE_POLL_INTERVAL * pollMultiplier);
  }

  function stopPolling() {
    if (pollingInterval) {
      clearInterval(pollingInterval);
      pollingInterval = null;
    }
    currentChatUser = null;
  }

  // === USER SELECTION ===
  function setupUserSelection() {
    document.querySelectorAll('.user-item').forEach(item => {
      item.addEventListener('click', function () {
        const userId = this.dataset.userId;
        if (!userId) return;

        document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
        this.classList.add('active');
        
        const userName = this.querySelector('.user-name').textContent.trim();
        document.getElementById('chatWithLabel').innerHTML = `<i class="fas fa-comments me-2"></i>${userName}`;

        sessionStorage.setItem('receiver_id', userId);

        // Remove empty state
        const emptyState = document.querySelector('.empty-chat-state');
        if (emptyState) {
          emptyState.style.display = 'none';
        }

        const saved = getSavedHistory(userId);
        if (saved.length > 0) {
          renderMessages(saved, true);
          updateLastMessageId(userId, saved);
        } else {
          clearChatUI();
          document.getElementById('chatMessages').innerHTML = `
            <div class="empty-chat-state">
              <i class="fas fa-comment-dots"></i>
              <h5 class="mb-2">Mulai percakapan</h5>
              <p class="text-muted">Kirim pesan untuk memulai percakapan dengan ${userName}</p>
            </div>
          `;
        }

        startPolling(userId);
        resetActivity();
      });
    });
  }

  // === CONNECTION STATUS ===
  function updateConnectionStatus(online) {
    isOnline = online;
    const statusEl = document.getElementById('connectionStatus');
    const statusText = document.getElementById('statusText');
    
    if (online) {
      statusEl.className = 'connection-status online';
      statusText.textContent = 'Online';
      statusEl.style.display = 'block';
      setTimeout(() => { statusEl.style.display = 'none'; }, 2000);
    } else {
      statusEl.className = 'connection-status offline';
      statusText.textContent = 'Offline';
      statusEl.style.display = 'block';
    }
  }

  window.addEventListener('online', () => {
    updateConnectionStatus(true);
    if (currentChatUser) {
      loadMessages(currentChatUser, false);
    }
  });

  window.addEventListener('offline', () => updateConnectionStatus(false));

  // === INITIALIZE EVERYTHING ===
  function initializeChat() {
    // Initialize search
    initializeSearch();
    
    // Initialize file upload
    initializeFileUpload();
    
    // Initialize auto-resize textarea
    initializeAutoResize();
    
    // Setup user selection
    setupUserSelection();
    
    // Setup send message
    document.getElementById('chatSend').addEventListener('click', sendMessage);
    
    document.getElementById('chatText').addEventListener('keypress', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Restore conversation if exists
    const savedReceiver = sessionStorage.getItem('receiver_id');
    if (savedReceiver) {
      const userItem = document.querySelector(`.user-item[data-user-id="${savedReceiver}"]`);
      if (userItem) {
        userItem.click();
      }
    }

    // Event listeners
    window.addEventListener('beforeunload', stopPolling);
    
    document.addEventListener('visibilitychange', function() {
      if (document.hidden) {
        stopPolling();
      } else if (currentChatUser) {
        startPolling(currentChatUser);
      }
    });

    console.log('Chat initialization complete');
  }

  // Start the chat application
  initializeChat();
});
</script>
  
</body>
</html>
