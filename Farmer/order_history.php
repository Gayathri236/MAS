<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];
$ordersCollection = $db->orders;
$productsCollection = $db->products;
$usersCollection = $db->users;

// ---------------- Filters ----------------
$wholesalerFilter = $_GET['wholesaler'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

$filter = ['farmer' => $username, 'status' => 'Completed'];

if ($wholesalerFilter !== '') $filter['wholesaler'] = $wholesalerFilter;
if ($categoryFilter !== '') $filter['product_category'] = $categoryFilter;
if ($dateFrom !== '' || $dateTo !== '') {
    $dateFilter = [];
    if ($dateFrom !== '') $dateFilter['$gte'] = new MongoDB\BSON\UTCDateTime(strtotime($dateFrom)*1000);
    if ($dateTo !== '') $dateFilter['$lte'] = new MongoDB\BSON\UTCDateTime(strtotime($dateTo)*1000);
    $filter['completion_date'] = $dateFilter;
}

$ordersCursor = $ordersCollection->find($filter, ['sort'=>['_id'=>-1]]);
$orders = iterator_to_array($ordersCursor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order History</title>
<style>
body {
    font-family:'Segoe UI',sans-serif;
    background:#f0f4f8;
    margin:0;
    color:#333;
}
.container {
    width:95%;
    max-width:1300px;
    margin:30px auto;
    padding:25px;
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
h1 { text-align:center; margin-bottom:25px; color:#2c3e50; }
.filter-container {
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-bottom:20px;
}
.filter-container input, .filter-container select {
    padding:8px 12px;
    border:1px solid #ccc;
    border-radius:6px;
}
.filter-container button {
    padding:8px 16px;
    border:none;
    background:#27ae60;
    color:#fff;
    border-radius:6px;
    cursor:pointer;
}
.filter-container button:hover { background:#219150; }
table { width:100%; border-collapse:collapse; }
thead th {
    background:linear-gradient(90deg,#6a11cb,#2575fc);
    color:#fff;
    padding:12px; text-align:left;
}
tbody td { padding:10px; border-bottom:1px solid #e1e1e1; vertical-align:middle; }
tbody tr:hover { background:#f0f8ff; }
.actions a {
    display:inline-block;
    padding:4px 8px;
    margin:2px;
    background:#3498db;
    color:#fff;
    border-radius:6px;
    text-decoration:none;
    font-size:13px;
}
.actions a:hover { background:#2980b9; }
@media(max-width:768px){
    .filter-container { flex-direction:column; }
    table td, table th { font-size:12px; padding:6px; }
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
<h1>Completed Orders History</h1>

<!-- Filters -->
<form method="GET" class="filter-container">
    <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" placeholder="From">
    <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" placeholder="To">
    <input type="text" name="wholesaler" value="<?= htmlspecialchars($wholesalerFilter) ?>" placeholder="Wholesaler Name">
    <select name="category">
        <option value="">All Categories</option>
        <option value="Vegetables" <?= ($categoryFilter=='Vegetables')?'selected':'' ?>>Vegetables</option>
        <option value="Fruits" <?= ($categoryFilter=='Fruits')?'selected':'' ?>>Fruits</option>
        <option value="Grains" <?= ($categoryFilter=='Grains')?'selected':'' ?>>Grains</option>
        <option value="Other" <?= ($categoryFilter=='Other')?'selected':'' ?>>Other</option>
    </select>
    <button type="submit">Filter</button>
    <a href="export_orders.php" class="actions" style="background:#27ae60;">Export PDF/Excel</a>
</form>

<!-- Orders Table -->
<table>
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Wholesaler</th>
            <th>Product</th>
            <th>Quantity</th>
            <th>Final Price</th>
            <th>Completion Date</th>
            <th>Rating</th>
        </tr>
    </thead>
    <tbody>
    <?php if(count($orders)>0): ?>
        <?php foreach($orders as $order):
            $product = $productsCollection->findOne(['_id'=>$order['product_id']]) ?? [];
            $wholesaler = $usersCollection->findOne(['username'=>$order['wholesaler']]) ?? [];
            $completionDate = isset($order['completion_date']) ? date('d M Y', strtotime($order['completion_date'])) : '-';
            $rating = $order['rating'] ?? '-';
        ?>
        <tr>
            <td><?= $order['_id'] ?></td>
            <td><?= htmlspecialchars($wholesaler['username'] ?? '-') ?></td>
            <td><?= htmlspecialchars($product['name'] ?? '-') ?></td>
            <td><?= $order['quantity'] ?? '-' ?></td>
            <td><?= $order['total'] ?? 0 ?> LKR</td>
            <td><?= $completionDate ?></td>
            <td><?= htmlspecialchars($rating) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="7" style="text-align:center;">No completed orders found.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
</body>
</html>
