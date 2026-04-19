<?php
require_once __DIR__ . '/db.php';

// Check members table structure
$members_info = mysqli_fetch_assoc(mysqli_query($conn, "SHOW CREATE TABLE members"));
echo "<pre style='font-size:0.8rem;background:#f5f5f5;padding:1rem;'>" . htmlspecialchars($members_info['Create Table']) . "</pre>";

$queries = [
    "CREATE TABLE IF NOT EXISTS stray_animals (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        reported_by INT(11) NOT NULL,
        name VARCHAR(100) DEFAULT NULL,
        species ENUM('dog','cat','other') NOT NULL,
        breed VARCHAR(100) DEFAULT NULL,
        approximate_age ENUM('unknown','young','adult') NOT NULL DEFAULT 'unknown',
        gender ENUM('male','female','unknown') NOT NULL DEFAULT 'unknown',
        condition_status ENUM('unknown','healthy','injured','critical') NOT NULL DEFAULT 'unknown',
        area_label VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        photo VARCHAR(255) DEFAULT NULL,
        status ENUM('pending','active','claimed','removed') NOT NULL DEFAULT 'pending',
        claimed_by_shelter_id INT(11) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS stray_updates (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        stray_id INT(11) NOT NULL,
        updated_by INT(11) NOT NULL,
        condition_status ENUM('unknown','healthy','injured','critical') NOT NULL,
        note TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS stray_inquiries (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        stray_id INT(11) NOT NULL,
        member_id INT(11) NOT NULL,
        message TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$errors = [];
foreach ($queries as $sql) {
    if (!mysqli_query($conn, $sql)) {
        $errors[] = mysqli_error($conn);
    }
}

if (empty($errors)) {
    echo "<p style='color:green;font-family:monospace;'>✓ All stray tables created successfully.</p>";
} else {
    foreach ($errors as $e) {
        echo "<p style='color:red;font-family:monospace;'>✗ " . htmlspecialchars($e) . "</p>";
    }
}
?>
