<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('shelter');

$member_id = $_SESSION['member_id'];

// If shelter already set up, go to dashboard
$existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM shelters WHERE member_id = $member_id"));
if ($existing) redirect('index.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shelter_name   = sanitize($_POST['shelter_name']);
    $description    = sanitize($_POST['description']);
    $address        = sanitize($_POST['address']);
    $city           = sanitize($_POST['city']);
    $contact_number = sanitize($_POST['contact_number']);
    $website        = sanitize($_POST['website']);
    $capacity       = (int)$_POST['capacity'];
    $latitude       = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : 'NULL';
    $longitude      = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : 'NULL';

    if (empty($shelter_name) || empty($city) || empty($contact_number)) {
        $error = "Shelter name, city and contact number are required.";
    } else {
        // Handle logo upload
        $logo = null;
        if (!empty($_FILES['logo']['name'])) {
            $ext     = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg','jpeg','png','webp'];
            if (in_array(strtolower($ext), $allowed)) {
                $filename    = 'shelter_' . time() . '.' . $ext;
                $upload_path = '../../public/uploads/' . $filename;
                move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path);
                $logo = $filename;
            }
        }

        $lat_val  = is_numeric($latitude)  ? $latitude  : 'NULL';
        $lng_val  = is_numeric($longitude) ? $longitude : 'NULL';
        $logo_val = $logo ? "'" . mysqli_real_escape_string($conn, $logo) . "'" : 'NULL';

        $stmt = mysqli_prepare($conn, "
            INSERT INTO shelters (member_id, shelter_name, description, address, city, contact_number, website, capacity, latitude, longitude, logo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "issssssidds",
            $member_id, $shelter_name, $description, $address,
            $city, $contact_number, $website, $capacity,
            $latitude, $longitude, $logo
        );

        if (mysqli_stmt_execute($stmt)) {
            redirect('index.php');
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
    <title>Setup Shelter Profile — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-light">

<?php
    $dashboard_title = 'StrayLink Shelter';
    include '../../includes/navbar_dashboard.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h4 class="mb-1">Set Up Your Shelter Profile</h4>
                    <p class="text-muted mb-4">This information will be visible to potential adopters.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Shelter Name <span class="text-danger">*</span></label>
                            <input type="text" name="shelter_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Tell people about your shelter..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" required placeholder="e.g. Pokhara">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control" placeholder="Street address">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="text" name="contact_number" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Website</label>
                                <input type="text" name="website" class="form-control" placeholder="https://...">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacity (number of animals you can house)</label>
                            <input type="number" name="capacity" class="form-control" value="0" min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pin Location <small class="text-muted">(optional)</small></label>
                            <?php require_once '../../includes/map_picker.php'; renderMapPicker(); ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Shelter Logo</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-success w-100">Save & Continue</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>