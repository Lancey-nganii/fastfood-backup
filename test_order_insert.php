<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$dbname = 'fastfood';
$username = 'root';  // Default XAMPP username
$password = '';     // Default XAMPP password

try {
    // Create connection
    $dbh = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Test Order Insert</h2>";
    
    // Create test table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS `test_orders` (
        `order_id` int(11) NOT NULL AUTO_INCREMENT,
        `customer_id` int(11) NOT NULL,
        `order_date` datetime NOT NULL DEFAULT current_timestamp(),
        `status` varchar(50) NOT NULL DEFAULT 'Pending',
        `total_amount` decimal(10,2) NOT NULL,
        `payment_method` varchar(50) NOT NULL,
        PRIMARY KEY (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $dbh->exec($sql);
    echo "<p>Test table created or already exists.</p>";
    
    // Test insert
    $sql = "INSERT INTO `test_orders` (customer_id, total_amount, payment_method) 
            VALUES (:customer_id, :total_amount, :payment_method)";
    
    $stmt = $dbh->prepare($sql);
    $result = $stmt->execute([
        'customer_id' => 1,
        'total_amount' => 10.99,
        'payment_method' => 'test_payment'
    ]);
    
    if ($result) {
        $order_id = $dbh->lastInsertId();
        echo "<p style='color:green;'>Success! Inserted order with ID: $order_id</p>";
        
        // Show the inserted record
        $stmt = $dbh->query("SELECT * FROM test_orders WHERE order_id = $order_id");
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Inserted Order:</h3>";
        echo "<pre>";
        print_r($order);
        echo "</pre>";
        
        // Show all test orders
        $stmt = $dbh->query("SELECT * FROM test_orders");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>All Test Orders:</h3>";
        echo "<pre>";
        print_r($orders);
        echo "</pre>";
    } else {
        echo "<p style='color:red;'>Failed to insert order.</p>";
    }
    
    // Show table status
    $stmt = $dbh->query("SHOW TABLE STATUS LIKE 'test_orders'");
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Table Status:</h3>";
    echo "<pre>";
    print_r($status);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    
    // Show more error details
    echo "<h3>Error Details:</h3>";
    echo "<pre>Error Code: " . $e->getCode() . "\n";
    echo "Error Info: " . print_r($dbh->errorInfo(), true) . "</pre>";
}

// Test connection to the original order table
try {
    $stmt = $dbh->query("SHOW CREATE TABLE `order`");
    $create_table = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Original Order Table Structure:</h3>";
    echo "<pre>" . htmlspecialchars($create_table['Create Table'] ?? 'Table not found') . "</pre>";
    
    // Try to insert into the original table
    try {
        $sql = "INSERT INTO `order` (customer_id, total_amount, payment_method) 
                VALUES (:customer_id, :total_amount, :payment_method)";
        
        $stmt = $dbh->prepare($sql);
        $result = $stmt->execute([
            'customer_id' => 1,
            'total_amount' => 10.99,
            'payment_method' => 'test_payment'
        ]);
        
        if ($result) {
            $order_id = $dbh->lastInsertId();
            echo "<p style='color:green;'>Success! Inserted into original order table with ID: $order_id</p>";
        } else {
            echo "<p style='color:red;'>Failed to insert into original order table.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error inserting into original order table: " . $e->getMessage() . "</p>";
        echo "<pre>Error Info: " . print_r($dbh->errorInfo(), true) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error accessing original order table: " . $e->getMessage() . "</p>";
}
?>
