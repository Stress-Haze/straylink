<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

$error = '';
$success = '';
$post = null;
$edit_id = null;
$builder_data = [];

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id"));
    if (!$post) {
        redirect('posts.php');
    }
}

// Load builder data from session or database
$draft_key = 'blog_draft_' . ($edit_id ?: 'new');
if (isset($_SESSION[$draft_key])) {
    $builder_data = $_SESSION[$draft_key];
} elseif ($post) {
    $builder_data = [
        'title' => $post['title'] ?? '',
        'body' => $post['body'] ?? '',
        'status' => $post['status'] ?? 'draft',
        'cover_image' => $post['cover_image'] ?? null,
        'theme_tone' => $post['theme_tone'] ?? 'tone-1',
        'pattern_image' => $post['pattern_image'] ?? null,
    ];
} else {
    $builder_data = [
        'title' => '',
        'body' => '',
        'status' => 'draft',
        'cover_image' => null,
        'theme_tone' => 'tone-1',
        'pattern_image' => null,
    ];
}

// Get filled slots from database if available
$filled_slots = 0;
if ($post) {
    for ($i = 1; $i <= 6; $i++) {
        if (!empty($post["inline_image_$i"])) {
            $filled_slots++;
        }
    }
}

$title_value = $builder_data['title'] ?? '';
$body_value = $builder_data['body'] ?? '';
$status_value = $builder_data['status'] ?? 'draft';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $body = $_POST['body'];
    $status = sanitize($_POST['status']);
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));

    // Save post
    if (empty($title) || empty($body)) {
        $error = 'Title and body are required.';
    } else {
        $cover_image = $builder_data['cover_image'] ?? null;

        // Handle new cover image upload
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
            // Update existing post
            $slug = $slug . '-' . $edit_id;
            $update_cover = $cover_image ? "'" . mysqli_real_escape_string($conn, $cover_image) . "'" : 'cover_image';
            $query = "
                UPDATE posts SET
                    title = '" . mysqli_real_escape_string($conn, $title) . "',
                    slug = '" . mysqli_real_escape_string($conn, $slug) . "',
                    body = '" . mysqli_real_escape_string($conn, $body) . "',
                    cover_image = $update_cover,
                    status = '" . mysqli_real_escape_string($conn, $status) . "',
                    published_at = $published_at_sql
                WHERE id = $edit_id
            ";
            mysqli_query($conn, $query);
            if (mysqli_error($conn)) {
                $error = 'Database error: ' . mysqli_error($conn);
            } else {
                $success = 'Post updated successfully.';
                $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id"));
            }
        } else {
            // Create new post
            $slug = $slug . '-' . time();
            $author_id = (int) $_SESSION['member_id'];
            $query = "
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
            ";
            mysqli_query($conn, $query);
            if (mysqli_error($conn)) {
                $error = 'Database error: ' . mysqli_error($conn);
            } else {
                $success = 'Post created successfully.';
            }
        }

        if (empty($error)) {
            unset($_SESSION[$draft_key]);
            $builder_data = [
                'title' => '',
                'body' => '',
                'status' => 'draft',
                'cover_image' => null,
                'theme_tone' => 'tone-1',
                'pattern_image' => null,
            ];
            $title_value = '';
            $body_value = '';
            $status_value = 'draft';
            $filled_slots = 0;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_id ? 'Edit' : 'New' ?> Post - StrayLink Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .post-builder-card,
        .builder-summary-card {
            border: 0;
            border-radius: 24px;
            box-shadow: 0 18px 42px rgba(31, 41, 34, 0.08);
        }

        .builder-summary-card {
            background: linear-gradient(180deg, #fffdf8 0%, #f4ecde 100%);
        }

        .builder-icon {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f4c86a, #f18f5f);
            color: #fff;
            font-size: 1.35rem;
            box-shadow: 0 12px 24px rgba(241, 143, 95, 0.26);
        }

        .builder-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 0.8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            font-size: 0.88rem;
            color: #5e6157;
        }
    </style>
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
                <li class="nav-item"><a class="nav-link" href="lost_pets.php"><i class="bi bi-megaphone"></i> Lost Pets</a></li>
                <li class="nav-item"><a class="nav-link" href="shelters.php"><i class="bi bi-house"></i> Shelters</a></li>
                <li class="nav-item"><a class="nav-link active" href="posts.php"><i class="bi bi-newspaper"></i> Blog Posts</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><?= $edit_id ? 'Edit Post' : 'New Post' ?></h4>
                <a href="posts.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Posts</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?> <a href="posts.php">Back to posts</a></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
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
                                    <?php if (!empty($builder_data['cover_image']) || (!empty($post) && !empty($post['cover_image']))): ?>
                                        <div class="form-text mt-2">A styled cover image is already attached from the visual builder.</div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="draft" <?= $status_value === 'draft' ? 'selected' : '' ?>>Draft</option>
                                        <option value="published" <?= $status_value === 'published' ? 'selected' : '' ?>>Published</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <?= $edit_id ? 'Update Post' : 'Create Post' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card builder-summary-card border-0 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-info-circle"></i> About Blog Posts</h5>
                            <p class="text-muted small">Create engaging blog posts to share stories, updates, and insights with the StrayLink community. Posts can be saved as drafts before publishing.</p>
                            <div class="pt-3 border-top">
                                <p class="small text-muted mb-0"><i class="bi bi-check-circle text-success me-2"></i>Add a title and body text</p>
                                <p class="small text-muted mt-2 mb-0"><i class="bi bi-check-circle text-success me-2"></i>Upload a cover image</p>
                                <p class="small text-muted mt-2"><i class="bi bi-check-circle text-success me-2"></i>Choose draft or publish</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
