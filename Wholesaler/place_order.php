<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.php");
    exit();
}

$wholesaler = $_SESSION['username'];
$productsCollection = $db->products;
$ordersCollection = $db->orders;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $quantity = floatval($_POST['quantity']);

    $product = $productsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($product_id)]);
    if (!$product) {
        die("Product not found");
    }

    if ($quantity > $product['quantity']) {
        die("Not enough quantity available");
    }

    $order = [
        'wholesaler' => $wholesaler,
        'farmer' => $product['farmer'],
        'product_id' => $product_id,
        'product_name' => $product['name'],
        'quantity' => $quantity,
        'unit_price' => $product['price'],
        'total_amount' => $product['price'] * $quantity,
        'status' => 'pending',
        'order_date' => new MongoDB\BSON\UTCDateTime(),
        'delivery_address' => $_POST['delivery_address'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];

    $result = $ordersCollection->insertOne($order);

    if ($result->getInsertedCount() > 0) {
        $productsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($product_id)],
            ['$inc' => ['quantity' => -$quantity]]
        );
        header("Location: order_management.php?success=1");
        exit();
    } else {
        $error = "Failed to place order";
    }
}

$product_id = $_GET['id'] ?? '';
if ($product_id) {
    $product = $productsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($product_id)]);
    if (!$product) {
        die("Product not found");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order - DMAS</title>
    <style>
        /* ===== Global ===== */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(120deg, #f4f9f9, #e8f0f2);
            margin: 0;
            color: #333;
        }

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

        .navbar .nav-right a.active {
         background: #2ecc71;
         color: white;
        }

       .navbar .nav-right a.logout {
        background: #e74c3c;
        }

       /* Make only profile button green */
       .navbar .nav-right a.profile-active {
        background: #2ecc71 !important;
        color: white !important;
}



        /* ===== Container ===== */
        .container {
            margin: 120px auto 50px auto;
            max-width: 600px;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            animation: fadeIn 0.6s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h1 {
            text-align: center;
            color: #1e3d59;
            margin-bottom: 25px;
        }

        .product-summary {
            background: #f0f9ff;
            border-left: 6px solid #2ecc71;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .product-summary h3 {
            color: #1e3d59;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            font-weight: 600;
            color: #555;
            display: block;
            margin-bottom: 6px;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 15px;
            transition: 0.3s;
        }

        input:focus, textarea:focus {
            border-color: #2ecc71;
            outline: none;
            box-shadow: 0 0 8px rgba(46,204,113,0.2);
        }

        button {
            background: #2ecc71;
            color: white;
            border: none;
            padding: 14px;
            width: 100%;
            font-size: 16px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #27ae60;
            transform: translateY(-2px);
        }

        .total {
            background: #f4f8f7;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: 700;
            color: #1e3d59;
            margin-top: 10px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        @media screen and (max-width: 600px) {
            .container { padding: 25px; }
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
    <a href="profile.php" class="profile-active">👤 My Profile</a>
    <a href="../logout.php" class="logout">🚪 Logout</a>
  </div>
</nav>


    <!-- ===== Main Container ===== -->
    <div class="container">
        <h1>🛍️ Place Your Order</h1>

        <?php if(isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if(isset($product)): ?>
            <div class="product-summary">
                <h3><?= htmlspecialchars($product['name']) ?></h3>
                <p><strong>Price:</strong> LKR <?= number_format($product['price'], 2) ?> / <?= htmlspecialchars($product['unit']) ?></p>
                <p><strong>Available:</strong> <?= number_format($product['quantity'], 2) ?> <?= htmlspecialchars($product['unit']) ?></p>
                <p><strong>Farmer:</strong> <?= htmlspecialchars($product['farmer']) ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="order-form">
            <?php if(isset($product)): ?>
                <input type="hidden" name="product_id" value="<?= $product['_id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Quantity (<?= htmlspecialchars($product['unit'] ?? 'units') ?>):</label>
                <input type="number" name="quantity" min="0.1" step="0.1"
                       max="<?= $product['quantity'] ?? '' ?>" value="<?= $_POST['quantity'] ?? '1' ?>" required>
            </div>

            <div class="form-group">
                <label>Delivery Address:</label>
                <textarea name="delivery_address" rows="3" placeholder="Enter delivery location..." required><?= $_POST['delivery_address'] ?? '' ?></textarea>
            </div>

            <div class="form-group">
                <label>Special Instructions (Optional):</label>
                <textarea name="notes" rows="3" placeholder="E.g. Deliver between 9am - 11am"><?= $_POST['notes'] ?? '' ?></textarea>
            </div>

            <div class="total">
                💰 Total Amount: LKR <span id="totalAmount">0.00</span>
            </div>

            <button type="submit">✅ Confirm Order</button>
        </form>
    </div>

    <script>
        const unitPrice = <?= $product['price'] ?? 0 ?>;
        const quantityInput = document.querySelector('input[name="quantity"]');
        const totalAmount = document.getElementById('totalAmount');

        function updateTotal() {
            const q = parseFloat(quantityInput.value) || 0;
            totalAmount.textContent = (unitPrice * q).toFixed(2);
        }

        quantityInput.addEventListener('input', updateTotal);
        updateTotal();
    </script>
</body>
</html>
