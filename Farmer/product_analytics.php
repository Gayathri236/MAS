<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

$username = $_SESSION['username'] ?? null;
if (!$username || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

// Collections
$productsCollection = $db->products;
$ordersCollection = $db->orders;

// Fetch all products for this farmer
$productsCursor = $productsCollection->find(['farmer' => $username]);
$products = iterator_to_array($productsCursor);

// Prepare data for analytics
$productNames = [];
$viewsData = [];
$ordersData = [];
$priceTrend = [];
$lowStockProducts = [];
$expiryWarnings = [];
$today = new DateTime();

foreach ($products as $prod) {
    $name = $prod['name'] ?? 'Unknown';
    $productNames[] = $name;
    $viewsData[] = $prod['views'] ?? 0;

    // Count orders for this product
    $orderCount = $ordersCollection->count(['product_id' => $prod['_id']]);
    $ordersData[] = $orderCount;

    // Price trend (store latest price)
    $priceTrend[] = $prod['price'] ?? 0;

    // Low stock alert
    if (($prod['quantity'] ?? 0) <= 5) { // threshold = 5
        $lowStockProducts[] = $prod;
    }

    // Expiry warning
    if (!empty($prod['expiry_date'])) {
        $expiryDate = new DateTime($prod['expiry_date']);
        $diff = $today->diff($expiryDate)->days;
        if ($expiryDate >= $today && $diff <= 7) { // warning for next 7 days
            $expiryWarnings[] = $prod;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Product Analytics</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
<style>
/* ===== General Styles ===== */
body {
    font-family: 'Segoe UI', sans-serif;
    background-color: #f0f4f8;
    margin: 0;
    padding: 0;
    color: #333;
}

h1, h2 {
    text-align: center;
    color: #2c3e50;
}

h1 { margin: 20px 0; }
h2 { margin: 40px 0 20px; }

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

/* ===== Container ===== */
.container {
    width: 95%;
    max-width: 1300px;
    margin: 30px auto;
    padding: 30px;
    background-color: #fff;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

/* ===== Chart Containers ===== */
.chart-container {
    background-color: #fafafa;
    padding: 20px;
    margin-bottom: 40px;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

/* ===== Alerts ===== */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 15px;
    font-weight: 500;
    display: flex;
    flex-direction: column;
}

.alert ul {
    margin: 8px 0 0 20px;
}

.alert.low-stock {
    background: #fff3cd;
    color: #856404;
    border-left: 6px solid #ffecb5;
}

.alert.expiry-warning {
    background: #f8d7da;
    color: #721c24;
    border-left: 6px solid #f5c6cb;
}

/* ===== Table Styles ===== */
.table-container {
    overflow-x: auto;
    margin-bottom: 30px;
}

table {
    width: 100%;
    border-collapse: collapse;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

thead {
    background: linear-gradient(90deg, #6a11cb, #2575fc);
    color: #fff;
}

thead th {
    padding: 15px;
    text-align: left;
}

tbody td {
    padding: 12px 15px;
    border-bottom: 1px solid #e1e1e1;
    vertical-align: middle;
}

tbody tr:hover {
    background-color: #f0f8ff;
}

/* ===== Status Badges ===== */
td.active {
    color: #27ae60;
    font-weight: bold;
}

td.soldout {
    color: #c0392b;
    font-weight: bold;
}

td.expired {
    color: #e67e22;
    font-weight: bold;
}

/* ===== Chart Titles ===== */
.chart-title {
    text-align: center;
    font-weight: 600;
    margin-bottom: 15px;
    color: #34495e;
}

/* ===== Responsive ===== */
@media (max-width: 1024px) {
    .chart-container { padding: 15px; }
    table thead th, table tbody td { padding: 10px; font-size: 14px; }
}

@media (max-width: 768px) {
    .container { padding: 20px; }
    h1 { font-size: 24px; }
    h2 { font-size: 20px; }
}
</style>

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
<h1>Product Analytics Dashboard</h1>

<!-- Charts -->
<div class="chart-container">
    <canvas id="viewsOrdersChart"></canvas>
</div>

<div class="chart-container">
    <canvas id="priceTrendChart"></canvas>
</div>

<!-- Low Stock Alerts -->
<?php if(count($lowStockProducts) > 0): ?>
<div class="alert">
    <strong>Low Stock Alert:</strong>
    <ul>
        <?php foreach($lowStockProducts as $prod): ?>
            <li><?= htmlspecialchars($prod['name'] ?? '-') ?> — <?= $prod['quantity'] ?? 0 ?> left</li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Expiry Date Warnings -->
<?php if(count($expiryWarnings) > 0): ?>
<div class="alert">
    <strong>Expiry Warning (next 7 days):</strong>
    <ul>
        <?php foreach($expiryWarnings as $prod): ?>
            <li><?= htmlspecialchars($prod['name'] ?? '-') ?> — Expires on <?= htmlspecialchars($prod['expiry_date']) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Best Performing Products Table -->
<h2>Best Performing Products</h2>
<div class="table-container">
<table>
    <thead>
        <tr>
            <th>Product</th>
            <th>Views</th>
            <th>Orders</th>
            <th>Price</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($products as $prod): ?>
        <tr>
            <td><?= htmlspecialchars($prod['name'] ?? '-') ?></td>
            <td><?= $prod['views'] ?? 0 ?></td>
            <td><?= $ordersCollection->count(['product_id' => $prod['_id']]) ?></td>
            <td><?= $prod['price'] ?? 0 ?> LKR</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

</div>

<script>
// Views vs Orders Chart
const ctx1 = document.getElementById('viewsOrdersChart').getContext('2d');
new Chart(ctx1, {
    type: 'bar',
    data: {
        labels: <?= json_encode($productNames) ?>,
        datasets: [
            {
                label: 'Views',
                data: <?= json_encode($viewsData) ?>,
                backgroundColor: '#3498db'
            },
            {
                label: 'Orders',
                data: <?= json_encode($ordersData) ?>,
                backgroundColor: '#27ae60'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Price Trend Chart
const ctx2 = document.getElementById('priceTrendChart').getContext('2d');
new Chart(ctx2, {
    type: 'line',
    data: {
        labels: <?= json_encode($productNames) ?>,
        datasets: [{
            label: 'Price LKR',
            data: <?= json_encode($priceTrend) ?>,
            borderColor: '#e67e22',
            backgroundColor: 'rgba(230,126,34,0.2)',
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: false }
        }
    }
});
</script>
</body>
</html>
