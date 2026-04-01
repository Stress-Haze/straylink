<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) redirect('../auth/login.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $urgency = sanitize($_POST['urgency']);
    $location_label = sanitize($_POST['location_label']);
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $member_id = $_SESSION['member_id'];

    if (empty($title) || empty($description)) {
        $error = "Title and description are required.";
    } else {
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
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
    <title>Report a Stray - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php
    $active_page = 'rescue';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
    <section class="rescue-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="section-kicker mb-2">Rescue reporting</p>
                <h1 class="fw-bold mb-3">Report a stray quickly so the community can respond faster.</h1>
                <p class="text-muted mb-4">This page should feel like an action screen, not just a form. Add the most useful details you have now and the team can review and follow up from there.</p>
                <div class="gallery-chip-row">
                    <span class="gallery-chip"><i class="bi bi-clock-history"></i> Fast mobile reporting</span>
                    <span class="gallery-chip"><i class="bi bi-shield-check"></i> Admin reviewed</span>
                    <span class="gallery-chip"><i class="bi bi-geo-alt"></i> Location friendly</span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="gallery-summary-card">
                    <div>
                        <span class="summary-label">Best info to include</span>
                        <strong>Condition, urgency, exact location, and a recent photo if possible.</strong>
                    </div>
                    <div>
                        <span class="summary-label">What happens next</span>
                        <strong>Your report is reviewed first, then acted on by the appropriate team.</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="card shadow-sm rescue-form-card">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                        <div>
                            <h2 class="fw-bold mb-1">Rescue Report Form</h2>
                            <p class="text-muted">Share enough detail for the team to understand the case at a glance.</p>
                        </div>
                        <span class="badge text-bg-light border">Required fields marked *</span>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Injured dog near Lakeside Pokhara">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="5" required placeholder="Describe the animal's condition, what you saw, whether it can move, and any immediate risks..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Urgency</label>
                                <select name="urgency" class="form-select">
                                    <option value="low">Low - Stable, needs monitoring</option>
                                    <option value="medium" selected>Medium - Needs attention soon</option>
                                    <option value="high">High - Needs urgent help</option>
                                    <option value="critical">Critical - Life threatening</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" name="location_label" class="form-control" placeholder="e.g. Near Lakeside, Pokhara">
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
                            Your report will be reviewed by our admin team before action is taken.
                        </div>
                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            <i class="bi bi-exclamation-triangle"></i> Submit Report
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="rescue-side-stack">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Before You Submit</h5>
                        <div class="gallery-meta-list single-column">
                            <span><i class="bi bi-check2-circle"></i> Note the animal's condition and whether it can move safely.</span>
                            <span><i class="bi bi-check2-circle"></i> Share nearby landmarks so volunteers can find the location.</span>
                            <span><i class="bi bi-check2-circle"></i> Add a photo only if it is safe for you to take one.</span>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Urgency Guide</h5>
                        <div class="urgency-guide">
                            <div><span class="badge bg-success">Low</span><p>Visible but stable, needs monitoring or later pickup.</p></div>
                            <div><span class="badge text-bg-warning">Medium</span><p>Needs help soon, but not in immediate life-threatening danger.</p></div>
                            <div><span class="badge bg-danger">High / Critical</span><p>Serious injury, heavy bleeding, trapped, or unable to move.</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
