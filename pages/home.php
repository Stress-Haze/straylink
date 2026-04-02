<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$total_animals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM animals WHERE is_active = 1"))['count'];
$total_shelters = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM shelters"))['count'];
$total_adopted = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM animals WHERE adoption_status = 'adopted'"))['count'];

$featured = mysqli_query($conn, "
    SELECT a.*, s.shelter_name,
        (SELECT photo_path FROM animal_photos WHERE animal_id = a.id AND is_primary = 1 LIMIT 1) AS photo
    FROM animals a
    LEFT JOIN shelters s ON a.shelter_id = s.id
    WHERE a.is_active = 1 AND a.collar_status = 'green' AND a.adoption_status = 'available'
    ORDER BY a.created_at DESC
    LIMIT 6
");

$posts = mysqli_query($conn, "
    SELECT * FROM posts
    WHERE status = 'published'
    ORDER BY published_at DESC
    LIMIT 3
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StrayLink - Connecting Strays with Loving Homes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php
    $active_page = 'home';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<section class="hero-section">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <div class="row align-items-center g-4">
            <div class="col-lg-7 text-center text-lg-start">
                <span class="eyebrow-chip mb-3 d-inline-flex">Community animal welfare platform</span>
                <h1 class="display-4 fw-bold mb-3 text-white">Helping rescuers, shelters, and adopters care for strays together.</h1>
                <p class="lead mb-4 text-white">StrayLink brings rescue reporting, animal discovery, and community action into one place so every animal gets seen faster and supported better.</p>
                <div class="d-flex gap-3 flex-wrap justify-content-center justify-content-lg-start">
                    <a href="<?= isLoggedIn() && hasRole('user') ? 'account.php' : 'gallery.php' ?>" class="btn btn-light btn-lg text-success fw-bold">
                        <?= isLoggedIn() && hasRole('user') ? 'Open My App' : 'Browse Animals' ?>
                    </a>
                    <a href="rescue.php" class="btn btn-outline-hero btn-lg">Report a Stray</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-panel">
                    <div class="hero-panel-head">
                        <span>Live community snapshot</span>
                        <span class="status-dot"></span>
                    </div>
                    <div class="hero-panel-grid">
                        <div class="hero-panel-card">
                            <strong><?= $total_animals ?>+</strong>
                            <span>Animals listed</span>
                        </div>
                        <div class="hero-panel-card">
                            <strong><?= $total_shelters ?>+</strong>
                            <span>Partner shelters</span>
                        </div>
                        <div class="hero-panel-card">
                            <strong><?= $total_adopted ?>+</strong>
                            <span>Successful adoptions</span>
                        </div>
                        <div class="hero-panel-card">
                            <strong>24/7</strong>
                            <span>Rescue reports from the community</span>
                        </div>
                    </div>
                    <div class="hero-panel-footer">
                        <i class="bi bi-heart-pulse"></i>
                        <span>Built for fast reporting on mobile and clear discovery on desktop.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 home-intro-band">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-8">
                <div class="feature-surface h-100">
                    <p class="section-kicker mb-2">Why it feels like a community app</p>
                    <h2 class="fw-bold mb-3">One place for discovering animals, reporting cases, and following through.</h2>
                    <p class="text-muted mb-4">Instead of acting like a static website, StrayLink should help different people move quickly: adopters browse and track requests, volunteers report issues in the field, and shelters manage care and outreach.</p>
                    <div class="feature-points">
                        <div class="feature-point">
                            <i class="bi bi-phone"></i>
                            <div>
                                <strong>Mobile-first actions</strong>
                                <span>Fast rescue reporting and personal updates without desktop-only tables.</span>
                            </div>
                        </div>
                        <div class="feature-point">
                            <i class="bi bi-people"></i>
                            <div>
                                <strong>Community trust signals</strong>
                                <span>Karma, progress, and approved actions make participation visible.</span>
                            </div>
                        </div>
                        <div class="feature-point">
                            <i class="bi bi-house-heart"></i>
                            <div>
                                <strong>Real shelter pathways</strong>
                                <span>Animals, shelters, and adoption requests stay connected in one flow.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="community-note h-100">
                    <p class="section-kicker mb-2">Built for clarity</p>
                    <h3 class="fw-bold mb-3">A clearer path from discovery to action</h3>
                    <p class="text-muted mb-4">Visitors can quickly understand the mission, browse available animals, and move into the right next step without digging through crowded screens.</p>
                    <a href="<?= isLoggedIn() && hasRole('user') ? 'account.php' : '../auth/register.php' ?>" class="btn btn-success w-100">
                        <?= isLoggedIn() && hasRole('user') ? 'Go to My App' : 'Join the Community' ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">Collar Colour System</h2>
        <p class="text-center text-muted mb-5">A quick visual language that helps people understand an animal's current situation.</p>
        <div class="row g-4 justify-content-center">
            <div class="col-md-4">
                <div class="card border-success shadow-sm text-center p-4 h-100">
                    <div class="collar-dot collar-green mb-3"></div>
                    <h5 class="fw-bold text-success">Green</h5>
                    <p class="text-muted mb-0">Healthy, vaccinated, and ready for adoption.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-warning shadow-sm text-center p-4 h-100">
                    <div class="collar-dot collar-yellow mb-3"></div>
                    <h5 class="fw-bold text-warning">Yellow</h5>
                    <p class="text-muted mb-0">Needs treatment or monitoring before full placement.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-danger shadow-sm text-center p-4 h-100">
                    <div class="collar-dot collar-red mb-3"></div>
                    <h5 class="fw-bold text-danger">Red</h5>
                    <p class="text-muted mb-0">Urgent or critical case that needs immediate attention.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Animals Ready for Adoption</h2>
            <a href="gallery.php" class="btn btn-outline-success">View All</a>
        </div>
        <div class="row g-4">
        <?php
        $count = 0;
        while ($a = mysqli_fetch_assoc($featured)):
            $count++;
        ?>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <?php if ($a['photo']): ?>
                        <img src="../public/uploads/<?= htmlspecialchars($a['photo']) ?>" class="card-img-top" style="height:220px;object-fit:cover;">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height:220px;">
                            <i class="bi bi-image text-muted" style="font-size:3rem;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title mb-0"><?= htmlspecialchars($a['name'] ?? 'Unnamed') ?></h5>
                            <span class="badge bg-success">Ready</span>
                        </div>
                        <p class="text-muted small mb-2">
                            <?= ucfirst($a['species']) ?> · <?= ucfirst($a['gender']) ?> · <?= ucfirst($a['size']) ?>
                        </p>
                        <?php if ($a['shelter_name']): ?>
                            <p class="text-muted small mb-3"><i class="bi bi-house"></i> <?= htmlspecialchars($a['shelter_name']) ?></p>
                        <?php endif; ?>
                        <a href="animal.php?id=<?= $a['id'] ?>" class="btn btn-success w-100">View Profile</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        <?php if ($count === 0): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="bi bi-heart" style="font-size:3rem;"></i>
                <p class="mt-3">No animals ready for adoption yet. Check back soon.</p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center fw-bold mb-2">How StrayLink Works</h2>
        <p class="text-center text-muted mb-5">Simple steps for people who want to help.</p>
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <div class="p-4">
                    <i class="bi bi-search-heart how-icon"></i>
                    <h5 class="fw-bold mt-3">Browse</h5>
                    <p class="text-muted small">Explore animals available for adoption from shelters across Nepal.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4">
                    <i class="bi bi-telephone-forward how-icon"></i>
                    <h5 class="fw-bold mt-3">Contact</h5>
                    <p class="text-muted small">Reach out to the shelter to ask questions and plan a visit.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4">
                    <i class="bi bi-house-heart how-icon"></i>
                    <h5 class="fw-bold mt-3">Adopt</h5>
                    <p class="text-muted small">Give a stray animal a forever home and change two lives.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4">
                    <i class="bi bi-people how-icon"></i>
                    <h5 class="fw-bold mt-3">Volunteer</h5>
                    <p class="text-muted small">Join a wider network of people helping strays across Nepal.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (mysqli_num_rows($posts) > 0): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Awareness & Education</h2>
            <a href="blog.php" class="btn btn-outline-success">View All</a>
        </div>
        <div class="row g-4">
        <?php while ($p = mysqli_fetch_assoc($posts)): ?>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <?php if ($p['cover_image']): ?>
                        <img src="../public/uploads/<?= htmlspecialchars($p['cover_image']) ?>" class="card-img-top" style="height:180px;object-fit:cover;">
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?= htmlspecialchars($p['title']) ?></h5>
                        <p class="text-muted small"><?= date('M d, Y', strtotime($p['published_at'])) ?></p>
                        <a href="blog.php?slug=<?= $p['slug'] ?>" class="btn btn-outline-success btn-sm mt-auto">Read More</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
