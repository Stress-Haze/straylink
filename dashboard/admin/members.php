<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('admin');

// Handle verify
if (isset($_GET['verify']) && is_numeric($_GET['verify'])) {
    $id = (int)$_GET['verify'];
    mysqli_query($conn, "UPDATE members SET is_verified = 1 WHERE id = $id");
    redirect('members.php');
}

// Handle activate/deactivate
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    mysqli_query($conn, "UPDATE members SET is_active = !is_active WHERE id = $id");
    redirect('members.php');
}

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $id   = (int)$_POST['member_id'];
    $role = sanitize($_POST['role']);
    if (in_array($role, ['admin','shelter','volunteer','user'])) {
        mysqli_query($conn, "UPDATE members SET role = '$role' WHERE id = $id");
    }
    redirect('members.php');
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$verification_filter = isset($_GET['verification']) ? sanitize($_GET['verification']) : '';

$allowed_roles = ['admin', 'shelter', 'volunteer', 'user'];
$allowed_statuses = ['active', 'inactive'];
$allowed_verification = ['verified', 'unverified'];

$where_parts = ['1=1'];

if ($search !== '') {
    $like = mysqli_real_escape_string($conn, '%' . $search . '%');
    $where_parts[] = "(full_name LIKE '$like' OR email LIKE '$like' OR phone LIKE '$like' OR city LIKE '$like')";
}

if (in_array($role_filter, $allowed_roles, true)) {
    $where_parts[] = "role = '" . mysqli_real_escape_string($conn, $role_filter) . "'";
}

if (in_array($status_filter, $allowed_statuses, true)) {
    $where_parts[] = $status_filter === 'active' ? "is_active = 1" : "is_active = 0";
}

if (in_array($verification_filter, $allowed_verification, true)) {
    $where_parts[] = $verification_filter === 'verified' ? "is_verified = 1" : "is_verified = 0";
}

$members = mysqli_query($conn, "
    SELECT *
    FROM members
    WHERE " . implode(' AND ', $where_parts) . "
    ORDER BY created_at DESC
");

$member_items = [];
$role_counts = ['admin' => 0, 'shelter' => 0, 'volunteer' => 0, 'user' => 0];
$active_count = 0;

while ($row = mysqli_fetch_assoc($members)) {
    $member_items[] = $row;
    if (isset($role_counts[$row['role']])) {
        $role_counts[$row['role']]++;
    }
    if ((int)$row['is_active'] === 1) {
        $active_count++;
    }
}

$active_filters = [];
if ($search) $active_filters[] = 'Search: ' . $search;
if ($role_filter) $active_filters[] = 'Role: ' . ucfirst($role_filter);
if ($status_filter) $active_filters[] = 'Status: ' . ucfirst($status_filter);
if ($verification_filter) $active_filters[] = 'Verification: ' . ucfirst($verification_filter);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members — StrayLink Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<?php
    $dashboard_title = 'StrayLink Admin';
    include '../../includes/navbar_dashboard.php';
?>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="members.php"><i class="bi bi-people"></i> Members</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Animals</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rescues.php"><i class="bi bi-exclamation-triangle"></i> Rescue Reports</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="lost_pets.php"><i class="bi bi-megaphone"></i> Lost Pets</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="shelters.php"><i class="bi bi-house"></i> Shelters</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="posts.php"><i class="bi bi-newspaper"></i> Blog Posts</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Members</h4>
                    <p class="text-muted mb-0">Search and filter members by role, account status, and verification state.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light border px-3 py-2"><?= count($member_items) ?> shown</span>
                    <span class="badge text-bg-light border px-3 py-2"><?= $active_count ?> active</span>
                    <span class="badge text-bg-light border px-3 py-2"><?= $role_counts['shelter'] ?> shelters</span>
                    <span class="badge text-bg-light border px-3 py-2"><?= $role_counts['volunteer'] ?> volunteers</span>
                </div>
            </div>

            <form method="GET" class="card shadow-sm p-3 mb-4 filter-surface">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label small fw-bold">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, email, phone, or city" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small fw-bold">Role</label>
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="shelter" <?= $role_filter === 'shelter' ? 'selected' : '' ?>>Shelter</option>
                            <option value="volunteer" <?= $role_filter === 'volunteer' ? 'selected' : '' ?>>Volunteer</option>
                            <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small fw-bold">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small fw-bold">Verification</label>
                        <select name="verification" class="form-select">
                            <option value="">All</option>
                            <option value="verified" <?= $verification_filter === 'verified' ? 'selected' : '' ?>>Verified</option>
                            <option value="unverified" <?= $verification_filter === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-12 d-grid">
                        <button type="submit" class="btn btn-success">Go</button>
                    </div>
                </div>
                <?php if ($active_filters): ?>
                    <div class="active-filter-row mt-3">
                        <?php foreach ($active_filters as $filter): ?>
                            <span class="active-filter-pill"><?= htmlspecialchars($filter) ?></span>
                        <?php endforeach; ?>
                        <a href="members.php" class="btn btn-link btn-sm p-0">Clear all</a>
                    </div>
                <?php endif; ?>
            </form>

            <div class="card shadow">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($member_items as $m): ?>
                            <tr>
                                <td><?= $m['id'] ?></td>
                                <td><?= htmlspecialchars($m['full_name']) ?></td>
                                <td><?= htmlspecialchars($m['email']) ?></td>
                                <td><?= htmlspecialchars($m['phone'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($m['city'] ?? '—') ?></td>
                                <td>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                        <select name="role" class="form-select form-select-sm" style="width:110px">
                                            <?php foreach (['admin','shelter','volunteer','user'] as $r): ?>
                                                <option value="<?= $r ?>" <?= $m['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="change_role" class="btn btn-sm btn-outline-primary">Set</button>
                                    </form>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $m['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $m['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                                <td>
                                    <?php if ($m['email'] !== 'admin@straylink.np'): ?>
                                        <?php if (in_array($m['role'], ['volunteer','shelter']) && !$m['is_verified']): ?>
                                            <a href="?verify=<?= $m['id'] ?>" class="btn btn-sm btn-success">Verify</a>
                                        <?php endif; ?>
                                        <a href="?toggle=<?= $m['id'] ?>" class="btn btn-sm btn-<?= $m['is_active'] ? 'danger' : 'success' ?>">
                                            <?= $m['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Protected</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($member_items) === 0): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No members match the current filters.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
