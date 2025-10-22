<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.php");
    exit();
}

// Check if product ID is provided and valid
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Product ID is required");
}

$product_id = $_GET['id'];
$productsCollection = $db->products;
$usersCollection = $db->users;

try {
    // Convert string ID to MongoDB ObjectId
    $product = $productsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($product_id)]);
    
    if (!$product) {
        die("Product not found");
    }
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
    <title><?= htmlspecialchars($product['name'] ?? 'Product Details') ?> - DMAS</title>
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


        
        .product-details { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .product-image img { width: 100%; border-radius: 8px; }
        .product-info h1 { margin-top: 0; color: #2c3e50; }
        .price { font-size: 2rem; color: #27ae60; font-weight: bold; margin: 15px 0; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
        .info-item { padding: 10px; background: #f8f9fa; border-radius: 6px; }
        
        .order-form { margin-top: 20px; }
        .order-form input, .order-form button { padding: 10px; margin: 5px 0; width: 100%; border: 1px solid #ddd; border-radius: 6px; }
        .order-form button { background: #27ae60; color: white; border: none; font-weight: bold; cursor: pointer; }
        .order-form button:hover { background: #219150; }
        
        .farmer-card { background: #e8f4fd; padding: 20px; border-radius: 8px; margin-top: 20px; }
        
        @media (max-width: 768px) {
            .product-details { grid-template-columns: 1fr; }
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
        <a href="products_marketplace.php" style="color: #3498db; text-decoration: none; margin-bottom: 20px; display: inline-block;">← Back to Marketplace</a>
        
        <div class="product-details">
            <div class="product-image">
                <img src="../uploads/products/<?= htmlspecialchars($product['image'] ?? 'default.jpg') ?>" 
                     alt="<?= htmlspecialchars($product['name'] ?? 'Product Image') ?>" 
                     onerror="this.src='../uploads/products/default.jpg'">
            </div>
            
            <div class="product-info">
                <h1><?= htmlspecialchars($product['name'] ?? 'Unknown Product') ?></h1>
                <div class="price">LKR <?= number_format($product['price'] ?? 0, 2) ?> / <?= htmlspecialchars($product['unit'] ?? 'unit') ?></div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Category:</strong><br>
                        <?= htmlspecialchars($product['category'] ?? 'Not specified') ?>
                    </div>
                    <div class="info-item">
                        <strong>Quality Grade:</strong><br>
                        <span style="color: 
                            <?= ($product['quality_grade'] ?? 'C') == 'A' ? '#27ae60' : 
                               (($product['quality_grade'] ?? 'C') == 'B' ? '#f39c12' : '#e74c3c') ?>">
                            Grade <?= htmlspecialchars($product['quality_grade'] ?? 'C') ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <strong>Available Quantity:</strong><br>
                        <?= number_format($product['quantity'] ?? 0, 2) ?> <?= htmlspecialchars($product['unit'] ?? 'unit') ?>
                    </div>
                    <div class="info-item">
                        <strong>Harvest Date:</strong><br>
                        <?= isset($product['harvest_date']) ? 
                            date('M d, Y', $product['harvest_date']->toDateTime()->getTimestamp()) : 
                            'Not specified' ?>
                    </div>
                </div>
                
                <?php if(!empty($product['description'])): ?>
                    <div style="margin: 20px 0;">
                        <strong>Description:</strong><br>
                        <?= htmlspecialchars($product['description']) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Order Form -->
                <form class="order-form" action="place_order.php" method="POST">
                    <input type="hidden" name="product_id" value="<?= $product['_id'] ?>">
                    <label for="quantity">Order Quantity (<?= htmlspecialchars($product['unit'] ?? 'unit') ?>):</label>
                    <input type="number" name="quantity" id="quantity" 
                           min="0.1" max="<?= $product['quantity'] ?? 0 ?>" 
                           step="0.1" value="1" required
                           <?= ($product['quantity'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                    
                    <div style="margin: 10px 0; font-weight: bold;">
                        Total: LKR <span id="totalAmount"><?= number_format($product['price'] ?? 0, 2) ?></span>
                    </div>
                    
                    <button type="submit" <?= ($product['quantity'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                        <?= ($product['quantity'] ?? 0) <= 0 ? 'Out of Stock' : 'Place Order' ?>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Farmer Information -->
        <?php if($farmer): ?>
        <div class="farmer-card">
            <h3>Farmer Information</h3>
            <p><strong>Name:</strong> <?= htmlspecialchars($farmer['name'] ?? $product['farmer']) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($farmer['address'] ?? 'Not specified') ?></p>
            <p><strong>Rating:</strong> ⭐ <?= number_format($farmer['rating'] ?? 0, 1) ?>/5.0</p>
            <p><strong>Response Time:</strong> <?= htmlspecialchars($farmer['avg_response_time'] ?? 'Unknown') ?></p>
            <?php if(isset($farmer['phone'])): ?>
                <p><strong>Contact:</strong> <?= htmlspecialchars($farmer['phone']) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Calculate total amount
        const price = <?= $product['price'] ?? 0 ?>;
        const quantityInput = document.getElementById('quantity');
        const totalAmount = document.getElementById('totalAmount');
        
        if (quantityInput && totalAmount) {
            quantityInput.addEventListener('input', function() {
                const quantity = parseFloat(this.value) || 0;
                totalAmount.textContent = (price * quantity).toFixed(2);
            });
        }
    </script>
</body>
</html>