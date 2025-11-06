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
$db = new Database();
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
            border-radius:16px; 
            box-shadow:0 8px 25px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed: 72px;
        }

        .main-with-sidebar {
            margin-left: var(--sidebar-width);
            padding: 25px;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .sidebar.collapsed ~ .main-with-sidebar {
            margin-left: var(--sidebar-collapsed);
            width: calc(100% - var(--sidebar-collapsed));
        }

        .chat-card {
            height: calc(100vh - 160px);
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 15px;
            max-height: 400px;
        }

        .users-list-container {
            max-height: 400px;
            overflow-y: auto;
            border-radius: 12px;
        }

        .users-list-container::-webkit-scrollbar {
            width: 6px;
        }

        .users-list-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .users-list-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .chat-input { 
            margin-top: 12px; 
        }

        .left-column {
            padding-left: 0;
            padding-right: 20px;
        }

        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            flex: 1;
            padding: 20px;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            transform: rotate(45deg);
        }

        .stat-card:hover { 
            transform: translateY(-5px) scale(1.02);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 8px;
            position: relative;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .stat-label {
            font-size: 0.9rem;
            font-weight: 500;
            opacity: 0.9;
            position: relative;
        }

        .user-item {
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            margin-bottom: 8px;
            padding: 15px;
            border-left: 4px solid transparent;
        }

        .user-item:hover { 
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            transform: translateX(8px);
            border-left-color: #667eea;
        }

        .user-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-left-color: #ffd700;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .message-bubble {
            max-width: 75%;
            padding: 12px 18px;
            border-radius: 20px;
            margin-bottom: 12px;
            word-wrap: break-word;
            position: relative;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-sent {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-left: auto;
            margin-right: 0;
            border-bottom-right-radius: 5px;
        }

        .message-received {
            background: white;
            margin-left: 0;
            margin-right: auto;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .message-time {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8);
            margin-top: 5px;
            text-align: right;
        }

        .message-received .message-time {
            text-align: left;
            color: #6b7280;
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

        /* Search Section Styles */
        .search-section {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .search-box {
            position: relative;
        }

        .search-box .form-control {
            padding-left: 50px;
            border-radius: 50px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            height: 50px;
            font-size: 1rem;
            background: #f8f9fa;
        }

        .search-box .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 1.1rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
            border: 3px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .user-item:hover .user-avatar,
        .user-item.active .user-avatar {
            border-color: rgba(255,255,255,0.8);
            transform: scale(1.1);
        }

        .user-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #10b981;
            border: 2px solid white;
            position: absolute;
            bottom: 0;
            right: 0;
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

        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 16px 16px 0 0;
        }

        /* File Upload Styles */
        .file-upload-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .file-preview {
            max-width: 200px;
            margin: 10px 0;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .file-message {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 12px;
            padding: 15px;
            margin: 10px 0;
            max-width: 300px;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-icon {
            font-size: 2rem;
            color: #667eea;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            margin-bottom: 5px;
            word-break: break-all;
        }

        .file-size {
            font-size: 0.8rem;
            color: #666;
        }

        .download-btn {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .download-btn:hover {
            background: #5a6fd8;
            color: white;
        }

        .image-message {
            max-width: 300px;
            border-radius: 12px;
            margin: 10px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .image-message:hover {
            transform: scale(1.02);
        }

        .upload-progress {
            width: 100%;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }

        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-with-sidebar {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
            
            .chat-card { 
                height: calc(100vh - 120px); 
                margin-bottom: 15px;
            }
            
            .left-column { 
                padding-right: 0;
                margin-bottom: 20px;
            }
            
            .stats-row { 
                flex-direction: column; 
                gap: 12px; 
            }
            
            .message-bubble {
                max-width: 85%;
            }
            
            .search-section {
                padding: 15px;
            }

            .image-message {
                max-width: 200px;
            }
        }

        /* Loading animation */
        .typing-indicator {
            display: inline-flex;
            align-items: center;
            background: #f1f3f4;
            padding: 10px 15px;
            border-radius: 18px;
            margin-bottom: 10px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #667eea;
            margin: 0 2px;
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
    </style>
</head>
<body>
  <!-- Sidebar -->
  <?php include "dashboard-sidebar.php"; ?>


<!-- Connection Status Indicator -->
<div id="connectionStatus" class="connection-status">
    <i class="fas fa-circle"></i> <span id="statusText">Connecting...</span>
</div>

<!-- Image Preview Modal -->
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

        <div class="row">
            <!-- Kolom kiri: statistik + search + daftar user -->
            <div class="col-md-4">
                <div class="left-column">
                    <!-- Statistik cards -->
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="stat-number"><?= $total_users ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $total_cw ?></div>
                            <div class="stat-label">Creative Workers</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $total_umkm ?></div>
                            <div class="stat-label">UMKM</div>
                        </div>
                    </div>

                    <!-- Search Section -->
                    <div class="search-section">
                        <div class="search-box">
                            <input type="text" id="userSearch" placeholder="Cari user..." class="form-control">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>

                    <!-- Daftar user -->
                    <div class="card p-4">
                        <h6 class="mb-3 fw-bold text-dark">Daftar Pengguna</h6>
                        <div class="users-list-container">
                            <ul id="userList" class="list-group">
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $u): ?>
                                        <li class="list-group-item user-item d-flex align-items-center" 
                                            data-user-id="<?= htmlspecialchars($u['id']) ?>"
                                            data-user-name="<?= htmlspecialchars($u['name']) ?>"
                                            data-user-role="<?= htmlspecialchars($u['role'] ?? $u['user_type'] ?? '') ?>">
                                            <div class="position-relative">
                                                <img src="<?= htmlspecialchars($u['avatar_url'] ?: 'https://via.placeholder.com/45') ?>" 
                                                     class="user-avatar" 
                                                     alt="avatar"
                                                     onerror="this.src='https://via.placeholder.com/45'">
                                                <div class="user-status"></div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold"><?= htmlspecialchars($u['name']) ?></div>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($u['role'] ?? $u['user_type'] ?? 'User') ?>
                                                </small>
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
                </div>
            </div>

            <!-- Kolom kanan: chat -->
            <div class="col-md-8">
                <div class="card chat-card">
                    <div class="chat-header">
                        <strong id="chatWithLabel" class="fs-5">
                            <i class="fas fa-comments me-2"></i>Pilih user untuk mulai chat
                        </strong>
                    </div>
                    <div id="chatMessages" class="chat-messages">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comment-dots fa-3x mb-3"></i>
                            <p>Pilih user dari daftar untuk memulai percakapan</p>
                        </div>
                    </div>
                    <div id="chatStatus" class="alert alert-warning mx-3 mb-3" style="display:none"></div>
                    <div class="chat-input p-3">
    <div class="input-group">
        <textarea id="chatText" class="form-control" rows="3" placeholder="Tulis pesan Anda..." maxlength="5000" style="border-radius: 12px; resize: none;"></textarea>
    </div>
    <div id="filePreview" class="mt-2"></div>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="buttons-container">
            <button type="button" class="file-upload-btn">
                <i class="fas fa-paperclip me-2"></i>Lampiran
                <input type="file" id="fileInput" style="display: none;" multiple accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar">
            </button>
            <button id="chatSend" class="send-btn">
                <i class="fas fa-paper-plane me-2"></i>Kirim
            </button>
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
        
        const userName = this.querySelector('.fw-semibold').textContent.trim();
        document.getElementById('chatWithLabel').innerHTML = `<i class="fas fa-comments me-2"></i>Chat dengan ${userName}`;

        sessionStorage.setItem('receiver_id', userId);

        const saved = getSavedHistory(userId);
        if (saved.length > 0) {
          renderMessages(saved, true);
          updateLastMessageId(userId, saved);
        } else {
          clearChatUI();
          document.getElementById('chatMessages').innerHTML = `
            <div class="text-center text-muted py-5">
              <i class="fas fa-comment-dots fa-3x mb-3"></i>
              <p>Mulai percakapan dengan ${userName}</p>
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