<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Ensure user is logged in
if (!isCustomerLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = 'My Orders';

// Get customer's orders
$stmt = $dbh->prepare("
    SELECT o.*, COUNT(od.orderdetail_id) as item_count, 
           SUM(od.quantity) as total_items,
           (SELECT status FROM order_status 
            WHERE order_id = o.order_id 
            ORDER BY status_date DESC LIMIT 1) as current_status
    FROM `order` o
    LEFT JOIN order_details od ON o.order_id = od.order_id
    WHERE o.customer_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-list"></i> My Orders</h1>
        <p class="lead">View your order history and track current orders</p>
    </div>

    <?php if (empty($orders)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> You haven't placed any orders yet.
            <a href="menu.php" class="alert-link">Browse our menu</a> to get started!
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?></td>
                            <td><?php echo (int)$order['total_items']; ?> item<?php echo $order['total_items'] != 1 ? 's' : ''; ?></td>
                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <?php 
                                $status_class = [
                                    'pending' => 'warning',
                                    'preparing' => 'info',
                                    'ready' => 'primary',
                                    'completed' => 'success',
                                    'cancelled' => 'danger'
                                ][strtolower($order['current_status'] ?? 'pending')] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo ucfirst(htmlspecialchars($order['current_status'] ?? 'pending')); ?>
                                </span>
                            </td>
                            <td>
                                <a href="generate_receipt.php?order_id=<?php echo $order['order_id']; ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-outline-primary"
                                   title="View Receipt">
                                    <i class="fas fa-receipt"></i> Receipt
                                </a>
                                <button class="btn btn-sm btn-outline-secondary view-order-details" 
                                        data-order-id="<?php echo $order['order_id']; ?>"
                                        title="View Details">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order #<span id="modalOrderId"></span> Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <!-- Order details will be loaded here via AJAX -->
                <div class="text-center my-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading order details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="viewReceiptBtn" class="btn btn-primary">
                    <i class="fas fa-receipt"></i> View Full Receipt
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Handle order details modal
$(document).ready(function() {
    $('.view-order-details').on('click', function() {
        const orderId = $(this).data('order-id');
        const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
        
        // Update modal title and receipt button
        $('#modalOrderId').text(orderId);
        $('#viewReceiptBtn').attr('href', `generate_receipt.php?order_id=${orderId}`);
        
        // Load order details via AJAX
        $.get(`ajax/get_order_details.php?order_id=${orderId}`, function(data) {
            $('#orderDetailsContent').html(data);
        }).fail(function() {
            $('#orderDetailsContent').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Failed to load order details. Please try again later.
                </div>
            `);
        });
        
        modal.show();
    });
    
    // Refresh page when modal is closed if needed
    $('#orderDetailsModal').on('hidden.bs.modal', function () {
        const needsRefresh = $(this).data('refresh-on-close');
        if (needsRefresh) {
            location.reload();
        }
    });
});
</script>
