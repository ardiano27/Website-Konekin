<?php
session_start();
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'creative') {
    header("Location: dashboard.php");
} else {
    header("Location: index.php");
}
exit;
?>