<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$city = isset($_GET['city']) ? sanitize($_GET['city']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$city_stmt = mysqli_prepare($conn, "
    SELECT DISTINCT s.city
    FROM shelters s
    JOIN members m ON s.member_id = m.id
    WHERE m.is_active = 1 AND s.city IS NOT NULL AND s.city <> ''
    ORDER BY city ASC
");
mysqli_stmt_execute($city_stmt);
$city_result = mysqli_stmt_get_result($city_stmt);
$cities = [];
while ($row = mysqli_fetch_assoc($city_result)) {
    $cities[] = $row['city'];
}

$where = ["m.is_active = 1"];
$types = '';
$params = [];

if ($city !== '') {
    $where[] = "s.city = ?";
    $types .= 's';
    $params[] = $city;
}

if ($search !== '') {
    $where[] = "(s.shelter_name LIKE ? OR s.city LIKE ? OR s.address LIKE ?)";
    $types .= 'sss';
    $search_like = '%' . $search . '%';
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
}

$where_sql = implode(' AND ', $where);

$shelter_sql = "
    SELECT
        s.*,
        COUNT(DISTINCT a.id) AS total_animals,
        SUM(CASE WHEN a.adoption_status = 'available' THEN 1 ELSE 0 END) AS available_animals,
        SUM(CASE WHEN a.adoption_status = 'adopted' THEN 1 ELSE 0 END) AS adopted_animals
    FROM shelters s
    JOIN members m ON s.member_id = m.id
    LEFT JOIN animals a ON a.shelter_id = s.id AND a.is_active = 1
    WHERE $where_sql
    GROUP BY s.id
    ORDER BY s.city ASC, s.shelter_name ASC
";

$shelter_stmt = mysqli_prepare($conn, $shelter_sql);
if ($types !== '') {
    mysqli_stmt_bind_param($shelter_stmt, $types, ...$params);
}
mysqli_stmt_execute($shelter_stmt);
$shelter_result = mysqli_stmt_get_result($shelter_stmt);

$shelters = [];
$total_available = 0;
while ($row = mysqli_fetch_assoc($shelter_result)) {
    $row['total_animals'] = (int)($row['total_animals'] ?? 0);
    $row['available_animals'] = (int)($row['available_animals'] ?? 0);
    $row['adopted_animals'] = (int)($row['adopted_animals'] ?? 0);
    $total_available += $row['available_animals'];
    $shelters[] = $row;
}

$summary_stmt = mysqli_prepare($conn, "
    SELECT
        COUNT(*) AS total_shelters,
        COUNT(DISTINCT s.city) AS total_cities
    FROM shelters s
    JOIN members m ON s.member_id = m.id
    WHERE m.is_active = 1
");
mysqli_stmt_execute($summary_stmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($summary_stmt));

$active_filters = [];
if ($city !== '') {
    $active_filters[] = 'City: ' . $city;
}
if ($search !== '') {
    $active_filters[] = 'Search: ' . $search;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shelters - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .shelters-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .shelter-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .shelter-card-item {
            background: white;
            border: 1px solid #e2d9ce;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
        }

        .shelter-card-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .shelter-card-logo-section {
            background: #f0eae0;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 140px;
            border-bottom: 1px solid #e2d9ce;
        }

        .shelter-card-logo-section img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .shelter-card-logo-placeholder {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #198754;
            font-size: 36px;
            border: 1px solid #e2d9ce;
        }

        .shelter-card-content {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .shelter-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2c1a0e;
            margin: 0 0 8px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .shelter-card-location {
            font-size: 0.9rem;
            color: #6b5744;
            margin: 0 0 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .shelter-card-stats {
            font-size: 0.85rem;
            color: #a08070;
            margin: 12px 0;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-item strong {
            color: #2c1a0e;
        }

        .shelter-card-button {
            background: #198754;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin-top: auto;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .shelter-card-button:hover {
            background: #146c43;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .shelter-cards-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        @media (min-width: 769px) and (max-width: 1199px) {
            .shelter-cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1200px) {
            .shelter-cards-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }
    </style>
</head>
<body>

<?php
    $active_page = 'shelters';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="shelters-container py-5 px-3">
    <section class="shelter-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="section-kicker mb-2">Shelter directory</p>
                <h1 class="fw-bold mb-3">Find shelters by city and explore the animals they are caring for.</h1>
                <p class="text-muted mb-4">Browse shelters near you, see what animals they have, and get contact information.</p>
                <div class="gallery-chip-row">
                    <span class="gallery-chip"><strong><?= (int)($summary['total_shelters'] ?? 0) ?></strong> shelters</span>
                    <span class="gallery-chip"><strong><?= (int)($summary['total_cities'] ?? 0) ?></strong> cities</span>
                    <span class="gallery-chip"><strong><?= $total_available ?></strong> animals available</span>
                </div>
            </div>
        </div>
    </section>

    <form method="GET" class="card shadow-sm p-3 mb-4 filter-surface">
        <div class="row g-3 align-items-end">
            <div class="col-lg-5 col-md-6">
                <label class="form-label small fw-bold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Shelter name, city, or address" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-lg-4 col-md-6">
                <label class="form-label small fw-bold">City</label>
                <select name="city" class="form-select">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $city_name): ?>
                        <option value="<?= htmlspecialchars($city_name) ?>" <?= $city === $city_name ? 'selected' : '' ?>>
                            <?= htmlspecialchars($city_name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-12 d-grid">
                <button type="submit" class="btn btn-success">Browse Shelters</button>
            </div>
        </div>
        <?php if ($active_filters): ?>
            <div class="active-filter-row mt-3">
                <?php foreach ($active_filters as $filter): ?>
                    <span class="active-filter-pill"><?= htmlspecialchars($filter) ?></span>
                <?php endforeach; ?>
                <a href="shelters.php" class="btn btn-link btn-sm p-0">Clear all</a>
            </div>
        <?php endif; ?>
    </form>

    <div class="mb-3">
        <h2 class="fw-bold mb-1">Available Shelters</h2>
        <p class="text-muted mb-0"><?= count($shelters) ?> shelters currently match your filters.</p>
    </div>

    <div class="shelter-cards-grid">
        <?php foreach ($shelters as $shelter): ?>
            <div class="shelter-card-item">
                <div class="shelter-card-logo-section">
                    <?php if (!empty($shelter['logo'])): ?>
                        <img src="../public/uploads/<?= htmlspecialchars($shelter['logo']) ?>" alt="<?= htmlspecialchars($shelter['shelter_name']) ?>">
                    <?php else: ?>
                        <div class="shelter-card-logo-placeholder">
                            <i class="bi bi-house-heart"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="shelter-card-content">
                    <h3 class="shelter-card-title"><?= htmlspecialchars($shelter['shelter_name']) ?></h3>
                    <p class="shelter-card-location">
                        <i class="bi bi-geo-alt"></i>
                        <?= htmlspecialchars($shelter['city'] ?: 'Location not specified') ?>
                    </p>
                    <div class="shelter-card-stats">
                        <div class="stat-item">
                            <i class="bi bi-heart"></i>
                            <span><strong><?= $shelter['total_animals'] ?></strong> animals</span>
                        </div>
                        <div class="stat-item">
                            <i class="bi bi-check-circle"></i>
                            <span><strong><?= $shelter['available_animals'] ?></strong> available</span>
                        </div>
                    </div>
                    <a href="shelter.php?id=<?= (int)$shelter['id'] ?>" class="shelter-card-button">View Shelter</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($shelters) === 0): ?>
        <div class="text-center text-muted py-5" style="grid-column: 1/-1;">
            <i class="bi bi-house-heart" style="font-size:3rem;"></i>
            <p class="mt-3 mb-3">No shelters match your current filters.</p>
            <a href="shelters.php" class="btn btn-outline-success">Clear Filters</a>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
