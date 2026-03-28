<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) redirect('gallery.php');
$animal_id = (int)$_GET['id'];

$animal = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT a.*, s.shelter_name, s.contact_number, s.address, s.city, s.logo,
           m.full_name AS reporter
    FROM animals a
    LEFT JOIN shelters s ON a.shelter_id = s.id
    LEFT JOIN members m  ON a.reported_by = m.id
    WHERE a.id = $animal_id AND a.is_active = 1
"));

if (!$animal) redirect('gallery.php');

// Get photos
$photos = mysqli_query($conn, "SELECT * FROM animal_photos WHERE animal_id = $animal_id");

// Get health records
$health = mysqli_query($conn, "
    SELECT h.*, m.full_name AS recorded_by_name
    FROM health_records h
    JOIN members m ON h.recorded_by = m.id
    WHERE h.animal_id = $animal_id
    ORDER BY h.performed_at DESC
");

// Handle adoption request
$request_error   = '';
$request_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adopt'])) {
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }
    $message   = sanitize($_POST['message']);
    $member_id = $_SESSION['member_id'];
    $shelter_id = $animal['shelter_id'];

    // Check if already requested
    $existing = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT id FROM adoption_requests 
        WHERE animal_id = $animal_id AND member_id = $member_id AND status = 'pending'
    "));

    if ($existing) {
        $request_error = "You already have a pending request for this animal.";
    } elseif ($animal['adoption_status'] !== 'available') {
        $request_error = "This animal is no longer available for adoption.";
    } else {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO adoption_requests (animal_id, member_id, shelter_id, message)
            VALUES (?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "iiis", $animal_id, $member_id, $shelter_id, $message);
        if (mysqli_stmt_execute($stmt)) {
            $request_success = "Your adoption request has been sent to the shelter!";
        } else {
            $request_error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($animal['name'] ?? 'Animal Profile') ?> — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<?php
    $active_page = '';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
    <a href="gallery.php" class="btn btn-outline-secondary btn-sm mb-4">← Back to Gallery</a>

    <div class="row g-4">
        <!-- Left: Photos -->
        <div class="col-md-5">
            <?php
            $photos_arr = [];
            while ($p = mysqli_fetch_assoc($photos)) $photos_arr[] = $p;
            $primary = null;
            foreach ($photos_arr as $p) { if ($p['is_primary']) { $primary = $p; break; } }
            if (!$primary && count($photos_arr) > 0) $primary = $photos_arr[0];
            ?>
            <?php if ($primary): ?>
                <img src="../public/uploads/<?= $primary['photo_path'] ?>" class="img-fluid rounded shadow mb-3" style="width:100%;height:350px;object-fit:cover;">
            <?php else: ?>
                <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" style="height:350px;">
                    <i class="bi bi-image text-muted" style="font-size:4rem;"></i>
                </div>
            <?php endif; ?>

            <!-- Thumbnail row -->
            <?php if (count($photos_arr) > 1): ?>
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($photos_arr as $p): ?>
                    <img src="../public/uploads/<?= $p['photo_path'] ?>" width="70" height="70"
                         style="object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid <?= $p['is_primary'] ? '#198754' : '#dee2e6' ?>"
                         onclick="document.querySelector('.main-photo').src=this.src">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Details -->
        <div class="col-md-7">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h2 class="fw-bold mb-0"><?= htmlspecialchars($animal['name'] ?? 'Unnamed') ?></h2>
                <span class="badge fs-6" style="background-color:<?= $animal['collar_status'] === 'green' ? '#198754' : ($animal['collar_status'] === 'yellow' ? '#ffc107' : '#dc3545') ?>">
                    <?= $animal['collar_status'] === 'green' ? '🟢 Ready' : ($animal['collar_status'] === 'yellow' ? '🟡 In Treatment' : '🔴 Critical') ?>
                </span>
            </div>

            <p class="text-muted mb-4">
                <?= ucfirst($animal['species']) ?>
                <?= $animal['breed'] ? '· ' . htmlspecialchars($animal['breed']) : '' ?>
                · <?= ucfirst($animal['gender']) ?>
                · <?= ucfirst($animal['size']) ?>
                <?php if ($animal['age_years'] || $animal['age_months']): ?>
                    · <?= $animal['age_years'] ? $animal['age_years'] . 'y ' : '' ?><?= $animal['age_months'] ? $animal['age_months'] . 'm' : '' ?>
                <?php endif; ?>
            </p>

            <!-- Info Grid -->
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="bg-light rounded p-3 text-center">
                        <small class="text-muted d-block">Vaccinated</small>
                        <strong><?= $animal['is_vaccinated'] ? '✅ Yes' : '❌ No' ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-light rounded p-3 text-center">
                        <small class="text-muted d-block">Sterilized</small>
                        <strong><?= $animal['is_sterilized'] ? '✅ Yes' : '❌ No' ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-light rounded p-3 text-center">
                        <small class="text-muted d-block">Adoption Status</small>
                        <strong><?= ucfirst($animal['adoption_status']) ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-light rounded p-3 text-center">
                        <small class="text-muted d-block">Location</small>
                        <strong><?= $animal['is_in_shelter'] ? 'In Shelter' : 'Outside / Monitored' ?></strong>
                    </div>
                </div>
            </div>

            <?php if ($animal['description']): ?>
            <div class="mb-4">
                <h6 class="fw-bold">About</h6>
                <p class="text-muted"><?= nl2br(htmlspecialchars($animal['description'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Shelter Info -->
            <?php if ($animal['is_in_shelter'] && $animal['shelter_name']): ?>
            <div class="card border-success mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-house-heart"></i> Shelter Information</h6>
                    <p class="mb-1"><strong><?= htmlspecialchars($animal['shelter_name']) ?></strong></p>
                    <?php if ($animal['address']): ?>
                        <p class="text-muted small mb-1"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($animal['address']) ?>, <?= htmlspecialchars($animal['city']) ?></p>
                    <?php endif; ?>
                    <?php if ($animal['contact_number']): ?>
                        <p class="mb-0">
                            <i class="bi bi-telephone"></i>
                            <strong><a href="tel:<?= $animal['contact_number'] ?>" class="text-success"><?= htmlspecialchars($animal['contact_number']) ?></a></strong>
                            <small class="text-muted ms-2">— Call to enquire or book a visit</small>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif (!$animal['is_in_shelter'] && $animal['location_label']): ?>
            <div class="card border-warning mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="bi bi-geo-alt"></i> Last Known Location</h6>
                    <p class="mb-0 text-muted"><?= htmlspecialchars($animal['location_label']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Adoption Request -->
            <?php if ($animal['is_in_shelter'] && $animal['adoption_status'] === 'available'): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Interested in Adopting <?= htmlspecialchars($animal['name'] ?? 'this animal') ?>?</h6>

                    <?php if ($request_error): ?>
                        <div class="alert alert-danger"><?= $request_error ?></div>
                    <?php endif; ?>
                    <?php if ($request_success): ?>
                        <div class="alert alert-success"><?= $request_success ?></div>
                    <?php endif; ?>

                    <?php if (isLoggedIn() && hasRole('user')): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Message to Shelter <small class="text-muted">(optional)</small></label>
                                <textarea name="message" class="form-control" rows="3"
                                    placeholder="Tell the shelter a bit about yourself and why you'd like to adopt..."></textarea>
                            </div>
                            <button type="submit" name="adopt" class="btn btn-success w-100">
                                <i class="bi bi-heart"></i> Send Adoption Request
                            </button>
                        </form>
                    <?php elseif (!isLoggedIn()): ?>
                        <p class="text-muted mb-3">You need to be logged in to send an adoption request.</p>
                        <a href="../auth/login.php" class="btn btn-success w-100">Login to Adopt</a>
                    <?php else: ?>
                        <p class="text-muted">Only registered users can submit adoption requests.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Health Records -->
    <?php if (mysqli_num_rows($health) > 0): ?>
    <div class="card shadow-sm mt-5">
        <div class="card-header bg-white fw-bold">Health Records</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Vet</th>
                        <th>Date</th>
                        <th>Next Due</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($h = mysqli_fetch_assoc($health)): ?>
                    <tr>
                        <td><span class="badge bg-info"><?= ucfirst($h['record_type']) ?></span></td>
                        <td><?= htmlspecialchars($h['title']) ?></td>
                        <td><small><?= htmlspecialchars(substr($h['description'] ?? '—', 0, 80)) ?></small></td>
                        <td><?= htmlspecialchars($h['vet_name'] ?? '—') ?></td>
                        <td><?= date('M d, Y', strtotime($h['performed_at'])) ?></td>
                        <td><?= $h['next_due_date'] ? date('M d, Y', strtotime($h['next_due_date'])) : '—' ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>