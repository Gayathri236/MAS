<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];
$productsCollection = $db->products;
$marketCollection = $db->market_prices;

// Fetch all farmer products
$productsCursor = $productsCollection->find(['farmer' => $username]);
$products = iterator_to_array($productsCursor);

// Fetch latest market prices
$marketPrices = [];
foreach($products as $product) {
    $market = $marketCollection->findOne(['product_name' => $product['name']]);
    $marketPrices[$product['name']] = $market['avg_price'] ?? 0;
}

// Example AI Predictions (this could be replaced by actual AI module)
$aiPredictions = [];
foreach($products as $product) {
    $currentPrice = $marketPrices[$product['name']] ?? 0;
    $aiPredictions[$product['name']] = [
        'next_7_days' => $currentPrice * (1 + rand(-5,5)/100), // +/-5% random
        'seasonal_trend' => $currentPrice * (1 + rand(-10,10)/100)
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Price Intelligence Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    font-family:'Segoe UI',sans-serif;
    background:#f0f4f9;
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
h1 { text-align:center; margin-bottom:30px; color:#2c3e50; }

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

/* Product Price Table */
table {
    width:100%;
    border-collapse:collapse;
    margin-bottom:30px;
}
thead th {
    background:#6a11cb;
    color:#fff;
    padding:12px;
    text-align:left;
}
tbody td {
    padding:10px;
    border-bottom:1px solid #e1e1e1;
}
tbody tr:hover { background:#f0f8ff; }
.price-current { color:#27ae60; font-weight:bold; }
.price-market { color:#2980b9; font-weight:bold; }
.price-ai { color:#8e44ad; font-weight:bold; }

/* Chart Containers */
.chart-box {
    margin:30px 0;
    padding:20px;
    border-radius:12px;
    background:#f9f9f9;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

/* Alerts */
.alert {
    padding:12px 20px;
    border-radius:8px;
    margin-bottom:15px;
    color:#fff;
}
.alert.up { background:#27ae60; }
.alert.down { background:#e74c3c; }

/* Responsive */
@media(max-width:768px){
    table td, table th { font-size:13px; padding:8px; }
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
<h1>Price Intelligence Dashboard</h1>

<!-- Price Table -->
<table>
    <thead>
        <tr>
            <th>Product</th>
            <th>Your Price</th>
            <th>Market Avg Price</th>
            <th>AI Prediction (Next 7 days)</th>
            <th>Seasonal Trend</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($products as $product): 
        $marketPrice = $marketPrices[$product['name']] ?? 0;
        $aiNext7 = $aiPredictions[$product['name']]['next_7_days'] ?? 0;
        $aiSeasonal = $aiPredictions[$product['name']]['seasonal_trend'] ?? 0;
    ?>
        <tr>
            <td><?= htmlspecialchars($product['name']) ?></td>
            <td class="price-current"><?= htmlspecialchars($product['price']) ?> LKR</td>
            <td class="price-market"><?= $marketPrice ?> LKR</td>
            <td class="price-ai"><?= round($aiNext7,2) ?> LKR</td>
            <td class="price-ai"><?= round($aiSeasonal,2) ?> LKR</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Price Trend Chart -->
<div class="chart-box">
    <canvas id="priceTrendChart"></canvas>
</div>

<!-- Example Alerts -->
<div class="alert up">📈 Market price increased for Tomatoes by 5%</div>
<div class="alert down">📉 Market price decreased for Carrots by 3%</div>

</div>

<script>
const ctx = document.getElementById('priceTrendChart').getContext('2d');
const labels = [<?php foreach($products as $product){ echo "'".$product['name']."',"; } ?>];
const data = {
    labels: labels,
    datasets: [
        {
            label:'Your Price',
            data:[<?php foreach($products as $p){ echo $p['price'].','; } ?>],
            borderColor:'#27ae60',
            backgroundColor:'rgba(39,174,96,0.2)',
            tension:0.3
        },
        {
            label:'Market Avg Price',
            data:[<?php foreach($products as $p){ echo $marketPrices[$p['name']].','; } ?>],
            borderColor:'#2980b9',
            backgroundColor:'rgba(41,128,185,0.2)',
            tension:0.3
        },
        {
            label:'AI Predicted Price',
            data:[<?php foreach($products as $p){ echo round($aiPredictions[$p['name']]['next_7_days'],2).','; } ?>],
            borderColor:'#8e44ad',
            backgroundColor:'rgba(142,68,173,0.2)',
            tension:0.3
        }
    ]
};
const config = { type:'line', data:data, options:{ responsive:true, plugins:{ legend:{ position:'top' } } } };
new Chart(ctx, config);
</script>

</body>
</html>
