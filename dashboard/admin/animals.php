<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

// Get shelter filter
$shelter_filter = isset($_GET['shelter']) ? sanitize($_GET['shelter']) : '';
$search_name = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM animals WHERE id = $id");
    redirect('animals.php');
}

// Handle toggle active
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    mysqli_query($conn, "UPDATE animals SET is_active = !is_active WHERE id = $id");
    redirect('animals.php');
}

// Build query with shelter filter and search
$query = "
    SELECT a.*, m.full_name AS reporter, s.shelter_name, s.city AS shelter_city
    FROM animals a
    JOIN members m ON a.reported_by = m.id
    LEFT JOIN shelters s ON a.shelter_id = s.id
WHERE 1=1
";

// Apply name search
if ($search_name !== '') {
    $search_safe = mysqli_real_escape_string($conn, $search_name);
    $query .= " AND a.name LIKE '%$search_safe%'";
}

// Apply shelter filter
if ($shelter_filter === 'outside') {
    $query .= " AND a.shelter_id IS NULL";
} elseif ($shelter_filter && $shelter_filter !== 'all') {
    $query .= " AND a.shelter_id = " . (int)$shelter_filter;
}

$query .= " ORDER BY a.created_at DESC";

$animals = mysqli_query($conn, $query);

// Get all shelters for dropdown
$shelters = mysqli_query($conn, "SELECT id, shelter_name FROM shelters ORDER BY shelter_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animals — StrayLink Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<?php
    $dashboard_title = 'StrayLink Admin';
    include '../../includes/navbar_dashboard.php';
?>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="members.php"><i class="bi bi-people"></i> Members</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="animals.php"><i class="bi bi-heart"></i> Animals</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rescues.php"><i class="bi bi-exclamation-triangle"></i> Rescue Reports</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../pages/rescue_board.php"><i class="bi bi-broadcast"></i> Rescue Board</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="strays.php"><i class="bi bi-geo-alt"></i> Strays</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="lost_pets.php"><i class="bi bi-megaphone"></i> Lost Pets</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="shelters.php"><i class="bi bi-house"></i> Shelters</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="posts.php"><i class="bi bi-newspaper"></i> Blog Posts</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <h4 class="mb-4">Animals</h4>

            <!-- Search by Name -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-12 col-md-8">
                            <input type="text" name="search" class="form-control" placeholder="Search by animal name..." value="<?= htmlspecialchars($search_name) ?>">
                        </div>
                        <div class="col-6 col-md-2">
                            <button type="submit" class="btn btn-success w-100">Search</button>
                        </div>
                        <?php if ($search_name): ?>
                            <div class="col-6 col-md-2">
                                <a href="animals.php<?= $shelter_filter ? '?shelter=' . htmlspecialchars($shelter_filter) : '' ?>" class="btn btn-outline-secondary w-100">Clear</a>
                            </div>
                        <?php endif; ?>
                        <?php if ($shelter_filter): ?>
                            <input type="hidden" name="shelter" value="<?= htmlspecialchars($shelter_filter) ?>">
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Shelter Filter -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-6">
                            <label for="shelterFilter" class="form-label"><strong>Filter by Shelter</strong></label>
                            <select id="shelterFilter" class="form-select" onchange="filterByShelter(this.value)">
                                <option value="">All Shelters</option>
                                <option value="outside" <?= $shelter_filter === 'outside' ? 'selected' : '' ?>>Outside Shelter (No Shelter Assigned)</option>
                                <?php while ($s = mysqli_fetch_assoc($shelters)): ?>
                                    <option value="<?= $s['id'] ?>" <?= $shelter_filter === (string)$s['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['shelter_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <?php if ($shelter_filter): ?>
                            <div class="col-md-6">
                                <a href="animals.php" class="btn btn-outline-secondary">Clear Filter</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Species</th>
                                <th>Collar</th>
                                <th>Location</th>
                                <th>Shelter</th>
                                <th>Reported By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($a = mysqli_fetch_assoc($animals)): ?>
                            <?php
                            $photo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT photo_path FROM animal_photos WHERE animal_id = {$a['id']} AND is_primary = 1 LIMIT 1"));
                            ?>
                            <tr>
                                <td><?= $a['id'] ?></td>
                                <td>
                                    <?php if ($photo): ?>
                                        <img src="../../public/uploads/<?= htmlspecialchars($photo['photo_path']) ?>" width="50" height="50" style="object-fit:cover; border-radius:4px;">
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a['name'] ?? '—') ?></td>
                                <td><?= ucfirst($a['species'] ?? '—') ?></td>
                                <td>
                                    <?php if ($a['collar_status']): ?>
                                        <span class="badge" style="background-color:<?= $a['collar_status'] === 'green' ? '#198754' : ($a['collar_status'] === 'yellow' ? '#ffc107' : '#dc3545') ?>">
                                            <?= ucfirst($a['collar_status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a['is_in_shelter'] ? ($a['shelter_city'] ?? $a['shelter_name'] ?? '—') : ($a['location_label'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars($a['shelter_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($a['reporter'] ?? '—') ?></td>
                                <td>
                                    <span class="badge bg-<?= $a['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $a['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="?toggle=<?= $a['id'] ?>" class="btn btn-sm btn-<?= $a['is_active'] ? 'warning' : 'success' ?>">
                                            <?= $a['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </a>
                                        <a href="?delete=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this animal?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($animals) === 0): ?>
                            <tr><td colspan="10" class="text-center text-muted py-3">No animals found</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function filterByShelter(shelterValue) {
        if (shelterValue === '') {
            window.location.href = 'animals.php';
        } else {
            window.location.href = 'animals.php?shelter=' + encodeURIComponent(shelterValue);
        }
    }
</script>
</body>
</html>
