<?php
class DashboardCharts {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function getAdminStats() {
        try {
            $query = "SELECT 
                (SELECT COUNT(*) FROM projects) as total_projects,
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid') as total_revenue,
                (SELECT COALESCE(AVG(rating), 0) FROM reviews) as avg_rating";
            
            $result = $this->db->query($query);
            if ($result) {
                return $result->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Error in getAdminStats: " . $e->getMessage());
        }
        
        return [
            'total_projects' => 0,
            'total_users' => 0,
            'total_revenue' => 0,
            'avg_rating' => 0
        ];
    }
    
    public function getProjectStatusStats() {
        try {
            $query = "SELECT status, COUNT(*) as count FROM projects GROUP BY status";
            $result = $this->db->query($query);
            return $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            error_log("Error in getProjectStatusStats: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUserDistribution() {
        try {
            $query = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
            $result = $this->db->query($query);
            return $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            error_log("Error in getUserDistribution: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUMKMStats($user_id) {
        try {
            $query = "SELECT 
                (SELECT COUNT(*) FROM projects WHERE umkm_user_id = :user_id) as total_projects,
                (SELECT COUNT(*) FROM users WHERE user_type = 'creative') as total_creative,
                (SELECT COUNT(*) FROM contracts WHERE umkm_user_id = :user_id AND status = 'active') as active_contracts,
                (SELECT COALESCE(SUM(amount), 0) FROM payments p 
                 JOIN contracts c ON p.contract_id = c.id 
                 WHERE c.umkm_user_id = :user_id AND p.status = 'paid') as total_spent";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: [
                'total_projects' => 0,
                'total_creative' => 0,
                'active_contracts' => 0,
                'total_spent' => 0
            ];
        } catch (Exception $e) {
            error_log("Error in getUMKMStats: " . $e->getMessage());
            return [
                'total_projects' => 0,
                'total_creative' => 0,
                'active_contracts' => 0,
                'total_spent' => 0
            ];
        }
    }
    
    public function getUMKMProjectStatus($user_id) {
        try {
            $query = "SELECT status, COUNT(*) as count FROM projects 
                      WHERE umkm_user_id = :user_id GROUP BY status";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUMKMProjectStatus: " . $e->getMessage());
            return [];
        }
    }
    
    public function getCWStats($user_id) {
        try {
            $query = "SELECT 
                (SELECT COUNT(*) FROM projects WHERE status = 'open') as available_projects,
                (SELECT COUNT(*) FROM portfolios WHERE creative_profile_id IN 
                    (SELECT id FROM creative_profiles WHERE user_id = :user_id)) as total_portfolios,
                (SELECT COUNT(*) FROM contracts WHERE creative_user_id = :user_id) as total_contracts,
                (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE reviewed_id = :user_id) as avg_rating";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: [
                'available_projects' => 0,
                'total_portfolios' => 0,
                'total_contracts' => 0,
                'avg_rating' => 0
            ];
        } catch (Exception $e) {
            error_log("Error in getCWStats: " . $e->getMessage());
            return [
                'available_projects' => 0,
                'total_portfolios' => 0,
                'total_contracts' => 0,
                'avg_rating' => 0
            ];
        }
    }
    
    public function getCWProjectCategories() {
        try {
            $query = "SELECT category, COUNT(*) as count FROM projects 
                      WHERE status = 'open' GROUP BY category";
            $result = $this->db->query($query);
            return $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            error_log("Error in getCWProjectCategories: " . $e->getMessage());
            return [];
        }
    }
    
    // === FUNGSI TAMBAHAN UNTUK DEMO DATA ===
    public function getPortfolioGrowth($user_id) {
        // Demo data untuk perkembangan portfolio
        return [
            ['month' => '2024-01', 'count' => 1],
            ['month' => '2024-02', 'count' => 2],
            ['month' => '2024-03', 'count' => 3],
            ['month' => '2024-04', 'count' => 5],
            ['month' => '2024-05', 'count' => 7],
            ['month' => '2024-06', 'count' => 10]
        ];
    }
    
    public function getUMKMBudgets($user_id) {
        // Demo data untuk budget proyek
        return [
            ['project_name' => 'Website Toko Online', 'budget' => 5000000],
            ['project_name' => 'Logo Brand', 'budget' => 1500000],
            ['project_name' => 'Social Media Campaign', 'budget' => 3000000],
            ['project_name' => 'Video Promosi', 'budget' => 7000000]
        ];
    }
}
?>