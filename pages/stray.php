<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) redirect('strays.php');

$stray = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT s.*, m.full_name AS reporter_name
    FROM stray_animals s
    JOIN members m ON s.reported_by = m.id
    WHERE s.id = $id AND s.status = 'active'
"));
if (!$stray) redirect('strays.php');

$updates = mysqli_query($conn, "
    SELECT su.*, m.full_name, m.role
    FROM stray_updates su
    JOIN members m ON su.updated_by = m.id
    WHERE su.stray_id = $id
    ORDER BY su.created_at DESC
    LIMIT 10
");
$update_list = [];
while ($u = mysqli_fetch_assoc($updates)) $update_list[] = $u;

$error = $success = '';

// Handle inquiry submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquire'])) {
    if (!isLoggedIn()) redirect('../auth/login.php');
    $message = sanitize($_POST['message']);
    $member_id = (int)$_SESSION['member_id'];
    if ($message) {
        $msg_safe = mysqli_real_escape_string($conn, $message);
        mysqli_query($conn, "INSERT INTO stray_inquiries (stray_id, member_id, message) VALUES ($id, $member_id, '$msg_safe')");
        $success = "Your inquiry has been submitted. The community team will follow up.";
    } else {
        $error = "Please write a message before submitting.";
    }
}

$cond_color = match($stray['condition_status']) {
    'healthy'  => 'success',
    'injured'  => 'warning',
    'critical' => 'danger',
    default    => 'secondary'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($stray['name'] ?? 'Stray Animal') ?> — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php $active_page = 'strays'; $nav_depth = 1; include '../includes/navbar.php'; ?>

<div class="container py-5">
    <a href="strays.php" class="btn btn-outline-secondary btn-sm mb-4"><i class="bi bi-arrow-left me-1"></i>Back to Strays</a>

    <div class="row g-4">
        <div class="col-lg-7">
            <?php if ($stray['photo']): ?>
                <img src="../public/uploads/<?= htmlspecialchars($stray['photo']) ?>" class="img-fluid rounded-4 shadow mb-4 w-100" style="max-height:420px;object-fit:cover;">
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <h2 class="fw-bold mb-1"><?= htmlspecialchars($stray['name'] ?? 'Unnamed Stray') ?></h2>
                            <p class="text-muted mb-0">
                                <?= ucfirst($stray['species']) ?>
                                <?= $stray['breed'] ? ' · ' . htmlspecialchars($stray['breed']) : '' ?>
                                · <?= ucfirst($stray['gender']) ?>
                                · <?= ucfirst($stray['approximate_age']) ?>
                            </p>
                        </div>
                        <span class="badge bg-<?= $cond_color ?> fs-6 px-3 py-2">
                            <?= ucfirst($stray['condition_status']) ?>
                        </span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="small text-muted mb-1">General Area</div>
                                <strong><i class="bi bi-geo-alt text-success me-1"></i><?= htmlspecialchars($stray['area_label'] ?? 'Unknown') ?></strong>
                                <div class="small text-muted mt-1">Exact location not shown for safety</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="small text-muted mb-1">Last Updated</div>
                                <strong><i class="bi bi-clock text-success me-1"></i><?= timeAgo($stray['updated_at']) ?></strong>
                                <div class="small text-muted mt-1">Reported <?= date('M d, Y', strtotime($stray['created_at'])) ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($stray['description']): ?>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($stray['description'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Condition history -->
            <?php if (!empty($update_list)): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-clock-history me-2 text-success"></i>Condition Updates</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                    <?php foreach ($update_list as $u):
                        $uc = match($u['condition_status']) { 'healthy'=>'success','injured'=>'warning','critical'=>'danger',default=>'secondary' };
                    ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-<?= $uc ?> me-2"><?= ucfirst($u['condition_status']) ?></span>
                                    <small class="text-muted"><?= htmlspecialchars($u['full_name']) ?> · <?= ucfirst($u['role']) ?></small>
                                    <?php if ($u['note']): ?>
                                        <p class="mb-0 mt-1 small"><?= htmlspecialchars($u['note']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted flex-shrink-0"><?= timeAgo($u['created_at']) ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm sticky-top" style="top:1.5rem;">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="bi bi-chat-dots me-2"></i>Inquire About This Stray
                </div>
                <div class="card-body">
                    <?php if ($stray['status'] === 'claimed'): ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle me-2"></i>This stray has been claimed by a shelter and is now in care.
                        </div>
                    <?php elseif (!isLoggedIn()): ?>
                        <p class="text-muted mb-3">Want to help or learn more about this animal? Log in to send an inquiry to our volunteer team.</p>
                        <a href="../auth/login.php" class="btn btn-success w-100">Login to Inquire</a>
                        <a href="../auth/register.php" class="btn btn-outline-success w-100 mt-2">Create an Account</a>
                    <?php else: ?>
                        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
                        <?php if (!$success): ?>
                        <p class="text-muted small mb-3">Your message goes to our volunteer team. They'll follow up with more details or coordinate a visit.</p>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Your Message</label>
                                <textarea name="message" class="form-control" rows="4" required
                                    placeholder="e.g. I spotted this dog near my home and want to help. Can I get more info?"></textarea>
                            </div>
                            <button type="submit" name="inquire" class="btn btn-success w-100">Send Inquiry</button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light small text-muted">
                    <i class="bi bi-shield-check me-1 text-success"></i>
                    Exact location is never shared publicly to protect this animal.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
