<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Must be logged in to report
if (!isLoggedIn()) redirect('../auth/login.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title          = sanitize($_POST['title']);
    $description    = sanitize($_POST['description']);
    $urgency        = sanitize($_POST['urgency']);
    $location_label = sanitize($_POST['location_label']);
    $latitude       = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
    $longitude      = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $member_id      = $_SESSION['member_id'];

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
                move_uploaded_file($_FILES['photo']['tmp_name'], '../public/uploads/' . $filename);
                $photo = $filename;
            }
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO rescue_reports
            (reported_by, title, description, urgency, location_label, latitude, longitude, photo, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        mysqli_stmt_bind_param($stmt, "issssdds",
            $member_id, $title, $description, $urgency,
            $location_label, $latitude, $longitude, $photo
        );

        if (mysqli_stmt_execute($stmt)) {
            $success = "Thank you! Your report has been submitted and will be reviewed by our team shortly.";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report a Stray — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<?php
    $active_page = 'rescue';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <h2 class="fw-bold mb-2">Report a Stray Animal</h2>
            <p class="text-muted mb-4">Spotted an animal in distress? Let us know and our team will follow up.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                placeholder="e.g. Injured dog near Lakeside Pokhara">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required
                                placeholder="Describe the animal's condition, what you saw, and any other relevant details..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Urgency</label>
                                <select name="urgency" class="form-select">
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
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle"></i>
                            Your report will be reviewed by our admin team before being acted upon.
                        </div>
                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            <i class="bi bi-exclamation-triangle"></i> Submit Report
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>