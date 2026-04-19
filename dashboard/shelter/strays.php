<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('shelter');

$member_id = (int)$_SESSION['member_id'];
$shelter = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shelters WHERE member_id = $member_id LIMIT 1"));
if (!$shelter) redirect('setup.php');

$shelter_id = (int)$shelter['id'];
$error = $success = '';

// Claim a stray
if (isset($_GET['claim']) && is_numeric($_GET['claim'])) {
    $stray_id = (int)$_GET['claim'];
    mysqli_query($conn, "UPDATE stray_animals SET status='claimed', claimed_by_shelter_id=$shelter_id WHERE id=$stray_id AND status='active'");
    $success = "Stray claimed! You can now add them as an animal in your shelter.";
}

$strays = mysqli_query($conn, "SELECT * FROM stray_animals WHERE status='active' ORDER BY updated_at DESC");
$list = [];
while ($row = mysqli_fetch_assoc($strays)) $list[] = $row;

$claimed = mysqli_query($conn, "SELECT * FROM stray_animals WHERE claimed_by_shelter_id=$shelter_id ORDER BY updated_at DESC");
$claimed_list = [];
while ($row = mysqli_fetch_assoc($claimed)) $claimed_list[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strays — StrayLink Shelter</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<?php $dashboard_title = 'StrayLink Shelter'; include '../../includes/navbar_dashboard.php'; ?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Animals</a></li>
                <li class="nav-item"><a class="nav-link active" href="strays.php"><i class="bi bi-geo-alt"></i> Strays</a></li>
                <li class="nav-item"><a class="nav-link" href="requests.php"><i class="bi bi-inbox"></i> Requests</a></li>
                <li class="nav-item"><a class="nav-link" href="donations.php"><i class="bi bi-cash"></i> Donations</a></li>
                <li class="nav-item"><a class="nav-link" href="../../pages/rescue_board.php"><i class="bi bi-broadcast"></i> Rescue Board</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-building"></i> Shelter Profile</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <h4 class="mb-1">Community Strays</h4>
            <p class="text-muted mb-4">Browse active stray reports. Claim a stray to take them into your shelter's care.</p>

            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

            <?php if (!empty($claimed_list)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-check-circle text-success me-2"></i>Claimed by Your Shelter</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Animal</th><th>Area</th><th>Condition</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($claimed_list as $s):
                            $cc = match($s['condition_status']) {'healthy'=>'success','injured'=>'warning','critical'=>'danger',default=>'secondary'};
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['name'] ?? 'Unnamed') ?></strong><br><small class="text-muted"><?= ucfirst($s['species']) ?></small></td>
                                <td><?= htmlspecialchars($s['area_label'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= $cc ?>"><?= ucfirst($s['condition_status']) ?></span></td>
                                <td><a href="animal_add.php" class="btn btn-sm btn-success">Add to Shelter</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <h5 class="fw-bold mb-3">Available Strays</h5>
            <?php if (empty($list)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-geo-alt" style="font-size:3rem;display:block;margin-bottom:1rem;"></i>
                    <p>No active strays right now.</p>
                </div>
            <?php else: ?>
            <div class="row g-4">
            <?php foreach ($list as $s):
                $cc = match($s['condition_status']) {'healthy'=>'success','injured'=>'warning','critical'=>'danger',default=>'secondary'};
            ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card shadow-sm h-100">
                        <?php if ($s['photo']): ?>
                            <img src="../../public/uploads/<?= htmlspecialchars($s['photo']) ?>" class="card-img-top" style="height:160px;object-fit:cover;">
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <strong><?= htmlspecialchars($s['name'] ?? 'Unnamed') ?></strong>
                                <span class="badge bg-<?= $cc ?>"><?= ucfirst($s['condition_status']) ?></span>
                            </div>
                            <p class="text-muted small mb-3">
                                <?= ucfirst($s['species']) ?> · <?= ucfirst($s['approximate_age']) ?><br>
                                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($s['area_label'] ?? '—') ?>
                            </p>
                            <a href="?claim=<?= $s['id'] ?>" class="btn btn-success btn-sm mt-auto"
                               onclick="return confirm('Claim this stray for your shelter?')">
                                <i class="bi bi-plus-circle me-1"></i>Claim Stray
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
