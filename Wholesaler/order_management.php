<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.php");
    exit();
}

$wholesaler = $_SESSION['username'];

$ordersCollection = $db->orders;
$productsCollection = $db->products;
$usersCollection = $db->users;


$statusFilter = $_GET['status'] ?? 'all';

$filter = ['wholesaler' => $wholesaler];
if ($statusFilter !== 'all') {
    $filter['status'] = $statusFilter;
}


try {
    $ordersCursor = $ordersCollection->find(
        $filter,
        ['sort' => ['order_date' => -1]]
    );
    $orders = iterator_to_array($ordersCursor);

    $allOrders = $ordersCollection->find(['wholesaler' => $wholesaler])->toArray();
    $statusCounts = [];
    foreach ($allOrders as $ord) {
        $s = $ord['status'] ?? 'unknown';
        $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
    }
} catch (Exception $e) {
    $orders = [];
}

function to_colombo_datetime($mongoDate) {
    if ($mongoDate instanceof MongoDB\BSON\UTCDateTime) {
        $dt = $mongoDate->toDateTime();
        $dt->setTimezone(new DateTimeZone('Asia/Colombo'));
        return $dt;
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders - DMAS</title>
<link rel="stylesheet" href="assets/order_management.css">
</head>
<body>

     <nav class="navbar">
            <div class="brand">🌾 DMAS - Wholesaler Portal</div>
            <div class="menu" id="menu">
                <a href="dashboard.php">🏠 Dashboard</a>
                <a href="product_marketplace.php">🛒 Marketplace</a>
                <a href="order_management.php"class="active">📦 Orders</a>
                <a href="prediction copy.php">📈 Price Prediction</a>
                <a href="view_workers.php">👨‍🔧 View Workers</a>
                <a href="profile.php">👤 My Profile</a>
                <a href="../logout.php" class="logout" onclick="return confirm('Are you sure you want to logout?');">🚪 Logout</a>
            </div>
            <div class="hamburger" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
     </nav>

    <div class="container">
    <h1>My Orders</h1>

        <div class="status-tabs">
            <a href="?status=all" class="status-tab <?= $statusFilter=='all'?'active':'' ?>">All (<?= array_sum($statusCounts) ?>)</a>
            <a href="?status=accepted" class="status-tab <?= $statusFilter=='accepted'?'active':'' ?>">Accepted (<?= $statusCounts['accepted'] ?? 0 ?>)</a>
            <a href="?status=delivery" class="status-tab <?= $statusFilter=='delivery'?'active':'' ?>">In Delivery (<?= $statusCounts['delivery'] ?? 0 ?>)</a>
            <a href="?status=completed" class="status-tab <?= $statusFilter=='completed'?'active':'' ?>">Completed (<?= $statusCounts['completed'] ?? 0 ?>)</a>
            <a href="?status=rejected" class="status-tab <?= $statusFilter=='rejected'?'active':'' ?>">Rejected (<?= $statusCounts['rejected'] ?? 0 ?>)</a>
        </div>

    <div class="orders-list">

    <?php if(count($orders)>0): ?>

    <div class="order-header">
        <div>Product & Farmer</div>
        <div>Qty</div>
        <div>Unit Price</div>
        <div>Total Amount</div>
        <div>Order Date</div>
        <div>Market Arrival</div>
        <div>Status</div>
    </div>

<?php foreach($orders as $order): 
        $order_id = $order['_id'];
        $product_name = $order['product_name'];
        $farmer_username = $order['farmer'];
        $quantity = $order['quantity'];
        $unit_price = floatval($order['unit_price']);
        $total = floatval($order['total_amount']);
        $status = $order['status'];
        $date = $order['order_date'];


        $farmer = $usersCollection->findOne(['username' => $farmer_username]);
        $farmer_name = $farmer['name'] ?? $farmer_username;


    $market_arrival_display = '-';
    if (in_array($status, ['accepted','delivery','completed']) && !empty($order['market_arrival_datetime'])) {
        $market_dt = to_colombo_datetime($order['market_arrival_datetime']);
        $market_arrival_display = $market_dt->format('d M Y, h:i A');
    }
?>

    <div class="order-item">
        <div>
            <strong><?= $product_name ?></strong><br>
            <small>Farmer: <?= $farmer_name ?></small><br>
            <small style="color:#888">Order ID: <?= $order_id ?></small>
        </div>

        <div><?= $quantity ?></div>
        <div>LKR <?= number_format($unit_price,2) ?></div>
        <div><strong>LKR <?= number_format($total,2) ?></strong></div>
        <div>
            <?= date('M d, Y', $date->toDateTime()->getTimestamp()) ?><br>
            <small><?= date('h:i A',$date->toDateTime()->getTimestamp()) ?></small>
        </div>
        <div><?= $market_arrival_display ?></div>
        <div>
            <div class="status <?= $status ?>"><?= ucfirst($status) ?></div>
            <?php if($status==='pending'): ?>
                <a class="action-btn" href="cancel_order.php?id=<?= $order_id ?>" onclick="return confirm('Cancel this order?')">Cancel</a>
            <?php endif; ?>
        </div>
    </div>

<?php endforeach; ?>

<?php else: ?>
<p style="padding:30px; text-align:center;">No orders found.</p>
<?php endif; ?>

</div>
</div>
    <script>
        function toggleMenu() {
            document.getElementById('menu').classList.toggle('show');
        }
    </script>
</body>
</html>
