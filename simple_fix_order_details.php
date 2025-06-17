<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$dbname = 'fastfood';
$username = 'root';
$password = '';

try {
    // Connect to database
    $dbh = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Simple Fix for Order Details Table</h2>";
    
    // 1. Backup the current table
    echo "<p>Creating backup of order_details table...</p>";
    $dbh->exec("DROP TABLE IF EXISTS `order_details_backup_final`");
    $dbh->exec("CREATE TABLE `order_details_backup_final` SELECT * FROM `order_details`");
    
    // 2. Create a new table with the correct structure
    echo "<p>Creating new order_details table with correct structure...</p>";
    
    // Drop the existing table
    $dbh->exec("DROP TABLE IF EXISTS `order_details`");
    
    // Create the new table with AUTO_INCREMENT
    $createSQL = "
    CREATE TABLE `order_details` (
        `orderdetail_id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` int(11) NOT NULL,
        `item_id` int(11) NOT NULL,
        `quantity` int(11) NOT NULL,
        `subtotal` decimal(10,2) NOT NULL,
        PRIMARY KEY (`orderdetail_id`),
        KEY `order_id` (`order_id`),
        KEY `item_id` (`item_id`),
        CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE,
        CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `menu` (`item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $dbh->exec($createSQL);
    
    // 3. Copy data from backup (without the orderdetail_id to let AUTO_INCREMENT work)
    echo "<p>Copying data from backup...</p>";
    $dbh->exec("INSERT INTO `order_details` (order_id, item_id, quantity, subtotal) 
                 SELECT order_id, item_id, quantity, subtotal FROM `order_details_backup_final`");
    
    // 4. Show the new table structure
    $stmt = $dbh->query("SHOW CREATE TABLE `order_details`");
    $table = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>New Table Structure:</h3>";
    echo "<pre>" . htmlspecialchars($table['Create Table']) . "</pre>";
    
    // 5. Test insert
    echo "<h3>Testing Insert:</h3>";
    
    // Get a valid order_id and item_id for testing
    $testOrderId = $dbh->query("SELECT order_id FROM `order` ORDER BY order_id DESC LIMIT 1")->fetchColumn();
    $testItemId = $dbh->query("SELECT item_id FROM `menu` LIMIT 1")->fetchColumn();
    
    if ($testOrderId && $testItemId) {
        $testStmt = $dbh->prepare("INSERT INTO `order_details` (order_id, item_id, quantity, subtotal) VALUES (?, ?, 1, 10.00)");
        $testStmt->execute([$testOrderId, $testItemId]);
        $testId = $dbh->lastInsertId();
        
        if ($testId) {
            echo "<p style='color:green;'>✅ Test insert successful! Order Detail ID: $testId</p>";
            
            // Show the test order detail
            $testDetail = $dbh->query("SELECT * FROM `order_details` WHERE orderdetail_id = $testId")->fetch(PDO::FETCH_ASSOC);
            echo "<pre>";
            print_r($testDetail);
            echo "</pre>";
            
            // Clean up test data
            $dbh->exec("DELETE FROM `order_details` WHERE orderdetail_id = $testId");
        } else {
            throw new Exception("Test insert failed - no ID returned");
        }
    } else {
        echo "<p style='color:orange;'>⚠️ Could not test insert - no orders or menu items found</p>";
    }
    
    echo "<p style='color:green;'>✅ Order details table fixed successfully!</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Show more error details
    if (isset($dbh)) {
        echo "<h3>Error Details:</h3>";
        echo "<pre>Error Code: " . $e->getCode() . "\n";
        echo "Error Info: " . print_r($dbh->errorInfo(), true) . "</pre>";
    }
}

echo "<p><a href='test_order_flow.php'>Test Order Flow Again</a></p>";
?>
