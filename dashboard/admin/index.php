<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

$total_animals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM animals"))['count'];
$active_animals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM animals WHERE is_active = 1"))['count'];
$total_members = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM members"))['count'];
$active_members = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM members WHERE is_active = 1"))['count'];
$total_shelters = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM shelters"))['count'];
$verified_shelters = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM members WHERE role = 'shelter' AND is_verified = 1"))['count'];
$volunteers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM members WHERE role = 'volunteer'"))['count'];
$verified_volunteers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM members WHERE role = 'volunteer' AND is_verified = 1"))['count'];
$pending_rescues = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rescue_reports WHERE status = 'pending'"))['count'];
$pending_adoptions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM adoption_requests WHERE status = 'pending'"))['count'];
$pending_donations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM donations WHERE status = 'pending'"))['count'];
$pending_lost_pets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM lost_pet_posts WHERE status = 'pending' OR status = '' OR (visibility = 'hidden' AND status = 'active')"))['count'];
$published_posts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM posts WHERE status = 'published'"))['count'];

$recent_reports = mysqli_query($conn, "
    SELECT r.*, m.full_name
    FROM rescue_reports r
    JOIN members m ON r.reported_by = m.id
    ORDER BY r.created_at DESC
    LIMIT 5
");

$recent_lost_pets = mysqli_query($conn, "
    SELECT p.id, p.pet_name, p.status, p.visibility, p.created_at, p.city, m.full_name
    FROM lost_pet_posts p
    JOIN members m ON p.member_id = m.id
    ORDER BY p.created_at DESC
    LIMIT 5
");

$recent_members = mysqli_query($conn, "
    SELECT full_name, email, role, is_active, created_at
    FROM members
    ORDER BY created_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .admin-hero {
            background:
                radial-gradient(circle at top right, rgba(34, 139, 94, 0.14), transparent 34%),
                linear-gradient(135deg, #fffdf8 0%, #f4f0e6 100%);
            border: 1px solid rgba(55, 93, 72, 0.1);
            border-radius: 26px;
            padding: 2rem;
            box-shadow: 0 24px 50px rgba(33, 52, 40, 0.08);
        }

        .admin-kicker {
            text-transform: uppercase;
            letter-spacing: 0.16em;
            font-size: 0.78rem;
            font-weight: 700;
            color: #567a64;
            margin-bottom: 0.7rem;
        }

        .admin-hero-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0.9rem;
        }

        .admin-hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.8rem 1rem;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(86, 122, 100, 0.12);
            border-radius: 16px;
            font-weight: 600;
            color: #3c4d42;
        }

        .admin-metric-card {
            border: 0;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(36, 45, 39, 0.08);
            min-height: 100%;
        }

        .admin-metric-card .card-body {
            padding: 1.35rem;
        }

        .admin-metric-card .metric-label {
            display: block;
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.78;
            margin-bottom: 0.5rem;
        }

        .admin-metric-card .metric-value {
            display: block;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.45rem;
        }

        .admin-metric-card .metric-subtext {
            display: block;
            font-size: 0.92rem;
            opacity: 0.92;
        }

        .admin-metric-card a {
            color: inherit;
            text-decoration: none;
            font-weight: 700;
        }

        .admin-queue-card,
        .admin-panel {
            border: 0;
            border-radius: 22px;
            box-shadow: 0 18px 40px rgba(36, 45, 39, 0.08);
        }

        .admin-queue-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.95rem 0;
            border-bottom: 1px solid rgba(88, 107, 94, 0.12);
        }

        .admin-queue-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .admin-queue-item:first-child {
            padding-top: 0;
        }

        .admin-panel .table {
            margin-bottom: 0;
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
                <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="members.php"><i class="bi bi-people"></i> Members</a></li>
                <li class="nav-item"><a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Animals</a></li>
                <li class="nav-item"><a class="nav-link" href="rescues.php"><i class="bi bi-exclamation-triangle"></i> Rescue Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="lost_pets.php"><i class="bi bi-megaphone"></i> Lost Pets</a></li>
                <li class="nav-item"><a class="nav-link" href="shelters.php"><i class="bi bi-house"></i> Shelters</a></li>
                <li class="nav-item"><a class="nav-link" href="posts.php"><i class="bi bi-newspaper"></i> Blog Posts</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <section class="admin-hero mb-4">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-7">
                        <p class="admin-kicker">Admin control center</p>
                        <h2 class="fw-bold mb-2">Track platform health, moderation queues, and recent activity from one place.</h2>
                        <p class="text-muted mb-0">This dashboard gives you a fast view of members, shelter operations, rescue intake, lost-pet moderation, and content activity without jumping between modules.</p>
                    </div>
                    <div class="col-lg-5">
                        <div class="admin-hero-stats">
                            <span class="admin-hero-chip"><i class="bi bi-heart"></i> <?= $active_animals ?> active animals</span>
                            <span class="admin-hero-chip"><i class="bi bi-people"></i> <?= $active_members ?> active members</span>
                            <span class="admin-hero-chip"><i class="bi bi-house-heart"></i> <?= $verified_shelters ?>/<?= $total_shelters ?> shelters verified</span>
                            <span class="admin-hero-chip"><i class="bi bi-person-badge"></i> <?= $verified_volunteers ?>/<?= $volunteers ?> volunteers verified</span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card admin-metric-card text-white" style="background: linear-gradient(135deg, #2f8f69 0%, #236f51 100%);">
                        <div class="card-body">
                            <span class="metric-label">Animals</span>
                            <span class="metric-value"><?= $total_animals ?></span>
                            <span class="metric-subtext"><?= $active_animals ?> currently visible on the platform</span>
                            <div class="mt-3"><a href="animals.php">Open animals</a></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card admin-metric-card text-white" style="background: linear-gradient(135deg, #2a79c9 0%, #1e5f9d 100%);">
                        <div class="card-body">
                            <span class="metric-label">Members</span>
                            <span class="metric-value"><?= $total_members ?></span>
                            <span class="metric-subtext"><?= $volunteers ?> volunteers and <?= $total_shelters ?> shelter accounts</span>
                            <div class="mt-3"><a href="members.php">Manage members</a></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card admin-metric-card text-white" style="background: linear-gradient(135deg, #dd8a1d 0%, #b86a07 100%);">
                        <div class="card-body">
                            <span class="metric-label">Pending Rescue Intake</span>
                            <span class="metric-value"><?= $pending_rescues ?></span>
                            <span class="metric-subtext">New field reports waiting for admin action</span>
                            <div class="mt-3"><a href="rescues.php">Review rescues</a></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card admin-metric-card text-white" style="background: linear-gradient(135deg, #7a4dbb 0%, #57328b 100%);">
                        <div class="card-body">
                            <span class="metric-label">Published Stories</span>
                            <span class="metric-value"><?= $published_posts ?></span>
                            <span class="metric-subtext">Public blog and awareness content currently live</span>
                            <div class="mt-3"><a href="posts.php">Open blog posts</a></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-5">
                    <div class="card admin-queue-card h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">Action Queue</h5>
                            <div class="admin-queue-item">
                                <div>
                                    <strong>Lost pet approvals</strong>
                                    <div class="small text-muted">Posters waiting to be reviewed before public release</div>
                                </div>
                                <a href="lost_pets.php?status=pending" class="btn btn-outline-primary btn-sm"><?= $pending_lost_pets ?></a>
                            </div>
                            <div class="admin-queue-item">
                                <div>
                                    <strong>Adoption requests</strong>
                                    <div class="small text-muted">Applications that still need shelter action</div>
                                </div>
                                <a href="../shelter/requests.php?status=pending" class="btn btn-outline-primary btn-sm"><?= $pending_adoptions ?></a>
                            </div>
                            <div class="admin-queue-item">
                                <div>
                                    <strong>Donation verifications</strong>
                                    <div class="small text-muted">Submitted donations waiting for shelter confirmation</div>
                                </div>
                                <a href="../shelter/donations.php" class="btn btn-outline-primary btn-sm"><?= $pending_donations ?></a>
                            </div>
                            <div class="admin-queue-item">
                                <div>
                                    <strong>Pending rescues</strong>
                                    <div class="small text-muted">Urgent rescue reports still in intake review</div>
                                </div>
                                <a href="rescues.php?status=pending" class="btn btn-outline-primary btn-sm"><?= $pending_rescues ?></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card admin-panel h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">Recent Rescue Reports</h5>
                                <a href="rescues.php" class="btn btn-sm btn-outline-success">View all</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Reported By</th>
                                            <th>Urgency</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($recent_reports)): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['title']) ?></td>
                                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $row['urgency'] === 'critical' ? 'danger' : ($row['urgency'] === 'high' ? 'warning' : 'secondary') ?>">
                                                    <?= ucfirst($row['urgency']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $row['status'] === 'pending' ? 'warning' : 'success' ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if (mysqli_num_rows($recent_reports) === 0): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No rescue reports yet</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card admin-panel h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">Recent Lost Pet Posters</h5>
                                <a href="lost_pets.php" class="btn btn-sm btn-outline-success">Open queue</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Pet</th>
                                            <th>Owner</th>
                                            <th>City</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($recent_lost_pets)): ?>
                                        <?php $lost_pet_status = $row['status'] === '' ? 'pending' : $row['status']; ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['pet_name']) ?></td>
                                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td><?= htmlspecialchars($row['city']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $lost_pet_status === 'pending' ? 'warning' : ($lost_pet_status === 'active' ? 'success' : 'secondary') ?>">
                                                    <?= ucfirst($lost_pet_status) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if (mysqli_num_rows($recent_lost_pets) === 0): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No lost-pet posters yet</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card admin-panel h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">Newest Members</h5>
                                <a href="members.php" class="btn btn-sm btn-outline-success">Manage members</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($member = mysqli_fetch_assoc($recent_members)): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($member['full_name']) ?></td>
                                            <td><?= htmlspecialchars($member['email']) ?></td>
                                            <td><span class="badge text-bg-light border"><?= ucfirst($member['role']) ?></span></td>
                                            <td>
                                                <span class="badge bg-<?= $member['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $member['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($member['created_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if (mysqli_num_rows($recent_members) === 0): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No members yet</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-none">
            <h4 class="mb-4">Dashboard Overview</h4>
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-success shadow">
                        <div class="card-body">
                            <h6 class="card-title">Total Animals</h6>
                            <h2 class="text-white"><?= $total_animals ?></h2>
                            <a href="animals.php" class="text-white small">View all →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-primary shadow">
                        <div class="card-body">
                            <h6 class="card-title">Total Members</h6>
                            <h2 class="text-white"><?= $total_members ?></h2>
                            <a href="members.php" class="text-white small">View all →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info shadow">
                        <div class="card-body">
                            <h6 class="card-title">Shelters</h6>
                            <h2 class="text-white"><?= $total_shelters ?></h2>
                            <a href="shelters.php" class="text-white small">View all →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning shadow">
                        <div class="card-body">
                            <h6 class="card-title">Pending Rescues</h6>
                            <h2 class="text-white"><?= $pending_rescues ?></h2>
                            <a href="rescues.php" class="text-white small">View all →</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header bg-white fw-bold">Recent Rescue Reports</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Reported By</th>
                                <th>Urgency</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $reports = mysqli_query($conn, "
                            SELECT r.*, m.full_name 
                            FROM rescue_reports r 
                            JOIN members m ON r.reported_by = m.id 
                            ORDER BY r.created_at DESC 
                            LIMIT 5
                        ");
                        while ($row = mysqli_fetch_assoc($reports)):
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $row['urgency'] === 'critical' ? 'danger' : ($row['urgency'] === 'high' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($row['urgency']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $row['status'] === 'pending' ? 'warning' : 'success' ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td><a href="rescues.php" class="btn btn-sm btn-outline-success">View</a></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($reports) === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No rescue reports yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
