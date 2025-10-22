<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only farmers
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];
$ordersCollection = $db->orders;
$usersCollection = $db->users;
$productsCollection = $db->products;

// ---------------- Handle Actions ----------------
if (isset($_GET['action'], $_GET['id'])) {
    $orderId = $_GET['id'];
    $action = $_GET['action'];

    try {
        $objId = new MongoDB\BSON\ObjectId($orderId);
    } catch (Exception $e) {
        die("Invalid Order ID");
    }

    if ($action === 'accept') {
        $ordersCollection->updateOne(
            ['_id' => $objId, 'farmer' => $username],
            ['$set' => ['status' => 'Accepted']]
        );
    } elseif ($action === 'reject') {
        $ordersCollection->updateOne(
            ['_id' => $objId, 'farmer' => $username],
            ['$set' => ['status' => 'Rejected']]
        );
    } elseif ($action === 'complete') {
        $ordersCollection->updateOne(
            ['_id' => $objId, 'farmer' => $username],
            ['$set' => ['status' => 'Completed']]
        );
    }

    header("Location: order_management.php");
    exit();
}

// ---------------- Fetch Orders ----------------
$ordersCursor = $ordersCollection->find(['farmer' => $username], ['sort' => ['_id' => -1]]);
$orders = iterator_to_array($ordersCursor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Management</title>
<style>
body {
    font-family:'Segoe UI',sans-serif;
    background:#f0f4f8;
    margin:0;
    color:#333;
}
.container {
    width: 95%;
    max-width: 1300px;
    margin: 30px auto;
    padding: 25px;
    background:#fff;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,0.08);
}
/* 🌿 Navigation Bar */
.navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #3e8e41;
  color: white;
  padding: 15px 30px;
  box-shadow: 0 3px 6px rgba(0,0,0,0.2);
  position: sticky;
  top: 0;
  z-index: 10;
}

.nav-left h2 {
  margin: 0;
  font-size: 22px;
  letter-spacing: 0.5px;
}

.nav-right a {
  color: white;
  text-decoration: none;
  margin: 0 12px;
  font-weight: 500;
  transition: color 0.3s;
}

.nav-right a:hover {
  color: #d9ffd8;
}

.logout-btn {
  background-color: #ff4d4d;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: bold;
}

.logout-btn:hover {
  background-color: #e60000;
}
h1 { text-align:center; margin-bottom:30px; color:#2c3e50; }
table {
    width:100%;
    border-collapse:collapse;
}
thead th {
    background:linear-gradient(90deg,#6a11cb,#2575fc);
    color:#fff;
    padding:12px;
    text-align:left;
}
tbody td {
    padding:10px;
    border-bottom:1px solid #e1e1e1;
    vertical-align:middle;
}
tbody tr:hover {
    background:#f0f8ff;
}
/* Status badges */
.status {
    padding:4px 8px;
    border-radius:6px;
    font-weight:600;
    color:#fff;
    font-size:13px;
}
.status.Pending { background:#f39c12; }
.status.Accepted { background:#27ae60; }
.status.Rejected { background:#c0392b; }
.status.Completed { background:#2980b9; }
/* Action buttons */
.actions a {
    display:inline-block;
    margin:2px;
    padding:5px 10px;
    border-radius:6px;
    color:#fff;
    text-decoration:none;
    font-size:13px;
    transition:0.3s;
}
.actions .accept { background:#27ae60; }
.actions .accept:hover { background:#219150; }
.actions .reject { background:#c0392b; }
.actions .reject:hover { background:#962d22; }
.actions .counter { background:#f39c12; }
.actions .counter:hover { background:#d68910; }
.actions .contact { background:#1abc9c; }
.actions .contact:hover { background:#149a87; }
.actions .schedule { background:#3498db; }
.actions .schedule:hover { background:#2980b9; }
.actions .rate { background:#8e44ad; }
.actions .rate:hover { background:#71368a; }
/* Responsive */
@media(max-width:768px) {
    table thead th, table tbody td { font-size:12px; padding:6px; }
    .actions a { font-size:11px; padding:4px 6px; }
}
</style>
</head>
<body>
    <!-- 🌿 Navigation Bar -->
<nav class="navbar">
    <div class="nav-left">
        <h2>🌾 DMAS Farmer Panel</h2>
    </div>
    <div class="nav-right">
        <a href="farmer_dashboard.php">Dashboard</a>
        <a href="add_product.php">Products</a>
        <a href="farmer_orders.php">Orders</a>
         <a href="price_intelligence.php">Price Intelligence</a>
        <a href="product_analytics.php">Product Analytics</a>
        <a href="order_history.php">Order History</a>
        <a href="reports.php">Reports</a>
        <a href="profile.php">Profile</a>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
<div class="container">
<h1>Order Management</h1>

<table>
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Wholesaler</th>
            <th>Product</th>
            <th>Quantity</th>
            <th>Offered Price</th>
            <th>Order Date</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if(count($orders) > 0): ?>
        <?php foreach($orders as $order):
            $product = $productsCollection->findOne(['_id' => $order['product_id']]) ?? [];
            $wholesaler = $usersCollection->findOne(['username' => $order['wholesaler']]) ?? [];
            $status = $order['status'] ?? 'Pending';
            $orderDate = isset($order['date']) ? date('d M Y, H:i', strtotime($order['date'])) : '-';
        ?>
        <tr>
            <td><?= $order['_id'] ?></td>
            <td><?= htmlspecialchars($wholesaler['username'] ?? '-') ?></td>
            <td><?= htmlspecialchars($product['name'] ?? '-') ?></td>
            <td><?= $order['quantity'] ?? '-' ?></td>
            <td><?= $order['total'] ?? 0 ?> LKR</td>
            <td><?= $orderDate ?></td>
            <td><span class="status <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span></td>
            <td class="actions">
                <?php if($status === 'Pending'): ?>
                    <a href="?action=accept&id=<?= $order['_id'] ?>" class="accept">Accept</a>
                    <a href="?action=reject&id=<?= $order['_id'] ?>" class="reject">Reject</a>
                    <a href="#" class="counter">Counter Offer</a>
                <?php elseif($status === 'Accepted'): ?>
                    <?php if(!empty($wholesaler['contact'])): 
                        $wa = preg_replace('/[^0-9]/','',$wholesaler['contact']);
                        $waLink = "https://wa.me/$wa";
                    ?>
                        <a href="<?= $waLink ?>" target="_blank" class="contact">Contact</a>
                    <?php endif; ?>
                    <a href="#" class="schedule">Schedule Pickup</a>
                <?php elseif($status === 'Completed'): ?>
                    <a href="#" class="rate">Rate Wholesaler</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="8" style="text-align:center;">No orders found.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
</body>
</html>
