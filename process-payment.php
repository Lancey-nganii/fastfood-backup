<?php
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set error logging to a file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Function to log debug information
function log_debug($message, $data = []) {
    $log = "[" . date('d-M-Y H:i:s') . "] " . $message . "\n";
    if (!empty($data)) {
        $log .= "Data: " . print_r($data, true) . "\n";
    }
    $log .= "\n";
    file_put_contents('debug.log', $log, FILE_APPEND);
    
    // Also log to error log for better visibility
    if (is_array($data) || is_object($data)) {
        $data = json_encode($data, JSON_PRETTY_PRINT);
    }
    error_log("DEBUG: " . $message . " - " . $data);
}

// Function to verify database connection and table structure
function verify_database_connection($dbh) {
    try {
        // Check connection
        $dbh->query('SELECT 1');
        
        // Check order_details table structure
        $stmt = $dbh->query("SHOW CREATE TABLE `order_details`");
        $table = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (strpos($table['Create Table'], 'AUTO_INCREMENT') === false) {
            throw new Exception('order_details table is missing AUTO_INCREMENT on primary key');
        }
        
        return true;
    } catch (Exception $e) {
        log_debug('Database verification failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}

// Function to send JSON response and exit
function sendResponse($statusCode, $message, $data = []) {
    error_log("Sending response - Status: $statusCode, Message: $message");
    error_log("Response data: " . print_r($data, true));
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    $response = [
        'success' => $statusCode >= 200 && $statusCode < 300,
        'message' => $message,
        'data' => $data
    ];
    
    // Add debug info in development
    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        $response['debug'] = [
            'session' => $_SESSION,
            'post' => $_POST,
            'request_method' => $_SERVER['REQUEST_METHOD']
        ];
    }
    
    echo json_encode($response);
    exit;
}

try {
    // Include config file
    require_once('includes/config.php');
    
    // Log start of script execution
    log_debug('=== NEW PAYMENT REQUEST ===');
    log_debug('POST data: ' . print_r($_POST, true));
    log_debug('Session data: ' . print_r($_SESSION, true));

    // Get raw input data
    $input = json_decode(file_get_contents('php://input'), true);
    log_debug('Raw input data: ' . print_r($input, true));

    // Check if we have cart data
    if (empty($input['items'])) {
        log_debug('No items in cart');
        sendResponse(400, 'Cart is empty');
    }

    // For testing - hardcode user ID and other required fields
    $user_id = 1; // Hardcoded for testing
    $payment_method = $input['payment_method'] ?? 'cash';
    $discount = $input['discount'] ?? 0;
    $total_amount = $input['total_amount'] ?? 0;

    log_debug('Processing order', [
        'user_id' => $user_id,
        'payment_method' => $payment_method,
        'discount' => $discount,
        'total_amount' => $total_amount
    ]);

    // Test database connection
    try {
        $dbh->query('SELECT 1');
    } catch (PDOException $e) {
        error_log('Database connection test failed: ' . $e->getMessage());
        sendResponse(500, 'Database connection failed: ' . $e->getMessage());
    }
    
    // Use the already parsed input data
    $data = $input;
    
    // Validate input data
    if (!is_numeric($user_id) || $user_id <= 0) {
        sendResponse(400, 'Invalid user ID. Please log in to place an order.');
    }
    if (!is_numeric($total_amount) || $total_amount <= 0) {
        sendResponse(400, 'Invalid total amount');
    }
    
    // Verify database connection and table structure
    verify_database_connection($dbh);
    
    // Log current auto_increment value for order_details
    $ai = $dbh->query("SHOW TABLE STATUS LIKE 'order_details'")->fetch(PDO::FETCH_ASSOC);
    log_debug('Current order_details auto_increment', [
        'auto_increment' => $ai['Auto_increment'] ?? 'unknown',
        'table_status' => $ai
    ]);
    
    // Log database transaction start
    log_debug('Starting database transaction');
    
    // Start transaction
    try {
        $dbh->beginTransaction();
        log_debug('Transaction started successfully');
    } catch (PDOException $e) {
        log_debug('Error starting transaction', ['error' => $e->getMessage()]);
        sendResponse(500, 'Database error: ' . $e->getMessage());
    }
    
    try {
        // Check table status and auto_increment value
        $stmt = $dbh->query("SHOW TABLE STATUS LIKE 'order'");
        $table_status = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_id = $table_status['Auto_increment'] ?? 'N/A';
        
        log_debug('Table status before insert', [
            'auto_increment' => $next_id,
            'table_status' => $table_status
        ]);
        
        // Check if auto_increment is 0 or null - this is the problem!
        if (!$next_id || $next_id == 0) {
            log_debug('Auto_increment is 0 or null, fixing...');
            
            // Get the maximum ID and set auto_increment
            $max_id_stmt = $dbh->query("SELECT COALESCE(MAX(order_id), 0) + 1 as next_id FROM `order`");
            $max_id_result = $max_id_stmt->fetch(PDO::FETCH_ASSOC);
            $new_auto_increment = $max_id_result['next_id'];
            
            // Fix the auto_increment value
            $dbh->exec("ALTER TABLE `order` AUTO_INCREMENT = " . (int)$new_auto_increment);
            
            log_debug('Fixed auto_increment', ['new_value' => $new_auto_increment]);
        }
        
        // Check for potential duplicate entries before inserting
        $duplicate_check = $dbh->prepare("
            SELECT COUNT(*) as count 
            FROM `order` 
            WHERE customer_id = ? 
            AND total_amount = ? 
            AND order_date > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $duplicate_check->execute([$user_id, $total_amount]);
        $duplicate_result = $duplicate_check->fetch(PDO::FETCH_ASSOC);
        
        if ($duplicate_result['count'] > 0) {
            throw new Exception('Duplicate order detected. Please wait before placing another order.');
        }
        
        // Insert the order - DO NOT specify order_id in the INSERT
        $sql = "INSERT INTO `order` (
                    customer_id, 
                    employee_id, 
                    order_date, 
                    status, 
                    total_amount, 
                    payment_method, 
                    discount
                ) VALUES (
                    :customer_id, 
                    NULL, 
                    NOW(), 
                    'Pending', 
                    :total_amount, 
                    :payment_method, 
                    :discount
                )";
                
        $params = [
            ':customer_id' => (int)$user_id,
            ':total_amount' => (float)$total_amount,
            ':payment_method' => $payment_method,
            ':discount' => (float)$discount
        ];
        
        log_debug('Preparing SQL', ['sql' => $sql, 'params' => $params]);
        
        $stmt = $dbh->prepare($sql);
        if ($stmt === false) {
            $error = $dbh->errorInfo();
            throw new Exception('Failed to prepare statement: ' . ($error[2] ?? 'Unknown error'));
        }
        
        // Execute the insert
        log_debug('Executing order insert', $params);
        $result = $stmt->execute($params);
        
        if (!$result) {
            $error = $stmt->errorInfo();
            throw new Exception('Failed to execute order insert: ' . ($error[2] ?? 'Unknown error'));
        }
        
        log_debug('Order insert executed successfully');
        
        // Get the last insert ID
        $order_id = $dbh->lastInsertId();
        log_debug('Order ID retrieved', ['order_id' => $order_id]);
        
        if (!$order_id || $order_id == 0) {
            // Alternative method to get the order ID
            $last_order = $dbh->prepare("
                SELECT order_id 
                FROM `order` 
                WHERE customer_id = ? 
                AND total_amount = ? 
                ORDER BY order_date DESC 
                LIMIT 1
            ");
            $last_order->execute([$user_id, $total_amount]);
            $order_result = $last_order->fetch(PDO::FETCH_ASSOC);
            $order_id = $order_result['order_id'] ?? null;
            
            if (!$order_id) {
                throw new Exception('Failed to retrieve order ID');
            }
        }
        
        // Insert order details
        $stmt_item = $dbh->prepare("INSERT INTO order_details (order_id, item_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
        
        // Debug: Log the items we're trying to insert
        log_debug('Processing order items', ['items' => $data['items']]);
        
        foreach ($data['items'] as $item) {
            try {
                // Get item_id from menu with error handling
                $menu_stmt = $dbh->prepare("SELECT item_id FROM menu WHERE item_name = ?");
                $menu_stmt->execute([$item['ItemName']]);
                $menu_item = $menu_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$menu_item) {
                    throw new Exception('Menu item not found: ' . $item['ItemName']);
                }
                
                $subtotal = $item['ItemPrice'] * $item['ItemQuantity'];
                
                // Debug: Log the item being processed
                log_debug('Processing order item', [
                    'item_name' => $item['ItemName'],
                    'item_id' => $menu_item['item_id'],
                    'quantity' => $item['ItemQuantity'],
                    'subtotal' => $subtotal
                ]);
                
                // Insert order detail
                $result = $stmt_item->execute([
                    $order_id, 
                    $menu_item['item_id'], 
                    $item['ItemQuantity'], 
                    $subtotal
                ]);
                
                if (!$result) {
                    $error = $stmt_item->errorInfo();
                    throw new Exception('Failed to insert order detail: ' . ($error[2] ?? 'Unknown error'));
                }
                
                // Verify the insert
                $detail_id = $dbh->lastInsertId();
                log_debug('Order detail inserted', ['detail_id' => $detail_id]);
                
                if (!$detail_id) {
                    throw new Exception('Failed to get order detail ID after insert');
                }
                
            } catch (Exception $e) {
                log_debug('Error processing order item', [
                    'error' => $e->getMessage(),
                    'item' => $item
                ]);
                throw new Exception('Error processing order item "' . $item['ItemName'] . '": ' . $e->getMessage());
            }
        }
        
        // If we get here, everything was successful
        $dbh->commit();
        
        // Generate receipt HTML
        ob_start();
        include 'generate_receipt.php';
        $receiptHtml = generateReceipt($dbh, $order_id, false);
        
        // Save receipt to file (optional)
        $receiptDir = __DIR__ . '/receipts';
        if (!file_exists($receiptDir)) {
            mkdir($receiptDir, 0755, true);
        }
        $receiptFile = $receiptDir . '/receipt_' . $order_id . '_' . time() . '.html';
        file_put_contents($receiptFile, $receiptHtml);
        
        // Clear the cart
        unset($_SESSION['cart']);
        
        // Return success response with receipt URL
        $receiptUrl = 'generate_receipt.php?order_id=' . $order_id;
        sendResponse(200, 'Order placed successfully', [
            'order_id' => $order_id,
            'receipt_url' => $receiptUrl,
            'receipt_html' => $receiptHtml // Include the receipt HTML in the response
        ]);
        
    } catch (Exception $e) {
        log_debug('Error in transaction', [
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        try {
            $dbh->rollBack();
            log_debug('Transaction rolled back');
        } catch (Exception $rollbackEx) {
            log_debug('Error rolling back transaction', ['error' => $rollbackEx->getMessage()]);
        }
        
        sendResponse(500, 'Error: ' . $e->getMessage());
    }
    
} catch (PDOException $e) {
    // Database error
    $errorInfo = $e->errorInfo;
    $errorMsg = 'Database error: ' . $e->getMessage() . 
              ' | Error Code: ' . $e->getCode() .
              ' | SQL State: ' . ($errorInfo[0] ?? '') . 
              ' | Driver Code: ' . ($errorInfo[1] ?? '') .
              ' | Driver Message: ' . ($errorInfo[2] ?? '');
        
    error_log($errorMsg);
        
    // If we're in a transaction, roll it back
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
        
    // Only show detailed error in development
    $errorMessage = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)
                  ? $errorMsg
                  : 'A database error occurred. Please try again later.';
        
    sendResponse(500, $errorMessage);
        
} catch (Exception $e) {
    // Other errors
    error_log('Error in process-payment: ' . $e->getMessage());
        
    // If we're in a transaction, roll it back
    if (isset($dbh) && $dbh->inTransaction()) {
        $dbh->rollBack();
    }
        
    sendResponse(400, $e->getMessage());
}