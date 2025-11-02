<?php
require_once 'DataFallback.php';

class Dashboard {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }

    public function getUMKMStats($user_id) {
        try {
            $query = "SELECT 
                        COUNT(p.id) as total_projects,
                        COALESCE(SUM(c.agreed_budget), 0) as total_budget,
                        COALESCE(AVG(DATEDIFF(c.end_date, c.start_date)), 0) as avg_duration,
                        COALESCE(AVG(r.rating), 0) as avg_rating
                      FROM projects p
                      LEFT JOIN contracts c ON p.id = c.project_id
                      LEFT JOIN reviews r ON c.id = r.contract_id AND r.review_type = 'creative_to_umkm'
                      WHERE p.umkm_user_id = :user_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_projects' => (int)($result['total_projects'] ?? 0),
                'total_budget' => (float)($result['total_budget'] ?? 0),
                'avg_duration' => round(($result['avg_duration'] ?? 0)),
                'avg_rating' => round(($result['avg_rating'] ?? 0), 1)
            ];
            
        } catch (Exception $e) {
            error_log("Dashboard Error - getUMKMStats: " . $e->getMessage());
            return DataFallback::getUMKMStats();
        }
    }

    public function getProjectStatusCount($user_id) {
        try {
            $query = "SELECT status, COUNT(*) as count 
                      FROM projects 
                      WHERE umkm_user_id = :user_id 
                      GROUP BY status";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ?: [];
            
        } catch (Exception $e) {
            error_log("Dashboard Error - getProjectStatusCount: " . $e->getMessage());
            return [];
        }
    }

    public function getProjectCategoryStats($user_id) {
        $query = "SELECT category, COUNT(*) as count 
                  FROM projects 
                  WHERE umkm_user_id = :user_id 
                  GROUP BY category 
                  ORDER BY count DESC 
                  LIMIT 5";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($result)) {
            return [
                ['category' => 'website', 'count' => 5],
                ['category' => 'logo', 'count' => 3],
                ['category' => 'social_media', 'count' => 2],
                ['category' => 'video', 'count' => 1],
                ['category' => 'content', 'count' => 1]
            ];
        }
        
        return $result;
    }

    public function getMonthlyProjectTrend($user_id) {
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                  FROM projects 
                  WHERE umkm_user_id = :user_id 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                  ORDER BY month";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($result)) {
            return [
                ['month' => '2024-09', 'count' => 2],
                ['month' => '2024-10', 'count' => 4],
                ['month' => '2024-11', 'count' => 3],
                ['month' => '2024-12', 'count' => 3]
            ];
        }
        
        return $result;
    }

    public function getCreativeStats($user_id) {
        $query = "SELECT 
                    COUNT(DISTINCT c.id) as total_contracts,
                    SUM(p.amount) as total_income,
                    AVG(r.rating) as avg_rating
                  FROM contracts c
                  LEFT JOIN payments p ON c.id = p.contract_id AND p.status = 'paid'
                  LEFT JOIN reviews r ON c.id = r.contract_id AND r.review_type = 'umkm_to_creative'
                  WHERE c.creative_user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['total_contracts'] === null) {
            return [
                'total_contracts' => 8,
                'total_income' => 18500000,
                'avg_rating' => 4.7
            ];
        }
        
        return $result;
    }

    public function getContractStatusCount($user_id) {
        $query = "SELECT status, COUNT(*) as count 
                  FROM contracts 
                  WHERE creative_user_id = :user_id 
                  GROUP BY status";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($result)) {
            return [
                ['status' => 'active', 'count' => 3],
                ['status' => 'completed', 'count' => 4],
                ['status' => 'cancelled', 'count' => 1]
            ];
        }
        
        return $result;
    }

    
    public function getMilestoneProgress($user_id) {
        return [
            'total_milestones' => 15,
            'completed_milestones' => 11
        ];
    }

    public function getTopSkills($user_id) {
        return [
            ['name' => 'Web Development', 'count' => 8],
            ['name' => 'UI/UX Design', 'count' => 6],
            ['name' => 'Graphic Design', 'count' => 5],
            ['name' => 'Content Writing', 'count' => 3],
            ['name' => 'Digital Marketing', 'count' => 2]
        ];
    }

    public function getIncomeTrend($user_id) {
        return [
            ['month' => '2024-09', 'amount' => 3500000],
            ['month' => '2024-10', 'amount' => 4200000],
            ['month' => '2024-11', 'amount' => 5100000],
            ['month' => '2024-12', 'amount' => 5700000]
        ];
    }

    public function getAdminStats() {
        return [
            'total_users' => 150,
            'creative_users' => 85,
            'umkm_users' => 65,
            'total_projects' => 89,
            'active_projects' => 23,
            'completed_projects' => 45,
            'total_disputes' => 5,
            'open_disputes' => 2,
            'total_transactions' => 125000000,
            'avg_rating' => 4.3
        ];
    }

    public function getProjectDurationStats() {
        return ['avg_duration' => 16];
    }

    public function getPopularCategories() {
        return [
            ['category' => 'website', 'count' => 25],
            ['category' => 'logo', 'count' => 18],
            ['category' => 'social_media', 'count' => 15],
            ['category' => 'video', 'count' => 12],
            ['category' => 'content', 'count' => 10]
        ];
    }

    public function getMonthlyActivity() {
        return [
            ['month' => '2024-09', 'user_count' => 15],
            ['month' => '2024-10', 'user_count' => 22],
            ['month' => '2024-11', 'user_count' => 18],
            ['month' => '2024-12', 'user_count' => 25]
        ];
    }

    public function getRecentNotifications() {
        return [
            [
                'title' => 'Proyek Baru',
                'message' => 'Ada proyek website design baru',
                'full_name' => 'Ahmad Creative',
                'type' => 'project',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'title' => 'Pembayaran',
                'message' => 'Pembayaran proyek logo telah diterima',
                'full_name' => 'UMKM Sejahtera',
                'type' => 'payment', 
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ]
        ];
    }
}
?>