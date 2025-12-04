<?php 
include "check_login.php";

if ($_SESSION['user_type'] !== 'umkm') {
    header("Location: dashboard.php");
    exit();
}

require_once "config/Database.php";

try {
    $user_id = $_SESSION['user_id'];
    
    // Query untuk data profil UMKM
    $profile_query = "SELECT up.*, u.full_name, u.email, u.phone, u.avatar_url, u.background_url 
                      FROM umkm_profiles up 
                      JOIN users u ON up.user_id = u.id 
                      WHERE up.user_id = ?";
    $stmt = $conn->prepare($profile_query);
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        $insert_profile = "INSERT INTO umkm_profiles (user_id) VALUES (?)";
        $stmt = $conn->prepare($insert_profile);
        $stmt->execute([$user_id]);
        
        $stmt = $conn->prepare($profile_query);
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Query untuk produk/layanan (jika ada tabel products)
    $products_query = "SELECT * FROM products 
                       WHERE umkm_user_id = ? 
                       AND status = 'active'
                       ORDER BY created_at DESC 
                       LIMIT 6";
    $stmt = $conn->prepare($products_query);
    $stmt->execute([$user_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk statistik UMKM
    $stats_query = "SELECT 
                    COUNT(DISTINCT p.id) as total_projects,
                    COUNT(DISTINCT c.id) as total_contracts,
                    COUNT(DISTINCT pr.id) as total_proposals_received,
                    SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed_projects
                    FROM umkm_profiles up
                    LEFT JOIN projects p ON p.umkm_user_id = up.user_id
                    LEFT JOIN proposals pr ON pr.project_id = p.id
                    LEFT JOIN contracts c ON c.project_id = p.id
                    WHERE up.user_id = ?";
    $stmt = $conn->prepare($stats_query);
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Query untuk proyek aktif
    $active_projects_query = "SELECT COUNT(DISTINCT p.id) as active_projects
                             FROM projects p
                             WHERE p.umkm_user_id = ? 
                             AND p.status = 'in_progress'";
    $stmt = $conn->prepare($active_projects_query);
    $stmt->execute([$user_id]);
    $active_projects = $stmt->fetch(PDO::FETCH_ASSOC);

    // Query untuk review
    $reviews_query = "SELECT 
                     COUNT(DISTINCT r.id) as total_reviews,
                     COALESCE(AVG(r.rating), 0) as avg_rating
                     FROM reviews r
                     JOIN contracts c ON r.contract_id = c.id
                     JOIN projects p ON c.project_id = p.id
                     WHERE p.umkm_user_id = ? 
                     AND r.review_type = 'creative_to_umkm'";
    $stmt = $conn->prepare($reviews_query);
    $stmt->execute([$user_id]);
    $reviews = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil UMKM - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #10B981;
            --primary-dark: #059669;
            --primary-light: #34D399;
            --secondary-color: #F59E0B;
            --secondary-dark: #D97706;
            --accent-color: #8B5CF6;
            --accent-light: #A78BFA;
            --success-color: #10B981;
            --info-color: #0EA5E9;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --dark-color: #1F2937;
            --gray-color: #6B7280;
            --gray-light: #F3F4F6;
            --light-color: #F9FAFB;
            --white-color: #FFFFFF;
        }

        /* ===== BASE STYLES ===== */
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            color: var(--dark-color);
            line-height: 1.6;
            min-height: 100vh;
        }

        .umkm-profile-wrapper {
            min-height: 100vh;
        }

        /* ===== PROFILE HEADER ===== */
        .profile-header-wrapper {
            position: relative;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            padding: 3.5rem 0;
            margin-bottom: 2rem;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.2);
        }

        .profile-header-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.9) 0%, rgba(5, 150, 105, 0.9) 100%);
            z-index: 1;
        }

        .profile-header-content {
            position: relative;
            z-index: 2;
            color: var(--white-color);
        }

        .profile-avatar-container {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 0 auto;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .profile-avatar-container:hover {
            transform: scale(1.05) rotate(2deg);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        .profile-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--white-color);
        }

        .avatar-badge {
            position: absolute;
            bottom: -10px;
            right: -10px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .profile-name {
            font-size: 2.75rem;
            font-weight: 800;
            margin: 0;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
            background: linear-gradient(135deg, #FFFFFF 0%, #E5E7EB 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .profile-tagline {
            font-size: 1.35rem;
            opacity: 0.95;
            margin: 0;
            font-weight: 300;
        }

        .btn-edit-profile {
            background: rgba(255, 255, 255, 0.25);
            border: 2px solid rgba(255, 255, 255, 0.4);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-edit-profile:hover {
            background: rgba(255, 255, 255, 0.35);
            border-color: rgba(255, 255, 255, 0.6);
            transform: translateY(-3px) scale(1.05);
            color: white;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .rating-display {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 20px;
            border-radius: 30px;
            backdrop-filter: blur(10px);
        }

        .stars {
            color: #FFD700;
            font-size: 1.2rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .rating-value {
            font-weight: 700;
            font-size: 1.3rem;
        }

        .reviews-count {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .meta-items {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            opacity: 0.95;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 12px;
            backdrop-filter: blur(5px);
        }

        .profile-social-links {
            display: flex;
            gap: 15px;
            margin-top: 2rem;
        }

        .social-link {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            font-size: 1.2rem;
            backdrop-filter: blur(5px);
        }

        .social-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-5px) rotate(5deg);
            color: white;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        /* ===== STAT CARDS ===== */
        .stat-card {
            background: var(--white-color);
            border-radius: 20px;
            padding: 1.75rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            height: 100%;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border-color: var(--primary-light);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .stat-card-2 .stat-icon {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #EC4899 100%);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
        }

        .stat-card-3 .stat-icon {
            background: linear-gradient(135deg, var(--accent-color) 0%, #0EA5E9 100%);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.3);
        }

        .stat-card-4 .stat-icon {
            background: linear-gradient(135deg, var(--info-color) 0%, #10B981 100%);
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.3);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
            color: var(--dark-color);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .stat-card-2 .stat-value {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #EC4899 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .stat-card-3 .stat-value {
            background: linear-gradient(135deg, var(--accent-color) 0%, #0EA5E9 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .stat-card-4 .stat-value {
            background: linear-gradient(135deg, var(--info-color) 0%, #10B981 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .stat-label {
            color: var(--gray-color);
            margin: 0;
            font-size: 0.95rem;
            font-weight: 500;
        }

        /* ===== PROFILE SECTIONS ===== */
        .profile-section {
            background: var(--white-color);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
        }

        .profile-section:hover {
            box-shadow: 0 12px 35px rgba(0,0,0,0.12);
            border-color: var(--primary-light);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-light);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title::before {
            content: '';
            width: 8px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            border-radius: 4px;
        }

        .section-edit-btn {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            border: 2px solid var(--primary-light);
            background: var(--white-color);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1.1rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
        }

        .section-edit-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: rotate(15deg) scale(1.1);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.25);
        }

        /* ===== BUSINESS INFO ===== */
        .business-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-card {
            background: linear-gradient(135deg, var(--light-color) 0%, var(--white-color) 100%);
            border-radius: 16px;
            padding: 1.5rem;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-left-color: var(--accent-color);
        }

        .info-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .info-card h6 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        .info-card p {
            color: var(--gray-color);
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* ===== PRODUCTS SECTION ===== */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.75rem;
        }

        .product-card {
            background: var(--white-color);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
        }

        .product-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            border-color: var(--primary-light);
        }

        .product-image {
            position: relative;
            height: 220px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .product-card:hover .product-image img {
            transform: scale(1.1);
        }

        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .product-content {
            padding: 1.5rem;
        }

        .product-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--dark-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-desc {
            color: var(--gray-color);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        /* ===== EMPTY STATES ===== */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-color);
            background: linear-gradient(135deg, var(--light-color) 0%, var(--white-color) 100%);
            border-radius: 20px;
            border: 2px dashed var(--gray-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .empty-state h5 {
            color: var(--dark-color);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .empty-state p {
            margin-bottom: 2rem;
            font-size: 1rem;
        }

        /* ===== CONTACT INFO ===== */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.25rem;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--light-color) 0%, var(--white-color) 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
        }

        .contact-item:hover {
            transform: translateX(8px);
            border-color: var(--primary-light);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .contact-details {
            flex: 1;
        }

        .contact-label {
            font-size: 0.85rem;
            color: var(--gray-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .contact-value {
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 600;
            display: block;
            font-size: 1.1rem;
        }

        .contact-value:hover {
            color: var(--primary-color);
        }

        /* ===== BUSINESS TYPE BADGE ===== */
        .business-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-light) 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.2);
        }

        /* ===== QUICK ACTIONS ===== */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .quick-action-card {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.75rem;
            background: linear-gradient(135deg, var(--light-color) 0%, var(--white-color) 100%);
            border-radius: 18px;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
        }

        .quick-action-card:hover {
            transform: translateY(-6px) scale(1.02);
            border-color: var(--primary-light);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
            text-decoration: none;
        }

        .action-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.2);
        }

        .quick-action-card:nth-child(2) .action-icon {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #EC4899 100%);
        }

        .quick-action-card:nth-child(3) .action-icon {
            background: linear-gradient(135deg, var(--accent-color) 0%, #0EA5E9 100%);
        }

        .quick-action-card:nth-child(4) .action-icon {
            background: linear-gradient(135deg, var(--info-color) 0%, #10B981 100%);
        }

        .action-content h6 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        .action-content p {
            color: var(--gray-color);
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-section {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }

        /* ===== DECORATIVE ELEMENTS ===== */
        .decorative-circle {
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
            z-index: 0;
        }

        .circle-1 {
            top: -150px;
            right: -150px;
        }

        .circle-2 {
            bottom: -150px;
            left: -150px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .profile-name {
                font-size: 2.25rem;
            }
            
            .profile-avatar-container {
                width: 160px;
                height: 160px;
            }
        }

        @media (max-width: 992px) {
            .profile-header-wrapper {
                padding: 2.5rem 0;
            }
            
            .profile-name {
                font-size: 2rem;
            }
            
            .profile-avatar-container {
                width: 140px;
                height: 140px;
                margin-bottom: 1.5rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .profile-header-wrapper {
                padding: 2rem 0;
            }
            
            .profile-name {
                font-size: 1.75rem;
            }
            
            .profile-tagline {
                font-size: 1.1rem;
            }
            
            .meta-items {
                flex-direction: column;
                gap: 1rem;
            }
            
            .products-grid,
            .business-info-grid,
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }
            
            .stat-icon {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 576px) {
            .profile-name {
                font-size: 1.5rem;
            }
            
            .profile-section {
                padding: 1.5rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .btn-edit-profile {
                width: 100%;
                text-align: center;
            }
        }

        /* ===== CUSTOM SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-color);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #7C3AED 100%);
        }
    </style>
</head>
<body>
    <?php include "dashboard-sidebar.php"; ?>
    
    <div class="umkm-profile-wrapper">
        <!-- Header Profil UMKM -->
        <div class="profile-header-wrapper" style="<?php echo !empty($profile['background_url']) ? 'background-image: url(\'' . htmlspecialchars($profile['background_url']) . '\'); background-size: cover; background-position: center;' : ''; ?>">
            <div class="decorative-circle circle-1"></div>
            <div class="decorative-circle circle-2"></div>
            <div class="profile-header-overlay"></div>
            <div class="container">
                <div class="profile-header-content">
                    <div class="row align-items-center">
                        <!-- Logo/Avatar UMKM -->
                        <div class="col-xl-2 col-lg-3 col-md-4">
                            <div class="profile-avatar-container animate__animated animate__fadeIn float-animation">
                                <?php if (!empty($profile['business_logo_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($profile['business_logo_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($profile['business_name']); ?>" 
                                         class="profile-avatar-img">
                                <?php elseif (!empty($profile['avatar_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($profile['avatar_url']); ?>" 
                                         alt="Avatar" class="profile-avatar-img">
                                <?php else: ?>
                                    <div class="profile-avatar-placeholder">
                                        <i class="fas fa-store"></i>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($profile['business_type'])): ?>
                                    <div class="avatar-badge">
                                        <?php 
                                        $business_types = [
                                            'food' => 'Makanan',
                                            'fashion' => 'Fashion',
                                            'craft' => 'Kerajinan',
                                            'service' => 'Jasa',
                                            'retail' => 'Retail',
                                            'other' => 'Lainnya'
                                        ];
                                        echo $business_types[$profile['business_type']] ?? 'UMKM';
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Informasi UMKM -->
                        <div class="col-xl-10 col-lg-9 col-md-8">
                            <div class="profile-info animate__animated animate__fadeInUp">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h1 class="profile-name mb-2"><?php echo htmlspecialchars($profile['business_name']); ?></h1>
                                        <p class="profile-tagline mb-3"><?php echo htmlspecialchars($profile['full_name'] ?? 'Pemilik UMKM'); ?></p>
                                    </div>
                                    <div class="d-flex gap-3">
                                        <a href="edit-umkm-profile.php" class="btn btn-edit-profile">
                                            <i class="fas fa-edit me-2"></i>Edit Profil
                                        </a>
                                        <a href="create-project.php" class="btn btn-edit-profile" style="background: rgba(255, 255, 255, 0.15);">
                                            <i class="fas fa-plus-circle me-2"></i>Buat Proyek
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Rating dan Stats -->
                                <div class="profile-meta mb-4">
                                    <div class="rating-display mb-3">
                                        <div class="stars">
                                            <?php
                                            $rating = $reviews['avg_rating'];
                                            $fullStars = floor($rating);
                                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                            $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
                                            
                                            for ($i = 0; $i < $fullStars; $i++) {
                                                echo '<i class="fas fa-star"></i>';
                                            }
                                            if ($hasHalfStar) {
                                                echo '<i class="fas fa-star-half-alt"></i>';
                                            }
                                            for ($i = 0; $i < $emptyStars; $i++) {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                            ?>
                                        </div>
                                        <span class="rating-value"><?php echo number_format($rating, 1); ?></span>
                                        <span class="reviews-count">(<?php echo $reviews['total_reviews']; ?> ulasan)</span>
                                    </div>
                                    
                                    <div class="meta-items">
                                        <?php if (!empty($profile['location'])): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($profile['location']); ?>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($profile['established_year'])): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-calendar-alt"></i>
                                            Berdiri sejak <?php echo htmlspecialchars($profile['established_year']); ?>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($profile['business_size'])): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-chart-bar"></i>
                                            <?php 
                                            $sizes = [
                                                'micro' => 'Mikro',
                                                'small' => 'Kecil',
                                                'medium' => 'Menengah'
                                            ];
                                            echo $sizes[$profile['business_size']] ?? $profile['business_size'];
                                            ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Social Links -->
                                <div class="profile-social-links">
                                    <?php if (!empty($profile['business_website'])): ?>
                                        <a href="<?php echo htmlspecialchars($profile['business_website']); ?>" 
                                           target="_blank" class="social-link" data-bs-toggle="tooltip" title="Website">
                                            <i class="fas fa-globe"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($profile['email']); ?>" 
                                           class="social-link" data-bs-toggle="tooltip" title="Email">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($profile['phone']); ?>" 
                                           class="social-link" data-bs-toggle="tooltip" title="Telepon">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="#" class="social-link" data-bs-toggle="tooltip" title="Share Profil" onclick="shareProfile()">
                                        <i class="fas fa-share-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="container mt-5">
            <div class="row g-4">
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-1 animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo $stats['total_projects'] ?? 0; ?></h3>
                            <p class="stat-label">Total Proyek</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-2 animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo $stats['completed_projects'] ?? 0; ?></h3>
                            <p class="stat-label">Proyek Selesai</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-3 animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo $stats['total_proposals_received'] ?? 0; ?></h3>
                            <p class="stat-label">Proposal Diterima</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-6">
                    <div class="stat-card stat-card-4 animate-section">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-value"><?php echo $active_projects['active_projects'] ?? 0; ?></h3>
                            <p class="stat-label">Proyek Aktif</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Konten Utama -->
        <div class="container mt-5">
            <div class="row g-4">
                <!-- Sidebar Kiri -->
                <div class="col-xl-4 col-lg-5">
                    <!-- Tentang Bisnis -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                Tentang Bisnis
                            </h3>
                            <button class="section-edit-btn" onclick="editProfile()">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($profile['business_description'])): ?>
                                <p class="business-description"><?php echo nl2br(htmlspecialchars($profile['business_description'])); ?></p>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    <p>Belum ada deskripsi tentang bisnis Anda.</p>
                                    <button class="btn btn-outline-primary" onclick="editProfile()">
                                        Tambahkan Deskripsi
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Informasi Kontak -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                Kontak & Lokasi
                            </h3>
                        </div>
                        <div class="section-content">
                            <div class="contact-info">
                                <?php if (!empty($profile['email'])): ?>
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">Email Bisnis</span>
                                        <a href="mailto:<?php echo htmlspecialchars($profile['email']); ?>" class="contact-value">
                                            <?php echo htmlspecialchars($profile['email']); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['phone'])): ?>
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">Telepon</span>
                                        <a href="tel:<?php echo htmlspecialchars($profile['phone']); ?>" class="contact-value">
                                            <?php echo htmlspecialchars($profile['phone']); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['address'])): ?>
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">Alamat</span>
                                        <span class="contact-value">
                                            <?php echo htmlspecialchars($profile['address']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['tax_id'])): ?>
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-id-card"></i>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label">NPWP</span>
                                        <span class="contact-value">
                                            <?php echo htmlspecialchars($profile['tax_id']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Bisnis -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                Detail Bisnis
                            </h3>
                        </div>
                        <div class="section-content">
                            <div class="business-info-grid">
                                <?php if (!empty($profile['business_type'])): ?>
                                <div class="info-card">
                                    <div class="info-icon">
                                        <i class="fas fa-tag"></i>
                                    </div>
                                    <div>
                                        <h6>Jenis Bisnis</h6>
                                        <p>
                                            <?php 
                                            $business_types = [
                                                'food' => 'Makanan & Minuman',
                                                'fashion' => 'Fashion & Pakaian',
                                                'craft' => 'Kerajinan Tangan',
                                                'service' => 'Jasa & Layanan',
                                                'retail' => 'Retail & Eceran',
                                                'other' => 'Lainnya'
                                            ];
                                            echo $business_types[$profile['business_type']] ?? 'UMKM';
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['business_size'])): ?>
                                <div class="info-card">
                                    <div class="info-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div>
                                        <h6>Skala Bisnis</h6>
                                        <p>
                                            <?php 
                                            $sizes = [
                                                'micro' => 'Mikro (1-4 karyawan)',
                                                'small' => 'Kecil (5-19 karyawan)',
                                                'medium' => 'Menengah (20-99 karyawan)'
                                            ];
                                            echo $sizes[$profile['business_size']] ?? 'UMKM';
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($profile['established_year'])): ?>
                                <div class="info-card">
                                    <div class="info-icon">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <div>
                                        <h6>Tahun Berdiri</h6>
                                        <p><?php echo htmlspecialchars($profile['established_year']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-card">
                                    <div class="info-icon">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div>
                                        <h6>Rating</h6>
                                        <p><?php echo number_format($reviews['avg_rating'], 1); ?> (<?php echo $reviews['total_reviews']; ?> ulasan)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Konten Utama -->
                <div class="col-xl-8 col-lg-7">
                    <!-- Produk/Layanan -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                Produk & Layanan
                            </h3>
                            <div class="d-flex gap-2">
                                <a href="add-product.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus me-1"></i>Tambah
                                </a>
                                <a href="products.php" class="btn btn-sm btn-primary">
                                    Lihat Semua
                                </a>
                            </div>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($products)): ?>
                                <div class="products-grid">
                                    <?php foreach ($products as $product): ?>
                                        <div class="product-card">
                                            <div class="product-image">
                                                <?php if (!empty($product['image_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         class="img-fluid">
                                                <?php else: ?>
                                                    <div class="product-image-placeholder">
                                                        <i class="fas fa-box"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($product['category'])): ?>
                                                    <div class="product-badge">
                                                        <?php echo htmlspecialchars($product['category']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-content">
                                                <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                                <p class="product-desc">
                                                    <?php echo strlen($product['description'] ?? '') > 120 ? 
                                                          substr($product['description'], 0, 120) . '...' : 
                                                          ($product['description'] ?? 'Tidak ada deskripsi'); ?>
                                                </p>
                                                <?php if (!empty($product['price'])): ?>
                                                    <div class="product-price">
                                                        Rp <?php echo number_format($product['price'], 0, ',', '.'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center mt-3">
                                                    <?php if (!empty($product['status'])): ?>
                                                        <span class="badge <?php echo $product['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo $product['status'] === 'active' ? 'Tersedia' : 'Tidak Tersedia'; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <a href="product-detail.php?id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        Detail
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-box-open"></i>
                                    <h5>Belum ada produk</h5>
                                    <p>Tampilkan produk atau layanan Anda untuk menarik perhatian kreator.</p>
                                    <a href="add-product.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Tambah Produk Pertama
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Proyek Terbaru -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                Proyek Terbaru
                            </h3>
                            <a href="projects.php" class="btn btn-sm btn-outline-primary">
                                Lihat Semua
                            </a>
                        </div>
                        <div class="section-content">
                            <?php 
                            // Query untuk proyek terbaru
                            $recent_projects_query = "SELECT * FROM projects 
                                                     WHERE umkm_user_id = ? 
                                                     ORDER BY created_at DESC 
                                                     LIMIT 3";
                            $stmt = $conn->prepare($recent_projects_query);
                            $stmt->execute([$user_id]);
                            $recent_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <?php if (!empty($recent_projects)): ?>
                                <div class="row">
                                    <?php foreach ($recent_projects as $project): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="info-card h-100">
                                                <div class="info-icon">
                                                    <i class="fas fa-project-diagram"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-2"><?php echo htmlspecialchars($project['title']); ?></h6>
                                                    <p class="text-muted mb-2" style="font-size: 0.85rem;">
                                                        <?php echo strlen($project['description']) > 80 ? 
                                                              substr($project['description'], 0, 80) . '...' : 
                                                              $project['description']; ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="badge bg-primary">
                                                            <?php 
                                                            $statuses = [
                                                                'draft' => 'Draft',
                                                                'open' => 'Terbuka',
                                                                'in_progress' => 'Berjalan',
                                                                'completed' => 'Selesai',
                                                                'cancelled' => 'Dibatalkan'
                                                            ];
                                                            echo $statuses[$project['status']] ?? $project['status'];
                                                            ?>
                                                        </span>
                                                        <small class="text-muted">
                                                            <?php echo date('d M Y', strtotime($project['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 2rem;">
                                    <i class="fas fa-tasks"></i>
                                    <h5>Belum ada proyek</h5>
                                    <p>Mulai buat proyek pertama Anda untuk bekerja sama dengan kreator.</p>
                                    <a href="create-project.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Buat Proyek Pertama
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="profile-section animate-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                Aksi Cepat
                            </h3>
                        </div>
                        <div class="section-content">
                            <div class="quick-actions-grid">
                                <a href="create-project.php" class="quick-action-card">
                                    <div class="action-icon">
                                        <i class="fas fa-plus-circle"></i>
                                    </div>
                                    <div class="action-content">
                                        <h6>Buat Proyek Baru</h6>
                                        <p class="text-muted mb-0">Posting kebutuhan kreatif Anda</p>
                                    </div>
                                </a>
                                
                                <a href="edit-umkm-profile.php" class="quick-action-card">
                                    <div class="action-icon">
                                        <i class="fas fa-user-edit"></i>
                                    </div>
                                    <div class="action-content">
                                        <h6>Edit Profil UMKM</h6>
                                        <p class="text-muted mb-0">Perbarui informasi bisnis</p>
                                    </div>
                                </a>
                                
                                <a href="messages.php" class="quick-action-card">
                                    <div class="action-icon">
                                        <i class="fas fa-comments"></i>
                                    </div>
                                    <div class="action-content">
                                        <h6>Pesan & Diskusi</h6>
                                        <p class="text-muted mb-0">Lihat pesan dari kreator</p>
                                    </div>
                                </a>
                                
                                <a href="contracts.php" class="quick-action-card">
                                    <div class="action-icon">
                                        <i class="fas fa-file-contract"></i>
                                    </div>
                                    <div class="action-content">
                                        <h6>Kontrak & Kerjasama</h6>
                                        <p class="text-muted mb-0">Kelola kontrak dengan kreator</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk edit profil
        function editProfile() {
            window.location.href = 'edit-umkm-profile.php';
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Animate stat counters
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(value => {
                const target = parseInt(value.textContent) || 0;
                let current = 0;
                const increment = target / 30;
                
                const updateCounter = () => {
                    current += increment;
                    if (current < target) {
                        value.textContent = Math.floor(current);
                        setTimeout(updateCounter, 50);
                    } else {
                        value.textContent = target;
                    }
                };
                
                // Start animation when element is in viewport
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            updateCounter();
                            observer.unobserve(entry.target);
                        }
                    });
                });
                
                observer.observe(value);
            });

            // Add hover effects to cards
            const cards = document.querySelectorAll('.product-card, .stat-card, .info-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });

        // Share profile function
        function shareProfile() {
            if (navigator.share) {
                navigator.share({
                    title: 'Profil UMKM - <?php echo htmlspecialchars($profile['business_name']); ?>',
                    text: 'Lihat profil UMKM <?php echo htmlspecialchars($profile['business_name']); ?> di Konekin',
                    url: window.location.href,
                })
                .then(() => console.log('Berhasil membagikan'))
                .catch((error) => console.log('Error sharing:', error));
            } else {
                // Fallback for browsers that don't support Web Share API
                const text = "Lihat profil UMKM <?php echo htmlspecialchars($profile['business_name']); ?> di Konekin: " + window.location.href;
                navigator.clipboard.writeText(text).then(() => {
                    alert('Link profil telah disalin ke clipboard!');
                });
            }
        }

        // Print profile function
        function printProfile() {
            window.print();
        }

        // Export profile as PDF (placeholder)
        function exportProfilePDF() {
            alert('Fitur export PDF akan segera hadir!');
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>