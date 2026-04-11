<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = (int)$_SESSION['member_id'];
    $pet_name = sanitize($_POST['pet_name']);
    $species = sanitize($_POST['species']);
    $breed = sanitize($_POST['breed']);
    $gender = sanitize($_POST['gender']);
    $age_text = sanitize($_POST['age_text']);
    $color_markings = sanitize($_POST['color_markings']);
    $description = sanitize($_POST['description']);
    $contact_name = sanitize($_POST['contact_name']);
    $contact_number = sanitize($_POST['contact_number']);
    $contact_email = sanitize($_POST['contact_email']);
    $city = sanitize($_POST['city']);
    $last_seen_label = sanitize($_POST['last_seen_label']);
    $last_seen_latitude = $_POST['last_seen_latitude'] !== '' ? (float)$_POST['last_seen_latitude'] : null;
    $last_seen_longitude = $_POST['last_seen_longitude'] !== '' ? (float)$_POST['last_seen_longitude'] : null;
    $last_seen_at = sanitize($_POST['last_seen_at']);
    $reward_amount = $_POST['reward_amount'] !== '' ? (float)$_POST['reward_amount'] : null;
    $reward_note = sanitize($_POST['reward_note']);

    if ($pet_name === '' || $contact_name === '' || $contact_number === '' || $city === '' || $last_seen_label === '' || $last_seen_at === '' || empty($_FILES['poster_image']['name'])) {
        $error = "Please fill in all required fields and add a poster image.";
    } elseif (!in_array($species, ['dog', 'cat', 'other'], true)) {
        $error = "Please choose a valid species.";
    } elseif (!in_array($gender, ['male', 'female', 'unknown'], true)) {
        $error = "Please choose a valid gender.";
    } else {
        $poster_image = null;
        $ext = pathinfo($_FILES['poster_image']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array(strtolower($ext), $allowed, true)) {
            $poster_image = 'lost_pet_' . time() . '.' . strtolower($ext);
            move_uploaded_file($_FILES['poster_image']['tmp_name'], '../public/uploads/' . $poster_image);
        } else {
            $error = "Poster image must be JPG, PNG, or WEBP.";
        }

        if (!$error) {
            $expires_at = date('Y-m-d H:i:s', strtotime($last_seen_at . ' +30 days'));
            $stmt = mysqli_prepare($conn, "
                INSERT INTO lost_pet_posts
                (member_id, pet_name, species, breed, gender, age_text, color_markings, description,
                 contact_name, contact_number, contact_email, city, last_seen_label, last_seen_latitude,
                 last_seen_longitude, last_seen_at, reward_amount, reward_note, poster_image, status, visibility, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'hidden', ?)
            ");

            mysqli_stmt_bind_param(
                $stmt,
                "issssssssssssddsdsss",
                $member_id,
                $pet_name,
                $species,
                $breed,
                $gender,
                $age_text,
                $color_markings,
                $description,
                $contact_name,
                $contact_number,
                $contact_email,
                $city,
                $last_seen_label,
                $last_seen_latitude,
                $last_seen_longitude,
                $last_seen_at,
                $reward_amount,
                $reward_note,
                $poster_image,
                $expires_at
            );

            if (mysqli_stmt_execute($stmt)) {
                $post_id = mysqli_insert_id($conn);
                mysqli_query($conn, "
                    INSERT INTO lost_pet_status_history (lost_pet_post_id, changed_by_member_id, old_status, new_status, note)
                    VALUES ($post_id, $member_id, NULL, 'pending', 'Poster submitted for admin review')
                ");
                redirect('lost_pet.php?id=' . $post_id);
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Lost Pet Poster - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php
    $active_page = 'lost_pets';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
    <section class="rescue-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="section-kicker mb-2">Create a poster</p>
                <h1 class="fw-bold mb-3">Put up a clear lost-pet poster with the details people need most.</h1>
                <p class="text-muted mb-4">A strong poster needs a recent photo, the last seen area, a contact number, and anything distinctive that helps someone recognize your pet quickly.</p>
            </div>
            <div class="col-lg-5">
                <div class="gallery-summary-card">
                    <div>
                        <span class="summary-label">Review first</span>
                        <strong>New posters stay private until an admin approves them for the public board.</strong>
                    </div>
                    <div>
                        <span class="summary-label">After recovery</span>
                        <strong>Mark the case as found so it leaves the active board and stops circulating as missing.</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card shadow-sm rescue-form-card">
                <div class="card-body p-4 p-lg-5">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pet Name <span class="text-danger">*</span></label>
                                <input type="text" name="pet_name" class="form-control" required>
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
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Breed</label>
                                <input type="text" name="breed" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="unknown">Unknown</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Age</label>
                                <input type="text" name="age_text" class="form-control" placeholder="e.g. 2 years">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Color / Markings</label>
                            <input type="text" name="color_markings" class="form-control" placeholder="e.g. White paws, black collar spot">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Poster Image <span class="text-danger">*</span></label>
                            <input type="file" name="poster_image" class="form-control" accept="image/*" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Seen Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="last_seen_at" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Seen Location <span class="text-danger">*</span></label>
                            <input type="text" name="last_seen_label" class="form-control" required placeholder="e.g. Near Lakeside Gate 3, Pokhara">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="last_seen_latitude" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="last_seen_longitude" class="form-control" placeholder="Optional">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Temperament, collar, identifying traits, or anything people should know..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Contact Name <span class="text-danger">*</span></label>
                                <input type="text" name="contact_name" class="form-control" required value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="text" name="contact_number" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Contact Email</label>
                                <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Reward Amount</label>
                                <input type="number" step="0.01" min="0" name="reward_amount" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Reward Note</label>
                                <input type="text" name="reward_note" class="form-control" placeholder="Optional note about the reward">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            <i class="bi bi-megaphone"></i> Submit Poster for Review
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="rescue-side-stack">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">What helps most</h5>
                        <div class="gallery-meta-list single-column">
                            <span><i class="bi bi-check2-circle"></i> Use a clear recent photo where the pet is easy to recognize.</span>
                            <span><i class="bi bi-check2-circle"></i> Add landmarks, road names, or neighborhood details in the last seen area.</span>
                            <span><i class="bi bi-check2-circle"></i> Mention collars, scars, patterns, or behavior that could help someone identify your pet.</span>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Case rules</h5>
                        <div class="gallery-meta-list single-column">
                            <span><i class="bi bi-shield-check"></i> Every new poster is checked before it goes public.</span>
                            <span><i class="bi bi-clock-history"></i> Approved posters stay active for 30 days before expiry.</span>
                            <span><i class="bi bi-arrow-repeat"></i> You can renew the case if your pet is still missing.</span>
                            <span><i class="bi bi-heart"></i> Once your pet is back home, mark the poster as found.</span>
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
