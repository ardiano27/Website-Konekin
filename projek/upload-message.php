<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    // Pastikan path ini benar relatif terhadap file upload-message.php
    $uploadDir = __DIR__ . '/assets/uploads/messages';
    $baseUploadUrl = 'assets/uploads/messages'; // URL base untuk diakses di frontend
    
    // Create directory if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $file = $_FILES['file'];
    
    // Validate file type
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 
        'application/pdf', 'text/plain', 
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // DOC & DOCX
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // XLS & XLSX
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', // PPT & PPTX
        'application/zip', 'application/x-rar-compressed' // ZIP & RAR
    ];
    $maxSize = 10 * 1024 * 1024; // 10MB

    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Tipe file tidak diizinkan. Tipe yang diterima: ' . implode(', ', array_map(fn($t) => explode('/', $t)[1], $allowedTypes)));
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('Ukuran file terlalu besar. Maksimal 10MB.');
    }

    // Generate safe filename: UUID_OriginalFilename.ext
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $originalFilename = pathinfo($file['name'], PATHINFO_FILENAME);
    // Bersihkan nama file asli
    $safeOriginalFilename = preg_replace('/[^a-zA-Z0-9\\._-]/', '_', $originalFilename); 
    
    $safeFilename = uniqid() . '_' . $safeOriginalFilename . '.' . $fileExtension;
    $filePath = $uploadDir . $safeFilename;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $fileUrl = $baseUploadUrl . $safeFilename;
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'fileUrl' => $fileUrl,
            'fileName' => $file['name']
        ]);
    } else {
        throw new Exception('Gagal memindahkan file yang diunggah.');
    }

} catch (Exception $e) {
    ob_clean();
    error_log('upload-message.php error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}