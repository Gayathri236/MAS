<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.php");
    exit();
}

$wholesaler = $_SESSION['username'];
$ordersCollection = $db->orders;
$productsCollection = $db->products;
$usersCollection = $db->users;

// Filter by status
$statusFilter = $_GET['status'] ?? 'all';
$filter = ['wholesaler' => $wholesaler];
if ($statusFilter !== 'all') {
    $filter['status'] = $statusFilter;
}

// Debug: Check the filter being applied
error_log("Status Filter: " . $statusFilter);
error_log("MongoDB Filter: " . json_encode($filter));

// Get orders with error handling
try {
    $ordersCursor = $ordersCollection->find(
        $filter,
        ['sort' => ['order_date' => -1]]
    );
    $orders = iterator_to_array($ordersCursor);
    
    // Debug: Check what orders are found
    error_log("Found " . count($orders) . " orders for wholesaler: " . $wholesaler . " with status: " . $statusFilter);
    
    // Debug: List all order statuses for this wholesaler
    $allOrders = $ordersCollection->find(['wholesaler' => $wholesaler])->toArray();
    $statusCounts = [];
    foreach ($allOrders as $ord) {
        $status = $ord['status'] ?? 'unknown';
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }
    error_log("All order status counts: " . json_encode($statusCounts));
    
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - DMAS</title>
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
        
        .status-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .status-tab { padding: 8px 16px; background: #ecf0f1; border-radius: 20px; text-decoration: none; color: #333; font-weight: bold; }
        .status-tab.active { background: #3498db; color: white; }
        
        .debug-info { background: #fff3cd; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        
        .orders-list { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        .order-header { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 15px; padding: 15px 20px; background: #34495e; color: white; font-weight: bold; }
        .order-item { padding: 20px; border-bottom: 1px solid #ecf0f1; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 15px; align-items: center; }
        .order-item:last-child { border-bottom: none; }
        .order-item:hover { background: #f8f9fa; }
        
        .status { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-align: center; }
        .status.pending { background: #fff3cd; color: #856404; }
        .status.accepted { background: #d1ecf1; color: #0c5460; }
        .status.rejected { background: #f8d7da; color: #721c24; }
        .status.completed { background: #d4edda; color: #155724; }
        .status.delivery { background: #cce7ff; color: #004085; }
        .status.unknown { background: #e2e3e5; color: #383d41; }
        
        .action-btn { padding: 6px 12px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; display: inline-block; }
        .action-btn:hover { background: #2980b9; }
        .action-btn.view { background: #27ae60; }
        .action-btn.view:hover { background: #219150; }
        
        .success-message { background: #d4edda; color: #155724; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
        .empty-state { text-align: center; padding: 40px; color: #7f8c8d; }
        .empty-state a { color: #3498db; text-decoration: none; font-weight: bold; }
        
        @media (max-width: 768px) {
            .order-header { display: none; }
            .order-item { grid-template-columns: 1fr; gap: 10px; text-align: center; }
        }
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
        <h1>My Orders</h1>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="success-message">
                ✅ Order placed successfully! The farmer has been notified.
            </div>
        <?php endif; ?>
        
        <!-- Debug Information (remove after testing) -->
        <div class="debug-info">
            <strong>Debug Info:</strong><br>
            Current Filter: <strong><?= $statusFilter ?></strong><br>
            Orders Found: <strong><?= count($orders) ?></strong><br>
            <?php 
            // Show all available statuses and counts
            if (isset($statusCounts)) {
                echo "Available Statuses: ";
                foreach ($statusCounts as $status => $count) {
                    echo "<span style='margin: 0 5px; padding: 2px 8px; background: #ddd; border-radius: 10px;'>$status ($count)</span>";
                }
            }
            ?>
        </div>
        
        <!-- Status Tabs -->
        <div class="status-tabs">
            <a href="?status=all" class="status-tab <?= $statusFilter === 'all' ? 'active' : '' ?>">All Orders</a>
            <a href="?status=pending" class="status-tab <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="?status=accepted" class="status-tab <?= $statusFilter === 'accepted' ? 'active' : '' ?>">Accepted</a>
            <a href="?status=delivery" class="status-tab <?= $statusFilter === 'delivery' ? 'active' : '' ?>">In Delivery</a>
            <a href="?status=completed" class="status-tab <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
            <a href="?status=rejected" class="status-tab <?= $statusFilter === 'rejected' ? 'active' : '' ?>">Rejected</a>
        </div>

        <!-- Orders List -->
        <div class="orders-list">
            <?php if(count($orders) > 0): ?>
                <!-- Header -->
                <div class="order-header">
                    <div>Product & Farmer</div>
                    <div>Quantity</div>
                    <div>Unit Price</div>
                    <div>Total Amount</div>
                    <div>Order Date</div>
                    <div>Status & Actions</div>
                </div>

                <!-- Order Items -->
                <?php foreach($orders as $order): 
                    // Safely get order data with null checks
                    $order_id = $order['_id'] ?? '';
                    $product_name = $order['product_name'] ?? 'Unknown Product';
                    $farmer_username = $order['farmer'] ?? 'Unknown Farmer';
                    $quantity = $order['quantity'] ?? 0;
                    $unit_price = $order['unit_price'] ?? 0;
                    $total_amount = $order['total_amount'] ?? 0;
                    $status = $order['status'] ?? 'unknown';
                    $order_date = $order['order_date'] ?? new MongoDB\BSON\UTCDateTime();
                    
                    // Get farmer details
                    $farmer = $usersCollection->findOne(['username' => $farmer_username]);
                    $farmer_name = $farmer['name'] ?? $farmer_username;
                ?>
                <div class="order-item">
                    <div>
                        <strong><?= htmlspecialchars($product_name) ?></strong>
                        <div style="font-size: 12px; color: #666;">Farmer: <?= htmlspecialchars($farmer_name) ?></div>
                        <?php if(isset($order['delivery_address'])): ?>
                            <div style="font-size: 12px; color: #666;">To: <?= htmlspecialchars($order['delivery_address']) ?></div>
                        <?php endif; ?>
                        <div style="font-size: 10px; color: #999;">Order ID: <?= $order_id ?></div>
                    </div>
                    <div><?= number_format($quantity, 2) ?></div>
                    <div>LKR <?= number_format($unit_price, 2) ?></div>
                    <div><strong>LKR <?= number_format($total_amount, 2) ?></strong></div>
                    <div>
                        <?= date('M d, Y', $order_date->toDateTime()->getTimestamp()) ?>
                        <div style="font-size: 12px; color: #666;">
                            <?= date('h:i A', $order_date->toDateTime()->getTimestamp()) ?>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <div class="status <?= $status ?>"><?= ucfirst($status) ?></div>
                        <div style="margin-top: 8px; display: flex; gap: 5px; flex-direction: column;">
                            <a href="order_details.php?id=<?= $order_id ?>" class="action-btn view">View Details</a>
                            <?php if($status === 'pending'): ?>
                                <a href="cancel_order.php?id=<?= $order_id ?>" class="action-btn" 
                                   onclick="return confirm('Are you sure you want to cancel this order?')">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No orders found</h3>
                    <p>
                        <?php if($statusFilter !== 'all'): ?>
                            You don't have any <strong><?= $statusFilter ?></strong> orders.
                        <?php else: ?>
                            You haven't placed any orders yet.
                        <?php endif; ?>
                    </p>
                    <p><a href="products_marketplace.php">Browse products and place your first order →</a></p>
                    
                    <?php if($statusFilter === 'completed'): ?>
                        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <h4>Why no completed orders?</h4>
                            <p style="font-size: 14px; text-align: left;">
                                Completed orders appear when farmers mark your orders as delivered and completed.<br>
                                Make sure your orders are being accepted and delivered by farmers.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>