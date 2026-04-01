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
    LEFT JOIN members m ON a.reported_by = m.id
    WHERE a.id = $animal_id AND a.is_active = 1
"));

if (!$animal) redirect('gallery.php');

$photos = mysqli_query($conn, "SELECT * FROM animal_photos WHERE animal_id = $animal_id ORDER BY is_primary DESC, id ASC");
$health = mysqli_query($conn, "
    SELECT h.*, m.full_name AS recorded_by_name
    FROM health_records h
    JOIN members m ON h.recorded_by = m.id
    WHERE h.animal_id = $animal_id
    ORDER BY h.performed_at DESC
");

$request_error = '';
$request_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adopt'])) {
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }

    $message = sanitize($_POST['message']);
    $member_id = $_SESSION['member_id'];
    $shelter_id = $animal['shelter_id'];

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

$photos_arr = [];
while ($p = mysqli_fetch_assoc($photos)) {
    $photos_arr[] = $p;
}
$primary = $photos_arr[0] ?? null;

$health_items = [];
while ($record = mysqli_fetch_assoc($health)) {
    $health_items[] = $record;
}

$collar_class = $animal['collar_status'] === 'green' ? 'bg-success' : ($animal['collar_status'] === 'yellow' ? 'text-bg-warning' : 'bg-danger');
$collar_label = $animal['collar_status'] === 'green' ? 'Ready for adoption' : ($animal['collar_status'] === 'yellow' ? 'In treatment' : 'Critical care');
$status_class = $animal['adoption_status'] === 'available' ? 'bg-success' : ($animal['adoption_status'] === 'reserved' ? 'text-bg-warning' : 'bg-secondary');
$availability_note = $animal['adoption_status'] === 'available' ? 'Available to request now' : ucfirst(str_replace('_', ' ', $animal['adoption_status']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($animal['name'] ?? 'Animal Profile') ?> - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php
    $active_page = '';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
    <a href="gallery.php" class="btn btn-outline-secondary btn-sm mb-4">Back to Gallery</a>

    <section class="animal-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge <?= $collar_class ?>"><?= $collar_label ?></span>
                    <span class="badge <?= $status_class ?>"><?= ucfirst(str_replace('_', ' ', $animal['adoption_status'])) ?></span>
                    <span class="badge text-bg-light border"><?= $animal['is_in_shelter'] ? 'In shelter care' : 'Outside monitored' ?></span>
                </div>
                <h1 class="fw-bold mb-2"><?= htmlspecialchars($animal['name'] ?? 'Unnamed') ?></h1>
                <p class="text-muted mb-3 animal-subtitle">
                    <?= ucfirst($animal['species']) ?>
                    <?= !empty($animal['breed']) ? ' · ' . htmlspecialchars($animal['breed']) : '' ?>
                    · <?= ucfirst($animal['gender']) ?>
                    · <?= ucfirst($animal['size']) ?>
                    <?php if ($animal['age_years'] || $animal['age_months']): ?>
                        · <?= $animal['age_years'] ? (int)$animal['age_years'] . 'y ' : '' ?><?= $animal['age_months'] ? (int)$animal['age_months'] . 'm' : '' ?>
                    <?php endif; ?>
                </p>
                <p class="text-muted mb-0">A clearer profile screen helps users understand care status quickly and decide whether to contact, adopt, or keep monitoring.</p>
            </div>
            <div class="col-lg-4">
                <div class="animal-summary-panel">
                    <div class="summary-stat compact">
                        <strong><?= count($health_items) ?></strong>
                        <span>Health records</span>
                    </div>
                    <div class="summary-stat compact">
                        <strong><?= $animal['is_vaccinated'] ? 'Yes' : 'No' ?></strong>
                        <span>Vaccinated</span>
                    </div>
                    <div class="summary-stat compact">
                        <strong><?= $animal['is_sterilized'] ? 'Yes' : 'No' ?></strong>
                        <span>Sterilized</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4 align-items-start">
        <div class="col-lg-7">
            <div class="card shadow-sm overflow-hidden mb-4">
                <?php if ($primary): ?>
                    <img src="../public/uploads/<?= htmlspecialchars($primary['photo_path']) ?>" class="img-fluid main-photo animal-main-photo" alt="<?= htmlspecialchars($animal['name'] ?? 'Animal photo') ?>">
                <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center animal-main-photo">
                        <i class="bi bi-image text-muted" style="font-size:4rem;"></i>
                    </div>
                <?php endif; ?>
                <?php if (count($photos_arr) > 1): ?>
                <div class="animal-thumb-strip">
                    <?php foreach ($photos_arr as $p): ?>
                        <button type="button" class="animal-thumb-btn" onclick="document.querySelector('.main-photo').src='<?= '../public/uploads/' . htmlspecialchars($p['photo_path'], ENT_QUOTES) ?>'">
                            <img src="../public/uploads/<?= htmlspecialchars($p['photo_path']) ?>" alt="Thumbnail for <?= htmlspecialchars($animal['name'] ?? 'animal') ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="animal-info-tile">
                        <small>Vaccinated</small>
                        <strong><?= $animal['is_vaccinated'] ? 'Yes' : 'No' ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="animal-info-tile">
                        <small>Sterilized</small>
                        <strong><?= $animal['is_sterilized'] ? 'Yes' : 'No' ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="animal-info-tile">
                        <small>Adoption</small>
                        <strong><?= ucfirst(str_replace('_', ' ', $animal['adoption_status'])) ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="animal-info-tile">
                        <small>Location</small>
                        <strong><?= $animal['is_in_shelter'] ? 'Shelter' : 'Outside' ?></strong>
                    </div>
                </div>
            </div>

            <?php if ($animal['description']): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold">About <?= htmlspecialchars($animal['name'] ?? 'This Animal') ?></div>
                <div class="card-body">
                    <p class="text-muted"><?= nl2br(htmlspecialchars($animal['description'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($animal['is_in_shelter'] && $animal['shelter_name']): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-house-heart text-success"></i> Shelter Information</div>
                <div class="card-body">
                    <h5 class="fw-bold mb-2"><?= htmlspecialchars($animal['shelter_name']) ?></h5>
                    <?php if ($animal['address'] || $animal['city']): ?>
                        <p class="text-muted mb-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars(trim(($animal['address'] ?? '') . ', ' . ($animal['city'] ?? ''), ', ')) ?></p>
                    <?php endif; ?>
                    <?php if ($animal['contact_number']): ?>
                        <p class="mb-0"><i class="bi bi-telephone"></i> <a href="tel:<?= htmlspecialchars($animal['contact_number']) ?>" class="text-success fw-bold"><?= htmlspecialchars($animal['contact_number']) ?></a></p>
                    <?php endif; ?>
                    <div class="mt-3">
                        <a href="donate.php?shelter_id=<?= (int)$animal['shelter_id'] ?>" class="btn btn-outline-success">Support This Shelter</a>
                    </div>
                </div>
            </div>
            <?php elseif (!$animal['is_in_shelter'] && $animal['location_label']): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold"><i class="bi bi-geo-alt text-success"></i> Last Known Location</div>
                <div class="card-body">
                    <p class="text-muted mb-0"><?= htmlspecialchars($animal['location_label']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($health_items) > 0): ?>
            <div class="card shadow-sm">
                <div class="card-header fw-bold">Health Records</div>
                <div class="card-body p-0">
                    <div class="d-none d-md-block">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Vet</th>
                                    <th>Date</th>
                                    <th>Next Due</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($health_items as $h): ?>
                                <tr>
                                    <td><span class="badge text-bg-info"><?= ucfirst($h['record_type']) ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($h['title']) ?></strong>
                                        <?php if (!empty($h['description'])): ?>
                                            <div><small class="text-muted"><?= htmlspecialchars(substr($h['description'], 0, 90)) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($h['vet_name'] ?? '-') ?></td>
                                    <td><?= date('M d, Y', strtotime($h['performed_at'])) ?></td>
                                    <td><?= $h['next_due_date'] ? date('M d, Y', strtotime($h['next_due_date'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-md-none p-3">
                        <?php foreach ($health_items as $h): ?>
                            <div class="mobile-request-card">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                    <strong><?= htmlspecialchars($h['title']) ?></strong>
                                    <span class="badge text-bg-info"><?= ucfirst($h['record_type']) ?></span>
                                </div>
                                <?php if (!empty($h['description'])): ?>
                                    <p class="text-muted small mb-2"><?= htmlspecialchars(substr($h['description'], 0, 120)) ?></p>
                                <?php endif; ?>
                                <div class="small text-muted">Performed: <?= date('M d, Y', strtotime($h['performed_at'])) ?></div>
                                <div class="small text-muted">Next due: <?= $h['next_due_date'] ? date('M d, Y', strtotime($h['next_due_date'])) : '-' ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">
            <div class="animal-side-stack">
                <div class="card shadow-sm animal-action-card">
                    <div class="card-body">
                        <p class="section-kicker mb-2">Adoption readiness</p>
                        <h4 class="fw-bold mb-2"><?= $availability_note ?></h4>
                        <p class="text-muted mb-3">Use this panel as the main next-action area on mobile and desktop.</p>

                        <?php if ($request_error): ?>
                            <div class="alert alert-danger"><?= $request_error ?></div>
                        <?php endif; ?>
                        <?php if ($request_success): ?>
                            <div class="alert alert-success"><?= $request_success ?></div>
                        <?php endif; ?>

                        <?php if ($animal['is_in_shelter'] && $animal['adoption_status'] === 'available'): ?>
                            <?php if (isLoggedIn() && hasRole('user')): ?>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Message to Shelter <small class="text-muted">(optional)</small></label>
                                        <textarea name="message" class="form-control" rows="4" placeholder="Tell the shelter a bit about yourself and why you'd like to adopt..."></textarea>
                                    </div>
                                    <button type="submit" name="adopt" class="btn btn-success w-100">
                                        <i class="bi bi-heart"></i> Send Adoption Request
                                    </button>
                                </form>
                            <?php elseif (!isLoggedIn()): ?>
                                <p class="text-muted mb-3">You need to be logged in to send an adoption request.</p>
                                <a href="../auth/login.php" class="btn btn-success w-100">Login to Adopt</a>
                            <?php else: ?>
                                <p class="text-muted mb-0">Only registered users can submit adoption requests.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0">This animal is not currently open for new adoption requests.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Quick Facts</h5>
                        <div class="gallery-meta-list single-column">
                            <span><i class="bi bi-shield-check"></i> Collar status: <?= $collar_label ?></span>
                            <span><i class="bi bi-clipboard-heart"></i> Adoption status: <?= ucfirst(str_replace('_', ' ', $animal['adoption_status'])) ?></span>
                            <?php if (!empty($animal['reporter'])): ?>
                                <span><i class="bi bi-person"></i> Reported by: <?= htmlspecialchars($animal['reporter']) ?></span>
                            <?php endif; ?>
                            <?php if ($animal['is_in_shelter'] && !empty($animal['shelter_name'])): ?>
                                <span><i class="bi bi-house"></i> Shelter: <?= htmlspecialchars($animal['shelter_name']) ?></span>
                            <?php elseif (!$animal['is_in_shelter'] && !empty($animal['location_label'])): ?>
                                <span><i class="bi bi-pin-map"></i> Area: <?= htmlspecialchars($animal['location_label']) ?></span>
                            <?php endif; ?>
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
