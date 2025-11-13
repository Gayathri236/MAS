<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// ✅ Only farmers can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];

$ordersCollection = $db->orders;
$productsCollection = $db->products;
$usersCollection = $db->users;

// ✅ Handle order accept
if (isset($_GET['accept_order'])) {
    $order_id = $_GET['accept_order'];
    try {
        $order = $ordersCollection->findOne([
            '_id' => new MongoDB\BSON\ObjectId($order_id),
            'farmer' => $username
        ]);

        if ($order) {
            // ✅ Update order status
            $ordersCollection->updateOne(
                ['_id' => $order['_id']],
                [
                    '$set' => [
                        'status' => 'accepted',
                        'accepted_date' => new MongoDB\BSON\UTCDateTime(),
                        'accepted_by' => $username
                    ]
                ]
            );

            // ✅ Save accepted order in farmer user record
            $usersCollection->updateOne(
                ['username' => $username],
                ['$addToSet' => ['accepted_orders' => (string)$order['_id']]]
            );
        }

        header("Location: farmer_orders.php?success=2");
        exit();
    } catch (Exception $e) {
        header("Location: farmer_orders.php?error=2");
        exit();
    }
}

// ✅ Handle order reject
if (isset($_GET['reject_order'])) {
    $order_id = $_GET['reject_order'];
    try {
        $ordersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($order_id), 'farmer' => $username],
            ['$set' => ['status' => 'rejected']]
        );
        header("Location: farmer_orders.php?success=3");
        exit();
    } catch (Exception $e) {
        header("Location: farmer_orders.php?error=3");
        exit();
    }
}

// ✅ Fetch all farmer orders
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
<title>Farmer Orders - DMAS</title>
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f4f7f9; margin: 0; color: #333; }

/* Navbar */
.navbar {
    display: flex; justify-content: space-between; align-items: center;
    background-color: #3e8e41; color: white; padding: 15px 30px;
    box-shadow: 0 3px 6px rgba(0,0,0,0.2);
}
.nav-left h2 { margin:0; font-size: 22px; }
.nav-right a { color:white; text-decoration:none; margin:0 12px; font-weight:500; }
.nav-right a:hover { color: #d9ffd8; }
.logout-btn { background-color: #ff4d4d; padding:8px 16px; border-radius:6px; font-weight:bold; }
.logout-btn:hover { background-color:#e60000; }

/* Container */
.container { width:95%; max-width:1200px; margin:20px auto; }
h1 { text-align:center; margin-bottom:30px; color:#2c3e50; }

/* Order Card */
.order-card {
    background:#fff; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05);
    padding:20px; margin-bottom:20px; display:flex; gap:20px; align-items:flex-start;
    border-left:4px solid #3e8e41;
}
.order-image-section { display:flex; flex-direction:column; align-items:center; gap:10px; min-width:140px; }
.order-card img { width:120px; height:120px; object-fit:cover; border-radius:8px; border:2px solid #e0e0e0; }
.image-placeholder { width:120px; height:120px; background:#f8f9fa; border:2px dashed #ddd; border-radius:8px;
    display:flex; align-items:center; justify-content:center; color:#999; font-size:12px; flex-direction:column; }
.order-id { font-family:'Courier New', monospace; font-size:12px; color:#666; background:#f8f9fa; padding:4px 8px; border-radius:4px; font-weight:bold; }

.order-details { flex:1; }
.order-details h3 { margin:0 0 10px 0; color:#2c3e50; font-size:18px; }
.order-details p { margin:5px 0; font-size:14px; }
.detail-row { display:flex; flex-wrap:wrap; gap:20px; margin-bottom:10px; }
.detail-item { flex:1; min-width:200px; }
.detail-item strong { color:#555; }

.status { display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:bold; margin-top:10px; text-transform:uppercase; }
.status.pending { background:#fff3cd; color:#856404; border:1px solid #ffeaa7; }
.status.accepted { background:#d1ecf1; color:#0c5460; border:1px solid #b8daff; }
.status.completed { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.status.rejected { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

.actions { display:flex; flex-direction:column; gap:10px; min-width:160px; }
.actions button { padding:10px 16px; border:none; border-radius:6px; cursor:pointer; font-weight:bold; transition: all 0.3s; font-size:12px; }
.actions .accept { background:#3498db; color:white; }
.actions .accept:hover { background:#2980b9; }
.actions .reject { background:#e74c3c; color:white; }
.actions .reject:hover { background:#c0392b; }
.actions .contact { background:#25D366; color:white; }
.actions .contact:hover { background:#128C7E; }
.actions a { text-decoration:none; }

.empty-state { text-align:center; padding:60px 20px; color:#7f8c8d; background:white; border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.05); }
.empty-state h3 { color:#95a5a6; margin-bottom:15px; }

.success-message { background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #27ae60; }
.error-message { background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #e74c3c; }
.price-highlight { color:#27ae60; font-weight:bold; font-size:16px; }
.category-badge { background:#e3f2fd; color:#1976d2; padding:4px 8px; border-radius:12px; font-size:12px; font-weight:500; margin-left:8px; }
</style>
</head>
<body>
<nav class="navbar">
    <div class="nav-left"><h2>🌾 DMAS Farmer Panel</h2></div>
    <div class="nav-right">
        <a href="farmer_dashboard.php">Dashboard</a>
        <a href="add_product.php">Products</a>
        <a href="farmer_orders.php" style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 6px;">Orders</a>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<div class="container">
<h1>📦 Received Orders</h1>

<?php if(isset($_GET['success'])): ?>
    <div class="success-message">
        <?php 
        switch($_GET['success']) {
            case 2: echo "✅ Order accepted successfully and saved in database!"; break;
            case 3: echo "✅ Order rejected successfully!"; break;
            default: echo "✅ Action completed!";
        }
        ?>
    </div>
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
    <div class="error-message">
        ❌ Failed to process the order. Please try again.
    </div>
<?php endif; ?>

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
                }
            } catch (Exception $e) {}
        }

        $image_path = "../uploads/products/" . $product_image;

        // Wholesaler info
        $wholesaler = $usersCollection->findOne(['username' => $wholesaler_username]) ?? [];
        $wholesaler_name = $wholesaler['name'] ?? $wholesaler_username;
        $wholesaler_contact = $wholesaler['phone'] ?? $wholesaler['contact'] ?? '';

        $order_date_formatted = date('d M Y, H:i', $order_date->toDateTime()->getTimestamp());
    ?>
    <div class="order-card">
        <div class="order-image-section">
            <img src="<?= htmlspecialchars($image_path) ?>" alt="<?= htmlspecialchars($product_name) ?>" 
                 onerror="this.style.display='none'; document.getElementById('placeholder-<?= $order_id ?>').style.display='flex';">
            <div class="image-placeholder" id="placeholder-<?= $order_id ?>" style="display: none;">
                <span>📷</span><span>No Image</span>
            </div>
            <div class="order-id">ID: <?= $order_id ?></div>
        </div>

        <div class="order-details">
            <h3><?= htmlspecialchars($product_name) ?>
                <span class="category-badge"><?= htmlspecialchars($product_category) ?></span>
            </h3>
            <div class="detail-row">
                <div class="detail-item"><strong>Wholesaler:</strong> <?= htmlspecialchars($wholesaler_name) ?></div>
                <div class="detail-item"><strong>Order Date:</strong> <?= $order_date_formatted ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-item"><strong>Quantity:</strong> <?= $quantity ?></div>
                <div class="detail-item"><strong>Unit Price:</strong> LKR <?= number_format($unit_price, 2) ?></div>
                <div class="detail-item"><strong>Total:</strong> <span class="price-highlight">LKR <?= number_format($total_amount, 2) ?></span></div>
            </div>
            <span class="status <?= htmlspecialchars($status) ?>">Status: <?= ucfirst($status) ?></span>
        </div>

        <div class="actions">
            <?php if($status === 'pending'): ?>
                <a href="?accept_order=<?= $order_id ?>"><button class="accept">✅ Accept Order</button></a>
                <a href="?reject_order=<?= $order_id ?>"><button class="reject">❌ Reject</button></a>
            <?php endif; ?>
            <?php if(!empty($wholesaler_contact)): 
                $wa_number = preg_replace('/[^0-9]/', '', $wholesaler_contact);
                if ($wa_number): $wa_link = "https://wa.me/$wa_number"; ?>
                    <a href="<?= $wa_link ?>" target="_blank"><button class="contact">💬 WhatsApp</button></a>
            <?php endif; endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state">
        <h3>No orders received yet</h3>
        <p>Orders placed by wholesalers will appear here once received.</p>
    </div>
<?php endif; ?>
</div>
</body>
</html>
