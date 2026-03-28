<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$member_id = $_SESSION['member_id'];
$member    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM members WHERE id = $member_id"));

$error   = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name']);
    $phone     = sanitize($_POST['phone']);
    $city      = sanitize($_POST['city']);

    if (empty($full_name)) {
        $error = "Name cannot be empty.";
    } else {
        mysqli_query($conn, "
            UPDATE members SET
                full_name = '" . mysqli_real_escape_string($conn, $full_name) . "',
                phone     = '" . mysqli_real_escape_string($conn, $phone) . "',
                city      = '" . mysqli_real_escape_string($conn, $city) . "'
            WHERE id = $member_id
        ");
        $_SESSION['full_name'] = $full_name;
        $success = "Profile updated successfully!";
        $member  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM members WHERE id = $member_id"));
    }
}

// Handle blog post submission (karma >= 10 only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
    if (!canPostBlog($member['karma'])) {
        $error = "You need at least 10 karma to post blogs.";
    } else {
        $title  = sanitize($_POST['title']);
        $body   = $_POST['body'];
        $slug   = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-')) . '-' . time();

        if (empty($title) || empty($body)) {
            $error = "Title and body are required.";
        } else {
            $cover_image = null;
            if (!empty($_FILES['cover_image']['name'])) {
                $ext     = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                $allowed = ['jpg','jpeg','png','webp'];
                if (in_array(strtolower($ext), $allowed)) {
                    $filename    = 'post_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['cover_image']['tmp_name'], '../public/uploads/' . $filename);
                    $cover_image = $filename;
                }
            }

            $cover_val = $cover_image ? "'" . mysqli_real_escape_string($conn, $cover_image) . "'" : 'NULL';
            mysqli_query($conn, "
                INSERT INTO posts (author_id, title, slug, body, cover_image, status)
                VALUES ($member_id,
                    '" . mysqli_real_escape_string($conn, $title) . "',
                    '" . mysqli_real_escape_string($conn, $slug) . "',
                    '" . mysqli_real_escape_string($conn, $body) . "',
                    $cover_val,
                    'draft'
                )
            ");
            $success = "Blog post submitted for admin review!";
        }
    }
}

// Fetch adoption requests
$requests = mysqli_query($conn, "
    SELECT ar.*, a.name AS animal_name, a.species, a.collar_status,
           s.shelter_name, s.contact_number
    FROM adoption_requests ar
    JOIN animals a  ON ar.animal_id  = a.id
    LEFT JOIN shelters s ON ar.shelter_id = s.id
    WHERE ar.member_id = $member_id
    ORDER BY ar.created_at DESC
");

// Fetch my blog posts if karma >= 10
$my_posts = null;
if (canPostBlog($member['karma'])) {
    $my_posts = mysqli_query($conn, "
        SELECT * FROM posts WHERE author_id = $member_id ORDER BY created_at DESC
    ");
}

$karma = $member['karma'];
$level = getKarmaLevel($karma);
$next  = getNextMilestone($karma);
$progress = $next ? min(100, round(($karma / $next['target']) * 100)) : 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<?php
    $active_page = 'account';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Left Column: Profile + Karma -->
        <div class="col-md-4">

            <!-- Profile Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold bg-white">
                    <i class="bi bi-person-circle text-success"></i> My Profile
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control"
                                value="<?= htmlspecialchars($member['full_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control"
                                value="<?= htmlspecialchars($member['email']) ?>" disabled>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control"
                                value="<?= htmlspecialchars($member['phone'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control"
                                value="<?= htmlspecialchars($member['city'] ?? '') ?>">
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-success w-100">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Karma Card -->
            <div class="card shadow-sm">
                <div class="card-header fw-bold bg-white">
                    <i class="bi bi-star text-warning"></i> Karma & Milestones
                </div>
                <div class="card-body text-center">
                    <div style="font-size:2.5rem;"><?= $level['icon'] ?></div>
                    <h3 class="fw-bold mt-2"><?= $karma ?> <small class="fs-6 text-muted">karma</small></h3>
                    <span class="badge bg-<?= $level['color'] ?> mb-3"><?= $level['label'] ?></span>

                    <?php if ($next): ?>
                        <p class="text-muted small mb-2">Next: <strong><?= $next['label'] ?></strong></p>
                        <div class="progress mb-2" style="height:10px;border-radius:50px;">
                            <div class="progress-bar bg-success" style="width:<?= $progress ?>%"></div>
                        </div>
                        <small class="text-muted"><?= $karma ?> / <?= $next['target'] ?> karma</small>
                    <?php else: ?>
                        <p class="text-success fw-bold">🏆 Maximum level reached!</p>
                    <?php endif; ?>

                    <hr>
                    <div class="text-start small text-muted">
                        <p class="mb-1">🌱 <strong>0</strong> — Newcomer (browse & adopt)</p>
                        <p class="mb-1">🌿 <strong>10</strong> — Contributor (post blogs)</p>
                        <p class="mb-1">⭐ <strong>25</strong> — Trusted member</p>
                        <p class="mb-0">🏆 <strong>50</strong> — Champion</p>
                    </div>
                    <hr>
                    <div class="text-start small text-muted">
                        <p class="mb-1"><strong>How to earn karma:</strong></p>
                        <p class="mb-1">+5 — Rescue report approved</p>
                        <p class="mb-0">+10 — Adoption request approved</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Requests + Blog -->
        <div class="col-md-8">

            <!-- Adoption Requests -->
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold bg-white">
                    <i class="bi bi-heart text-success"></i> My Adoption Requests
                </div>
                <div class="card-body p-0">
                    <?php if (mysqli_num_rows($requests) === 0): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-heart mb-2" style="font-size:2rem;display:block;"></i>
                            No adoption requests yet. <a href="gallery.php">Browse animals →</a>
                        </div>
                    <?php else: ?>
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Animal</th>
                                <th>Shelter</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($r = mysqli_fetch_assoc($requests)): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($r['animal_name'] ?? 'Unnamed') ?></strong><br>
                                    <small class="text-muted"><?= ucfirst($r['species']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($r['shelter_name'] ?? '—') ?></td>
                                <td>
                                    <?php if ($r['contact_number']): ?>
                                        <a href="tel:<?= $r['contact_number'] ?>" class="text-success">
                                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($r['contact_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $r['status'] === 'approved'  ? 'success' : 
                                        ($r['status'] === 'rejected'  ? 'danger' : 
                                        ($r['status'] === 'cancelled' ? 'secondary' : 'warning')) ?>">
                                        <?= ucfirst($r['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Blog Post Submission -->
            <?php if (canPostBlog($karma)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold bg-white">
                    <i class="bi bi-pencil text-success"></i> Write a Blog Post
                    <span class="badge bg-success ms-2">Unlocked</span>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required
                                placeholder="e.g. Why I adopted a stray dog">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea name="body" class="form-control" rows="6" required
                                placeholder="Share your story, experience or awareness message..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cover Image <small class="text-muted">(optional)</small></label>
                            <input type="file" name="cover_image" class="form-control" accept="image/*">
                        </div>
                        <div class="alert alert-info small mb-3">
                            <i class="bi bi-info-circle"></i> Your post will be reviewed by admin before going live.
                        </div>
                        <button type="submit" name="submit_post" class="btn btn-success">Submit Post</button>
                    </form>
                </div>
            </div>

            <!-- My Posts -->
            <?php if ($my_posts && mysqli_num_rows($my_posts) > 0): ?>
            <div class="card shadow-sm">
                <div class="card-header fw-bold bg-white">
                    <i class="bi bi-newspaper text-success"></i> My Blog Posts
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($p = mysqli_fetch_assoc($my_posts)): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['title']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $p['status'] === 'published' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <!-- Locked blog -->
            <div class="card shadow-sm border-dashed">
                <div class="card-body text-center py-4 text-muted">
                    <i class="bi bi-lock mb-2" style="font-size:2rem;display:block;"></i>
                    <strong>Blog posting is locked</strong>
                    <p class="small mt-2 mb-0">Reach <strong>10 karma</strong> to unlock. Report strays and get adoptions approved to earn karma.</p>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>