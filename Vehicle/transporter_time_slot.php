<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Colombo');

require __DIR__ . '/../mongodb_config.php';

$ordersCollection = $db->orders;
$usersCollection  = $db->users;
$timeslotCollection = $db->timeslot; // <-- NEW COLLECTION

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

// ------------------------- HANDLE START/END TRANSPORT -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $orderId = $_POST['order_id'] ?? null;

    if ($orderId) {

        $orderObjectId = new MongoDB\BSON\ObjectId($orderId);

        // Fetch Order
        $order = $ordersCollection->findOne(['_id' => $orderObjectId]);

        // Fetch farmer & wholesaler information
        $farmer = getUserInfo($usersCollection, $order['farmer'] ?? null, 'farmer');
        $wholesaler = getUserInfo($usersCollection, $order['wholesaler'] ?? null, 'wholesaler');

        // Common timeslot data
        $timeslotData = [
            'order_id'        => $orderObjectId,
            'transporter'     => $transporter,
            'farmer_name'     => $farmer['name'] ?? '-',
            'farmer_location' => $farmer['location'] ?? '-',
            'wholesaler_name' => $wholesaler['name'] ?? '-',
            'quantity'        => $order['quantity'] ?? '-',
            'pickup_time'     => $order['pickup_datetime'] ?? null,
            'arrival_time'    => $order['market_arrival_datetime'] ?? null,
            'map'             => "https://www.google.com/maps?q=" . urlencode($farmer['location'] ?? 'Sri Lanka'),
        ];

        // START TRANSPORT
        if (isset($_POST['start_transport'])) {

            $ordersCollection->updateOne(
                ['_id' => $orderObjectId],
                ['$set' => [
                    'status' => 'in_transit',
                    'transport_start_time' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            // Save to timeslot log
            $timeslotCollection->insertOne(array_merge($timeslotData, [
                'action' => 'start_transport',
                'timestamp' => new MongoDB\BSON\UTCDateTime()
            ]));
        }

        // END TRANSPORT
        if (isset($_POST['end_transport'])) {

            $ordersCollection->updateOne(
                ['_id' => $orderObjectId],
                ['$set' => [
                    'status' => 'delivered',
                    'transport_end_time' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            // Save to timeslot log
            $timeslotCollection->insertOne(array_merge($timeslotData, [
                'action' => 'end_transport',
                'timestamp' => new MongoDB\BSON\UTCDateTime()
            ]));
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ------------------------- FETCH ORDERS -------------------------
try {
    $ordersCursor = $ordersCollection->find(
        [
            'transporter' => $transporter,
            'status' => ['$in' => ['accepted','in_transit']],
            'wholesaler' => ['$ne' => '']
        ],
        ['sort' => ['pickup_slot_date' => 1, 'pickup_slot_label' => 1]]
    );
    $orders = iterator_to_array($ordersCursor);
} catch (Exception $e) {
    $orders = [];
}

// ------------------------- STATS -------------------------
$totalOrders = count($orders);
$acceptedOrders = count(array_filter($orders, fn($o) => $o['status'] === 'accepted'));
$inTransitOrders = count(array_filter($orders, fn($o) => $o['status'] === 'in_transit'));

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

function getUserInfo($usersCollection, $username, $role) {
    if (!$username) return ['name' => 'Unknown', 'phone' => '-', 'distance' => '-', 'location'=>'-'];

    $user = $usersCollection->findOne(['username'=>$username,'role'=>$role]);
    if (!$user) return ['name'=>$username,'phone'=>'-','distance'=>'-','location'=>'-'];

    return [
        'name'     => $user['full_name'] ?? $user['name'] ?? $user['username'],
        'phone'    => $user['phone'] ?? '-',
        'distance' => $user['distance_to_market_km'] ?? '-',
        'location' => $user['location'] ?? '-'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transporter - Time Slots</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/time_slot.css">
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
        <a href="transporter_time_slot.php" class="active">
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
    <h1>🕒 Your Assigned Time Slots</h1>
    
    <!-- Stats Overview -->
    <div class="stats-overview">
        <div class="stat-box">
            <i class="fas fa-clipboard-list"></i>
            <h3>Total Slots</h3>
            <div class="count"><?= $totalOrders ?></div>
        </div>
        
        <div class="stat-box">
            <i class="fas fa-hourglass-half"></i>
            <h3>Ready for Pickup</h3>
            <div class="count"><?= $acceptedOrders ?></div>
        </div>
        
        <div class="stat-box">
            <i class="fas fa-truck-moving"></i>
            <h3>In Transit</h3>
            <div class="count"><?= $inTransitOrders ?></div>
        </div>
    </div>

    <?php if(empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No Assigned Time Slots</h3>
            <p>You don't have any assigned time slots yet. Time slots will appear here when farmers accept your availability.</p>
            <p style="color: #ff9a3c; font-weight: 500;"><i class="fas fa-info-circle"></i> Make sure your profile is complete and you've set your availability.</p>
        </div>
    <?php else: ?>
        <div class="slot-cards-container">
            <?php foreach ($orders as $order):
                $farmer_info = getUserInfo($usersCollection, $order['farmer'] ?? null, 'farmer');
                $wholesaler_info = getUserInfo($usersCollection, $order['wholesaler'] ?? null, 'wholesaler');

                $pickup_display = format_datetime_sl($order['pickup_datetime'] ?? null);
                $market_display = format_datetime_sl($order['market_arrival_datetime'] ?? null);
                $status = $order['status'] ?? 'accepted';
            ?>
            <div class="slot-card">
                <div class="slot-header">
                    <h3>
                        <i class="fas fa-box"></i>
                        <?= htmlspecialchars($order['product_name'] ?? 'Unknown Product') ?>
                    </h3>
                    <div class="slot-time">
                        <i class="fas fa-clock"></i>
                        <?= htmlspecialchars(($order['pickup_slot_label'] ?? 'Time') . ' - ' . ($order['pickup_slot_date'] ?? 'Date')) ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <span class="status-badge status-<?= $status ?>">
                            <?= ucfirst(str_replace('_', ' ', $status)) ?>
                        </span>
                    </div>
                </div>
                
                <div class="slot-content">
                    <div class="info-row">
                        <span class="info-label">Order ID:</span>
                        <span class="info-value"><?= substr((string)($order['_id'] ?? ''), -8) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Quantity:</span>
                        <span class="info-value"><?= htmlspecialchars($order['quantity'] ?? '-') ?> units</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Farmer:</span>
                        <span class="info-value"><?= htmlspecialchars($farmer_info['name']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Farmer Phone:</span>
                        <span class="info-value"><?= htmlspecialchars($farmer_info['phone']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Distance to Market:</span>
                        <span class="info-value"><?= htmlspecialchars($farmer_info['distance']) ?> km</span>
                    </div>
                    
                    <div class="map-container">
                        <iframe 
                            src="https://www.google.com/maps?q=<?= urlencode($farmer_info['location'] ?? 'Sri Lanka') ?>&output=embed&zoom=12"
                            allowfullscreen>
                        </iframe>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Wholesaler:</span>
                        <span class="info-value"><?= htmlspecialchars($wholesaler_info['name']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Wholesaler Phone:</span>
                        <span class="info-value"><?= htmlspecialchars($wholesaler_info['phone']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Pickup Time:</span>
                        <span class="info-value"><?= $pickup_display ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Market Arrival:</span>
                        <span class="info-value"><?= $market_display ?></span>
                    </div>
                    
                    <!-- Transport buttons -->
                    <div class="transport-actions">
                        <?php if($status === 'accepted' || $status === 'in_transit'): ?>
                            <form method="POST" class="transport-form">
                                <input type="hidden" name="order_id" value="<?= $order['_id'] ?>">
                                <?php if($status === 'accepted'): ?>
                                    <button type="submit" name="start_transport" class="btn start-btn">
                                        <i class="fas fa-play-circle"></i> Start Transport
                                    </button>
                                <?php elseif($status === 'in_transit'): ?>
                                    <button type="submit" name="end_transport" class="btn end-btn">
                                        <i class="fas fa-flag-checkered"></i> End Transport
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
 <script src="assets/time_slot.js"></script>
</body>
</html>

