<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('shelter');

$member_id = $_SESSION['member_id'];
$shelter   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shelters WHERE member_id = $member_id"));
if (!$shelter) redirect('setup.php');
$shelter_id = $shelter['id'];

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
    $latitude       = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
    $longitude      = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

    if (empty($shelter_name) || empty($city) || empty($contact_number)) {
        $error = "Shelter name, city and contact number are required.";
    } else {
        // Handle logo upload
        $logo = $shelter['logo'];
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

        $lat_val  = $latitude  ? $latitude  : 'NULL';
        $lng_val  = $longitude ? $longitude : 'NULL';
        $logo_val = $logo ? "'" . mysqli_real_escape_string($conn, $logo) . "'" : 'NULL';

        mysqli_query($conn, "
            UPDATE shelters SET
                shelter_name   = '" . mysqli_real_escape_string($conn, $shelter_name) . "',
                description    = '" . mysqli_real_escape_string($conn, $description) . "',
                address        = '" . mysqli_real_escape_string($conn, $address) . "',
                city           = '" . mysqli_real_escape_string($conn, $city) . "',
                contact_number = '" . mysqli_real_escape_string($conn, $contact_number) . "',
                website        = '" . mysqli_real_escape_string($conn, $website) . "',
                capacity       = $capacity,
                latitude       = $lat_val,
                longitude      = $lng_val,
                logo           = $logo_val
            WHERE id = $shelter_id
        ");

        $success = "Profile updated successfully!";
        $shelter = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shelters WHERE id = $shelter_id"));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shelter Profile — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<?php
    $dashboard_title = $shelter['shelter_name'] ?? 'StrayLink Shelter';
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
                    <a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Our Animals</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="animal_add.php"><i class="bi bi-plus-circle"></i> Add Animal</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="requests.php"><i class="bi bi-envelope"></i> Adoption Requests</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="donations.php"><i class="bi bi-cash-coin"></i> Donations</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="profile.php"><i class="bi bi-gear"></i> Shelter Profile</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <h4 class="mb-4">Shelter Profile</h4>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">

                        <!-- Current Logo -->
                        <?php if ($shelter['logo']): ?>
                        <div class="mb-3">
                            <label class="form-label">Current Logo</label><br>
                            <img src="../../public/uploads/<?= $shelter['logo'] ?>" height="80" style="border-radius:6px;">
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Shelter Name <span class="text-danger">*</span></label>
                            <input type="text" name="shelter_name" class="form-control" required
                                value="<?= htmlspecialchars($shelter['shelter_name']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($shelter['description'] ?? '') ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" required
                                    value="<?= htmlspecialchars($shelter['city'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control"
                                    value="<?= htmlspecialchars($shelter['address'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="text" name="contact_number" class="form-control" required
                                    value="<?= htmlspecialchars($shelter['contact_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Website</label>
                                <input type="text" name="website" class="form-control"
                                    value="<?= htmlspecialchars($shelter['website'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" name="capacity" class="form-control" min="0"
                                value="<?= $shelter['capacity'] ?? 0 ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" class="form-control"
                                    value="<?= $shelter['latitude'] ?? '' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" class="form-control"
                                    value="<?= $shelter['longitude'] ?? '' ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Update Logo</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>

                        <button type="submit" class="btn btn-success px-4">Save Changes</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
