<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('shelters.php');
}

$shelter_id = (int)$_GET['id'];

$shelter_stmt = mysqli_prepare($conn, "
    SELECT
        s.*,
        COUNT(DISTINCT a.id) AS total_animals,
        SUM(CASE WHEN a.adoption_status = 'available' THEN 1 ELSE 0 END) AS available_animals,
        SUM(CASE WHEN a.adoption_status = 'adopted' THEN 1 ELSE 0 END) AS adopted_animals
    FROM shelters s
    JOIN members m ON s.member_id = m.id
    LEFT JOIN animals a ON a.shelter_id = s.id AND a.is_active = 1
    WHERE s.id = ? AND m.is_active = 1
    GROUP BY s.id
    LIMIT 1
");
mysqli_stmt_bind_param($shelter_stmt, "i", $shelter_id);
mysqli_stmt_execute($shelter_stmt);
$shelter = mysqli_fetch_assoc(mysqli_stmt_get_result($shelter_stmt));

if (!$shelter) {
    redirect('shelters.php');
}

$payment_stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS active_methods
    FROM shelter_payment_methods
    WHERE shelter_id = ? AND is_active = 1
");
mysqli_stmt_bind_param($payment_stmt, "i", $shelter_id);
mysqli_stmt_execute($payment_stmt);
$payment_summary = mysqli_fetch_assoc(mysqli_stmt_get_result($payment_stmt));

$animal_stmt = mysqli_prepare($conn, "
    SELECT
        a.*,
        (SELECT photo_path FROM animal_photos WHERE animal_id = a.id AND is_primary = 1 LIMIT 1) AS photo
    FROM animals a
    WHERE a.shelter_id = ? AND a.is_active = 1
    ORDER BY
        CASE WHEN a.adoption_status = 'available' THEN 0 ELSE 1 END,
        a.created_at DESC
");
mysqli_stmt_bind_param($animal_stmt, "i", $shelter_id);
mysqli_stmt_execute($animal_stmt);
$animal_result = mysqli_stmt_get_result($animal_stmt);

$animals = [];
while ($row = mysqli_fetch_assoc($animal_result)) {
    $animals[] = $row;
}

$has_map = !empty($shelter['latitude']) && !empty($shelter['longitude']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shelter['shelter_name']) ?> - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <?php if ($has_map): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <?php endif; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .shelter-detail-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .shelter-header {
            background: white;
            border: 1px solid #e2d9ce;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
        }

        .shelter-header-top {
            display: flex;
            align-items: flex-start;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .shelter-logo-display {
            width: 140px;
            height: 140px;
            background: #f0eae0;
            border-radius: 12px;
            border: 1px solid #e2d9ce;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .shelter-logo-display img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 10px;
        }

        .shelter-logo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #198754;
            font-size: 54px;
        }

        .shelter-header-info h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2c1a0e;
            margin: 0 0 12px;
            line-height: 1.2;
        }

        .shelter-header-info .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 8px 0;
            color: #6b5744;
            font-size: 1rem;
        }

        .shelter-header-info .info-row a {
            color: #198754;
            text-decoration: none;
        }

        .shelter-header-info .info-row a:hover {
            text-decoration: underline;
        }

        .shelter-description {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2d9ce;
            color: #6b5744;
            line-height: 1.6;
            font-size: 1rem;
        }

        .shelter-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            padding: 20px 0;
        }

        .stat-box {
            text-align: center;
        }

        .stat-box strong {
            display: block;
            font-size: 2rem;
            color: #198754;
            font-weight: 700;
        }

        .stat-box span {
            display: block;
            font-size: 0.85rem;
            color: #a08070;
            margin-top: 4px;
        }

        .sidebar-section {
            background: white;
            border: 1px solid #e2d9ce;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
        }

        .sidebar-section h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2c1a0e;
            margin: 0 0 16px;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .info-item {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            color: #6b5744;
            font-size: 0.95rem;
        }

        .info-item i {
            color: #198754;
            flex-shrink: 0;
        }

        #shelter-map {
            width: 100%;
            height: 300px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .donate-button {
            background: #198754;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            margin-top: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .donate-button:hover {
            background: #146c43;
            text-decoration: none;
            color: white;
        }

        .animals-section {
            margin-top: 30px;
        }

        .animals-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .animals-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c1a0e;
        }

        .animal-count-badge {
            background: #198754;
            color: white;
            padding: 8px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .animals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
        }

        .animal-card {
            background: white;
            border: 1px solid #e2d9ce;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
        }

        .animal-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .animal-card-image {
            width: 100%;
            height: 180px;
            background: #f0eae0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .animal-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .animal-card-image-placeholder {
            font-size: 2rem;
            color: #a08070;
        }

        .animal-card-content {
            padding: 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .animal-card-name {
            font-weight: 700;
            font-size: 1rem;
            color: #2c1a0e;
            margin: 0 0 4px;
        }

        .animal-card-info {
            font-size: 0.8rem;
            color: #a08070;
            margin: 0 0 8px;
        }

        .animal-card-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .animal-card-badges .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }

        .animal-card-link {
            background: #198754;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin-top: auto;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .animal-card-link:hover {
            background: #146c43;
            color: white;
            text-decoration: none;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border: 1px solid #e2d9ce;
            border-radius: 12px;
            color: #6b5744;
        }

        .empty-state i {
            font-size: 3rem;
            color: #a08070;
            margin-bottom: 16px;
        }

        .layout-wrapper {
            display: grid;
            grid-template-columns: 1fr 2.5fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .layout-wrapper {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .shelter-header {
                padding: 20px;
            }

            .shelter-header-top {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 20px;
            }

            .shelter-header-info h1 {
                font-size: 1.5rem;
            }

            .animals-grid {
                grid-template-columns: 1fr;
            }

            .layout-wrapper {
                gap: 16px;
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

<div class="shelter-detail-container py-5 px-3">
    <a href="shelters.php" class="btn btn-outline-secondary btn-sm mb-4">← Back to Shelters</a>

    <!-- HEADER SECTION -->
    <div class="shelter-header">
        <div class="shelter-header-top">
            <div class="shelter-logo-display">
                <?php if (!empty($shelter['logo'])): ?>
                    <img src="../public/uploads/<?= htmlspecialchars($shelter['logo']) ?>" alt="<?= htmlspecialchars($shelter['shelter_name']) ?>">
                <?php else: ?>
                    <div class="shelter-logo-placeholder">
                        <i class="bi bi-house-heart"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="shelter-header-info">
                <h1><?= htmlspecialchars($shelter['shelter_name']) ?></h1>
                <div class="info-row">
                    <i class="bi bi-geo-alt"></i>
                    <?= htmlspecialchars(trim(($shelter['address'] ?? '') . ', ' . ($shelter['city'] ?? ''), ', ')) ?: 'Location not added' ?>
                </div>
                <?php if (!empty($shelter['contact_number'])): ?>
                    <div class="info-row">
                        <i class="bi bi-telephone"></i>
                        <a href="tel:<?= htmlspecialchars($shelter['contact_number']) ?>"><?= htmlspecialchars($shelter['contact_number']) ?></a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($shelter['website'])): ?>
                    <div class="info-row">
                        <i class="bi bi-globe"></i>
                        <a href="<?= htmlspecialchars($shelter['website']) ?>" target="_blank" rel="noopener noreferrer">Visit website</a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($shelter['description'])): ?>
                    <div class="shelter-description">
                        <?= htmlspecialchars($shelter['description']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- STATS ROW -->
        <div class="shelter-stats">
            <div class="stat-box">
                <strong><?= (int)($shelter['total_animals'] ?? 0) ?></strong>
                <span>Total Animals</span>
            </div>
            <div class="stat-box">
                <strong><?= (int)($shelter['available_animals'] ?? 0) ?></strong>
                <span>Available Now</span>
            </div>
            <div class="stat-box">
                <strong><?= (int)($shelter['capacity'] ?? 0) ?></strong>
                <span>Capacity</span>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT WITH SIDEBAR -->
    <div class="layout-wrapper">
        <!-- SIDEBAR -->
        <aside>
            <!-- INFORMATION SECTION -->
            <div class="sidebar-section">
                <h3>Shelter Details</h3>
                <div class="info-list">
                    <div class="info-item">
                        <i class="bi bi-house-door"></i>
                        <div>
                            <strong>Capacity</strong><br>
                            <?= (int)($shelter['capacity'] ?? 0) ?> animals
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-heart"></i>
                        <div>
                            <strong>Listed Animals</strong><br>
                            <?= (int)($shelter['total_animals'] ?? 0) ?> total
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-check-circle"></i>
                        <div>
                            <strong>Available for Adoption</strong><br>
                            <?= (int)($shelter['available_animals'] ?? 0) ?> animals
                        </div>
                    </div>
                </div>
                <?php if ((int)($payment_summary['active_methods'] ?? 0) > 0): ?>
                    <a href="donate.php?shelter_id=<?= $shelter_id ?>" class="donate-button">Support This Shelter</a>
                <?php endif; ?>
            </div>

            <!-- MAP SECTION -->
            <?php if ($has_map): ?>
                <div class="sidebar-section">
                    <h3>Location</h3>
                    <div id="shelter-map"></div>
                </div>
            <?php endif; ?>
        </aside>

        <!-- MAIN CONTENT -->
        <main>
            <div class="animals-section">
                <div class="animals-header">
                    <h2>Animals in This Shelter</h2>
                    <span class="animal-count-badge"><?= count($animals) ?> animals</span>
                </div>

                <?php if (count($animals) === 0): ?>
                    <div class="empty-state">
                        <i class="bi bi-heart"></i>
                        <p>This shelter has not listed any animals yet.</p>
                    </div>
                <?php else: ?>
                    <div class="animals-grid">
                        <?php foreach ($animals as $animal): ?>
                            <?php
                                $collar_class = $animal['collar_status'] === 'green' ? 'bg-success' : ($animal['collar_status'] === 'yellow' ? 'text-bg-warning' : 'bg-danger');
                                $adoption_class = $animal['adoption_status'] === 'available' ? 'bg-success' : ($animal['adoption_status'] === 'reserved' ? 'text-bg-warning' : 'bg-secondary');
                            ?>
                            <div class="animal-card">
                                <div class="animal-card-image">
                                    <?php if (!empty($animal['photo'])): ?>
                                        <img src="../public/uploads/<?= htmlspecialchars($animal['photo']) ?>" alt="<?= htmlspecialchars($animal['name'] ?? 'Animal') ?>">
                                    <?php else: ?>
                                        <div class="animal-card-image-placeholder">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="animal-card-content">
                                    <h4 class="animal-card-name"><?= htmlspecialchars($animal['name'] ?? 'Unnamed') ?></h4>
                                    <p class="animal-card-info">
                                        <?= ucfirst($animal['species']) ?> · <?= ucfirst($animal['gender']) ?> · <?= ucfirst($animal['size']) ?>
                                    </p>
                                    <div class="animal-card-badges">
                                        <span class="badge <?= $collar_class ?>"><?= ucfirst($animal['collar_status']) ?></span>
                                        <span class="badge <?= $adoption_class ?>"><?= ucfirst(str_replace('_', ' ', $animal['adoption_status'])) ?></span>
                                    </div>
                                    <a href="animal.php?id=<?= (int)$animal['id'] ?>" class="animal-card-link">View Profile</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($has_map): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    const shelterMap = L.map('shelter-map').setView([<?= (float)$shelter['latitude'] ?>, <?= (float)$shelter['longitude'] ?>], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(shelterMap);
    L.marker([<?= (float)$shelter['latitude'] ?>, <?= (float)$shelter['longitude'] ?>])
        .addTo(shelterMap)
        .bindPopup(`<?= htmlspecialchars(addslashes($shelter['shelter_name'])) ?>`)
        .openPopup();
    </script>
<?php endif; ?>
</body>
</html>
