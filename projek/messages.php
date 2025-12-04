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

// Get base URL for file downloads
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

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
        /* Semua CSS tetap sama seperti sebelumnya */
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
            padding: 12px 16px;
            border-radius: 18px;
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
            max-width: 300px;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-icon {
            font-size: 1.5rem;
        }

        .file-details {
            flex: 1;
            min-width: 0;
        }

        .file-name {
            font-weight: 600;
            margin-bottom: 2px;
            word-break: break-all;
            font-size: 0.85rem;
        }

        .file-size {
            font-size: 0.7rem;
            color: #666;
        }

        .download-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .download-btn:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .download-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Image preview styles */
        .image-attachment-container {
            position: relative;
            display: inline-block;
            margin-bottom: 10px;
        }

        .image-message {
            max-width: 250px;
            max-height: 300px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            cursor: zoom-in;
            transition: all 0.3s ease;
            object-fit: contain;
            background-color: #f8f9fa;
        }

        .image-message:hover {
            transform: scale(1.03);
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: zoom-in;
        }

        .image-attachment-container:hover .image-overlay {
            opacity: 1;
        }

        .overlay-content {
            color: white;
            text-align: center;
            padding: 20px;
        }

        .overlay-content i {
            margin-bottom: 10px;
        }

        .image-controls {
            margin-top: 8px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .img-loading {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Modal enhancements */
        #imageModal .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            background: #1a1a1a;
        }

        #imageModal .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.8);
        }

        #imageModal .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.8);
        }

        #imageModal .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }

        #imageModal .btn-close:hover {
            opacity: 1;
        }

        .modal-image {
            transition: opacity 0.3s ease;
            max-height: 70vh;
            object-fit: contain;
        }

        .modal-image.img-loading {
            opacity: 0.7;
        }

        /* Image navigation */
        .image-navigation {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
            transform: translateY(-50%);
            z-index: 1000;
        }

        .nav-btn {
            background: rgba(0,0,0,0.7);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            background: rgba(0,0,0,0.9);
            transform: scale(1.1);
        }

        .nav-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        /* Image counter */
        .image-counter {
            position: absolute;
            top: 60px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            z-index: 1000;
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

        .file-input-hidden {
            display: none;
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

        /* File preview container */
        .file-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .file-preview-item {
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 300px;
        }

        .file-preview-info {
            flex: 1;
            min-width: 0;
        }

        .file-preview-name {
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-preview-size {
            font-size: 0.7rem;
            color: #6b7280;
        }

        .file-preview-remove {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            padding: 2px;
        }

        /* File icon colors */
        .fa-file-pdf { color: #e74c3c; }
        .fa-file-word { color: #2b579a; }
        .fa-file-excel { color: #217346; }
        .fa-file-powerpoint { color: #d24726; }
        .fa-file-image { color: #27ae60; }
        .fa-image { color: #27ae60; }
        .fa-file-archive { color: #f39c12; }
        .fa-file-alt { color: #7f8c8d; }
        .fa-file { color: #95a5a6; }

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
                max-height: 250px;
            }
            
            .nav-btn {
                width: 35px;
                height: 35px;
            }
        }
    </style>
</head>
<body>
  <!-- Navbar -->
  <?php include "dashboard-sidebar.php"; ?>
  
  <div id="connectionStatus" class="connection-status">
    <i class="fas fa-circle"></i> <span id="statusText">Connecting...</span>
  </div>

  <!-- Image Preview Modal -->
  <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
      <div class="modal-content">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title" id="imageModalLabel">Preview Gambar</h5>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-light" onclick="window.downloadFromModal()" title="Download gambar">
              <i class="fas fa-download"></i>
            </button>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
        </div>
        <div class="modal-body p-0 bg-dark">
          <div id="imageCounter" class="image-counter" style="display: none;"></div>
          <div class="d-flex justify-content-center align-items-center" style="min-height: 70vh;">
            <img id="modalImage" 
                 src="" 
                 class="modal-image img-fluid" 
                 alt="Preview Gambar"
                 style="max-height: 70vh; max-width: 100%; object-fit: contain;">
          </div>
        </div>
        <div class="modal-footer bg-dark">
          <div id="imageNavigation" class="image-navigation" style="display: none;">
            <button type="button" class="nav-btn prev-btn" onclick="window.prevImage()">
              <i class="fas fa-chevron-left"></i>
            </button>
            <button type="button" class="nav-btn next-btn" onclick="window.nextImage()">
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i> Tutup
          </button>
          <button type="button" class="btn btn-primary" onclick="window.downloadFromModal()">
            <i class="fas fa-download me-1"></i> Download
          </button>
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
                    <input type="file" id="fileInput" class="file-input-hidden" multiple accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar">
                    <div class="chat-input-area">
                        <textarea id="chatText" class="chat-textarea" rows="1" placeholder="Ketik pesan..." maxlength="5000"></textarea>
                        <div class="action-buttons">
                            <button type="button" class="file-upload-btn" id="fileUploadBtn">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <button id="chatSend" class="send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                    <div id="filePreviewContainer" class="file-preview-container"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="sidebar.js"></script>

<script>
// Global variables
let currentGallery = null;

document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM loaded, initializing chat with preview & download support...');
    
    const currentUserId = <?= (int)$current_user_id ?>;
    const baseUrl = '<?= $base_url ?>';
    let pollingInterval = null;
    let currentChatUser = null;
    let pollMultiplier = 1;
    let lastActivityTime = Date.now();
    let isOnline = navigator.onLine;
    
    const FETCH_URL = 'fetch_messages.php';
    const SEND_URL = 'send_message.php';
    const DOWNLOAD_URL = 'download_file.php';
    const BASE_POLL_INTERVAL = 5000;

    const lastMessageIds = {};
    const historyKeyPrefix = 'chat_history_' + currentUserId + '_';
    const renderedMessageIds = new Set();
    let selectedFiles = [];

    console.log('Current user ID:', currentUserId);
    console.log('Base URL:', baseUrl);

    // === UTILITY FUNCTIONS - DIPERBAIKI ===
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function getFileIcon(fileType) {
        if (!fileType) return 'fa-file';
        
        if (fileType.includes('pdf')) return 'fa-file-pdf';
        if (fileType.includes('word')) return 'fa-file-word';
        if (fileType.includes('excel') || fileType.includes('spreadsheet')) return 'fa-file-excel';
        if (fileType.includes('powerpoint') || fileType.includes('presentation')) return 'fa-file-powerpoint';
        if (fileType.includes('text')) return 'fa-file-alt';
        if (fileType.includes('zip') || fileType.includes('rar') || fileType.includes('archive')) return 'fa-file-archive';
        if (fileType.includes('audio')) return 'fa-file-audio';
        if (fileType.includes('video')) return 'fa-file-video';
        if (fileType.startsWith('image/')) return 'fa-file-image';
        
        return 'fa-file';
    }

    function generateMessageId(message) {
        return String(message.id || message.message_id || message.uuid || 'temp_' + Date.now() + '_' + Math.random());
    }

    // FUNGSI PENTING: DIPERBAIKI UNTUK URL GAMBAR
    function getFullFileUrl(relativeUrl) {
        if (!relativeUrl) return '';
        
        console.log('Getting full URL for:', relativeUrl);
        
        // Jika sudah full URL, kembalikan as is
        if (relativeUrl.startsWith('http://') || relativeUrl.startsWith('https://') || relativeUrl.startsWith('blob:')) {
            return relativeUrl;
        }
        
        // Jika relative path dimulai dengan /uploads/
        if (relativeUrl.startsWith('/uploads/')) {
            return baseUrl + relativeUrl;
        }
        
        // Jika hanya nama file, anggap ada di /uploads/
        if (relativeUrl && !relativeUrl.includes('/') && !relativeUrl.startsWith('.')) {
            return baseUrl + '/uploads/' + relativeUrl;
        }
        
        // Tambahkan base URL untuk path lainnya
        return baseUrl + (relativeUrl.startsWith('/') ? '' : '/') + relativeUrl;
    }

    function getDownloadUrl(messageId, filename, originalName) {
        if (!filename || !messageId) return '#';
        
        // Pastikan filename hanya nama file, bukan full path
        const cleanFilename = filename.split('/').pop();
        
        return DOWNLOAD_URL + '?file=' + encodeURIComponent(cleanFilename) + 
               '&msg_id=' + messageId + 
               '&original=' + encodeURIComponent(originalName || cleanFilename);
    }

    function extractFilenameFromUrl(url) {
        if (!url) return '';
        return url.split('/').pop().split('?')[0];
    }

    // === RENDER MESSAGE WITH PREVIEW & DOWNLOAD - DIPERBAIKI ===
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
        messageDiv.setAttribute('data-message-id', message.id || '');

        const bubbleClass = isMe ? 'message-bubble message-sent' : 'message-bubble message-received';
        
        let attachmentsHtml = '';
        if (message.attachment_urls && message.attachment_urls.length > 0) {
            attachmentsHtml += '<div class="mb-2">';
            
            // Filter gambar dan file lainnya
            const imageAttachments = [];
            const fileAttachments = [];
            
            message.attachment_urls.forEach((attachment, index) => {
                const url = attachment.url || attachment;
                const fileType = attachment.type || '';
                const fileName = attachment.original_name || (typeof attachment === 'string' ? extractFilenameFromUrl(url) : 'File');
                
                // Debug log
                console.log('Processing attachment:', { url, fileType, fileName });
                
                if (url.match(/\.(jpg|jpeg|png|gif|webp|bmp)$/i) || fileType.startsWith('image/')) {
                    imageAttachments.push({
                        ...attachment,
                        index: index,
                        fileName: fileName
                    });
                } else {
                    fileAttachments.push({
                        ...attachment,
                        index: index,
                        fileName: fileName
                    });
                }
            });
            
            // Tampilkan gambar
            if (imageAttachments.length > 0) {
                attachmentsHtml += '<div class="image-attachments mb-3">';
                imageAttachments.forEach((img, imgIndex) => {
                    const fullImageUrl = message.is_temp ? img.url : getFullFileUrl(img.url);
                    console.log('Image URL:', { original: img.url, full: fullImageUrl, is_temp: message.is_temp });
                    
                    // Encode untuk attribute HTML
                    const safeImageUrl = fullImageUrl.replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                    const safeFileName = escapeHtml(img.fileName);
                    const fileSize = img.size ? formatFileSize(img.size) : (img.formatted_size || '');
                    const filename = extractFilenameFromUrl(img.url);
                    
                    attachmentsHtml += `
                        <div class="mb-3">
                            <div class="image-attachment-container">
                                <img src="${fullImageUrl}" 
                                     class="image-message" 
                                     alt="${safeFileName}"
                                     data-image-index="${imgIndex}"
                                     data-message-id="${message.id || ''}"
                                     onclick="openImageModal('${safeImageUrl}', '${safeFileName}', ${message.id || 0}, ${imgIndex}, ${imageAttachments.length})"
                                     onerror="this.onerror=null; this.src='https://via.placeholder.com/250x300?text=Gagal+memuat+gambar'; this.style.cursor='default'; console.error('Failed to load image:', '${safeImageUrl}')">
                                
                                <div class="image-overlay" 
                                     onclick="openImageModal('${safeImageUrl}', '${safeFileName}', ${message.id || 0}, ${imgIndex}, ${imageAttachments.length})">
                                    <div class="overlay-content">
                                        <i class="fas fa-expand fa-2x"></i>
                                        <div class="mt-2">Klik untuk preview</div>
                                    </div>
                                </div>
                            </div>
                            <div class="image-controls">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="openImageModal('${safeImageUrl}', '${safeFileName}', ${message.id || 0}, ${imgIndex}, ${imageAttachments.length})">
                                    <i class="fas fa-expand me-1"></i> Preview
                                </button>
                                ${!message.is_temp ? `
                                <a href="${getDownloadUrl(message.id, filename, img.fileName)}" 
                                   class="btn btn-sm btn-outline-success"
                                   download="${safeFileName}"
                                   title="Download ${safeFileName}">
                                    <i class="fas fa-download me-1"></i> Download
                                </a>` : `
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                    <i class="fas fa-spinner fa-spin me-1"></i> Uploading...
                                </button>`}
                            </div>
                        </div>
                    `;
                });
                attachmentsHtml += '</div>';
            }
            
            // Tampilkan file non-gambar
            if (fileAttachments.length > 0) {
                attachmentsHtml += '<div class="file-attachments">';
                fileAttachments.forEach((file, fileIndex) => {
                    const fileName = file.fileName;
                    const safeFileName = escapeHtml(fileName);
                    const fileSize = file.size ? formatFileSize(file.size) : (file.formatted_size || '');
                    const fileType = file.type || '';
                    const filename = extractFilenameFromUrl(file.url);
                    
                    attachmentsHtml += `
                        <div class="file-message mb-2">
                            <div class="file-info">
                                <i class="fas ${getFileIcon(fileType)} file-icon"></i>
                                <div class="file-details">
                                    <div class="file-name">${safeFileName}</div>
                                    ${fileSize ? `<div class="file-size">${fileSize}</div>` : ''}
                                    <div class="file-type text-muted">${fileType || 'Unknown type'}</div>
                                </div>
                                ${!message.is_temp ? `
                                <a href="${getDownloadUrl(message.id, filename, fileName)}" 
                                   class="download-btn" 
                                   download="${safeFileName}"
                                   title="Download ${safeFileName}">
                                    <i class="fas fa-download"></i> Download
                                </a>` : `
                                <button type="button" class="download-btn" disabled style="opacity: 0.5;">
                                    <i class="fas fa-spinner fa-spin"></i> Uploading
                                </button>`}
                            </div>
                        </div>
                    `;
                });
                attachmentsHtml += '</div>';
            }
            
            attachmentsHtml += '</div>';
        }
        
        messageDiv.innerHTML = `
            <div class="${bubbleClass}">
                ${attachmentsHtml}
                ${message.message_text ? `<div style="white-space:pre-wrap" class="mb-2">${escapeHtml(message.message_text || '')}</div>` : ''}
                <div class="message-time">
                    ${escapeHtml(formatTime(message.created_at))}
                    ${message.is_temp ? '<span class="message-status"><i class="fas fa-clock"></i> Sending...</span>' : ''}
                </div>
            </div>
        `;

        if (append) {
            chatBox.appendChild(messageDiv);
            chatBox.scrollTop = chatBox.scrollHeight;
        } else {
            chatBox.insertBefore(messageDiv, chatBox.firstChild);
        }
    }

    // === IMAGE MODAL FUNCTIONS - DIPERBAIKI ===
    window.openImageModal = function(imageUrl, imageTitle, messageId, currentIndex, totalImages) {
        const modalImage = document.getElementById('modalImage');
        const modalTitle = document.getElementById('imageModalLabel');
        const imageCounter = document.getElementById('imageCounter');
        const imageNavigation = document.getElementById('imageNavigation');
        
        console.log('Opening modal with image:', imageUrl);
        
        // Set global gallery data
        currentGallery = {
            messageId: messageId,
            currentIndex: currentIndex || 0,
            totalImages: totalImages || 1,
            images: [] // Will be populated from message data
        };
        
        // Set image - gunakan URL langsung (tidak perlu encode)
        modalImage.src = imageUrl.replace(/&#39;/g, "'").replace(/&quot;/g, '"');
        modalImage.alt = imageTitle || 'Preview Gambar';
        
        // Set title
        if (modalTitle) {
            modalTitle.textContent = imageTitle || 'Preview Gambar';
        }
        
        // Show/hide counter and navigation
        if (totalImages > 1) {
            imageCounter.textContent = `Gambar ${currentIndex + 1} dari ${totalImages}`;
            imageCounter.style.display = 'block';
            imageNavigation.style.display = 'flex';
            
            // Update navigation buttons
            updateNavigationButtons();
        } else {
            imageCounter.style.display = 'none';
            imageNavigation.style.display = 'none';
        }
        
        // Add loading state
        modalImage.classList.add('img-loading');
        modalImage.onload = function() {
            modalImage.classList.remove('img-loading');
            console.log('Modal image loaded successfully');
        };
        
        modalImage.onerror = function() {
            console.error('Failed to load modal image:', imageUrl);
            modalImage.src = 'https://via.placeholder.com/800x600?text=Gagal+memuat+gambar';
            modalImage.alt = 'Gambar tidak dapat dimuat';
            modalImage.classList.remove('img-loading');
        };
        
        // Show modal
        const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        imageModal.show();
    };

    window.downloadFromModal = function() {
        const modalImage = document.getElementById('modalImage');
        const imageUrl = modalImage.src;
        const imageName = modalImage.alt || 'image.jpg';
        
        console.log('Downloading from modal:', imageUrl);
        
        if (imageUrl && !imageUrl.includes('via.placeholder.com')) {
            // Check if it's a blob URL or regular URL
            if (imageUrl.startsWith('blob:')) {
                // For blob URLs, create download link
                const link = document.createElement('a');
                link.href = imageUrl;
                link.download = imageName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                // For regular URLs, redirect to download handler
                const filename = imageUrl.split('/').pop();
                const messageId = currentGallery ? currentGallery.messageId : 0;
                
                if (messageId && filename) {
                    window.location.href = DOWNLOAD_URL + '?file=' + encodeURIComponent(filename) + 
                                          '&msg_id=' + messageId + 
                                          '&original=' + encodeURIComponent(imageName);
                } else {
                    // Direct download if we can't get message ID
                    const link = document.createElement('a');
                    link.href = imageUrl;
                    link.download = imageName;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            }
            
            console.log('Downloading image:', imageName);
        } else {
            alert('Tidak dapat mendownload gambar placeholder');
        }
    };

    window.prevImage = function() {
        if (!currentGallery || currentGallery.currentIndex <= 0) return;
        
        currentGallery.currentIndex--;
        updateImageCounter();
        updateNavigationButtons();
        
        console.log('Loading previous image:', currentGallery.currentIndex);
        // TODO: Load previous image from message data
    };

    window.nextImage = function() {
        if (!currentGallery || currentGallery.currentIndex >= currentGallery.totalImages - 1) return;
        
        currentGallery.currentIndex++;
        updateImageCounter();
        updateNavigationButtons();
        
        console.log('Loading next image:', currentGallery.currentIndex);
        // TODO: Load next image from message data
    };

    function updateImageCounter() {
        const imageCounter = document.getElementById('imageCounter');
        if (imageCounter && currentGallery) {
            imageCounter.textContent = `Gambar ${currentGallery.currentIndex + 1} dari ${currentGallery.totalImages}`;
        }
    }

    function updateNavigationButtons() {
        const prevBtn = document.querySelector('.prev-btn');
        const nextBtn = document.querySelector('.next-btn');
        
        if (prevBtn && currentGallery) {
            prevBtn.disabled = currentGallery.currentIndex === 0;
        }
        if (nextBtn && currentGallery) {
            nextBtn.disabled = currentGallery.currentIndex === currentGallery.totalImages - 1;
        }
    }

    // === FUNGSI LAINNYA (tetap sama) ===
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

    function formatTime(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 1) return 'Baru saja';
        if (diffMins < 60) return `${diffMins} menit lalu`;
        
        const options = { 
            hour: '2-digit', 
            minute: '2-digit',
            day: '2-digit',
            month: 'short'
        };
        
        if (date.getFullYear() === now.getFullYear()) {
            return date.toLocaleDateString('id-ID', options);
        } else {
            options.year = 'numeric';
            return date.toLocaleDateString('id-ID', options);
        }
    }

    // === SEARCH FUNCTIONALITY ===
    function initializeSearch() {
        const searchInput = document.getElementById('userSearch');
        const userItems = document.querySelectorAll('#userList .user-item');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            let visibleCount = 0;
            
            userItems.forEach(item => {
                const userName = item.dataset.userName.toLowerCase();
                const userRole = item.dataset.userRole.toLowerCase();
                
                if (userName.includes(searchTerm) || userRole.includes(searchTerm) || searchTerm === '') {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            const noResultsMsg = document.querySelector('.no-users-found');
            const noUsersElement = document.querySelector('.no-users');
            
            if (visibleCount === 0 && searchTerm !== '') {
                if (!noResultsMsg) {
                    const noResults = document.createElement('div');
                    noResults.className = 'no-users-found';
                    noResults.innerHTML = `
                        <div class="text-center py-4">
                            <i class="fas fa-search fa-2x mb-3 text-muted"></i>
                            <p class="text-muted mb-0">Tidak ada user yang cocok dengan "<strong>${searchTerm}</strong>"</p>
                        </div>
                    `;
                    document.getElementById('userList').appendChild(noResults);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
            
            if (noUsersElement) {
                if (visibleCount === 0 && searchTerm === '') {
                    noUsersElement.style.display = 'block';
                } else {
                    noUsersElement.style.display = 'none';
                }
            }
        });
        
        searchInput.value = '';
    }

    // === FILE UPLOAD FUNCTIONALITY ===
    function initializeFileUpload() {
        const fileInput = document.getElementById('fileInput');
        const fileUploadBtn = document.getElementById('fileUploadBtn');
        const filePreviewContainer = document.getElementById('filePreviewContainer');
        const chatText = document.getElementById('chatText');

        fileUploadBtn.addEventListener('click', function() {
            fileInput.click();
        });

        fileInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            addFilesToSelection(files);
        });

        function addFilesToSelection(files) {
            files.forEach(file => {
                // Check file size (max 10MB)
                if (file.size > 10 * 1024 * 1024) {
                    alert(`File ${escapeHtml(file.name)} terlalu besar (maksimum 10MB)`);
                    return;
                }

                // Check file type
                const allowedTypes = [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
                    'application/pdf', 'text/plain',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip', 'application/x-rar-compressed'
                ];
                
                // Allow empty type (some browsers don't set it)
                if (file.type && !allowedTypes.includes(file.type)) {
                    alert(`Tipe file ${escapeHtml(file.name)} tidak diizinkan`);
                    return;
                }

                // Add to selected files
                selectedFiles.push(file);
                displayFilePreview();
            });
            
            // Reset file input untuk memungkinkan upload file yang sama lagi
            fileInput.value = '';
        }

        function displayFilePreview() {
            filePreviewContainer.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const fileElement = document.createElement('div');
                fileElement.className = 'file-preview-item';
                fileElement.setAttribute('data-file-index', index);
                
                // Get file icon based on type
                let fileIcon = getFileIcon(file.type);
                let iconColor = '';
                
                if (file.type.startsWith('image/')) {
                    fileIcon = 'fa-image';
                    iconColor = 'text-success';
                } else if (file.type.includes('pdf')) {
                    fileIcon = 'fa-file-pdf';
                    iconColor = 'text-danger';
                } else if (file.type.includes('word')) {
                    fileIcon = 'fa-file-word';
                    iconColor = 'text-primary';
                } else if (file.type.includes('text')) {
                    fileIcon = 'fa-file-alt';
                    iconColor = 'text-info';
                } else if (file.type.includes('zip') || file.type.includes('rar')) {
                    fileIcon = 'fa-file-archive';
                    iconColor = 'text-warning';
                }
                
                // Buat preview thumbnail untuk gambar
                let thumbnailHtml = '';
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = fileElement.querySelector('.file-thumbnail');
                        if (img) {
                            img.src = e.target.result;
                        }
                    };
                    reader.readAsDataURL(file);
                    thumbnailHtml = `<img src="" class="file-thumbnail" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 8px;" alt="Preview">`;
                }
                
                fileElement.innerHTML = `
                    ${thumbnailHtml}
                    <i class="fas ${fileIcon} ${iconColor} ${thumbnailHtml ? '' : 'me-2'}"></i>
                    <div class="file-preview-info">
                        <div class="file-preview-name">${escapeHtml(file.name)}</div>
                        <div class="file-preview-size">${formatFileSize(file.size)}</div>
                    </div>
                    <button type="button" class="file-preview-remove" data-index="${index}" title="Hapus file">
                        <i class="fas fa-times text-danger"></i>
                    </button>
                `;
                
                filePreviewContainer.appendChild(fileElement);
            });

            // Add remove file functionality
            document.querySelectorAll('.file-preview-remove').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    selectedFiles.splice(index, 1);
                    displayFilePreview();
                });
            });
            
            // Scroll ke preview file
            if (filePreviewContainer.children.length > 0) {
                filePreviewContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Drag and drop functionality
        const chatInputArea = document.querySelector('.chat-input-area');
        const chatContainer = document.querySelector('.chat-area');
        
        chatContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.border = '2px dashed #667eea';
            this.style.background = 'rgba(102, 126, 234, 0.05)';
        });
        
        chatContainer.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.border = 'none';
            this.style.background = '';
        });
        
        chatContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.border = 'none';
            this.style.background = '';
            
            const files = Array.from(e.dataTransfer.files);
            if (files.length > 0) {
                addFilesToSelection(files);
                // Beri feedback visual
                const originalColor = chatText.style.color;
                chatText.style.color = '#667eea';
                setTimeout(() => {
                    chatText.style.color = originalColor;
                }, 500);
            }
        });
    }

    // === AUTO-RESIZE TEXTAREA ===
    function initializeAutoResize() {
        const textarea = document.getElementById('chatText');
        
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        // Enter to send, Shift+Enter for new line
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
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
        
        // Validasi: boleh hanya file atau hanya teks atau keduanya
        if (!msg && selectedFiles.length === 0) {
            alert('Silakan ketik pesan atau lampirkan file');
            return;
        }

        if (!isOnline) {
            alert('Anda sedang offline. Periksa koneksi internet Anda.');
            return;
        }

        try {
            // Create FormData for sending
            const formData = new FormData();
            formData.append('receiver_id', receiver);
            formData.append('message_text', msg);
            
            // Debug info
            console.log('📤 Sending message:', {
                text: msg,
                filesCount: selectedFiles.length,
                files: selectedFiles.map(f => ({ name: f.name, size: f.size, type: f.type }))
            });
            
            // Append files dengan cara yang benar untuk PHP
            selectedFiles.forEach((file, index) => {
                console.log(`Appending file ${index + 1}:`, file.name);
                // Gunakan bracket notation agar PHP menerima sebagai array
                formData.append('attachment[]', file);
            });

            // Create temporary message untuk preview langsung
            const tempId = 'temp_' + Date.now();
            const tempBlobUrls = [];
            
            const tempMessage = {
                id: tempId,
                sender_id: currentUserId,
                receiver_id: receiver,
                message_text: msg,
                attachment_urls: selectedFiles.map(file => {
                    const blobUrl = URL.createObjectURL(file);
                    tempBlobUrls.push(blobUrl);
                    return {
                        url: blobUrl,
                        original_name: file.name,
                        size: file.size,
                        formatted_size: formatFileSize(file.size),
                        type: file.type
                    };
                }),
                created_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
                is_temp: true
            };
            
            // Render temporary message untuk feedback instan
            renderMessage(tempMessage, true);
            
            // Clear inputs
            textEl.value = '';
            textEl.style.height = 'auto';
            selectedFiles = [];
            document.getElementById('filePreviewContainer').innerHTML = '';
            document.getElementById('fileInput').value = '';

            // Send to server
            console.log('Sending FormData to server...');
            
            const response = await fetch(SEND_URL, { 
                method: 'POST', 
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            console.log('Server response:', data);
            
            if (!data.success) {
                alert(data.error || 'Gagal mengirim pesan');
                // Remove temporary message jika gagal
                renderedMessageIds.delete(tempId);
                const tempEl = document.querySelector(`[data-mid="${tempId}"]`);
                if (tempEl) tempEl.remove();
                
                // Cleanup blob URLs
                tempBlobUrls.forEach(url => URL.revokeObjectURL(url));
                return;
            }

            // Success - remove temporary message
            renderedMessageIds.delete(tempId);
            const tempEl = document.querySelector(`[data-mid="${tempId}"]`);
            if (tempEl) tempEl.remove();
            
            // Cleanup blob URLs
            tempBlobUrls.forEach(url => URL.revokeObjectURL(url));
            
            // Render the real message from server
            if (data.message) {
                renderMessage(data.message, true);
                
                // Save to local storage
                const saved = getSavedHistory(receiver);
                saved.push(data.message);
                saveHistory(receiver, saved);
                updateLastMessageId(receiver, [data.message]);
            }
            
            console.log('✅ Message sent successfully');
            
        } catch (err) {
            console.error('❌ Send error:', err);
            alert('Gagal mengirim pesan. Coba lagi.');
        }
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
        
        // Initialize connection status
        updateConnectionStatus(navigator.onLine);

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

        console.log('Chat initialization complete with preview & download support');
    }

    // Start the chat application
    initializeChat();
});
</script>
</body>
</html>
