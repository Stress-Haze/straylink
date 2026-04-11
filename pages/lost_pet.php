<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

mysqli_query($conn, "UPDATE lost_pet_posts SET status = 'expired' WHERE status IN ('active', 'sighted') AND expires_at < NOW()");

$format_datetime = static function ($datetime, $format = 'M d, Y g:i A', $fallback = 'Not added') {
    if (!$datetime || $datetime === '0000-00-00 00:00:00' || $datetime === '0000-00-00') {
        return $fallback;
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false || $timestamp <= 0) {
        return $fallback;
    }
    return date($format, $timestamp);
};

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('lost_pets.php');
}

$post_id = (int)$_GET['id'];
$post = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, m.full_name AS owner_name, m.email AS owner_email
    FROM lost_pet_posts p
    JOIN members m ON p.member_id = m.id
    WHERE p.id = $post_id
    LIMIT 1
"));

if (!$post) {
    redirect('lost_pets.php');
}

if ($post['status'] === '' && $post['visibility'] === 'hidden') {
    $post['status'] = 'pending';
}

$is_owner = isLoggedIn() && (int)$_SESSION['member_id'] === (int)$post['member_id'];
$is_admin = isAdmin();
$can_view_hidden = $is_owner || $is_admin;

if ($post['visibility'] !== 'public' && !$can_view_hidden) {
    redirect('lost_pets.php');
}

$status_success = '';
$sighting_error = '';
$sighting_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['owner_action']) && $is_owner) {
    $action = sanitize($_POST['owner_action']);

    if ($action === 'found') {
        $reunion_note = sanitize($_POST['reunion_note']);
        mysqli_query($conn, "
            UPDATE lost_pet_posts
            SET status = 'found', found_at = NOW(), visibility = 'hidden',
                reunion_note = '" . mysqli_real_escape_string($conn, $reunion_note) . "'
            WHERE id = $post_id
        ");
        mysqli_query($conn, "
            INSERT INTO lost_pet_status_history (lost_pet_post_id, changed_by_member_id, old_status, new_status, note)
            VALUES ($post_id, {$_SESSION['member_id']}, '" . mysqli_real_escape_string($conn, $post['status']) . "', 'found', 'Owner marked pet as found')
        ");
        $status_success = "Poster updated. This case is now marked as found.";
    } elseif ($action === 'close') {
        mysqli_query($conn, "
            UPDATE lost_pet_posts
            SET status = 'closed', closed_at = NOW(), visibility = 'hidden'
            WHERE id = $post_id
        ");
        mysqli_query($conn, "
            INSERT INTO lost_pet_status_history (lost_pet_post_id, changed_by_member_id, old_status, new_status, note)
            VALUES ($post_id, {$_SESSION['member_id']}, '" . mysqli_real_escape_string($conn, $post['status']) . "', 'closed', 'Owner closed the case')
        ");
        $status_success = "Poster closed.";
    } elseif ($action === 'renew') {
        $new_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        mysqli_query($conn, "
            UPDATE lost_pet_posts
            SET status = 'pending', visibility = 'hidden', expires_at = '$new_expires', renewal_count = renewal_count + 1
            WHERE id = $post_id
        ");
        mysqli_query($conn, "
            INSERT INTO lost_pet_status_history (lost_pet_post_id, changed_by_member_id, old_status, new_status, note)
            VALUES ($post_id, {$_SESSION['member_id']}, '" . mysqli_real_escape_string($conn, $post['status']) . "', 'pending', 'Owner renewed the poster for another review cycle')
        ");
        $status_success = "Poster renewed and sent back for admin review.";
    } elseif ($action === 'delete') {
        $poster_image = $post['poster_image'];
        $sighting_photos = [];
        $photo_result = mysqli_query($conn, "SELECT photo_path FROM lost_pet_sightings WHERE lost_pet_post_id = $post_id");
        while ($photo_row = mysqli_fetch_assoc($photo_result)) {
            if (!empty($photo_row['photo_path'])) {
                $sighting_photos[] = $photo_row['photo_path'];
            }
        }

        mysqli_query($conn, "DELETE FROM lost_pet_posts WHERE id = $post_id");

        if ($poster_image && file_exists('../public/uploads/' . $poster_image)) {
            unlink('../public/uploads/' . $poster_image);
        }
        foreach ($sighting_photos as $photo_path) {
            if ($photo_path && file_exists('../public/uploads/' . $photo_path)) {
                unlink('../public/uploads/' . $photo_path);
            }
        }

        redirect('lost_pets.php');
    }

    $post = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT p.*, m.full_name AS owner_name, m.email AS owner_email
        FROM lost_pet_posts p
        JOIN members m ON p.member_id = m.id
        WHERE p.id = $post_id
        LIMIT 1
    "));

    if ($post && $post['status'] === '' && $post['visibility'] === 'hidden') {
        $post['status'] = 'pending';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sighting'])) {
    $reporter_name = sanitize($_POST['reporter_name']);
    $reporter_contact = sanitize($_POST['reporter_contact']);
    $location_label = sanitize($_POST['location_label']);
    $latitude = $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $longitude = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    $seen_at = sanitize($_POST['seen_at']);
    $notes = sanitize($_POST['notes']);
    $reported_by_member_id = isLoggedIn() ? (int)$_SESSION['member_id'] : null;

    if ($reporter_name === '' || $location_label === '' || $seen_at === '') {
        $sighting_error = "Reporter name, seen location, and seen date are required.";
    } else {
        $photo_path = null;
        if (!empty($_FILES['photo_path']['name'])) {
            $ext = pathinfo($_FILES['photo_path']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array(strtolower($ext), $allowed, true)) {
                $photo_path = 'sighting_' . $post_id . '_' . time() . '.' . strtolower($ext);
                move_uploaded_file($_FILES['photo_path']['tmp_name'], '../public/uploads/' . $photo_path);
            }
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO lost_pet_sightings
            (lost_pet_post_id, reported_by_member_id, reporter_name, reporter_contact, location_label, latitude, longitude, seen_at, notes, photo_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "iisssddsss", $post_id, $reported_by_member_id, $reporter_name, $reporter_contact, $location_label, $latitude, $longitude, $seen_at, $notes, $photo_path);

        if (mysqli_stmt_execute($stmt)) {
            if ($post['visibility'] === 'public' && in_array($post['status'], ['active', 'expired'], true)) {
                mysqli_query($conn, "UPDATE lost_pet_posts SET status = 'sighted', visibility = 'public' WHERE id = $post_id");
                mysqli_query($conn, "
                    INSERT INTO lost_pet_status_history (lost_pet_post_id, changed_by_member_id, old_status, new_status, note)
                    VALUES ($post_id, " . ($reported_by_member_id ? $reported_by_member_id : 'NULL') . ", '" . mysqli_real_escape_string($conn, $post['status']) . "', 'sighted', 'A new sighting was submitted')
                ");
            }
            $sighting_success = "Sighting submitted. Thank you for sending a lead.";
        } else {
            $sighting_error = "Something went wrong. Please try again.";
        }
    }

    $post = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT p.*, m.full_name AS owner_name, m.email AS owner_email
        FROM lost_pet_posts p
        JOIN members m ON p.member_id = m.id
        WHERE p.id = $post_id
        LIMIT 1
    "));

    if ($post && $post['status'] === '' && $post['visibility'] === 'hidden') {
        $post['status'] = 'pending';
    }
}

$sightings = [];
$sighting_result = mysqli_query($conn, "SELECT * FROM lost_pet_sightings WHERE lost_pet_post_id = $post_id ORDER BY seen_at DESC, created_at DESC");
while ($row = mysqli_fetch_assoc($sighting_result)) {
    $sightings[] = $row;
}

$status_class = $post['status'] === 'active'
    ? 'bg-danger'
    : ($post['status'] === 'sighted'
        ? 'text-bg-warning'
        : ($post['status'] === 'found'
            ? 'bg-success'
            : ($post['status'] === 'pending'
                ? 'text-bg-info'
                : ($post['status'] === 'rejected' ? 'bg-dark' : 'bg-secondary'))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['pet_name']) ?> - Lost Pet Poster</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .poster-photo-card,
        .poster-detail-card,
        .poster-description-card {
            border: 0;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(58, 39, 20, 0.08);
        }

        .poster-photo-card img {
            width: 100%;
            max-height: 560px;
            object-fit: cover;
            background: #f7f1eb;
        }

        .poster-detail-card .card-header,
        .poster-description-card .card-header {
            background: #fff8f1;
            border-bottom: 1px solid rgba(196, 126, 63, 0.12);
            font-weight: 700;
        }

        .poster-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .poster-detail-item {
            background: #fffaf5;
            border: 1px solid rgba(196, 126, 63, 0.14);
            border-radius: 16px;
            padding: 1rem;
        }

        .poster-detail-item small {
            display: block;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9b8976;
            margin-bottom: 0.35rem;
        }

        .poster-detail-item strong {
            display: block;
            color: #2f241b;
            line-height: 1.45;
        }

        .owner-action-grid {
            display: grid;
            gap: 0.85rem;
        }

        @media (max-width: 767.98px) {
            .poster-detail-grid {
                grid-template-columns: 1fr;
            }
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
    <a href="lost_pets.php" class="btn btn-outline-secondary btn-sm mb-4">Back to Posters</a>

    <?php if ($status_success): ?><div class="alert alert-success"><?= $status_success ?></div><?php endif; ?>
    <?php if ($post['status'] === 'pending' && $can_view_hidden): ?>
        <div class="alert alert-info">This poster is waiting for admin approval, so it is still hidden from the public board.</div>
    <?php elseif ($post['status'] === 'rejected' && $can_view_hidden): ?>
        <div class="alert alert-warning">This poster was not approved for the public board.</div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <div class="col-lg-7">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge <?= $status_class ?>"><?= ucfirst($post['status']) ?></span>
                <span class="badge text-bg-light border"><?= ucfirst($post['species']) ?></span>
                <?php if ($post['reward_amount'] !== null && (float)$post['reward_amount'] > 0): ?>
                    <span class="badge bg-success">Reward Offered</span>
                <?php endif; ?>
            </div>

            <div class="card poster-photo-card mb-4">
                <img src="../public/uploads/<?= htmlspecialchars($post['poster_image']) ?>" class="img-fluid animal-main-photo" alt="Poster for <?= htmlspecialchars($post['pet_name']) ?>">
            </div>

            <div class="card poster-detail-card mb-4">
                <div class="card-header">Pet Details</div>
                <div class="card-body">
                    <div class="poster-detail-grid">
                        <div class="poster-detail-item">
                            <small>Last sighted</small>
                            <strong><?= htmlspecialchars($format_datetime($post['last_seen_at'])) ?></strong>
                        </div>
                        <div class="poster-detail-item">
                            <small>Last seen area</small>
                            <strong><?= htmlspecialchars($post['last_seen_label']) ?></strong>
                        </div>
                        <div class="poster-detail-item">
                            <small>City</small>
                            <strong><?= htmlspecialchars($post['city']) ?></strong>
                        </div>
                        <div class="poster-detail-item">
                            <small>Status</small>
                            <strong><?= ucfirst($post['status']) ?></strong>
                        </div>
                        <div class="poster-detail-item">
                            <small>Type</small>
                            <strong><?= htmlspecialchars($post['breed'] ?: ucfirst($post['species'])) ?></strong>
                        </div>
                        <div class="poster-detail-item">
                            <small>Gender / age</small>
                            <strong><?= ucfirst($post['gender']) ?><?= !empty($post['age_text']) ? ' | ' . htmlspecialchars($post['age_text']) : '' ?></strong>
                        </div>
                        <div class="poster-detail-item">
                            <small>Color / markings</small>
                            <strong><?= htmlspecialchars($post['color_markings'] ?: 'Not added') ?></strong>
                        </div>
                        <div class="poster-detail-item">
                            <small>Reward</small>
                            <strong>
                                <?= $post['reward_amount'] !== null && (float)$post['reward_amount'] > 0 ? 'NPR ' . number_format((float)$post['reward_amount'], 2) : 'None' ?>
                                <?= $post['reward_note'] ? '<br><span class="text-muted fw-normal">' . htmlspecialchars($post['reward_note']) . '</span>' : '' ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card poster-description-card mb-4">
                <div class="card-header">Description</div>
                <div class="card-body">
                    <p class="mb-0 text-muted"><?= nl2br(htmlspecialchars($post['description'] ?: 'No extra description was added for this poster.')) ?></p>
                    <?php if ($post['status'] === 'found' && !empty($post['reunion_note'])): ?>
                        <hr>
                        <p class="mb-0"><strong>Reunion note:</strong> <?= htmlspecialchars($post['reunion_note']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-bold">Sightings & Leads</div>
                <div class="card-body">
                    <?php if (count($sightings) === 0): ?>
                        <p class="text-muted mb-0">No sightings have been submitted yet.</p>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php foreach ($sightings as $sighting): ?>
                                <div class="mobile-request-card">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                        <strong><?= htmlspecialchars($sighting['reporter_name']) ?></strong>
                                        <span class="badge text-bg-light border"><?= htmlspecialchars($format_datetime($sighting['seen_at'], 'M d, Y', 'Unknown date')) ?></span>
                                    </div>
                                    <div class="small text-muted mb-2">Seen near <?= htmlspecialchars($sighting['location_label']) ?></div>
                                    <?php if (!empty($sighting['notes'])): ?><p class="text-muted small mb-2"><?= htmlspecialchars($sighting['notes']) ?></p><?php endif; ?>
                                    <?php if (!empty($sighting['reporter_contact'])): ?><div class="small text-muted">Contact: <?= htmlspecialchars($sighting['reporter_contact']) ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="animal-side-stack">
                <div class="card shadow-sm animal-action-card">
                    <div class="card-body">
                        <p class="section-kicker mb-2">Contact now</p>
                        <h4 class="fw-bold mb-2"><?= htmlspecialchars($post['contact_name']) ?></h4>
                        <div class="gallery-meta-list single-column mb-3">
                            <span><i class="bi bi-telephone"></i> <a href="tel:<?= htmlspecialchars($post['contact_number']) ?>"><?= htmlspecialchars($post['contact_number']) ?></a></span>
                            <?php if (!empty($post['contact_email'])): ?><span><i class="bi bi-envelope"></i> <a href="mailto:<?= htmlspecialchars($post['contact_email']) ?>"><?= htmlspecialchars($post['contact_email']) ?></a></span><?php endif; ?>
                            <span><i class="bi bi-person"></i> Poster owner: <?= htmlspecialchars($post['owner_name']) ?></span>
                            <span><i class="bi bi-clock-history"></i> Poster expires: <?= htmlspecialchars($format_datetime($post['expires_at'], 'M d, Y', 'TBD')) ?></span>
                        </div>

                        <?php if ($sighting_error): ?><div class="alert alert-danger"><?= $sighting_error ?></div><?php endif; ?>
                        <?php if ($sighting_success): ?><div class="alert alert-success"><?= $sighting_success ?></div><?php endif; ?>

                        <?php if ($post['visibility'] === 'public' && !in_array($post['status'], ['found', 'closed'], true)): ?>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3"><label class="form-label">Your Name</label><input type="text" name="reporter_name" class="form-control" required value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>"></div>
                                <div class="mb-3"><label class="form-label">Contact Info</label><input type="text" name="reporter_contact" class="form-control" placeholder="Phone or email"></div>
                                <div class="mb-3"><label class="form-label">Seen Location</label><input type="text" name="location_label" class="form-control" required placeholder="Where did you see the pet?"></div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">Latitude</label><input type="text" name="latitude" class="form-control"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">Longitude</label><input type="text" name="longitude" class="form-control"></div>
                                </div>
                                <div class="mb-3"><label class="form-label">Seen Date & Time</label><input type="datetime-local" name="seen_at" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>"></div>
                                <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3" placeholder="Behavior, direction, condition, or anything useful..."></textarea></div>
                                <div class="mb-3"><label class="form-label">Photo</label><input type="file" name="photo_path" class="form-control" accept="image/*"></div>
                                <button type="submit" name="submit_sighting" class="btn btn-success w-100"><i class="bi bi-binoculars"></i> Submit Sighting</button>
                            </form>
                        <?php else: ?>
                            <p class="text-muted mb-0"><?= $post['visibility'] !== 'public' ? 'Sightings open after admin approval.' : 'This case is no longer accepting new sightings.' ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_owner): ?>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Owner Actions</h5>
                            <div class="owner-action-grid">
                                <a href="lost_pet_edit.php?id=<?= (int)$post['id'] ?>" class="btn btn-outline-primary w-100">Edit Poster</a>

                            <?php if (in_array($post['status'], ['pending', 'active', 'sighted', 'expired'], true)): ?>
                                <form method="POST">
                                    <input type="hidden" name="owner_action" value="found">
                                    <label class="form-label">Reunion Note</label>
                                    <textarea name="reunion_note" class="form-control mb-3" rows="3" placeholder="Optional note about where and how your pet was found"></textarea>
                                    <button type="submit" class="btn btn-success w-100">Mark Pet as Found</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($post['status'] === 'expired'): ?>
                                <form method="POST">
                                    <input type="hidden" name="owner_action" value="renew">
                                    <button type="submit" class="btn btn-outline-success w-100">Renew Poster for Review</button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($post['status'], ['pending', 'active', 'sighted', 'expired'], true)): ?>
                                <form method="POST">
                                    <input type="hidden" name="owner_action" value="close">
                                    <button type="submit" class="btn btn-outline-secondary w-100">Close Case</button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" onsubmit="return confirm('Delete this poster permanently?');">
                                <input type="hidden" name="owner_action" value="delete">
                                <button type="submit" class="btn btn-outline-danger w-100">Delete Poster</button>
                            </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
