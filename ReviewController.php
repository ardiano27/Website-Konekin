<?php
session_start();
include "../config/Database.php";
include "../models/Review.php";
include "../models/Notification.php";

class ReviewController {
    private $db;
    private $reviewModel;
    private $notificationModel;
    
    public function __construct() {
        $this->db = new Database();
        $this->reviewModel = new Review($this->db);
        $this->notificationModel = new Notification($this->db);
    }
    
    public function handleRequest() {
        if ($_POST['action'] ?? '') {
            $action = $_POST['action'];
            
            switch ($action) {
                case 'submit_review':
                    $this->submitReview();
                    break;
                default:
                    $this->redirectWithError('Aksi tidak valid');
            }
        }
    }
    
    private function submitReview() {
        try {
            $requiredFields = ['contract_id', 'creative_id', 'overall_rating', 'public_review', 'would_recommend'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Field $field harus diisi");
                }
            }
            
            if (empty($_POST['category_ratings']) || !is_array($_POST['category_ratings'])) {
                throw new Exception("Rating kategori harus diisi");
            }
            
            $reviewData = [
                'contract_id' => $_POST['contract_id'],
                'creative_id' => $_POST['creative_id'],
                'umkm_id' => $_SESSION['user_id'],
                'overall_rating' => $_POST['overall_rating'],
                'public_review' => $_POST['public_review'],
                'private_feedback' => $_POST['private_feedback'] ?? '',
                'category_ratings' => $_POST['category_ratings'],
                'would_recommend' => $_POST['would_recommend']
            ];
            
            $result = $this->reviewModel->submitEnhancedReview($reviewData);
            
            if ($result) {
                $this->notificationModel->create(
                    $reviewData['creative_id'],
                    "Review Baru Diterima",
                    "UMKM telah memberikan review untuk pekerjaan Anda",
                    'review',
                    'contract',
                    $reviewData['contract_id']
                );
                
                $_SESSION['review_success'] = "Review berhasil dikirim! Terima kasih atas feedbacknya.";
                header("Location: ../views/review/review-success.php");
            } else {
                throw new Exception("Gagal menyimpan review");
            }
            
        } catch (Exception $e) {
            $_SESSION['review_error'] = $e->getMessage();
            header("Location: " . $_SERVER['HTTP_REFERER']);
        }
        exit;
    }
    
    private function redirectWithError($message) {
        $_SESSION['review_error'] = $message;
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../dashboard/'));
        exit;
    }
}

$controller = new ReviewController();
$controller->handleRequest();
?>