<?php
// Ketika creative mengajukan proposal
if ($proposalSubmitted) {
    $notificationManager = new NotificationManager();
    $notificationManager->notifyProposalSubmitted(
        $proposalId,
        $_SESSION['user_id'],
        $projectId,
        $umkmUserId
    );
}
?>

<?php
// Ketika UMKM menerima proposal
if ($proposalAccepted) {
    $notificationManager = new NotificationManager();
    $notificationManager->notifyProposalAccepted(
        $proposalId,
        $creativeUserId,
        $_SESSION['user_id'],
        $projectId
    );
}
?>

<?php
// Ketika ada pesan baru
if ($newMessage) {
    $notificationManager = new NotificationManager();
    $notificationManager->notifyNewMessage(
        $_SESSION['user_id'],
        $receiverId,
        $projectId
    );
}
?>