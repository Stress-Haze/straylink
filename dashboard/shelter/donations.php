<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('shelter');

$member_id = $_SESSION['member_id'];
$shelter = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shelters WHERE member_id = $member_id"));
if (!$shelter) redirect('setup.php');
$shelter_id = (int)$shelter['id'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_method'])) {
    $method_id = isset($_POST['method_id']) ? (int)$_POST['method_id'] : 0;
    $method_type = sanitize($_POST['method_type']);
    $display_name = sanitize($_POST['display_name']);
    $account_name = sanitize($_POST['account_name']);
    $account_number = sanitize($_POST['account_number']);
    $phone_number = sanitize($_POST['phone_number']);
    $payment_identifier = sanitize($_POST['payment_identifier']);
    $instructions = sanitize($_POST['instructions']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($method_type) || empty($display_name)) {
        $error = "Method type and display name are required.";
    } else {
        $qr_image = null;
        if ($method_id > 0) {
            $existing_method = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shelter_payment_methods WHERE id = $method_id AND shelter_id = $shelter_id"));
            if (!$existing_method) {
                $error = "Payment method not found.";
            } else {
                $qr_image = $existing_method['qr_image'];
            }
        }

        if (!$error && !empty($_FILES['qr_image']['name'])) {
            $ext = pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'donation_qr_' . $shelter_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['qr_image']['tmp_name'], '../../public/uploads/' . $filename);
                $qr_image = $filename;
            }
        }

        if (!$error) {
            $qr_val = $qr_image ? "'" . mysqli_real_escape_string($conn, $qr_image) . "'" : 'NULL';

            if ($method_id > 0) {
                mysqli_query($conn, "
                    UPDATE shelter_payment_methods SET
                        method_type = '" . mysqli_real_escape_string($conn, $method_type) . "',
                        display_name = '" . mysqli_real_escape_string($conn, $display_name) . "',
                        account_name = '" . mysqli_real_escape_string($conn, $account_name) . "',
                        account_number = '" . mysqli_real_escape_string($conn, $account_number) . "',
                        phone_number = '" . mysqli_real_escape_string($conn, $phone_number) . "',
                        payment_identifier = '" . mysqli_real_escape_string($conn, $payment_identifier) . "',
                        qr_image = $qr_val,
                        instructions = '" . mysqli_real_escape_string($conn, $instructions) . "',
                        is_active = $is_active
                    WHERE id = $method_id AND shelter_id = $shelter_id
                ");
                $success = "Donation method updated successfully.";
            } else {
                mysqli_query($conn, "
                    INSERT INTO shelter_payment_methods
                    (shelter_id, method_type, display_name, account_name, account_number, phone_number, payment_identifier, qr_image, instructions, is_active)
                    VALUES
                    ($shelter_id,
                    '" . mysqli_real_escape_string($conn, $method_type) . "',
                    '" . mysqli_real_escape_string($conn, $display_name) . "',
                    '" . mysqli_real_escape_string($conn, $account_name) . "',
                    '" . mysqli_real_escape_string($conn, $account_number) . "',
                    '" . mysqli_real_escape_string($conn, $phone_number) . "',
                    '" . mysqli_real_escape_string($conn, $payment_identifier) . "',
                    $qr_val,
                    '" . mysqli_real_escape_string($conn, $instructions) . "',
                    $is_active)
                ");
                $success = "Donation method added successfully.";
            }
        }
    }
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    mysqli_query($conn, "UPDATE shelter_payment_methods SET is_active = NOT is_active WHERE id = $id AND shelter_id = $shelter_id");
    redirect('donations.php');
}

if (isset($_GET['delete_method']) && is_numeric($_GET['delete_method'])) {
    $id = (int)$_GET['delete_method'];
    mysqli_query($conn, "DELETE FROM shelter_payment_methods WHERE id = $id AND shelter_id = $shelter_id");
    redirect('donations.php');
}

if (isset($_GET['verify']) && is_numeric($_GET['verify'])) {
    $id = (int)$_GET['verify'];
    mysqli_query($conn, "
        UPDATE donations
        SET status = 'verified',
            verified_by_member_id = $member_id,
            verified_at = NOW(),
            rejection_reason = NULL
        WHERE id = $id AND shelter_id = $shelter_id
    ");
    redirect('donations.php?tab=records');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_donation'])) {
    $donation_id = (int)$_POST['donation_id'];
    $rejection_reason = sanitize($_POST['rejection_reason']);
    mysqli_query($conn, "
        UPDATE donations
        SET status = 'rejected',
            verified_by_member_id = $member_id,
            verified_at = NOW(),
            rejection_reason = '" . mysqli_real_escape_string($conn, $rejection_reason) . "'
        WHERE id = $donation_id AND shelter_id = $shelter_id
    ");
    redirect('donations.php?tab=records');
}

$tab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'methods';
if (!in_array($tab, ['methods', 'records'])) $tab = 'methods';

$edit_method = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_method = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shelter_payment_methods WHERE id = $edit_id AND shelter_id = $shelter_id"));
}

$methods = mysqli_query($conn, "SELECT * FROM shelter_payment_methods WHERE shelter_id = $shelter_id ORDER BY created_at DESC");
$donations = mysqli_query($conn, "
    SELECT d.*, spm.display_name AS method_name, m.full_name AS verified_by_name
    FROM donations d
    LEFT JOIN shelter_payment_methods spm ON d.payment_method_id = spm.id
    LEFT JOIN members m ON d.verified_by_member_id = m.id
    WHERE d.shelter_id = $shelter_id
    ORDER BY d.created_at DESC
");

$total_donations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM donations WHERE shelter_id = $shelter_id"))['count'];
$pending_donations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM donations WHERE shelter_id = $shelter_id AND status = 'pending'"))['count'];
$verified_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM donations WHERE shelter_id = $shelter_id AND status = 'verified'"))['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donations - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<?php
    $dashboard_title = $shelter['shelter_name'] ?? 'StrayLink Shelter';
    include '../../includes/navbar_dashboard.php';
?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="animals.php"><i class="bi bi-heart"></i> Our Animals</a></li>
                <li class="nav-item"><a class="nav-link" href="animal_add.php"><i class="bi bi-plus-circle"></i> Add Animal</a></li>
                <li class="nav-item"><a class="nav-link" href="requests.php"><i class="bi bi-envelope"></i> Adoption Requests</a></li>
                <li class="nav-item"><a class="nav-link active" href="donations.php"><i class="bi bi-cash-coin"></i> Donations</a></li>
                <li class="nav-item"><a class="nav-link" href="../../pages/rescue_board.php"><i class="bi bi-broadcast"></i> Rescue Board</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-gear"></i> Shelter Profile</a></li>
            </ul>
        </nav>

        <main class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Donations</h4>
                    <p class="text-muted mb-0">Set your payment methods and track community support.</p>
                </div>
                <a href="../../pages/donate.php?shelter_id=<?= $shelter_id ?>" class="btn btn-outline-success" target="_blank">Open Public Donation Page</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-success shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title">Tracked Donations</h6>
                            <h2 class="text-white"><?= $total_donations ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-warning shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title">Pending Verification</h6>
                            <h2 class="text-white"><?= $pending_donations ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-primary shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title">Verified Amount</h6>
                            <h2 class="text-white">NPR <?= number_format((float)$verified_amount, 2) ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs mb-4">
                <li class="nav-item"><a class="nav-link <?= $tab === 'methods' ? 'active' : '' ?>" href="?tab=methods">Payment Methods</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab === 'records' ? 'active' : '' ?>" href="?tab=records">Donation Records</a></li>
            </ul>

            <?php if ($tab === 'methods'): ?>
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card shadow-sm">
                            <div class="card-header fw-bold"><?= $edit_method ? 'Edit Payment Method' : 'Add Payment Method' ?></div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="method_id" value="<?= (int)($edit_method['id'] ?? 0) ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Method Type</label>
                                        <select name="method_type" class="form-select" required>
                                            <?php $method_types = ['esewa' => 'eSewa', 'khalti' => 'Khalti', 'bank_transfer' => 'Bank Transfer', 'qr_code' => 'QR Code', 'cash' => 'Cash', 'other' => 'Other']; ?>
                                            <option value="">Select method</option>
                                            <?php foreach ($method_types as $value => $label): ?>
                                                <option value="<?= $value ?>" <?= ($edit_method['method_type'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Display Name</label>
                                        <input type="text" name="display_name" class="form-control" required value="<?= htmlspecialchars($edit_method['display_name'] ?? '') ?>" placeholder="e.g. Shelter eSewa or Khalti Number">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Account Name</label>
                                        <input type="text" name="account_name" class="form-control" value="<?= htmlspecialchars($edit_method['account_name'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Account / Wallet Number</label>
                                        <input type="text" name="account_number" class="form-control" value="<?= htmlspecialchars($edit_method['account_number'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars($edit_method['phone_number'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Payment Identifier</label>
                                        <input type="text" name="payment_identifier" class="form-control" value="<?= htmlspecialchars($edit_method['payment_identifier'] ?? '') ?>" placeholder="Merchant ID, Khalti ID, etc.">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Instructions</label>
                                        <textarea name="instructions" class="form-control" rows="4"><?= htmlspecialchars($edit_method['instructions'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">QR Image</label>
                                        <input type="file" name="qr_image" class="form-control" accept="image/*">
                                        <?php if (!empty($edit_method['qr_image'])): ?>
                                            <div class="mt-2">
                                                <img src="../../public/uploads/<?= htmlspecialchars($edit_method['qr_image']) ?>" alt="QR code" style="max-width: 140px; border-radius: 10px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= !isset($edit_method['is_active']) || $edit_method['is_active'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_active">Method is active</label>
                                    </div>
                                    <button type="submit" name="save_method" class="btn btn-success"><?= $edit_method ? 'Update Method' : 'Save Method' ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card shadow-sm">
                            <div class="card-header fw-bold">Current Payment Methods</div>
                            <div class="card-body p-0">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Number / ID</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($method = mysqli_fetch_assoc($methods)): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($method['display_name']) ?></strong>
                                                <?php if (!empty($method['account_name'])): ?>
                                                    <div><small class="text-muted"><?= htmlspecialchars($method['account_name']) ?></small></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= ucfirst(str_replace('_', ' ', $method['method_type'])) ?></td>
                                            <td><?= htmlspecialchars($method['account_number'] ?: ($method['payment_identifier'] ?: '-')) ?></td>
                                            <td><span class="badge bg-<?= $method['is_active'] ? 'success' : 'secondary' ?>"><?= $method['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <a href="?tab=methods&edit=<?= $method['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                                    <a href="?toggle=<?= $method['id'] ?>" class="btn btn-sm btn-<?= $method['is_active'] ? 'warning' : 'success' ?>" title="<?= $method['is_active'] ? 'Disable' : 'Enable' ?>">
                                                        <i class="bi bi-<?= $method['is_active'] ? 'pause-fill' : 'play-fill' ?>"></i>
                                                    </a>
                                                    <a href="?delete_method=<?= $method['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this payment method?')"><i class="bi bi-trash"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if (mysqli_num_rows($methods) === 0): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No payment methods added yet.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-header fw-bold">Donation Records</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Donor</th>
                                    <th>Method</th>
                                    <th>Amount</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                    <th>Proof</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($donation = mysqli_fetch_assoc($donations)): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($donation['donor_name'] ?: 'Anonymous') ?></strong>
                                        <?php if (!empty($donation['donor_email'])): ?>
                                            <div><small class="text-muted"><?= htmlspecialchars($donation['donor_email']) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($donation['method_name'] ?: 'Manual') ?></td>
                                    <td>NPR <?= number_format((float)$donation['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($donation['reference_code'] ?: '-') ?></td>
                                    <td><span class="badge bg-<?= $donation['status'] === 'verified' ? 'success' : ($donation['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= ucfirst($donation['status']) ?></span></td>
                                    <td>
                                        <?php if (!empty($donation['proof_image'])): ?>
                                            <a href="../../public/uploads/<?= htmlspecialchars($donation['proof_image']) ?>" target="_blank">View Proof</a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($donation['status'] === 'pending'): ?>
                                            <a href="?tab=records&verify=<?= $donation['id'] ?>" class="btn btn-sm btn-success">Verify</a>
                                            <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#reject-<?= $donation['id'] ?>">Reject</button>
                                            <div class="collapse mt-2" id="reject-<?= $donation['id'] ?>">
                                                <form method="POST">
                                                    <input type="hidden" name="donation_id" value="<?= $donation['id'] ?>">
                                                    <input type="text" name="rejection_reason" class="form-control form-control-sm mb-2" placeholder="Reason">
                                                    <button type="submit" name="reject_donation" class="btn btn-sm btn-danger">Confirm Reject</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-muted"><?= $donation['verified_by_name'] ? 'Handled by ' . htmlspecialchars($donation['verified_by_name']) : 'Reviewed' ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if (mysqli_num_rows($donations) === 0): ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">No donation records yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
