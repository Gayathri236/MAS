<?php
session_start();
require __DIR__ . '/../mongodb_config.php';
date_default_timezone_set('Asia/Colombo');

// Only farmers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Collections
$usersCollection = $db->users;
$productsCollection = $db->products;
$ordersCollection = $db->orders;

// ---------------- PROFILE ----------------
$farmerProfile = $usersCollection->findOne(['username' => $username, 'role' => 'farmer']);
if (!$farmerProfile) {
    // graceful fallback
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

// Handle actions
if (isset($_GET['action'], $_GET['id'])) {
    $productId = $_GET['id'];
    $action = $_GET['action'];

    try {
        $objId = new MongoDB\BSON\ObjectId($productId);
    } catch (Exception $e) {
        die("Invalid product ID");
    }

    if ($action === 'delete') {
        $productsCollection->deleteOne(['_id' => $objId, 'farmer' => $username]);
    } elseif ($action === 'soldout') {
        $productsCollection->updateOne(
            ['_id' => $objId, 'farmer' => $username],
            ['$set' => ['status' => 'Sold Out']]
        );
    }

    header("Location: my_products.php");
    exit();
}

// Filters
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$filter = ['farmer' => $username];

if ($search !== '') $filter['name'] = ['$regex' => $search, '$options' => 'i'];
if ($statusFilter !== '') $filter['status'] = ['$regex' => '^'.preg_quote($statusFilter,'/').'$','$options'=>'i'];
if ($categoryFilter !== '') $filter['category'] = ['$regex' => '^'.preg_quote($categoryFilter,'/').'$','$options'=>'i'];

$productsCursor = $productsCollection->find($filter, ['sort' => ['_id'=>-1]]);
$products = iterator_to_array($productsCursor);

// Count orders per product
foreach ($products as &$product) {
    $productId = $product['_id']->__toString();
    $product['orders'] = $ordersCollection->countDocuments(['product_id' => $productId]);
}


$totalProducts = count($products);
$activeProducts = 0;
$soldOutProducts = 0;

foreach ($products as $product) {
    $status = strtolower(trim($product['status'] ?? 'active'));
    
    if (strpos($status, 'sold') !== false || strpos($status, 'out') !== false) {
        $soldOutProducts++;
    } elseif (strpos($status, 'active') !== false || empty($status)) {
        $activeProducts++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/my_products.css">
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
        <a href="my_products.php" class="active">
            <i class="fas fa-seedling"></i>
            <span>My Products</span>
        </a>
        <a href="farmer_orders.php">
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
    <h1>My Products</h1>
    
    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <i class="fas fa-box"></i>
            <div class="stat-number"><?= $totalProducts ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <div class="stat-number"><?= $activeProducts ?></div>
            <div class="stat-label">Active Products</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-times-circle"></i>
            <div class="stat-number"><?= $soldOutProducts ?></div>
            <div class="stat-label">Sold Out</div>
        </div>
    </div>

    <!-- Filter Form -->
    <form class="filter-form" method="GET">
        <div>
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#2a9d8f;">Search Products</label>
            <input type="text" name="search" placeholder="Search by product name..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div>
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#2a9d8f;">Status</label>
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?= (strtolower($statusFilter)=='active')?'selected':'' ?>>Active</option>
                <option value="sold" <?= (strtolower($statusFilter)=='sold'||strtolower($statusFilter)=='soldout')?'selected':'' ?>>Sold Out</option>
            </select>
        </div>
        <div>
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#2a9d8f;">Category</label>
            <select name="category">
                <option value="">All Categories</option>
                <option value="Vegetables" <?= ($categoryFilter=='Vegetables')?'selected':'' ?>>Vegetables</option>
                <option value="Fruits" <?= ($categoryFilter=='Fruits')?'selected':'' ?>>Fruits</option>
                <option value="Grains" <?= ($categoryFilter=='Grains')?'selected':'' ?>>Grains</option>
                <option value="Spices" <?= ($categoryFilter=='Spices')?'selected':'' ?>>Spices</option>
                <option value="Herbs" <?= ($categoryFilter=='Herbs')?'selected':'' ?>>Herbs</option>
                <option value="Other" <?= ($categoryFilter=='Other')?'selected':'' ?>>Other</option>
            </select>
        </div>
        <div>
            <button type="submit"><i class="fas fa-filter"></i> Apply Filters</button>
        </div>
    </form>

    <!-- Products Table -->
    <div class="table-container">
        <?php if(count($products) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Orders</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($products as $product): 
                        $status = $product['status'] ?? 'active';
                        $statusLower = strtolower($status);
                        $productId = $product['_id']->__toString();
                        
                        // Determine display status
                        if (strpos($statusLower, 'sold') !== false || strpos($statusLower, 'out') !== false) {
                            $displayStatus = 'Sold Out';
                            $statusClass = 'soldout';
                        } else {
                            $displayStatus = 'Active';
                            $statusClass = 'active';
                        }
                    ?>
                    <tr>
                        <td>
                            <img src="../uploads/products/<?= htmlspecialchars($product['image'] ?? 'default.jpg') ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 onerror="this.src='../uploads/default.png'">
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($product['name']) ?></strong>
                            <?php if(!empty($product['description'])): ?>
                                <div style="font-size:0.85rem;color:#666;margin-top:4px;">
                                    <?= substr(htmlspecialchars($product['description']), 0, 50) ?>...
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="background:#e9ecef;padding:4px 10px;border-radius:15px;font-size:0.85rem;">
                                <?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($product['quantity']) ?></strong>
                            <span style="color:#666;font-size:0.9rem;"><?= htmlspecialchars($product['unit'] ?? '') ?></span>
                        </td>
                        <td>
                            <strong style="color:#2a9d8f;">LKR <?= number_format($product['price'], 2) ?></strong>
                            <div style="font-size:0.85rem;color:#666;">per <?= htmlspecialchars($product['unit'] ?? 'unit') ?></div>
                        </td>
                        <td>
                            <span class="status-badge <?= $statusClass ?>">
                                <?= $displayStatus ?>
                            </span>
                        </td>
                        <td>
                            <div style="text-align:center;">
                                <div style="font-size:1.5rem;font-weight:700;color:#264653;"><?= $product['orders'] ?></div>
                                <div style="font-size:0.8rem;color:#666;">orders</div>
                            </div>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="?action=delete&id=<?= $productId ?>" class="delete" onclick="return confirm('Are you sure you want to delete this product?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                                <?php if($statusClass !== 'soldout'): ?>
                                <a href="?action=soldout&id=<?= $productId ?>" class="soldout">
                                    <i class="fas fa-tag"></i> Mark Sold Out
                                </a>
                                <?php endif; ?>
                                <a href="prediction.php?id=<?= $productId ?>" class="prediction">
                                    <i class="fas fa-chart-line"></i> View Prediction
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No Products Found</h3>
                <p>You haven't added any products yet or no products match your filters.</p>
                <a href="add_product.php" class="add-product-btn">
                    <i class="fas fa-plus"></i> Add Your First Product
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
        <script src="assets/my_products.js"></script>
</body>
</html>