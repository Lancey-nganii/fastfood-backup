<?php
/**
 * Generate and output a receipt for an order
 * 
 * @param PDO $dbh Database connection
 * @param int $orderId The order ID to generate receipt for
 * @param bool $output Whether to output the receipt (true) or return as string (false)
 * @return string|void Returns receipt HTML if $output is false
 */
function generateReceipt($dbh, $orderId, $output = true) {
    try {
        // Get order details
        $orderStmt = $dbh->prepare("
            SELECT o.*, c.first_name, c.last_name, c.email, c.phone, c.address
            FROM `order` o
            LEFT JOIN customer c ON o.customer_id = c.customer_id
            WHERE o.order_id = ?
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception("Order not found");
        }
        
        // Get order items
        $itemsStmt = $dbh->prepare("
            SELECT m.item_name, od.quantity, m.price, od.subtotal
            FROM order_details od
            JOIN menu m ON od.item_id = m.item_id
            WHERE od.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $subtotal = $order['total_amount'] + $order['discount'];
        $tax = $subtotal * 0.12; // Assuming 12% tax rate, adjust as needed
        $total = $subtotal + $tax;
        
        // Start building receipt HTML
        $receipt = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Order Receipt #' . htmlspecialchars($orderId) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .restaurant-name { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
                .receipt-title { font-size: 20px; margin: 10px 0; }
                .order-info { margin-bottom: 20px; }
                .order-info p { margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f5f5f5; }
                .text-right { text-align: right; }
                .total-row { font-weight: bold; }
                .footer { margin-top: 30px; text-align: center; font-size: 14px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="restaurant-name">FastFood Restaurant</div>
                <div>123 Food Street, Cityville</div>
                <div>Phone: (123) 456-7890</div>
            </div>
            
            <h2 class="receipt-title">Order Receipt</h2>
            
            <div class="order-info">
                <p><strong>Order #:</strong> ' . htmlspecialchars($orderId) . '</p>
                <p><strong>Date:</strong> ' . date('F j, Y g:i A', strtotime($order['order_date'])) . '</p>
                <p><strong>Customer:</strong> ' . htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) . '</p>
                <p><strong>Status:</strong> ' . ucfirst(htmlspecialchars($order['status'])) . '</p>
                <p><strong>Payment Method:</strong> ' . ucfirst(htmlspecialchars($order['payment_method'])) . '</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>';
        
        // Add order items
        foreach ($items as $item) {
            $receipt .= '
                    <tr>
                        <td>' . htmlspecialchars($item['item_name']) . '</td>
                        <td class="text-right">' . (int)$item['quantity'] . '</td>
                        <td class="text-right">₱' . number_format($item['price'], 2) . '</td>
                        <td class="text-right">₱' . number_format($item['subtotal'], 2) . '</td>
                    </tr>';
        }
        
        // Add totals
        $receipt .= '
                    <tr class="total-row">
                        <td colspan="3" class="text-right">Subtotal:</td>
                        <td class="text-right">₱' . number_format($subtotal, 2) . '</td>
                    </tr>';
        
        if ($order['discount'] > 0) {
            $receipt .= '
                    <tr>
                        <td colspan="3" class="text-right">Discount:</td>
                        <td class="text-right">-₱' . number_format($order['discount'], 2) . '</td>
                    </tr>';
        }
        
        $receipt .= '
                    <tr>
                        <td colspan="3" class="text-right">Tax (12%):</td>
                        <td class="text-right">₱' . number_format($tax, 2) . '</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3" class="text-right">Total:</td>
                        <td class="text-right">₱' . number_format($order['total_amount'], 2) . '</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="footer">
                <p>Thank you for your order!</p>
                <p>For any questions, please contact us at info@fastfood.com</p>
                <p>Order ID: ' . htmlspecialchars($orderId) . '</p>
            </div>
        </body>
        </html>';
        
        if ($output) {
            // Output as HTML
            header('Content-Type: text/html');
            echo $receipt;
        } else {
            return $receipt;
        }
        
    } catch (Exception $e) {
        error_log("Error generating receipt: " . $e->getMessage());
        if ($output) {
            echo "<p>Error generating receipt: " . htmlspecialchars($e->getMessage()) . "</p>";
        } else {
            return "Error generating receipt: " . $e->getMessage();
        }
    }
}

// If this file is called directly, show receipt for testing
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once 'config.php'; // Make sure to include your database configuration
    $orderId = $_GET['order_id'] ?? 0;
    if ($orderId) {
        generateReceipt($dbh, $orderId);
    } else {
        echo "<p>No order ID provided. Use ?order_id=X in the URL to view a receipt.</p>";
    }
}
?>
