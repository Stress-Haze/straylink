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

// Handle approve — award karma to adopter
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $id  = (int)$_GET['approve'];
    $req = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM adoption_requests WHERE id = $id AND shelter_id = $shelter_id"));
    if ($req) {
        mysqli_query($conn, "UPDATE adoption_requests SET status = 'approved' WHERE id = $id");
        mysqli_query($conn, "UPDATE animals SET adoption_status = 'reserved' WHERE id = {$req['animal_id']}");
        addKarma($conn, $req['member_id'], 10);
    }
    redirect('requests.php');
}

if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    mysqli_query($conn, "UPDATE adoption_requests SET status = 'rejected' WHERE id = $id AND shelter_id = $shelter_id");
    redirect('requests.php');
}

$filter  = isset($_GET['status']) ? sanitize($_GET['status']) : 'pending';
$allowed = ['pending','approved','rejected','cancelled'];
if (!in_array($filter, $allowed)) $filter = 'pending';

$requests = mysqli_query($conn, "
    SELECT ar.*, a.name AS animal_name, a.species, m.full_name, m.phone, m.email, m.karma
    FROM adoption_requests ar
    JOIN animals a  ON ar.animal_id  = a.id
    JOIN members m  ON ar.member_id  = m.id
    WHERE ar.shelter_id = $shelter_id AND ar.status = '$filter'
    ORDER BY ar.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adoption Requests — StrayLink</title>
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
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Our Animals</a></li>
                <li class="nav-item"><a class="nav-link" href="animal_add.php"><i class="bi bi-plus-circle"></i> Add Animal</a></li>
                <li class="nav-item"><a class="nav-link active" href="requests.php"><i class="bi bi-envelope"></i> Adoption Requests</a></li>
                <li class="nav-item"><a class="nav-link" href="donations.php"><i class="bi bi-cash-coin"></i> Donations</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-gear"></i> Shelter Profile</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h4 class="mb-4">Adoption Requests</h4>

            <ul class="nav nav-tabs mb-4">
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
                                <th>#</th>
                                <th>Animal</th>
                                <th>Requested By</th>
                                <th>Contact</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($r = mysqli_fetch_assoc($requests)): ?>
                            <tr>
                                <td><?= $r['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($r['animal_name'] ?? 'Unnamed') ?></strong><br>
                                    <small class="text-muted"><?= ucfirst($r['species']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($r['full_name']) ?>
                                    <br><small class="text-muted"><?= $r['karma'] ?> karma</small>
                                </td>
                                <td>
                                    <small>
                                        <?= htmlspecialchars($r['email']) ?><br>
                                        <?= htmlspecialchars($r['phone'] ?? '—') ?>
                                    </small>
                                </td>
                                <td><small><?= htmlspecialchars(substr($r['message'] ?? '—', 0, 80)) ?></small></td>
                                <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <a href="?approve=<?= $r['id'] ?>" class="btn btn-sm btn-success">Approve +10 karma</a>
                                        <a href="?reject=<?= $r['id'] ?>" class="btn btn-sm btn-danger">Reject</a>
                                    <?php else: ?>
                                        <span class="badge bg-<?= $r['status'] === 'approved' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($r['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($requests) === 0): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">No <?= $filter ?> requests</td></tr>
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
