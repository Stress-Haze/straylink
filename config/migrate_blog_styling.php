<?php
require_once 'db.php';

// Add styling columns to posts table
$columns_to_check = [
    'bg_color' => "ALTER TABLE posts ADD COLUMN bg_color VARCHAR(7) DEFAULT '#faf7f2';",
    'bg_pattern' => "ALTER TABLE posts ADD COLUMN bg_pattern VARCHAR(50) DEFAULT 'none';",
    'side_images' => "ALTER TABLE posts ADD COLUMN side_images LONGTEXT DEFAULT NULL;",
    'sticker_images' => "ALTER TABLE posts ADD COLUMN sticker_images LONGTEXT DEFAULT NULL;"
];

foreach ($columns_to_check as $col_name => $alter_query) {
    // Check if column exists
    $check = mysqli_query($conn, "SHOW COLUMNS FROM posts LIKE '$col_name'");
    if (mysqli_num_rows($check) == 0) {
        if (mysqli_query($conn, $alter_query)) {
            echo "✓ Added column: $col_name\n";
        } else {
            echo "✗ Error adding column $col_name: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "✓ Column already exists: $col_name\n";
    }
}

echo "\nMigration complete!";
?>
