<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('shelter');

$member_id = $_SESSION['member_id'];
$shelter   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shelters WHERE member_id = $member_id"));
if (!$shelter) redirect('setup.php');
$shelter_id = $shelter['id'];

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM animals WHERE id = $id AND shelter_id = $shelter_id");
    redirect('animals.php');
}

// Filter
$filter  = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$where   = "WHERE a.shelter_id = $shelter_id";
$allowed = ['available','reserved','adopted','not_available'];
if (in_array($filter, $allowed)) $where .= " AND a.adoption_status = '$filter'";

$animals = mysqli_query($conn, "
    SELECT a.* FROM animals a
    $where
    ORDER BY a.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Animals — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<?php
    $dashboard_title = $shelter['shelter_name'] ?? 'StrayLink Shelter';
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
                    <a class="nav-link active" href="animals.php"><i class="bi bi-heart"></i> Our Animals</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="animal_add.php"><i class="bi bi-plus-circle"></i> Add Animal</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="requests.php"><i class="bi bi-envelope"></i> Adoption Requests</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php"><i class="bi bi-gear"></i> Shelter Profile</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Our Animals</h4>
                <a href="animal_add.php" class="btn btn-success"><i class="bi bi-plus"></i> Add Animal</a>
            </div>

            <!-- Filter Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="?status=all">All</a>
                </li>
                <?php foreach ($allowed as $s): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $filter === $s ? 'active' : '' ?>" href="?status=<?= $s ?>">
                            <?= ucfirst($s) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="card shadow">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Species</th>
                                <th>Gender</th>
                                <th>Collar</th>
                                <th>Adoption</th>
                                <th>Vaccinated</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($a = mysqli_fetch_assoc($animals)): ?>
                            <?php
                            $photo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT photo_path FROM animal_photos WHERE animal_id = {$a['id']} AND is_primary = 1 LIMIT 1"));
                            ?>
                            <tr>
                                <td>
                                    <?php if ($photo): ?>
                                        <img src="../../public/uploads/<?= $photo['photo_path'] ?>" width="50" height="50" style="object-fit:cover;border-radius:4px;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="width:50px;height:50px;border-radius:4px;">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a['name'] ?? 'Unnamed') ?></td>
                                <td><?= ucfirst($a['species']) ?></td>
                                <td><?= ucfirst($a['gender']) ?></td>
                                <td>
                                    <span class="badge" style="background-color:<?= $a['collar_status'] === 'green' ? '#198754' : ($a['collar_status'] === 'yellow' ? '#ffc107' : '#dc3545') ?>">
                                        <?= ucfirst($a['collar_status']) ?>
                                    </span>
                                </td>
                                <td><?= ucfirst($a['adoption_status']) ?></td>
                                <td>
                                    <?php if ($a['is_vaccinated']): ?>
                                        <span class="badge bg-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                                <td class="d-flex gap-1">
                                    <a href="animal_edit.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <a href="animal_health.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-info">Health</a>
                                    <a href="?delete=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this animal?')">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($animals) === 0): ?>
                            <tr><td colspan="9" class="text-center text-muted py-3">No animals found</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>