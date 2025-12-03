<?php
include "check_login.php";

// Hanya UMKM yang bisa akses halaman ini
if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit;
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$conn = $database->getConnection();


// Ambil semua proyek milik UMKM ini dengan informasi pembayaran
$sql = "
    SELECT 
        p.*,
        (SELECT c.id FROM contracts c WHERE c.project_id = p.id AND c.status IN ('active', 'completed') ORDER BY 
            CASE 
                WHEN c.status = 'active' THEN 0
                WHEN c.status = 'completed' THEN 1
                ELSE 2
            END, 
            c.created_at DESC LIMIT 1) as contract_id,
        
        (SELECT c.progress_percentage FROM contracts c WHERE c.project_id = p.id AND c.status IN ('active', 'completed') ORDER BY 
            CASE 
                WHEN c.status = 'active' THEN 0
                WHEN c.status = 'completed' THEN 1
                ELSE 2
            END, 
            c.created_at DESC LIMIT 1) as contract_progress,
        
        (SELECT c.payment_status FROM contracts c WHERE c.project_id = p.id AND c.status IN ('active', 'completed') ORDER BY 
            CASE 
                WHEN c.status = 'active' THEN 0
                WHEN c.status = 'completed' THEN 1
                ELSE 2
            END, 
            c.created_at DESC LIMIT 1) as payment_status,
        
        (SELECT c.creative_user_id FROM contracts c WHERE c.project_id = p.id AND c.status IN ('active', 'completed') ORDER BY 
            CASE 
                WHEN c.status = 'active' THEN 0
                WHEN c.status = 'completed' THEN 1
                ELSE 2
            END, 
            c.created_at DESC LIMIT 1) as creative_user_id,
        
        (SELECT c.title FROM contracts c WHERE c.project_id = p.id AND c.status IN ('active', 'completed') ORDER BY 
            CASE 
                WHEN c.status = 'active' THEN 0
                WHEN c.status = 'completed' THEN 1
                ELSE 2
            END, 
            c.created_at DESC LIMIT 1) as contract_title,
        
        COUNT(DISTINCT pr.id) as proposal_count,
        COUNT(DISTINCT CASE WHEN pr.status = 'submitted' THEN pr.id END) as new_proposal_count,
        (SELECT COUNT(DISTINCT c.id) FROM contracts c WHERE c.project_id = p.id AND c.status IN ('active', 'completed')) as contract_count,
        MAX(pr.submitted_at) as last_proposal_date,
        u.avatar_url as umkm_avatar,
        up.business_name
    FROM projects p
    LEFT JOIN proposals pr ON p.id = pr.project_id
    JOIN users u ON p.umkm_user_id = u.id
    LEFT JOIN umkm_profiles up ON u.id = up.user_id
    WHERE p.umkm_user_id = :user_id
    GROUP BY p.id
    ORDER BY 
        CASE 
            WHEN p.status = 'open' AND MAX(pr.submitted_at) IS NOT NULL THEN 0
            WHEN p.status = 'open' THEN 1
            WHEN p.status = 'in_progress' THEN 2
            WHEN p.status = 'completed' THEN 3
            ELSE 4
        END,
        p.created_at DESC
";
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            ' . $_SESSION['success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            ' . $_SESSION['error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['error']);
}
$projects = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

$stats = [
    'total' => count($projects),
    'open' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'draft' => 0,
    'cancelled' => 0,
    'total_proposals' => 0,
    'new_proposals' => 0,
    'total_budget' => 0,
    'pending_payments' => 0,
    'paid_payments' => 0,
    'partial_payments' => 0,
    'total_progress' => 0,
    'active_projects_count' => 0
];

foreach ($projects as $project) {
    $stats[$project['status']]++;
    $stats['total_proposals'] += $project['proposal_count'];
    $stats['new_proposals'] += $project['new_proposal_count'];
    if ($project['budget_range_min']) {
        $stats['total_budget'] += $project['budget_range_min'];
    }
    
    // Hitung statistik pembayaran
    if ($project['payment_status'] === 'pending') {
        $stats['pending_payments']++;
    } elseif ($project['payment_status'] === 'paid') {
        $stats['paid_payments']++;
    } elseif ($project['payment_status'] === 'partial') {
        $stats['partial_payments']++;
    }
    
    // Hitung progress rata-rata untuk proyek aktif
    if ($project['status'] === 'in_progress' && isset($project['contract_progress'])) {
        $stats['total_progress'] += $project['contract_progress'];
        $stats['active_projects_count']++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyek Saya - Konekin</title>
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
        .projects-hero {
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

        .projects-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .projects-hero-content {
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

        .cta-button {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .cta-button:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            color: white;
        }

        /* Statistics Cards */
        .stats-section {
            margin-bottom: 2.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.75rem;
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
        .stat-card:nth-child(6) { animation-delay: 0.6s; }

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
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
            border-radius: 4px 4px 0 0;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }

        .stat-card.total { border-top-color: var(--primary-color); }
        .stat-card.pending { border-top-color: #d68910; }
        .stat-card.paid { border-top-color: var(--success-color); }
        .stat-card.active { border-top-color: var(--warning-color); }
        .stat-card.completed { border-top-color: var(--info-color); }
        .stat-card.draft { border-top-color: var(--secondary-color); }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
            transition: var(--transition);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-card.total .stat-icon { background: var(--primary-light); color: var(--primary-color); }
        .stat-card.pending .stat-icon { background: rgba(214, 137, 16, 0.1); color: #d68910; }
        .stat-card.paid .stat-icon { background: var(--success-light); color: var(--success-color); }
        .stat-card.active .stat-icon { background: var(--warning-light); color: var(--warning-color); }
        .stat-card.completed .stat-icon { background: var(--info-light); color: var(--info-color); }
        .stat-card.draft .stat-icon { background: rgba(108, 117, 125, 0.1); color: var(--secondary-color); }

        .stat-number {
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, var(--text-dark), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.95rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-trend {
            font-size: 0.85rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            background: var(--primary-light);
            color: var(--primary-color);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 600;
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
            position: relative;
            overflow: hidden;
        }

        .filter-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(62, 127, 213, 0.1), transparent);
            transition: left 0.5s;
        }

        .filter-btn:hover::before {
            left: 100%;
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

        /* Projects Grid */
        .projects-section {
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

        .projects-count {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .projects-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Project Card */
        .project-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: var(--transition);
            border: 2px solid transparent;
            animation: fadeInUp 0.6s ease backwards;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .project-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }

        .project-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }

        .project-card-header {
            padding: 1.5rem 1.5rem 0;
            position: relative;
            min-height: 70px;
        }

        .project-category {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .category-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--primary-color);
            transition: var(--transition);
        }

        .project-card:hover .category-icon {
            transform: rotate(10deg) scale(1.1);
        }

        .category-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .project-status-container {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        .project-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border: 2px solid transparent;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .status-draft { background: rgba(108, 117, 125, 0.1); color: var(--secondary-color); border-color: rgba(108, 117, 125, 0.2); }
        .status-open { background: rgba(40, 167, 69, 0.1); color: var(--success-color); border-color: rgba(40, 167, 69, 0.2); }
        .status-in_progress { background: rgba(255, 193, 7, 0.1); color: var(--warning-color); border-color: rgba(255, 193, 7, 0.2); }
        .status-completed { background: rgba(23, 162, 184, 0.1); color: var(--info-color); border-color: rgba(23, 162, 184, 0.2); }
        .status-cancelled { background: rgba(220, 53, 69, 0.1); color: var(--danger-color); border-color: rgba(220, 53, 69, 0.2); }

        /* Payment Status Styles */
        .payment-status {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            border: 1px solid transparent;
            margin-left: 0.5rem;
            gap: 0.3rem;
        }

        .payment-pending {
            background: rgba(214, 137, 16, 0.1);
            color: #d68910;
            border-color: rgba(214, 137, 16, 0.2);
        }

        .payment-partial {
            background: rgba(41, 128, 185, 0.1);
            color: #2980b9;
            border-color: rgba(41, 128, 185, 0.2);
        }

        .payment-paid {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
            border-color: rgba(39, 174, 96, 0.2);
        }

        .payment-failed {
            background: rgba(192, 57, 43, 0.1);
            color: #c0392b;
            border-color: rgba(192, 57, 43, 0.2);
        }

        .project-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .project-description {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .project-card-body {
            padding: 0 1.5rem;
            flex: 1;
        }

        /* Project Meta */
        .project-meta {
            background: rgba(248, 249, 250, 0.5);
            border-radius: var(--border-radius-sm);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .meta-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .meta-value {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .meta-value.budget {
            color: var(--primary-color);
        }

        .payment-status-badge {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-weight: 600;
        }

        .payment-status-badge i {
            font-size: 0.8rem;
        }

        .payment-status-badge.payment-pending { color: #d68910; }
        .payment-status-badge.payment-partial { color: #2980b9; }
        .payment-status-badge.payment-paid { color: #27ae60; }
        .payment-status-badge.payment-failed { color: #c0392b; }

        .proposal-count {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .new-proposal-badge {
            background: var(--danger-color);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Progress Bar */
        .progress-container {
            margin-bottom: 1.5rem;
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
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .progress-bar {
            height: 8px;
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

        /* Action Buttons */
        .project-card-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            background: rgba(248, 249, 250, 0.5);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.6rem 1rem;
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

        .action-btn.warning {
            background: var(--warning-color);
            border-color: var(--warning-color);
            color: var(--text-dark);
        }

        .action-btn.warning:hover {
            background: #e0a800;
            border-color: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
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
            grid-column: 1 / -1;
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

        /* Floating Create Button */
        .floating-create-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            z-index: 1000;
            text-decoration: none;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .floating-create-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1) rotate(90deg);
            box-shadow: var(--shadow-xl);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .projects-hero {
                padding: 2rem 1.5rem;
                text-align: center;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                justify-content: center;
            }
            
            .section-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .projects-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .project-status-container {
                position: relative;
                top: 0;
                right: 0;
                flex-direction: row;
                justify-content: flex-start;
                margin-top: 1rem;
            }
        }

        /* Loading Skeletons */
        .loading-skeleton {
            animation: skeleton-loading 1.5s infinite;
        }

        @keyframes skeleton-loading {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }

        /* Notifications */
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
            <div class="projects-hero">
                <div class="projects-hero-content">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="hero-title">
                                <i class="fas fa-briefcase me-3"></i>Proyek Saya
                            </h1>
                            <p class="hero-subtitle">
                                Kelola dan pantau semua proyek Anda di satu tempat. Temukan kreator terbaik untuk mewujudkan ide Anda.
                            </p>
                            <div class="d-flex gap-3 flex-wrap">
                                <a href="create-project.php" class="cta-button">
                                    <i class="fas fa-plus-circle me-2"></i>Buat Proyek Baru
                                </a>
                                <a href="find-creatives.php" class="cta-button">
                                    <i class="fas fa-search me-2"></i>Cari Kreator
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4 text-lg-end d-none d-lg-block">
                            <div class="position-relative" style="max-width: 300px; margin-left: auto;">
                                <div class="position-absolute top-0 start-0 w-100 h-100 bg-blue opacity-10 rounded-3"></div>
                                <div class="position-relative p-4 rounded-3">
                                    <h5 class="mb-2">💰 Pembayaran</h5>
                                    <h2 class="mb-0">Rp <?php echo number_format($stats['total_budget'], 0, ',', '.'); ?></h2>
                                    <small class="opacity-75"><?php echo $stats['paid_payments']; ?> Lunas / <?php echo $stats['pending_payments']; ?> Pending</small>
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
                        <h3 class="section-title mb-0">📊 Ringkasan Proyek</h3>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <!-- Total Projects -->
                    <div class="stat-card total">
                        <div class="stat-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Proyek</div>
                        <div class="stat-trend">
                            <i class="fas fa-chart-line"></i>
                            <?php echo $stats['open']; ?> Open
                        </div>
                    </div>

                    <!-- Pending Payments -->
                    <div class="stat-card pending">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['pending_payments']; ?></div>
                        <div class="stat-label">Pembayaran Pending</div>
                        <div class="stat-trend">
                            <i class="fas fa-exclamation-circle"></i>
                            Perlu Tindakan
                        </div>
                    </div>

                    <!-- Paid Payments -->
                    <div class="stat-card paid">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['paid_payments']; ?></div>
                        <div class="stat-label">Pembayaran Lunas</div>
                        <div class="stat-trend">
                            <i class="fas fa-trophy"></i>
                            Rp <?php echo number_format($stats['total_budget'], 0, ',', '.'); ?>
                        </div>
                    </div>

                    <!-- Active Projects -->
                    <div class="stat-card active">
                        <div class="stat-icon">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                        <div class="stat-label">Sedang Berjalan</div>
                        <div class="stat-trend">
                            <i class="fas fa-play-circle"></i>
                            Active Now
                        </div>
                    </div>

                    <!-- Completed Projects -->
                    <div class="stat-card completed">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['completed']; ?></div>
                        <div class="stat-label">Selesai</div>
                        <div class="stat-trend">
                            <i class="fas fa-trophy"></i>
                            Success Rate: 95%
                        </div>
                    </div>

                    <!-- Draft Projects -->
                    <div class="stat-card draft">
                        <div class="stat-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['draft']; ?></div>
                        <div class="stat-label">Dalam Draft</div>
                        <div class="stat-trend">
                            <i class="fas fa-pen"></i>
                            Need Attention
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row align-items-center">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <h4 class="filter-title">Filter Proyek</h4>
                    </div>
                    <div class="col-md-8">
                        <div class="filter-buttons">
                            <button class="filter-btn active" data-filter="all">
                                Semua <span class="badge"><?php echo $stats['total']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="open">
                                Open <span class="badge"><?php echo $stats['open']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="in_progress">
                                Berjalan <span class="badge"><?php echo $stats['in_progress']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="completed">
                                Selesai <span class="badge"><?php echo $stats['completed']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="draft">
                                Draft <span class="badge"><?php echo $stats['draft']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="cancelled">
                                Dibatalkan <span class="badge"><?php echo $stats['cancelled']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="payment_pending">
                                <i class="fas fa-clock me-1"></i>Pending <span class="badge"><?php echo $stats['pending_payments']; ?></span>
                            </button>
                            <button class="filter-btn" data-filter="payment_paid">
                                <i class="fas fa-check-circle me-1"></i>Lunas <span class="badge"><?php echo $stats['paid_payments']; ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Projects Section -->
            <div class="projects-section">
                <div class="section-header">
                    <div class="d-flex align-items-center gap-3">
                        <h2 class="section-title">Daftar Proyek</h2>
                        <span class="projects-count"><?php echo $stats['total']; ?> Proyek</span>
                    </div>
                    <div class="search-box" style="max-width: 300px;">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 search-input" placeholder="Cari proyek...">
                            <button class="btn btn-outline-primary" type="button" id="searchBtn">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="projects-grid" id="projectsContainer">
                    <?php if (count($projects) > 0): ?>
                        <?php foreach ($projects as $index => $project): ?>
                            <?php 
                            $category_icons = [
                                'website' => ['icon' => 'fas fa-globe', 'color' => '#3E7FD5'],
                                'logo' => ['icon' => 'fas fa-palette', 'color' => '#FF6B6B'],
                                'social_media' => ['icon' => 'fas fa-hashtag', 'color' => '#6BC5D2'],
                                'video' => ['icon' => 'fas fa-video', 'color' => '#FFC300'],
                                'content' => ['icon' => 'fas fa-file-alt', 'color' => '#28a745'],
                                'marketing' => ['icon' => 'fas fa-bullhorn', 'color' => '#9C27B0'],
                                'other' => ['icon' => 'fas fa-ellipsis-h', 'color' => '#6c757d']
                            ];
                            
                            $category = $project['category'] ?? 'other';
                            $icon = $category_icons[$category] ?? $category_icons['other'];
                            
                            $status_labels = [
                                'draft' => 'Draft',
                                'open' => 'Open',
                                'in_progress' => 'In Progress',
                                'completed' => 'Selesai',
                                'cancelled' => 'Dibatalkan'
                            ];
                            
                            $status_label = $status_labels[$project['status']] ?? $project['status'];
                            
                            $payment_labels = [
                                'pending' => ['label' => 'Pending', 'class' => 'payment-pending', 'icon' => 'fas fa-clock'],
                                'partial' => ['label' => 'Partial', 'class' => 'payment-partial', 'icon' => 'fas fa-money-bill-wave'],
                                'paid' => ['label' => 'Paid', 'class' => 'payment-paid', 'icon' => 'fas fa-check-circle']
                            ];
                            
                            $payment_status = $project['payment_status'] ?? 'pending';
                            $payment_info = $payment_labels[$payment_status] ?? $payment_labels['pending'];
                            
                            $progress = 0;
                            if ($project['status'] === 'in_progress' || $project['status'] === 'completed') {
                                // Gunakan progress dari database jika tersedia
                                if (isset($project['contract_progress']) && $project['contract_progress'] !== null) {
                                    $progress = $project['contract_progress'];
                                } elseif ($project['status'] === 'completed') {
                                    $progress = 100;
                                } elseif ($project['contract_count'] > 0) {
                                    // Fallback jika tidak ada progress di database tapi ada kontrak
                                    $progress = 0;
                                }
                            }
                            ?>
                            
                            <div class="project-card project-item" 
                                 data-status="<?php echo $project['status']; ?>"
                                 data-payment="<?php echo $payment_status; ?>"
                                 data-category="<?php echo $category; ?>"
                                 data-title="<?php echo strtolower($project['title']); ?>"
                                 style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                
                                <!-- Payment Status Badge -->
                                <?php if (in_array($project['status'], ['in_progress', 'completed']) && $payment_status === 'paid'): ?>
                                    <div class="position-absolute top-0 start-0 m-2">
                                        <div class="paid-badge" style="background: #27ae60; color: white; padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.7rem; font-weight: 600; z-index: 1;">
                                            <i class="fas fa-check-circle me-1"></i>LUNAS
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Project Header -->
                                <div class="project-card-header">
                                    <div class="project-category">
                                        <div class="category-icon" style="background: <?php echo $icon['color']; ?>20; color: <?php echo $icon['color']; ?>">
                                            <i class="<?php echo $icon['icon']; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="category-name">
                                                <?php echo ucfirst(str_replace('_', ' ', $category)); ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="far fa-calendar me-1"></i>
                                                <?php echo date('d M Y', strtotime($project['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="project-status-container">
                                        <?php if (in_array($project['status'], ['in_progress', 'completed'])): ?>
                                            <div class="payment-status <?php echo $payment_info['class']; ?>">
                                                <i class="<?php echo $payment_info['icon']; ?>"></i>
                                                <?php echo $payment_info['label']; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <span class="project-status status-<?php echo $project['status']; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Project Body -->
                                <div class="project-card-body">
                                    <h3 class="project-title" title="<?php echo htmlspecialchars($project['title']); ?>">
                                        <?php echo htmlspecialchars($project['title']); ?>
                                    </h3>
                                    
                                    <p class="project-description" title="<?php echo htmlspecialchars($project['description']); ?>">
                                        <?php echo htmlspecialchars(mb_strlen($project['description']) > 150 ? mb_substr($project['description'], 0, 150) . '...' : $project['description']); ?>
                                    </p>
                                    
                                    <!-- Project Meta -->
                                    <div class="project-meta">
                                        <div class="meta-grid">
                                            <div class="meta-item">
                                                <span class="meta-label">Budget</span>
                                                <span class="meta-value budget">
                                                    <?php if ($project['budget_range_min']): ?>
                                                        Rp <?php echo number_format($project['budget_range_min'], 0, ',', '.'); ?>
                                                        <?php if ($project['budget_range_max'] && $project['budget_range_max'] > $project['budget_range_min']): ?>
                                                            - <?php echo number_format($project['budget_range_max'], 0, ',', '.'); ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        Negotiable
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="meta-item">
                                                <span class="meta-label">Proposal</span>
                                                <div class="proposal-count">
                                                    <span class="meta-value"><?php echo $project['proposal_count']; ?></span>
                                                    <?php if ($project['new_proposal_count'] > 0): ?>
                                                        <span class="new-proposal-badge"><?php echo $project['new_proposal_count']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="meta-item">
                                                <span class="meta-label">Deadline</span>
                                                <span class="meta-value">
                                                    <?php if ($project['deadline']): ?>
                                                        <?php echo date('d M Y', strtotime($project['deadline'])); ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            
                                            <?php if (in_array($project['status'], ['in_progress', 'completed'])): ?>
                                                <div class="meta-item">
                                                    <span class="meta-label">Status Pembayaran</span>
                                                    <div class="payment-status-badge <?php echo $payment_info['class']; ?>">
                                                        <i class="<?php echo $payment_info['icon']; ?>"></i>
                                                        <span><?php echo $payment_info['label']; ?></span>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="meta-item">
                                                    <span class="meta-label">Kontrak</span>
                                                    <span class="meta-value"><?php echo $project['contract_count']; ?> Aktif</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress Bar (for in_progress projects) -->
                                    <?php if (in_array($project['status'], ['in_progress', 'completed']) && $progress > 0): ?>
                                        <div class="progress-container">
                                            <div class="progress-header">
                                                <span class="progress-label">Progress Pengerjaan</span>
                                                <span class="progress-percentage"><?php echo $progress; ?>%</span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="project-card-footer">
                                    <div class="action-buttons">
                                        <?php if ($project['status'] === 'draft'): ?>
                                            <a href="edit-project.php?id=<?php echo $project['id']; ?>" class="action-btn primary">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </a>
                                            <a href="publish-project.php?id=<?php echo $project['id']; ?>" class="action-btn success">
                                                <i class="fas fa-rocket me-1"></i>Publish
                                            </a>
                                            <a href="delete-project.php?id=<?php echo $project['id']; ?>" class="action-btn outline" onclick="return confirm('Yakin ingin menghapus proyek ini?')">
                                                <i class="fas fa-trash me-1"></i>Hapus
                                            </a>
                                        <?php elseif ($project['status'] === 'open'): ?>
                                            <a href="view-project.php?id=<?php echo $project['id']; ?>" class="action-btn primary">
                                                <i class="fas fa-eye me-1"></i>Detail
                                            </a>
                                            <a href="project-proposals.php?id=<?php echo $project['id']; ?>" class="action-btn success">
                                                <i class="fas fa-file-alt me-1"></i>Proposal
                                                <?php if ($project['new_proposal_count'] > 0): ?>
                                                    <span class="badge bg-danger ms-1"><?php echo $project['new_proposal_count']; ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <a href="messages.php?project_id=<?php echo $project['id']; ?>" class="action-btn info">
                                                <i class="fas fa-comments me-1"></i>Chat
                                            </a>
                                        <?php elseif ($project['status'] === 'in_progress'): ?>
                                            <a href="project-progress.php?id=<?php echo $project['id']; ?>" class="action-btn primary">
                                                <i class="fas fa-tasks me-1"></i>Progress
                                            </a>
                                            <a href="project-messages.php?id=<?php echo $project['id']; ?>" class="action-btn info">
                                                <i class="fas fa-comments me-1"></i>Chat
                                            </a>
                                            <?php if ($payment_status === 'paid'): ?>
                                                <a href="view-payment.php?id=<?php echo $project['id']; ?>" class="action-btn success">
                                                    <i class="fas fa-receipt me-1"></i>Invoice
                                                </a>
                                            <?php else: ?>
                                                <a href="contract-details.php?id=<?php echo $project['contract_id']; ?>" class="action-btn warning">
                                                    <i class="fas fa-credit-card me-1"></i>Bayar
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($project['status'] === 'completed'): ?>
                                            <!-- Progress Button - Show for both UMKM and Creative Worker -->
                                            <?php if ($project['contract_progress'] > 0 || $project['contract_id']): ?>
                                                <a href="project-progress.php?id=<?php echo $project['id']; ?>" class="action-btn info">
                                                    <i class="fas fa-chart-line me-1"></i>Lihat Progress
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="download-deliverables.php?id=<?php echo $project['id']; ?>" class="action-btn success" 
                                            onclick="return confirm('Download hasil akhir proyek ini?')">
                                                <i class="fas fa-download me-1"></i>Download Hasil
                                            </a>
                                            
                                            <!-- Review Button - Only if not reviewed yet -->
                                            <?php 
                                            // Check if review already exists
                                            $review_check_sql = "SELECT id FROM reviews WHERE contract_id = :contract_id AND reviewer_id = :user_id";
                                            $review_exists = false;
                                            if ($project['contract_id']) {
                                                try {
                                                    $stmt = $conn->prepare($review_check_sql);
                                                    $stmt->execute([':contract_id' => $project['contract_id'], ':user_id' => $_SESSION['user_id']]);
                                                    $review_exists = $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
                                                } catch (PDOException $e) {
                                                    error_log("Error checking review: " . $e->getMessage());
                                                }
                                            }
                                            
                                            if (!$review_exists && $project['contract_id']): ?>
                                                <a href="project-review.php?id=<?php echo $project['id']; ?>" class="action-btn warning">
                                                    <i class="fas fa-star me-1"></i>Berikan Review
                                                </a>
                                            <?php else: ?>
                                                <a href="project-review.php?id=<?php echo $project['id']; ?>" class="action-btn outline">
                                                    <i class="fas fa-star me-1"></i>Lihat Review
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Invoice Button -->
                                            <?php if ($payment_status === 'paid'): ?>
                                                <a href="view-payment.php?id=<?php echo $project['id']; ?>" class="action-btn outline">
                                                    <i class="fas fa-receipt me-1"></i>Invoice
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="view-project.php?id=<?php echo $project['id']; ?>" class="action-btn primary">
                                                <i class="fas fa-eye me-1"></i>Detail
                                            </a>
                                            <a href="#" class="action-btn outline">
                                                <i class="fas fa-redo me-1"></i>Restart
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <h3 class="empty-title">Belum Ada Proyek</h3>
                            <p class="empty-description">
                                Mulai buat proyek pertama Anda dan temukan creative worker terbaik untuk mewujudkan ide Anda.
                                Prosesnya mudah dan cepat!
                            </p>
                            <a href="create-project.php" class="btn btn-primary btn-lg px-5 rounded-pill">
                                <i class="fas fa-plus-circle me-2"></i>Buat Proyek Pertama
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Create Button (Mobile) -->
    <a href="create-project.php" class="floating-create-btn d-md-none">
        <i class="fas fa-plus"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class ProjectsManager {
            constructor() {
                this.projects = document.querySelectorAll('.project-item');
                this.filterButtons = document.querySelectorAll('.filter-btn');
                this.searchInput = document.querySelector('.search-input');
                this.searchBtn = document.querySelector('#searchBtn');
                this.init();
            }

            init() {
                this.bindEvents();
                this.setupAnimations();
                this.setupAutoRefresh();
                this.initializeSorting();
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

                if (this.searchBtn) {
                    this.searchBtn.addEventListener('click', () => this.handleSearch());
                }

                // Project card interactions
                this.projects.forEach(card => {
                    card.addEventListener('mouseenter', (e) => this.handleCardHover(e));
                    card.addEventListener('mouseleave', (e) => this.handleCardLeave(e));
                    card.addEventListener('click', (e) => this.handleCardClick(e));
                });

                // Keyboard shortcuts
                document.addEventListener('keydown', (e) => this.handleKeyboardShortcuts(e));
            }

            handleFilter(e) {
                const filter = e.currentTarget.dataset.filter;
                
                // Update active state
                this.filterButtons.forEach(btn => {
                    btn.classList.remove('active');
                });
                e.currentTarget.classList.add('active');

                // Handle payment status filters
                if (filter.includes('payment_')) {
                    this.filterByPaymentStatus(filter);
                } else {
                    this.animateFilterChange(filter);
                }
            }

            filterByPaymentStatus(filter) {
                const paymentStatus = filter.replace('payment_', '');
                
                this.projects.forEach((project, index) => {
                    const projectPaymentStatus = project.dataset.payment;
                    
                    if (paymentStatus === 'all' || projectPaymentStatus === paymentStatus) {
                        // Show with animation
                        setTimeout(() => {
                            project.style.display = 'block';
                            project.style.animation = 'fadeInUp 0.5s ease forwards';
                        }, index * 50);
                    } else {
                        // Hide with animation
                        project.style.animation = 'fadeOutDown 0.5s ease forwards';
                        setTimeout(() => {
                            project.style.display = 'none';
                        }, 500);
                    }
                });
            }

            animateFilterChange(filter) {
                this.projects.forEach((project, index) => {
                    const status = project.dataset.status;
                    
                    if (filter === 'all' || status === filter) {
                        // Show with animation
                        setTimeout(() => {
                            project.style.display = 'block';
                            project.style.animation = 'fadeInUp 0.5s ease forwards';
                        }, index * 50);
                    } else {
                        // Hide with animation
                        project.style.animation = 'fadeOutDown 0.5s ease forwards';
                        setTimeout(() => {
                            project.style.display = 'none';
                        }, 500);
                    }
                });
            }

            handleSearch(e = null) {
                const searchTerm = this.searchInput ? this.searchInput.value.toLowerCase() : '';
                
                this.projects.forEach((project, index) => {
                    const title = project.dataset.title || '';
                    const category = project.dataset.category || '';
                    const status = project.dataset.status || '';
                    
                    const matchesSearch = title.includes(searchTerm) || 
                                         category.includes(searchTerm) || 
                                         status.includes(searchTerm);
                    
                    const currentFilter = document.querySelector('.filter-btn.active')?.dataset.filter;
                    let matchesFilter = true;
                    
                    if (currentFilter && currentFilter !== 'all') {
                        if (currentFilter.includes('payment_')) {
                            const paymentStatus = currentFilter.replace('payment_', '');
                            matchesFilter = project.dataset.payment === paymentStatus;
                        } else {
                            matchesFilter = status === currentFilter;
                        }
                    }
                    
                    if (matchesSearch && matchesFilter) {
                        project.style.display = 'block';
                        project.style.animation = 'fadeInUp 0.5s ease forwards';
                    } else {
                        project.style.animation = 'fadeOutDown 0.5s ease forwards';
                        setTimeout(() => {
                            project.style.display = 'none';
                        }, 500);
                    }
                });
            }

            handleCardHover(e) {
                const card = e.currentTarget;
                const progressFill = card.querySelector('.progress-fill');
                
                if (progressFill) {
                    const targetWidth = progressFill.style.width;
                    progressFill.style.width = '0%';
                    setTimeout(() => {
                        progressFill.style.width = targetWidth;
                    }, 100);
                }

                // Add hover effects
                card.style.transform = 'translateY(-10px) scale(1.02)';
                
                // Pulsate effect for new proposals
                const newBadge = card.querySelector('.new-proposal-badge');
                if (newBadge) {
                    newBadge.style.animation = 'pulse 0.5s ease infinite';
                }
                
                // Highlight payment status
                const paymentBadge = card.querySelector('.payment-status');
                if (paymentBadge) {
                    paymentBadge.style.transform = 'scale(1.05)';
                }
            }

            handleCardLeave(e) {
                const card = e.currentTarget;
                card.style.transform = 'translateY(0) scale(1)';
                
                // Reset pulsate effect
                const newBadge = card.querySelector('.new-proposal-badge');
                if (newBadge) {
                    newBadge.style.animation = 'pulse 2s infinite';
                }
                
                // Reset payment badge
                const paymentBadge = card.querySelector('.payment-status');
                if (paymentBadge) {
                    paymentBadge.style.transform = 'scale(1)';
                }
            }

            handleCardClick(e) {
                // Prevent click if clicking on action buttons
                if (e.target.closest('.action-btn') || e.target.closest('a')) {
                    return;
                }
                
                const card = e.currentTarget;
                const projectId = card.querySelector('a[href*="id="]')?.href?.match(/id=(\d+)/)?.[1];
                
                if (projectId) {
                    this.showLoading();
                    setTimeout(() => {
                        window.location.href = `view-project.php?id=${projectId}`;
                    }, 300);
                }
            }

            setupAnimations() {
                // Animate stats numbers
                this.animateStats();
                
                // Stagger animation for project cards
                this.projects.forEach((card, index) => {
                    card.style.animationDelay = `${index * 0.1}s`;
                });
            }

            animateStats() {
                const statNumbers = document.querySelectorAll('.stat-number');
                
                statNumbers.forEach(stat => {
                    const target = parseInt(stat.textContent);
                    let current = 0;
                    const increment = target / 30;
                    const duration = 1500;
                    const stepTime = duration / (target / increment);
                    
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        stat.textContent = Math.round(current);
                    }, stepTime);
                });
            }

            setupAutoRefresh() {
                // Auto-check for new proposals every 30 seconds
                setInterval(() => {
                    this.checkNewProposals();
                }, 30000);
            }

            checkNewProposals() {
                // In a real application, you would make an AJAX request here
                // and update the badge counts accordingly
                console.log('🔍 Checking for new proposals...');
                
                // Simulate notification
                this.showNotification('Memeriksa proposal baru...', 'info');
            }

            initializeSorting() {
                // Add sorting dropdown in a real implementation
                console.log('Sorting initialized');
            }

            handleKeyboardShortcuts(e) {
                // Ctrl/Cmd + F to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    if (this.searchInput) {
                        this.searchInput.focus();
                    }
                }
                
                // Escape to clear search
                if (e.key === 'Escape' && this.searchInput) {
                    this.searchInput.value = '';
                    this.handleSearch();
                }
            }

            showLoading() {
                // Create loading overlay
                const overlay = document.createElement('div');
                overlay.className = 'loading-overlay';
                overlay.innerHTML = `
                    <div class="spinner"></div>
                    <p>Memuat...</p>
                `;
                
                overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                    color: white;
                `;
                
                const spinner = document.createElement('div');
                spinner.className = 'spinner';
                spinner.style.cssText = `
                    width: 50px;
                    height: 50px;
                    border: 5px solid rgba(255, 255, 255, 0.3);
                    border-top: 5px solid white;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin-bottom: 15px;
                `;
                
                overlay.prepend(spinner);
                document.body.appendChild(overlay);
                
                setTimeout(() => {
                    if (overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                }, 2000);
                
                // Add spin animation if not already in CSS
                if (!document.querySelector('style#spin-animation')) {
                    const style = document.createElement('style');
                    style.id = 'spin-animation';
                    style.textContent = `
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    `;
                    document.head.appendChild(style);
                }
            }

            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.innerHTML = `
                    <div class="notification-content">
                        <i class="fas fa-${this.getNotificationIcon(type)} me-2"></i>
                        <span>${message}</span>
                    </div>
                `;
                
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: white;
                    padding: 15px 20px;
                    border-radius: 12px;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                    transform: translateX(400px);
                    transition: transform 0.3s ease;
                    z-index: 10000;
                    border-left: 4px solid ${this.getNotificationColor(type)};
                `;
                
                document.body.appendChild(notification);
                
                // Animate in
                setTimeout(() => {
                    notification.style.transform = 'translateX(0)';
                }, 100);
                
                // Remove after 3 seconds
                setTimeout(() => {
                    notification.style.transform = 'translateX(400px)';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }, 3000);
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
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            const projectsManager = new ProjectsManager();
            
            // Add CSS animations if not already present
            if (!document.querySelector('style#animations')) {
                const style = document.createElement('style');
                style.id = 'animations';
                style.textContent = `
                    @keyframes fadeInUp {
                        from { opacity: 0; transform: translateY(20px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    
                    @keyframes fadeOutDown {
                        from { opacity: 1; transform: translateY(0); }
                        to { opacity: 0; transform: translateY(20px); }
                    }
                    
                    .project-item {
                        animation: fadeInUp 0.6s ease backwards;
                    }
                `;
                document.head.appendChild(style);
            }
        });

        // Export for global access
        window.ProjectsManager = ProjectsManager;
    </script>
</body>
</html>