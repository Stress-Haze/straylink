<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

mysqli_query($conn, "UPDATE lost_pet_posts SET status = 'expired' WHERE status IN ('active', 'sighted') AND expires_at < NOW()");

if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM lost_pet_posts WHERE id = $id LIMIT 1"));
    if ($post && in_array($post['status'], ['', 'pending', 'expired'], true)) {
        $old_status = mysqli_real_escape_string($conn, $post['status']);
        $admin_id = (int)$_SESSION['member_id'];
        mysqli_query($conn, "UPDATE lost_pet_posts SET status = 'active', visibility = 'public', admin_note = NULL WHERE id = $id");
        mysqli_query($conn, "
            INSERT INTO lost_pet_status_history (lost_pet_post_id, changed_by_member_id, old_status, new_status, note)
            VALUES ($id, $admin_id, '$old_status', 'active', 'Admin approved poster')
        ");
    }
    redirect('lost_pets.php');
}

if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM lost_pet_posts WHERE id = $id LIMIT 1"));
    if ($post && in_array($post['status'], ['', 'pending', 'expired'], true)) {
        $old_status = mysqli_real_escape_string($conn, $post['status']);
        $admin_id = (int)$_SESSION['member_id'];
        mysqli_query($conn, "UPDATE lost_pet_posts SET status = 'rejected', visibility = 'hidden' WHERE id = $id");
        mysqli_query($conn, "
            INSERT INTO lost_pet_status_history (lost_pet_post_id, changed_by_member_id, old_status, new_status, note)
            VALUES ($id, $admin_id, '$old_status', 'rejected', 'Admin rejected poster')
        ");
    }
    redirect('lost_pets.php');
}

$filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'pending';
$allowed = ['pending', 'active', 'sighted', 'expired', 'found', 'closed', 'rejected'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'pending';
}

if ($filter === 'pending') {
    $where_clause = "(p.status = 'pending' OR p.status = '' OR (p.visibility = 'hidden' AND p.status = 'active'))";
} else {
    $where_clause = "p.status = '" . mysqli_real_escape_string($conn, $filter) . "'";
}

$posts = mysqli_query($conn, "
    SELECT p.*, m.full_name, m.email
    FROM lost_pet_posts p
    JOIN members m ON p.member_id = m.id
    WHERE $where_clause
    ORDER BY p.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Pet Posters - StrayLink Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<?php
    $dashboard_title = 'StrayLink Admin';
    include '../../includes/navbar_dashboard.php';
?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="members.php"><i class="bi bi-people"></i> Members</a></li>
                <li class="nav-item"><a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Animals</a></li>
                <li class="nav-item"><a class="nav-link" href="rescues.php"><i class="bi bi-exclamation-triangle"></i> Rescue Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="../../pages/rescue_board.php"><i class="bi bi-broadcast"></i> Rescue Board</a></li>
                <li class="nav-item"><a class="nav-link" href="strays.php"><i class="bi bi-geo-alt"></i> Strays</a></li>
                <li class="nav-item"><a class="nav-link active" href="lost_pets.php"><i class="bi bi-megaphone"></i> Lost Pets</a></li>
                <li class="nav-item"><a class="nav-link" href="shelters.php"><i class="bi bi-house"></i> Shelters</a></li>
                <li class="nav-item"><a class="nav-link" href="posts.php"><i class="bi bi-newspaper"></i> Blog Posts</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <div class="mb-4">
                <h4 class="mb-1">Lost Pet Posters</h4>
                <p class="text-muted mb-0">Review new posters before they go to the public board.</p>
            </div>

            <ul class="nav nav-tabs mb-4">
                <?php foreach ($allowed as $status_name): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $filter === $status_name ? 'active' : '' ?>" href="?status=<?= $status_name ?>">
                            <?= ucfirst($status_name) ?>
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
                                <th>Poster</th>
                                <th>Owner</th>
                                <th>Last Seen</th>
                                <th>Submitted</th>
                                <th>Visibility</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = mysqli_fetch_assoc($posts)): ?>
                            <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="../../public/uploads/<?= htmlspecialchars($row['poster_image']) ?>" width="64" height="64" style="object-fit:cover;border-radius:10px;" alt="<?= htmlspecialchars($row['pet_name']) ?>">
                                        <div>
                                            <strong><?= htmlspecialchars($row['pet_name']) ?></strong>
                                            <div class="small text-muted"><?= htmlspecialchars($row['breed'] ?: ucfirst($row['species'])) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($row['city']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['full_name']) ?>
                                    <div class="small text-muted"><?= htmlspecialchars($row['email']) ?></div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($row['last_seen_label']) ?></div>
                                    <div class="small text-muted"><?= date('M d, Y g:i A', strtotime($row['last_seen_at'])) ?></div>
                                </td>
                                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <span class="badge <?= $row['visibility'] === 'public' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ucfirst($row['visibility']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <a href="../../pages/lost_pet.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php if (in_array($row['status'], ['', 'pending', 'expired'], true) || ($row['visibility'] === 'hidden' && $row['status'] === 'active')): ?>
                                            <a href="?approve=<?= (int)$row['id'] ?>" class="btn btn-sm btn-success">Approve</a>
                                            <a href="?reject=<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger">Reject</a>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border align-self-center"><?= htmlspecialchars(ucfirst($row['status'] ?: 'pending')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($posts) === 0): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">No <?= htmlspecialchars($filter) ?> lost-pet posters</td></tr>
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
