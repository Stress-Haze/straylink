<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$species = isset($_GET['species']) ? sanitize($_GET['species']) : '';
$collar = isset($_GET['collar']) ? sanitize($_GET['collar']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';

$where = "WHERE a.is_active = 1";

if ($species && in_array($species, ['dog', 'cat', 'other'])) {
    $where .= " AND a.species = '$species'";
}
if ($collar && in_array($collar, ['green', 'yellow', 'red'])) {
    $where .= " AND a.collar_status = '$collar'";
}
if ($status && in_array($status, ['available', 'reserved', 'adopted', 'not_available'])) {
    $where .= " AND a.adoption_status = '$status'";
}
if ($type === 'shelter') {
    $where .= " AND a.is_in_shelter = 1";
}
if ($type === 'outside') {
    $where .= " AND a.is_in_shelter = 0";
}
if ($search) {
    $where .= " AND (a.name LIKE '%$search%' OR a.breed LIKE '%$search%' OR a.location_label LIKE '%$search%' OR s.city LIKE '%$search%' OR s.address LIKE '%$search%' OR s.shelter_name LIKE '%$search%')";
}

$animals = mysqli_query($conn, "
    SELECT a.*, s.shelter_name,
        (SELECT photo_path FROM animal_photos WHERE animal_id = a.id AND is_primary = 1 LIMIT 1) AS photo
    FROM animals a
    LEFT JOIN shelters s ON a.shelter_id = s.id
    $where
    ORDER BY a.created_at DESC
");

$map_animals = mysqli_query($conn, "
    SELECT a.id, a.name, a.species, a.collar_status, a.is_in_shelter,
           a.location_label, a.latitude, a.longitude, s.shelter_name
    FROM animals a
    LEFT JOIN shelters s ON a.shelter_id = s.id
    WHERE a.is_active = 1 AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL
");

$map_data = [];
while ($m = mysqli_fetch_assoc($map_animals)) {
    $map_data[] = $m;
}

$animals_list = [];
while ($a = mysqli_fetch_assoc($animals)) {
    $animals_list[] = $a;
}

$total_results = count($animals_list);
$available_count = 0;
$shelter_count = 0;
foreach ($animals_list as $item) {
    if ($item['adoption_status'] === 'available') {
        $available_count++;
    }
    if ((int)$item['is_in_shelter'] === 1) {
        $shelter_count++;
    }
}

$active_filters = [];
if ($search) $active_filters[] = 'Search: ' . $search;
if ($species) $active_filters[] = 'Species: ' . ucfirst($species);
if ($collar) $active_filters[] = 'Collar: ' . ucfirst($collar);
if ($status) $active_filters[] = 'Status: ' . ucfirst(str_replace('_', ' ', $status));
if ($type) $active_filters[] = $type === 'shelter' ? 'In shelter' : 'Outside monitored';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Gallery - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php
    $active_page = 'gallery';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
    <section class="gallery-hero mb-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="section-kicker mb-3">
                    <i class="bi bi-heart-fill text-success me-2"></i>Adoption Gallery
                </p>
                <h1 class="fw-bold mb-3 display-5">Find Your Perfect Companion</h1>
                <p class="text-muted mb-4 fs-5">Browse loving animals waiting for their forever home. Every life matters, and your choice can change everything.</p>
                <div class="gallery-chip-row">
                    <span class="gallery-chip"><i class="bi bi-grid-3x3-gap me-1"></i><strong><?= $total_results ?></strong> animals</span>
                    <span class="gallery-chip"><i class="bi bi-check-circle me-1"></i><strong><?= $available_count ?></strong> available</span>
                    <span class="gallery-chip"><i class="bi bi-house-heart me-1"></i><strong><?= $shelter_count ?></strong> in shelter</span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="gallery-summary-card">
                    <div>
                        <span class="summary-label"><i class="bi bi-stars me-1"></i>Best for Adopters</span>
                        <strong>Use filters to narrow by species, collar status, and readiness.</strong>
                    </div>
                    <div>
                        <span class="summary-label"><i class="bi bi-phone me-1"></i>Quick Mobile Access</span>
                        <strong>Open profiles directly from card views without hunting for details.</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <form method="GET" class="card shadow p-4 mb-5 filter-surface" style="border: 2px solid var(--green-light); background: linear-gradient(135deg, #ffffff 0%, #f8faf6 100%);">
        <div class="row g-3 align-items-end">
            <div class="col-lg-2 col-md-6">
                <label class="form-label small fw-bold text-success"><i class="bi bi-search me-1"></i>Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, breed, or location" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small fw-bold text-success"><i class="bi bi-paw me-1"></i>Species</label>
                <select name="species" class="form-select">
                    <option value="">All Species</option>
                    <option value="dog" <?= $species === 'dog' ? 'selected' : '' ?>>Dog</option>
                    <option value="cat" <?= $species === 'cat' ? 'selected' : '' ?>>Cat</option>
                    <option value="other" <?= $species === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small fw-bold text-success"><i class="bi bi-tag me-1"></i>Collar</label>
                <select name="collar" class="form-select">
                    <option value="">All</option>
                    <option value="green" <?= $collar === 'green' ? 'selected' : '' ?>>Green</option>
                    <option value="yellow" <?= $collar === 'yellow' ? 'selected' : '' ?>>Yellow</option>
                    <option value="red" <?= $collar === 'red' ? 'selected' : '' ?>>Red</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small fw-bold text-success"><i class="bi bi-heart me-1"></i>Adoption</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="reserved" <?= $status === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                    <option value="adopted" <?= $status === 'adopted' ? 'selected' : '' ?>>Adopted</option>
                    <option value="not_available" <?= $status === 'not_available' ? 'selected' : '' ?>>Not Available</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label small fw-bold text-success"><i class="bi bi-geo-alt me-1"></i>Location Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="shelter" <?= $type === 'shelter' ? 'selected' : '' ?>>In Shelter</option>
                    <option value="outside" <?= $type === 'outside' ? 'selected' : '' ?>>Outside Monitored</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4 d-grid">
                <button type="submit" class="btn btn-success"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </div>
        <?php if ($active_filters): ?>
            <div class="active-filter-row mt-3 d-flex flex-wrap align-items-center gap-2">
                <?php foreach ($active_filters as $filter): ?>
                    <span class="active-filter-pill"><?= htmlspecialchars($filter) ?></span>
                <?php endforeach; ?>
                <a href="gallery.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-x-circle me-1"></i>Clear All
                </a>
            </div>
        <?php endif; ?>
    </form>

    <?php if (count($map_data) > 0): ?>
    <div class="card shadow-sm mb-4 overflow-hidden">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-geo-alt text-success"></i> Community Animal Map</span>
            <small class="text-muted">Known locations only</small>
        </div>
        <div id="map" style="height: 350px;"></div>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1 text-success"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Browse Results</h2>
            <p class="text-muted mb-0"><?= $total_results ?> animals currently match your filters</p>
        </div>
        <?php if (isLoggedIn()): ?>
            <?php
                if (isAdmin())         { $dash_href = '../dashboard/admin/index.php';     $dash_label = 'Admin Dashboard'; }
                elseif (isShelter())   { $dash_href = '../dashboard/shelter/index.php';   $dash_label = 'Shelter Dashboard'; }
                elseif (isVolunteer()) { $dash_href = '../dashboard/volunteer/index.php'; $dash_label = 'My Dashboard'; }
                else                   { $dash_href = 'account.php';                      $dash_label = 'My App'; }
            ?>
            <a href="<?= $dash_href ?>" class="btn btn-outline-success">
                <i class="bi bi-house-heart me-1"></i><?= $dash_label ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="row g-4">
    <?php foreach ($animals_list as $a): ?>
        <?php
            $collar_class = $a['collar_status'] === 'green' ? 'bg-success' : ($a['collar_status'] === 'yellow' ? 'text-bg-warning' : 'bg-danger');
            $collar_label = $a['collar_status'] === 'green' ? '✓ Ready' : ($a['collar_status'] === 'yellow' ? '⚕ In Treatment' : '🚨 Critical');
            $adoption_class = $a['adoption_status'] === 'available' ? 'bg-success' : ($a['adoption_status'] === 'reserved' ? 'text-bg-warning' : 'bg-secondary');
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100 gallery-card">
                <div class="gallery-card-media">
                    <?php if (!empty($a['photo'])): ?>
                        <img src="../public/uploads/<?= htmlspecialchars($a['photo']) ?>" class="card-img-top" alt="<?= htmlspecialchars($a['name'] ?? 'Animal photo') ?>">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height:240px;">
                            <i class="bi bi-image text-muted" style="font-size:3rem;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="gallery-card-badges">
                        <span class="badge <?= $collar_class ?>"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i><?= $collar_label ?></span>
                        <span class="badge <?= $adoption_class ?>"><i class="bi bi-heart-fill me-1" style="font-size:0.5rem;"></i><?= ucfirst(str_replace('_', ' ', $a['adoption_status'])) ?></span>
                    </div>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h5 class="card-title mb-2"><?= htmlspecialchars($a['name'] ?? 'Unnamed') ?></h5>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-<?= $a['species'] === 'dog' ? 'dog' : ($a['species'] === 'cat' ? 'cat' : 'box') ?> me-1"></i>
                                <?= ucfirst($a['species']) ?> · <?= ucfirst($a['gender']) ?> · <?= ucfirst($a['size']) ?>
                            </p>
                        </div>
                    </div>
                    <div class="gallery-meta-list mb-3">
                        <?php if (!empty($a['breed'])): ?>
                            <span><i class="bi bi-bookmark-heart"></i> <?= htmlspecialchars($a['breed']) ?></span>
                        <?php endif; ?>
                        <?php if ($a['is_in_shelter'] && $a['shelter_name']): ?>
                            <span><i class="bi bi-house-heart"></i> <?= htmlspecialchars($a['shelter_name']) ?></span>
                        <?php elseif (!$a['is_in_shelter'] && $a['location_label']): ?>
                            <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($a['location_label']) ?></span>
                        <?php endif; ?>
                        <span><i class="bi bi-clipboard2-pulse"></i> <?= $a['is_in_shelter'] ? 'In shelter care' : 'Outside monitored' ?></span>
                    </div>
                    <a href="animal.php?id=<?= $a['id'] ?>" class="btn btn-success w-100 mt-auto">
                        <i class="bi bi-eye me-1"></i>View Profile
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($total_results === 0): ?>
        <div class="col-12 text-center text-muted py-5">
            <div class="mb-4">
                <i class="bi bi-emoji-frown text-muted" style="font-size:4rem;"></i>
            </div>
            <h4 class="mb-3">No animals found</h4>
            <p class="mb-4">Try adjusting your filters to find more animals.</p>
            <a href="gallery.php" class="btn btn-success btn-lg">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Clear All Filters
            </a>
        </div>
    <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
<?php if (count($map_data) > 0): ?>
const map = L.map('map').setView([28.2096, 83.9856], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

const animals = <?= json_encode($map_data) ?>;
animals.forEach(a => {
    const label = a.collar_status === 'green' ? 'Ready' : (a.collar_status === 'yellow' ? 'In treatment' : 'Critical');
    const place = a.is_in_shelter ? (a.shelter_name || 'Shelter') : (a.location_label || 'Outside monitored');
    L.marker([a.latitude, a.longitude])
        .addTo(map)
        .bindPopup(`<strong>${a.name || 'Unnamed'}</strong><br>${label}<br>${place}<br><a href="animal.php?id=${a.id}">View Profile</a>`);
});
<?php endif; ?>
</script>
</body>
</html>
