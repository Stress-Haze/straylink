<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM shelters WHERE id = $id");
    redirect('shelters.php');
}

$shelters = mysqli_query($conn, "
    SELECT s.*, m.full_name, m.email, m.is_active
    FROM shelters s
    JOIN members m ON s.member_id = m.id
    ORDER BY s.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shelters — StrayLink Admin</title>
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
                    <a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Animals</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rescues.php"><i class="bi bi-exclamation-triangle"></i> Rescue Reports</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="lost_pets.php"><i class="bi bi-megaphone"></i> Lost Pets</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="shelters.php"><i class="bi bi-house"></i> Shelters</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="posts.php"><i class="bi bi-newspaper"></i> Blog Posts</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <h4 class="mb-4">Shelters</h4>

            <div class="card shadow">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Logo</th>
                                <th>Shelter Name</th>
                                <th>Account Owner</th>
                                <th>Email</th>
                                <th>City</th>
                                <th>Contact</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($s = mysqli_fetch_assoc($shelters)): ?>
                            <tr>
                                <td><?= $s['id'] ?></td>
                                <td>
                                    <?php if ($s['logo']): ?>
                                        <img src="../../public/uploads/<?= $s['logo'] ?>" width="50" height="50" style="object-fit:cover; border-radius:4px;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="width:50px;height:50px;border-radius:4px;">
                                            <i class="bi bi-house text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($s['shelter_name']) ?></td>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><?= htmlspecialchars($s['email']) ?></td>
                                <td><?= htmlspecialchars($s['city'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($s['contact_number'] ?? '—') ?></td>
                                <td><?= $s['capacity'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $s['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this shelter?')">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($shelters) === 0): ?>
                            <tr><td colspan="10" class="text-center text-muted py-3">No shelters registered yet</td></tr>
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
