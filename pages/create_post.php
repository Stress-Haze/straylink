<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$member_id = $_SESSION['member_id'];
$member = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM members WHERE id = $member_id"));

if (!canPostBlog($member['karma'])) {
    redirect('account.php');
}

$error = '';
$success = '';
$post = null;
$edit_id = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id AND author_id = $member_id"));
    if (!$post) {
        redirect('account.php');
    }
}

$title_value = $post['title'] ?? '';
$body_value = $post['body'] ?? '';
$status_value = $post['status'] ?? 'draft';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $body = sanitize($_POST['body']);
    $status = sanitize($_POST['status']);
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));

    if (empty($title) || empty($body)) {
        $error = 'Title and body are required.';
    } else {
        $cover_image = $post['cover_image'] ?? null;
        $has_new_cover = false;

        if (!empty($_FILES['cover_image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed, true)) {
                $filename = 'post_' . time() . '.' . $ext;
                $upload_path = '../public/uploads/' . $filename;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
                    $cover_image = $filename;
                    $has_new_cover = true;
                }
            }
        }

        // All user posts go to pending for admin review
        $final_status = 'pending';
        $published_at_sql = 'NULL';

        if ($edit_id) {
            // If editing existing post
            if ($has_new_cover) {
                $final_status = 'pending';
            } else {
                // Keep existing status unless title/body changed (then require review)
                $existing_post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM posts WHERE id = $edit_id"));
                if ($existing_post['status'] === 'published' && ($title !== $post['title'] || $body !== $post['body'])) {
                    $final_status = 'pending';
                } else {
                    $final_status = $existing_post['status'];
                    if ($final_status === 'published') {
                        $published_at_sql = "'" . mysqli_real_escape_string($conn, date('Y-m-d H:i:s')) . "'";
                    }
                }
            }
            $slug = $slug . '-' . $edit_id;
            $update_cover = $cover_image ? "'" . mysqli_real_escape_string($conn, $cover_image) . "'" : 'cover_image';
            mysqli_query($conn, "
                UPDATE posts SET
                    title = '" . mysqli_real_escape_string($conn, $title) . "',
                    slug = '" . mysqli_real_escape_string($conn, $slug) . "',
                    body = '" . mysqli_real_escape_string($conn, $body) . "',
                    cover_image = $update_cover,
                    status = '" . mysqli_real_escape_string($conn, $final_status) . "',
                    published_at = $published_at_sql
                WHERE id = $edit_id
            ");
            $success = $final_status === 'pending' ? 'Post updated and sent for admin review.' : 'Post updated successfully.';
            $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id"));
        } else {
            // New post - always pending
            $slug = $slug . '-' . time();
            mysqli_query($conn, "
                INSERT INTO posts (
                    author_id, title, slug, body, cover_image, status, published_at
                ) VALUES (
                    $member_id,
                    '" . mysqli_real_escape_string($conn, $title) . "',
                    '" . mysqli_real_escape_string($conn, $slug) . "',
                    '" . mysqli_real_escape_string($conn, $body) . "',
                    " . ($cover_image ? "'" . mysqli_real_escape_string($conn, $cover_image) . "'" : 'NULL') . ",
                    'pending',
                    NULL
                )
            ");
            $success = 'Post created and sent for admin review. It will appear once approved.';
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
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .post-creator-card {
            border: 0;
            border-radius: 24px;
            box-shadow: 0 18px 42px rgba(31, 41, 34, 0.08);
        }
    </style>

</head>
<body>
<?php
    $active_page = 'account';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
    <div class="mb-4">
        <h4 class="mb-0"><?= $edit_id ? 'Edit Post' : 'Create a Blog Post' ?></h4>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?> <a href="blog.php">View on blog</a></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card post-creator-card">
                <div class="card-body p-4 p-lg-5">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control form-control-lg" required value="<?= htmlspecialchars($title_value) ?>" placeholder="Give your post a good title...">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Story <span class="text-danger">*</span></label>
                            <textarea name="body" class="form-control" rows="12" required placeholder="Share your thoughts, experiences, and stories..."><?= htmlspecialchars($body_value) ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Cover Image</label>
                            <input type="file" name="cover_image" class="form-control" accept="image/*">
                            <small class="text-muted">Optional image to represent your post</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Publish Status</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= $status_value === 'draft' ? 'selected' : '' ?>>Save as Draft</option>
                                <option value="published" disabled>Submit for Review (Admin approval required)</option>
                            </select>
                            <small class="text-muted">All posts require admin review before publishing. Drafts are private.</small>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-1"></i> <?= $edit_id ? 'Update Post' : 'Create Post' ?>
                            </button>
                            <a href="account.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4" style="border: 0; border-radius: 16px;">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-palette text-info"></i> Blog Builder
                    </h5>
                    <p class="text-muted small mb-3">Want to make your blog more visually interesting? Use our advanced Blog Builder to add stickers, customize colors, and create a unique design.</p>
                    <a href="<?= $edit_id ? 'blog_builder.php?edit=' . $edit_id : 'blog_builder.php' ?>" class="btn btn-info w-100">
                        <i class="bi bi-palette me-2"></i> <?= $edit_id ? 'Switch to Builder' : 'Open Builder' ?>
                    </a>
                </div>
            </div>

            <div class="card shadow-sm" style="border: 0; border-radius: 16px;">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-info-circle text-success"></i> Tips
                    </h5>
                    <ul class="small text-muted mb-0">
                        <li>Use clear, engaging titles</li>
                        <li>Share personal experiences</li>
                        <li>Add a cover image for visual appeal</li>
                        <li>Use the Blog Builder for advanced styling</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
