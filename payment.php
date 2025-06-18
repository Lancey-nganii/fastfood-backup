<?php
session_start();
include('includes/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

// Fetch cart items from session
$cart = $_SESSION['cart'] ?? [];
$itemDetails = [];
$total = 0;

if (!empty($cart)) {
    $itemIds = array_column($cart, 'item_id');
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

    $stmt = $dbh->prepare("SELECT * FROM menu WHERE item_id IN ($placeholders)");
    $stmt->execute($itemIds);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        foreach ($cart as $item) {
            if ($item['item_id'] == $row['item_id']) {
                $row['quantity'] = $item['quantity'];
                $row['subtotal'] = $item['quantity'] * $row['price'];
                $total += $row['subtotal'];
                $itemDetails[] = $row;
                break;
            }
        }
    }
}

// If no items, redirect back to cart
if (empty($itemDetails)) {
    header('Location: cart.php');
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
  <style>
    :root {
      --sidebar-width: 220px;
      --primary: #cc5050;
      --secondary: #d3c260;
      --bg-light: #fff8f0;
      --text-dark: #333;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: "Segoe UI", sans-serif;
    }

    body {
      display: flex;
      background-color: var(--bg-light);
      min-height: 100vh;
    }

    .sidebar {
      width: var(--sidebar-width);
      background: linear-gradient(to right, var(--primary), var(--secondary));
      color: white;
      padding-top: 2rem;
      position: fixed;
      top: 0;
      bottom: 0;
      left: 0;
    }

    .sidebar h2 {
      text-align: center;
      margin-bottom: 2rem;
      font-size: 1.5rem;
    }

    .sidebar ul {
      list-style: none;
      padding: 0;
    }

    .sidebar ul li {
      margin: 1rem 0;
    }

    .sidebar ul li a {
      color: white;
      text-decoration: none;
      padding: 0.8rem 1.5rem;
      display: block;
      transition: background 0.3s;
    }

    .sidebar ul li a:hover,
    .sidebar ul li a.active {
      background: rgba(255, 255, 255, 0.2);
      border-left: 4px solid white;
    }

    .main-content {
      margin-left: var(--sidebar-width);
      padding: 2rem;
      flex: 1;
    }

    .main-content h1 {
      font-size: 2rem;
      margin-bottom: 1.5rem;
      color: var(--text-dark);
    }

    .payment-card,
    .receipt-card {
      background: white;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
      max-width: 700px;
      margin-bottom: 2rem;
    }

    .section {
      margin-bottom: 2rem;
    }

    .section h2 {
      font-size: 1.2rem;
      margin-bottom: 1rem;
      color: var(--primary);
    }

    .payment-methods label {
      display: block;
      margin-bottom: 1rem;
    }

    .order-summary table {
      width: 100%;
      border-collapse: collapse;
    }

    .order-summary th,
    .order-summary td {
      text-align: left;
      padding: 0.75rem;
      border-bottom: 1px solid #eee;
    }

    .total {
      text-align: right;
      font-weight: bold;
      margin-top: 1rem;
    }

    button {
      background: var(--primary);
      color: white;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1rem;
    }

    button:hover {
      background: #b84444;
    }

    .hidden {
      display: none;
    }

    input[type="text"] {
      padding: 0.5rem;
      width: 100%;
      margin-top: 0.5rem;
      border: 1px solid #ccc;
      border-radius: 5px;
    }

    @media (max-width: 768px) {
      .sidebar {
        width: 100%;
        position: static;
        display: flex;
        justify-content: space-around;
        flex-wrap: wrap;
        height: auto;
      }

      .sidebar h2 {
        display: none;
      }

      .sidebar ul {
        display: flex;
        width: 100%;
        justify-content: space-around;
      }

      .main-content {
        margin-left: 0;
        padding-top: 1rem;
      }
    }
  </style>

</head>
<body>
  <nav class="sidebar">
    <h2>FastBite</h2>
    <ul>
      <li><a href="customer-dashboard.php"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="menu.php"><i class="fas fa-utensils"></i> Menu</a></li>
      <li><a href="customer-orders.php" class="active"><i class="fas fa-clipboard-list"></i> Track My Orders</a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log out</a></li>
    </ul>
  </nav>

  <div class="main-content">
    <h1>Payment</h1>

    <div class="payment-card" id="paymentForm">
      <div class="section payment-methods">
        <h2>Select Payment Method</h2>
        <label><input type="radio" name="payment" value="Cash on Delivery" checked> Cash on Delivery</label>
        <label><input type="radio" name="payment" value="GCash"> GCash</label>
        <label><input type="radio" name="payment" value="Credit Card"> Credit/Debit Card</label>
      </div>

      <div class="section">
        <h2>Apply Discount</h2>
        <label><input type="radio" name="discount" value="NONE" checked onchange="loadOrderSummary()"> No Discount</label><br>
        <label><input type="radio" name="discount" value="PWD20" onchange="loadOrderSummary()"> PWD (20%)</label><br>
        <label><input type="radio" name="discount" value="SENIOR20" onchange="loadOrderSummary()"> Senior Citizen (20%)</label>
      </div>

      <div class="section order-summary">
        <h2>Order Summary</h2>
        <table id="orderTable"></table>
        <div class="total" id="totalAmount">Total: ₱0.00</div>
      </div>

      <button type="button" id="submitPayment" onclick="confirmPayment()">
        <i class="fa fa-credit-card"></i> Confirm Payment
      </button>
    </div>

    <div class="receipt-card hidden" id="receiptCard">
      <div class="section">
        <h2>Receipt</h2>
        <p><strong>Receipt ID:</strong> <span id="receiptID"></span></p>
        <p><strong>Customer ID:</strong> <span id="customerID"></span></p>
        <p><strong>Date:</strong> <span id="receiptDate"></span></p>
        <p><strong>Payment Method:</strong> <span id="receiptMethod"></span></p>
        <p><strong>Payment Status:</strong> <span id="paymentStatus"></span></p>
      </div>

      <div class="section order-summary">
        <h3>Items</h3>
        <table id="receiptTable"></table>
        <div class="total" id="receiptTotal">Total Paid: ₱0.00</div>
      </div>
    </div>
  </div>

  <script>
    // Pass PHP cart items to JS
    const order = {
      Items: <?php
        // Output as JS array of objects
        $jsItems = [];
        foreach ($itemDetails as $item) {
          $jsItems[] = [
            'ItemName' => $item['item_name'],
            'ItemPrice' => $item['price'],
            'ItemQuantity' => $item['quantity']
          ];
        }
        echo json_encode($jsItems);
      ?>
    };
    let baseTotal = 0;

    function formatCurrency(amount) {
      return `₱${amount.toFixed(2)}`;
    }

    function loadOrderSummary() {
      const orderTable = document.getElementById("orderTable");
      const receiptTable = document.getElementById("receiptTable");
      const totalAmount = document.getElementById("totalAmount");
      const receiptTotal = document.getElementById("receiptTotal");

      orderTable.innerHTML = "";
      receiptTable.innerHTML = "";
      baseTotal = 0;

      order.Items.forEach(item => {
        const subtotal = item.ItemPrice * item.ItemQuantity;
        baseTotal += subtotal;
        const row = `<tr><td>${item.ItemName} x${item.ItemQuantity}</td><td>${formatCurrency(subtotal)}</td></tr>`;
        orderTable.innerHTML += row;
        receiptTable.innerHTML += row;
      });

      let regularDiscount = baseTotal >= 500 ? baseTotal * 0.10 : 0;
      const discountType = document.querySelector('input[name="discount"]:checked')?.value || "NONE";
      let specialDiscount = 0;
      if (discountType === "PWD20" || discountType === "SENIOR20") {
        specialDiscount = (baseTotal - regularDiscount) * 0.20;
      }

      const finalTotal = baseTotal - regularDiscount - specialDiscount;

      let discountDetails = '';
      if (regularDiscount > 0) discountDetails += `<div>Regular Discount (10%): -${formatCurrency(regularDiscount)}</div>`;
      if (specialDiscount > 0) discountDetails += `<div>Special Discount: -${formatCurrency(specialDiscount)}</div>`;

      totalAmount.innerHTML = `
        <div>Subtotal: ${formatCurrency(baseTotal)}</div>
        ${discountDetails}
        <div style="font-weight: bold; margin-top: 0.5rem;">Total: ${formatCurrency(finalTotal)}</div>
      `;

      receiptTotal.textContent = `Total Paid: ${formatCurrency(finalTotal)}`;
    }

    function confirmPayment() {
      loadOrderSummary();

      const selectedMethod = document.querySelector('input[name="payment"]:checked').value;
      const discount = document.querySelector('input[name="discount"]:checked').value;

      const payload = {
        payment_method: selectedMethod,
        discount: discount,
        items: order.Items,
        total_amount: parseFloat(document.getElementById("receiptTotal").textContent.replace(/[^\d.]/g, ''))
      };

      // Show loading state
      const submitBtn = document.getElementById('submitPayment');
      const originalBtnText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';

      // Include the receipt modal script
      const receiptModalScript = document.createElement('script');
      receiptModalScript.src = 'js/receipt-modal.js';
      document.head.appendChild(receiptModalScript);
      
      // Include Font Awesome for icons
      const fontAwesome = document.createElement('link');
      fontAwesome.rel = 'stylesheet';
      fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
      document.head.appendChild(fontAwesome);

      fetch("process-payment.php", {
        method: "POST",
        headers: { 
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest"
        },
        body: JSON.stringify(payload)
      })
      .then(async (response) => {
        const data = await response.json();
        
        if (!response.ok) {
          // Handle HTTP error status codes
          throw new Error(data.message || 'Payment failed');
        }
        
        if (data.status === 'success') {
          // Show receipt modal with order details
          showReceiptModal(
            data.data.order_id, 
            data.data.receipt_url || `generate_receipt.php?order_id=${data.data.order_id}`
          );
          
          // Clear the cart after successful payment
          if (typeof clearCart === 'function') {
            clearCart();
          }
        } else {
          throw new Error(data.message || 'Invalid response from server');
        }
      })
      .catch(error => {
        console.error('Payment error:', error);
        alert(error.message || 'An error occurred during payment. Please try again.');
      })
      .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
      });
    }

    window.onload = loadOrderSummary;
  </script>
</body>
</html>
