<?php
include "check_login.php";
if ($_SESSION['user_type'] !== 'creative') {
    header("Location: dashboard.php");
    exit;
}

include "config/Database.php";
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Get creative profile ID
$profile_sql = "SELECT id FROM creative_profiles WHERE user_id = :user_id";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bindParam(':user_id', $_SESSION['user_id']);
$profile_stmt->execute();
$creative_profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);

if (!$creative_profile) {
    $_SESSION['error'] = "Profile creative tidak ditemukan";
    header("Location: portfolio.php");
    exit;
}

$creative_profile_id = $creative_profile['id'];

if ($_POST['action'] === 'add') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $project_url = $_POST['project_url'] ?? null;
    $project_date = $_POST['project_date'] ?? null;
    $video_url = $_POST['video_url'] ?? null;
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    $skills_used = [];
    if (!empty($_POST['skills_used'])) {
        $skills_used = array_map('trim', explode(',', $_POST['skills_used']));
        $skills_used = array_filter($skills_used);
    }

    $image_urls = [];
    if (!empty($_FILES['images']['name'][0])) {
        $upload_dir = "uploads/portfolios/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = time() . '_' . uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $image_urls[] = $file_path;
                }
            }
        }
    }
    
    try {
        $sql = "INSERT INTO portfolios (creative_profile_id, title, description, project_url, image_urls, video_url, skills_used, project_date, is_public) 
                VALUES (:creative_profile_id, :title, :description, :project_url, :image_urls, :video_url, :skills_used, :project_date, :is_public)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':creative_profile_id', $creative_profile_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':project_url', $project_url);
        $stmt->bindParam(':image_urls', json_encode($image_urls));
        $stmt->bindParam(':video_url', $video_url);
        $stmt->bindParam(':skills_used', json_encode($skills_used));
        $stmt->bindParam(':project_date', $project_date);
        $stmt->bindParam(':is_public', $is_public);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Portfolio berhasil ditambahkan";
        } else {
            $_SESSION['error'] = "Gagal menambahkan portfolio";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
} elseif ($_POST['action'] === 'edit') {
    $portfolio_id = $_POST['portfolio_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $project_url = $_POST['project_url'] ?? null;
    $project_date = $_POST['project_date'] ?? null;
    $video_url = $_POST['video_url'] ?? null;
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    $skills_used = [];
    if (!empty($_POST['skills_used'])) {
        $skills_used = array_map('trim', explode(',', $_POST['skills_used']));
        $skills_used = array_filter($skills_used);
    }
    
    $current_sql = "SELECT image_urls FROM portfolios WHERE id = :id AND creative_profile_id = :creative_profile_id";
    $current_stmt = $conn->prepare($current_sql);
    $current_stmt->bindParam(':id', $portfolio_id);
    $current_stmt->bindParam(':creative_profile_id', $creative_profile_id);
    $current_stmt->execute();
    $current_portfolio = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    $current_images = json_decode($current_portfolio['image_urls'] ?? '[]', true);
    $new_images = $current_images;

    if (!empty($_FILES['images']['name'][0])) {
        $upload_dir = "uploads/portfolios/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = time() . '_' . uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $new_images[] = $file_path;
                }
            }
        }
    }
    
    try {
        $sql = "UPDATE portfolios SET 
                title = :title, 
                description = :description, 
                project_url = :project_url, 
                image_urls = :image_urls, 
                video_url = :video_url, 
                skills_used = :skills_used, 
                project_date = :project_date, 
                is_public = :is_public 
                WHERE id = :id AND creative_profile_id = :creative_profile_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':project_url', $project_url);
        $stmt->bindParam(':image_urls', json_encode($new_images));
        $stmt->bindParam(':video_url', $video_url);
        $stmt->bindParam(':skills_used', json_encode($skills_used));
        $stmt->bindParam(':project_date', $project_date);
        $stmt->bindParam(':is_public', $is_public);
        $stmt->bindParam(':id', $portfolio_id);
        $stmt->bindParam(':creative_profile_id', $creative_profile_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Portfolio berhasil diupdate";
        } else {
            $_SESSION['error'] = "Gagal mengupdate portfolio";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
} elseif ($_POST['action'] === 'delete') {
    $portfolio_id = $_POST['portfolio_id'];
    
    try {
        $sql = "DELETE FROM portfolios WHERE id = :id AND creative_profile_id = :creative_profile_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $portfolio_id);
        $stmt->bindParam(':creative_profile_id', $creative_profile_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Portfolio berhasil dihapus";
        } else {
            $_SESSION['error'] = "Gagal menghapus portfolio";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

header("Location: portfolio.php");
exit;
?>