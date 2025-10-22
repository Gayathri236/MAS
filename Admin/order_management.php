<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only admin can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
$ordersCollection = $db->orders;
// Fetch filters
$filterStatus = $_GET['status'] ?? ''; // pending, accepted, rejected, completed

$filter = [];
if ($filterStatus !== '') {
    $filter['status'] = ucfirst(strtolower($filterStatus));
}

// Fetch orders
$ordersCursor = $ordersCollection->find($filter, ['sort'=>['_id'=>-1]]);
$orders = iterator_to_array($ordersCursor);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Management</title>
<link rel="stylesheet" href="order_management.css">
<style>
/* ==== Basic CSS ==== */
body { font-family: Arial, sans-serif; background: #f4f6f8; margin:0; padding:0; }
/* ===== MODERN ADMIN NAVBAR ===== */
.navbar {
  background: linear-gradient(135deg, #141e30, #243b55);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 50px;
  position: sticky;
  top: 0;
  z-index: 1000;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
  border-bottom: 2px solid rgba(255, 255, 255, 0.1);
}

.nav-left h2 {
  color: #ffffff;
  font-size: 22px;
  letter-spacing: 1px;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.nav-right {
  display: flex;
  align-items: center;
  gap: 15px;
}

.nav-right a {
  color: #eaeaea;
  text-decoration: none;
  font-weight: 500;
  padding: 8px 14px;
  border-radius: 25px;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.3s ease;
  font-size: 15px;
}

.nav-right a:hover {
  background: rgba(255, 255, 255, 0.15);
  transform: translateY(-2px);
}

.nav-right a.active {
  background: #27ae60;
  color: #fff;
  box-shadow: 0 0 10px rgba(39, 174, 96, 0.4);
}

.nav-right a.logout {
  background: #e74c3c;
  color: #fff;
  font-weight: 600;
  border-radius: 25px;
}

.nav-right a.logout:hover {
  background: #c0392b;
  transform: scale(1.05);
}

/* ===== MOBILE FRIENDLY NAVBAR ===== */
@media (max-width: 900px) {
  .navbar {
    flex-direction: column;
    align-items: flex-start;
    padding: 15px 25px;
  }

  .nav-right {
    flex-wrap: wrap;
    width: 100%;
    justify-content: flex-start;
    margin-top: 10px;
    gap: 10px;
  }

  .nav-right a {
    font-size: 14px;
    padding: 8px 10px;
  }
}

.container { width: 95%; margin: 20px auto; }

h1 { text-align: center; margin-bottom: 20px; }

/* Tabs */
.tabs { display: flex; justify-content: center; margin-bottom: 20px; gap:10px; }
.tab { padding: 10px 20px; background: #ddd; text-decoration: none; color:#333; border-radius:5px; }
.tab.active { background: #007bff; color:#fff; }

/* Table */
.table-container { overflow-x:auto; }
table { width:100%; border-collapse: collapse; background:#fff; }
th, td { padding:12px 15px; border:1px solid #ddd; text-align:center; }
th { background:#007bff; color:#fff; }
.status.Pending { color:#ffc107; font-weight:bold; }
.status.Accepted { color:#17a2b8; font-weight:bold; }
.status.Rejected { color:#dc3545; font-weight:bold; }
.status.Completed { color:#28a745; font-weight:bold; }

/* Action Buttons */
.actions a { text-decoration:none; margin:2px; }
.actions button { padding:5px 10px; border:none; border-radius:3px; cursor:pointer; color:#fff; }
.complete { background:#28a745; }
.contact { background:#25d366; }
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
    <a href="order_management.php"class="active">📦  Manage Orders</a>
    <a href="time_slot_management.php">🚚 Time Slot</a>
    <a href="worker_registration.php">📝 Worker Registration</a>
    <a href="../logout.php" class="logout">🚪 Logout</a>
  </div>
</nav>


<div class="container">
<h1>Order Management</h1>

<!-- Tabs -->
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
<th>Products</th>
<th>Quantity</th>
<th>Price</th>
<th>Order Date</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if(count($orders) > 0): ?>
<?php foreach($orders as $order): 
    $product = $productsCollection->findOne(['_id'=>$order['product_id']]) ?? [];
    $farmer = $usersCollection->findOne(['username'=>$order['farmer']]) ?? [];
    $wholesaler = $usersCollection->findOne(['username'=>$order['wholesaler']]) ?? [];
    $totalPrice = $order['total'] ?? ($order['quantity']*($order['unit_price'] ?? 0));
    $orderDate = isset($order['date']) ? date('d M Y, H:i', strtotime($order['date'])) : '-';
?>
<tr>
<td><?= htmlspecialchars($order['order_id'] ?? '-') ?></td>
<td><?= htmlspecialchars($wholesaler['name'] ?? $order['wholesaler']) ?></td>
<td><?= htmlspecialchars($farmer['name'] ?? $order['farmer']) ?></td>
<td><?= htmlspecialchars($product['name'] ?? $order['product_name']) ?></td>
<td><?= htmlspecialchars($order['quantity'] ?? '-') ?> <?= htmlspecialchars($product['unit'] ?? '-') ?></td>
<td><?= htmlspecialchars($totalPrice) ?> LKR</td>
<td><?= $orderDate ?></td>
<td><span class="status <?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
<td class="actions">
    <?php if($order['status']=='Pending'): ?>
        <a href="update_order.php?accept=<?= $order['_id'] ?>"><button class="complete">Accept</button></a>
        <a href="update_order.php?reject=<?= $order['_id'] ?>"><button class="complete" style="background:#dc3545;">Reject</button></a>
    <?php endif; ?>
    <a href="#"><button class="contact">View Details</button></a>
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
