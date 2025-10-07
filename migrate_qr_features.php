<?php
// Database migration to add private_key column to wg_peers table
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $db = get_db();
    
    // Check if private_key column already exists
    $stmt = $db->prepare("SHOW COLUMNS FROM wg_peers LIKE 'private_key'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE wg_peers ADD COLUMN private_key TEXT AFTER public_key";
        $db->exec($sql);
        echo "✅ Added private_key column to wg_peers table\n";
    } else {
        echo "ℹ️  private_key column already exists in wg_peers table\n";
    }
    
    // Check if we need to add any other QR code related columns
    $columns_to_add = [
        'qr_code_generated' => 'BOOLEAN DEFAULT FALSE',
        'config_downloaded' => 'BOOLEAN DEFAULT FALSE',
        'last_config_update' => 'TIMESTAMP NULL'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        $stmt = $db->prepare("SHOW COLUMNS FROM wg_peers LIKE ?");
        $stmt->execute([$column]);
        
        if ($stmt->rowCount() == 0) {
            $sql = "ALTER TABLE wg_peers ADD COLUMN {$column} {$definition}";
            $db->exec($sql);
            echo "✅ Added {$column} column to wg_peers table\n";
        } else {
            echo "ℹ️  {$column} column already exists in wg_peers table\n";
        }
    }
    
    echo "\n🎉 Database migration completed successfully!\n";
    echo "The QR code and configuration download features are now ready to use.\n";
    
} catch (Exception $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
    echo "Please ensure your database connection is working and you have the necessary permissions.\n";
}
?>