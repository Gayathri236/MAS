<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only farmers can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];

$ordersCollection = $db->orders;
$productsCollection = $db->products;
$usersCollection = $db->users;

// Fetch all orders for the farmer, sorted by date descending
try {
    $ordersCursor = $ordersCollection->find(
        ['farmer' => $username],
        ['sort' => ['order_date' => -1]]
    );
    $orders = iterator_to_array($ordersCursor);
} catch (Exception $e) {
    $orders = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order History - DMAS</title>
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f4f7f9; margin: 0; color: #333; }
.navbar { display: flex; justify-content: space-between; align-items: center; background-color: #3e8e41; color: white; padding: 15px 30px; box-shadow: 0 3px 6px rgba(0,0,0,0.2); position: sticky; top: 0; z-index: 10; }
.nav-left h2 { margin: 0; font-size: 22px; letter-spacing: 0.5px; }
.nav-right a { color: white; text-decoration: none; margin: 0 12px; font-weight: 500; transition: color 0.3s; }
.nav-right a:hover { color: #d9ffd8; }
.logout-btn { background-color: #ff4d4d; padding: 8px 16px; border-radius: 6px; font-weight: bold; }
.logout-btn:hover { background-color: #e60000; }
.container { width: 95%; max-width: 1200px; margin: 20px auto; }
h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }

/* Order Card */
.order-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; display: flex; gap: 20px; align-items: flex-start; border-left: 4px solid #3e8e41; }
.order-image-section { display: flex; flex-direction: column; align-items: center; gap: 10px; min-width: 140px; }
.order-card img { width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid #e0e0e0; }
.image-placeholder { width: 120px; height: 120px; background: #f8f9fa; border: 2px dashed #ddd; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px; text-align: center; flex-direction: column; }
.order-id { font-family: 'Courier New', monospace; font-size: 12px; color: #666; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
.order-details { flex: 1; }
.order-details h3 { margin: 0 0 10px 0; color: #2c3e50; font-size: 18px; }
.detail-row { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 10px; }
.detail-item { flex: 1; min-width: 200px; }
.detail-item strong { color: #555; }
.status { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-top: 10px; text-transform: uppercase; }
.status.pending { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
.status.accepted { background: #d1ecf1; color: #0c5460; border: 1px solid #b8daff; }
.status.completed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.status.rejected { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.price-highlight { color: #27ae60; font-weight: bold; font-size: 16px; }
.category-badge { background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; display: inline-block; margin-left: 8px; }
.empty-state { text-align: center; padding: 60px 20px; color: #7f8c8d; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.empty-state h3 { color: #95a5a6; margin-bottom: 15px; }
@media (max-width: 768px) {
    .order-card { flex-direction: column; text-align: left; }
    .order-image-section { flex-direction: row; justify-content: space-between; width: 100%; }
    .detail-row { flex-direction: column; gap: 10px; }
}
</style>
</head>
<body>
<nav class="navbar">
    <div class="nav-left"><h2>🌾 DMAS Farmer Panel</h2></div>
    <div class="nav-right">
        <a href="farmer_dashboard.php">Dashboard</a>
        <a href="add_product.php">Products</a>
        <a href="farmer_orders.php">Orders</a>
        <a href="order_history.php" style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 6px;">Order History</a>
        <a href="price_intelligence.php">Price Intelligence</a>
        <a href="product_analytics.php">Product Analytics</a>
        <a href="reports.php">Reports</a>
        <a href="profile.php">Profile</a>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<div class="container">
    <h1>📜 Order History</h1>

    <?php if(count($orders) > 0): ?>
        <?php foreach($orders as $order):
            $order_id = $order['_id'];
            $product_id = $order['product_id'] ?? null;
            $wholesaler_username = $order['wholesaler'] ?? 'Unknown Wholesaler';
            $quantity = $order['quantity'] ?? 0;
            $unit_price = $order['unit_price'] ?? 0;
            $total_amount = $order['total_amount'] ?? ($quantity * $unit_price);
            $status = $order['status'] ?? 'pending';
            $order_date = $order['order_date'] ?? new MongoDB\BSON\UTCDateTime();

            // Product info
            $product_image = 'default.jpg';
            $product_name = 'Unknown Product';
            $product_category = 'Not specified';
            $product_unit = 'units';
            if ($product_id) {
                try {
                    if (!($product_id instanceof MongoDB\BSON\ObjectId)) {
                        $product_id = new MongoDB\BSON\ObjectId($product_id);
                    }
                    $product = $productsCollection->findOne(['_id' => $product_id]);
                    if ($product) {
                        $product_image = $product['image'] ?? 'default.jpg';
                        $product_name = $product['name'] ?? 'Unknown Product';
                        $product_category = $product['category'] ?? 'Not specified';
                        $product_unit = $product['unit'] ?? 'units';
                    }
                } catch (Exception $e) {}
            } else {
                $product_name = $order['product_name'] ?? 'Unknown Product';
                $product_category = $order['product_category'] ?? 'Not specified';
                $product_unit = $order['unit'] ?? 'units';
            }

            $image_path = "../uploads/products/" . $product_image;

            // Wholesaler info
            $wholesaler = $usersCollection->findOne(['username' => $wholesaler_username]) ?? [];
            $wholesaler_name = $wholesaler['name'] ?? $wholesaler_username;

            $order_date_formatted = date('d M Y, H:i', $order_date->toDateTime()->getTimestamp());
        ?>
        <div class="order-card">
            <div class="order-image-section">
                <img src="<?= htmlspecialchars($image_path) ?>" alt="<?= htmlspecialchars($product_name) ?>"
                     onerror="this.style.display='none'; document.getElementById('placeholder-<?= $order_id ?>').style.display='flex';">
                <div class="image-placeholder" id="placeholder-<?= $order_id ?>" style="display: none;">
                    <span>📷</span>
                    <span>No Image</span>
                </div>
                <div class="order-id">ID: <?= $order_id ?></div>
            </div>
            <div class="order-details">
                <h3><?= htmlspecialchars($product_name) ?><span class="category-badge"><?= htmlspecialchars($product_category) ?></span></h3>
                <div class="detail-row">
                    <div class="detail-item"><strong>Wholesaler:</strong> <?= htmlspecialchars($wholesaler_name) ?></div>
                    <div class="detail-item"><strong>Order Date:</strong> <?= $order_date_formatted ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-item"><strong>Quantity:</strong> <?= number_format($quantity,2) ?> <?= htmlspecialchars($product_unit) ?></div>
                    <div class="detail-item"><strong>Unit Price:</strong> LKR <?= number_format($unit_price,2) ?></div>
                    <div class="detail-item"><strong>Total Price:</strong> <span class="price-highlight">LKR <?= number_format($total_amount,2) ?></span></div>
                </div>
                <span class="status <?= htmlspecialchars($status) ?>">Status: <?= ucfirst($status) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <h3>No order history yet</h3>
            <p>Once orders are completed, rejected, or accepted, they will appear here.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.order-card img').forEach(img => {
        img.addEventListener('error', function() {
            this.style.display = 'none';
            const placeholder = this.nextElementSibling;
            if(placeholder) placeholder.style.display = 'flex';
        });
    });
});
</script>
</body>
</html>
