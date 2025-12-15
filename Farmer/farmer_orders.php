<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Colombo');

require __DIR__ . '/../mongodb_config.php';

// ------------------------- ONLY FARMERS ALLOWED -------------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Collections
$ordersCollection   = $db->orders;
$productsCollection = $db->products;
$usersCollection    = $db->users;

// ------------------------- PROFILE -------------------------
$farmerProfile = $usersCollection->findOne(['username' => $username, 'role' => 'farmer']);
if (!$farmerProfile) {
    $farmerProfile = [
        'username' => $username,
        'name'     => $username,
        'phone'    => 'N/A',
        'location'  => 'N/A',
        'photo'    => 'default_profile.png'
    ];
}
$displayUsername = htmlspecialchars($farmerProfile['username'] ?? $username);
$displayName     = htmlspecialchars($farmerProfile['name'] ?? 'N/A');
$displayPhone    = htmlspecialchars($farmerProfile['phone'] ?? 'N/A');
$displayAddress  = htmlspecialchars($farmerProfile['location'] ?? ($farmerProfile['location'] ?? 'N/A'));
$displayImage = htmlspecialchars($farmerProfile['image'] ?? 'default.png');  


$AVG_SPEED_KMH = 40; 
$farmer_distance_km = isset($farmerProfile['distance_to_market_km']) ? floatval($farmerProfile['distance_to_market_km']) : 10;

function to_colombo_datetime($mongoDate) {
    if ($mongoDate instanceof MongoDB\BSON\UTCDateTime) {
        $dt = $mongoDate->toDateTime();
        $dt->setTimezone(new DateTimeZone('Asia/Colombo'));
        return $dt;
    } elseif ($mongoDate instanceof DateTime) {
        $mongoDate->setTimezone(new DateTimeZone('Asia/Colombo'));
        return $mongoDate;
    } elseif (is_numeric($mongoDate)) {
        $dt = new DateTime('@' . ($mongoDate / 1000));
        $dt->setTimezone(new DateTimeZone('Asia/Colombo'));
        return $dt;
    }
    return null;
}


function get_remaining_stock_after_order($productsCollection, $product_id, $order_quantity, $status) {
    $product = $productsCollection->findOne(['_id' => $product_id]);
    if (!$product) return 0;
    
    $current_stock = (int)($product['quantity'] ?? 0);
    
    if ($status === 'accepted' || $status === 'in_transit' || $status === 'delivered') {
        return $current_stock;
    } else {
        $remaining_stock = $current_stock - $order_quantity;
        return max(0, $remaining_stock); 
    }
}

// ------------------------- HANDLE ORDER ACCEPT -------------------------
if (isset($_GET['accept_order'])) {
    $order_id = $_GET['accept_order'];
    try {
        $orderObjId = new MongoDB\BSON\ObjectId($order_id);
        $orderObj = $ordersCollection->findOne(['_id' => $orderObjId, 'farmer' => $username]);

        if ($orderObj) {
            $product_id = $orderObj['product_id'] ?? null;
            $order_quantity = (int)($orderObj['quantity'] ?? 0);
            
            // Check if product still exists
            if ($product_id) {
                if(!($product_id instanceof MongoDB\BSON\ObjectId)){
                    try { $product_id = new MongoDB\BSON\ObjectId($product_id); } catch(Exception $e){}
                }
                
                $product = $productsCollection->findOne(['_id' => $product_id]);
                if (!$product) {
                    header("Location: farmer_orders.php?error=product_not_found");
                    exit();
                }
                
                // Check current stock BEFORE accepting
                $current_stock = (int)($product['quantity'] ?? 0);
                
                if ($current_stock < $order_quantity) {
                    header("Location: farmer_orders.php?error=insufficient_stock");
                    exit();
                }
            }

            // Wholesaler requested date
            $needed_dt = to_colombo_datetime($orderObj['needed_datetime']);
            if (!$needed_dt) {
                $needed_dt = new DateTime('tomorrow 06:00', new DateTimeZone('Asia/Colombo'));
            }

            // Compute travel time (pickup time)
            $travel_time_min = ceil($farmer_distance_km / $AVG_SPEED_KMH * 60);
            $pickup_dt = clone $needed_dt;
            $pickup_dt->modify('-' . $travel_time_min . ' minutes');

            // Update order in DB
            $ordersCollection->updateOne(
                ['_id' => $orderObj['_id']],
                ['$set' => [
                    'status' => 'accepted',
                    'accepted_date' => new MongoDB\BSON\UTCDateTime(),
                    'accepted_by' => $username,
                    'pickup_datetime' => new MongoDB\BSON\UTCDateTime($pickup_dt->getTimestamp()*1000),
                    'market_arrival_datetime' => new MongoDB\BSON\UTCDateTime($needed_dt->getTimestamp()*1000)
                ]]
            );

            // DEDUCT PRODUCT STOCK - This is where stock actually reduces
            if ($product_id && $order_quantity > 0) {
                $productsCollection->updateOne(
                    ['_id' => $product_id],
                    ['$inc' => ['quantity' => -1 * $order_quantity]]
                );
            }

            // Add order to farmer accepted_orders
            $usersCollection->updateOne(
                ['username' => $username],
                ['$addToSet' => ['accepted_orders' => (string)$orderObj['_id']]]
            );
        }

        header("Location: farmer_orders.php?success=accepted");
        exit();
    } catch (Exception $e) {
        error_log("Accept order error: " . $e->getMessage());
        header("Location: farmer_orders.php?error=accept");
        exit();
    }
}

// ------------------------- HANDLE ORDER REJECT -------------------------
if (isset($_GET['reject_order'])) {
    $order_id = $_GET['reject_order'];
    try {
        $orderObjId = new MongoDB\BSON\ObjectId($order_id);
        $ordersCollection->updateOne(
            ['_id' => $orderObjId, 'farmer' => $username],
            ['$set' => [
                'status' => 'rejected',
                'rejected_date' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        header("Location: farmer_orders.php?success=rejected");
        exit();
    } catch (Exception $e) {
        error_log("Reject order error: " . $e->getMessage());
        header("Location: farmer_orders.php?error=reject");
        exit();
    }
}

// ------------------------- FETCH ORDERS -------------------------
$ordersCursor = $ordersCollection->find(
    ['farmer' => $username],
    ['sort' => ['order_date' => -1]]
);
$orders = iterator_to_array($ordersCursor);

// Get order statistics
$totalOrders = count($orders);
$pendingOrders = $ordersCollection->countDocuments(['farmer' => $username, 'status' => 'pending']);
$acceptedOrders = $ordersCollection->countDocuments(['farmer' => $username, 'status' => 'accepted']);
$rejectedOrders = $ordersCollection->countDocuments(['farmer' => $username, 'status' => 'rejected']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/farmer_orders.css">
</head>
<body>

<div class="sidebar">
    <div class="profile-section">
         <img src="../uploads/<?= htmlspecialchars($displayImage) ?>" alt="Profile" class="profile-img" onerror="this.src='../uploads/default.png'; this.onerror=null;">
        <h3><?= $displayName ?></h3>
        <p><?= $username ?></p>
        <p><i class="fas fa-map-marker-alt"></i> <?= $displayAddress ?></p>
    </div>
    
    <div class="nav-links">
        <a href="farmer_dashboard.php">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="add_product.php">
            <i class="fas fa-plus-circle"></i>
            <span>Add Product</span>
        </a>
        <a href="my_products.php">
            <i class="fas fa-seedling"></i>
            <span>My Products</span>
        </a>
        <a href="farmer_orders.php" class="active">
            <i class="fas fa-shopping-cart"></i>
            <span>Orders</span>
        </a>
        <a href="prediction.php">
            <i class="fas fa-chart-line"></i>
            <span>Prediction</span>
        </a>
        <a href="report.php">
            <i class="fas fa-file-export"></i>
            <span>Reports</span>
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
    <h1>📦 Order Management</h1>
    
    <?php if(isset($_GET['success'])): ?>
        <div class="message success-message">
            <i class="fas fa-check-circle"></i>
            <?php if($_GET['success']==='accepted'): ?>
                Order accepted successfully! Stock has been updated.
            <?php elseif($_GET['success']==='rejected'): ?>
                Order rejected successfully.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
        <div class="message error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php if($_GET['error']==='insufficient_stock'): ?>
                Cannot accept order: Not enough stock available.
            <?php elseif($_GET['error']==='product_not_found'): ?>
                Cannot accept order: Product no longer exists.
            <?php else: ?>
                Error processing your request. Please try again.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="stat-card" onclick="filterOrders('all')">
            <i class="fas fa-shopping-bag"></i>
            <div class="stat-number"><?= $totalOrders ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card" onclick="filterOrders('pending')">
            <i class="fas fa-clock"></i>
            <div class="stat-number"><?= $pendingOrders ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card" onclick="filterOrders('accepted')">
            <i class="fas fa-check-circle"></i>
            <div class="stat-number"><?= $acceptedOrders ?></div>
            <div class="stat-label">Accepted</div>
        </div>
        <div class="stat-card" onclick="filterOrders('rejected')">
            <i class="fas fa-times-circle"></i>
            <div class="stat-number"><?= $rejectedOrders ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- Orders Container -->
    <div class="orders-container">
        <?php if(count($orders) === 0): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No Orders Yet</h3>
                <p>You haven't received any orders yet. When wholesalers place orders for your products, they will appear here.</p>
                <p>Make sure your products are listed and have sufficient stock.</p>
            </div>
        <?php else: ?>
            <?php foreach($orders as $order):
                $order_id = (string) ($order['_id'] ?? $order['order_id'] ?? '');
                $product_id = $order['product_id'] ?? null;
                $wholesaler_username = $order['wholesaler'] ?? '';
                $quantity = (int)($order['quantity'] ?? 0);
                $unit_price = $order['unit_price'] ?? ($order['price'] ?? 0);
                $total_amount = $order['total_amount'] ?? ($quantity * $unit_price);
                $status = strtolower($order['status'] ?? 'pending');
                
                // Skip completed orders
                if ($status === 'completed') continue;
                
                // Product details
                $product_name = 'Unknown';
                $product_image = '';
                $product_unit = $order['unit'] ?? '';
                $remaining_stock = 0;
                
                if($product_id) {
                    try {
                        if(!($product_id instanceof MongoDB\BSON\ObjectId)) {
                            try { $product_id = new MongoDB\BSON\ObjectId($product_id); } catch(Exception $e) {}
                        }
                        $productDoc = $productsCollection->findOne(['_id' => $product_id]);
                        if($productDoc) {
                            $product_name = $productDoc['name'] ?? $product_name;
                            $product_image = $productDoc['image'] ?? '';
                            $product_unit = $productDoc['unit'] ?? $product_unit;
                            
                            // Calculate remaining stock after this order
                            $remaining_stock = get_remaining_stock_after_order(
                                $productsCollection, 
                                $product_id, 
                                $quantity, 
                                $status
                            );
                        } else {
                            $product_name = $order['product_name'] ?? $product_name;
                        }
                    } catch(Exception $e) {
                        $product_name = $order['product_name'] ?? $product_name;
                    }
                } else {
                    $product_name = $order['product_name'] ?? $product_name;
                }
                
                // Wholesaler details
                $wholesalerDoc = $usersCollection->findOne(['username' => $wholesaler_username]) ?? [];
                $wholesaler_name = $wholesalerDoc['name'] ?? $wholesaler_username;
                $wholesaler_contact = $wholesalerDoc['phone'] ?? $wholesalerDoc['contact'] ?? '';
                
                // Pickup & Market time with NULL checking
                $pickup_datetime_display = '-';
                $market_datetime_display = '-';
                if (!empty($order['pickup_datetime'])) {
                    $pickup_dt = to_colombo_datetime($order['pickup_datetime']);
                    if ($pickup_dt instanceof DateTime) {
                        $pickup_datetime_display = $pickup_dt->format('d M Y, h:i A');
                    }
                }
                if (!empty($order['market_arrival_datetime'])) {
                    $market_dt = to_colombo_datetime($order['market_arrival_datetime']);
                    if ($market_dt instanceof DateTime) {
                        $market_datetime_display = $market_dt->format('d M Y, h:i A');
                    }
                }
                
                // WhatsApp link
                $wa_link = '';
                if(!empty($wholesaler_contact)) {
                    $onlynums = preg_replace('/[^0-9]/', '', $wholesaler_contact);
                    if($onlynums) {
                        $wa_link = "https://wa.me/{$onlynums}";
                    }
                }
                
                // Order date with NULL checking
                $order_date_display = '-';
                if (!empty($order['order_date'])) {
                    $order_dt = to_colombo_datetime($order['order_date']);
                    if ($order_dt instanceof DateTime) {
                        $order_date_display = $order_dt->format('d M Y, h:i A');
                    }
                } elseif (!empty($order['created_at'])) {
                    $order_dt = to_colombo_datetime($order['created_at']);
                    if ($order_dt instanceof DateTime) {
                        $order_date_display = $order_dt->format('d M Y, h:i A');
                    }
                }
            ?>
            
            <div class="order-card <?= $status ?>" data-status="<?= $status ?>">
                <div class="order-image">
                    <?php if(!empty($product_image)): ?>
                        <img src="<?= htmlspecialchars("../uploads/products/{$product_image}") ?>" 
                             alt="<?= htmlspecialchars($product_name) ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="image-placeholder" style="display:none;">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php else: ?>
                        <div class="image-placeholder">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="order-details">
                    <div class="order-header">
                        <h3><?= htmlspecialchars($product_name) ?></h3>
                        <span class="status-badge status-<?= $status ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </div>
                    
                    <div class="order-info">
                        <div class="info-item">
                            <span class="info-label">Order ID</span>
                            <span class="info-value"><?= htmlspecialchars(substr($order_id, -8)) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Wholesaler</span>
                            <span class="info-value"><?= htmlspecialchars($wholesaler_name) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Order Quantity</span>
                            <?php 
                            $quantity_value = $quantity;
                            $unit_value = htmlspecialchars($product_unit);
                            $quantity_display = $quantity_value;
                            if (!empty($unit_value) && !preg_match('/\s*' . preg_quote($unit_value, '/') . '\s*$/i', strval($quantity_value))) {
                                $quantity_display .= ' ' . $unit_value;
                            }
                            ?>
                            <span class="info-value"><?= $quantity_display ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">
                                <?php if($status === 'pending'): ?>
                                    Stock After Accepting
                                <?php else: ?>
                                    Current Stock
                                <?php endif; ?>
                            </span>
                            <?php 
                            $stock_display = $remaining_stock;
                            if (!empty($unit_value) && !preg_match('/\s*' . preg_quote($unit_value, '/') . '\s*$/i', strval($remaining_stock))) {
                                $stock_display .= ' ' . $unit_value;
                            }
                            
                            // Show appropriate styling
                            if ($status === 'pending') {
                                $stock_class = ($remaining_stock < 0) ? 'stock-warning' : 'stock-info';
                            } else {
                                $stock_class = 'stock-info';
                            }
                            ?>
                            <span class="info-value <?= $stock_class ?>">
                                <?= $stock_display ?>
                                <?php if($status === 'pending' && $remaining_stock < 0): ?>
                                    <br><small style="color: #e74c3c;">(Will go negative!)</small>
                                <?php elseif($status === 'pending' && $remaining_stock == 0): ?>
                                    <br><small style="color: #f39c12;">(Will be out of stock)</small>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Order Date</span>
                            <span class="info-value"><?= $order_date_display ?></span>
                        </div>
                    </div>
                    
                    <div class="time-info">
                        <?php if($pickup_datetime_display !== '-'): ?>
                            <div class="time-item">
                                <i class="fas fa-truck-pickup"></i>
                                <span><strong>Pickup Time:</strong> <?= $pickup_datetime_display ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if($market_datetime_display !== '-'): ?>
                            <div class="time-item">
                                <i class="fas fa-store"></i>
                                <span><strong>Market Arrival:</strong> <?= $market_datetime_display ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="order-actions">
                    <?php if($status === 'pending'): ?>
                        <?php if($remaining_stock >= 0): ?>
                            <a href="?accept_order=<?= htmlspecialchars($order_id) ?>" class="action-btn btn-accept">
                                <i class="fas fa-check"></i> Accept Order
                            </a>
                        <?php else: ?>
                            <button class="action-btn btn-disabled" title="Accepting this order would make stock negative">
                                <i class="fas fa-check"></i> Cannot Accept
                            </button>
                        <?php endif; ?>
                        <a href="?reject_order=<?= htmlspecialchars($order_id) ?>" class="action-btn btn-reject" onclick="return confirm('Are you sure you want to reject this order?')">
                            <i class="fas fa-times"></i> Reject Order
                        </a>
                    <?php else: ?>
                        <button class="action-btn btn-disabled">
                            <i class="fas fa-check-double"></i> <?= ucfirst($status) ?>
                        </button>
                    <?php endif; ?>
                    
                    <?php if($wa_link): ?>
                        <a href="<?= htmlspecialchars($wa_link) ?>" target="_blank" class="action-btn btn-whatsapp">
                            <i class="fab fa-whatsapp"></i> Contact
                        </a>
                    <?php else: ?>
                        <button class="action-btn btn-disabled">
                            <i class="fas fa-phone"></i> No Contact
                        </button>
                    <?php endif; ?>
                    
                    <div style="text-align: center; margin-top: 10px;">
                        <span class="info-value amount">LKR <?= number_format($total_amount, 2) ?></span>
                        <?php if(!empty($unit_value)): ?>
                            <div style="font-size: 0.8rem; color: #666;">
                                per <?= $unit_value ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
    <script src="assets/farmer_orders.js"></script>
</body>
</html>