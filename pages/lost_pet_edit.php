<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('lost_pets.php');
}

$post_id = (int)$_GET['id'];
$member_id = (int)$_SESSION['member_id'];
$post = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT *
    FROM lost_pet_posts
    WHERE id = $post_id AND member_id = $member_id
    LIMIT 1
"));

if (!$post) {
    redirect('lost_pets.php');
}

$error = '';
$success = '';

$format_input_datetime = static function ($datetime) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00' || $datetime === '0000-00-00') {
        return date('Y-m-d\TH:i');
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false || $timestamp <= 0) {
        return date('Y-m-d\TH:i');
    }
    return date('Y-m-d\TH:i', $timestamp);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if ($pet_name === '' || $contact_name === '' || $contact_number === '' || $city === '' || $last_seen_label === '' || $last_seen_at === '') {
        $error = "Please fill in all required fields.";
    } elseif (!in_array($species, ['dog', 'cat', 'other'], true)) {
        $error = "Please choose a valid species.";
    } elseif (!in_array($gender, ['male', 'female', 'unknown'], true)) {
        $error = "Please choose a valid gender.";
    } else {
        $poster_image = $post['poster_image'];

        if (!empty($_FILES['poster_image']['name'])) {
            $ext = pathinfo($_FILES['poster_image']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array(strtolower($ext), $allowed, true)) {
                $error = "Poster image must be JPG, PNG, or WEBP.";
            } else {
                $new_image = 'lost_pet_' . time() . '.' . strtolower($ext);
                move_uploaded_file($_FILES['poster_image']['tmp_name'], '../public/uploads/' . $new_image);

                if ($poster_image && file_exists('../public/uploads/' . $poster_image)) {
                    unlink('../public/uploads/' . $poster_image);
                }

                $poster_image = $new_image;
            }
        }

        if (!$error) {
            $needs_review = !in_array($post['status'], ['found', 'closed'], true);
            $new_status = $needs_review ? 'pending' : $post['status'];
            $new_visibility = 'hidden';
            $expires_at = date('Y-m-d H:i:s', strtotime($last_seen_at . ' +30 days'));

            $stmt = mysqli_prepare($conn, "
                UPDATE lost_pet_posts
                SET pet_name = ?, species = ?, breed = ?, gender = ?, age_text = ?, color_markings = ?, description = ?,
                    contact_name = ?, contact_number = ?, contact_email = ?, city = ?, last_seen_label = ?, last_seen_latitude = ?,
                    last_seen_longitude = ?, last_seen_at = ?, reward_amount = ?, reward_note = ?, poster_image = ?, status = ?,
                    visibility = ?, expires_at = ?
                WHERE id = ? AND member_id = ?
            ");

            mysqli_stmt_bind_param(
                $stmt,
                "ssssssssssssddsdsssssii",
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
                $new_status,
                $new_visibility,
                $expires_at,
                $post_id,
                $member_id
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_query($conn, "
                    INSERT INTO lost_pet_status_history (lost_pet_post_id, changed_by_member_id, old_status, new_status, note)
                    VALUES ($post_id, $member_id, '" . mysqli_real_escape_string($conn, $post['status']) . "', '" . mysqli_real_escape_string($conn, $new_status) . "', 'Owner edited poster details')
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
    <title>Edit Lost Pet Poster - StrayLink</title>
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
    <a href="lost_pet.php?id=<?= $post_id ?>" class="btn btn-outline-secondary btn-sm mb-4">Back to Poster</a>

    <section class="rescue-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="section-kicker mb-2">Edit poster</p>
                <h1 class="fw-bold mb-3">Update the poster details without starting over.</h1>
                <p class="text-muted mb-0">If you edit an open missing-pet case, it goes back into review before it appears on the public board again.</p>
            </div>
        </div>
    </section>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card shadow-sm rescue-form-card">
                <div class="card-body p-4 p-lg-5">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pet Name <span class="text-danger">*</span></label>
                                <input type="text" name="pet_name" class="form-control" required value="<?= htmlspecialchars($post['pet_name']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Species <span class="text-danger">*</span></label>
                                <select name="species" class="form-select" required>
                                    <option value="dog" <?= $post['species'] === 'dog' ? 'selected' : '' ?>>Dog</option>
                                    <option value="cat" <?= $post['species'] === 'cat' ? 'selected' : '' ?>>Cat</option>
                                    <option value="other" <?= $post['species'] === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Breed</label>
                                <input type="text" name="breed" class="form-control" value="<?= htmlspecialchars($post['breed']) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="unknown" <?= $post['gender'] === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                                    <option value="male" <?= $post['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= $post['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Age</label>
                                <input type="text" name="age_text" class="form-control" value="<?= htmlspecialchars($post['age_text']) ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Color / Markings</label>
                            <input type="text" name="color_markings" class="form-control" value="<?= htmlspecialchars($post['color_markings']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Replace Poster Image</label>
                            <input type="file" name="poster_image" class="form-control" accept="image/*">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" required value="<?= htmlspecialchars($post['city']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Seen Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="last_seen_at" class="form-control" required value="<?= htmlspecialchars($format_input_datetime($post['last_seen_at'])) ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Seen Location <span class="text-danger">*</span></label>
                            <input type="text" name="last_seen_label" class="form-control" required value="<?= htmlspecialchars($post['last_seen_label']) ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="last_seen_latitude" class="form-control" value="<?= htmlspecialchars((string)$post['last_seen_latitude']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="last_seen_longitude" class="form-control" value="<?= htmlspecialchars((string)$post['last_seen_longitude']) ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($post['description']) ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Contact Name <span class="text-danger">*</span></label>
                                <input type="text" name="contact_name" class="form-control" required value="<?= htmlspecialchars($post['contact_name']) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="text" name="contact_number" class="form-control" required value="<?= htmlspecialchars($post['contact_number']) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Contact Email</label>
                                <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($post['contact_email']) ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Reward Amount</label>
                                <input type="number" step="0.01" min="0" name="reward_amount" class="form-control" value="<?= htmlspecialchars((string)$post['reward_amount']) ?>">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Reward Note</label>
                                <input type="text" name="reward_note" class="form-control" value="<?= htmlspecialchars($post['reward_note']) ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">What happens after editing</h5>
                    <div class="gallery-meta-list single-column">
                        <span><i class="bi bi-shield-check"></i> Open missing-pet posters go back to review.</span>
                        <span><i class="bi bi-image"></i> You can keep the current poster image or replace it.</span>
                        <span><i class="bi bi-pencil-square"></i> Updating details is better than creating duplicates.</span>
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
