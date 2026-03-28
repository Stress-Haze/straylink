<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM posts WHERE id = $id");
    redirect('posts.php');
}

// Handle publish/unpublish
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM posts WHERE id = $id"));
    if ($post) {
        $new_status = $post['status'] === 'published' ? 'draft' : 'published';
        $published_at = $new_status === 'published' ? "NOW()" : "NULL";
        mysqli_query($conn, "UPDATE posts SET status = '$new_status', published_at = $published_at WHERE id = $id");
    }
    redirect('posts.php');
}

$posts = mysqli_query($conn, "
    SELECT p.*, m.full_name
    FROM posts p
    JOIN members m ON p.author_id = m.id
    ORDER BY p.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Posts — StrayLink Admin</title>
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
                    <a class="nav-link" href="shelters.php"><i class="bi bi-house"></i> Shelters</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="posts.php"><i class="bi bi-newspaper"></i> Blog Posts</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Blog Posts</h4>
                <a href="post_create.php" class="btn btn-success">
                    <i class="bi bi-plus"></i> New Post
                </a>
            </div>

            <div class="card shadow">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Cover</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Status</th>
                                <th>Published</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($p = mysqli_fetch_assoc($posts)): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td>
                                    <?php if ($p['cover_image']): ?>
                                        <img src="../../public/uploads/<?= $p['cover_image'] ?>" width="60" height="40" style="object-fit:cover; border-radius:4px;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="width:60px;height:40px;border-radius:4px;">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($p['title']) ?></strong><br>
                                    <small class="text-muted">/<?= htmlspecialchars($p['slug']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($p['full_name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $p['status'] === 'published' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $p['published_at'] ? date('M d, Y', strtotime($p['published_at'])) : '—' ?>
                                </td>
                                <td class="d-flex gap-1">
                                    <a href="post_create.php?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <a href="?toggle=<?= $p['id'] ?>" class="btn btn-sm btn-<?= $p['status'] === 'published' ? 'warning' : 'success' ?>">
                                        <?= $p['status'] === 'published' ? 'Unpublish' : 'Publish' ?>
                                    </a>
                                    <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this post?')">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($posts) === 0): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">No blog posts yet</td></tr>
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