<?php
class Review {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function getCreativeReviews($creativeId) {
        return array_filter($this->dummyData['reviews'], function($review) use ($creativeId) {
            return $review['creative_id'] == $creativeId;
        });
    }
    
    public function getCreativeStats($creativeId) {
        return $this->dummyData['stats'][$creativeId] ?? null;
    }
    
    public function getUser($userId) {
        return $this->dummyData['users'][$userId] ?? null;
    }
    
    public function calculateRatingAverage($creativeId) {
        $reviews = $this->getCreativeReviews($creativeId);
        
        if (empty($reviews)) {
            return ['overall' => 0, 'quality' => 0, 'communication' => 0, 'collaboration' => 0, 'timeliness' => 0, 'count' => 0];
        }
        
        $totals = ['overall' => 0, 'quality' => 0, 'communication' => 0, 'collaboration' => 0, 'timeliness' => 0];
        
        foreach ($reviews as $review) {
            $totals['overall'] += $review['rating'];
            $totals['quality'] += $review['quality'];
            $totals['communication'] += $review['communication'];
            $totals['collaboration'] += $review['collaboration'];
            $totals['timeliness'] += $review['timeliness'];
        }
        
        $count = count($reviews);
        return [
            'overall' => round($totals['overall'] / $count, 1),
            'quality' => round($totals['quality'] / $count, 1),
            'communication' => round($totals['communication'] / $count, 1),
            'collaboration' => round($totals['collaboration'] / $count, 1),
            'timeliness' => round($totals['timeliness'] / $count, 1),
            'count' => $count
        ];
    }
    
    public function getPaginatedReviews($creativeId, $page = 1, $limit = 5, $filters = []) {
        $offset = ($page - 1) * $limit;
        $whereConditions = ["creative_id = ?"];
        $params = [$creativeId];
        
        if (!empty($filters['rating'])) {
            $whereConditions[] = "rating = ?";
            $params[] = $filters['rating'];
        }
        
        $whereClause = implode(" AND ", $whereConditions);
        
        $sql = "SELECT SQL_CALC_FOUND_ROWS r.*, 
                       u.full_name, u.avatar_url,
                       up.business_name,
                       DATE_FORMAT(r.created_at, '%d %M %Y') as formatted_date
                FROM reviews r
                JOIN users u ON r.umkm_id = u.id
                LEFT JOIN umkm_profiles up ON u.id = up.user_id
                WHERE $whereClause
                ORDER BY r.created_at DESC
                LIMIT $offset, $limit";
        
        $reviews = $this->db->query($sql, $params);
        $total = $this->db->query("SELECT FOUND_ROWS() as total")[0]['total'];
        
        return [
            'reviews' => $reviews,
            'total_pages' => ceil($total / $limit),
            'current_page' => $page,
            'total_reviews' => $total
        ];
    }
    
    public function getRatingBreakdown($creativeId) {
        $sql = "SELECT 
                rating,
                COUNT(*) as count,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM reviews WHERE creative_id = ?)), 1) as percentage
                FROM reviews 
                WHERE creative_id = ?
                GROUP BY rating
                ORDER BY rating DESC";
        
        return $this->db->query($sql, [$creativeId, $creativeId]);
    }
    
    public function submitEnhancedReview($reviewData) {
        $sql = "INSERT INTO reviews 
                (contract_id, creative_id, umkm_id, rating, review_text, private_feedback, 
                 quality, communication, collaboration, timeliness, professionalism, 
                 would_recommend, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        return $this->db->execute($sql, [
            $reviewData['contract_id'],
            $reviewData['creative_id'],
            $reviewData['umkm_id'],
            $reviewData['overall_rating'],
            $reviewData['public_review'],
            $reviewData['private_feedback'],
            $reviewData['category_ratings']['quality'],
            $reviewData['category_ratings']['communication'],
            $reviewData['category_ratings']['collaboration'],
            $reviewData['category_ratings']['timeliness'],
            $reviewData['category_ratings']['professionalism'],
            $reviewData['would_recommend']
        ]);
    }
    
    public function getReviewByContract($contractId) {
        $sql = "SELECT * FROM reviews WHERE contract_id = ?";
        $result = $this->db->query($sql, [$contractId]);
        return $result[0] ?? null;
    }
}
?>