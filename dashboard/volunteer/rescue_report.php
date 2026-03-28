<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('volunteer');

$member_id = $_SESSION['member_id'];
$error     = '';
$success   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title          = sanitize($_POST['title']);
    $description    = sanitize($_POST['description']);
    $urgency        = sanitize($_POST['urgency']);
    $location_label = sanitize($_POST['location_label']);
    $latitude       = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
    $longitude      = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

    if (empty($title) || empty($description)) {
        $error = "Title and description are required.";
    } else {
        // Handle photo upload
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $ext     = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg','jpeg','png','webp'];
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'rescue_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], '../../public/uploads/' . $filename);
                $photo = $filename;
            }
        }

        $lat_val   = $latitude  ? $latitude  : 'NULL';
        $lng_val   = $longitude ? $longitude : 'NULL';
        $photo_val = $photo ? "'" . mysqli_real_escape_string($conn, $photo) . "'" : 'NULL';

        $stmt = mysqli_prepare($conn, "
            INSERT INTO rescue_reports
            (reported_by, title, description, urgency, location_label, latitude, longitude, photo, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        mysqli_stmt_bind_param($stmt, "issssddss",
            $member_id, $title, $description, $urgency,
            $location_label, $latitude, $longitude, $photo
        );

        if (mysqli_stmt_execute($stmt)) {
            $success = "Rescue report submitted! It will go live after admin approval.";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}

// My past reports
$reports = mysqli_query($conn, "
    SELECT * FROM rescue_reports 
    WHERE reported_by = $member_id 
    ORDER BY created_at DESC 
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Rescue — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">🐾 StrayLink Volunteer</a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-white">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="log_activity.php"><i class="bi bi-journal-plus"></i> Log Activity</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="rescue_report.php"><i class="bi bi-exclamation-triangle"></i> Report Rescue</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <h4 class="mb-4">Report a Rescue</h4>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                placeholder="e.g. Injured dog near Lakeside">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required
                                placeholder="Describe the animal's condition, situation, and any relevant details..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Urgency <span class="text-danger">*</span></label>
                                <select name="urgency" class="form-select" required>
                                    <option value="low">Low — Stable, needs monitoring</option>
                                    <option value="medium" selected>Medium — Needs attention soon</option>
                                    <option value="high">High — Needs urgent help</option>
                                    <option value="critical">Critical — Life threatening</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" name="location_label" class="form-control"
                                    placeholder="e.g. Near Lakeside, Pokhara">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitude <small class="text-muted">(optional)</small></label>
                                <input type="text" name="latitude" class="form-control" placeholder="e.g. 28.2096">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitude <small class="text-muted">(optional)</small></label>
                                <input type="text" name="longitude" class="form-control" placeholder="e.g. 83.9856">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Photo <small class="text-muted">(optional but helpful)</small></label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Your report will be reviewed by an admin before going live on the platform.
                        </div>
                        <button type="submit" class="btn btn-success px-4">Submit Report</button>
                    </form>
                </div>
            </div>

            <!-- Past Reports -->
            <div class="card shadow">
                <div class="card-header bg-white fw-bold">My Rescue Reports</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Urgency</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($r = mysqli_fetch_assoc($reports)): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $r['urgency'] === 'critical' ? 'danger' : ($r['urgency'] === 'high' ? 'warning' : ($r['urgency'] === 'medium' ? 'info' : 'secondary')) ?>">
                                        <?= ucfirst($r['urgency']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($r['location_label'] ?? '—') ?></td>
                                <td>
                                    <span class="badge bg-<?= $r['status'] === 'approved' ? 'success' : ($r['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                        <?= ucfirst($r['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($reports) === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No reports submitted yet</td></tr>
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