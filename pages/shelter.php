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
            max-width: 1320px;
            margin: 0 auto;
        }

        .shelter-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 8px 14px;
        }

        .shelter-header {
            background: linear-gradient(180deg, #fffdf9 0%, #f8f1e7 100%);
            border: 1px solid #e2d9ce;
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(44, 26, 14, 0.08);
            position: relative;
            overflow: hidden;
        }

        .shelter-header::after {
            content: "";
            position: absolute;
            top: -80px;
            right: -60px;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(25, 135, 84, 0.14) 0%, rgba(25, 135, 84, 0) 70%);
            pointer-events: none;
        }

        .shelter-header-top {
            display: flex;
            align-items: flex-start;
            gap: 28px;
            margin-bottom: 26px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .shelter-logo-display {
            width: 140px;
            height: 140px;
            background: #f4ede3;
            border-radius: 22px;
            border: 1px solid #e2d9ce;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
        }

        .shelter-logo-display img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 14px;
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

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(25, 135, 84, 0.1);
            color: #146c43;
            border: 1px solid rgba(25, 135, 84, 0.16);
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
        }

        .shelter-header-info {
            flex: 1 1 420px;
            max-width: 760px;
        }

        .shelter-donation-card {
            flex: 0 1 290px;
            min-width: 260px;
            background: linear-gradient(180deg, rgba(25, 135, 84, 0.12) 0%, rgba(255, 255, 255, 0.96) 100%);
            border: 1px solid rgba(25, 135, 84, 0.16);
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 12px 24px rgba(20, 108, 67, 0.08);
            align-self: stretch;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .shelter-donation-card .mini-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
            background: rgba(255, 255, 255, 0.9);
            color: #146c43;
            border: 1px solid rgba(25, 135, 84, 0.14);
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .shelter-donation-card h3 {
            margin: 0;
            font-size: 1.18rem;
            line-height: 1.25;
            color: #2c1a0e;
        }

        .shelter-donation-card p {
            margin: 0;
            color: #6b5744;
            line-height: 1.6;
            font-size: 0.92rem;
        }

        .donation-points {
            display: grid;
            gap: 8px;
            margin: 4px 0 2px;
        }

        .donation-points span {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4e3d2d;
            font-size: 0.88rem;
        }

        .donation-points i {
            color: #198754;
        }

        .donation-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .donation-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(226, 217, 206, 0.9);
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 0.8rem;
            color: #6b5744;
        }

        .shelter-header-info h1 {
            font-size: clamp(2rem, 3vw, 2.8rem);
            font-weight: 700;
            color: #2c1a0e;
            margin: 0 0 14px;
            line-height: 1.1;
        }

        .shelter-header-info .info-row {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 14px 10px 0;
            color: #6b5744;
            font-size: 0.95rem;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(226, 217, 206, 0.9);
            border-radius: 999px;
            padding: 8px 12px;
        }

        .shelter-header-info .info-row a {
            color: #198754;
            text-decoration: none;
        }

        .shelter-header-info .info-row a:hover {
            text-decoration: underline;
        }

        .shelter-description {
            margin-top: 14px;
            padding-top: 18px;
            border-top: 1px solid rgba(226, 217, 206, 0.9);
            color: #6b5744;
            line-height: 1.75;
            font-size: 1rem;
            max-width: 64ch;
        }

        .shelter-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
            position: relative;
            z-index: 1;
        }

        .stat-box {
            text-align: left;
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(226, 217, 206, 0.9);
            border-radius: 18px;
            padding: 18px 18px 16px;
        }

        .stat-box strong {
            display: block;
            font-size: 2rem;
            color: #198754;
            font-weight: 700;
            line-height: 1;
        }

        .stat-box span {
            display: block;
            font-size: 0.82rem;
            color: #a08070;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
        }

        .sidebar-section {
            background: white;
            border: 1px solid #e2d9ce;
            border-radius: 20px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 10px 24px rgba(44, 26, 14, 0.06);
        }

        .sidebar-section h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2c1a0e;
            margin: 0 0 18px;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .info-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            color: #6b5744;
            font-size: 0.95rem;
            background: #fbf7f1;
            border: 1px solid #efe5d8;
            border-radius: 16px;
            padding: 12px;
        }

        .info-item i {
            color: #198754;
            flex-shrink: 0;
            font-size: 1rem;
            margin-top: 2px;
        }

        #shelter-map {
            width: 100%;
            height: 300px;
            border-radius: 14px;
            margin-top: 10px;
            border: 1px solid #efe5d8;
        }

        .donate-button {
            background: linear-gradient(180deg, #198754 0%, #146c43 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 999px;
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
            background: linear-gradient(180deg, #1d925b 0%, #146c43 100%);
            text-decoration: none;
            color: white;
            transform: translateY(-1px);
        }

        .animals-section {
            margin-top: 8px;
        }

        .animals-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            gap: 14px;
            flex-wrap: wrap;
        }

        .animals-header h2 {
            margin: 0;
            font-size: 1.65rem;
            font-weight: 700;
            color: #2c1a0e;
        }

        .animal-count-badge {
            background: rgba(25, 135, 84, 0.12);
            color: #146c43;
            border: 1px solid rgba(25, 135, 84, 0.18);
            padding: 8px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.88rem;
        }

        .animals-panel {
            background: white;
            border: 1px solid #e2d9ce;
            border-radius: 22px;
            padding: 20px;
            box-shadow: 0 10px 24px rgba(44, 26, 14, 0.06);
        }

        .animals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 18px;
        }

        .animal-card {
            background: linear-gradient(180deg, #ffffff 0%, #fcf8f2 100%);
            border: 2px solid #e2d9ce;
            border-radius: 24px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 20px rgba(44, 26, 14, 0.08);
            position: relative;
        }

        .animal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #198754 0%, #146c43 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .animal-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 16px 32px rgba(25, 135, 84, 0.15);
            border-color: #198754;
        }

        .animal-card:hover::before {
            opacity: 1;
        }

        .animal-card-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f0eae0 0%, #e8dfd3 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .animal-card-image::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(to top, rgba(0,0,0,0.1), transparent);
            pointer-events: none;
        }

        .animal-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            filter: brightness(0.98) saturate(1.05);
        }

        .animal-card:hover .animal-card-image img {
            transform: scale(1.08);
            filter: brightness(1) saturate(1.15);
        }

        .animal-card-image-placeholder {
            font-size: 2.5rem;
            color: #a08070;
            transition: transform 0.3s ease;
        }

        .animal-card:hover .animal-card-image-placeholder {
            transform: scale(1.1);
        }

        .animal-card-content {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: linear-gradient(180deg, #ffffff 0%, #faf8f5 100%);
        }

        .animal-card-name {
            font-weight: 700;
            font-size: 1.15rem;
            color: #2c1a0e;
            margin: 0 0 8px;
            font-family: 'Playfair Display', serif;
            transition: color 0.3s ease;
        }

        .animal-card:hover .animal-card-name {
            color: #198754;
        }

        .animal-card-info {
            font-size: 0.82rem;
            color: #a08070;
            margin: 0 0 10px;
        }

        .animal-card-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .animal-card-badges .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }

        .animal-card-meta {
            display: grid;
            gap: 8px;
            margin-bottom: 14px;
            font-size: 0.84rem;
            color: #6b5744;
        }

        .animal-card-meta span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .animal-card-meta i {
            color: #198754;
        }

        .animal-card-link {
            background: #f1f8f4;
            color: #146c43;
            border: 1px solid #cfe5d7;
            padding: 9px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin-top: auto;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .animal-card-link:hover {
            background: #198754;
            color: white;
            text-decoration: none;
            border-color: #198754;
        }

        .empty-state {
            text-align: center;
            padding: 44px 20px;
            background: linear-gradient(180deg, #ffffff 0%, #faf4eb 100%);
            border: 1px solid #e2d9ce;
            border-radius: 18px;
            color: #6b5744;
        }

        .empty-state i {
            font-size: 3rem;
            color: #a08070;
            margin-bottom: 16px;
        }

        .layout-wrapper {
            display: grid;
            grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
            gap: 24px;
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
                border-radius: 18px;
            }

            .shelter-header-top {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 20px;
            }

            .shelter-donation-card {
                width: 100%;
                min-width: 0;
            }

            .shelter-header-info h1 {
                font-size: 1.5rem;
            }

            .shelter-header-info .info-row {
                margin-right: 0;
                justify-content: center;
            }

            .shelter-stats {
                grid-template-columns: 1fr;
            }

            .animals-grid {
                grid-template-columns: 1fr;
            }

            .layout-wrapper {
                gap: 16px;
            }

            .animals-panel,
            .sidebar-section {
                padding: 18px;
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
    <a href="shelters.php" class="btn btn-outline-secondary btn-sm mb-4 shelter-back-link"><i class="bi bi-arrow-left"></i> Back to Shelters</a>

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
                <div class="section-tag"><i class="bi bi-house-heart"></i> Shelter Profile</div>
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
            <div class="shelter-donation-card">
                <div class="mini-tag"><i class="bi bi-heart-fill"></i> Donation</div>
                <h3>Help this shelter care for more animals.</h3>
                <p>Support goes toward food, treatment, and day-to-day shelter needs for the animals currently in care.</p>
                <div class="donation-points">
                    <span><i class="bi bi-bag-heart"></i> Everyday care and shelter supplies</span>
                    <span><i class="bi bi-shield-plus"></i> Treatment, recovery, and health support</span>
                </div>
                <div class="donation-meta">
                    <span><i class="bi bi-house-door"></i> Capacity: <?= (int)($shelter['capacity'] ?? 0) ?></span>
                    <span><i class="bi bi-heart"></i> <?= (int)($shelter['total_animals'] ?? 0) ?> animals listed</span>
                </div>
                <?php if ((int)($payment_summary['active_methods'] ?? 0) > 0): ?>
                    <a href="donate.php?shelter_id=<?= $shelter_id ?>" class="donate-button">Support This Shelter</a>
                <?php else: ?>
                    <p class="small text-muted mb-0">Donation methods will appear here once the shelter adds them.</p>
                <?php endif; ?>
            </div>
        </div>

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

    <div class="layout-wrapper">
        <aside>
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
            </div>

            <?php if ($has_map): ?>
                <div class="sidebar-section">
                    <h3>Location</h3>
                    <div id="shelter-map"></div>
                </div>
            <?php endif; ?>
        </aside>

        <main>
            <div class="animals-section animals-panel">
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
                                        <?= ucfirst($animal['species']) ?> | <?= ucfirst($animal['gender']) ?> | <?= ucfirst($animal['size']) ?>
                                    </p>
                                    <div class="animal-card-badges">
                                        <span class="badge <?= $collar_class ?>"><?= ucfirst($animal['collar_status']) ?></span>
                                        <span class="badge <?= $adoption_class ?>"><?= ucfirst(str_replace('_', ' ', $animal['adoption_status'])) ?></span>
                                    </div>
                                    <div class="animal-card-meta">
                                        <span><i class="bi bi-shield-check"></i> <?= $animal['is_vaccinated'] ? 'Vaccinated' : 'Vaccination not listed' ?></span>
                                        <span><i class="bi bi-clipboard2-pulse"></i> <?= $animal['is_sterilized'] ? 'Sterilized' : 'Sterilization not listed' ?></span>
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
