<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('volunteer');

$member_id = (int)$_SESSION['member_id'];
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stray_id'])) {
    $stray_id  = (int)$_POST['stray_id'];
    $condition = sanitize($_POST['condition_status']);
    $note      = sanitize($_POST['note']);

    $stmt = mysqli_prepare($conn, "INSERT INTO stray_updates (stray_id, updated_by, condition_status, note) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'iiss', $stray_id, $member_id, $condition, $note);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_query($conn, "UPDATE stray_animals SET condition_status='$condition', updated_at=NOW() WHERE id=$stray_id");
        $success = "Condition update posted.";
    } else {
        $error = "Something went wrong.";
    }
}

$strays = mysqli_query($conn, "SELECT * FROM stray_animals WHERE status='active' ORDER BY updated_at DESC");
$list = [];
while ($row = mysqli_fetch_assoc($strays)) $list[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stray Updates — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<?php $dashboard_title = 'StrayLink Volunteer'; include '../../includes/navbar_dashboard.php'; ?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="log_activity.php"><i class="bi bi-journal-plus"></i> Log Activity</a></li>
                <li class="nav-item"><a class="nav-link" href="rescue_report.php"><i class="bi bi-exclamation-triangle"></i> Report Rescue</a></li>
                <li class="nav-item"><a class="nav-link active" href="strays.php"><i class="bi bi-geo-alt"></i> Stray Updates</a></li>
                <li class="nav-item"><a class="nav-link" href="posts.php"><i class="bi bi-newspaper"></i> My Posts</a></li>
                <li class="nav-item"><a class="nav-link" href="../../pages/rescue_board.php"><i class="bi bi-broadcast"></i> Rescue Board</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h4 class="mb-1">Stray Condition Updates</h4>
            <p class="text-muted mb-4">Post condition updates for active strays you've spotted in the field.</p>

            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

            <?php if (empty($list)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-geo-alt" style="font-size:3rem;display:block;margin-bottom:1rem;"></i>
                    <p>No active strays to update yet.</p>
                    <a href="../../pages/stray_report.php" class="btn btn-success">Report a Stray</a>
                </div>
            <?php else: ?>
            <div class="row g-4">
            <?php foreach ($list as $s):
                $cc = match($s['condition_status']) {'healthy'=>'success','injured'=>'warning','critical'=>'danger',default=>'secondary'};
            ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card shadow-sm h-100">
                        <?php if ($s['photo']): ?>
                            <img src="../../public/uploads/<?= htmlspecialchars($s['photo']) ?>" class="card-img-top" style="height:160px;object-fit:cover;">
                        <?php endif; ?>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <strong><?= htmlspecialchars($s['name'] ?? 'Unnamed') ?></strong>
                                <span class="badge bg-<?= $cc ?>"><?= ucfirst($s['condition_status']) ?></span>
                            </div>
                            <p class="text-muted small mb-3">
                                <?= ucfirst($s['species']) ?> · <?= htmlspecialchars($s['area_label'] ?? '—') ?><br>
                                Updated <?= timeAgo($s['updated_at']) ?>
                            </p>
                            <form method="POST">
                                <input type="hidden" name="stray_id" value="<?= $s['id'] ?>">
                                <div class="mb-2">
                                    <select name="condition_status" class="form-select form-select-sm">
                                        <option value="unknown" <?= $s['condition_status']==='unknown'?'selected':'' ?>>Unknown</option>
                                        <option value="healthy" <?= $s['condition_status']==='healthy'?'selected':'' ?>>Healthy</option>
                                        <option value="injured" <?= $s['condition_status']==='injured'?'selected':'' ?>>Injured</option>
                                        <option value="critical" <?= $s['condition_status']==='critical'?'selected':'' ?>>Critical</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Quick note (optional)"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100">Post Update</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
