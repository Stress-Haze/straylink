<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Single post view
$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';
$post = null;

if ($slug) {
    $post = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT p.*, m.full_name AS author_name
        FROM posts p
        JOIN members m ON p.author_id = m.id
        WHERE p.slug = '" . mysqli_real_escape_string($conn, $slug) . "' AND p.status = 'published'
    "));
    if (!$post) redirect('blog.php');
}

// All posts
$posts = mysqli_query($conn, "
    SELECT p.*, m.full_name AS author_name
    FROM posts p
    JOIN members m ON p.author_id = m.id
    WHERE p.status = 'published'
    ORDER BY p.published_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $post ? htmlspecialchars($post['title']) . ' — ' : '' ?>Blog — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<?php
    $active_page = 'blog';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">

<?php if ($post): ?>
    <!-- Single Post View -->
    <a href="blog.php" class="btn btn-outline-secondary btn-sm mb-4">← Back to Blog</a>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if ($post['cover_image']): ?>
                <img src="../public/uploads/<?= $post['cover_image'] ?>" class="img-fluid rounded mb-4" style="width:100%;height:350px;object-fit:cover;">
            <?php endif; ?>
            <h1 class="fw-bold mb-2"><?= htmlspecialchars($post['title']) ?></h1>
            <p class="text-muted mb-4">
                By <?= htmlspecialchars($post['author_name']) ?> · 
                <?= date('M d, Y', strtotime($post['published_at'])) ?>
            </p>
            <div class="post-body">
                <?= nl2br(htmlspecialchars($post['body'])) ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Blog Listing -->
    <h2 class="fw-bold mb-4">Awareness & Education</h2>
    <div class="row g-4">
    <?php 
    $count = 0;
    while ($p = mysqli_fetch_assoc($posts)): 
        $count++;
    ?>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <?php if ($p['cover_image']): ?>
                    <img src="../public/uploads/<?= $p['cover_image'] ?>" class="card-img-top" style="height:200px;object-fit:cover;">
                <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:200px;">
                        <i class="bi bi-newspaper text-muted" style="font-size:3rem;"></i>
                    </div>
                <?php endif; ?>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><?= htmlspecialchars($p['title']) ?></h5>
                    <p class="text-muted small mb-3">
                        By <?= htmlspecialchars($p['author_name']) ?> · 
                        <?= date('M d, Y', strtotime($p['published_at'])) ?>
                    </p>
                    <a href="blog.php?slug=<?= $p['slug'] ?>" class="btn btn-outline-success mt-auto">Read More</a>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
    <?php if ($count === 0): ?>
        <div class="col-12 text-center text-muted py-5">
            <i class="bi bi-newspaper" style="font-size:3rem;"></i>
            <p class="mt-3">No posts published yet — check back soon!</p>
        </div>
    <?php endif; ?>
    </div>
<?php endif; ?>

</div>

<!-- Footer -->
<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>