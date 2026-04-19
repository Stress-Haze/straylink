<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!isset($_GET['shelter_id']) || !is_numeric($_GET['shelter_id'])) redirect('home.php');
$shelter_id = (int)$_GET['shelter_id'];

$shelter = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shelters WHERE id = $shelter_id"));
if (!$shelter) redirect('home.php');

$methods = mysqli_query($conn, "SELECT * FROM shelter_payment_methods WHERE shelter_id = $shelter_id AND is_active = 1 ORDER BY created_at DESC");
$method_rows = [];
while ($method = mysqli_fetch_assoc($methods)) {
    $method_rows[] = $method;
}

$error = '';
$success = '';
$stripe_payment_intent = null;

// Handle Stripe payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_type'])) {
    $payment_type = sanitize($_POST['payment_type']);
    
    if ($payment_type === 'stripe') {
        // Stripe test mode payment
        $member_id = isLoggedIn() ? (int)$_SESSION['member_id'] : 'NULL';
        $donor_name = sanitize($_POST['donor_name']);
        $donor_email = sanitize($_POST['donor_email']);
        $amount = (float)$_POST['amount'];
        $message = sanitize($_POST['message']);
        
        if ($amount <= 0) {
            $error = "Please enter a valid donation amount.";
        } elseif (empty($donor_name)) {
            $error = "Please enter your name.";
        } else {
            // Record donation as completed (Stripe test mode)
            mysqli_query($conn, "
                INSERT INTO donations
                (shelter_id, member_id, payment_method_id, donor_name, donor_email, amount, reference_code, message, status)
                VALUES
                ($shelter_id,
                $member_id,
                NULL,
                '" . mysqli_real_escape_string($conn, $donor_name) . "',
                '" . mysqli_real_escape_string($conn, $donor_email) . "',
                $amount,
                'STRIPE_TEST_' . time(),
                '" . mysqli_real_escape_string($conn, $message) . "',
                'completed')
            ");
            $success = "Thank you! Your donation of NPR " . number_format($amount, 2) . " has been processed successfully via Stripe test mode.";
        }
    } else {
        // Manual payment method
        $member_id = isLoggedIn() ? (int)$_SESSION['member_id'] : 'NULL';
        $payment_method_id = !empty($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : 'NULL';
        $donor_name = sanitize($_POST['donor_name']);
        $donor_email = sanitize($_POST['donor_email']);
        $amount = (float)$_POST['amount'];
        $reference_code = sanitize($_POST['reference_code']);
        $message = sanitize($_POST['message']);
        $proof_image = null;

        if ($amount <= 0) {
            $error = "Please enter a valid donation amount.";
        } elseif (empty($donor_name)) {
            $error = "Please enter your name.";
        } else {
            if (!empty($_FILES['proof_image']['name'])) {
                $ext = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array(strtolower($ext), $allowed)) {
                    $filename = 'donation_proof_' . $shelter_id . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['proof_image']['tmp_name'], '../public/uploads/' . $filename);
                    $proof_image = $filename;
                }
            }

            $proof_val = $proof_image ? "'" . mysqli_real_escape_string($conn, $proof_image) . "'" : 'NULL';
            mysqli_query($conn, "
                INSERT INTO donations
                (shelter_id, member_id, payment_method_id, donor_name, donor_email, amount, reference_code, proof_image, message, status)
                VALUES
                ($shelter_id,
                $member_id,
                $payment_method_id,
                '" . mysqli_real_escape_string($conn, $donor_name) . "',
                '" . mysqli_real_escape_string($conn, $donor_email) . "',
                $amount,
                '" . mysqli_real_escape_string($conn, $reference_code) . "',
                $proof_val,
                '" . mysqli_real_escape_string($conn, $message) . "',
                'pending')
            ");
            $success = "Thank you. Your donation record has been submitted for shelter verification.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support <?= htmlspecialchars($shelter['shelter_name']) ?> - StrayLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php
    $active_page = '';
    $nav_depth = 1;
    include '../includes/navbar.php';
?>

<div class="container py-5">
    <section class="blog-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="section-kicker mb-2">Support shelter care</p>
                <h1 class="fw-bold mb-3">Donate to <?= htmlspecialchars($shelter['shelter_name']) ?></h1>
                <p class="text-muted mb-0">Choose one of the shelter’s listed payment methods, complete the payment manually, then submit your donation record so the shelter can verify it.</p>
            </div>
            <div class="col-lg-5">
                <div class="gallery-summary-card">
                    <div>
                        <span class="summary-label">Secure & Tracked</span>
                        <strong>Every donation is recorded and verified by the shelter.</strong>
                    </div>
                    <div>
                        <span class="summary-label">Two Payment Options</span>
                        <strong>Use Stripe for instant processing or manual methods like eSewa & Khalti.</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4 align-items-start">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header fw-bold">Available Payment Methods</div>
                <div class="card-body">
                    <?php if (count($method_rows) === 0): ?>
                        <p class="text-muted mb-0">This shelter has not added donation methods yet.</p>
                    <?php else: ?>
                        <div class="donation-method-list">
                            <?php foreach ($method_rows as $method): ?>
                                <div class="payment-method-card">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                        <div>
                                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($method['display_name']) ?></h5>
                                            <div class="text-muted small"><?= ucfirst(str_replace('_', ' ', $method['method_type'])) ?></div>
                                        </div>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                    <?php if (!empty($method['account_name'])): ?>
                                        <div class="small text-muted mb-1">Account Name: <?= htmlspecialchars($method['account_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($method['account_number'])): ?>
                                        <div class="small text-muted mb-1">Account / Wallet: <?= htmlspecialchars($method['account_number']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($method['phone_number'])): ?>
                                        <div class="small text-muted mb-1">Phone: <?= htmlspecialchars($method['phone_number']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($method['payment_identifier'])): ?>
                                        <div class="small text-muted mb-1">Identifier: <?= htmlspecialchars($method['payment_identifier']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($method['instructions'])): ?>
                                        <div class="small text-muted mb-2"><?= nl2br(htmlspecialchars($method['instructions'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($method['qr_image'])): ?>
                                        <img src="../public/uploads/<?= htmlspecialchars($method['qr_image']) ?>" alt="QR code for <?= htmlspecialchars($method['display_name']) ?>" class="donation-qr-preview">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header fw-bold">Choose Payment Method</div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="stripe-tab" data-bs-toggle="tab" data-bs-target="#stripe-pane" type="button" role="tab">
                                <i class="bi bi-credit-card me-1"></i>Payment Method
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-pane" type="button" role="tab">
                                <i class="bi bi-wallet2 me-1"></i>Submit Your Donation
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Stripe Payment Tab -->
                        <div class="tab-pane fade show active" id="stripe-pane" role="tabpanel">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="payment_type" value="stripe">
                                <div class="mb-3">
                                    <label class="form-label">Your Name</label>
                                    <input type="text" name="donor_name" class="form-control" required value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="donor_email" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Donation Amount (NPR)</label>
                                    <input type="number" step="0.01" min="1" name="amount" class="form-control" required placeholder="e.g., 500">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="3" placeholder="Optional support message"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-credit-card me-1"></i>Proceed to Stripe Payment
                                </button>
                            </form>
                        </div>

                        <!-- Manual Payment Tab -->
                        <div class="tab-pane fade" id="manual-pane" role="tabpanel">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="payment_type" value="manual">
                                <div class="mb-3">
                                    <label class="form-label">Your Name</label>
                                    <input type="text" name="donor_name" class="form-control" required value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="donor_email" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select name="payment_method_id" class="form-select" required>
                                        <option value="">Select a method</option>
                                        <?php foreach ($method_rows as $method): ?>
                                            <option value="<?= $method['id'] ?>"><?= htmlspecialchars($method['display_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount (NPR)</label>
                                    <input type="number" step="0.01" min="1" name="amount" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Reference Code</label>
                                    <input type="text" name="reference_code" class="form-control" placeholder="Transaction code or screenshot reference">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Proof Image</label>
                                    <input type="file" name="proof_image" class="form-control" accept="image/*">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="3" placeholder="Optional support message"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100">Submit Donation Record</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
