<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Ensure user is logged in and request is AJAX
if (!isCustomerLoggedIn() || empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    exit('Access Denied');
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    http_response_code(400);
    exit('Invalid order ID');
}

$user_id = $_SESSION['user_id'];

try {
    // Verify the order belongs to the logged-in user
    $stmt = $dbh->prepare("
        SELECT o.*, 
               (SELECT status FROM order_status 
                WHERE order_id = o.order_id 
                ORDER BY status_date DESC LIMIT 1) as current_status
        FROM `order` o
        WHERE o.order_id = ? AND o.customer_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found or access denied');
    }
    
    // Get order items
    $stmt = $dbh->prepare("
        SELECT m.item_name, od.quantity, m.price, od.subtotal
        FROM order_details od
        JOIN menu m ON od.item_id = m.item_id
        WHERE od.order_id = ?
        ORDER BY m.item_name
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order status history
    $stmt = $dbh->prepare("
        SELECT status, status_date, notes
        FROM order_status
        WHERE order_id = ?
        ORDER BY status_date DESC
    ");
    $stmt->execute([$order_id]);
    $status_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $subtotal = $order['total_amount'] + $order['discount'];
    $tax = $subtotal * 0.12; // 12% tax
    
    // Start output buffering
    ob_start();
    ?>
    
    <div class="row">
        <div class="col-md-6">
            <h5>Order Summary</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td class="text-end"><?php echo (int)$item['quantity']; ?></td>
                                <td class="text-end">₱<?php echo number_format($item['price'], 2); ?></td>
                                <td class="text-end">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">Subtotal:</th>
                            <th class="text-end">₱<?php echo number_format($subtotal, 2); ?></th>
                        </tr>
                        <?php if ($order['discount'] > 0): ?>
                        <tr>
                            <th colspan="3" class="text-end">Discount:</th>
                            <th class="text-end">-₱<?php echo number_format($order['discount'], 2); ?></th>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th colspan="3" class="text-end">Tax (12%):</th>
                            <th class="text-end">₱<?php echo number_format($tax, 2); ?></th>
                        </tr>
                        <tr class="table-active">
                            <th colspan="3" class="text-end">Total:</th>
                            <th class="text-end">₱<?php echo number_format($order['total_amount'], 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="mt-4">
                <h5>Delivery Information</h5>
                <p class="mb-1">
                    <strong>Payment Method:</strong> 
                    <?php echo ucfirst(htmlspecialchars($order['payment_method'])); ?>
                </p>
                <p class="mb-1">
                    <strong>Delivery Address:</strong> 
                    <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?>
                </p>
                <?php if (!empty($order['special_instructions'])): ?>
                <p class="mb-0">
                    <strong>Special Instructions:</strong> 
                    <?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-6">
            <h5>Order Status</h5>
            <div class="timeline">
                <?php 
                $statuses = [
                    'pending' => ['Pending', 'Your order has been received and is being processed.'],
                    'preparing' => ['Preparing', 'Your order is being prepared in our kitchen.'],
                    'ready' => ['Ready for Pickup/Delivery', 'Your order is ready for pickup or out for delivery.'],
                    'completed' => ['Completed', 'Your order has been delivered and completed.'],
                    'cancelled' => ['Cancelled', 'Your order has been cancelled.']
                ];
                
                $current_status = strtolower($order['current_status']);
                $status_found = false;
                
                foreach ($statuses as $status => $status_info):
                    $is_active = $status === $current_status || $status_found;
                    $is_completed = $status !== $current_status && !$status_found;
                    $status_found = $status === $current_status || $status_found;
                    ?>
                    <div class="timeline-item <?php echo $is_active ? 'active' : ''; ?>">
                        <div class="timeline-icon">
                            <?php if ($is_active): ?>
                                <i class="fas fa-<?php echo $status === $current_status ? 'check-circle' : 'circle-notch fa-spin'; ?>"></i>
                            <?php else: ?>
                                <i class="fas fa-<?php echo $is_completed ? 'check-circle' : 'circle'; ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="timeline-content">
                            <h6><?php echo $status_info[0]; ?></h6>
                            <p class="mb-1"><?php echo $status_info[1]; ?></p>
                            <?php 
                            // Find matching status update
                            $status_update = array_filter($status_history, function($s) use ($status) {
                                return strtolower($s['status']) === $status;
                            });
                            $status_update = reset($status_update);
                            
                            if ($status_update): ?>
                                <small class="text-muted">
                                    <?php echo date('M j, Y g:i A', strtotime($status_update['status_date'])); ?>
                                    <?php if (!empty($status_update['notes'])): ?>
                                        <br><?php echo htmlspecialchars($status_update['notes']); ?>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    .timeline:before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
        opacity: 0.6;
    }
    .timeline-item.active {
        opacity: 1;
    }
    .timeline-item:last-child {
        margin-bottom: 0;
    }
    .timeline-icon {
        position: absolute;
        left: -30px;
        top: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
    }
    .timeline-icon i {
        font-size: 16px;
        color: #6c757d;
    }
    .timeline-item.active .timeline-icon i {
        color: #0d6efd;
    }
    .timeline-content {
        padding: 10px 15px;
        background: #f8f9fa;
        border-radius: 4px;
        margin-left: 10px;
    }
    .timeline-item.active .timeline-content {
        background: #f0f7ff;
        border-left: 3px solid #0d6efd;
    }
    </style>
    
    <?php
    // Get the buffered content and clean the buffer
    $output = ob_get_clean();
    echo $output;
    
} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
