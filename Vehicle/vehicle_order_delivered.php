<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Colombo');

require __DIR__ . '/../mongodb_config.php';

$ordersCollection = $db->orders;
$usersCollection  = $db->users;

// ------------------------- ONLY TRANSPORTERS -------------------------
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'transporter') {
    header("Location: ../login.php");
    exit();
}

$transporter = $_SESSION['username'];

// ------------------------- FETCH TRANSPORTER PROFILE -------------------------
$transporterProfile = $usersCollection->findOne(['username' => $transporter, 'role' => 'transporter']);
if (!$transporterProfile) {
    $transporterProfile = [
        'username' => $transporter,
        'name'     => $transporter,
        'phone'    => 'N/A',
        'location' => 'N/A',
        'photo'    => 'default_profile.png'
    ];
}
$displayName = htmlspecialchars($transporterProfile['name'] ?? $transporter);
$displayImage = htmlspecialchars($transporterProfile['image'] ?? 'default.png');

// ------------------------- FETCH DELIVERED ORDERS -------------------------
try {
    $ordersCursor = $ordersCollection->find(
        [
            'transporter' => $transporter,
            'status' => 'delivered'
        ],
        ['sort' => ['market_arrival_datetime' => -1]]
    );
    $orders = iterator_to_array($ordersCursor);
} catch (Exception $e) {
    $orders = [];
}

// ------------------------- STATS -------------------------
$totalDelivered = count($orders);

// ------------------------- HELPER FUNCTIONS -------------------------
function format_datetime_sl($val){
    if (!$val) return '-';
    if ($val instanceof MongoDB\BSON\UTCDateTime){
        $dt = $val->toDateTime();
        $dt->setTimezone(new DateTimeZone('Asia/Colombo'));
        return $dt->format('d M Y, h:i A');
    }
    if (is_string($val)){
        $ts = strtotime($val);
        return $ts ? date('d M Y, h:i A', $ts) : htmlspecialchars($val);
    }
    return '-';
}

function getUser($usersCollection, $username, $role) {
    if (!$username) return ['name' => 'Unknown', 'phone' => '-', 'distance' => '-'];
    $user = $usersCollection->findOne(['username'=>$username,'role'=>$role]);
    if (!$user) return ['name'=>$username,'phone'=>'-','distance'=>'-'];
    $distance = ($role==='farmer' && isset($user['distance_to_market_km'])) ? $user['distance_to_market_km'] : '-';
    return [
        'name'=>$user['full_name']??$user['name']??$user['username'],
        'phone'=>$user['phone']??'-',
        'distance'=>$distance
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivered Orders - Transporter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/orders.css">
</head>
<body>

<div class="sidebar">
    <div class="profile-section">
         <img src="../uploads/<?= $displayImage ?>" alt="Profile" class="profile-img" onerror="this.src='../uploads/default.png'; this.onerror=null;">
        <h3><?= $displayName ?></h3>
        <p><?= $transporter ?></p>
        <p><i class="fas fa-truck"></i> Transporter</p>
    </div>
    
    <div class="nav-links">
        <a href="transporter_dashboard.php">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="transporter_time_slot.php">
            <i class="fas fa-clock"></i>
            <span>Time Slots</span>
        </a>
        <a href="transporter_delivered.php" class="active">
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
    <h1>✅ Delivered Orders History</h1>
    
    <!-- Single Stats Box -->
    <div class="stats-overview">
        <div class="stat-box">
            <i class="fas fa-boxes"></i>
            <div class="count"><?= $totalDelivered ?></div>
            <h3>Total Delivered Orders</h3>
        </div>
    </div>

    <?php if(empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-check"></i>
            <h3>No Delivered Orders Yet</h3>
            <p>You haven't delivered any orders yet. Completed deliveries will appear here.</p>
            <p style="color: #ff9a3c; font-weight: 500;"><i class="fas fa-info-circle"></i> Deliver orders from your Time Slots page to see them here.</p>
        </div>
    <?php else: ?>
        <div class="orders-grid">
            <?php foreach($orders as $order):
                $farmer = getUser($usersCollection, $order['farmer'] ?? null, 'farmer');
                $wholesaler = getUser($usersCollection, $order['wholesaler'] ?? null, 'wholesaler');
                $pickup_time = format_datetime_sl($order['pickup_datetime'] ?? null);
                $arrival_time = format_datetime_sl($order['market_arrival_datetime'] ?? null);
            ?>
            <div class="order-card">
                <div class="order-header">
                    <h3>
                        <i class="fas fa-box"></i>
                        <?= htmlspecialchars($order['product_name'] ?? 'Unknown Product') ?>
                    </h3>
                    <div class="order-id">ID: <?= substr((string)($order['_id'] ?? ''), -8) ?></div>
                    <div style="font-size: 0.95rem; opacity: 0.9;">
                        Quantity: <?= htmlspecialchars($order['quantity'] ?? '-') ?> units
                    </div>
                </div>
                
                <div class="order-content">
                    <div class="info-section">
                        <div class="info-title">
                            <i class="fas fa-user-tie"></i>
                            Farmer Details
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Name</div>
                                <div class="info-value"><?= htmlspecialchars($farmer['name']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?= htmlspecialchars($farmer['phone']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Distance</div>
                                <div class="info-value"><?= htmlspecialchars($farmer['distance']) ?> km</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <div class="info-title">
                            <i class="fas fa-store"></i>
                            Wholesaler Details
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Name</div>
                                <div class="info-value"><?= htmlspecialchars($wholesaler['name']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?= htmlspecialchars($wholesaler['phone']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="time-section">
                        <div class="time-item">
                            <div class="time-label">
                                <i class="fas fa-arrow-up"></i>
                                Pickup Time
                            </div>
                            <div class="time-value"><?= $pickup_time ?></div>
                        </div>
                        <div class="time-item">
                            <div class="time-label">
                                <i class="fas fa-arrow-down"></i>
                                Arrival Time
                            </div>
                            <div class="time-value"><?= $arrival_time ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
 <script src="assets/orders.js"></script>

</body>
</html>