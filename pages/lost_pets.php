<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

mysqli_query($conn, "UPDATE lost_pet_posts SET status = 'expired' WHERE status IN ('active', 'sighted') AND expires_at < NOW()");

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$city = isset($_GET['city']) ? sanitize($_GET['city']) : '';
$species = isset($_GET['species']) ? sanitize($_GET['species']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$cities = [];
$city_query = mysqli_query($conn, "SELECT DISTINCT city FROM lost_pet_posts WHERE visibility = 'public' ORDER BY city ASC");
while ($row = mysqli_fetch_assoc($city_query)) {
    $cities[] = $row['city'];
}

$allowed_species = ['dog', 'cat', 'other'];
$allowed_status = ['active', 'sighted', 'found', 'expired', 'closed'];
$where_parts = ["p.visibility = 'public'"];

if ($species && in_array($species, $allowed_species, true)) {
    $where_parts[] = "p.species = '" . mysqli_real_escape_string($conn, $species) . "'";
}
if ($status && in_array($status, $allowed_status, true)) {
    $where_parts[] = "p.status = '" . mysqli_real_escape_string($conn, $status) . "'";
} else {
    $where_parts[] = "p.status IN ('active', 'sighted')";
}
if ($city !== '') {
    $where_parts[] = "p.city = '" . mysqli_real_escape_string($conn, $city) . "'";
}
if ($search !== '') {
    $like = mysqli_real_escape_string($conn, '%' . $search . '%');
    $where_parts[] = "(p.pet_name LIKE '$like' OR p.breed LIKE '$like' OR p.city LIKE '$like' OR p.last_seen_label LIKE '$like')";
}

$posts = mysqli_query($conn, "
    SELECT p.*, m.full_name AS owner_name
    FROM lost_pet_posts p
    JOIN members m ON p.member_id = m.id
    WHERE " . implode(' AND ', $where_parts) . "
    ORDER BY CASE WHEN p.status = 'sighted' THEN 0 WHEN p.status = 'active' THEN 1 ELSE 2 END, p.last_seen_at DESC
");

$post_items = [];
$active_count = 0;
$sighted_count = 0;
while ($row = mysqli_fetch_assoc($posts)) {
    if ($row['status'] === 'active') $active_count++;
    if ($row['status'] === 'sighted') $sighted_count++;
    $post_items[] = $row;
}

$active_filters = [];
if ($search) $active_filters[] = 'Search: ' . $search;
if ($city) $active_filters[] = 'City: ' . $city;
if ($species) $active_filters[] = 'Species: ' . ucfirst($species);
if ($status) $active_filters[] = 'Status: ' . ucfirst($status);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Pets - StrayLink</title>
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
    <section class="gallery-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="section-kicker mb-2">Lost pet posters</p>
                <h1 class="fw-bold mb-3">Share missing-pet posters and help families bring their animals home.</h1>
                <p class="text-muted mb-4">Browse approved cases, filter by city and species, and open each poster for sightings, reward notes, and contact details.</p>
                <div class="gallery-chip-row">
                    <span class="gallery-chip"><strong><?= count($post_items) ?></strong> visible posters</span>
                    <span class="gallery-chip"><strong><?= $active_count ?></strong> active</span>
                    <span class="gallery-chip"><strong><?= $sighted_count ?></strong> with leads</span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="gallery-summary-card">
                    <div>
                        <span class="summary-label">For pet owners</span>
                        <strong>Create a poster with a photo, last seen location, contact number, and optional reward.</strong>
                    </div>
                    <div>
                        <span class="summary-label">Before it goes live</span>
                        <strong>Each new poster is reviewed first so the public board stays cleaner and more trustworthy.</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1">Current Posters</h2>
            <p class="text-muted mb-0">Missing-pet cases expire unless the owner renews them, so the board stays current.</p>
        </div>
        <a href="<?= isLoggedIn() ? 'lost_pet_create.php' : '../auth/login.php' ?>" class="btn btn-success"><i class="bi bi-plus-circle"></i> Create Poster</a>
    </div>

    <form method="GET" class="card shadow-sm p-3 mb-4 filter-surface">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label class="form-label small fw-bold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Pet name, breed, city, or last seen area" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-bold">City</label>
                <select name="city" class="form-select">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $city_name): ?>
                        <option value="<?= htmlspecialchars($city_name) ?>" <?= $city === $city_name ? 'selected' : '' ?>><?= htmlspecialchars($city_name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small fw-bold">Species</label>
                <select name="species" class="form-select">
                    <option value="">All</option>
                    <option value="dog" <?= $species === 'dog' ? 'selected' : '' ?>>Dog</option>
                    <option value="cat" <?= $species === 'cat' ? 'selected' : '' ?>>Cat</option>
                    <option value="other" <?= $species === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="">Active Board</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="sighted" <?= $status === 'sighted' ? 'selected' : '' ?>>Sighted</option>
                    <option value="found" <?= $status === 'found' ? 'selected' : '' ?>>Found</option>
                    <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                    <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-12 d-grid">
                <button type="submit" class="btn btn-success">Go</button>
            </div>
        </div>
        <?php if ($active_filters): ?>
            <div class="active-filter-row mt-3">
                <?php foreach ($active_filters as $filter): ?>
                    <span class="active-filter-pill"><?= htmlspecialchars($filter) ?></span>
                <?php endforeach; ?>
                <a href="lost_pets.php" class="btn btn-link btn-sm p-0">Clear all</a>
            </div>
        <?php endif; ?>
    </form>

    <div class="row g-4">
        <?php foreach ($post_items as $post): ?>
            <?php $status_class = $post['status'] === 'active' ? 'bg-danger' : ($post['status'] === 'sighted' ? 'text-bg-warning' : ($post['status'] === 'found' ? 'bg-success' : 'bg-secondary')); ?>
            <div class="col-md-6 col-xl-4">
                <div class="card shadow-sm h-100 gallery-card">
                    <div class="gallery-card-media">
                        <img src="../public/uploads/<?= htmlspecialchars($post['poster_image']) ?>" class="card-img-top" style="height:260px;object-fit:cover;" alt="Poster for <?= htmlspecialchars($post['pet_name']) ?>">
                        <div class="gallery-card-badges">
                            <span class="badge <?= $status_class ?>"><?= ucfirst($post['status']) ?></span>
                            <span class="badge text-bg-light border"><?= ucfirst($post['species']) ?></span>
                            <?php if ($post['reward_amount'] !== null && (float)$post['reward_amount'] > 0): ?>
                                <span class="badge bg-success">Reward</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title mb-1"><?= htmlspecialchars($post['pet_name']) ?></h5>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($post['breed'] ?: ucfirst($post['species'])) ?></p>
                        <div class="gallery-meta-list mb-3">
                            <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($post['last_seen_label']) ?></span>
                            <span><i class="bi bi-calendar-event"></i> <?= date('M d, Y g:i A', strtotime($post['last_seen_at'])) ?></span>
                            <span><i class="bi bi-person"></i> <?= htmlspecialchars($post['contact_name']) ?></span>
                            <?php if ($post['reward_amount'] !== null && (float)$post['reward_amount'] > 0): ?>
                                <span><i class="bi bi-cash-coin"></i> NPR <?= number_format((float)$post['reward_amount'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="lost_pet.php?id=<?= (int)$post['id'] ?>" class="btn btn-success w-100 mt-auto">Open Poster</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (count($post_items) === 0): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="bi bi-megaphone" style="font-size:3rem;"></i>
                <p class="mt-3 mb-3">No lost-pet posters match your current filters.</p>
                <a href="lost_pets.php" class="btn btn-outline-success">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
