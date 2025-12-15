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
$usersCollection = $db->users;

$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$minPrice = floatval($_GET['min_price'] ?? 0);
$maxPrice = floatval($_GET['max_price'] ?? 0);
$qualityFilter = $_GET['quality'] ?? '';


$filter = [];
if ($search !== '') {
    $filter['name'] = ['$regex' => $search, '$options' => 'i'];
}
if ($categoryFilter !== '') {
    $filter['category'] = $categoryFilter;
}
if ($minPrice > 0 || $maxPrice > 0) {
    $priceFilter = [];
    if ($minPrice > 0) $priceFilter['$gte'] = $minPrice;
    if ($maxPrice > 0) $priceFilter['$lte'] = $maxPrice;
    $filter['price'] = $priceFilter;
}
if ($qualityFilter !== '') {
    $filter['quality_grade'] = $qualityFilter;
}

$productsCursor = $productsCollection->find($filter, ['sort'=>['_id'=>-1]]);
$products = iterator_to_array($productsCursor);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Product Marketplace</title>
<link rel="stylesheet" href="assets/marketplace.css">
</head>
<body>
    
      <nav class="navbar">
            <div class="brand">🌾 DMAS - Wholesaler Portal</div>
            <div class="menu" id="menu">
                <a href="dashboard.php">🏠 Dashboard</a>
                <a href="product_marketplace.php"class="active">🛒 Marketplace</a>
                <a href="order_management.php">📦 Orders</a>
                <a href="prediction copy.php">📈 Price Prediction</a>
                <a href="view_workers.php">👨‍🔧 View Workers</a>
                <a href="profile.php">👤 My Profile</a>
                <a href="../logout.php" class="logout" onclick="return confirm('Are you sure you want to logout?');">🚪 Logout</a>
            </div>
            <div class="hamburger" onclick="toggleMenu()">
            <span></span><span></span><span></span>
</div>
     </nav>

<div class="container">
<h1>Product Marketplace</h1>

<form class="filter-form" method="GET">
    <input type="text" name="search" placeholder="Search product..." value="<?= htmlspecialchars($search) ?>">
    <select name="category">
        <option value="">All Categories</option>
        <option value="Vegetables" <?= $categoryFilter=='Vegetables'?'selected':'' ?>>Vegetables</option>
        <option value="Fruits" <?= $categoryFilter=='Fruits'?'selected':'' ?>>Fruits</option>
        <option value="Grains" <?= $categoryFilter=='Grains'?'selected':'' ?>>Grains</option>
        <option value="Other" <?= $categoryFilter=='Other'?'selected':'' ?>>Other</option>
    </select>
    <input type="number" name="min_price" placeholder="Min Price..." value="<?= htmlspecialchars($minPrice) ?>">
    <input type="number" name="max_price" placeholder="Max Price" value="<?= htmlspecialchars($maxPrice) ?>">
    <button type="submit">Filter</button>
</form>

<div class="products-grid">
<?php if(count($products) > 0): ?>
    <?php foreach($products as $product): 
        $farmer = $usersCollection->findOne(['username' => $product['farmer'], 'role'=>'farmer']) ?? [];
        $remaining_qty = $product['quantity'] ?? 0; 
    ?>
    <div class="card">
        <img src="../uploads/products/<?= htmlspecialchars($product['image'] ?? 'default.jpg') ?>" alt="Product Image">
        <div class="card-body">
            <h3><?= htmlspecialchars($product['name'] ?? '-') ?></h3>
            <p><strong>Farmer:</strong> <?= htmlspecialchars($farmer['name'] ?? $product['farmer']) ?></p>
            <p><strong>Unit Price:</strong> <?= htmlspecialchars($product['price'] ?? '-') ?> LKR / <?= htmlspecialchars($product['unit'] ?? '-') ?></p>
            <p><strong>Available:</strong> <?= htmlspecialchars($remaining_qty) ?> <?= htmlspecialchars($product['unit'] ?? '-') ?></p>
            <div class="actions">
                <a href="product_details.php?id=<?= $product['_id'] ?>" class="view">View Details</a>
                <a href="place_order.php?id=<?= $product['_id'] ?>" class="order">Quick Order</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <p style="text-align:center; font-size:16px; width:100%;">No products found.</p>
<?php endif; ?>
</div>

</div>
    <script>
        // Toggle menu
        function toggleMenu(){
        const menu = document.getElementById('menu');
        menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
}
    </script>
</body>
</html>
