<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$member_id = (int)$_SESSION['member_id'];

// Mark all as read
mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE member_id = $member_id");

$notifications = mysqli_query($conn, "
    SELECT * FROM notifications
    WHERE member_id = $member_id
    ORDER BY created_at DESC
    LIMIT 50
");

$nav_depth  = 1;
$active_page = 'notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container py-5" style="max-width:680px;">
    <h2 class="fw-bold mb-4"><i class="bi bi-bell text-success me-2"></i>Notifications</h2>

    <?php $count = mysqli_num_rows($notifications); ?>
    <?php if ($count === 0): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-bell-slash" style="font-size:3rem;display:block;margin-bottom:1rem;"></i>
            <p>No notifications yet.</p>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-2">
        <?php while ($n = mysqli_fetch_assoc($notifications)): ?>
            <?php
                $icon_map = [
                    'rescue_approved' => 'bi-broadcast text-success',
                    'rescue_message'  => 'bi-chat-dots text-primary',
                    'rescue_resolved' => 'bi-check-circle text-success',
                ];
                $icon = $icon_map[$n['type']] ?? 'bi-bell text-secondary';
            ?>
            <div class="card shadow-sm">
                <div class="card-body d-flex gap-3 align-items-start py-3">
                    <i class="bi <?= $icon ?>" style="font-size:1.4rem;flex-shrink:0;margin-top:2px;"></i>
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-1"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($n['body']) ?></div>
                        <?php if ($n['link']): ?>
                            <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-sm btn-outline-success mt-2">View</a>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted flex-shrink-0"><?= timeAgo($n['created_at']) ?></small>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
