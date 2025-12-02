<?php
include "check_login.php";

// Hanya UMKM yang bisa akses halaman ini
if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

$project_id = $_GET['id'] ?? 0;

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

// Ambil detail proyek dengan lebih banyak data
$project_sql = "
    SELECT 
        p.*,
        u.full_name as umkm_name,
        u.avatar_url as umkm_avatar,
        up.business_name,
        up.business_logo_url,
        COUNT(DISTINCT pr.id) as proposal_count,
        COUNT(DISTINCT CASE WHEN pr.status = 'submitted' THEN pr.id END) as new_proposal_count,
        COUNT(DISTINCT c.id) as contract_count,
        COUNT(DISTINCT m.id) as milestone_count,
        COUNT(DISTINCT CASE WHEN m.status = 'completed' THEN m.id END) as completed_milestones,
        SUM(CASE WHEN m.status = 'completed' THEN m.amount ELSE 0 END) as paid_amount
    FROM projects p
    JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    LEFT JOIN proposals pr ON p.id = pr.project_id
    LEFT JOIN contracts c ON p.id = c.project_id AND c.status = 'active'
    LEFT JOIN milestones m ON c.id = m.contract_id
    WHERE p.id = :id AND p.umkm_user_id = :user_id
    GROUP BY p.id
";

$project_stmt = $conn->prepare($project_sql);
$project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
$project = $project_stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: projects.php");
    exit;
}

// Handle form edit
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_project'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $budget_type = $_POST['budget_type'] ?? '';
    $budget_min = floatval($_POST['budget_min'] ?? 0);
    $budget_max = floatval($_POST['budget_max'] ?? 0);
    $deadline = $_POST['deadline'] ?? '';
    $required_skills = $_POST['required_skills'] ?? [];
    
    // Validasi
    if (empty($title)) {
        $errors['title'] = 'Judul proyek harus diisi';
    }
    
    if (empty($description)) {
        $errors['description'] = 'Deskripsi proyek harus diisi';
    }
    
    if (empty($category)) {
        $errors['category'] = 'Kategori harus dipilih';
    }
    
    // Update project
    if (empty($errors)) {
        try {
            $required_skills_json = json_encode($required_skills);
            
            $update_sql = "
                UPDATE projects 
                SET title = :title, 
                    description = :description, 
                    category = :category, 
                    budget_range_min = :budget_min, 
                    budget_range_max = :budget_max, 
                    budget_type = :budget_type, 
                    deadline = :deadline, 
                    required_skills = :required_skills,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND umkm_user_id = :user_id
            ";
            
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':category' => $category,
                ':budget_min' => $budget_min,
                ':budget_max' => $budget_max,
                ':budget_type' => $budget_type,
                ':deadline' => $deadline,
                ':required_skills' => $required_skills_json,
                ':id' => $project_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $success = true;
            
            // Refresh project data
            $project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
            $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $errors['database'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_files'])) {
    $attachment_urls = json_decode($project['attachment_urls'] ?? '[]', true) ?: [];
    
    if (isset($_FILES['new_attachments']) && !empty($_FILES['new_attachments']['name'][0])) {
        $upload_dir = 'assets/uploads/projects/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['new_attachments']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['new_attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = time() . '_' . basename($_FILES['new_attachments']['name'][$key]);
                $file_path = $upload_dir . $file_name;
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'video/mp4', 'video/avi', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $file_type = mime_content_type($tmp_name);
                
                if (in_array($file_type, $allowed_types)) {
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $attachment_urls[] = $file_path;
                    } else {
                        $errors['attachments'] = 'Gagal mengupload beberapa file';
                    }
                } else {
                    $errors['attachments'] = 'Tipe file tidak diizinkan';
                }
            }
        }
        
        // Update attachment URLs in database
        if (empty($errors['attachments'])) {
            $attachment_urls_json = json_encode($attachment_urls);
            $update_attachments_sql = "UPDATE projects SET attachment_urls = :attachments, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $update_attachments_stmt = $conn->prepare($update_attachments_sql);
            $update_attachments_stmt->execute([':attachments' => $attachment_urls_json, ':id' => $project_id]);
            
            // Refresh project data
            $project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
            $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $file_index = $_POST['file_index'] ?? null;
    if ($file_index !== null) {
        $attachment_urls = json_decode($project['attachment_urls'] ?? '[]', true) ?: [];
        
        if (isset($attachment_urls[$file_index])) {
            // Delete physical file
            $file_path = $attachment_urls[$file_index];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Remove from array
            unset($attachment_urls[$file_index]);
            $attachment_urls = array_values($attachment_urls); // Reindex array
            
            // Update database
            $attachment_urls_json = json_encode($attachment_urls);
            $update_attachments_sql = "UPDATE projects SET attachment_urls = :attachments, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $update_attachments_stmt = $conn->prepare($update_attachments_sql);
            $update_attachments_stmt->execute([':attachments' => $attachment_urls_json, ':id' => $project_id]);
            
            // Refresh project data
            $project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
            $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Parse project data
$required_skills = json_decode($project['required_skills'] ?? '[]', true) ?: [];
$attachment_urls = json_decode($project['attachment_urls'] ?? '[]', true) ?: [];

// Calculate time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'baru saja';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "{$minutes} menit yang lalu";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "{$hours} jam yang lalu";
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return "{$days} hari yang lalu";
    } else {
        return date('d M Y', $time);
    }
}

// Calculate project progress
$progress = 0;
if ($project['status'] === 'in_progress') {
    if ($project['milestone_count'] > 0) {
        $progress = ($project['completed_milestones'] / $project['milestone_count']) * 100;
    } else {
        $progress = 50; // Default progress for active projects without milestones
    }
} elseif ($project['status'] === 'completed') {
    $progress = 100;
}

// Category icons
$category_icons = [
    'website' => ['icon' => 'fas fa-globe', 'color' => '#3E7FD5', 'bg' => 'rgba(62, 127, 213, 0.1)'],
    'logo' => ['icon' => 'fas fa-palette', 'color' => '#FF6B6B', 'bg' => 'rgba(255, 107, 107, 0.1)'],
    'social_media' => ['icon' => 'fas fa-hashtag', 'color' => '#6BC5D2', 'bg' => 'rgba(107, 197, 210, 0.1)'],
    'video' => ['icon' => 'fas fa-video', 'color' => '#FFC300', 'bg' => 'rgba(255, 195, 0, 0.1)'],
    'content' => ['icon' => 'fas fa-file-alt', 'color' => '#28a745', 'bg' => 'rgba(40, 167, 69, 0.1)'],
    'marketing' => ['icon' => 'fas fa-bullhorn', 'color' => '#9C27B0', 'bg' => 'rgba(156, 39, 176, 0.1)'],
    'other' => ['icon' => 'fas fa-ellipsis-h', 'color' => '#6c757d', 'bg' => 'rgba(108, 117, 125, 0.1)']
];

$category = $project['category'] ?? 'other';
$category_icon = $category_icons[$category] ?? $category_icons['other'];

// Status labels and colors
$status_config = [
    'draft' => ['label' => 'Draft', 'color' => '#6c757d', 'bg' => 'rgba(108, 117, 125, 0.1)', 'icon' => 'fas fa-edit'],
    'open' => ['label' => 'Open', 'color' => '#28a745', 'bg' => 'rgba(40, 167, 69, 0.1)', 'icon' => 'fas fa-door-open'],
    'in_progress' => ['label' => 'In Progress', 'color' => '#FFC300', 'bg' => 'rgba(255, 193, 7, 0.1)', 'icon' => 'fas fa-spinner'],
    'completed' => ['label' => 'Completed', 'color' => '#17a2b8', 'bg' => 'rgba(23, 162, 184, 0.1)', 'icon' => 'fas fa-check-circle'],
    'cancelled' => ['label' => 'Cancelled', 'color' => '#dc3545', 'bg' => 'rgba(220, 53, 69, 0.1)', 'icon' => 'fas fa-ban']
];

$status = $project['status'];
$status_info = $status_config[$status] ?? $status_config['draft'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['title']); ?> - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2c5ea3;
            --primary-light: rgba(62, 127, 213, 0.1);
            --success-color: #28a745;
            --success-light: rgba(40, 167, 69, 0.1);
            --warning-color: #ffc107;
            --warning-light: rgba(255, 193, 7, 0.1);
            --danger-color: #dc3545;
            --danger-light: rgba(220, 53, 69, 0.1);
            --info-color: #17a2b8;
            --info-light: rgba(23, 162, 184, 0.1);
            --purple-color: #6f42c1;
            --purple-light: rgba(111, 66, 193, 0.1);
            --bg-light: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.12);
            --shadow-xl: 0 12px 40px rgba(0, 0, 0, 0.15);
            --border-radius: 16px;
            --border-radius-sm: 12px;
            --border-radius-lg: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        .main-content {
            padding: 20px 0;
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Hero Header */
        .project-hero {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius-lg);
            padding: 2.5rem 2rem;
            margin-bottom: 2.5rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            animation: slideInDown 0.6s ease;
        }

        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .project-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .project-hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .hero-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
            max-width: 600px;
        }

        /* Project Header */
        .project-header-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 2px solid transparent;
            transition: var(--transition);
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .project-header-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-lg);
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .status-badge {
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        .project-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--bg-light);
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
        }

        .meta-item:hover {
            background: var(--primary-light);
            transform: translateX(5px);
        }

        .meta-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .meta-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .meta-value {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1rem;
        }

        .meta-value.budget {
            color: var(--primary-color);
        }

        /* Content Cards */
        .content-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 2px solid transparent;
            transition: var(--transition);
            animation: fadeInUp 0.6s ease backwards;
        }

        .content-card:nth-child(1) { animation-delay: 0.1s; }
        .content-card:nth-child(2) { animation-delay: 0.2s; }
        .content-card:nth-child(3) { animation-delay: 0.3s; }

        .content-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--bg-light);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        /* Skills Section */
        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .skill-tag {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .skill-tag:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .skill-tag i {
            font-size: 0.75rem;
        }

        /* File Attachments */
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }

        .file-card {
            background: var(--bg-light);
            border-radius: var(--border-radius-sm);
            padding: 1rem;
            border: 2px solid transparent;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .file-card:hover {
            border-color: var(--primary-light);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .file-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .file-info {
            flex: 1;
            min-width: 0;
        }

        .file-name {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-size {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .file-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Progress Bar */
        .progress-container {
            margin: 1.5rem 0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .progress-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .progress-percentage {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .progress-bar {
            height: 10px;
            background: var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
            border-radius: 10px;
            transition: width 1s ease-in-out;
            position: relative;
            overflow: hidden;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* Stats Cards */
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            animation: fadeInUp 0.6s ease backwards;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Quick Actions */
        .quick-actions-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.75rem;
            box-shadow: var(--shadow-md);
            border: 2px solid transparent;
            transition: var(--transition);
            animation: fadeInUp 0.6s ease backwards;
            animation-delay: 0.4s;
        }

        .quick-actions-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-lg);
        }

        .action-btn {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background: transparent;
            color: var(--text-dark);
            font-weight: 600;
            text-align: left;
            transition: var(--transition);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            text-decoration: none;
        }

        .action-btn:hover {
            border-color: var(--primary-color);
            background: var(--primary-light);
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .action-btn.danger:hover {
            border-color: var(--danger-color);
            background: var(--danger-light);
            color: var(--danger-color);
        }

        .action-btn.warning:hover {
            border-color: var(--warning-color);
            background: var(--warning-light);
            color: var(--warning-color);
        }

        .action-btn.success:hover {
            border-color: var(--success-color);
            background: var(--success-light);
            color: var(--success-color);
        }

        /* Edit Form */
        .edit-form-container {
            background: var(--bg-light);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-top: 1.5rem;
            border: 2px dashed var(--border-color);
            animation: fadeIn 0.6s ease;
        }

        .form-section {
            background: white;
            border-radius: var(--border-radius-sm);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .form-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-title::before {
            content: '';
            width: 3px;
            height: 16px;
            background: var(--primary-color);
            border-radius: 1.5px;
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-lg);
            border-left: 4px solid var(--primary-color);
            transform: translateX(400px);
            transition: transform 0.3s ease;
            z-index: 9999;
            max-width: 400px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            border-left-color: var(--success-color);
        }

        .notification.error {
            border-left-color: var(--danger-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .project-hero {
                padding: 2rem 1.5rem;
                text-align: center;
            }
            
            .hero-title {
                font-size: 1.75rem;
            }
            
            .project-meta-grid {
                grid-template-columns: 1fr;
            }
            
            .file-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-card {
                padding: 1.25rem;
            }
            
            .stats-number {
                font-size: 2rem;
            }
        }

        /* Loading Animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Tooltip */
        .tooltip-custom {
            position: relative;
            display: inline-block;
        }

        .tooltip-custom .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: var(--text-dark);
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 0.5rem;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.85rem;
            font-weight: 400;
        }

        .tooltip-custom:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        .tooltip-custom .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: var(--text-dark) transparent transparent transparent;
        }
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Success Notification -->
            <?php if ($success): ?>
                <div class="notification success show" id="successNotification">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-3" style="color: var(--success-color); font-size: 1.25rem;"></i>
                        <div>
                            <strong class="d-block">Berhasil!</strong>
                            <small>Proyek berhasil diperbarui.</small>
                        </div>
                        <button type="button" class="btn-close ms-auto" onclick="hideNotification('successNotification')"></button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Hero Section -->
            <div class="project-hero">
                <div class="project-hero-content">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <div class="d-flex align-items-center mb-3">
                                <div class="category-badge" style="background: <?php echo $category_icon['bg']; ?>; color: <?php echo $category_icon['color']; ?>;">
                                    <i class="<?php echo $category_icon['icon']; ?>"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $category)); ?>
                                </div>
                                <span class="status-badge ms-3" style="background: <?php echo $status_info['bg']; ?>; color: <?php echo $status_info['color']; ?>;">
                                    <i class="<?php echo $status_info['icon']; ?>"></i>
                                    <?php echo $status_info['label']; ?>
                                </span>
                            </div>
                            <h1 class="hero-title"><?php echo htmlspecialchars($project['title']); ?></h1>
                            <p class="hero-subtitle">
                                <i class="far fa-calendar me-2"></i>
                                Dibuat <?php echo date('d M Y', strtotime($project['created_at'])); ?>
                                <?php if ($project['deadline']): ?>
                                    • Deadline: <?php echo date('d M Y', strtotime($project['deadline'])); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-lg-4 text-lg-end">
                            <div class="d-flex gap-2 justify-content-lg-end">
                                <a href="projects.php" class="btn btn-light px-4 rounded-pill">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali
                                </a>
                                <button class="btn btn-primary px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#actionModal">
                                    <i class="fas fa-ellipsis-h me-2"></i>Aksi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Left Column - Main Content -->
                <div class="col-lg-8">
                    <!-- Project Header Card -->
                    <div class="project-header-card">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h3 class="card-title mb-2">Deskripsi Proyek</h3>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-clock me-1"></i>
                                    Terakhir diupdate <?php echo timeAgo($project['updated_at']); ?>
                                </p>
                            </div>
                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3" type="button" data-bs-toggle="collapse" data-bs-target="#editForm">
                                <i class="fas fa-edit me-1"></i>Edit
                            </button>
                        </div>
                        
                        <div class="mb-4">
                            <p class="mb-0" style="line-height: 1.8;"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                        </div>

                        <!-- Project Meta Grid -->
                        <div class="project-meta-grid">
                            <div class="meta-item">
                                <div class="meta-icon">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div>
                                    <div class="meta-label">Budget</div>
                                    <div class="meta-value budget">
                                        <?php if ($project['budget_range_min']): ?>
                                            Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?>
                                            <?php if ($project['budget_range_max'] && $project['budget_range_max'] > $project['budget_range_min']): ?>
                                                - <?php echo number_format($project['budget_range_max'], 0, ',', '.'); ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Negotiable
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="meta-item">
                                <div class="meta-icon" style="background: rgba(62, 127, 213, 0.1); color: #3E7FD5;">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <div class="meta-label">Proposal</div>
                                    <div class="meta-value">
                                        <?php echo $project['proposal_count']; ?>
                                        <?php if ($project['new_proposal_count'] > 0): ?>
                                            <span class="badge bg-danger ms-2"><?php echo $project['new_proposal_count']; ?> baru</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="meta-item">
                                <div class="meta-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                    <i class="fas fa-handshake"></i>
                                </div>
                                <div>
                                    <div class="meta-label">Kontrak</div>
                                    <div class="meta-value"><?php echo $project['contract_count']; ?> aktif</div>
                                </div>
                            </div>
                            
                            <?php if ($project['deadline']): ?>
                                <div class="meta-item">
                                    <div class="meta-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <div>
                                        <div class="meta-label">Deadline</div>
                                        <div class="meta-value"><?php echo date('d M Y', strtotime($project['deadline'])); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Edit Form (Collapsible) -->
                    <div class="collapse" id="editForm">
                        <div class="edit-form-container">
                            <h4 class="mb-4"><i class="fas fa-edit me-2"></i>Edit Proyek</h4>
                            <form method="POST" id="editProjectForm" onsubmit="return false;">
                                <input type="hidden" name="edit_project" value="1">
                                
                                <div class="form-section">
                                    <h5 class="form-title">Informasi Dasar</h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Judul Proyek *</label>
                                            <input type="text" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                                   name="title" value="<?php echo htmlspecialchars($project['title']); ?>" required>
                                            <?php if (isset($errors['title'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['title']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Kategori *</label>
                                            <select class="form-select <?php echo isset($errors['category']) ? 'is-invalid' : ''; ?>" name="category" required>
                                                <option value="">Pilih Kategori</option>
                                                <option value="website" <?php echo $project['category'] === 'website' ? 'selected' : ''; ?>>Website</option>
                                                <option value="logo" <?php echo $project['category'] === 'logo' ? 'selected' : ''; ?>>Logo & Branding</option>
                                                <option value="social_media" <?php echo $project['category'] === 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                                                <option value="video" <?php echo $project['category'] === 'video' ? 'selected' : ''; ?>>Video</option>
                                                <option value="content" <?php echo $project['category'] === 'content' ? 'selected' : ''; ?>>Konten</option>
                                                <option value="marketing" <?php echo $project['category'] === 'marketing' ? 'selected' : ''; ?>>Marketing</option>
                                                <option value="other" <?php echo $project['category'] === 'other' ? 'selected' : ''; ?>>Lainnya</option>
                                            </select>
                                            <?php if (isset($errors['category'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['category']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Deskripsi *</label>
                                        <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                                  name="description" rows="4" required><?php echo htmlspecialchars($project['description']); ?></textarea>
                                        <?php if (isset($errors['description'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['description']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5 class="form-title">Budget & Timeline</h5>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Tipe Budget</label>
                                            <select class="form-select" name="budget_type" required>
                                                <option value="fixed" <?php echo $project['budget_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                                                <option value="hourly" <?php echo $project['budget_type'] === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                                <option value="negotiable" <?php echo $project['budget_type'] === 'negotiable' ? 'selected' : ''; ?>>Negotiable</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Budget Minimum (Rp)</label>
                                            <input type="number" class="form-control" name="budget_min" 
                                                   value="<?php echo $project['budget_range_min']; ?>" min="0">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Budget Maximum (Rp)</label>
                                            <input type="number" class="form-control" name="budget_max" 
                                                   value="<?php echo $project['budget_range_max']; ?>" min="0">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Deadline</label>
                                        <input type="date" class="form-control" name="deadline" 
                                               value="<?php echo $project['deadline']; ?>">
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5 class="form-title">Keahlian yang Dibutuhkan</h5>
                                    <div class="mb-3">
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php
                                            $all_skills = ['Graphic Design', 'Web Development', 'Social Media Marketing', 'Content Writing', 'Video Editing', 'UI/UX Design', 'Digital Marketing', 'Photography', 'Logo Design', 'WordPress', 'Adobe Illustrator', 'Adobe Photoshop', 'Figma', 'PHP', 'JavaScript', 'Laravel', 'Vue.js', 'React', 'MySQL', 'SEO', 'Google Ads', 'Copywriting', 'Adobe Premiere', 'After Effects'];
                                            
                                            foreach ($all_skills as $skill) {
                                                $checked = in_array($skill, $required_skills) ? 'checked' : '';
                                                echo '
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" name="required_skills[]" value="' . htmlspecialchars($skill) . '" id="skill_' . md5($skill) . '" ' . $checked . '>
                                                    <label class="form-check-label" for="skill_' . md5($skill) . '">' . $skill . '</label>
                                                </div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editForm">
                                        Batal
                                    </button>
                                    <button type="submit" class="btn btn-primary px-4" onclick="showLoading()">
                                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Required Skills Card -->
                    <?php if (!empty($required_skills)): ?>
                        <div class="content-card">
                            <div class="card-header">
                                <h3 class="card-title">Keahlian yang Dibutuhkan</h3>
                            </div>
                            <div class="skills-container">
                                <?php foreach ($required_skills as $skill): ?>
                                    <span class="skill-tag">
                                        <i class="fas fa-check"></i>
                                        <?php echo htmlspecialchars($skill); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- File Attachments Card -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">File Lampiran</h3>
                            <button class="btn btn-primary btn-sm rounded-pill px-3" type="button" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="fas fa-plus me-1"></i>Tambah File
                            </button>
                        </div>
                        
                        <?php if (!empty($attachment_urls)): ?>
                            <div class="file-grid">
                                <?php foreach ($attachment_urls as $index => $file_url): ?>
                                    <?php
                                    $extension = pathinfo($file_url, PATHINFO_EXTENSION);
                                    $file_icons = [
                                        'jpg' => ['icon' => 'fas fa-image', 'color' => '#FF6B6B', 'bg' => 'rgba(255, 107, 107, 0.1)'],
                                        'jpeg' => ['icon' => 'fas fa-image', 'color' => '#FF6B6B', 'bg' => 'rgba(255, 107, 107, 0.1)'],
                                        'png' => ['icon' => 'fas fa-image', 'color' => '#FF6B6B', 'bg' => 'rgba(255, 107, 107, 0.1)'],
                                        'gif' => ['icon' => 'fas fa-image', 'color' => '#FF6B6B', 'bg' => 'rgba(255, 107, 107, 0.1)'],
                                        'pdf' => ['icon' => 'fas fa-file-pdf', 'color' => '#dc3545', 'bg' => 'rgba(220, 53, 69, 0.1)'],
                                        'mp4' => ['icon' => 'fas fa-video', 'color' => '#FFC300', 'bg' => 'rgba(255, 195, 0, 0.1)'],
                                        'avi' => ['icon' => 'fas fa-video', 'color' => '#FFC300', 'bg' => 'rgba(255, 195, 0, 0.1)'],
                                        'doc' => ['icon' => 'fas fa-file-word', 'color' => '#3E7FD5', 'bg' => 'rgba(62, 127, 213, 0.1)'],
                                        'docx' => ['icon' => 'fas fa-file-word', 'color' => '#3E7FD5', 'bg' => 'rgba(62, 127, 213, 0.1)']
                                    ];
                                    $file_info = $file_icons[strtolower($extension)] ?? ['icon' => 'fas fa-file', 'color' => '#6c757d', 'bg' => 'rgba(108, 117, 125, 0.1)'];
                                    
                                    $file_size = '';
                                    if (file_exists($file_url)) {
                                        $size = filesize($file_url);
                                        if ($size < 1024) {
                                            $file_size = $size . ' bytes';
                                        } elseif ($size < 1048576) {
                                            $file_size = round($size / 1024, 2) . ' KB';
                                        } else {
                                            $file_size = round($size / 1048576, 2) . ' MB';
                                        }
                                    }
                                    ?>
                                    
                                    <div class="file-card">
                                        <div class="file-icon" style="background: <?php echo $file_info['bg']; ?>; color: <?php echo $file_info['color']; ?>;">
                                            <i class="<?php echo $file_info['icon']; ?>"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name" title="<?php echo basename($file_url); ?>">
                                                <?php echo basename($file_url); ?>
                                            </div>
                                            <div class="file-size"><?php echo $file_size; ?></div>
                                        </div>
                                        <div class="file-actions">
                                            <a href="<?php echo $file_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="delete_file" value="1">
                                                <input type="hidden" name="file_index" value="<?php echo $index; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin ingin menghapus file ini?')" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-upload fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-4">Belum ada file yang diupload</p>
                                <button class="btn btn-outline-primary rounded-pill px-4" type="button" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                    <i class="fas fa-upload me-2"></i>Upload File Pertama
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column - Sidebar -->
                <div class="col-lg-4">
                    <!-- Stats Overview -->
                    <div class="stats-card mb-3">
                        <div class="stats-number"><?php echo $project['proposal_count']; ?></div>
                        <div class="stats-label">Total Proposal</div>
                        <?php if ($project['new_proposal_count'] > 0): ?>
                            <div class="mt-3">
                                <span class="badge bg-danger px-3 py-2">
                                    <i class="fas fa-bell me-1"></i><?php echo $project['new_proposal_count']; ?> Proposal Baru
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Progress Section (for active projects) -->
                    <?php if ($project['status'] === 'in_progress'): ?>
                        <div class="content-card mb-3">
                            <h3 class="card-title mb-3">Progress Proyek</h3>
                            <div class="progress-container">
                                <div class="progress-header">
                                    <span class="progress-label">Status Pengerjaan</span>
                                    <span class="progress-percentage"><?php echo round($progress); ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                            <div class="row mt-3 text-center">
                                <div class="col-6">
                                    <div class="fw-bold"><?php echo $project['completed_milestones']; ?></div>
                                    <small class="text-muted">Milestone Selesai</small>
                                </div>
                                <div class="col-6">
                                    <div class="fw-bold"><?php echo $project['milestone_count']; ?></div>
                                    <small class="text-muted">Total Milestone</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <div class="quick-actions-card">
                        <h3 class="card-title mb-3">Aksi Cepat</h3>
                        
                        <a href="project-proposals.php?id=<?php echo $project['id']; ?>" class="action-btn">
                            <i class="fas fa-file-alt"></i>
                            <span>Kelola Proposal</span>
                            <?php if ($project['new_proposal_count'] > 0): ?>
                                <span class="badge bg-danger ms-auto"><?php echo $project['new_proposal_count']; ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <?php if ($project['status'] === 'draft'): ?>
                            <a href="publish-project.php?id=<?php echo $project['id']; ?>" class="action-btn success">
                                <i class="fas fa-rocket"></i>
                                <span>Publish Proyek</span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($project['status'] === 'open'): ?>
                            <button class="action-btn warning" onclick="showNotification('Fitur ini akan segera tersedia', 'warning')">
                                <i class="fas fa-lock"></i>
                                <span>Tutup Proyek</span>
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($project['contract_count'] > 0): ?>
                            <button class="action-btn" onclick="window.location.href='contracts.php?project_id=<?php echo $project['id']; ?>'">
                                <i class="fas fa-file-contract"></i>
                                <span>Lihat Kontrak</span>
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($project['status'] === 'in_progress'): ?>
                            <button class="action-btn" onclick="window.location.href='milestones.php?project_id=<?php echo $project['id']; ?>'">
                                <i class="fas fa-flag"></i>
                                <span>Kelola Milestone</span>
                            </button>
                        <?php endif; ?>
                        
                        <button class="action-btn danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i>
                            <span>Hapus Proyek</span>
                        </button>
                    </div>

                    <!-- Project Info -->
                    <div class="content-card">
                        <h3 class="card-title mb-3">Informasi Proyek</h3>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">ID Proyek</small>
                            <div class="d-flex align-items-center gap-2">
                                <code><?php echo $project['uuid']; ?></code>
                                <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('<?php echo $project['uuid']; ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Dibuat</small>
                            <div class="fw-bold">
                                <i class="far fa-calendar me-2"></i>
                                <?php echo date('d M Y H:i', strtotime($project['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Terakhir Diupdate</small>
                            <div class="fw-bold">
                                <i class="fas fa-sync-alt me-2"></i>
                                <?php echo date('d M Y H:i', strtotime($project['updated_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($project['business_name']): ?>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">UMKM</small>
                                <div class="fw-bold">
                                    <i class="fas fa-store me-2"></i>
                                    <?php echo htmlspecialchars($project['business_name']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload File Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="upload_files" value="1">
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="form-label">Pilih File</label>
                            <input type="file" class="form-control" name="new_attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.mp4,.avi,.doc,.docx" required>
                            <div class="form-text">Maksimal 5 file, masing-masing maksimal 10MB. Format: JPG, PNG, GIF, PDF, MP4, DOC</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>File akan ditambahkan ke daftar lampiran proyek</small>
                        </div>
                        
                        <?php if (isset($errors['attachments'])): ?>
                            <div class="alert alert-danger"><?php echo $errors['attachments']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Upload File</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aksi Proyek</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <a href="project-proposals.php?id=<?php echo $project['id']; ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <div class="icon-wrapper bg-primary-light rounded p-2">
                                <i class="fas fa-file-alt text-primary"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Kelola Proposal</div>
                                <small class="text-muted">Tinjau dan kelola proposal masuk</small>
                            </div>
                            <?php if ($project['new_proposal_count'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $project['new_proposal_count']; ?> baru</span>
                            <?php endif; ?>
                        </a>
                        
                        <a href="create-project.php?duplicate=<?php echo $project['id']; ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <div class="icon-wrapper bg-success-light rounded p-2">
                                <i class="fas fa-copy text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Duplikat Proyek</div>
                                <small class="text-muted">Buat proyek baru dengan pengaturan yang sama</small>
                            </div>
                        </a>
                        
                        <button class="list-group-item list-group-item-action d-flex align-items-center gap-3" onclick="shareProject()">
                            <div class="icon-wrapper bg-info-light rounded p-2">
                                <i class="fas fa-share-alt text-info"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Bagikan Proyek</div>
                                <small class="text-muted">Bagikan tautan proyek ke kreator</small>
                            </div>
                        </button>
                        
                        <button class="list-group-item list-group-item-action d-flex align-items-center gap-3 text-danger" onclick="confirmDelete()">
                            <div class="icon-wrapper bg-danger-light rounded p-2">
                                <i class="fas fa-trash text-danger"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Hapus Proyek</div>
                                <small class="text-muted">Hapus proyek secara permanen</small>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <p class="mt-3 text-muted">Memproses...</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>


        function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
}
        class ProjectViewManager {
            constructor() {
                this.init();
            }

            init() {
                this.bindEvents();
                this.setupAnimations();
                this.setupAutoRefresh();
                this.initializeCopyButtons();
            }

            bindEvents() {
                // Form submission
                const editForm = document.getElementById('editProjectForm');
                if (editForm) {
                    editForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
                }

                // File upload form
                const uploadForm = document.getElementById('uploadForm');
                if (uploadForm) {
                    uploadForm.addEventListener('submit', (e) => this.handleFileUpload(e));
                }

                // Skill tag interactions
                document.querySelectorAll('.skill-tag').forEach(tag => {
                    tag.addEventListener('click', (e) => this.handleSkillClick(e));
                });

                // File card interactions
                document.querySelectorAll('.file-card').forEach(card => {
                    card.addEventListener('mouseenter', (e) => this.handleFileHover(e));
                    card.addEventListener('mouseleave', (e) => this.handleFileLeave(e));
                });
            }

           handleFormSubmit(e) {
    e.preventDefault();
                const form = e.target;
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    this.showNotification('Harap isi semua field yang wajib diisi', 'error');
                }
            }

            handleFileUpload(e) {
                e.preventDefault();
                const form = e.target;
                const fileInput = form.querySelector('input[type="file"]');
                
                if (fileInput.files.length > 5) {
                    e.preventDefault();
                    this.showNotification('Maksimal 5 file yang dapat diupload sekaligus', 'error');
                    return;
                }

                let totalSize = 0;
                for (let file of fileInput.files) {
                    totalSize += file.size;
                    if (file.size > 10 * 1024 * 1024) { // 10MB
                        e.preventDefault();
                        this.showNotification(`File ${file.name} melebihi ukuran maksimal 10MB`, 'error');
                        return;
                    }
                }

                if (totalSize > 50 * 1024 * 1024) { // 50MB total
                    e.preventDefault();
                    this.showNotification('Total ukuran file melebihi 50MB', 'error');
                }
            }

            handleSkillClick(e) {
                const tag = e.currentTarget;
                tag.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    tag.style.transform = '';
                }, 200);
            }

            handleFileHover(e) {
                const card = e.currentTarget;
                card.style.transform = 'translateY(-3px)';
            }

            handleFileLeave(e) {
                const card = e.currentTarget;
                card.style.transform = '';
            }

            setupAnimations() {
                // Animate cards on scroll
                const observerOptions = {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                };

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }
                    });
                }, observerOptions);

                document.querySelectorAll('.content-card, .stats-card, .quick-actions-card').forEach(card => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    observer.observe(card);
                });
            }

            setupAutoRefresh() {
                // Auto-refresh new proposal count every 30 seconds
                setInterval(() => {
                    this.checkNewProposals();
                }, 30000);
            }

            checkNewProposals() {
                // In real implementation, make AJAX call here
                console.log('Checking for new proposals...');
            }

            initializeCopyButtons() {
                // Initialize copy to clipboard functionality
                document.querySelectorAll('[onclick*="copyToClipboard"]').forEach(btn => {
                    const match = btn.getAttribute('onclick').match(/copyToClipboard\('([^']+)'\)/);
                    if (match) {
                        btn.addEventListener('click', () => {
                            this.copyToClipboard(match[1]);
                        });
                    }
                });
            }

            copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showNotification('Berhasil disalin ke clipboard!', 'success');
                }).catch(err => {
                    console.error('Gagal menyalin: ', err);
                    this.showNotification('Gagal menyalin teks', 'error');
                });
            }

            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-${this.getNotificationIcon(type)} me-3" style="color: ${this.getNotificationColor(type)}; font-size: 1.25rem;"></i>
                        <div>
                            <strong class="d-block">${this.getNotificationTitle(type)}</strong>
                            <small>${message}</small>
                        </div>
                        <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                    </div>
                `;
                
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: white;
                    padding: 1rem 1.5rem;
                    border-radius: 12px;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                    transform: translateX(400px);
                    transition: transform 0.3s ease;
                    z-index: 10000;
                    border-left: 4px solid ${this.getNotificationColor(type)};
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.transform = 'translateX(0)';
                }, 100);
                
                setTimeout(() => {
                    notification.style.transform = 'translateX(400px)';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }, 5000);
            }

            getNotificationIcon(type) {
                const icons = {
                    success: 'check-circle',
                    error: 'exclamation-circle',
                    warning: 'exclamation-triangle',
                    info: 'info-circle'
                };
                return icons[type] || 'info-circle';
            }

            getNotificationColor(type) {
                const colors = {
                    success: '#28a745',
                    error: '#dc3545',
                    warning: '#ffc107',
                    info: '#3E7FD5'
                };
                return colors[type] || '#3E7FD5';
            }

            getNotificationTitle(type) {
                const titles = {
                    success: 'Berhasil!',
                    error: 'Error!',
                    warning: 'Peringatan!',
                    info: 'Info'
                };
                return titles[type] || 'Info';
            }
        }

        // Global functions
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.classList.add('show');
                setTimeout(() => {
                    overlay.classList.remove('show');
                }, 2000);
            }
        }

        function hideNotification(id) {
            const notification = document.getElementById(id);
            if (notification) {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }

        function confirmDelete() {
            if (confirm('Yakin ingin menghapus proyek ini? Tindakan ini tidak dapat dibatalkan.')) {
                showLoading();
                window.location.href = `delete-project.php?id=<?php echo $project['id']; ?>&confirm=1`;
            }
        }

        function shareProject() {
            const projectUrl = window.location.href;
            const shareText = `Lihat proyek saya di Konekin: ${project['title']}`;
            
            if (navigator.share) {
                navigator.share({
                    title: project['title'],
                    text: shareText,
                    url: projectUrl
                });
            } else {
                navigator.clipboard.writeText(projectUrl).then(() => {
                    const manager = new ProjectViewManager();
                    manager.showNotification('Tautan proyek berhasil disalin!', 'success');
                });
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            const projectViewManager = new ProjectViewManager();
            
            // Auto-hide success notification
            const successNotification = document.getElementById('successNotification');
            if (successNotification) {
                setTimeout(() => {
                    hideNotification('successNotification');
                }, 5000);
            }

            // Initialize tooltips
            const tooltips = document.querySelectorAll('[title]');
            tooltips.forEach(element => {
                element.classList.add('tooltip-custom');
            });

            // Add smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Add keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // Ctrl/Cmd + E to edit
                if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                    e.preventDefault();
                    const editButton = document.querySelector('[data-bs-target="#editForm"]');
                    if (editButton) editButton.click();
                }
                
                // Escape to close modals
                if (e.key === 'Escape') {
                    const modals = document.querySelectorAll('.modal.show');
                    if (modals.length > 0) {
                        bootstrap.Modal.getInstance(modals[0]).hide();
                    }
                }
            });
        });

        // Export for global access
        window.ProjectViewManager = ProjectViewManager;
        window.showLoading = showLoading;
        window.hideNotification = hideNotification;
        window.confirmDelete = confirmDelete;
        window.shareProject = shareProject;
    </script>
</body>
</html>