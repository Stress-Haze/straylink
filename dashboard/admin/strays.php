<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    mysqli_query($conn, "UPDATE stray_animals SET status='active' WHERE id=$id");
    redirect('strays.php');
}
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $id = (int)$_GET['remove'];
    mysqli_query($conn, "UPDATE stray_animals SET status='removed' WHERE id=$id");
    redirect('strays.php');
}

$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'pending';
$allowed = ['pending','active','claimed','removed'];
if (!in_array($filter, $allowed)) $filter = 'pending';

$strays = mysqli_query($conn, "
    SELECT s.*, m.full_name AS reporter_name
    FROM stray_animals s
    JOIN members m ON s.reported_by = m.id
    WHERE s.status = '$filter'
    ORDER BY s.created_at DESC
");
$list = [];
while ($row = mysqli_fetch_assoc($strays)) $list[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strays — StrayLink Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<?php $dashboard_title = 'StrayLink Admin'; include '../../includes/navbar_dashboard.php'; ?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="members.php"><i class="bi bi-people"></i> Members</a></li>
                <li class="nav-item"><a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Animals</a></li>
                <li class="nav-item"><a class="nav-link" href="rescues.php"><i class="bi bi-exclamation-triangle"></i> Rescue Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="../../pages/rescue_board.php"><i class="bi bi-broadcast"></i> Rescue Board</a></li>
                <li class="nav-item"><a class="nav-link active" href="strays.php"><i class="bi bi-geo-alt"></i> Strays</a></li>
                <li class="nav-item"><a class="nav-link" href="lost_pets.php"><i class="bi bi-megaphone"></i> Lost Pets</a></li>
                <li class="nav-item"><a class="nav-link" href="shelters.php"><i class="bi bi-house"></i> Shelters</a></li>
                <li class="nav-item"><a class="nav-link" href="posts.php"><i class="bi bi-newspaper"></i> Blog Posts</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h4 class="mb-1">Stray Reports</h4>
            <p class="text-muted mb-4">Review community-submitted stray sightings before they go public.</p>

            <ul class="nav nav-tabs mb-4">
                <?php foreach ($allowed as $f): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $filter===$f?'active':'' ?>" href="?filter=<?= $f ?>"><?= ucfirst($f) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="card shadow">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Photo</th>
                                <th>Animal</th>
                                <th>Condition</th>
                                <th>Area</th>
                                <th>Reported By</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($list as $s):
                            $cc = match($s['condition_status']) {'healthy'=>'success','injured'=>'warning','critical'=>'danger',default=>'secondary'};
                        ?>
                            <tr>
                                <td>
                                    <?php if ($s['photo']): ?>
                                        <img src="../../public/uploads/<?= htmlspecialchars($s['photo']) ?>" width="60" height="50" style="object-fit:cover;border-radius:6px;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="width:60px;height:50px;border-radius:6px;">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($s['name'] ?? 'Unnamed') ?></strong><br>
                                    <small class="text-muted"><?= ucfirst($s['species']) ?> · <?= ucfirst($s['approximate_age']) ?> · <?= ucfirst($s['gender']) ?></small>
                                </td>
                                <td><span class="badge bg-<?= $cc ?>"><?= ucfirst($s['condition_status']) ?></span></td>
                                <td><?= htmlspecialchars($s['area_label'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($s['reporter_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
                                <td>
                                    <a href="../../pages/stray.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                                    <?php if ($s['status'] === 'pending'): ?>
                                        <a href="?approve=<?= $s['id'] ?>" class="btn btn-sm btn-success">Approve</a>
                                        <a href="?remove=<?= $s['id'] ?>" class="btn btn-sm btn-danger">Remove</a>
                                    <?php elseif ($s['status'] === 'active'): ?>
                                        <a href="?remove=<?= $s['id'] ?>" class="btn btn-sm btn-danger">Remove</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($list)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No <?= $filter ?> stray reports.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
