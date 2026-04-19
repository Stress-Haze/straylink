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

$total_animals    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM animals WHERE shelter_id = $shelter_id"))['count'];
$available        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM animals WHERE shelter_id = $shelter_id AND adoption_status = 'available'"))['count'];
$adopted          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM animals WHERE shelter_id = $shelter_id AND adoption_status = 'adopted'"))['count'];
$pending_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM adoption_requests WHERE shelter_id = $shelter_id AND status = 'pending'"))['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shelter Dashboard — StrayLink</title>
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
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Our Animals</a></li>
                <li class="nav-item"><a class="nav-link" href="animal_add.php"><i class="bi bi-plus-circle"></i> Add Animal</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="requests.php"><i class="bi bi-envelope"></i> Adoption Requests
                        <?php if ($pending_requests > 0): ?>
                            <span class="badge bg-danger"><?= $pending_requests ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item"><a class="nav-link" href="donations.php"><i class="bi bi-cash-coin"></i> Donations</a></li>
                <li class="nav-item"><a class="nav-link" href="../../pages/rescue_board.php"><i class="bi bi-broadcast"></i> Rescue Board</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-gear"></i> Shelter Profile</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h4 class="mb-4">Dashboard Overview</h4>
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-success shadow">
                        <div class="card-body">
                            <h6 class="card-title">Total Animals</h6>
                            <h2 class="text-white"><?= $total_animals ?></h2>
                            <a href="animals.php" class="text-white small">View all →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-primary shadow">
                        <div class="card-body">
                            <h6 class="card-title">Available</h6>
                            <h2 class="text-white"><?= $available ?></h2>
                            <a href="animals.php?status=available" class="text-white small">View →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info shadow">
                        <div class="card-body">
                            <h6 class="card-title">Adopted</h6>
                            <h2 class="text-white"><?= $adopted ?></h2>
                            <a href="animals.php?status=adopted" class="text-white small">View →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning shadow">
                        <div class="card-body">
                            <h6 class="card-title">Pending Requests</h6>
                            <h2 class="text-white"><?= $pending_requests ?></h2>
                            <a href="requests.php" class="text-white small">View →</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header bg-white fw-bold d-flex justify-content-between">
                    Recently Added Animals
                    <a href="animal_add.php" class="btn btn-sm btn-success">+ Add Animal</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Species</th>
                                <th>Collar</th>
                                <th>Adoption Status</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $recent = mysqli_query($conn, "SELECT * FROM animals WHERE shelter_id = $shelter_id ORDER BY created_at DESC LIMIT 5");
                        while ($a = mysqli_fetch_assoc($recent)):
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($a['name'] ?? 'Unnamed') ?></td>
                                <td><?= ucfirst($a['species']) ?></td>
                                <td>
                                    <span class="badge" style="background-color:<?= $a['collar_status'] === 'green' ? '#198754' : ($a['collar_status'] === 'yellow' ? '#ffc107' : '#dc3545') ?>">
                                        <?= ucfirst($a['collar_status']) ?>
                                    </span>
                                </td>
                                <td><?= ucfirst($a['adoption_status']) ?></td>
                                <td><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                                <td><a href="animal_edit.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($recent) === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No animals added yet</td></tr>
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
