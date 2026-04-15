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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) redirect('animals.php');
$animal_id = (int)$_GET['id'];
$animal    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM animals WHERE id = $animal_id AND shelter_id = $shelter_id"));
if (!$animal) redirect('animals.php');

// Handle photo delete
if (isset($_GET['delete_photo']) && is_numeric($_GET['delete_photo'])) {
    $pid = (int)$_GET['delete_photo'];
    $photo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM animal_photos WHERE id = $pid AND animal_id = $animal_id"));
    if ($photo) {
        $filepath = '../../public/uploads/' . $photo['photo_path'];
        if (file_exists($filepath)) unlink($filepath);
        mysqli_query($conn, "DELETE FROM animal_photos WHERE id = $pid");
    }
    redirect("animal_edit.php?id=$animal_id");
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = sanitize($_POST['name']);
    $species         = sanitize($_POST['species']);
    $breed           = sanitize($_POST['breed']);
    $age_years       = (int)$_POST['age_years'];
    $age_months      = (int)$_POST['age_months'];
    $gender          = sanitize($_POST['gender']);
    $size            = sanitize($_POST['size']);
    $color           = sanitize($_POST['color']);
    $description     = sanitize($_POST['description']);
    $collar_status   = sanitize($_POST['collar_status']);
    $adoption_status = sanitize($_POST['adoption_status']);
    $is_vaccinated   = isset($_POST['is_vaccinated']) ? 1 : 0;
    $is_sterilized   = isset($_POST['is_sterilized']) ? 1 : 0;

    // Validate: Red collar animals cannot be marked as available for adoption
    if ($collar_status === 'red' && $adoption_status === 'available') {
        $error = "Red collar animals (injured/critical) cannot be marked as available for adoption. Only 'Reserved' or 'Not Available' are allowed.";
    } else {
        mysqli_query($conn, "
            UPDATE animals SET
                name            = '" . mysqli_real_escape_string($conn, $name) . "',
                species         = '$species',
                breed           = '" . mysqli_real_escape_string($conn, $breed) . "',
                age_years       = $age_years,
                age_months      = $age_months,
                gender          = '$gender',
                size            = '$size',
                color           = '" . mysqli_real_escape_string($conn, $color) . "',
                description     = '" . mysqli_real_escape_string($conn, $description) . "',
                collar_status   = '$collar_status',
                adoption_status = '$adoption_status',
                is_vaccinated   = $is_vaccinated,
                is_sterilized   = $is_sterilized
            WHERE id = $animal_id AND shelter_id = $shelter_id
        ");

        // Handle new photo uploads
        if (!empty($_FILES['photos']['name'][0])) {
            $allowed = ['jpg','jpeg','png','webp'];
            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp) {
                if ($_FILES['photos']['error'][$key] === 0) {
                    $ext = pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION);
                    if (in_array(strtolower($ext), $allowed)) {
                        $filename = 'animal_' . $animal_id . '_' . time() . '_' . $key . '.' . $ext;
                        move_uploaded_file($tmp, '../../public/uploads/' . $filename);
                        mysqli_query($conn, "INSERT INTO animal_photos (animal_id, photo_path, is_primary) VALUES ($animal_id, '$filename', 0)");
                    }
                }
            }
        }

        // Auto-set newest photo as primary if no primary exists
        $primary_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM animal_photos WHERE animal_id = $animal_id AND is_primary = 1"))['c'];
        if ($primary_count == 0 && mysqli_num_rows($photos) > 0) {
            $latest_photo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM animal_photos WHERE animal_id = $animal_id ORDER BY id DESC LIMIT 1"));
            if ($latest_photo) {
                mysqli_query($conn, "UPDATE animal_photos SET is_primary = 1 WHERE id = " . (int)$latest_photo['id']);
            }
        }

        $success = "Animal updated successfully!";
    }

    if (!$error) {
        $animal  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM animals WHERE id = $animal_id"));
    }
}


$photos = mysqli_query($conn, "SELECT * FROM animal_photos WHERE animal_id = $animal_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Animal — StrayLink</title>
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
                    <a class="nav-link active" href="animals.php"><i class="bi bi-heart"></i> Our Animals</a>
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
                    <a class="nav-link" href="profile.php"><i class="bi bi-gear"></i> Shelter Profile</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Edit Animal</h4>
                <a href="animals.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <!-- Existing Photos -->
            <?php if (mysqli_num_rows($photos) > 0): ?>
            <div class="card shadow mb-4">
<div class="card-header bg-white fw-bold">Current Photos</div>

                <div class="card-body d-flex gap-2 flex-wrap">
                    <?php while ($p = mysqli_fetch_assoc($photos)): ?>
                        <div class="position-relative">
                            <img src="../../public/uploads/<?= $p['photo_path'] ?>" width="100" height="100" style="object-fit:cover;border-radius:6px;">

                            <?php if ($p['is_primary']): ?>
                                <span class="badge bg-success position-absolute top-0 start-0">Main</span>
                            <?php endif; ?>
                            <a href="?id=<?= $animal_id ?>&delete_photo=<?= $p['id'] ?>"
                               class="badge bg-danger position-absolute top-0 end-0"
                               onclick="return confirm('Delete this photo?')">×</a>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>


            <div class="card shadow">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control"
                                    value="<?= htmlspecialchars($animal['name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Species <span class="text-danger">*</span></label>
                                <select name="species" class="form-select" required>
                                    <option value="dog"   <?= $animal['species'] === 'dog'   ? 'selected' : '' ?>>Dog</option>
                                    <option value="cat"   <?= $animal['species'] === 'cat'   ? 'selected' : '' ?>>Cat</option>
                                    <option value="other" <?= $animal['species'] === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Breed</label>
                                <input type="text" name="breed" class="form-control"
                                    value="<?= htmlspecialchars($animal['breed'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Age (Years)</label>
                                <input type="number" name="age_years" class="form-control"
                                    value="<?= $animal['age_years'] ?? 0 ?>" min="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Age (Months)</label>
                                <input type="number" name="age_months" class="form-control"
                                    value="<?= $animal['age_months'] ?? 0 ?>" min="0" max="11">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="unknown" <?= $animal['gender'] === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                                    <option value="male"    <?= $animal['gender'] === 'male'    ? 'selected' : '' ?>>Male</option>
                                    <option value="female"  <?= $animal['gender'] === 'female'  ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Size</label>
                                <select name="size" class="form-select">
                                    <option value="small"  <?= $animal['size'] === 'small'  ? 'selected' : '' ?>>Small</option>
                                    <option value="medium" <?= $animal['size'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="large"  <?= $animal['size'] === 'large'  ? 'selected' : '' ?>>Large</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-control"
                                    value="<?= htmlspecialchars($animal['color'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($animal['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Collar Status <span class="text-danger">*</span></label>
                                <select name="collar_status" class="form-select" required>
                                    <option value="green"  <?= $animal['collar_status'] === 'green'  ? 'selected' : '' ?>>🟢 Green — Ready for adoption</option>
                                    <option value="yellow" <?= $animal['collar_status'] === 'yellow' ? 'selected' : '' ?>>🟡 Yellow — Under treatment</option>
                                    <option value="red"    <?= $animal['collar_status'] === 'red'    ? 'selected' : '' ?>>🔴 Red — Injured / Critical</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Adoption Status</label>
                                <select name="adoption_status" class="form-select">
                                    <option value="available"     <?= $animal['adoption_status'] === 'available'     ? 'selected' : '' ?>>Available</option>
                                    <option value="reserved"      <?= $animal['adoption_status'] === 'reserved'      ? 'selected' : '' ?>>Reserved</option>
                                    <option value="adopted"       <?= $animal['adoption_status'] === 'adopted'       ? 'selected' : '' ?>>Adopted</option>
                                    <option value="not_available" <?= $animal['adoption_status'] === 'not_available' ? 'selected' : '' ?>>Not Available</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_vaccinated" class="form-check-input" id="vaccinated"
                                        <?= $animal['is_vaccinated'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="vaccinated">Vaccinated</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_sterilized" class="form-check-input" id="sterilized"
                                        <?= $animal['is_sterilized'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sterilized">Sterilized</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Add More Photos</label>
                            <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
                        </div>

                        <button type="submit" class="btn btn-success px-4">Save Changes</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Dynamic adoption status validation based on collar status
const collarStatusSelect = document.querySelector('select[name="collar_status"]');
const adoptionStatusSelect = document.querySelector('select[name="adoption_status"]');

function updateAdoptionOptions() {
    const isRed = collarStatusSelect.value === 'red';
    const availableOption = adoptionStatusSelect.querySelector('option[value="available"]');
    
    if (isRed) {
        availableOption.disabled = true;
        availableOption.title = "Red collar animals cannot be marked as available";
        // If "available" is currently selected, switch to "not_available"
        if (adoptionStatusSelect.value === 'available') {
            adoptionStatusSelect.value = 'not_available';
        }
    } else {
        availableOption.disabled = false;
        availableOption.title = "";
    }
}

collarStatusSelect.addEventListener('change', updateAdoptionOptions);
updateAdoptionOptions(); // Initialize on load
</script>
</body>
</html>
