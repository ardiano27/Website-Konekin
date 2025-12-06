<?php
require_once 'config/Database.php';
class NotificationManager {
    private $db;
    
    public function __construct() {
        $database = new DatabaseConnection();
        $this->db = $database->getConnection();
    }
    
    public function getDbConnection() {
    return $this->db;
}
    /**
     * Create notification with icon support
     */
    public function createNotification($user_id, $title, $message, $notification_type = 'system', 
                                     $related_entity_type = null, $related_entity_id = null) {
        try {
            $sql = "INSERT INTO notifications (user_id, title, message, notification_type, 
                    related_entity_type, related_entity_id, is_read) 
                    VALUES (:user_id, :title, :message, :notification_type, 
                    :related_entity_type, :related_entity_id, 0)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':user_id' => $user_id,
                ':title' => $title,
                ':message' => $message,
                ':notification_type' => $notification_type,
                ':related_entity_type' => $related_entity_type,
                ':related_entity_id' => $related_entity_id
            ]);
            
            if ($result) {
                error_log("✅ Notification created for user $user_id: $title");
                return $this->db->lastInsertId();
            } else {
                error_log("❌ Failed to create notification for user: $user_id");
                return false;
            }
        } catch (PDOException $e) {
            error_log("❌ Notification error: " . $e->getMessage());
            return false;
        }
    }
   
    public function getUserNotifications($user_id, $limit = 10, $unread_only = false) {
        try {
            $sql = "SELECT n.*, 
                    CASE 
                        WHEN n.notification_type = 'project' THEN '🚀'
                        WHEN n.notification_type = 'proposal' THEN '📩'
                        WHEN n.notification_type = 'message' THEN '💬'
                        WHEN n.notification_type = 'payment' THEN '💰'
                        WHEN n.notification_type = 'contract' THEN '📄'
                        ELSE '🔔'
                    END as icon
                    FROM notifications n 
                    WHERE n.user_id = :user_id";
            
            if ($unread_only) {
                $sql .= " AND n.is_read = 0";
            }
            
            $sql .= " ORDER BY n.created_at DESC, n.is_read ASC";
            
            if ($limit > 0) {
                $sql .= " LIMIT :limit";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($limit > 0) {
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $notifications;
        } catch (PDOException $e) {
            error_log("❌ Get notifications error: " . $e->getMessage());
            return [];
        }
    }
    
    public function markAsRead($notification_id, $user_id) {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id' => $notification_id,
                ':user_id' => $user_id
            ]);
        } catch (PDOException $e) {
            error_log("❌ Mark as read error: " . $e->getMessage());
            return false;
        }
    }
    
    public function markAllAsRead($user_id) {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([':user_id' => $user_id]);
            
            error_log("✅ Marked all as read for user: $user_id");
            return $result;
        } catch (PDOException $e) {
            error_log("❌ Mark all as read error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount($user_id) {
        try {
            $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (PDOException $e) {
            error_log("❌ Unread count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * =========== NOTIFICATION FACTORY METHODS ===========
     */
    
    /**
     * Notify UMKM when new proposal submitted
     */
    public function notifyProposalSubmitted($proposal_id, $creative_user_id, $project_id, $umkm_user_id) {
        $creative_name = $this->getUserName($creative_user_id);
        $project_title = $this->getProjectTitle($project_id);
        
        $title = "📩 Proposal Baru Diterima";
        $message = "$creative_name mengajukan proposal untuk \"$project_title\"";
        
        return $this->createNotification(
            $umkm_user_id,
            $title,
            $message,
            'proposal',
            'proposal',
            $proposal_id
        );
    }
    
    /**
     * Notify Creative when proposal accepted
     */
    public function notifyProposalAccepted($proposal_id, $creative_user_id, $umkm_user_id, $project_id) {
        $umkm_name = $this->getUserName($umkm_user_id);
        $project_title = $this->getProjectTitle($project_id);
        
        $title = "🎉 Proposal Diterima!";
        $message = "Proposal Anda untuk \"$project_title\" diterima oleh $umkm_name";
        
        return $this->createNotification(
            $creative_user_id,
            $title,
            $message,
            'proposal',
            'proposal',
            $proposal_id
        );
    }
    
    /**
     * Notify Creative when proposal rejected
     */
    public function notifyProposalRejected($proposal_id, $creative_user_id, $umkm_user_id, $project_id) {
        $umkm_name = $this->getUserName($umkm_user_id);
        $project_title = $this->getProjectTitle($project_id);
        
        $title = "❌ Proposal Ditolak";
        $message = "Proposal untuk \"$project_title\" ditolak oleh $umkm_name";
        
        return $this->createNotification(
            $creative_user_id,
            $title,
            $message,
            'proposal',
            'proposal',
            $proposal_id
        );
    }
    
    /**
     * Notify Creative about new project matching their skills
     */
    public function notifyNewProject($project_id, $umkm_user_id) {
        $umkm_name = $this->getUserName($umkm_user_id);
        $project_title = $this->getProjectTitle($project_id);
        
        $title = "🚀 Proyek Baru Tersedia";
        $message = "$umkm_name mempublikasikan proyek: \"$project_title\"";
        
        // Get creative users with matching skills
        $creative_users = $this->getCreativeUsersByProjectSkills($project_id);
        
        $notification_count = 0;
        foreach ($creative_users as $user) {
            $this->createNotification(
                $user['id'],
                $title,
                $message,
                'project',
                'project',
                $project_id
            );
            $notification_count++;
        }
        
        return $notification_count;
    }
    
    /**
     * Notify about new message
     */
    public function notifyNewMessage($sender_id, $receiver_id, $project_id = null) {
        $sender_name = $this->getUserName($sender_id);
        
        $title = "💬 Pesan Baru";
        $message = "Pesan baru dari $sender_name";
        
        return $this->createNotification(
            $receiver_id,
            $title,
            $message,
            'message',
            'message',
            null
        );
    }
    
    /**
     * Notify about contract status update
     */
    public function notifyContractUpdate($contract_id, $user_id, $status, $title) {
        $status_texts = [
            'draft' => '📝 Kontrak dibuat',
            'active' => '✅ Kontrak aktif',
            'completed' => '🏆 Kontrak selesai',
            'cancelled' => '❌ Kontrak dibatalkan'
        ];
        
        $status_text = $status_texts[$status] ?? '📄 Update kontrak';
        
        return $this->createNotification(
            $user_id,
            $status_text,
            "Kontrak \"$title\" telah diperbarui",
            'contract',
            'contract',
            $contract_id
        );
    }
    
    /**
     * Notify about payment
     */
    public function notifyPayment($payment_id, $user_id, $amount, $status) {
        $status_text = ($status === 'paid') ? 'berhasil' : 'gagal';
        
        return $this->createNotification(
            $user_id,
            "💰 Pembayaran $status_text",
            "Pembayaran Rp " . number_format($amount, 0, ',', '.') . " $status_text",
            'payment',
            'payment',
            $payment_id
        );
    }
    
    /**
     * Notify about milestone completion
     */
    public function notifyMilestoneUpdate($milestone_id, $contract_id, $user_id, $title, $status) {
        $status_texts = [
            'completed' => '✅ Milestone selesai',
            'approved' => '👍 Milestone disetujui',
            'paid' => '💰 Milestone dibayar'
        ];
        
        $status_text = $status_texts[$status] ?? '📋 Update milestone';
        
        return $this->createNotification(
            $user_id,
            $status_text,
            "Milestone \"$title\" telah diperbarui",
            'system',
            'milestone',
            $milestone_id
        );
    }
    
    /**
     * System notification
     */
    public function notifySystem($user_id, $title, $message) {
        return $this->createNotification(
            $user_id,
            "🔔 $title",
            $message,
            'system',
            null,
            null
        );
    }
    
    /**
     * =========== HELPER METHODS ===========
     */
    
    private function getUserName($user_id) {
        try {
            $sql = "SELECT full_name FROM users WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['full_name'] ?? 'Pengguna';
        } catch (PDOException $e) {
            return 'Pengguna';
        }
    }
    
    private function getProjectTitle($project_id) {
        try {
            $sql = "SELECT title FROM projects WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $project_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['title'] ?? 'Proyek';
        } catch (PDOException $e) {
            return 'Proyek';
        }
    }
    
    private function getCreativeUsersByProjectSkills($project_id) {
        try {
            // Get project skills
            $sql = "SELECT required_skills FROM projects WHERE id = :project_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':project_id' => $project_id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project || empty($project['required_skills'])) {
                return [];
            }
            
            $skills = json_decode($project['required_skills'], true);
            if (empty($skills)) {
                return [];
            }
            
            // Get creative users with matching skills (simplified query)
            $placeholders = implode(',', array_fill(0, count($skills), '?'));
            $sql = "SELECT DISTINCT u.id 
                    FROM users u 
                    INNER JOIN creative_profiles cp ON u.id = cp.user_id
                    WHERE u.user_type = 'creative' 
                    AND u.is_active = 1
                    AND cp.is_available = 1
                    LIMIT 50";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("❌ Get creative users error: " . $e->getMessage());
            return [];
        }
    }
}
?>