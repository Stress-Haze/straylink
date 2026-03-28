<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('volunteer');

$member_id = $_SESSION['member_id'];
$error     = '';
$success   = '';

// Get animals for dropdown
$animals = mysqli_query($conn, "SELECT id, name, species FROM animals WHERE is_active = 1 ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_type = sanitize($_POST['activity_type']);
    $animal_id     = !empty($_POST['animal_id']) ? (int)$_POST['animal_id'] : null;
    $notes         = sanitize($_POST['notes']);
    $location_label = sanitize($_POST['location_label']);
    $latitude      = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
    $longitude     = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $logged_at     = sanitize($_POST['logged_at']);

    if (empty($activity_type)) {
        $error = "Activity type is required.";
    } else {
        $animal_val = $animal_id ? $animal_id : 'NULL';
        $lat_val    = $latitude  ? $latitude  : 'NULL';
        $lng_val    = $longitude ? $longitude : 'NULL';

        $stmt = mysqli_prepare($conn, "
            INSERT INTO volunteer_logs 
            (volunteer_id, activity_type, animal_id, notes, location_label, latitude, longitude, logged_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "isisssds",
            $member_id, $activity_type, $animal_id, $notes,
            $location_label, $latitude, $longitude, $logged_at
        );

        if (mysqli_stmt_execute($stmt)) {
            $success = "Activity logged successfully!";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}

// Past logs
$logs = mysqli_query($conn, "
    SELECT vl.*, a.name AS animal_name
    FROM volunteer_logs vl
    LEFT JOIN animals a ON vl.animal_id = a.id
    WHERE vl.volunteer_id = $member_id
    ORDER BY vl.logged_at DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Activity — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<?php
    $dashboard_title = 'StrayLink Volunteer';
    include '../../includes/navbar_dashboard.php';
?>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="log_activity.php"><i class="bi bi-journal-plus"></i> Log Activity</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rescue_report.php"><i class="bi bi-exclamation-triangle"></i> Report Rescue</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <h4 class="mb-4">Log Activity</h4>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Activity Type <span class="text-danger">*</span></label>
                                <select name="activity_type" class="form-select" required>
                                    <option value="">-- Select --</option>
                                    <option value="feeding">Feeding</option>
                                    <option value="rescue">Rescue</option>
                                    <option value="vaccination">Vaccination</option>
                                    <option value="foster">Foster</option>
                                    <option value="awareness">Awareness</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Related Animal <small class="text-muted">(optional)</small></label>
                                <select name="animal_id" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php while ($a = mysqli_fetch_assoc($animals)): ?>
                                        <option value="<?= $a['id'] ?>">
                                            <?= htmlspecialchars($a['name'] ?? 'Unnamed') ?> (<?= ucfirst($a['species']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="What did you do? Any observations?"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location_label" class="form-control" placeholder="e.g. Near Lakeside, Pokhara">
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Latitude <small class="text-muted">(optional)</small></label>
                                <input type="text" name="latitude" class="form-control" placeholder="e.g. 28.2096">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Longitude <small class="text-muted">(optional)</small></label>
                                <input type="text" name="longitude" class="form-control" placeholder="e.g. 83.9856">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date & Time</label>
                                <input type="datetime-local" name="logged_at" class="form-control" 
                                    value="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success px-4">Log Activity</button>
                    </form>
                </div>
            </div>

            <!-- Past Logs -->
            <div class="card shadow">
                <div class="card-header bg-white fw-bold">My Recent Logs</div>
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
                        <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                            <tr>
                                <td><span class="badge bg-success"><?= ucfirst($log['activity_type']) ?></span></td>
                                <td><?= htmlspecialchars($log['animal_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($log['location_label'] ?? '—') ?></td>
                                <td><small><?= htmlspecialchars(substr($log['notes'] ?? '—', 0, 60)) ?></small></td>
                                <td><?= date('M d, Y H:i', strtotime($log['logged_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($logs) === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No logs yet</td></tr>
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