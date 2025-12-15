<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Collections
$ordersCollection = $db->orders;
$usersCollection = $db->users;
$productsCollection = $db->products;

// Status filter (case-insensitive)
$filterStatus = $_GET['status'] ?? '';
$filter = [];

if ($filterStatus !== '') {
    $allStatuses = $ordersCollection->distinct('status');
    $matchedStatus = null;
    foreach ($allStatuses as $dbStatus) {
        if (strtolower($dbStatus) === strtolower($filterStatus)) {
            $matchedStatus = $dbStatus;
            break;
        }
    }
    if ($matchedStatus !== null) {
        $filter['status'] = $matchedStatus;
    }
}

// Fetch orders
$ordersCursor = $ordersCollection->find($filter, ['sort' => ['_id' => -1]]);
$orders = iterator_to_array($ordersCursor);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Management</title>
<style>
body { font-family: Arial, sans-serif; background: #f4f6f8; margin:0; padding:0; }
.navbar { background: linear-gradient(135deg, #141e30, #243b55); display: flex; justify-content: space-between; align-items: center; padding: 16px 50px; box-shadow: 0 4px 12px rgba(0,0,0,0.25);}
.nav-left h2 { color: #fff; font-size: 22px; margin: 0; }
.nav-right a { color: #eaeaea; text-decoration: none; font-weight: 500; padding: 8px 14px; border-radius: 25px; transition: 0.3s;}
.nav-right a.active { background: #27ae60; color: #fff; }
.nav-right a.logout { background: #e74c3c; color: #fff; }
.nav-right a:hover { background: rgba(255, 255, 255, 0.15); }
.container { width: 95%; margin: 20px auto; }
h1 { text-align: center; margin-bottom: 20px; color: #2c3e50; }
.tabs { display: flex; justify-content: center; margin-bottom: 20px; gap:10px; flex-wrap: wrap;}
.tab { padding: 10px 20px; background: #ddd; text-decoration: none; color:#333; border-radius:5px; }
.tab.active { background: #007bff; color:#fff; }
.table-container { overflow-x:auto; background:#fff; border-radius:10px; box-shadow:0 3px 8px rgba(0,0,0,0.1); }
table { width:100%; border-collapse: collapse; }
th, td { padding:12px 15px; border-bottom:1px solid #ddd; text-align:center; }
th { background:#007bff; color:#fff; }
.status.Pending { color:#ffc107; font-weight:bold; }
.status.Accepted { color:#17a2b8; font-weight:bold; }
.status.Rejected { color:#dc3545; font-weight:bold; }
.status.Completed { color:#28a745; font-weight:bold; }
.user-info { font-size: 14px; }
.user-name { font-weight: bold; }
.user-username { color: #666; font-size: 12px; }
</style>
</head>
<body>

<nav class="navbar">
  <div class="nav-left"><h2>🌾 DMAS Admin Panel</h2></div>
  <div class="nav-right">
    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="user_management.php">👥 Manage Users</a>
    <a href="order_management.php" class="active">📦 View Orders</a>
    <a href="admin_report.php">📄 Report</a>
    <a href="worker_registration.php">📝 Worker Registration</a>
    <a href="../logout.php" class="logout">🚪 Logout</a>
  </div>
</nav>

<div class="container">
<h1>Order Management</h1>

<div class="tabs">
  <a href="?status=" class="tab <?= ($filterStatus=='')?'active':'' ?>">All Orders</a>
  <a href="?status=pending" class="tab <?= (strtolower($filterStatus)=='pending')?'active':'' ?>">Pending</a>
  <a href="?status=accepted" class="tab <?= (strtolower($filterStatus)=='accepted')?'active':'' ?>">Accepted</a>
  <a href="?status=rejected" class="tab <?= (strtolower($filterStatus)=='rejected')?'active':'' ?>">Rejected</a>
  <a href="?status=completed" class="tab <?= (strtolower($filterStatus)=='completed')?'active':'' ?>">Completed</a>
</div>

<div class="table-container">
<table>
<thead>
<tr>
<th>Order ID</th>
<th>Wholesaler</th>
<th>Farmer</th>
<th>Product</th>
<th>Quantity</th>
<th>Total Price</th>
<th>Order Date</th>
<th>Status</th>
</tr>
</thead>
<tbody>

<?php if(count($orders) > 0): ?>
  <?php foreach($orders as $order): ?>
    <?php
    // Product
    $product = null;
    if(!empty($order['product_id'])){
        try {
            $product = $productsCollection->findOne(['_id'=>new MongoDB\BSON\ObjectId($order['product_id'])]);
        } catch(Exception $e){ $product = null; }
    }

    // Farmer - Get username from order and find user
    $farmerUsername = $order['farmer'] ?? '';
    $farmer = null;
    if ($farmerUsername) {
        $farmer = $usersCollection->findOne(['username' => $farmerUsername]);
    }

    // Wholesaler - Get username from order and find user
    $wholesalerUsername = $order['wholesaler'] ?? '';
    $wholesaler = null;
    if ($wholesalerUsername) {
        $wholesaler = $usersCollection->findOne(['username' => $wholesalerUsername]);
    }

    // IDs
    $orderId = $order['order_id'] ?? $order['_id'] ?? '-';
    if ($orderId instanceof MongoDB\BSON\ObjectId) $orderId = (string)$orderId;

    // Quantity & Price
    $quantity = 0;
    if(isset($order['quantity'])){
        $quantity = is_numeric($order['quantity']) ? (float)$order['quantity'] : floatval($order['quantity']);
    }
    $unitPrice = isset($product['price']) && is_numeric($product['price']) ? (float)$product['price'] : 0;
    $totalPrice = isset($order['total']) ? (float)$order['total'] : ($quantity * $unitPrice);

    // Order date
    $orderDate = '-';
    if(isset($order['order_date'])){
        if($order['order_date'] instanceof MongoDB\BSON\UTCDateTime){
            $dt = $order['order_date']->toDateTime();
            $dt->setTimezone(new DateTimeZone('Asia/Colombo'));
            $orderDate = $dt->format('d M Y, h:i A');
        } else {
            $orderDate = date('d M Y, h:i A', strtotime($order['order_date']));
        }
    }
    ?>
    <tr>
      <td><?= htmlspecialchars(substr($orderId, -8)) ?></td>
      <td class="user-info">
        <?php if($wholesaler): ?>
          <?= htmlspecialchars($wholesaler['name'] ?? $wholesaler['username']) ?>
        <?php elseif($wholesalerUsername): ?>
          <?= htmlspecialchars($wholesalerUsername) ?>
        <?php else: ?>
          Not Found
        <?php endif; ?>
      </td>
      <td class="user-info">
        <?php if($farmer): ?>
          <?= htmlspecialchars($farmer['name'] ?? $farmer['username']) ?>
        <?php elseif($farmerUsername): ?>
          <?= htmlspecialchars($farmerUsername) ?>
        <?php else: ?>
          Not Found
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($product['name'] ?? $order['product_name'] ?? '-') ?></td>
      <td><?= $quantity ?></td>
      <td><?= number_format($totalPrice,2) ?> LKR</td>
      <td><?= htmlspecialchars($orderDate) ?></td>
      <td><span class="status <?= ucfirst(strtolower($order['status'] ?? '')) ?>"><?= htmlspecialchars($order['status'] ?? '-') ?></span></td>
    </tr>
  <?php endforeach; ?>
<?php else: ?>
  <tr><td colspan="8">No orders found.</td></tr>
<?php endif; ?>

</tbody>
</table>
</div>
</div>
</body>
</html>