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

$wholesaler = $_SESSION['username'];
$productsCollection = $db->products;
$ordersCollection = $db->orders;
$usersCollection = $db->users;


$product_id = $_GET['id'] ?? '';
if (!$product_id) die("Product ID missing");
$product = $productsCollection->findOne(['_id'=>new MongoDB\BSON\ObjectId($product_id)]);
if (!$product) die("Product not found");


$transporters = $usersCollection->find(['role'=>'transporter']);


if($_SERVER['REQUEST_METHOD']==='POST'){
    $quantity = trim($_POST['quantity']); 
    $transporter = $_POST['transporter'];
    $needed_date_raw = $_POST['needed_date'];
    $qtyNumber = floatval($quantity);

    $needed_datetime_str = $needed_date_raw . ' ' . $needed_time_raw;
    $needed_datetime = new MongoDB\BSON\UTCDateTime(strtotime($needed_datetime_str) * 1000);

    if($qtyNumber > $product['quantity']){
        $error = "Not enough quantity. Remaining: {$product['quantity']} {$product['unit']}";
    } else {
        $order = [
            'wholesaler'=>$wholesaler,
            'farmer'=>$product['farmer'],
            'product_id'=>$product_id,
            'product_name'=>$product['name'],
            'quantity'=>$quantity,
            'unit'=>$product['unit'],
            'unit_price'=>$product['price'],
            'total_amount'=>$product['price']*$qtyNumber,
            'transporter'=>$transporter,
            'needed_datetime'=>$needed_datetime,   // store wholesaler request
            'status'=>'pending',
            'order_date'=>new MongoDB\BSON\UTCDateTime()
        ];

        $res = $ordersCollection->insertOne($order);
        if($res->getInsertedCount()>0){
            header("Location: order_management.php?success=1");
            exit();
        } else { 
            $error="Failed to place order"; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/place_order.css">
<title>Place Order - DMAS</title>

</head>
<body>
    <nav class="navbar">
            <div class="brand">🌾 DMAS - Wholesaler Portal</div>
            <div class="menu" id="menu">
                <a href="dashboard.php">🏠 Dashboard</a>
                <a href="product_marketplace.php">🛒 Marketplace</a>
                <a href="order_management.php"class="active">📦 Orders</a>
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
<h2>🛍️ Place Your Order</h2>
<?php if(isset($error)) echo "<div class='error'>".htmlspecialchars($error)."</div>"; ?>

<div class="product-summary">
    <h3><?= htmlspecialchars($product['name']) ?></h3>
    <p>1<?= htmlspecialchars($product['unit']) ?> Price: LKR <?= number_format($product['price'],2) ?></p>
    <p>Available: <?= $product['quantity'] ?> <?= htmlspecialchars($product['unit']) ?></p>
    <p>Farmer: <?= htmlspecialchars($product['farmer']) ?></p>
</div>

<form method="POST">
    <label>Quantity:</label>
    <input type="text" name="quantity" placeholder="Enter quantity (e.g., 2kg)" required>

    <label>Order Needed Date:</label>
    <input type="date" name="needed_date" required>

    <label>Select Transporter:</label>
    <select name="transporter" required>
    <option value="">-- Select Transporter --</option>
    <?php foreach($transporters as $t): ?>
        <option value="<?= htmlspecialchars($t['username']) ?>">
            <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['phone'] ?? '-') ?>)
        </option>
    <?php endforeach; ?>
    </select>


    <div class="total">Total: LKR <span id="totalAmount"><?= number_format($product['price'],2) ?></span></div>
    <button type="submit">Confirm Order</button>
</form>
</div>

<script>
        const unitPrice = <?= $product['price'] ?>;
        const qtyInput = document.querySelector('input[name="quantity"]');
        const totalAmount = document.getElementById('totalAmount');

        function updateTotal(){
            let val = qtyInput.value.trim().toLowerCase();
            let qty = 0;
            if(val.endsWith("kg")) qty = parseFloat(val.replace("kg","")) || 0;
            else if(val.endsWith("g")) qty = (parseFloat(val.replace("g","")) || 0) / 1000;
            else qty = parseFloat(val) || 0;
            totalAmount.textContent = (qty * unitPrice).toFixed(2);
        }
        qtyInput.addEventListener('input', updateTotal);
        updateTotal();

        function toggleMenu() {
        const menu = document.querySelector('.navbar .menu');
        menu.classList.toggle('show');
        }

</script>
</body>
</html>
