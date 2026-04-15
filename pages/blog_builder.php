<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$member_id = $_SESSION['member_id'];
$member = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM members WHERE id = $member_id"));

// Check if user is admin or has blog posting permission
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if (!$is_admin && !canPostBlog($member['karma'])) {
    redirect('account.php');
}

$error = '';
$success = '';
$post = null;
$edit_id = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    // Admins can edit any post, regular users can only edit their own
    if ($is_admin) {
        $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id"));
    } else {
        $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id AND author_id = $member_id"));
    }
    if (!$post) {
        redirect('account.php');
    }
}

$title_value = $post['title'] ?? '';
$body_value = $post['body'] ?? '';
$status_value = $post['status'] ?? 'draft';
$bg_color = $post['bg_color'] ?? '#ffffff';
$bg_pattern = $post['bg_pattern'] ?? 'none';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $body = $_POST['body'];
    $status = sanitize($_POST['status']);
    $bg_color = sanitize($_POST['bg_color'] ?? '#ffffff');
    $bg_pattern = sanitize($_POST['bg_pattern'] ?? 'none');
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
                $upload_path = '../public/uploads/' . $filename;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
                    $cover_image = $filename;
                }
            }
        }

        if (empty($error)) {
            $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;
            $published_at_sql = $published_at ? "'" . mysqli_real_escape_string($conn, $published_at) . "'" : 'NULL';

            if ($edit_id) {
                // If editing and new images/changes were made, set status to pending for review
                if (!empty($_FILES['cover_image']['name'])) {
                    $status = 'pending';
                }
                $slug = $slug . '-' . $edit_id;
                $update_cover = $cover_image ? "'" . mysqli_real_escape_string($conn, $cover_image) . "'" : 'cover_image';
                
                $update_query = "
                    UPDATE posts SET
                        title = '" . mysqli_real_escape_string($conn, $title) . "',
                        slug = '" . mysqli_real_escape_string($conn, $slug) . "',
                        body = '" . mysqli_real_escape_string($conn, $body) . "',
                        cover_image = $update_cover,
                        status = '" . mysqli_real_escape_string($conn, $status) . "',
                        published_at = $published_at_sql,
                        bg_color = '" . mysqli_real_escape_string($conn, $bg_color) . "',
                        bg_pattern = '" . mysqli_real_escape_string($conn, $bg_pattern) . "'
                    WHERE id = $edit_id
                ";
                $result = mysqli_query($conn, $update_query);
                if (!$result) {
                    $error = 'Database error: ' . mysqli_error($conn);
                } else {
                    $status_msg = 'Blog post updated successfully.';
                    if (!empty($_FILES['cover_image']['name'])) {
                        $status_msg .= ' ✓ Cover image updated. Changes sent for admin review.';
                        // Status already set to pending above
                    }
                    $success = $status_msg;
                    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM posts WHERE id = $edit_id"));
                }
            } else {
                $slug = $slug . '-' . time();
                
                $insert_query = "
                    INSERT INTO posts (
                        author_id, title, slug, body, cover_image, status, published_at, bg_color, bg_pattern
                    ) VALUES (
                        $member_id,
                        '" . mysqli_real_escape_string($conn, $title) . "',
                        '" . mysqli_real_escape_string($conn, $slug) . "',
                        '" . mysqli_real_escape_string($conn, $body) . "',
                        " . ($cover_image ? "'" . mysqli_real_escape_string($conn, $cover_image) . "'" : 'NULL') . ",
                        '" . mysqli_real_escape_string($conn, $status) . "',
                        $published_at_sql,
                        '" . mysqli_real_escape_string($conn, $bg_color) . "',
                        '" . mysqli_real_escape_string($conn, $bg_pattern) . "'
                    )
                ";
                $result = mysqli_query($conn, $insert_query);
                if (!$result) {
                    $error = 'Database error: ' . mysqli_error($conn);
                } else {
                    $success = 'Blog post created successfully.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_id ? 'Edit' : 'Create' ?> Blog with Builder - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .blog-builder-container {
            max-width: 850px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .blog-editor-wrapper {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            align-items: start;
        }

        .sticker-panel {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .blog-main-content {
            background: white;
            border: 1px solid #e2d9ce;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #2c1a0e;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2d9ce;
        }

        .customization-panel {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2d9ce;
        }

        .customization-panel h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #2c1a0e;
            margin-bottom: 16px;
        }

        .color-palette {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin: 16px 0 30px 0;
        }

        .color-swatch {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 10px;
            border: 3px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .color-swatch:hover {
            transform: scale(1.05);
            border-color: #198754;
        }

        .color-swatch input[type="radio"] {
            display: none;
        }

        .color-swatch input[type="radio"]:checked + div {
            border: 3px solid #198754;
            box-shadow: 0 0 0 2px white, 0 0 0 4px #198754;
        }

        .pattern-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .pattern-option {
            background: white;
            border: 2px solid #e2d9ce;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pattern-option:hover {
            border-color: #198754;
        }

        .pattern-option input[type="radio"] {
            display: none;
        }

        .pattern-option input[type="radio"]:checked + label {
            color: #198754;
            font-weight: 700;
        }

        .pattern-option label {
            cursor: pointer;
            margin: 0;
            display: block;
            font-size: 0.9rem;
        }

        .preview-bg {
            width: 100%;
            height: 180px;
            border-radius: 12px;
            border: 1px solid #e2d9ce;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b5744;
            font-weight: 600;
        }

        @media (max-width: 1200px) {
            .blog-editor-wrapper {
                grid-template-columns: 1fr;
            }
        }

        .submit-section {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .submit-section button,
        .submit-section a {
            flex: 1;
        }
    </style>
</head>
<body>

<?php
    $active_page = 'account';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container-fluid py-5">
    <div class="blog-builder-container">
        <div class="mb-4">
            <a href="account.php" class="btn btn-outline-secondary btn-sm">← Back to Account</a>
            <h1 class="fw-bold mt-3"><?= $edit_id ? 'Edit' : 'Create' ?> Blog Post</h1>
            <p class="text-muted">Design a beautiful blog post with stickers, customization, and more.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="blog-editor-wrapper">
                <!-- MAIN CONTENT -->
                <div class="blog-main-content">
                    <div class="form-section">
                        <h3>Blog Title</h3>
                        <input type="text" name="title" class="form-control form-control-lg" required value="<?= htmlspecialchars($title_value) ?>" placeholder="Give your blog an interesting title">
                    </div>

                    <div class="form-section">
                        <h3>Blog Content</h3>
                        <textarea name="body" class="form-control" rows="8" required placeholder="Share your story, thoughts, or awareness message..."><?= htmlspecialchars($body_value) ?></textarea>
                    </div>

                    <div class="form-section">
                        <h3>Cover Image</h3>
                        <input type="file" name="cover_image" class="form-control" accept="image/*">
                        <small class="text-muted">JPG, PNG, or WEBP</small>
                        <?php if ($post && $post['cover_image']): ?>
                            <div class="mt-2">
                                <small class="text-muted">Current cover:</small>
                                <img src="../public/uploads/<?= htmlspecialchars($post['cover_image']) ?>" style="max-width: 200px; max-height: 150px; border-radius: 8px; margin-top: 0.5rem;">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="customization-panel">
                        <h3>Customize Your Blog</h3>
                        
                        <label class="form-label fw-bold">Background Color</label>
                        <div class="color-palette">
                            <label class="color-swatch" style="background: #ffffff; border-color: #e2d9ce;" title="Pure White">
                                <input type="radio" name="bg_color" value="#ffffff" <?= $bg_color === '#ffffff' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #fff4e6; border-color: #e2d9ce;" title="Warm Cream">
                                <input type="radio" name="bg_color" value="#fff4e6" <?= $bg_color === '#fff4e6' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #ffb347; border-color: #e2d9ce;" title="Peachy Orange">
                                <input type="radio" name="bg_color" value="#ffb347" <?= $bg_color === '#ffb347' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #90ee90; border-color: #e2d9ce;" title="Fresh Mint">
                                <input type="radio" name="bg_color" value="#90ee90" <?= $bg_color === '#90ee90' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #87ceeb; border-color: #e2d9ce;" title="Sky Blue">
                                <input type="radio" name="bg_color" value="#87ceeb" <?= $bg_color === '#87ceeb' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #dda0dd; border-color: #e2d9ce;" title="Plum Purple">
                                <input type="radio" name="bg_color" value="#dda0dd" <?= $bg_color === '#dda0dd' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #ff6b9d; border-color: #e2d9ce;" title="Coral Pink">
                                <input type="radio" name="bg_color" value="#ff6b9d" <?= $bg_color === '#ff6b9d' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #ffd700; border-color: #e2d9ce;" title="Golden Yellow">
                                <input type="radio" name="bg_color" value="#ffd700" <?= $bg_color === '#ffd700' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #ffb6c1; border-color: #e2d9ce;" title="Light Pink">
                                <input type="radio" name="bg_color" value="#ffb6c1" <?= $bg_color === '#ffb6c1' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #98d8c8; border-color: #e2d9ce;" title="Teal Green">
                                <input type="radio" name="bg_color" value="#98d8c8" <?= $bg_color === '#98d8c8' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #f7dc6f; border-color: #e2d9ce;" title="Butter Yellow">
                                <input type="radio" name="bg_color" value="#f7dc6f" <?= $bg_color === '#f7dc6f' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                            <label class="color-swatch" style="background: #bb86fc; border-color: #e2d9ce;" title="Vivid Purple">
                                <input type="radio" name="bg_color" value="#bb86fc" <?= $bg_color === '#bb86fc' ? 'checked' : '' ?>>
                                <div></div>
                            </label>
                        </div>

                        <label class="form-label fw-bold mt-4">Background Pattern</label>
                        <div class="pattern-grid">
                            <div class="pattern-option">
                                <label>
                                    <input type="radio" name="bg_pattern" value="none" <?= $bg_pattern === 'none' ? 'checked' : '' ?>>
                                    None
                                </label>
                            </div>
                            <div class="pattern-option">
                                <label>
                                    <input type="radio" name="bg_pattern" value="hearts" <?= $bg_pattern === 'hearts' ? 'checked' : '' ?>>
                                    ❤️ Hearts
                                </label>
                            </div>
                            <div class="pattern-option">
                                <label>
                                    <input type="radio" name="bg_pattern" value="stars" <?= $bg_pattern === 'stars' ? 'checked' : '' ?>>
                                    ⭐ Stars
                                </label>
                            </div>
                            <div class="pattern-option">
                                <label>
                                    <input type="radio" name="bg_pattern" value="dots" <?= $bg_pattern === 'dots' ? 'checked' : '' ?>>
                                    • Dots
                                </label>
                            </div>
                            <div class="pattern-option">
                                <label>
                                    <input type="radio" name="bg_pattern" value="paws" <?= $bg_pattern === 'paws' ? 'checked' : '' ?>>
                                    🐾 Paws
                                </label>
                            </div>
                            <div class="pattern-option">
                                <label>
                                    <input type="radio" name="bg_pattern" value="flowers" <?= $bg_pattern === 'flowers' ? 'checked' : '' ?>>
                                    🌸 Flowers
                                </label>
                            </div>
                            <div class="pattern-option">
                                <label>
                                    <input type="radio" name="bg_pattern" value="bones" <?= $bg_pattern === 'bones' ? 'checked' : '' ?>>
                                    🦴 Bones
                                </label>
                            </div>
                            <div class="pattern-option">
                                <label>
                                    <input type="radio" name="bg_pattern" value="sparkles" <?= $bg_pattern === 'sparkles' ? 'checked' : '' ?>>
                                    ✨ Sparkles
                                </label>
                            </div>
                            <div class="pattern-option">
                                <label>
                                    <input type="radio" name="bg_pattern" value="waves" <?= $bg_pattern === 'waves' ? 'checked' : '' ?>>
                                    〰️ Waves
                                </label>
                            </div>
                            <div class="pattern-option">
                                <label>
                                    <input type="radio" name="bg_pattern" value="checkerboard" <?= $bg_pattern === 'checkerboard' ? 'checked' : '' ?>>
                                    ▦ Checkers
                                </label>
                            </div>
                        </div>

                        <div class="preview-bg" id="previewBg">Preview</div>
                    </div>

                    <div class="form-section">
                        <label class="form-label">Post Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?= $status_value === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= $status_value === 'published' ? 'selected' : '' ?>>Publish</option>
                        </select>
                        <small class="text-muted">Published posts are reviewed by admin before going live.</small>
                    </div>

                    <div class="submit-section">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle me-2"></i> <?= $edit_id ? 'Update Post' : 'Create Post' ?>
                        </button>
                        <a href="account.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const patterns = {
        'none': '',
        'hearts': '❤️ ❤️ ❤️',
        'stars': '⭐ ⭐ ⭐',
        'dots': '• • • • • • •',
        'paws': '🐾 🐾 🐾',
        'flowers': '🌸 🌸 🌸',
        'bones': '🦴 🦴 🦴',
        'sparkles': '✨ ✨ ✨',
        'waves': '〰️ 〰️ 〰️',
        'checkerboard': '▦ ▦ ▦'
    };

    document.querySelectorAll('input[name="bg_color"], input[name="bg_pattern"]').forEach(input => {
        input.addEventListener('change', updatePreview);
    });

    function updatePreview() {
        const color = document.querySelector('input[name="bg_color"]:checked').value;
        const pattern = document.querySelector('input[name="bg_pattern"]:checked').value;
        const preview = document.getElementById('previewBg');
        
        preview.style.backgroundColor = color;
        preview.textContent = patterns[pattern] || 'Preview';
        preview.style.fontSize = pattern === 'none' ? '1rem' : '1.8rem';
        preview.style.opacity = pattern === 'none' ? '0.6' : '0.8';
    }

    updatePreview();
</script>
</body>
</html>
