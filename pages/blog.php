<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

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

$posts = mysqli_query($conn, "
    SELECT p.*, m.full_name AS author_name
    FROM posts p
    JOIN members m ON p.author_id = m.id
    WHERE p.status = 'published'
    ORDER BY p.published_at DESC
");

$post_items = [];
while ($row = mysqli_fetch_assoc($posts)) {
    $post_items[] = $row;
}

$render_blog_body = static function ($post) {
    $chunks = preg_split('/(\[image[1-6]\])/', (string)$post['body'], -1, PREG_SPLIT_DELIM_CAPTURE);
    $tones = ['blog-tone-1', 'blog-tone-2', 'blog-tone-3'];
    $tone_index = 0;
    $output = '';

    foreach ($chunks as $chunk) {
        if (preg_match('/^\[image([1-6])\]$/', $chunk, $matches)) {
            $image_key = 'inline_image_' . $matches[1];
            $image_file = $post[$image_key] ?? null;

            if ($image_file) {
                $tone = $tones[$tone_index % count($tones)];
                $output .= '<figure class="blog-inline-figure ' . $tone . '">';
                $output .= '<img src="../public/uploads/' . htmlspecialchars($image_file) . '" alt="Inline image for ' . htmlspecialchars($post['title']) . '">';
                $output .= '</figure>';
                $tone_index++;
            }
            continue;
        }

        $trimmed = trim($chunk);
        if ($trimmed === '') {
            continue;
        }

        $tone = $tones[$tone_index % count($tones)];
        $output .= '<section class="blog-body-panel ' . $tone . '">';
        $output .= nl2br(htmlspecialchars($trimmed));
        $output .= '</section>';
        $tone_index++;
    }

    return $output;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $post ? htmlspecialchars($post['title']) . ' - ' : '' ?>Blog - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .blog-tone-1 {
            background: linear-gradient(180deg, #fff9ef 0%, #f8f0e1 100%);
        }

        .blog-tone-2 {
            background: linear-gradient(180deg, #f3fbf6 0%, #e5f3ea 100%);
        }

        .blog-tone-3 {
            background: linear-gradient(180deg, #f5f7ff 0%, #e8edfb 100%);
        }

        .blog-inline-figure {
            margin: 1.5rem 0;
            padding: 0.9rem;
            border-radius: 22px;
            border: 1px solid rgba(60, 81, 68, 0.08);
        }

        .blog-inline-figure img {
            width: 100%;
            max-height: 420px;
            object-fit: cover;
            border-radius: 16px;
            display: block;
        }

        .blog-body-panel {
            border-radius: 22px;
            padding: 1.15rem 1.25rem;
            margin: 1rem 0;
            border: 1px solid rgba(60, 81, 68, 0.08);
        }

        .blog-card-accent-1 .card-body {
            background: linear-gradient(180deg, #fffdf8 0%, #f7f0e7 100%);
        }

        .blog-card-accent-2 .card-body {
            background: linear-gradient(180deg, #f4fbf6 0%, #e9f4ec 100%);
        }

        .blog-card-accent-3 .card-body {
            background: linear-gradient(180deg, #f7f8ff 0%, #edf0fb 100%);
        }
    </style>
</head>
<body>

<?php
    $active_page = 'blog';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
<?php if ($post): ?>
    <a href="blog.php" class="btn btn-outline-secondary btn-sm mb-4">Back to Blog</a>
    <section class="blog-post-shell mx-auto">
        <?php if ($post['cover_image']): ?>
            <img src="../public/uploads/<?= htmlspecialchars($post['cover_image']) ?>" class="img-fluid blog-post-cover mb-4" alt="Cover image for <?= htmlspecialchars($post['title']) ?>">
        <?php endif; ?>
        <div class="blog-post-meta mb-3">
            <span class="active-filter-pill">Community story</span>
            <span class="text-muted">By <?= htmlspecialchars($post['author_name']) ?></span>
            <span class="text-muted"><?= date('M d, Y', strtotime($post['published_at'])) ?></span>
        </div>
        <h1 class="fw-bold mb-3"><?= htmlspecialchars($post['title']) ?></h1>
        <div class="post-body rich-post-body">
            <?= $render_blog_body($post) ?>
        </div>
    </section>
<?php else: ?>
    <section class="blog-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="section-kicker mb-2">Community stories</p>
                <h1 class="fw-bold mb-3">Awareness, adoption stories, and education from the StrayLink community.</h1>
                <p class="text-muted mb-4">This page helps the app feel alive. It shows that the platform is not only for transactions, but also for trust-building, advocacy, and shared knowledge.</p>
                <div class="gallery-chip-row">
                    <span class="gallery-chip"><i class="bi bi-journal-richtext"></i> Stories</span>
                    <span class="gallery-chip"><i class="bi bi-heart"></i> Adoption experiences</span>
                    <span class="gallery-chip"><i class="bi bi-lightbulb"></i> Awareness and tips</span>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="gallery-summary-card">
                    <div>
                        <span class="summary-label">Why this matters</span>
                        <strong>Community content makes the product feel active, trusted, and human.</strong>
                    </div>
                    <div>
                        <span class="summary-label">What this adds</span>
                        <strong>Stories and updates help people connect with the shelters, animals, and the wider mission behind the platform.</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1">Latest Posts</h2>
            <p class="text-muted"><?= count($post_items) ?> published stories from the community.</p>
        </div>
        <?php if (isLoggedIn() && hasRole('user')): ?>
            <a href="account.php" class="btn btn-outline-success">Write from My App</a>
        <?php endif; ?>
    </div>

    <div class="row g-4">
    <?php foreach ($post_items as $index => $p): ?>
        <?php $accent_class = 'blog-card-accent-' . (($index % 3) + 1); ?>
        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100 blog-card <?= $accent_class ?>">
                <?php if ($p['cover_image']): ?>
                    <img src="../public/uploads/<?= htmlspecialchars($p['cover_image']) ?>" class="card-img-top" style="height:220px;object-fit:cover;" alt="Cover image for <?= htmlspecialchars($p['title']) ?>">
                <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:220px;">
                        <i class="bi bi-newspaper text-muted" style="font-size:3rem;"></i>
                    </div>
                <?php endif; ?>
                <div class="card-body d-flex flex-column">
                    <div class="blog-post-meta mb-3">
                        <span class="text-muted"><?= date('M d, Y', strtotime($p['published_at'])) ?></span>
                        <span class="text-muted">By <?= htmlspecialchars($p['author_name']) ?></span>
                    </div>
                    <h5 class="card-title mb-3"><?= htmlspecialchars($p['title']) ?></h5>
                    <p class="text-muted small mb-4">Tap in to read the full story and learn more from the community.</p>
                    <a href="blog.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-outline-success mt-auto">Read More</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (count($post_items) === 0): ?>
        <div class="col-12 text-center text-muted py-5">
            <i class="bi bi-newspaper" style="font-size:3rem;"></i>
            <p class="mt-3">No posts published yet. Check back soon.</p>
        </div>
    <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
