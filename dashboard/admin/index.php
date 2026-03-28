<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

$total_animals   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM animals"))['count'];
$total_members   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM members"))['count'];
$total_shelters  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM shelters"))['count'];
$pending_rescues = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rescue_reports WHERE status = 'pending'"))['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — StrayLink</title>
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
                <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="members.php"><i class="bi bi-people"></i> Members</a></li>
                <li class="nav-item"><a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Animals</a></li>
                <li class="nav-item"><a class="nav-link" href="rescues.php"><i class="bi bi-exclamation-triangle"></i> Rescue Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="shelters.php"><i class="bi bi-house"></i> Shelters</a></li>
                <li class="nav-item"><a class="nav-link" href="posts.php"><i class="bi bi-newspaper"></i> Blog Posts</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h4 class="mb-4">Dashboard Overview</h4>
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-success shadow">
                        <div class="card-body">
                            <h6 class="card-title">Total Animals</h6>
                            <h2><?= $total_animals ?></h2>
                            <a href="animals.php" class="text-white small">View all →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-primary shadow">
                        <div class="card-body">
                            <h6 class="card-title">Total Members</h6>
                            <h2><?= $total_members ?></h2>
                            <a href="members.php" class="text-white small">View all →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info shadow">
                        <div class="card-body">
                            <h6 class="card-title">Shelters</h6>
                            <h2><?= $total_shelters ?></h2>
                            <a href="shelters.php" class="text-white small">View all →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning shadow">
                        <div class="card-body">
                            <h6 class="card-title">Pending Rescues</h6>
                            <h2><?= $pending_rescues ?></h2>
                            <a href="rescues.php" class="text-white small">View all →</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header bg-white fw-bold">Recent Rescue Reports</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Reported By</th>
                                <th>Urgency</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $reports = mysqli_query($conn, "
                            SELECT r.*, m.full_name 
                            FROM rescue_reports r 
                            JOIN members m ON r.reported_by = m.id 
                            ORDER BY r.created_at DESC 
                            LIMIT 5
                        ");
                        while ($row = mysqli_fetch_assoc($reports)):
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $row['urgency'] === 'critical' ? 'danger' : ($row['urgency'] === 'high' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($row['urgency']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $row['status'] === 'pending' ? 'warning' : 'success' ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td><a href="rescues.php" class="btn btn-sm btn-outline-success">View</a></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($reports) === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No rescue reports yet</td></tr>
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