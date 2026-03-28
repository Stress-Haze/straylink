<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

// Handle verify
if (isset($_GET['verify']) && is_numeric($_GET['verify'])) {
    $id = (int)$_GET['verify'];
    mysqli_query($conn, "UPDATE members SET is_verified = 1 WHERE id = $id");
    redirect('members.php');
}

// Handle activate/deactivate
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    mysqli_query($conn, "UPDATE members SET is_active = !is_active WHERE id = $id");
    redirect('members.php');
}

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $id   = (int)$_POST['member_id'];
    $role = sanitize($_POST['role']);
    if (in_array($role, ['admin','shelter','volunteer','user'])) {
        mysqli_query($conn, "UPDATE members SET role = '$role' WHERE id = $id");
    }
    redirect('members.php');
}

$members = mysqli_query($conn, "SELECT * FROM members ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members — StrayLink Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">🐾 StrayLink Admin</a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-white">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="members.php"><i class="bi bi-people"></i> Members</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Animals</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rescues.php"><i class="bi bi-exclamation-triangle"></i> Rescue Reports</a>
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
            <h4 class="mb-4">Members</h4>

            <div class="card shadow">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($m = mysqli_fetch_assoc($members)): ?>
                            <tr>
                                <td><?= $m['id'] ?></td>
                                <td><?= htmlspecialchars($m['full_name']) ?></td>
                                <td><?= htmlspecialchars($m['email']) ?></td>
                                <td><?= htmlspecialchars($m['phone'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($m['city'] ?? '—') ?></td>
                                <td>
                                    <!-- Role change form -->
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                        <select name="role" class="form-select form-select-sm" style="width:110px">
                                            <?php foreach (['admin','shelter','volunteer','user'] as $r): ?>
                                                <option value="<?= $r ?>" <?= $m['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="change_role" class="btn btn-sm btn-outline-primary">Set</button>
                                    </form>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $m['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $m['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                                <td>
                                    <?php if ($m['email'] !== 'admin@straylink.np'): ?>
                                        <?php if (in_array($m['role'], ['volunteer','shelter']) && !$m['is_verified']): ?>
                                            <a href="?verify=<?= $m['id'] ?>" class="btn btn-sm btn-success">Verify</a>
                                        <?php endif; ?>
                                        <a href="?toggle=<?= $m['id'] ?>" class="btn btn-sm btn-<?= $m['is_active'] ? 'danger' : 'success' ?>">
                                            <?= $m['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Protected</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
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