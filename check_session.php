<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .label { font-weight: bold; color: #333; }
        .value { color: #0066cc; }
        .error { color: #cc0000; }
        .success { color: #00cc00; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Session Diagnostic Tool</h2>
        <p>This page shows your current session data to help diagnose the volunteer dashboard issue.</p>
    </div>

    <div class="box">
        <h3>Session Status</h3>
        <?php if (isset($_SESSION['member_id'])): ?>
            <p class="success">✓ You are logged in</p>
        <?php else: ?>
            <p class="error">✗ You are NOT logged in</p>
        <?php endif; ?>
    </div>

    <div class="box">
        <h3>Session Data</h3>
        <?php if (!empty($_SESSION)): ?>
            <table style="width: 100%; border-collapse: collapse;">
                <?php foreach ($_SESSION as $key => $value): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td class="label" style="padding: 8px;"><?= htmlspecialchars($key) ?></td>
                        <td class="value" style="padding: 8px;"><?= htmlspecialchars($value) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p class="error">No session data found</p>
        <?php endif; ?>
    </div>

    <div class="box">
        <h3>Role Check</h3>
        <?php if (isset($_SESSION['role'])): ?>
            <p><span class="label">Your role:</span> <span class="value"><?= htmlspecialchars($_SESSION['role']) ?></span></p>
            
            <?php if ($_SESSION['role'] === 'volunteer'): ?>
                <p class="success">✓ You have the volunteer role</p>
                <p><a href="dashboard/volunteer/index.php" style="color: #0066cc;">Click here to try accessing volunteer dashboard</a></p>
            <?php elseif ($_SESSION['role'] === 'user'): ?>
                <p class="error">✗ You have the 'user' role, not 'volunteer'</p>
                <p>This is why you can't access the volunteer dashboard. The dashboard requires role='volunteer'.</p>
            <?php else: ?>
                <p>You have the '<?= htmlspecialchars($_SESSION['role']) ?>' role</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="error">No role found in session</p>
        <?php endif; ?>
    </div>

    <div class="box">
        <h3>Database Check</h3>
        <?php
        if (isset($_SESSION['member_id'])) {
            require_once 'config/db.php';
            $member_id = (int)$_SESSION['member_id'];
            $result = mysqli_query($conn, "SELECT id, full_name, email, role, is_verified FROM members WHERE id = $member_id");
            $member = mysqli_fetch_assoc($result);
            
            if ($member) {
                echo '<p class="success">✓ Found your account in database</p>';
                echo '<table style="width: 100%; border-collapse: collapse;">';
                foreach ($member as $key => $value) {
                    echo '<tr style="border-bottom: 1px solid #ddd;">';
                    echo '<td class="label" style="padding: 8px;">' . htmlspecialchars($key) . '</td>';
                    echo '<td class="value" style="padding: 8px;">' . htmlspecialchars($value) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                if ($member['role'] !== $_SESSION['role']) {
                    echo '<p class="error">⚠ WARNING: Database role (' . htmlspecialchars($member['role']) . ') does not match session role (' . htmlspecialchars($_SESSION['role']) . ')</p>';
                    echo '<p>You need to log out and log back in to sync your session.</p>';
                }
            } else {
                echo '<p class="error">✗ Could not find your account in database</p>';
            }
        } else {
            echo '<p>Not logged in - cannot check database</p>';
        }
        ?>
    </div>

    <div class="box">
        <h3>Next Steps</h3>
        <ul>
            <li>If your role is 'user' instead of 'volunteer', we need to update it in the database</li>
            <li>If your role is 'volunteer' but you still get 404, there may be a file permission issue</li>
            <li>After any database changes, log out and log back in to refresh your session</li>
        </ul>
    </div>
</body>
</html>
