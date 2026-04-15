<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('volunteer');

$member_id = $_SESSION['member_id'];

$total_logs    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM volunteer_logs WHERE volunteer_id = $member_id"))['count'];
$total_rescues = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rescue_reports WHERE reported_by = $member_id"))['count'];
$resolved      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rescue_reports WHERE reported_by = $member_id AND status = 'resolved'"))['count'];
$pending       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rescue_reports WHERE reported_by = $member_id AND status = 'pending'"))['count'];

$recent_logs = mysqli_query($conn, "
    SELECT vl.*, a.name AS animal_name
    FROM volunteer_logs vl
    LEFT JOIN animals a ON vl.animal_id = a.id
    WHERE vl.volunteer_id = $member_id
    ORDER BY vl.logged_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Dashboard — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .feature-card-link {
            text-decoration: none;
        }

        .feature-card-link .card {
            transition: all 0.3s ease;
        }

        .feature-card-link:hover .card {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
        }

        .feature-card-link:hover .card i {
            transform: scale(1.1);
        }

        .feature-card-link .card i {
            transition: transform 0.3s ease;
        }
    </style>
</head>
<body>

<?php
    $dashboard_title = 'StrayLink Volunteer';
    include '../../includes/navbar_dashboard.php';
?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="log_activity.php"><i class="bi bi-journal-plus"></i> Log Activity</a></li>
                <li class="nav-item"><a class="nav-link" href="rescue_report.php"><i class="bi bi-exclamation-triangle"></i> Report Rescue</a></li>
                <li class="nav-item"><a class="nav-link" href="posts.php"><i class="bi bi-newspaper"></i> My Posts</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h4 class="mb-4">Dashboard Overview</h4>
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-success shadow">
                        <div class="card-body">
                            <h6 class="card-title">Total Activities</h6>
                            <h2 class="text-white"><?= $total_logs ?></h2>
                            <a href="log_activity.php" class="text-white small">Log new →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-primary shadow">
                        <div class="card-body">
                            <h6 class="card-title">Rescues Reported</h6>
                            <h2 class="text-white"><?= $total_rescues ?></h2>
                            <a href="rescue_report.php" class="text-white small">Report new →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning shadow">
                        <div class="card-body">
                            <h6 class="card-title">Pending Approval</h6>
                            <h2 class="text-white"><?= $pending ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <a href="post_create.php" class="feature-card-link">
                        <div class="card text-white bg-secondary shadow h-100 d-flex align-items-center justify-content-center" style="min-height: 150px;">
                            <div class="card-body text-center">
                                <i class="bi bi-newspaper" style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem;"></i>
                                <h6 class="card-title m-0">Create Post</h6>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header bg-white fw-bold d-flex justify-content-between">
                    Recent Activity
                    <a href="log_activity.php" class="btn btn-sm btn-success">+ Log Activity</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Activity</th>
                                <th>Animal</th>
                                <th>Location</th>
                                <th>Notes</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($log = mysqli_fetch_assoc($recent_logs)): ?>
                            <tr>
                                <td><span class="badge bg-success"><?= ucfirst($log['activity_type']) ?></span></td>
                                <td><?= htmlspecialchars($log['animal_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($log['location_label'] ?? '—') ?></td>
                                <td><small><?= htmlspecialchars(substr($log['notes'] ?? '—', 0, 60)) ?></small></td>
                                <td><?= date('M d, Y', strtotime($log['logged_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($recent_logs) === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No activity logged yet</td></tr>
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