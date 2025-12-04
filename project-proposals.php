<?php
include "check_login.php";

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

$project_id = $_GET['id'] ?? 0;

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();

$project_sql = "SELECT * FROM projects WHERE id = :id AND umkm_user_id = :user_id";
$project_stmt = $conn->prepare($project_sql);
$project_stmt->execute([':id' => $project_id, ':user_id' => $_SESSION['user_id']]);
$project = $project_stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: projects.php");
    exit;
}

// Di bagian atas file, setelah mendapatkan data project
$new_proposals_count_sql = "SELECT COUNT(*) FROM proposals WHERE project_id = :project_id AND status = 'submitted'";
$new_proposals_stmt = $conn->prepare($new_proposals_count_sql);
$new_proposals_stmt->execute([':project_id' => $project_id]);
$new_proposals_count = $new_proposals_stmt->fetchColumn();

$proposals_sql = "
    SELECT 
        pr.*,
        u.full_name as creative_name,
        u.email as creative_email,
        u.avatar_url as creative_avatar,
        cp.tagline as creative_tagline,
        cp.rating as creative_rating,
        cp.completed_projects as creative_completed_projects,
        cp.experience_level as creative_experience
    FROM proposals pr
    JOIN users u ON pr.creative_user_id = u.id
    LEFT JOIN creative_profiles cp ON u.id = cp.user_id
    WHERE pr.project_id = :project_id
    ORDER BY 
        CASE 
            WHEN pr.status = 'submitted' THEN 0
            WHEN pr.status = 'viewed' THEN 1
            ELSE 2
        END,
        pr.submitted_at DESC
";

$proposals_stmt = $conn->prepare($proposals_sql);
$proposals_stmt->execute([':project_id' => $project_id]);
$proposals = $proposals_stmt->fetchAll(PDO::FETCH_ASSOC);

// Update status proposal menjadi 'viewed' (jika ada proposal baru)
if ($new_proposals_count > 0) {
    $update_sql = "UPDATE proposals SET status = 'viewed' WHERE project_id = :project_id AND status = 'submitted'";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([':project_id' => $project_id]);
}

// Hitung statistik proposal
$proposal_stats = [
    'total' => count($proposals),
    'submitted' => 0,
    'viewed' => 0,
    'accepted' => 0,
    'rejected' => 0
];

foreach ($proposals as $proposal) {
    if (isset($proposal_stats[$proposal['status']])) {
        $proposal_stats[$proposal['status']]++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Proyek - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2c5ea3;
            --primary-light: rgba(62, 127, 213, 0.1);
            --secondary-color: #6c757d;
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
            padding-horizontal: 20px;
            padding-top: 10px;
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Hero Header */
        .proposals-hero {
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

        .proposals-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .proposals-hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 2.5rem;
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

        /* Statistics Cards */
        .stats-section {
            margin-bottom: 2.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 2px solid transparent;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease backwards;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 4px 4px 0 0;
        }

        .stat-card.total::before { background: linear-gradient(90deg, var(--primary-color), var(--info-color)); }
        .stat-card.new::before { background: var(--danger-color); }
        .stat-card.viewed::before { background: var(--info-color); }
        .stat-card.accepted::before { background: var(--success-color); }
        .stat-card.rejected::before { background: var(--danger-color); }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-card.total .stat-icon { background: var(--primary-light); color: var(--primary-color); }
        .stat-card.new .stat-icon { background: var(--danger-light); color: var(--danger-color); }
        .stat-card.viewed .stat-icon { background: var(--info-light); color: var(--info-color); }
        .stat-card.accepted .stat-icon { background: var(--success-light); color: var(--success-color); }
        .stat-card.rejected .stat-icon { background: var(--danger-light); color: var(--danger-color); }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, var(--text-dark), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            animation: slideInLeft 0.6s ease;
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .filter-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }

        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .filter-btn {
            padding: 0.6rem 1.25rem;
            border: 2px solid var(--border-color);
            border-radius: 50px;
            background: transparent;
            color: var(--text-muted);
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(62, 127, 213, 0.3);
        }

        .filter-btn .badge {
            background: rgba(255, 255, 255, 0.2);
            color: inherit;
            font-size: 0.75rem;
            padding: 0.1rem 0.5rem;
            border-radius: 10px;
        }

        /* Proposal Cards */
        .proposals-section {
            margin-bottom: 3rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            animation: fadeIn 0.6s ease;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title::before {
            content: '';
            width: 6px;
            height: 24px;
            background: var(--primary-color);
            border-radius: 3px;
        }

        .proposals-count {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .proposals-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Proposal Card */
        .proposal-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: var(--transition);
            border: 2px solid transparent;
            animation: fadeInUp 0.6s ease backwards;
            position: relative;
        }

        .proposal-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }

        .proposal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }

        .proposal-card-header {
            padding: 1.5rem 1.5rem 0;
            position: relative;
        }

        .proposal-status {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border: 2px solid transparent;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .status-submitted { 
            background: rgba(62, 127, 213, 0.1); 
            color: var(--primary-color); 
            border-color: rgba(62, 127, 213, 0.2); 
            animation: pulse 2s infinite;
        }
        
        .status-viewed { 
            background: rgba(23, 162, 184, 0.1); 
            color: var(--info-color); 
            border-color: rgba(23, 162, 184, 0.2); 
        }
        
        .status-accepted { 
            background: rgba(40, 167, 69, 0.1); 
            color: var(--success-color); 
            border-color: rgba(40, 167, 69, 0.2); 
        }
        
        .status-rejected { 
            background: rgba(220, 53, 69, 0.1); 
            color: var(--danger-color); 
            border-color: rgba(220, 53, 69, 0.2); 
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(62, 127, 213, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(62, 127, 213, 0); }
            100% { box-shadow: 0 0 0 0 rgba(62, 127, 213, 0); }
        }

        .proposal-card-body {
            padding: 1.5rem;
        }

        .creative-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .creative-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary-light);
            transition: var(--transition);
        }

        .proposal-card:hover .creative-avatar {
            transform: scale(1.05);
            border-color: var(--primary-color);
        }

        .creative-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .creative-avatar .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            font-weight: 600;
        }

        .creative-details {
            flex: 1;
        }

        .creative-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .creative-tagline {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }

        .creative-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .rating-stars {
            color: #FFC300;
        }

        .proposal-content {
            margin-bottom: 1.5rem;
        }

        .proposal-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .proposal-description {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .proposal-meta {
            background: rgba(248, 249, 250, 0.5);
            border-radius: var(--border-radius-sm);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .meta-item-large {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
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

        .proposal-card-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            background: rgba(248, 249, 250, 0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .proposal-date {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.6rem 1.25rem;
            border: 2px solid;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
        }

        .action-btn.primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .action-btn.primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(62, 127, 213, 0.3);
        }

        .action-btn.success {
            background: var(--success-color);
            border-color: var(--success-color);
            color: white;
        }

        .action-btn.success:hover {
            background: #218838;
            border-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .action-btn.danger {
            background: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
        }

        .action-btn.danger:hover {
            background: #c82333;
            border-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .action-btn.info {
            background: var(--info-color);
            border-color: var(--info-color);
            color: white;
        }

        .action-btn.info:hover {
            background: #138496;
            border-color: #138496;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
        }

        .action-btn.outline {
            background: transparent;
            border-color: var(--border-color);
            color: var(--text-muted);
        }

        .action-btn.outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            animation: fadeIn 0.6s ease;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
            color: var(--primary-color);
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-15px); }
            60% { transform: translateY(-7px); }
        }

        .empty-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
        }

        .empty-description {
            color: var(--text-muted);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .proposals-hero {
                padding: 2rem 1.5rem;
                text-align: center;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-buttons {
                justify-content: center;
            }
            
            .section-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .proposal-card-footer {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .meta-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .creative-info {
                flex-direction: column;
                text-align: center;
            }
            
            .creative-meta {
                justify-content: center;
            }
        }

        /* Modal Custom Styles */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            border-left: 4px solid var(--primary-color);
            transform: translateX(400px);
            transition: transform 0.3s ease;
            z-index: 9999;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success { border-left-color: var(--success-color); }
        .notification.error { border-left-color: var(--danger-color); }
        .notification.warning { border-left-color: var(--warning-color); }
        .notification.info { border-left-color: var(--primary-color); }
        
    </style>
</head>
<body>
    <?php include 'dashboard-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Hero Header -->
            <div class="proposals-hero">
                <div class="proposals-hero-content">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="hero-title">
                                <i class="fas fa-file-alt me-3"></i>Proposal Proyek
                            </h1>
                            <p class="hero-subtitle">
                                Kelola proposal dari creative worker untuk proyek Anda. Tinjau, terima, atau tolak proposal yang masuk.
                            </p>
                            <div class="d-flex gap-3 flex-wrap">
                                <a href="projects.php" class="btn btn-light rounded-pill px-4">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Proyek
                                </a>
                                <a href="view-project.php?id=<?php echo $project_id; ?>" class="btn btn-outline-light rounded-pill px-4">
                                    <i class="fas fa-eye me-2"></i>Lihat Detail Proyek
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4 text-lg-end d-none d-lg-block">
                            <div class="position-relative" style="max-width: 300px; margin-left: auto;">
                                <div class="position-absolute top-0 start-0 w-100 h-100 bg-blue opacity-10 rounded-3"></div>
                                <div class="position-relative p-4 rounded-3">
                                   
                                   
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="stats-section">
                <div class="row mb-4">
                    <div class="col-12">
                        <h3 class="section-title mb-0">📊 Statistik Proposal</h3>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <!-- Total Proposals -->
                    <div class="stat-card total">
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-number"><?php echo $proposal_stats['total']; ?></div>
                        <div class="stat-label">Total Proposal</div>
                    </div>

                    <!-- New Proposals -->
                    <div class="stat-card new">
                        <div class="stat-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="stat-number"><?php echo $proposal_stats['submitted']; ?></div>
                        <div class="stat-label">Baru (Belum Dilihat)</div>
                    </div>

                    <!-- Viewed Proposals -->
                    <div class="stat-card viewed">
                        <div class="stat-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="stat-number"><?php echo $proposal_stats['viewed']; ?></div>
                        <div class="stat-label">Sudah Dilihat</div>
                    </div>

                    <!-- Accepted Proposals -->
                    <div class="stat-card accepted">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number"><?php echo $proposal_stats['accepted']; ?></div>
                        <div class="stat-label">Diterima</div>
                    </div>

                    <!-- Rejected Proposals -->
                    <div class="stat-card rejected">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-number"><?php echo $proposal_stats['rejected']; ?></div>
                        <div class="stat-label">Ditolak</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row align-items-center">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <h4 class="filter-title">Filter Proposal</h4>
                    </div>
                    <div class="col-md-8">
                        <div class="filter-buttons">
                            <button class="filter-btn active" data-filter="all">
                                Semua <span class="badge"><?php echo $proposal_stats['total']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="submitted">
                                Baru <span class="badge"><?php echo $proposal_stats['submitted']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="viewed">
                                Dilihat <span class="badge"><?php echo $proposal_stats['viewed']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="accepted">
                                Diterima <span class="badge"><?php echo $proposal_stats['accepted']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="rejected">
                                Ditolak <span class="badge"><?php echo $proposal_stats['rejected']; ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Proposals Section -->
            <div class="proposals-section">
                <div class="section-header">
                    <div class="d-flex align-items-center gap-3">
                        <h2 class="section-title">Daftar Proposal</h2>
                        <span class="proposals-count"><?php echo $proposal_stats['total']; ?> Proposal</span>
                    </div>
                    <div class="search-box" style="max-width: 300px;">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 search-input" placeholder="Cari proposal...">
                        </div>
                    </div>
                </div>

                <div class="proposals-grid" id="proposalsContainer">
                    <?php if (count($proposals) > 0): ?>
                        <?php foreach ($proposals as $index => $proposal): ?>
                            <?php 
                            // Get first letter for avatar placeholder
                            $avatar_placeholder = strtoupper(substr($proposal['creative_name'], 0, 1));
                            
                            // Experience level labels
                            $experience_labels = [
                                'beginner' => 'Pemula',
                                'intermediate' => 'Menengah',
                                'expert' => 'Ahli'
                            ];
                            $experience_label = isset($proposal['creative_experience']) ? 
                                ($experience_labels[$proposal['creative_experience']] ?? $proposal['creative_experience']) : 
                                'Belum ditentukan';
                            ?>
                            
                            <div class="proposal-card proposal-item" 
                                 data-status="<?php echo $proposal['status']; ?>"
                                 data-creative="<?php echo strtolower($proposal['creative_name']); ?>"
                                 style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                
                                <!-- Proposal Header -->
                                <div class="proposal-card-header">
                                    <span class="proposal-status status-<?php echo $proposal['status']; ?>">
                                        <?php 
                                        $status_labels = [
                                            'submitted' => 'Baru',
                                            'viewed' => 'Dilihat',
                                            'accepted' => 'Diterima',
                                            'rejected' => 'Ditolak',
                                            'cancelled' => 'Dibatalkan'
                                        ];
                                        echo $status_labels[$proposal['status']] ?? $proposal['status'];
                                        ?>
                                    </span>
                                </div>
                                
                                <!-- Proposal Body -->
                                <div class="proposal-card-body">
                                    <!-- Creative Info -->
                                    <div class="creative-info">
                                        <div class="creative-avatar">
                                            <?php if ($proposal['creative_avatar']): ?>
                                                <img src="<?php echo htmlspecialchars($proposal['creative_avatar']); ?>" alt="<?php echo htmlspecialchars($proposal['creative_name']); ?>">
                                            <?php else: ?>
                                                <div class="avatar-placeholder">
                                                    <?php echo $avatar_placeholder; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="creative-details">
                                            <h3 class="creative-name"><?php echo htmlspecialchars($proposal['creative_name']); ?></h3>
                                            
                                            <?php if ($proposal['creative_tagline']): ?>
                                                <p class="creative-tagline"><?php echo htmlspecialchars($proposal['creative_tagline']); ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="creative-meta">
                                                <?php if ($proposal['creative_rating'] > 0): ?>
                                                    <div class="meta-item">
                                                        <div class="rating-stars">
                                                            <?php 
                                                            $rating = round($proposal['creative_rating']);
                                                            for ($i = 1; $i <= 5; $i++): 
                                                            ?>
                                                                <i class="fas fa-star<?php echo $i <= $rating ? '' : '-o'; ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <span><?php echo $proposal['creative_rating']; ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="meta-item">
                                                    <i class="fas fa-briefcase"></i>
                                                    <span><?php echo $proposal['creative_completed_projects']; ?> proyek</span>
                                                </div>
                                                
                                                <div class="meta-item">
                                                    <i class="fas fa-chart-line"></i>
                                                    <span><?php echo $experience_label; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Cover Letter -->
                                    <div class="proposal-content">
                                        <h4 class="proposal-title">
                                            <i class="fas fa-envelope-open-text"></i>Cover Letter
                                        </h4>
                                        <div class="proposal-description">
                                            <?php echo nl2br(htmlspecialchars($proposal['cover_letter'])); ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Proposal Details -->
                                    <div class="proposal-meta">
                                        <div class="meta-grid">
                                            <?php if ($proposal['proposed_budget']): ?>
                                                <div class="meta-item-large">
                                                    <span class="meta-label">Budget yang Diajukan</span>
                                                    <span class="meta-value budget">
                                                        Rp <?php echo number_format($proposal['proposed_budget'], 0, ',', '.'); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($proposal['timeline_days']): ?>
                                                <div class="meta-item-large">
                                                    <span class="meta-label">Estimasi Waktu</span>
                                                    <span class="meta-value">
                                                        <?php echo $proposal['timeline_days']; ?> hari
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($proposal['creative_email']): ?>
                                                <div class="meta-item-large">
                                                    <span class="meta-label">Email</span>
                                                    <span class="meta-value">
                                                        <?php echo htmlspecialchars($proposal['creative_email']); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($proposal['timeline_description']): ?>
                                            <div class="mt-3">
                                                <span class="meta-label">Rencana Pengerjaan:</span>
                                                <p class="mt-1"><?php echo nl2br(htmlspecialchars($proposal['timeline_description'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Proposal Footer -->
                                <div class="proposal-card-footer">
                                    <div class="proposal-date">
                                        <i class="far fa-clock me-1"></i>
                                        Diajukan: <?php echo date('d M Y H:i', strtotime($proposal['submitted_at'])); ?>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <?php if ($proposal['status'] == 'submitted' || $proposal['status'] == 'viewed'): ?>
                                            <button class="action-btn success" data-bs-toggle="modal" data-bs-target="#acceptModal<?php echo $proposal['id']; ?>">
                                                <i class="fas fa-check me-1"></i>Terima
                                            </button>
                                            <button class="action-btn danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $proposal['id']; ?>">
                                                <i class="fas fa-times me-1"></i>Tolak
                                            </button>
                                        <?php elseif ($proposal['status'] == 'accepted'): ?>
                                            <span class="text-success fw-bold">
                                                <i class="fas fa-check-circle me-1"></i>Proposal Diterima
                                            </span>
                                        <?php elseif ($proposal['status'] == 'rejected'): ?>
                                            <span class="text-danger fw-bold">
                                                <i class="fas fa-times-circle me-1"></i>Proposal Ditolak
                                            </span>
                                        <?php endif; ?>
                                        
                                        <a href="view-portfolio.php?creative_id=<?php echo $proposal['creative_user_id']; ?>" class="action-btn outline">
                                            <i class="fas fa-eye me-1"></i>Portfolio
                                        </a>
                                        
                                        <a href="messages.php?user_id=<?php echo $proposal['creative_user_id']; ?>&project_id=<?php echo $project_id; ?>" class="action-btn info">
                                            <i class="fas fa-envelope me-1"></i>Chat
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Accept Modal -->
                            <div class="modal fade" id="acceptModal<?php echo $proposal['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-check-circle text-success me-2"></i>Terima Proposal
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Apakah Anda yakin ingin menerima proposal dari <strong><?php echo htmlspecialchars($proposal['creative_name']); ?></strong>?</p>
                                            <div class="alert alert-success mt-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Proposal ini akan dipilih untuk mengerjakan proyek "<strong><?php echo htmlspecialchars($project['title']); ?></strong>".
                                            </div>
                                            <div class="mt-4 p-3 bg-light rounded">
                                                <h6>Detail Proposal:</h6>
                                                <ul class="mb-0">
                                                    <?php if ($proposal['proposed_budget']): ?>
                                                        <li>Budget: Rp <?php echo number_format($proposal['proposed_budget'], 0, ',', '.'); ?></li>
                                                    <?php endif; ?>
                                                    <?php if ($proposal['timeline_days']): ?>
                                                        <li>Estimasi: <?php echo $proposal['timeline_days']; ?> hari</li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                            <a href="accept-proposal.php?proposal_id=<?php echo $proposal['id']; ?>" class="btn btn-success">
                                                <i class="fas fa-check me-1"></i>Ya, Terima Proposal
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal<?php echo $proposal['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-times-circle text-danger me-2"></i>Tolak Proposal
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Apakah Anda yakin ingin menolak proposal dari <strong><?php echo htmlspecialchars($proposal['creative_name']); ?></strong>?</p>
                                            <div class="alert alert-warning mt-3">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                Creative worker akan diberitahu tentang penolakan proposal ini.
                                            </div>
                                            <div class="mb-3">
                                                <label for="rejectReason<?php echo $proposal['id']; ?>" class="form-label">Alasan penolakan (opsional):</label>
                                                <textarea class="form-control" id="rejectReason<?php echo $proposal['id']; ?>" rows="3" placeholder="Berikan alasan penolakan (akan dikirim ke creative worker)..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="button" class="btn btn-danger" onclick="rejectProposal(<?php echo $proposal['id']; ?>)">
                                                <i class="fas fa-times me-1"></i>Ya, Tolak Proposal
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h3 class="empty-title">Belum Ada Proposal</h3>
                            <p class="empty-description">
                                Proyek Anda belum menerima proposal dari creative worker.
                                Bagikan proyek Anda untuk mendapatkan proposal terbaik.
                            </p>
                            <div class="d-flex gap-3 justify-content-center">
                                <a href="projects.php" class="btn btn-outline-primary btn-lg px-4 rounded-pill">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Proyek
                                </a>
                                <a href="view-project.php?id=<?php echo $project_id; ?>" class="btn btn-primary btn-lg px-4 rounded-pill">
                                    <i class="fas fa-share-alt me-2"></i>Bagikan Proyek
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class ProposalsManager {
            constructor() {
                this.proposals = document.querySelectorAll('.proposal-item');
                this.filterButtons = document.querySelectorAll('.filter-btn');
                this.searchInput = document.querySelector('.search-input');
                this.init();
            }

            init() {
                this.bindEvents();
                this.setupAnimations();
                this.setupNotifications();
            }

            bindEvents() {
                // Filter buttons
                this.filterButtons.forEach(btn => {
                    btn.addEventListener('click', (e) => this.handleFilter(e));
                });

                // Search functionality
                if (this.searchInput) {
                    this.searchInput.addEventListener('input', (e) => this.handleSearch(e));
                }

                // Proposal card interactions
                this.proposals.forEach(card => {
                    card.addEventListener('mouseenter', (e) => this.handleCardHover(e));
                    card.addEventListener('mouseleave', (e) => this.handleCardLeave(e));
                });
            }

            handleFilter(e) {
                const filter = e.currentTarget.dataset.filter;
                
                // Update active state
                this.filterButtons.forEach(btn => {
                    btn.classList.remove('active');
                });
                e.currentTarget.classList.add('active');

                // Filter proposals
                this.animateFilterChange(filter);
            }

            animateFilterChange(filter) {
                this.proposals.forEach((proposal, index) => {
                    const status = proposal.dataset.status;
                    
                    if (filter === 'all' || status === filter) {
                        // Show with animation
                        setTimeout(() => {
                            proposal.style.display = 'block';
                            proposal.style.animation = 'fadeInUp 0.5s ease forwards';
                        }, index * 50);
                    } else {
                        // Hide with animation
                        proposal.style.animation = 'fadeOutDown 0.5s ease forwards';
                        setTimeout(() => {
                            proposal.style.display = 'none';
                        }, 500);
                    }
                });
            }

            handleSearch(e) {
                const searchTerm = this.searchInput ? this.searchInput.value.toLowerCase() : '';
                
                this.proposals.forEach((proposal, index) => {
                    const creativeName = proposal.dataset.creative || '';
                    
                    const matchesSearch = creativeName.includes(searchTerm);
                    
                    const currentFilter = document.querySelector('.filter-btn.active')?.dataset.filter;
                    const status = proposal.dataset.status || '';
                    const matchesFilter = !currentFilter || currentFilter === 'all' || status === currentFilter;
                    
                    if (matchesSearch && matchesFilter) {
                        proposal.style.display = 'block';
                        proposal.style.animation = 'fadeInUp 0.5s ease forwards';
                    } else {
                        proposal.style.animation = 'fadeOutDown 0.5s ease forwards';
                        setTimeout(() => {
                            proposal.style.display = 'none';
                        }, 500);
                    }
                });
            }

            handleCardHover(e) {
                const card = e.currentTarget;
                const avatar = card.querySelector('.creative-avatar');
                
                if (avatar) {
                    avatar.style.transform = 'scale(1.05)';
                    avatar.style.borderColor = 'var(--primary-color)';
                }
            }

            handleCardLeave(e) {
                const card = e.currentTarget;
                const avatar = card.querySelector('.creative-avatar');
                
                if (avatar) {
                    avatar.style.transform = 'scale(1)';
                    avatar.style.borderColor = 'var(--primary-light)';
                }
            }

            setupAnimations() {
                // Stagger animation for proposal cards
                this.proposals.forEach((card, index) => {
                    card.style.animationDelay = `${index * 0.1}s`;
                });
            }

            setupNotifications() {
                <?php if ($new_proposals_count > 0): ?>
                // Show notification for new proposals
                this.showNotification(
                    'Anda memiliki <?php echo $new_proposals_count; ?> proposal baru!',
                    'info'
                );
                <?php endif; ?>
            }

            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-${this.getNotificationIcon(type)} me-3 fs-5"></i>
                        <div>
                            <strong class="d-block">${this.getNotificationTitle(type)}</strong>
                            <span>${message}</span>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                // Animate in
                setTimeout(() => {
                    notification.classList.add('show');
                }, 100);
                
                // Remove after 5 seconds
                setTimeout(() => {
                    notification.classList.remove('show');
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

        // Function to reject proposal (from original code)
        function rejectProposal(proposalId) {
            const reason = document.getElementById('rejectReason' + proposalId)?.value || '';
            window.location.href = 'reject-proposal.php?proposal_id=' + proposalId + '&reason=' + encodeURIComponent(reason);
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            const proposalsManager = new ProposalsManager();
            
            // Add CSS animations if not already present
            if (!document.querySelector('style#proposal-animations')) {
                const style = document.createElement('style');
                style.id = 'proposal-animations';
                style.textContent = `
                    @keyframes fadeInUp {
                        from { opacity: 0; transform: translateY(20px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    
                    @keyframes fadeOutDown {
                        from { opacity: 1; transform: translateY(0); }
                        to { opacity: 0; transform: translateY(20px); }
                    }
                    
                    .proposal-item {
                        animation: fadeInUp 0.6s ease backwards;
                    }
                `;
                document.head.appendChild(style);
            }
        });

        // Export for global access
        window.ProposalsManager = ProposalsManager;
    </script>
</body>
</html>