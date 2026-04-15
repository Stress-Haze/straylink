<?php
/**
 * Database Migration: Add diary customization columns to lost_pet_posts table
 * Run this once to update existing databases
 */

require_once __DIR__ . '/db.php';

$migrations = [
    "ALTER TABLE lost_pet_posts ADD COLUMN side_images JSON DEFAULT NULL AFTER poster_image",
    "ALTER TABLE lost_pet_posts ADD COLUMN bg_color VARCHAR(7) DEFAULT '#faf7f2' AFTER side_images",
    "ALTER TABLE lost_pet_posts ADD COLUMN bg_pattern VARCHAR(20) DEFAULT 'none' AFTER bg_color"
];

echo "<h2>⚙️ Database Migration: Lost Pet Posts Diary Features</h2>";
echo "<p>Adding customization columns to lost_pet_posts table...</p>";

$success_count = 0;
$error_count = 0;

foreach ($migrations as $migration) {
    echo "<p><code>$migration</code>";
    
    $result = mysqli_query($conn, $migration);
    
    if ($result) {
        echo " ✅ Success</p>";
        $success_count++;
    } else {
        $error = mysqli_error($conn);
        // Check if column already exists (ignore this error)
        if (strpos($error, 'Duplicate column') !== false) {
            echo " ⚠️ Column already exists, skipping</p>";
            $success_count++;
        } else {
            echo " ❌ Error: $error</p>";
            $error_count++;
        }
    }
}

echo "<hr>";
if ($error_count === 0) {
    echo "<p style='color: green; font-weight: bold;'>✅ Migration completed successfully! All columns are ready.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>⚠️ Migration partially failed. Please check the errors above.</p>";
}

echo "<p><a href='../index.php'>← Back to StrayLink</a></p>";
?>
