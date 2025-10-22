<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only wholesalers can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.php");
    exit();
}

$wholesaler = $_SESSION['username'];
$ordersCollection = $db->orders;
$productsCollection = $db->products;

// Get statistics
$total_orders = $ordersCollection->countDocuments(['wholesaler' => $wholesaler]);
$pending_orders = $ordersCollection->countDocuments(['wholesaler' => $wholesaler, 'status' => 'pending']);
$completed_orders = $ordersCollection->countDocuments(['wholesaler' => $wholesaler, 'status' => 'completed']);
$monthly_spending = $ordersCollection->aggregate([
    ['$match' => ['wholesaler' => $wholesaler, 'status' => 'completed']],
    ['$group' => ['_id' => null, 'total' => ['$sum' => '$total_amount']]]
])->toArray();
$monthly_spending = $monthly_spending ? $monthly_spending[0]['total'] : 0;

// Get recent orders
$recent_orders = $ordersCollection->find(
    ['wholesaler' => $wholesaler],
    ['sort' => ['order_date' => -1], 'limit' => 5]
)->toArray();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wholesaler Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f9; margin: 0; color: #333; }
        /* ----- Navigation Bar ----- */
.navbar {
  background: #1e3d59;
  color: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 40px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}

.navbar .nav-left h2 {
  margin: 0;
  font-size: 22px;
}

.navbar .nav-right a {
  color: white;
  margin-left: 20px;
  text-decoration: none;
  font-weight: 600;
  transition: 0.3s;
  padding: 6px 10px;
  border-radius: 6px;
}

.navbar .nav-right a:hover {
  background: #3b7ea1;
}

.navbar .nav-right a.active {
  background: #2ecc71;
  color: white;
}

.navbar .nav-right a.logout {
  background: #e74c3c;
}


        .container { width: 95%; max-width: 1200px; margin: 20px auto; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #2c3e50; margin: 10px 0; }
        .stat-label { color: #7f8c8d; font-size: 14px; }
        
        .quick-actions { display: flex; gap: 15px; margin-bottom: 30px; justify-content: center; }
        .action-btn { padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: 0.3s; }
        .action-btn:hover { background: #2980b9; }
        .action-btn.green { background: #27ae60; }
        .action-btn.green:hover { background: #219150; }
        
        .recent-orders { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .order-item { padding: 15px; border-bottom: 1px solid #ecf0f1; display: flex; justify-content: space-between; align-items: center; }
        .order-item:last-child { border-bottom: none; }
        .status { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status.pending { background: #fff3cd; color: #856404; }
        .status.accepted { background: #d1ecf1; color: #0c5460; }
        .status.completed { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
     <nav class="navbar">
        <div>🌾 DMAS - Wholesaler Portal</div>
       <div class="nav-right">
      <a href="dashboard.php">🏠 Dashboard</a>
      <a href="product_marketplace.php">🛒 Marketplace</a>
      <a href="order_management.php">📦 Orders</a>
      <a href="place_order.php">📝 Place Orders</a>
      <a href="profile.php" class="active">👤 My Profile</a>
      <a href="../logout.php" class="logout">🚪 Logout</a>
    </div>
    </nav>

    <div class="container">
        <h1>Welcome, <?= htmlspecialchars($_SESSION['name'] ?? $wholesaler) ?></h1>
        
        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_orders ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $pending_orders ?></div>
                <div class="stat-label">Pending Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $completed_orders ?></div>
                <div class="stat-label">Completed Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">LKR <?= number_format($monthly_spending, 2) ?></div>
                <div class="stat-label">Monthly Spending</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="order_management.php" class="action-btn">View Orders</a>
            <a href="" class="action-btn">Price Trends</a>
        </div>

        <!-- Recent Orders -->
        <div class="recent-orders">
            <h2>Recent Orders</h2>
            <?php if(count($recent_orders) > 0): ?>
                <?php foreach($recent_orders as $order): ?>
                    <div class="order-item">
                        <div>
                            <strong>Order #<?= $order['_id'] ?></strong>
                            <div>Products: <?= count($order['items'] ?? []) ?> items</div>
                            <div>Total: LKR <?= number_format($order['total_amount'] ?? 0, 2) ?></div>
                        </div>
                        <div class="status <?= $order['status'] ?>"><?= ucfirst($order['status']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #7f8c8d;">No orders yet. <a href="products_marketplace.php">Start shopping!</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>