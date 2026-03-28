<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Filters
$species  = isset($_GET['species'])  ? sanitize($_GET['species'])  : '';
$collar   = isset($_GET['collar'])   ? sanitize($_GET['collar'])   : '';
$status   = isset($_GET['status'])   ? sanitize($_GET['status'])   : '';
$search   = isset($_GET['search'])   ? sanitize($_GET['search'])   : '';
$type     = isset($_GET['type'])     ? sanitize($_GET['type'])     : ''; // in_shelter or outside

$where = "WHERE a.is_active = 1";

if ($species && in_array($species, ['dog','cat','other']))
    $where .= " AND a.species = '$species'";
if ($collar && in_array($collar, ['green','yellow','red']))
    $where .= " AND a.collar_status = '$collar'";
if ($status && in_array($status, ['available','reserved','adopted','not_available']))
    $where .= " AND a.adoption_status = '$status'";
if ($type === 'shelter')
    $where .= " AND a.is_in_shelter = 1";
if ($type === 'outside')
    $where .= " AND a.is_in_shelter = 0";
if ($search)
    $where .= " AND (a.name LIKE '%$search%' OR a.breed LIKE '%$search%' OR a.location_label LIKE '%$search%')";

$animals = mysqli_query($conn, "
    SELECT a.*, s.shelter_name,
        (SELECT photo_path FROM animal_photos WHERE animal_id = a.id AND is_primary = 1 LIMIT 1) AS photo
    FROM animals a
    LEFT JOIN shelters s ON a.shelter_id = s.id
    $where
    ORDER BY a.created_at DESC
");

// For map — animals with coordinates
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Gallery — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<?php
    $active_page = 'gallery';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
    <h2 class="fw-bold mb-4">Animal Gallery</h2>

    <!-- Filters -->
    <form method="GET" class="card shadow-sm p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, breed, location..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Species</label>
                <select name="species" class="form-select">
                    <option value="">All Species</option>
                    <option value="dog"   <?= $species === 'dog'   ? 'selected' : '' ?>>Dog</option>
                    <option value="cat"   <?= $species === 'cat'   ? 'selected' : '' ?>>Cat</option>
                    <option value="other" <?= $species === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Collar Status</label>
                <select name="collar" class="form-select">
                    <option value="">All</option>
                    <option value="green"  <?= $collar === 'green'  ? 'selected' : '' ?>>🟢 Green</option>
                    <option value="yellow" <?= $collar === 'yellow' ? 'selected' : '' ?>>🟡 Yellow</option>
                    <option value="red"    <?= $collar === 'red'    ? 'selected' : '' ?>>🔴 Red</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Adoption Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="available"     <?= $status === 'available'     ? 'selected' : '' ?>>Available</option>
                    <option value="reserved"      <?= $status === 'reserved'      ? 'selected' : '' ?>>Reserved</option>
                    <option value="adopted"       <?= $status === 'adopted'       ? 'selected' : '' ?>>Adopted</option>
                    <option value="not_available" <?= $status === 'not_available' ? 'selected' : '' ?>>Not Available</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="shelter" <?= $type === 'shelter' ? 'selected' : '' ?>>In Shelter</option>
                    <option value="outside" <?= $type === 'outside' ? 'selected' : '' ?>>Outside / Monitored</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-success w-100">Filter</button>
            </div>
        </div>
    </form>

    <!-- Map -->
    <?php if (count($map_data) > 0): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">📍 Animal Map</div>
        <div id="map" style="height: 350px;"></div>
    </div>
    <?php endif; ?>

    <!-- Animal Cards -->
    <div class="row g-4">
    <?php 
    $count = 0;
    while ($a = mysqli_fetch_assoc($animals)): 
        $count++;
    ?>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <?php if ($a['photo']): ?>
                    <img src="../public/uploads/<?= $a['photo'] ?>" class="card-img-top" style="height:220px;object-fit:cover;">
                <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:220px;">
                        <i class="bi bi-image text-muted" style="font-size:3rem;"></i>
                    </div>
                <?php endif; ?>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0"><?= htmlspecialchars($a['name'] ?? 'Unnamed') ?></h5>
                        <span class="badge" style="background-color:<?= $a['collar_status'] === 'green' ? '#198754' : ($a['collar_status'] === 'yellow' ? '#ffc107' : '#dc3545') ?>">
                            <?= $a['collar_status'] === 'green' ? '🟢' : ($a['collar_status'] === 'yellow' ? '🟡' : '🔴') ?>
                            <?= ucfirst($a['collar_status']) ?>
                        </span>
                    </div>
                    <p class="text-muted small mb-1">
                        <?= ucfirst($a['species']) ?> · <?= ucfirst($a['gender']) ?> · <?= ucfirst($a['size']) ?>
                    </p>
                    <?php if ($a['is_in_shelter'] && $a['shelter_name']): ?>
                        <p class="text-muted small mb-1"><i class="bi bi-house"></i> <?= htmlspecialchars($a['shelter_name']) ?></p>
                    <?php elseif (!$a['is_in_shelter'] && $a['location_label']): ?>
                        <p class="text-muted small mb-1"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($a['location_label']) ?></p>
                    <?php endif; ?>
                    <p class="text-muted small mb-3">
                        <span class="badge bg-<?= $a['adoption_status'] === 'available' ? 'success' : 'secondary' ?>">
                            <?= ucfirst($a['adoption_status']) ?>
                        </span>
                    </p>
                    <a href="animal.php?id=<?= $a['id'] ?>" class="btn btn-success w-100">View Profile</a>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
    <?php if ($count === 0): ?>
        <div class="col-12 text-center text-muted py-5">
            <i class="bi bi-search" style="font-size:3rem;"></i>
            <p class="mt-3">No animals found matching your filters.</p>
            <a href="gallery.php" class="btn btn-outline-success">Clear Filters</a>
        </div>
    <?php endif; ?>
    </div>
</div>

<!-- Footer -->
<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
<?php if (count($map_data) > 0): ?>
const map = L.map('map').setView([28.2096, 83.9856], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

const animals = <?= json_encode($map_data) ?>;
animals.forEach(a => {
    const color = a.collar_status === 'green' ? '🟢' : (a.collar_status === 'yellow' ? '🟡' : '🔴');
    const label = a.is_in_shelter ? (a.shelter_name || 'Shelter') : (a.location_label || 'Outside');
    L.marker([a.latitude, a.longitude])
        .addTo(map)
        .bindPopup(`<strong>${color} ${a.name || 'Unnamed'}</strong><br>${a.species}<br>${label}<br><a href="animal.php?id=${a.id}">View Profile →</a>`);
});
<?php endif; ?>
</script>
</body>
</html>