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
    
    echo "<h2>Testing Order Flow</h2>";
    
    // 1. Check order table structure
    echo "<h3>1. Checking order table structure</h3>";
    $orderTable = $dbh->query("SHOW CREATE TABLE `order`")->fetch(PDO::FETCH_ASSOC);
    echo "<pre>" . htmlspecialchars($orderTable['Create Table']) . "</pre>";
    
    // 2. Check order_details table structure
    echo "<h3>2. Checking order_details table structure</h3>";
    $detailsTable = $dbh->query("SHOW CREATE TABLE `order_details`")->fetch(PDO::FETCH_ASSOC);
    echo "<pre>" . htmlspecialchars($detailsTable['Create Table']) . "</pre>";
    
    // 3. Check auto_increment values
    echo "<h3>3. Checking auto_increment values</h3>";
    $tables = ['order', 'order_details'];
    foreach ($tables as $table) {
        $status = $dbh->query("SHOW TABLE STATUS LIKE '$table'")->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>$table</strong>: Auto_increment = " . $status['Auto_increment'] . "</p>";
    }
    
    // 4. Test insert
    echo "<h3>4. Testing order insertion</h3>";
    
    // Start transaction
    $dbh->beginTransaction();
    
    try {
        // Get a menu item for testing
        $menuItem = $dbh->query("SELECT item_id, item_name, price FROM menu LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if (!$menuItem) {
            throw new Exception("No menu items found. Please add items to the menu first.");
        }
        
        echo "<p>Using menu item: " . htmlspecialchars($menuItem['item_name']) . " (ID: " . $menuItem['item_id'] . ")</p>";
        
        // Insert test order
        $orderStmt = $dbh->prepare("
            INSERT INTO `order` (
                customer_id, employee_id, order_date, 
                status, total_amount, payment_method, discount
            ) VALUES (
                :customer_id, :employee_id, NOW(),
                :status, :total_amount, :payment_method, :discount
            )
        ");
        
        $orderData = [
            ':customer_id' => 1,
            ':employee_id' => 1,
            ':status' => 'pending',
            ':total_amount' => $menuItem['price'],
            ':payment_method' => 'test',
            ':discount' => 0
        ];
        
        echo "<p>Inserting test order with data:</p><pre>";
        print_r($orderData);
        echo "</pre>";
        
        $orderStmt->execute($orderData);
        $orderId = $dbh->lastInsertId();
        
        if (!$orderId) {
            throw new Exception("Failed to get order ID after insert");
        }
        
        echo "<p>✅ Order inserted successfully! Order ID: $orderId</p>";
        
        // Insert order details
        $detailStmt = $dbh->prepare("
            INSERT INTO order_details (
                order_id, item_id, quantity, subtotal
            ) VALUES (
                :order_id, :item_id, :quantity, :subtotal
            )
        ");
        
        $detailData = [
            ':order_id' => $orderId,
            ':item_id' => $menuItem['item_id'],
            ':quantity' => 1,
            ':subtotal' => $menuItem['price']
        ];
        
        echo "<p>Inserting order detail with data:</p><pre>";
        print_r($detailData);
        echo "</pre>";
        
        $detailStmt->execute($detailData);
        $detailId = $dbh->lastInsertId();
        
        if (!$detailId) {
            throw new Exception("Failed to get order detail ID after insert");
        }
        
        echo "<p>✅ Order detail inserted successfully! Detail ID: $detailId</p>";
        
        // Show the inserted data
        echo "<h3>5. Verifying inserted data</h3>";
        
        $order = $dbh->query("SELECT * FROM `order` WHERE order_id = $orderId")->fetch(PDO::FETCH_ASSOC);
        echo "<h4>Order:</h4><pre>";
        print_r($order);
        echo "</pre>";
        
        $details = $dbh->query("SELECT * FROM order_details WHERE order_detail_id = $detailId")->fetch(PDO::FETCH_ASSOC);
        echo "<h4>Order Details:</h4><pre>";
        print_r($details);
        echo "</pre>";
        
        // Rollback to not leave test data
        $dbh->rollBack();
        
        echo "<p style='color:green;'>✅ Test completed successfully! Changes were rolled back.</p>";
        
    } catch (Exception $e) {
        $dbh->rollBack();
        echo "<p style='color:red;'>❌ Test failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        
        if (isset($dbh)) {
            $error = $dbh->errorInfo();
            if (!empty($error[2])) {
                echo "<p>Database error: " . htmlspecialchars($error[2]) . "</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Add link to test the payment process
echo "<p><a href='process-payment.php'>Test Payment Process</a></p>";
?>
