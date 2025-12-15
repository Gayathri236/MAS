<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Colombo');

require __DIR__ . '/../mongodb_config.php';

$usersCollection = $db->users;
$ordersCollection = $db->orders;

// ------------------------- ONLY TRANSPORTERS -------------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'transporter') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ------------------------- PROFILE -------------------------
$transporterProfile = $usersCollection->findOne(['username' => $username, 'role' => 'transporter']);
if (!$transporterProfile) {
    $transporterProfile = [
        'username' => $username,
        'name'     => $username,
        'phone'    => 'N/A',
        'location' => 'N/A',
        'photo'    => 'default_profile.png'
    ];
}
$displayUsername = htmlspecialchars($transporterProfile['username'] ?? $username);
$displayName     = htmlspecialchars($transporterProfile['name'] ?? 'N/A');
$displayPhone    = htmlspecialchars($transporterProfile['phone'] ?? 'N/A');
$displayAddress  = htmlspecialchars($transporterProfile['location'] ?? ($transporterProfile['location'] ?? 'N/A'));
$displayImage = htmlspecialchars($transporterProfile['image'] ?? 'default.png');

// ------------------------- FETCH ORDERS -------------------------
$orders = iterator_to_array($ordersCollection->find(
    ['transporter' => $username]
));

// ------------------------- STATS -------------------------
$totalOrders = count($orders);
$pending     = count(array_filter($orders, fn($o) => $o['status'] === 'accepted'));
$delivered   = count(array_filter($orders, fn($o) => $o['status'] === 'delivered'));
$inProgress  = count(array_filter($orders, fn($o) => $o['status'] === 'in_progress'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transporter Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard.css">
</head>
<body>

<div class="sidebar">
    <div class="profile-section">
        <img src="../uploads/<?= $displayImage ?>" alt="Profile" class="profile-img" onerror="this.src='../uploads/default.png'; this.onerror=null;">
        <h3><?= $displayName ?></h3>
        <p><?= $username ?></p>
        <p><i class="fas fa-truck"></i> Transporter</p>
        <p><i class="fas fa-map-marker-alt"></i> <?= $displayAddress ?></p>
    </div>
    
    <div class="nav-links">
        <a href="transporter_dashboard.php" class="active">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="transporter_time_slot.php">
            <i class="fas fa-clock"></i>
            <span>Time Slots</span>
        </a>
        <a href="vehicle_order_delivered.php">
            <i class="fas fa-check-circle"></i>
            <span>Delivered Orders</span>
        </a>
        <a href="profile.php">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </div>
    
    <a href="../logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>

<div class="main">
    <h1>🚚 Transporter Dashboard</h1>
    
    <div class="dashboard-container">
        <!-- Stats Cards Only -->
        <div class="stats-cards">
            <a href="#" class="stat-card">
                <i class="fas fa-boxes"></i>
                <div class="stat-number"><?= $totalOrders ?></div>
                <div class="stat-label">Total Orders</div>
            </a>
            
            <a href="transporter_time_slot.php?status=accepted" class="stat-card">
                <i class="fas fa-hourglass-half"></i>
                <div class="stat-number"><?= $pending ?></div>
                <div class="stat-label">Pending Orders</div>
            </a>
            
            <a href="#" class="stat-card">
                <i class="fas fa-truck-moving"></i>
                <div class="stat-number"><?= $inProgress ?></div>
                <div class="stat-label">In Progress</div>
            </a>
            
            <a href="vehicle_order_delivered.php?status=delivered" class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div class="stat-number"><?= $delivered ?></div>
                <div class="stat-label">Delivered Orders</div>
            </a>
        </div>
    </div>
</div>
 <script src="assets/dashboard.js"></script>
</body>
</html>