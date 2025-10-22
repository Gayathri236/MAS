<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only wholesalers can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.php");
    exit();
}

$wholesaler = $_SESSION['username'];
$productsCollection = $db->products;
$usersCollection = $db->users;

// ---------------- Search & Filters ----------------
$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$minPrice = floatval($_GET['min_price'] ?? 0);
$maxPrice = floatval($_GET['max_price'] ?? 0);
$qualityFilter = $_GET['quality'] ?? '';

// Build filter
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

// Fetch products
$productsCursor = $productsCollection->find($filter, ['sort'=>['_id'=>-1]]);
$products = iterator_to_array($productsCursor);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Product Marketplace</title>
<style>
body { font-family:'Segoe UI',sans-serif; background:#f4f7f9; margin:0; color:#333; }
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

.container { width:95%; max-width:1200px; margin:20px auto; }
h1 { text-align:center; color:#2c3e50; margin-bottom:20px; }

/* Filters */
.filter-form { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; justify-content:center; }
.filter-form input, .filter-form select { padding:8px 12px; border:1px solid #ccc; border-radius:6px; }
.filter-form button { padding:8px 16px; background:#27ae60; color:#fff; border:none; border-radius:6px; cursor:pointer; }
.filter-form button:hover { background:#219150; }

/* Product Cards */
.products-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:20px; }
.card { background:#fff; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05); overflow:hidden; display:flex; flex-direction:column; }
.card img { width:100%; height:180px; object-fit:cover; }
.card-body { padding:15px; flex:1; display:flex; flex-direction:column; justify-content:space-between; }
.card-body h3 { margin:0 0 5px 0; font-size:18px; color:#2c3e50; }
.card-body p { margin:4px 0; font-size:14px; color:#555; }
.card-body .actions { margin-top:10px; display:flex; gap:10px; }
.card-body .actions a { flex:1; text-align:center; padding:8px 12px; border-radius:6px; color:#fff; text-decoration:none; font-weight:bold; transition:0.3s; }
.card-body .view { background:#3498db; } .card-body .view:hover { background:#2980b9; }
.card-body .order { background:#27ae60; } .card-body .order:hover { background:#219150; }

/* Responsive */
@media(max-width:768px){
    .filter-form { flex-direction:column; align-items:center; }
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
<h1>Product Marketplace</h1>

<!-- Filters -->
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

<!-- Products Grid -->
<div class="products-grid">
<?php if(count($products) > 0): ?>
    <?php foreach($products as $product): 
    // Try to find farmer by username OR name
    $farmer = $usersCollection->findOne([
        '$or' => [
            ['username' => $product['farmer']],
            ['name' => $product['farmer']]
        ],
        'role' => 'farmer'
    ]) ?? [];
?>

    <div class="card">
        <img src="../uploads/products/<?= htmlspecialchars($product['image'] ?? 'default.jpg') ?>" alt="Product Image">
        <div class="card-body">
            <h3><?= htmlspecialchars($product['name'] ?? '-') ?></h3>
            <p><strong>Farmer:</strong> <?= htmlspecialchars($farmer['name'] ?? $product['farmer']) ?> | Rating: <?= htmlspecialchars($farmer['rating'] ?? '-') ?></p>
            <p><strong>Price:</strong> <?= htmlspecialchars($product['price'] ?? '-') ?> LKR / <?= htmlspecialchars($product['unit'] ?? '-') ?></p>
            <p><strong>Available:</strong> <?= htmlspecialchars($product['quantity'] ?? '-') ?> <?= htmlspecialchars($product['unit'] ?? '-') ?></p>
            <!-- In the products grid section of products_marketplace.php -->
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
</body>
</html>
