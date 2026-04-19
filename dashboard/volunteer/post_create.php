<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('volunteer');

$error = '';
$success = '';
$post = null;
$edit_id = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $member_id = $_SESSION['member_id'];
    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id AND author_id = $member_id"));
    if (!$post) {
        redirect('index.php');
    }
}

$title_value = $post['title'] ?? '';
$body_value = $post['body'] ?? '';
$status_value = $post['status'] ?? 'draft';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $body = $_POST['body'];
    $status = sanitize($_POST['status']);
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));

    if (empty($title) || empty($body)) {
        $error = 'Title and body are required.';
    } else {
        $cover_image = $post['cover_image'] ?? null;

        if (!empty($_FILES['cover_image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed, true)) {
                $filename = 'post_' . time() . '.' . $ext;
                $upload_path = '../../public/uploads/' . $filename;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
                    $cover_image = $filename;
                }
            }
        }

        $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $published_at_sql = $published_at ? "'" . mysqli_real_escape_string($conn, $published_at) . "'" : 'NULL';

        if ($edit_id) {
            $slug = $slug . '-' . $edit_id;
            $update_cover = $cover_image ? "'" . mysqli_real_escape_string($conn, $cover_image) . "'" : 'cover_image';
            mysqli_query($conn, "
                UPDATE posts SET
                    title = '" . mysqli_real_escape_string($conn, $title) . "',
                    slug = '" . mysqli_real_escape_string($conn, $slug) . "',
                    body = '" . mysqli_real_escape_string($conn, $body) . "',
                    cover_image = $update_cover,
                    status = '" . mysqli_real_escape_string($conn, $status) . "',
                    published_at = $published_at_sql
                WHERE id = $edit_id
            ");
            $success = 'Post updated successfully.';
            $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id"));
        } else {
            $slug = $slug . '-' . time();
            $author_id = (int) $_SESSION['member_id'];
            mysqli_query($conn, "
                INSERT INTO posts (
                    author_id, title, slug, body, cover_image, status, published_at
                ) VALUES (
                    $author_id,
                    '" . mysqli_real_escape_string($conn, $title) . "',
                    '" . mysqli_real_escape_string($conn, $slug) . "',
                    '" . mysqli_real_escape_string($conn, $body) . "',
                    " . ($cover_image ? "'" . mysqli_real_escape_string($conn, $cover_image) . "'" : 'NULL') . ",
                    '" . mysqli_real_escape_string($conn, $status) . "',
                    $published_at_sql
                )
            ");
            $success = 'Post created successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_id ? 'Edit' : 'New' ?> Post - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .post-builder-card {
            border: 0;
            border-radius: 24px;
            box-shadow: 0 18px 42px rgba(31, 41, 34, 0.08);
        }
    </style>
</head>
<body>
<?php
    $dashboard_title = 'StrayLink Community';
    include '../../includes/navbar_dashboard.php';
?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="log_activity.php"><i class="bi bi-journal-plus"></i> Log Activity</a></li>
                <li class="nav-item"><a class="nav-link" href="rescue_report.php"><i class="bi bi-exclamation-triangle"></i> Report Rescue</a></li>
                <li class="nav-item"><a class="nav-link active" href="posts.php"><i class="bi bi-newspaper"></i> My Posts</a></li>
                <li class="nav-item"><a class="nav-link" href="strays.php"><i class="bi bi-geo-alt"></i> Stray Updates</a></li>
                <li class="nav-item"><a class="nav-link" href="../../pages/rescue_board.php"><i class="bi bi-broadcast"></i> Rescue Board</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><?= $edit_id ? 'Edit Post' : 'New Post' ?></h4>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">&larr; Back</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?> <a href="index.php">Back to dashboard</a></div>
            <?php endif; ?>

            <div class="card post-builder-card">
                <div class="card-body p-4 p-xl-5">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($title_value) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Body <span class="text-danger">*</span></label>
                            <textarea name="body" class="form-control" rows="12" required><?= htmlspecialchars($body_value) ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Cover Image</label>
                            <input type="file" name="cover_image" class="form-control" accept="image/*">
                            <small class="form-text text-muted">Optional cover image for your post</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= $status_value === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= $status_value === 'published' ? 'selected' : '' ?>>Publish Now</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <?= $edit_id ? 'Update Post' : 'Create Post' ?>
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
