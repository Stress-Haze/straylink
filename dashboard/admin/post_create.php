<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

$error   = '';
$success = '';
$post    = null;
$edit_id = null;

// Load post for editing
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $post    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id"));
    if (!$post) redirect('posts.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title  = sanitize($_POST['title']);
    $body   = $_POST['body'];
    $status = sanitize($_POST['status']);
    $slug   = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));

    if (empty($title) || empty($body)) {
        $error = "Title and body are required.";
    } else {
        // Handle cover image upload
        $cover_image = $post['cover_image'] ?? null;
        if (!empty($_FILES['cover_image']['name'])) {
            $ext      = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            $allowed  = ['jpg','jpeg','png','webp'];
            if (in_array(strtolower($ext), $allowed)) {
                $filename    = 'post_' . time() . '.' . $ext;
                $upload_path = '../../public/uploads/' . $filename;
                move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path);
                $cover_image = $filename;
            }
        }

        $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $published_at_sql = $published_at ? "'$published_at'" : "NULL";

        if ($edit_id) {
            // Make slug unique if title changed
            $slug = $slug . '-' . $edit_id;
            mysqli_query($conn, "
                UPDATE posts SET 
                    title = '" . mysqli_real_escape_string($conn, $title) . "',
                    slug  = '" . mysqli_real_escape_string($conn, $slug) . "',
                    body  = '" . mysqli_real_escape_string($conn, $body) . "',
                    cover_image  = " . ($cover_image ? "'" . mysqli_real_escape_string($conn, $cover_image) . "'" : "cover_image") . ",
                    status       = '$status',
                    published_at = $published_at_sql
                WHERE id = $edit_id
            ");
            $success = "Post updated successfully.";
            $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id"));
        } else {
            // Make slug unique
            $slug = $slug . '-' . time();
            $author_id = $_SESSION['member_id'];
            mysqli_query($conn, "
                INSERT INTO posts (author_id, title, slug, body, cover_image, status, published_at)
                VALUES ($author_id, 
                    '" . mysqli_real_escape_string($conn, $title) . "',
                    '" . mysqli_real_escape_string($conn, $slug) . "',
                    '" . mysqli_real_escape_string($conn, $body) . "',
                    " . ($cover_image ? "'" . mysqli_real_escape_string($conn, $cover_image) . "'" : "NULL") . ",
                    '$status',
                    $published_at_sql
                )
            ");
            $success = "Post created successfully.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_id ? 'Edit' : 'New' ?> Post — StrayLink Admin</title>
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
                <h4 class="mb-0"><?= $edit_id ? 'Edit Post' : 'New Post' ?></h4>
                <a href="posts.php" class="btn btn-outline-secondary btn-sm">← Back to Posts</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?> <a href="posts.php">Back to posts</a></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                value="<?= htmlspecialchars($post['title'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Body <span class="text-danger">*</span></label>
                            <textarea name="body" class="form-control" rows="12" required><?= htmlspecialchars($post['body'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cover Image</label>
                            <?php if (!empty($post['cover_image'])): ?>
                                <div class="mb-2">
                                    <img src="../../public/uploads/<?= $post['cover_image'] ?>" height="80" style="border-radius:4px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="cover_image" class="form-control" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <?= $edit_id ? 'Update Post' : 'Create Post' ?>
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>