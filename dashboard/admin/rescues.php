<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

// Handle approve — award karma to reporter
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $report = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM rescue_reports WHERE id = $id"));
    if ($report) {
        mysqli_query($conn, "UPDATE rescue_reports SET status = 'approved' WHERE id = $id");
        addKarma($conn, $report['reported_by'], 5);
        // Notify all volunteers and shelters that a rescue is on the board
        notifyRoles($conn, ['volunteer', 'shelter'], 'rescue_approved',
            'A new rescue report is on the board: ' . $report['title'],
            '../../pages/rescue_board.php?report=' . $id);
    }
    redirect('rescues.php');
}

if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    mysqli_query($conn, "UPDATE rescue_reports SET status = 'rejected' WHERE id = $id");
    redirect('rescues.php');
}

$filter  = isset($_GET['status']) ? sanitize($_GET['status']) : 'pending';
$allowed = ['pending','approved','rejected','in_progress','resolved'];
if (!in_array($filter, $allowed)) $filter = 'pending';

$reports = mysqli_query($conn, "
    SELECT r.*, m.full_name, m.karma
    FROM rescue_reports r 
    JOIN members m ON r.reported_by = m.id 
    WHERE r.status = '$filter'
    ORDER BY r.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rescue Reports — StrayLink Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<?php
    $dashboard_title = 'StrayLink Admin';
    include '../../includes/navbar_dashboard.php';
?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="members.php"><i class="bi bi-people"></i> Members</a></li>
                <li class="nav-item"><a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Animals</a></li>
                <li class="nav-item"><a class="nav-link active" href="rescues.php"><i class="bi bi-exclamation-triangle"></i> Rescue Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="../../pages/rescue_board.php"><i class="bi bi-broadcast"></i> Rescue Board</a></li>
                <li class="nav-item"><a class="nav-link" href="strays.php"><i class="bi bi-geo-alt"></i> Strays</a></li>
                <li class="nav-item"><a class="nav-link" href="lost_pets.php"><i class="bi bi-megaphone"></i> Lost Pets</a></li>
                <li class="nav-item"><a class="nav-link" href="shelters.php"><i class="bi bi-house"></i> Shelters</a></li>
                <li class="nav-item"><a class="nav-link" href="posts.php"><i class="bi bi-newspaper"></i> Blog Posts</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h4 class="mb-4">Rescue Reports</h4>

            <ul class="nav nav-tabs mb-4">
                <?php foreach ($allowed as $s): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $filter === $s ? 'active' : '' ?>" href="?status=<?= $s ?>">
                            <?= ucfirst($s) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="card shadow">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Reported By</th>
                                <th>Location</th>
                                <th>Urgency</th>
                                <th>Photo</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = mysqli_fetch_assoc($reports)): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['title']) ?></strong>
                                    <p class="text-muted small mb-0"><?= htmlspecialchars(substr($row['description'], 0, 80)) ?>...</p>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['full_name']) ?>
                                    <br><small class="text-muted"><?= $row['karma'] ?> karma</small>
                                </td>
                                <td><?= htmlspecialchars($row['location_label'] ?? '—') ?></td>
                                <td>
                                    <span class="badge bg-<?= $row['urgency'] === 'critical' ? 'danger' : ($row['urgency'] === 'high' ? 'warning' : ($row['urgency'] === 'medium' ? 'info' : 'secondary')) ?>">
                                        <?= ucfirst($row['urgency']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['photo']): ?>
                                        <a href="../../public/uploads/<?= $row['photo'] ?>" target="_blank">
                                            <img src="../../public/uploads/<?= $row['photo'] ?>" width="50" height="50" style="object-fit:cover;border-radius:4px;">
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <a href="?approve=<?= $row['id'] ?>" class="btn btn-sm btn-success">Approve +5 karma</a>
                                        <a href="?reject=<?= $row['id'] ?>" class="btn btn-sm btn-danger">Reject</a>
                                    <?php elseif ($row['status'] === 'approved' || $row['status'] === 'in_progress'): ?>
                                        <a href="../../pages/rescue_board.php?report=<?= $row['id'] ?>" class="btn btn-sm btn-outline-success" target="_blank">
                                            <i class="bi bi-broadcast me-1"></i>View on Board
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-<?= $row['status'] === 'resolved' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($reports) === 0): ?>
                            <tr><td colspan="8" class="text-center text-muted py-3">No <?= $filter ?> reports</td></tr>
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
