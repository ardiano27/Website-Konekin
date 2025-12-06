<?php
    // Gunakan session_status() untuk cek apakah session sudah aktif
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
    
    include_once "config/Database.php";
    $database = new DatabaseConnection();
    $conn = $database->getConnection();

    $sql = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        session_destroy();
        header("Location: index.php");
        exit;
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Update session dengan data terbaru dari database
    $_SESSION['user_uuid'] = $user['uuid'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['is_verified'] = $user['is_verified'];
    
    // Tambahkan juga data yang mungkin diperlukan
    $_SESSION['avatar_url'] = $user['avatar_url'] ?? null;
    $_SESSION['phone'] = $user['phone'] ?? null;
?>