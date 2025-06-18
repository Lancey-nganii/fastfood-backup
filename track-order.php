<?php
session_start();
include('includes/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// Fetch orders for display using PDO
$stmt = $dbh->prepare("SELECT o.order_id, o.order_date, o.status, o.total_amount, o.payment_method, o.delivery_address 
    FROM `order` o 
    WHERE o.customer_id = ? 
    ORDER BY o.order_date DESC");
$stmt->execute([$customer_id]);
$orders_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orders = [];
foreach ($orders_result as $row) {
    $order_id = $row['order_id'];

    // Fetch order items for each order
    $item_stmt = $dbh->prepare("SELECT m.item_name, od.quantity, od.subtotal 
        FROM order_details od 
        JOIN menu m ON od.item_id = m.item_id 
        WHERE od.order_id = ?");
    $item_stmt->execute([$order_id]);
    $items_result = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $total_items = 0;
    foreach ($items_result as $item) {
        $items[] = [
            'name' => $item['item_name'],
            'quantity' => (int)$item['quantity'],
            'subtotal' => (float)$item['subtotal']
        ];
        $total_items += (int)$item['quantity'];
    }

    // Get the latest status update
    $status_stmt = $dbh->prepare("SELECT status, status_date, notes 
        FROM order_status 
        WHERE order_id = ? 
        ORDER BY status_date DESC 
        LIMIT 1");
    $status_stmt->execute([$order_id]);
    $status_info = $status_stmt->fetch(PDO::FETCH_ASSOC);
    
    $current_status = $status_info ? strtolower($status_info['status']) : 'pending';
    $status_date = $status_info ? $status_info['status_date'] : $row['order_date'];
    $status_notes = $status_info ? $status_info['notes'] : '';

    $orders[] = [
        'id' => $row['order_id'],
        'date' => $row['order_date'],
        'status' => $current_status,
        'status_date' => $status_date,
        'status_notes' => $status_notes,
        'total' => (float)$row['total_amount'],
        'payment_method' => $row['payment_method'],
        'delivery_address' => $row['delivery_address'],
        'total_items' => $total_items,
        'items' => $items
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Track Order - FastBite</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --sidebar-width: 220px;
      --primary: #cc5050;
      --secondary: #d3c260;
      --bg-light: #f9f9f9;
      --text-dark: #333;
      --badge-radius: 12px;
    }
    body { display: flex; margin: 0; font-family: "Segoe UI", sans-serif; background: var(--bg-light);}
    .sidebar { width: var(--sidebar-width); background: linear-gradient(to right, var(--primary), var(--secondary)); color: white; padding-top: 2rem; position: fixed; top: 0; bottom: 0; left: 0;}
    .sidebar h2 { text-align: center; margin-bottom: 2rem;}
    .sidebar ul { list-style: none; padding: 0;}
    .sidebar ul li a { color: white; text-decoration: none; padding: 1rem 1.5rem; display: block;}
    .main { margin-left: var(--sidebar-width); padding: 2rem; flex: 1;}
    h1 { color: var(--text-dark); margin-bottom: 1rem;}
    .section { margin-bottom: 3rem;}
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden;}
    th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee;}
    th { background: #f1f1f1;}
    .status { display: inline-block; padding: 4px 10px; border-radius: var(--badge-radius); font-size: 0.9rem; text-transform: capitalize;}
    .pending { background: #ffe08a; color: #8a6d00;}
    .completed { background: #c9f7c9; color: #256029;}
    .cancelled { background: #fddede; color: #912d2d;}
    .empty { padding: 1rem; font-style: italic; color: #666;}
    @media (max-width: 768px) { th, td { padding: 0.6rem; font-size: 0.9rem; } }
  </style>
</head>
<body>

  <nav class="sidebar">
    <h2>FastBite</h2>
    <ul>
      <li><a href="customer-dashboard.php">Home</a></li>
      <li><a href="menu.php">Menu</a></li>
      <li><a href="track-order.php" class="active">Orders</a></li>
      <li><a href="profile.php">Profile</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>

  <div class="main">
    <h1>Track Your Orders</h1>

    <div class="section">
      <h2>Present Orders</h2>
      <table id="present-orders">
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Date</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        </tbody>
      </table>
    </div>

    <div class="section">
      <h2>Past Orders</h2>
      <table id="past-orders">
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Date</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    const orders = <?php echo json_encode($orders); ?>;

    const presentBody = document.querySelector("#present-orders tbody");
    const pastBody = document.querySelector("#past-orders tbody");

    function renderOrders() {
      presentBody.innerHTML = "";
      pastBody.innerHTML = "";

      let hasPresent = false;
      let hasPast = false;

      orders.forEach(order => {
        const row = document.createElement("tr");
        const itemsText = order.items.map(i => `${i.name} ×${i.quantity}`).join(", ");

        row.innerHTML = `
          <td>${order.id}</td>
          <td>${order.date}</td>
          <td>${itemsText}</td>
          <td>₱${order.total.toFixed(2)}</td>
          <td><span class="status ${order.status}">${order.status}</span></td>
        `;

        if (order.status === "pending") {
          presentBody.appendChild(row);
          hasPresent = true;
        } else {
          pastBody.appendChild(row);
          hasPast = true;
        }
      });

      if (!hasPresent) {
        presentBody.innerHTML = `<tr><td colspan="5" class="empty">No current orders.</td></tr>`;
      }
      if (!hasPast) {
        pastBody.innerHTML = `<tr><td colspan="5" class="empty">No past orders.</td></tr>`;
      }
    }

    renderOrders();
  </script>
</body>
</html>