<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$species = isset($_GET['species']) ? sanitize($_GET['species']) : '';
$condition = isset($_GET['condition']) ? sanitize($_GET['condition']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$where = "WHERE s.status = 'active'";
if ($species && in_array($species, ['dog','cat','other'])) $where .= " AND s.species = '$species'";
if ($condition && in_array($condition, ['unknown','healthy','injured','critical'])) $where .= " AND s.condition_status = '$condition'";
if ($search) $where .= " AND (s.name LIKE '%$search%' OR s.area_label LIKE '%$search%' OR s.breed LIKE '%$search%' OR s.description LIKE '%$search%')";

$strays = mysqli_query($conn, "
    SELECT s.*, m.full_name AS reporter_name
    FROM stray_animals s
    JOIN members m ON s.reported_by = m.id
    $where
    ORDER BY s.updated_at DESC
");
$stray_list = [];
while ($row = mysqli_fetch_assoc($strays)) $stray_list[] = $row;

$total = count($stray_list);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strays — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php $active_page = 'strays'; $nav_depth = 1; include '../includes/navbar.php'; ?>

<div class="container py-5">

    <section class="gallery-hero mb-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="section-kicker mb-3"><i class="bi bi-geo-alt-fill text-success me-2"></i>Community Strays</p>
                <h1 class="fw-bold mb-3 display-5">Strays in the Wild</h1>
                <p class="text-muted mb-4 fs-5">These animals have been spotted and reported by our community. They're not in shelters yet — but they're being watched over. Your interest could change their story.</p>
                <div class="gallery-chip-row">
                    <span class="gallery-chip"><i class="bi bi-eye me-1"></i><strong><?= $total ?></strong> strays tracked</span>
                    <span class="gallery-chip"><i class="bi bi-shield-check me-1"></i>Location kept vague for safety</span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="gallery-summary-card">
                    <div>
                        <span class="summary-label"><i class="bi bi-info-circle me-1"></i>Why no exact location?</span>
                        <strong>We show area-level info only to protect animals from bad actors. If you want to help, use the Inquire button.</strong>
                    </div>
                    <div>
                        <span class="summary-label"><i class="bi bi-person-plus me-1"></i>Want to report a stray?</span>
                        <strong><a href="stray_report.php" class="text-success">Submit a sighting</a> — all reports are reviewed before going live.</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <form method="GET" class="card shadow p-4 mb-5 filter-surface" style="border: 2px solid var(--green-light);">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label class="form-label small fw-bold text-success"><i class="bi bi-search me-1"></i>Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, area, or breed" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-bold text-success"><i class="bi bi-paw me-1"></i>Species</label>
                <select name="species" class="form-select">
                    <option value="">All Species</option>
                    <option value="dog" <?= $species==='dog'?'selected':'' ?>>Dog</option>
                    <option value="cat" <?= $species==='cat'?'selected':'' ?>>Cat</option>
                    <option value="other" <?= $species==='other'?'selected':'' ?>>Other</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-bold text-success"><i class="bi bi-heart-pulse me-1"></i>Condition</label>
                <select name="condition" class="form-select">
                    <option value="">All Conditions</option>
                    <option value="healthy" <?= $condition==='healthy'?'selected':'' ?>>Healthy</option>
                    <option value="injured" <?= $condition==='injured'?'selected':'' ?>>Injured</option>
                    <option value="critical" <?= $condition==='critical'?'selected':'' ?>>Critical</option>
                    <option value="unknown" <?= $condition==='unknown'?'selected':'' ?>>Unknown</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6 d-grid">
                <button type="submit" class="btn btn-success"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </div>
        <?php if ($search || $species || $condition): ?>
            <div class="mt-3"><a href="strays.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Clear</a></div>
        <?php endif; ?>
    </form>

    <?php if (empty($stray_list)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-emoji-frown" style="font-size:3rem;display:block;margin-bottom:1rem;"></i>
            <h5>No strays found matching your filters.</h5>
            <a href="strays.php" class="btn btn-success mt-3">Clear Filters</a>
        </div>
    <?php else: ?>
    <div class="row g-4">
    <?php foreach ($stray_list as $s):
        $cond_color = match($s['condition_status']) {
            'healthy'  => 'success',
            'injured'  => 'warning',
            'critical' => 'danger',
            default    => 'secondary'
        };
        $cond_icon = match($s['condition_status']) {
            'healthy'  => 'check-circle',
            'injured'  => 'bandaid',
            'critical' => 'exclamation-triangle',
            default    => 'question-circle'
        };
    ?>
        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100 gallery-card">
                <div class="gallery-card-media">
                    <?php if ($s['photo']): ?>
                        <img src="../public/uploads/<?= htmlspecialchars($s['photo']) ?>" class="card-img-top" alt="Stray animal" style="height:240px;object-fit:cover;">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height:240px;">
                            <i class="bi bi-image text-muted" style="font-size:3rem;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="gallery-card-badges">
                        <span class="badge bg-<?= $cond_color ?>">
                            <i class="bi bi-<?= $cond_icon ?> me-1"></i><?= ucfirst($s['condition_status']) ?>
                        </span>
                        <span class="badge bg-dark">
                            <?= ucfirst($s['approximate_age']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-1"><?= htmlspecialchars($s['name'] ?? 'Unnamed Stray') ?></h5>
                    <p class="text-muted small mb-2">
                        <i class="bi bi-<?= $s['species']==='dog'?'dog':($s['species']==='cat'?'cat':'box') ?> me-1"></i>
                        <?= ucfirst($s['species']) ?>
                        <?= $s['breed'] ? ' · ' . htmlspecialchars($s['breed']) : '' ?>
                        · <?= ucfirst($s['gender']) ?>
                    </p>
                    <div class="gallery-meta-list mb-3">
                        <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($s['area_label'] ?? 'Area unknown') ?></span>
                        <span><i class="bi bi-clock"></i> Last updated <?= timeAgo($s['updated_at']) ?></span>
                    </div>
                    <?php if ($s['description']): ?>
                        <p class="text-muted small mb-3"><?= htmlspecialchars(substr($s['description'], 0, 80)) ?>...</p>
                    <?php endif; ?>
                    <a href="stray.php?id=<?= $s['id'] ?>" class="btn btn-success w-100 mt-auto">
                        <i class="bi bi-eye me-1"></i>View & Inquire
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
