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
    $bg_color = sanitize($_POST['bg_color'] ?? $post['bg_color'] ?? '#faf7f2');
    $bg_pattern = sanitize($_POST['bg_pattern'] ?? $post['bg_pattern'] ?? 'none');

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

        // Handle side images
        $side_images = $post['side_images'] ? json_decode($post['side_images'], true) : [];
        for ($i = 1; $i <= 6; $i++) {
            if (!empty($_FILES["side_image_$i"]['name'])) {
                $ext = pathinfo($_FILES["side_image_$i"]['name'], PATHINFO_EXTENSION);
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array(strtolower($ext), $allowed, true)) {
                    $img_name = 'lost_pet_side_' . $i . '_' . time() . '.' . strtolower($ext);
                    move_uploaded_file($_FILES["side_image_$i"]['tmp_name'], '../public/uploads/' . $img_name);
                    $side_images[$i] = $img_name;
                }
            }
        }
        $side_images_json = json_encode($side_images);

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
                    visibility = ?, expires_at = ?, bg_color = ?, bg_pattern = ?, side_images = ?
                WHERE id = ? AND member_id = ?
            ");

            mysqli_stmt_bind_param(
                $stmt,
                "ssssssssssssddsdsssssssii",
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
                $bg_color,
                $bg_pattern,
                $side_images_json,
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
    <style>
        .edit-form-card {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .form-section-header {
            background: white;
            padding: 1.25rem;
            border-bottom: 1px solid #e9ecef;
            font-weight: 700;
            color: #2c3e50;
        }

        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>

<?php
    $active_page = 'lost_pets';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Edit Lost Pet Poster</h2>
        <a href="lost_pet.php?id=<?= $post_id ?>" class="btn btn-outline-secondary btn-sm">← Back to Poster</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="card edit-form-card">
        <form method="POST" enctype="multipart/form-data">
            <!-- Pet Details -->
            <div class="form-section-header">Pet Details</div>
            <div class="card-body">
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
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Breed</label>
                        <input type="text" name="breed" class="form-control" value="<?= htmlspecialchars($post['breed']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="unknown" <?= $post['gender'] === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                            <option value="male" <?= $post['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $post['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Age</label>
                        <input type="text" name="age_text" class="form-control" value="<?= htmlspecialchars($post['age_text']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Color / Markings</label>
                        <input type="text" name="color_markings" class="form-control" value="<?= htmlspecialchars($post['color_markings']) ?>">
                    </div>
                </div>
            </div>

            <!-- Location & Time -->
            <div class="form-section-header">Location & Last Seen</div>
            <div class="card-body">
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
            </div>

            <!-- Description -->
            <div class="form-section-header">Description</div>
            <div class="card-body">
                <textarea name="description" class="form-control" rows="5" placeholder="Describe your pet, behavior, distinctive features..."><?= htmlspecialchars($post['description']) ?></textarea>
            </div>

            <!-- Contact Information -->
            <div class="form-section-header">Contact Information</div>
            <div class="card-body">
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
            </div>

            <!-- Media -->
            <div class="form-section-header">Poster Image</div>
            <div class="card-body">
                <label class="form-label">Main Poster Image</label>
                <input type="file" name="poster_image" class="form-control" accept="image/*">
                <small class="text-muted">Leave blank to keep current image</small>
                <?php if ($post['poster_image']): ?>
                    <div class="mt-2">
                        <small class="text-muted">Current image:</small>
                        <img src="../public/uploads/<?= htmlspecialchars($post['poster_image']) ?>" class="image-preview" alt="Current poster">
                    </div>
                <?php endif; ?>
            </div>

            <!-- Reward -->
            <div class="form-section-header">Reward Information</div>
            <div class="card-body">
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
            </div>

            <!-- Submit -->
            <div class="card-body bg-light border-top">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-save me-2"></i> Save Changes
                </button>
                <a href="lost_pet.php?id=<?= $post_id ?>" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Handle image uploads with preview
    document.querySelectorAll('.image-upload-box').forEach(box => {
        const input = box.querySelector('input[type="file"]');
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    let img = box.querySelector('img');
                    if (!img) {
                        img = document.createElement('img');
                        box.appendChild(img);
                    }
                    img.src = evt.target.result;
                    box.querySelector('.image-upload-label').style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
    });
</script>
</body>
</html>
