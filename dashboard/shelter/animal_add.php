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

    if (empty($species) || empty($collar_status)) {
        $error = "Species and collar status are required.";
    } else {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO animals 
            (shelter_id, reported_by, name, species, breed, age_years, age_months, gender, size, color, description, collar_status, is_in_shelter, adoption_status, is_vaccinated, is_sterilized)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "iisssiissssssii",
            $shelter_id, $member_id, $name, $species, $breed,
            $age_years, $age_months, $gender, $size, $color,
            $description, $collar_status, $adoption_status,
            $is_vaccinated, $is_sterilized
        );

        if (mysqli_stmt_execute($stmt)) {
            $animal_id = mysqli_insert_id($conn);

            // Handle photo uploads
            if (!empty($_FILES['photos']['name'][0])) {
                $allowed = ['jpg','jpeg','png','webp'];
                $first   = true;
                foreach ($_FILES['photos']['tmp_name'] as $key => $tmp) {
                    if ($_FILES['photos']['error'][$key] === 0) {
                        $ext = pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION);
                        if (in_array(strtolower($ext), $allowed)) {
                            $filename = 'animal_' . $animal_id . '_' . time() . '_' . $key . '.' . $ext;
                            move_uploaded_file($tmp, '../../public/uploads/' . $filename);
                            $is_primary = $first ? 1 : 0;
                            mysqli_query($conn, "INSERT INTO animal_photos (animal_id, photo_path, is_primary) VALUES ($animal_id, '$filename', $is_primary)");
                            $first = false;
                        }
                    }
                }
            }
            $success = "Animal added successfully!";
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
    <title>Add Animal — StrayLink</title>
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
                    <a class="nav-link active" href="animal_add.php"><i class="bi bi-plus-circle"></i> Add Animal</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="requests.php"><i class="bi bi-envelope"></i> Adoption Requests</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php"><i class="bi bi-gear"></i> Shelter Profile</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Add New Animal</h4>
                <a href="animals.php" class="btn btn-outline-secondary btn-sm">← Back</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?> <a href="animals.php">View all animals</a></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name <small class="text-muted">(leave blank if unnamed)</small></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Buddy">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Species <span class="text-danger">*</span></label>
                                <select name="species" class="form-select" required>
                                    <option value="dog">Dog</option>
                                    <option value="cat">Cat</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Breed</label>
                                <input type="text" name="breed" class="form-control" placeholder="e.g. Labrador">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Age (Years)</label>
                                <input type="number" name="age_years" class="form-control" value="0" min="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Age (Months)</label>
                                <input type="number" name="age_months" class="form-control" value="0" min="0" max="11">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="unknown">Unknown</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Size</label>
                                <select name="size" class="form-select">
                                    <option value="small">Small</option>
                                    <option value="medium">Medium</option>
                                    <option value="large">Large</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-control" placeholder="e.g. Brown and white">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Personality, behaviour, special needs..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Collar Status <span class="text-danger">*</span></label>
                                <select name="collar_status" class="form-select" required>
                                    <option value="green">🟢 Green — Ready for adoption</option>
                                    <option value="yellow" selected>🟡 Yellow — Under treatment</option>
                                    <option value="red">🔴 Red — Injured / Critical</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Adoption Status</label>
                                <select name="adoption_status" class="form-select">
                                    <option value="available">Available</option>
                                    <option value="reserved">Reserved</option>
                                    <option value="adopted">Adopted</option>
                                    <option value="not_available">Not Available</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_vaccinated" class="form-check-input" id="vaccinated">
                                    <label class="form-check-label" for="vaccinated">Vaccinated</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_sterilized" class="form-check-input" id="sterilized">
                                    <label class="form-check-label" for="sterilized">Sterilized</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Photos <small class="text-muted">(first photo will be the main one)</small></label>
                            <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
                        </div>

                        <button type="submit" class="btn btn-success px-4">Add Animal</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>