<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// ✅ Only admin can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ✅ Collections
$ordersCollection = $db->orders;
$usersCollection = $db->users;
$productsCollection = $db->products;

// ✅ Status filter - CORRECTED FILTERING LOGIC
$filterStatus = $_GET['status'] ?? '';

$filter = [];
if ($filterStatus !== '') {
    // Check what status values are actually stored in the database
    $allStatuses = $ordersCollection->distinct('status');
    
    // Try to match the status exactly as stored in database
    if (in_array($filterStatus, $allStatuses)) {
        $filter['status'] = $filterStatus;
    } else {
        // If exact match not found, try case-insensitive match
        foreach ($allStatuses as $dbStatus) {
            if (strtolower($dbStatus) === strtolower($filterStatus)) {
                $filter['status'] = $dbStatus;
                break;
            }
        }
    }
}

// ✅ Fetch all orders
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

/* ===== MODERN ADMIN NAVBAR ===== */
.navbar {
  background: linear-gradient(135deg, #141e30, #243b55);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 50px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}
.nav-left h2 { color: #fff; font-size: 22px; margin: 0; }
.nav-right a {
  color: #eaeaea; text-decoration: none; font-weight: 500;
  padding: 8px 14px; border-radius: 25px; transition: 0.3s;
}
.nav-right a.active { background: #27ae60; color: #fff; }
.nav-right a.logout { background: #e74c3c; color: #fff; }
.nav-right a:hover { background: rgba(255, 255, 255, 0.15); }

.container { width: 95%; margin: 20px auto; }
h1 { text-align: center; margin-bottom: 20px; color: #2c3e50; }

/* Tabs */
.tabs { display: flex; justify-content: center; margin-bottom: 20px; gap:10px; }
.tab { padding: 10px 20px; background: #ddd; text-decoration: none; color:#333; border-radius:5px; }
.tab.active { background: #007bff; color:#fff; }

/* Table */
.table-container { overflow-x:auto; background:#fff; border-radius:10px; box-shadow:0 3px 8px rgba(0,0,0,0.1); }
table { width:100%; border-collapse: collapse; }
th, td { padding:12px 15px; border-bottom:1px solid #ddd; text-align:center; }
th { background:#007bff; color:#fff; }
.status.Pending { color:#ffc107; font-weight:bold; }
.status.Accepted { color:#17a2b8; font-weight:bold; }
.status.Rejected { color:#dc3545; font-weight:bold; }
.status.Completed { color:#28a745; font-weight:bold; }

/* Buttons */
.actions a { text-decoration:none; margin:2px; display:inline-block; }
button {
  padding:8px 15px; border:none; border-radius:5px; cursor:pointer;
  color:#fff; font-weight:bold; transition:0.3s;
}
.add-slot { background:#f39c12; }
button:hover { opacity:0.85; }

.user-info { font-size: 14px; }
.user-name { font-weight: bold; }
.user-username { color: #666; font-size: 12px; }
.user-role { color: #888; font-size: 11px; font-style: italic; }
</style>
</head>
<body>

<nav class="navbar">
  <div class="nav-left">
    <h2>🌾 DMAS Admin Panel</h2>
  </div>
  <div class="nav-right">
    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="user_management.php">👥 Manage Users</a>
    <a href="order_management.php" class="active">📦 Manage Orders</a>
    <a href="time_slot_management.php">🚚 Time Slot</a>
    <a href="worker_registration.php">📝 Worker Registration</a>
    <a href="../logout.php" class="logout">🚪 Logout</a>
  </div>
</nav>

<div class="container">
<h1>Order Management</h1>

<!-- Filter Tabs - CORRECTED -->
<div class="tabs">
  <a href="?status=" class="tab <?= ($filterStatus=='')?'active':'' ?>">All Orders</a>
  <a href="?status=pending" class="tab <?= ($filterStatus=='pending')?'active':'' ?>">Pending</a>
  <a href="?status=accepted" class="tab <?= ($filterStatus=='accepted')?'active':'' ?>">Accepted</a>
  <a href="?status=rejected" class="tab <?= ($filterStatus=='rejected')?'active':'' ?>">Rejected</a>
  <a href="?status=completed" class="tab <?= ($filterStatus=='completed')?'active':'' ?>">Completed</a>
</div>

<!-- Orders Table -->
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
<th>Actions</th>
</tr>
</thead>
<tbody>

<?php if(count($orders) > 0): ?>
  <?php foreach($orders as $order): ?>
    <?php
    // ✅ Get product details
    $product = null;
    if (isset($order['product_id'])) {
        try {
            $product = $productsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($order['product_id'])]);
        } catch (Exception $e) {
            $product = null;
        }
    }

    // ✅ Get farmer details - based on your user management structure
    $farmer = null;
    $farmerUsername = null;
    
    // Check different possible farmer identifier fields
    $farmerIdentifiers = ['farmer_username', 'farmer', 'farmer_id', 'user_id', 'farmer_name'];
    foreach ($farmerIdentifiers as $field) {
        if (isset($order[$field]) && !empty($order[$field])) {
            $farmerUsername = $order[$field];
            break;
        }
    }
    
    if ($farmerUsername) {
        // Try to find farmer by username (as shown in your user management)
        $farmer = $usersCollection->findOne([
            'username' => $farmerUsername,
            'role' => 'farmer'
        ]);
        
        // If not found by username, try by name
        if (!$farmer) {
            $farmer = $usersCollection->findOne([
                'name' => $farmerUsername,
                'role' => 'farmer'
            ]);
        }
        
        // If still not found, try any user with this username/name
        if (!$farmer) {
            $farmer = $usersCollection->findOne(['username' => $farmerUsername]);
            if (!$farmer) {
                $farmer = $usersCollection->findOne(['name' => $farmerUsername]);
            }
        }
    }

    // ✅ Get wholesaler details - based on your user management structure
    $wholesaler = null;
    $wholesalerUsername = null;
    
    // Check different possible wholesaler identifier fields
    $wholesalerIdentifiers = ['wholesaler_username', 'wholesaler', 'wholesaler_id', 'seller_id'];
    foreach ($wholesalerIdentifiers as $field) {
        if (isset($order[$field]) && !empty($order[$field])) {
            $wholesalerUsername = $order[$field];
            break;
        }
    }
    
    if ($wholesalerUsername) {
        // Try to find wholesaler by username (as shown in your user management)
        $wholesaler = $usersCollection->findOne([
            'username' => $wholesalerUsername,
            'role' => 'wholesaler'
        ]);
        
        // If not found by username, try by name
        if (!$wholesaler) {
            $wholesaler = $usersCollection->findOne([
                'name' => $wholesalerUsername,
                'role' => 'wholesaler'
            ]);
        }
        
        // If still not found, try any user with this username/name
        if (!$wholesaler) {
            $wholesaler = $usersCollection->findOne(['username' => $wholesalerUsername]);
            if (!$wholesaler) {
                $wholesaler = $usersCollection->findOne(['name' => $wholesalerUsername]);
            }
        }
    }

    // Get order ID
    $orderId = $order['order_id'] ?? $order['_id'] ?? '-';
    if ($orderId instanceof MongoDB\BSON\ObjectId) {
        $orderId = (string)$orderId;
    }

    $totalPrice = $order['total'] ?? ($order['quantity'] * ($product['price'] ?? 0));
    $orderDate = isset($order['order_date']) ? date('d M Y, h:i A', strtotime($order['order_date'])) : '-';
    ?>
    <tr>
      <td><?= htmlspecialchars($orderId) ?></td>
      <td class="user-info">
        <?php if ($wholesaler): ?>
          <div class="user-name"><?= htmlspecialchars($wholesaler['name'] ?? 'N/A') ?></div>
        <?php else: ?>
          <div class="user-name">Not Found</div>
          <div class="user-username">ID: <?= htmlspecialchars($wholesalerUsername ?? 'Unknown') ?></div>
        <?php endif; ?>
      </td>
      <td class="user-info">
        <?php if ($farmer): ?>
          <div class="user-name"><?= htmlspecialchars($farmer['name'] ?? 'N/A') ?></div>
        <?php else: ?>
          <div class="user-name">Not Found</div>
          <div class="user-username">ID: <?= htmlspecialchars($farmerUsername ?? 'Unknown') ?></div>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($product['name'] ?? $order['product_name'] ?? '-') ?></td>
      <td><?= htmlspecialchars($order['quantity'] ?? '-') ?></td>
      <td><?= htmlspecialchars($totalPrice) ?> LKR</td>
      <td><?= htmlspecialchars($orderDate) ?></td>
      <td><span class="status <?= htmlspecialchars($order['status'] ?? '-') ?>"><?= htmlspecialchars($order['status'] ?? '-') ?></span></td>
      <td class="actions">
        <a href="time_slot_management.php?order_id=<?= (string)$order['_id'] ?>">
          <button class="add-slot">Add Time Slot</button>
        </a>
      </td>
    </tr>
  <?php endforeach; ?>
<?php else: ?>
  <tr><td colspan="9">No orders found.</td></tr>
<?php endif; ?>

</tbody>
</table>
</div>

</div>

</body>
</html>



