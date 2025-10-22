<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Check login & role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];

// Fetch farmer profile
$farmerProfile = $usersCollection->findOne(['username' => $username, 'role' => 'farmer']);

// Stats
$totalProducts = $productsCollection->countDocuments(['farmer' => $username]);
$totalOrders = $ordersCollection->countDocuments(['farmer' => $username]);

// Calculate total sales
$salesCursor = $ordersCollection->find(['farmer' => $username]);
$totalSales = 0;
foreach ($salesCursor as $order) {
    $totalSales += $order['total_amount'] ?? 0;
}

// Prepare data for Chart.js (monthly sales)
$pipeline = [
    ['$match' => ['farmer' => $username]],
    ['$group' => [
        '_id' => ['$month' => ['$toDate' => '$order_date']],
        'total_sales' => ['$sum' => ['$toDouble' => '$total_amount']]
    ]],
    ['$sort' => ['_id' => 1]]
];
$monthlySales = $ordersCollection->aggregate($pipeline);

$months = [];
$totals = [];
foreach ($monthlySales as $m) {
    $monthNum = $m->_id;
    $monthName = date("M", mktime(0, 0, 0, $monthNum, 10));
    $months[] = $monthName;
    $totals[] = $m->total_sales;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Farmer Dashboard - DMAS</title>
    <link rel="stylesheet" href="farmer_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
         <a href="my_products.php">My Products</a>
        <a href="farmer_orders.php">Orders</a>
        <a href="">Product Analytics</a>
        <a href="">Order History</a>
        <a href="">Reports</a>
        <a href="profile.php">Profile</a>
        <a href="../logout.php" class="logout-btn">Logout</a>
        <!--<a href="price_intelligence.php">Price Intelligence</a>-->
    </div>
</nav>

<div class="container">

    <!-- Quick Stats -->
    <div class="stats">
        <div class="stat-box">
            <h3>Products Listed</h3>
            <p><?= $totalProducts ?></p>
        </div>
        <div class="stat-box">
            <h3>Orders Received</h3>
            <p><?= $totalOrders ?></p>
        </div>
        <div class="stat-box">
            <h3>Total Sales</h3>
            <p><?= number_format($totalSales, 2) ?> LKR</p>
        </div>
    </div>

    <!-- Farmer Profile -->
    <div class="profile-box">
        <h2>My Profile</h2>
        <p><strong>Username:</strong> <?= htmlspecialchars($farmerProfile['username'] ?? $username) ?></p>
        <p><strong>Full Name:</strong> <?= htmlspecialchars($farmerProfile['name'] ?? 'N/A') ?></p>
        <p><strong>Contact:</strong> <?= htmlspecialchars($farmerProfile['phone'] ?? 'N/A') ?></p>
        <p><strong>Location:</strong> <?= htmlspecialchars($farmerProfile['address'] ?? 'N/A') ?></p>
    </div>


    <!-- Sales Analysis Chart -->
    <div class="chart-box">
        <h2>📈 Monthly Sales Trend</h2>
        <canvas id="salesChart"></canvas>
    </div>
</div>

<!-- Chart Script -->
<script>
const months = <?= json_encode($months) ?>;
const totals = <?= json_encode($totals) ?>;

new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: months,
        datasets: [{
            label: 'Monthly Sales (LKR)',
            data: totals,
            borderColor: '#3e8e41',
            backgroundColor: 'rgba(62,142,65,0.2)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            title: {
                display: true,
                text: 'Sales Performance by Month'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: '#333' }
            },
            x: {
                ticks: { color: '#333' }
            }
        }
    }
});
</script>

</body>
</html>
