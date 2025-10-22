<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Allow only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// MongoDB collections
$usersCollection = $db->users;
$productsCollection = $db->products;
$ordersCollection = $db->orders;
$vehiclesCollection = $db->vehicles;

// ---------- SYSTEM OVERVIEW COUNTS ----------
$totalFarmers = $usersCollection->countDocuments(['role' => 'farmer']);
$totalWholesalers = $usersCollection->countDocuments(['role' => 'wholesaler']);
$totalDrivers = $usersCollection->countDocuments(['role' => 'transporter']);
$totalProducts = $productsCollection->countDocuments([]);
$ordersToday = $ordersCollection->countDocuments(['date' => date('Y-m-d')]);
$pendingSlots = $vehiclesCollection->countDocuments(['time_slot' => ['$exists' => false]]);

// ---------- RECENT ACTIVITY ----------
$recentUsers = $usersCollection->find(
    ['role' => ['$in' => ['farmer', 'wholesaler', 'transporter']]],
    ['sort' => ['_id' => -1], 'limit' => 5]
);

$recentOrders = $ordersCollection->find([], ['sort' => ['_id' => -1], 'limit' => 5]);

// ---------- CHART DATA ----------
$userMonths = array_fill(1, 12, 0);
foreach ($usersCollection->find(['role' => ['$in' => ['farmer', 'wholesaler', 'transporter']]]) as $u) {
    $createdAt = isset($u['created_at']) ? strtotime($u['created_at']) : time();
    $month = (int)date('n', $createdAt);
    $userMonths[$month]++;
}


$orderWeeks = array_fill(1, 4, 0);
foreach ($ordersCollection->find([]) as $o) {
    if (isset($o['date'])) {
        $day = (int)date('j', strtotime($o['date']));
        $week = ceil($day / 7);
        if ($week >= 1 && $week <= 4) {
            $orderWeeks[$week]++;
        }
    }
}

$categories = ['Fruits', 'Vegetables', 'Dairy Products', 'Baked Goods', 'Handmade Crafts'];
$productCategoryData = [];
foreach ($categories as $cat) {
    $productCategoryData[$cat] = $productsCollection->countDocuments(['category' => $cat]);
}

$chartData = [
    'userMonths' => array_values($userMonths),
    'orderWeeks' => array_values($orderWeeks),
    'productCategories' => array_values($productCategoryData)
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - DMAS</title>
<link rel="stylesheet" href="admin_dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<nav class="navbar">
  <div class="nav-left">
    <h2>🌾 DMAS Admin Panel</h2>
  </div>
  <div class="nav-right">
    <a href="admin_dashboard.php" class="active">🏠 Dashboard</a>
    <a href="user_management.php">👥 Manage Users</a>
    <a href="">📦  Manage Orders</a>
    <a href="">🚚 Time Slot</a>
    <a href="worker_registration.php">📝 Worker Registration</a>
    <a href="../logout.php" class="logout">🚪 Logout</a>
  </div>
</nav>


<div class="container">
    <h3>System Overview</h3>
    <div class="overview">
        <div class="card"><h4>Farmers</h4><p><?= $totalFarmers ?></p></div>
        <div class="card"><h4>Wholesalers</h4><p><?= $totalWholesalers ?></p></div>
        <div class="card"><h4>Drivers</h4><p><?= $totalDrivers ?></p></div>
        <div class="card"><h4>Products</h4><p><?= $totalProducts ?></p></div>
        <div class="card"><h4>Orders Today</h4><p><?= $ordersToday ?></p></div>
        <div class="card"><h4>Pending Slots</h4><p><?= $pendingSlots ?></p></div>
    </div>

    <h3>Quick Statistics</h3>
    <div class="charts">
        <div class="chart-box">
            <h4>User Registrations (Monthly)</h4>
            <canvas id="userChart"></canvas>
        </div>
        <div class="chart-box">
            <h4>Orders per Week</h4>
            <canvas id="orderChart"></canvas>
        </div>
        <div class="chart-box">
            <h4>Product Category Distribution</h4>
            <canvas id="productChart"></canvas>
        </div>
    </div>

    <h3>Recent Activity Feed</h3>
    <div class="activity">
        <div class="activity-box">
            <h4>New User Registrations</h4>
            <ul>
               <?php foreach ($recentUsers as $u): ?>
                    <li><strong><?= htmlspecialchars($u['name']) ?></strong> (<?= htmlspecialchars($u['role']) ?>)</li>
               <?php endforeach; ?>
            </ul>

        </div>
        <div class="activity-box">
            <h4>Recent Orders</h4>
            <ul>
                <?php foreach ($recentOrders as $o): ?>
                    <li>Order #<?= substr($o['_id'], -5) ?> - <?= htmlspecialchars($o['status'] ?? 'Pending') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="activity-box">
            <h4>System Alerts</h4>
            <ul>
                <li>⚠ <?= $pendingSlots ?> pending time slot requests</li>
                <li>✅ <?= $ordersToday ?> orders placed today</li>
                <li>🧑‍🌾 <?= $totalFarmers ?> farmers active</li>
            </ul>
        </div>
    </div>
</div>

<script>
const chartData = <?= json_encode($chartData) ?>;

new Chart(document.getElementById('userChart'), {
    type: 'line',
    data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets: [{
            label: 'Users',
            data: chartData.userMonths,
            borderColor: '#28a745',
            tension: 0.3,
            borderWidth: 2,
            fill: false
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

new Chart(document.getElementById('orderChart'), {
    type: 'bar',
    data: {
        labels: ['Week 1','Week 2','Week 3','Week 4'],
        datasets: [{
            label: 'Orders',
            data: chartData.orderWeeks,
            backgroundColor: '#007bff'
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

new Chart(document.getElementById('productChart'), {
    type: 'pie',
    data: {
        labels: ['Fruits', 'Vegetables', 'Dairy', 'Baked Goods', 'Crafts'],
        datasets: [{
            label: 'Products',
            data: chartData.productCategories,
            backgroundColor: ['#ffc107', '#28a745', '#17a2b8', '#ff5722', '#6f42c1']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});
</script>
</body>
</html>
