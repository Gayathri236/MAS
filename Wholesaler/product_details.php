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

if (!isset($_GET['id']) || empty($_GET['id'])) die("Product ID is required");

$product_id = $_GET['id'];
$productsCollection = $db->products;
$usersCollection = $db->users;

try {
    $product = $productsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($product_id)]);
    if (!$product) die("Product not found");
} catch (Exception $e) {
    die("Invalid product ID format");
}

$farmer = $usersCollection->findOne(['username' => $product['farmer']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($product['name']) ?> - DMAS</title>
<link rel="stylesheet" href="assets/product_details.css">
</head>
<body>
      <nav class="navbar">
            <div class="brand">🌾 DMAS - Wholesaler Portal</div>
            <div class="menu" id="menu">
                <a href="dashboard.php">🏠 Dashboard</a>
                <a href="product_marketplace.php">🛒 Marketplace</a>
                <a href="order_management.php" class="active">📦 Orders</a>
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
    <div class="product-details">
        <div class="product-image">
            <img src="../uploads/products/<?= htmlspecialchars($product['image'] ?? 'default.jpg') ?>" onerror="this.src='../uploads/products/default.jpg'">
        </div>

        <div class="product-info">
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <div class="price">LKR <?= number_format($product['price'], 2) ?></div>

            <div class="info-grid">
                <div class="info-item"><strong>Category:</strong><br><?= htmlspecialchars($product['category']) ?></div>
                <div class="info-item"><strong>Available Quantity:</strong><br><?= number_format($product['quantity'], 2) ?> <?= htmlspecialchars($product['unit']) ?></div>
                <div class="info-item"><strong>Harvest Date:</strong><br><?= date('M d, Y', $product['harvest_date']->toDateTime()->getTimestamp()) ?></div>
            </div>

            <?php if(!empty($product['description'])): ?>
                <p><strong>Description:</strong><br><?= htmlspecialchars($product['description']) ?></p>
            <?php endif; ?>

            <a href="place_order.php?id=<?= $product['_id'] ?>" style="display:inline-block;margin-top:15px;padding:12px 25px;background:#27ae60;color:white;border-radius:6px;text-decoration:none;font-weight:bold;">Place Order</a>
        </div>
    </div>
</div>
    <script>
        function toggleMenu() {
        const menu = document.querySelector('.navbar .menu');
        menu.classList.toggle('show');
       }
    </script>
</body>
</html>
