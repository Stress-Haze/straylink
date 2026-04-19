<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = sanitize($_POST['name']);
    $species     = sanitize($_POST['species']);
    $breed       = sanitize($_POST['breed']);
    $approx_age  = sanitize($_POST['approximate_age']);
    $gender      = sanitize($_POST['gender']);
    $condition   = sanitize($_POST['condition_status']);
    $area_label  = sanitize($_POST['area_label']);
    $description = sanitize($_POST['description']);
    $member_id   = (int)$_SESSION['member_id'];

    if (empty($species) || empty($area_label)) {
        $error = "Species and area are required.";
    } else {
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $filename = 'stray_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], '../public/uploads/' . $filename);
                $photo = $filename;
            }
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO stray_animals (reported_by, name, species, breed, approximate_age, gender, condition_status, area_label, description, photo, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        mysqli_stmt_bind_param($stmt, 'isssssssss',
            $member_id, $name, $species, $breed, $approx_age, $gender, $condition, $area_label, $description, $photo
        );

        if (mysqli_stmt_execute($stmt)) {
            $success = "Report submitted! It will go live after admin review.";
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
<?php $active_page = 'strays'; $nav_depth = 1; include '../includes/navbar.php'; ?>

<div class="container py-5" style="max-width:680px;">
    <a href="strays.php" class="btn btn-outline-secondary btn-sm mb-4"><i class="bi bi-arrow-left me-1"></i>Back to Strays</a>
    <h2 class="fw-bold mb-1">Report a Stray</h2>
    <p class="text-muted mb-4">Spotted an animal that needs attention? Fill in what you know — even partial info helps. All reports are reviewed before going public.</p>

    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?> <a href="strays.php">View all strays</a></div><?php endif; ?>

    <?php if (!$success): ?>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name <small class="text-muted">(if known)</small></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Brownie">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Species <span class="text-danger">*</span></label>
                        <select name="species" class="form-select" required>
                            <option value="">-- Select --</option>
                            <option value="dog">Dog</option>
                            <option value="cat">Cat</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Breed <small class="text-muted">(if known)</small></label>
                        <input type="text" name="breed" class="form-control" placeholder="e.g. Labrador mix">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Approximate Age</label>
                        <select name="approximate_age" class="form-select">
                            <option value="unknown">Unknown</option>
                            <option value="young">Young (puppy/kitten)</option>
                            <option value="adult">Adult</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="unknown">Unknown</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Condition</label>
                        <select name="condition_status" class="form-select">
                            <option value="unknown">Unknown</option>
                            <option value="healthy">Healthy</option>
                            <option value="injured">Injured</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">General Area <span class="text-danger">*</span></label>
                        <input type="text" name="area_label" class="form-control" required placeholder="e.g. Near Lakeside, Pokhara — keep it neighbourhood-level">
                        <small class="text-muted">Do not include exact addresses. Neighbourhood or landmark is enough.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Describe the animal's appearance, behaviour, or situation..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Photo <small class="text-muted">(optional but very helpful)</small></label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="alert alert-info mt-4 mb-3 small">
                    <i class="bi bi-info-circle me-1"></i>Your report will be reviewed by an admin before appearing publicly. Exact location is never shown.
                </div>
                <button type="submit" class="btn btn-success px-4">Submit Report</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
