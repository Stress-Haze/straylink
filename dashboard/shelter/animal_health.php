<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireRole('shelter');

$member_id = $_SESSION['member_id'];
$shelter   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shelters WHERE member_id = $member_id"));
if (!$shelter) redirect('setup.php');
$shelter_id = $shelter['id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) redirect('animals.php');
$animal_id = (int)$_GET['id'];

// Make sure this animal belongs to this shelter
$animal = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM animals WHERE id = $animal_id AND shelter_id = $shelter_id
"));
if (!$animal) redirect('animals.php');

$error   = '';
$success = '';

// Handle new health record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_type  = sanitize($_POST['record_type']);
    $title        = sanitize($_POST['title']);
    $description  = sanitize($_POST['description']);
    $performed_at = sanitize($_POST['performed_at']);
    $next_due     = !empty($_POST['next_due_date']) ? sanitize($_POST['next_due_date']) : null;
    $vet_name     = sanitize($_POST['vet_name']);
    $clinic_name  = sanitize($_POST['clinic_name']);

    if (empty($record_type) || empty($title) || empty($performed_at)) {
        $error = "Record type, title and date are required.";
    } else {
        // Handle attachment upload
        $attachment = null;
        if (!empty($_FILES['attachment']['name'])) {
            $ext     = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg','jpeg','png','webp','pdf'];
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'health_' . $animal_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['attachment']['tmp_name'], '../../public/uploads/' . $filename);
                $attachment = $filename;
            }
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO health_records 
            (animal_id, recorded_by, record_type, title, description, performed_at, next_due_date, vet_name, clinic_name, attachment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $next_due_val = $next_due ?: null;
        mysqli_stmt_bind_param($stmt, "iissssssss",
            $animal_id, $member_id, $record_type, $title,
            $description, $performed_at, $next_due_val,
            $vet_name, $clinic_name, $attachment
        );

        if (mysqli_stmt_execute($stmt)) {
            // Auto update is_vaccinated if vaccination record added
            if ($record_type === 'vaccination') {
                mysqli_query($conn, "UPDATE animals SET is_vaccinated = 1 WHERE id = $animal_id");
            }
            if ($record_type === 'sterilization') {
                mysqli_query($conn, "UPDATE animals SET is_sterilized = 1 WHERE id = $animal_id");
            }
            $success = "Health record added successfully!";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}

// Fetch all health records for this animal
$records = mysqli_query($conn, "
    SELECT h.*, m.full_name AS recorded_by_name
    FROM health_records h
    JOIN members m ON h.recorded_by = m.id
    WHERE h.animal_id = $animal_id
    ORDER BY h.performed_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Records — <?= htmlspecialchars($animal['name'] ?? 'Animal') ?></title>
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

        <!-- Sidebar -->
        <nav class="col-md-2 bg-light sidebar min-vh-100 p-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="animals.php"><i class="bi bi-heart"></i> Our Animals</a></li>
                <li class="nav-item"><a class="nav-link" href="animal_add.php"><i class="bi bi-plus-circle"></i> Add Animal</a></li>
                <li class="nav-item"><a class="nav-link" href="requests.php"><i class="bi bi-envelope"></i> Adoption Requests</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-gear"></i> Shelter Profile</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">

            <!-- Animal Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="animals.php" class="btn btn-outline-secondary btn-sm mb-2">← Back to Animals</a>
                    <h4 class="mb-0">
                        Health Records — <?= htmlspecialchars($animal['name'] ?? 'Unnamed') ?>
                        <span class="badge ms-2" style="background-color:<?= $animal['collar_status'] === 'green' ? '#198754' : ($animal['collar_status'] === 'yellow' ? '#ffc107' : '#dc3545') ?>">
                            <?= ucfirst($animal['collar_status']) ?>
                        </span>
                    </h4>
                    <p class="text-muted small mb-0">
                        <?= ucfirst($animal['species']) ?>
                        <?= $animal['breed'] ? '· ' . htmlspecialchars($animal['breed']) : '' ?>
                        · <?= ucfirst($animal['gender']) ?>
                        &nbsp;|&nbsp;
                        Vaccinated: <?= $animal['is_vaccinated'] ? '✅' : '❌' ?>
                        &nbsp;|&nbsp;
                        Sterilized: <?= $animal['is_sterilized'] ? '✅' : '❌' ?>
                    </p>
                </div>
                <a href="animal_edit.php?id=<?= $animal_id ?>" class="btn btn-outline-primary btn-sm">Edit Animal</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- Add Record Form -->
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header fw-bold bg-white">
                            <i class="bi bi-plus-circle text-success"></i> Add Health Record
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Record Type <span class="text-danger">*</span></label>
                                    <select name="record_type" class="form-select" required>
                                        <option value="">-- Select --</option>
                                        <option value="vaccination">Vaccination</option>
                                        <option value="sterilization">Sterilization</option>
                                        <option value="treatment">Treatment</option>
                                        <option value="checkup">Checkup</option>
                                        <option value="injury">Injury</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" required
                                        placeholder="e.g. Rabies Vaccination">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"
                                        placeholder="Notes, dosage, observations..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Date Performed <span class="text-danger">*</span></label>
                                    <input type="date" name="performed_at" class="form-control" required
                                        value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Next Due Date <small class="text-muted">(optional)</small></label>
                                    <input type="date" name="next_due_date" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Vet Name</label>
                                    <input type="text" name="vet_name" class="form-control" placeholder="Dr. ...">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Clinic / Hospital</label>
                                    <input type="text" name="clinic_name" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Attachment <small class="text-muted">(photo or PDF)</small></label>
                                    <input type="file" name="attachment" class="form-control" accept="image/*,.pdf">
                                </div>
                                <button type="submit" class="btn btn-success w-100">Add Record</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Records List -->
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header fw-bold bg-white">
                            <i class="bi bi-clipboard2-pulse text-success"></i> 
                            Health History
                            <span class="badge bg-success ms-2"><?= mysqli_num_rows($records) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (mysqli_num_rows($records) === 0): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-clipboard2 mb-3" style="font-size:2.5rem;display:block;"></i>
                                    No health records yet — add the first one using the form.
                                </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                            <?php while ($r = mysqli_fetch_assoc($records)): ?>
                                <div class="list-group-item px-4 py-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <span class="badge bg-<?= 
                                                $r['record_type'] === 'vaccination'   ? 'success' : 
                                                ($r['record_type'] === 'sterilization' ? 'info' :
                                                ($r['record_type'] === 'treatment'     ? 'warning' :
                                                ($r['record_type'] === 'injury'        ? 'danger' : 'secondary'))) 
                                            ?> me-2"><?= ucfirst($r['record_type']) ?></span>
                                            <strong><?= htmlspecialchars($r['title']) ?></strong>
                                        </div>
                                        <small class="text-muted"><?= date('M d, Y', strtotime($r['performed_at'])) ?></small>
                                    </div>
                                    <?php if ($r['description']): ?>
                                        <p class="text-muted small mb-1"><?= htmlspecialchars($r['description']) ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex gap-3 mt-1">
                                        <?php if ($r['vet_name']): ?>
                                            <small class="text-muted"><i class="bi bi-person"></i> <?= htmlspecialchars($r['vet_name']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($r['clinic_name']): ?>
                                            <small class="text-muted"><i class="bi bi-hospital"></i> <?= htmlspecialchars($r['clinic_name']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($r['next_due_date']): ?>
                                            <small class="text-warning"><i class="bi bi-calendar-event"></i> Due: <?= date('M d, Y', strtotime($r['next_due_date'])) ?></small>
                                        <?php endif; ?>
                                        <?php if ($r['attachment']): ?>
                                            <a href="../../public/uploads/<?= $r['attachment'] ?>" target="_blank" class="small">
                                                <i class="bi bi-paperclip"></i> Attachment
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted d-block mt-1">Recorded by <?= htmlspecialchars($r['recorded_by_name']) ?></small>
                                </div>
                            <?php endwhile; ?>
                            </div>
                            <?php endif; ?>
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