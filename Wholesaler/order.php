<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only wholesaler can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.php");
    exit();
}

$wholesaler = $_SESSION['username'];

// ----------------------------
// Validate Product ID
// ----------------------------
if (!isset($_GET['product_id'])) {
    die("Invalid product!");
}

$product_id = $_GET['product_id'];

// Fetch product from DB
$product = $productsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($product_id)]);
if (!$product) {
    die("Product not found!");
}

// ----------------------------
// Handle Order Submit
// ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = (int)$_POST['quantity'];

    if ($quantity <= 0 || $quantity > $product['quantity']) {
        $error = "Invalid quantity!";
    } else {
        // Generate Order ID: OR001, OR002, etc.
        $lastOrder = $ordersCollection->find([], ['sort' => ['_id' => -1], 'limit' => 1])->toArray();
        if (!empty($lastOrder)) {
            $lastIdNum = (int)substr($lastOrder[0]['order_id'], 2);
            $newOrderId = 'OR' . str_pad($lastIdNum + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newOrderId = 'OR001';
        }

        $orderData = [
            'order_id'     => $newOrderId,
            'product_id'   => $product['_id'],
            'product_name' => $product['name'],
            'farmer'       => $product['farmer'],
            'wholesaler'   => $wholesaler,
            'quantity'     => $quantity,
            'unit_price'   => $product['price'],
            'total'        => $quantity * $product['price'],
            'status'       => 'Pending',
            'date'         => date("Y-m-d H:i:s")
        ];

        $ordersCollection->insertOne($orderData);

        // Update farmer product stock
        $productsCollection->updateOne(
            ['_id' => $product['_id']],
            ['$inc' => ['quantity' => -$quantity]]
        );

        $success = "Order placed successfully! Order ID: {$newOrderId}";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Now</title>
<link rel="stylesheet" href="order.css">
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

<div class="order-container">
    <h1>Order Product</h1>

    <?php if (!empty($success)): ?>
        <p class="success"><?= $success ?></p>
        <a href="browse_products.php">← Back to Products</a>
    <?php else: ?>
        <?php if (!empty($error)): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>

        <div class="product-summary">
            <img src="../Farmer/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" width="150">
            <h2><?= htmlspecialchars($product['name']) ?></h2>
            <p>Farmer: <?= htmlspecialchars($product['farmer']) ?></p>
            <p>Available: <?= htmlspecialchars($product['quantity']) ?> <?= htmlspecialchars($product['unit']) ?></p>
            <p>Price: <?= htmlspecialchars($product['price']) ?> LKR / <?= htmlspecialchars($product['unit']) ?></p>
        </div>

        <form method="POST">
            <label>Quantity:</label>
            <input type="number" name="quantity" min="1" max="<?= $product['quantity'] ?>" required>
            <button type="submit">Confirm Order</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
