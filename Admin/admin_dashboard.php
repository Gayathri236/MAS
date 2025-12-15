<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$usersCollection = $db->users;
$productsCollection = $db->products;
$ordersCollection = $db->orders;
$vehiclesCollection = $db->vehicles;

// Basic counts
$totalFarmers = $usersCollection->countDocuments(['role' => 'farmer']);
$totalWholesalers = $usersCollection->countDocuments(['role' => 'wholesaler']);
$totalDrivers = $usersCollection->countDocuments(['role' => 'transporter']);
$totalProducts = $productsCollection->countDocuments([]);

// Orders today
$todayStart = strtotime('today 00:00:00') * 1000;
$todayEnd = strtotime('today 23:59:59') * 1000;

$ordersToday = $ordersCollection->countDocuments([
    'order_date' => [
        '$gte' => new MongoDB\BSON\UTCDateTime($todayStart),
        '$lte' => new MongoDB\BSON\UTCDateTime($todayEnd)
    ]
]);

// Recent users and orders - CONVERT TO ARRAYS IMMEDIATELY
$recentUsersCursor = $usersCollection->find(
    ['role' => ['$in' => ['farmer', 'wholesaler', 'transporter']]],
    ['sort' => ['_id' => -1], 'limit' => 5]
);
$recentUsers = iterator_to_array($recentUsersCursor);

$recentOrdersCursor = $ordersCollection->find([], ['sort' => ['_id' => -1], 'limit' => 5]);
$recentOrders = iterator_to_array($recentOrdersCursor);

// User registrations by month for current year
$currentYear = date('Y');
$userMonths = array_fill(1, 12, 0);

$yearStart = strtotime("$currentYear-01-01 00:00:00") * 1000;
$yearEnd = strtotime(($currentYear + 1) . "-01-01 00:00:00") * 1000;

// Get users created this year and convert to array
$usersThisYearCursor = $usersCollection->find([
    'created_at' => [
        '$gte' => new MongoDB\BSON\UTCDateTime($yearStart),
        '$lt' => new MongoDB\BSON\UTCDateTime($yearEnd)
    ]
]);
$usersThisYear = iterator_to_array($usersThisYearCursor);

foreach ($usersThisYear as $user) {
    if (isset($user['created_at'])) {
        if ($user['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $date = $user['created_at']->toDateTime();
            $month = (int)$date->format('n');
            $userMonths[$month]++;
        }
    }
}

// Orders per week for current month
$currentMonthStart = strtotime('first day of this month 00:00:00') * 1000;
$currentMonthEnd = strtotime('last day of this month 23:59:59') * 1000;

$orderWeeks = array_fill(1, 4, 0);

$ordersThisMonthCursor = $ordersCollection->find([
    'order_date' => [
        '$gte' => new MongoDB\BSON\UTCDateTime($currentMonthStart),
        '$lte' => new MongoDB\BSON\UTCDateTime($currentMonthEnd)
    ]
]);
$ordersThisMonth = iterator_to_array($ordersThisMonthCursor);

foreach ($ordersThisMonth as $order) {
    if (isset($order['order_date']) && $order['order_date'] instanceof MongoDB\BSON\UTCDateTime) {
        $date = $order['order_date']->toDateTime();
        $dayOfMonth = (int)$date->format('j');
        $week = ceil($dayOfMonth / 7);
        if ($week >= 1 && $week <= 4) {
            $orderWeeks[$week]++;
        }
    }
}

// Product category distribution
$categories = ['Fruits', 'Vegetables', 'Dairy', 'Grains', 'Other'];
$productCategoryData = [];

// Get all products and convert to array
$allProductsCursor = $productsCollection->find();
$allProducts = iterator_to_array($allProductsCursor);
$categoryCounts = [];

foreach ($allProducts as $product) {
    $category = $product['category'] ?? 'Other';
    if (!isset($categoryCounts[$category])) {
        $categoryCounts[$category] = 0;
    }
    $categoryCounts[$category]++;
}

// Ensure all categories have a value, even if 0
foreach ($categories as $cat) {
    $productCategoryData[$cat] = $categoryCounts[$cat] ?? 0;
}

// Prepare chart data
$chartData = [
    'userMonths' => array_values($userMonths),
    'orderWeeks' => array_values($orderWeeks),
    'productCategories' => array_values($productCategoryData),
    'categoryLabels' => $categories
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - DMAS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --secondary: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --dark: #1f2937;
    --light: #f9fafb;
    --gray: #6b7280;
    --border: #e5e7eb;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius: 12px;
    --radius-sm: 8px;
    --transition: all 0.3s ease;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
    min-height: 100vh;
    color: var(--dark);
}

/* Modern Navbar */
.navbar {
     background: linear-gradient(135deg, #141e30, #243b55); 
     display: flex; 
     justify-content: space-between; 
     align-items: center; 
     padding: 16px 50px; 
     box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}
.nav-left h2 {
     color: #fff; 
     font-size: 22px; 
     margin: 0; 
    }
.nav-right a { 
    color: #eaeaea; 
    text-decoration: none; 
    font-weight: 500; 
    padding: 8px 14px; 
    border-radius: 25px; 
    transition: 0.3s;
}
.nav-right a.active { 
    background: #27ae60; 
    color: #fff; 
}
.nav-right a.logout { 
    background: #e74c3c; 
    color: #fff; 
}
.nav-right a:hover { 
    background: rgba(255, 255, 255, 0.15); }

/* Main Container */
.container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 0 25px;
}

/* Header */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header h1 {
    font-size: 32px;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.header-info {
    display: flex;
    gap: 15px;
    align-items: center;
}

.date-badge {
    background: white;
    padding: 8px 16px;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    font-weight: 500;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 25px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.stat-card:nth-child(1)::before { background: linear-gradient(90deg, #10b981, #34d399); }
.stat-card:nth-child(2)::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.stat-card:nth-child(3)::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.stat-card:nth-child(4)::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
.stat-card:nth-child(5)::before { background: linear-gradient(90deg, #ef4444, #f87171); }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: white;
}

.stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #10b981, #34d399); }
.stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
.stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }
.stat-card:nth-child(5) .stat-icon { background: linear-gradient(135deg, #ef4444, #f87171); }

.stat-title {
    font-size: 14px;
    color: var(--gray);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.stat-change {
    font-size: 14px;
    color: var(--secondary);
    font-weight: 500;
}

/* Charts Section */
.section-title {
    font-size: 22px;
    font-weight: 600;
    color: var(--dark);
    margin: 40px 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: var(--primary);
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.chart-container {
    background: white;
    border-radius: var(--radius);
    padding: 25px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    transition: var(--transition);
}

.chart-container:hover {
    box-shadow: var(--shadow-lg);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
}

.chart-actions {
    display: flex;
    gap: 10px;
}

.chart-btn {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 12px;
    color: var(--gray);
    cursor: pointer;
    transition: var(--transition);
}

.chart-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.chart-wrapper {
    position: relative;
    height: 280px;
    width: 100%;
}

/* Activity Grid */
.activity-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.activity-card {
    background: white;
    border-radius: var(--radius);
    padding: 25px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.activity-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.activity-title i {
    color: var(--primary);
}

.badge {
    background: var(--primary-light);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.activity-list {
    list-style: none;
}

.activity-item {
    padding: 15px 0;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: var(--transition);
}

.activity-item:hover {
    background: var(--light);
    padding-left: 10px;
    border-radius: var(--radius-sm);
}

.activity-item:last-child {
    border-bottom: none;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}

.activity-content {
    flex: 1;
}

.activity-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 4px;
}

.activity-desc {
    font-size: 14px;
    color: var(--gray);
}

.activity-time {
    font-size: 12px;
    color: var(--gray);
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-pending { background: #fef3c7; color: #d97706; }
.status-accepted { background: #d1fae5; color: #065f46; }
.status-rejected { background: #fee2e2; color: #dc2626; }


/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.6s ease-out;
}

/* Responsive */
@media (max-width: 1024px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .activity-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .navbar {
        padding: 0 20px;
    }
    
    .nav-right {
        display: none;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .container {
        padding: 0 15px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}
</style>

</head>
<body>

<nav class="navbar">
  <div class="nav-left"><h2>🌾 DMAS Admin Panel</h2></div>
  <div class="nav-right">
    <a href="admin_dashboard.php"class="active">🏠 Dashboard</a>
    <a href="user_management.php">👥 Manage Users</a>
    <a href="order_management.php" >📦 View Orders</a>
    <a href="admin_report.php">📄 Report</a>
    <a href="worker_registration.php">📝 Worker Registration</a>
    <a href="../logout.php" class="logout">🚪 Logout</a>
  </div>
</nav>

<div class="container">
    <!-- Header -->
    <div class="header fade-in">
        <h1><i class="fas fa-chart-line"></i> Dashboard Overview</h1>
        <div class="header-info">
            <div class="date-badge">
                <i class="fas fa-calendar-alt"></i>
                <?= date('F j, Y') ?>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card fade-in" style="animation-delay: 0.1s">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Farmers</div>
                    <div class="stat-value"><?= $totalFarmers ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-seedling"></i>
                </div>
            </div>
            <div class="stat-change">Active users</div>
        </div>

        <div class="stat-card fade-in" style="animation-delay: 0.2s">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Wholesalers</div>
                    <div class="stat-value"><?= $totalWholesalers ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-store"></i>
                </div>
            </div>
            <div class="stat-change">Business accounts</div>
        </div>

        <div class="stat-card fade-in" style="animation-delay: 0.3s">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Drivers</div>
                    <div class="stat-value"><?= $totalDrivers ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-truck"></i>
                </div>
            </div>
            <div class="stat-change">Transporters</div>
        </div>

        <div class="stat-card fade-in" style="animation-delay: 0.4s">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Products</div>
                    <div class="stat-value"><?= $totalProducts ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-box-open"></i>
                </div>
            </div>
            <div class="stat-change">Available items</div>
        </div>

        <div class="stat-card fade-in" style="animation-delay: 0.5s">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Today's Orders</div>
                    <div class="stat-value"><?= $ordersToday ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
            </div>
            <div class="stat-change">Placed today</div>
        </div>
    </div>

    <!-- Charts Section -->
    <h2 class="section-title"><i class="fas fa-chart-pie"></i> Analytics & Metrics</h2>
    
    <div class="charts-grid">
        <div class="chart-container fade-in">
            <div class="chart-header">
                <div class="chart-title">User Registrations (<?= date('Y') ?>)</div>
                <div class="chart-actions">
                    <button class="chart-btn">Monthly</button>
                </div>
            </div>
            <div class="chart-wrapper">
                <canvas id="userChart"></canvas>
            </div>
        </div>

        <div class="chart-container fade-in">
            <div class="chart-header">
                <div class="chart-title">Orders This Month</div>
                <div class="chart-actions">
                    <button class="chart-btn">Weekly</button>
                </div>
            </div>
            <div class="chart-wrapper">
                <canvas id="orderChart"></canvas>
            </div>
        </div>

        <div class="chart-container fade-in">
            <div class="chart-header">
                <div class="chart-title">Product Categories</div>
                <div class="chart-actions">
                    <button class="chart-btn">Distribution</button>
                </div>
            </div>
            <div class="chart-wrapper">
                <canvas id="productChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Activity Section -->
    <h2 class="section-title"><i class="fas fa-history"></i> Recent Activity</h2>
    
    <div class="activity-grid">
        <div class="activity-card fade-in">
            <div class="activity-header">
                <div class="activity-title"><i class="fas fa-user-plus"></i> New Registrations</div>
                <span class="badge"><?= count($recentUsers) ?> new</span>
            </div>
            <ul class="activity-list">
                <?php if (count($recentUsers) > 0): ?>
                    <?php foreach ($recentUsers as $u): ?>
                        <li class="activity-item">
                            <div class="user-avatar">
                                <?= strtoupper(substr($u['name'] ?? $u['username'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-name"><?= htmlspecialchars($u['name'] ?? $u['username'] ?? 'Unknown') ?></div>
                                <div class="activity-desc">New <?= htmlspecialchars($u['role'] ?? 'user') ?> registered</div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="activity-item">
                        <div class="activity-content">
                            <div class="activity-name">No recent registrations</div>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="activity-card fade-in">
            <div class="activity-header">
                <div class="activity-title"><i class="fas fa-shopping-cart"></i> Recent Orders</div>
                <span class="badge"><?= count($recentOrders) ?> orders</span>
            </div>
            <ul class="activity-list">
                <?php if (count($recentOrders) > 0): ?>
                    <?php foreach ($recentOrders as $o): ?>
                        <?php 
                        $orderId = $o['_id'] instanceof MongoDB\BSON\ObjectId ? (string)$o['_id'] : $o['_id'];
                        $status = $o['status'] ?? 'pending';
                        $statusClass = "status-" . $status;
                        ?>
                        <li class="activity-item">
                            <div class="user-avatar" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-name">Order #<?= substr($orderId, -6) ?></div>
                                <div class="activity-desc">
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= htmlspecialchars(ucfirst($status)) ?>
                                    </span>
                                </div>
                                <div class="activity-time">Recently placed</div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="activity-item">
                        <div class="activity-content">
                            <div class="activity-name">No recent orders</div>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="activity-card fade-in">
            <div class="activity-header">
                <div class="activity-title"><i class="fas fa-chart-line"></i> System Status</div>
                <span class="badge">Live</span>
            </div>
            <ul class="activity-list">
                <li class="activity-item">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #10b981, #34d399);">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-name">System Online</div>
                        <div class="activity-desc">All services operational</div>
                    </div>
                </li>
                <li class="activity-item">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-name"><?= $ordersToday ?> Orders Today</div>
                        <div class="activity-desc">Active transactions</div>
                    </div>
                </li>
                <li class="activity-item">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-name"><?= $totalFarmers + $totalWholesalers + $totalDrivers ?> Total Users</div>
                        <div class="activity-desc">Active in system</div>
                    </div>
                </li>
            </ul>
        </div>
    </div>

</div>

<script>
const chartData = <?= json_encode($chartData) ?>;

// User Registrations Chart
new Chart(document.getElementById('userChart'), {
    type: 'line',
    data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets: [{
            label: 'Registrations',
            data: chartData.userMonths,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#6366f1',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.7)',
                padding: 12,
                cornerRadius: 6
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    stepSize: 1
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Orders per Week Chart
new Chart(document.getElementById('orderChart'), {
    type: 'bar',
    data: {
        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
        datasets: [{
            label: 'Orders',
            data: chartData.orderWeeks,
            backgroundColor: [
                'rgba(16, 185, 129, 0.8)',
                'rgba(16, 185, 129, 0.6)',
                'rgba(16, 185, 129, 0.4)',
                'rgba(16, 185, 129, 0.2)'
            ],
            borderColor: '#10b981',
            borderWidth: 1,
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    stepSize: 1
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Product Categories Chart
new Chart(document.getElementById('productChart'), {
    type: 'doughnut',
    data: {
        labels: chartData.categoryLabels,
        datasets: [{
            data: chartData.productCategories,
            backgroundColor: [
                '#6366f1',
                '#10b981',
                '#f59e0b',
                '#8b5cf6',
                '#6b7280'
            ],
            borderWidth: 2,
            borderColor: '#fff',
            hoverOffset: 15
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: {
                        size: 12
                    }
                }
            }
        },
        cutout: '65%'
    }
});

// Add hover animations
document.querySelectorAll('.stat-card, .chart-container, .activity-card').forEach(card => {
    card.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-5px)';
    });
    
    card.addEventListener('mouseleave', () => {
        card.style.transform = 'translateY(0)';
    });
});
</script>

</body>
</html>