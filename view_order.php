<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'glowcare');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle AJAX Requests for Marking as Completed and Deleting Orders
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $order_id = $_POST['order_id'];

        if ($_POST['action'] == 'mark_completed') {
            // Mark Order as Completed
            $stmt = $conn->prepare("UPDATE orders SET status = 'Completed' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            if ($stmt->execute()) {
                echo 'success';
            } else {
                echo 'error';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'cancel_order') {
            // Delete Related Order Items First
            $stmt_items = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt_items->bind_param("i", $order_id);
            if (!$stmt_items->execute()) {
                echo 'Failed to delete order items';
                exit();
            }
            $stmt_items->close();

            // Now, delete the order itself
            $stmt_order = $conn->prepare("DELETE FROM orders WHERE id = ?");
            $stmt_order->bind_param("i", $order_id);
            if ($stmt_order->execute()) {
                echo 'success';
            } else {
                echo 'Failed to delete order';
            }
            $stmt_order->close();
        }
    }
    exit(); // Prevent further page loading if it's an AJAX request
}

// Fetch orders with customer names
$sql = "SELECT o.id AS order_id, u.name AS customer_name, o.total_amount, o.status
        FROM orders o
        JOIN users u ON o.user_id = u.id";

$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Orders List - BareBelle</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Quicksand', sans-serif;
      background: linear-gradient(135deg, #c3cfea, #f8c8dc);
      padding: 60px 20px;
      min-height: 100vh;
    }

    .title {
      text-align: center;
      font-size: 2rem;
      font-weight: bold;
      color: #9f5f80;
      margin-bottom: 30px;
    }

    .btn-complete {
      background-color: #6bcf63;
      color: white;
    }

    .btn-cancel {
      background-color: #ff6b6b;
      color: white;
    }

    .btn-complete:hover {
      background-color: #5abb55;
    }

    .btn-cancel:hover {
      background-color: #e65b5b;
    }

    .table td,
    .table th {
      vertical-align: middle;
    }
  </style>
</head>
<body>

<!-- Navbar (same as before) -->
<nav class="navbar navbar-expand-lg navbar-light shadow fixed-top">
  <div class="container">
    <a class="navbar-brand" href="#">BareBelle Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="adminNavbar">
      <ul class="navbar-nav align-items-center">
        <li class="nav-item"><a class="nav-link" href="add_product.php">Add Product</a></li>
        <li class="nav-item"><a class="nav-link" href="manage_products.php">Manage Products</a></li>
        <li class="nav-item"><a class="nav-link" href="view_order.php">Manage Orders</a></li>
        <li class="nav-item"><a class="nav-link" href="user_list.php">Manage Customers</a></li>
        <li class="nav-item ms-3">
          <a class="btn" href="logout.php" style="background-color: #f8c8dc; color: black;">Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container">
  <div class="title">Customer Orders</div>

  <div class="table-responsive">
    <table class="table table-bordered table-striped bg-white shadow text-center align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Customer</th>
          <th>Total</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="ordersTable">
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while($order = $result->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($order['order_id']); ?></td>
              <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
              <td>Rs. <?php echo htmlspecialchars($order['total_amount']); ?></td>
              <td>
                <?php if ($order['status'] == 'Pending'): ?>
                  <span class="badge bg-warning text-dark">Pending</span>
                <?php else: ?>
                  <span class="badge bg-success">Completed</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($order['status'] == 'Pending'): ?>
                  <button class="btn btn-sm btn-complete" data-order-id="<?php echo $order['order_id']; ?>">Mark as Completed</button>
                  <button class="btn btn-sm btn-cancel" data-order-id="<?php echo $order['order_id']; ?>">Cancel</button>
                <?php else: ?>
                  <button class="btn btn-sm btn-cancel" data-order-id="<?php echo $order['order_id']; ?>">Delete</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5">No orders found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  // Handle "Mark as Completed" button click using AJAX
  document.querySelectorAll('.btn-complete').forEach(btn => {
    btn.addEventListener('click', function () {
        const orderId = this.getAttribute('data-order-id');
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '', true); // Use the current page for the request
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function () {
            if (xhr.status == 200 && xhr.responseText == 'success') {
                alert('Order marked as completed!');
                location.reload(); // Reload to reflect changes
            } else {
                alert('Failed to mark order as completed!');
            }
        };
        xhr.send('action=mark_completed&order_id=' + orderId);
    });
  });

  // Handle "Cancel" button click using AJAX
  document.querySelectorAll('.btn-cancel').forEach(btn => {
    btn.addEventListener('click', function () {
        if (confirm("Are you sure you want to cancel/delete this order?")) {
            const orderId = this.getAttribute('data-order-id');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true); // Use the current page for the request
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (xhr.status == 200 && xhr.responseText == 'success') {
                    alert('Order cancelled/deleted successfully!');
                    location.reload(); // Reload to reflect changes
                } else {
                    alert('Failed to cancel/delete order!');
                }
            };
            xhr.send('action=cancel_order&order_id=' + orderId);
        }
    });
  });
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
