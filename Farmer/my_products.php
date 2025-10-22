<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

$username = $_SESSION['username'] ?? null;
if (!$username || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

// Collections
$productsCollection = $db->products;

// ---------------- Handle Actions ----------------
if (isset($_GET['action']) && isset($_GET['id'])) {
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

// ---------------- Filters ----------------
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$filter = ['farmer' => $username];

if ($search !== '') {
    $filter['name'] = ['$regex' => $search, '$options' => 'i'];
}
if ($statusFilter !== '') {
    // Use regex to match status ignoring case
    $filter['status'] = ['$regex' => '^' . preg_quote($statusFilter, '/') . '$', '$options' => 'i'];
}
if ($categoryFilter !== '') {
    // Use regex to match category ignoring case
    $filter['category'] = ['$regex' => '^' . preg_quote($categoryFilter, '/') . '$', '$options' => 'i'];
}


// Fetch products
$productsCursor = $productsCollection->find($filter, ['sort' => ['_id' => -1]]);
$products = iterator_to_array($productsCursor);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Products</title>
<style>
    /* ===== General Styles ===== */
body {
    font-family: 'Segoe UI', sans-serif;
    background-color: #f4f7f9;
    color: #333;
    margin: 0;
    padding: 0;
}

h1 {
    text-align: center;
    margin: 20px 0;
    color: #2c3e50;
}

/* ===== Container ===== */
.container {
    width: 95%;
    max-width: 1200px;
    margin: auto;
    padding: 20px;
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
/* 🌿 Navigation Bar */
.navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #3e8e41;
  color: white;
  padding: 15px 30px;
  box-shadow: 0 3px 6px rgba(0,0,0,0.2);
  position: sticky;
  top: 0;
  z-index: 10;
}

.nav-left h2 {
  margin: 0;
  font-size: 22px;
  letter-spacing: 0.5px;
}

.nav-right a {
  color: white;
  text-decoration: none;
  margin: 0 12px;
  font-weight: 500;
  transition: color 0.3s;
}

.nav-right a:hover {
  color: #d9ffd8;
}

.logout-btn {
  background-color: #ff4d4d;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: bold;
}

.logout-btn:hover {
  background-color: #e60000;
}

/* ===== Filters & Search ===== */
.filter-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    margin-bottom: 20px;
    gap: 10px;
}

.filter-container input[type="text"],
.filter-container select {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
    flex: 1 1 200px;
}

.filter-container button {
    padding: 8px 16px;
    background-color: #27ae60;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: 0.3s;
}

.filter-container button:hover {
    background-color: #219150;
}

/* ===== Table ===== */
.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

thead {
    background-color: #2c3e50;
    color: #fff;
}

thead th {
    padding: 12px;
    text-align: left;
}

tbody td {
    padding: 10px;
    border-bottom: 1px solid #e1e1e1;
    vertical-align: middle;
}

tbody tr:hover {
    background-color: #f0f8ff;
}

/* ===== Images ===== */
table td img {
    border-radius: 6px;
    width: 60px;
    height: 60px;
    object-fit: cover;
}

/* ===== Status Badges ===== */
td.active {
    color: #27ae60;
    font-weight: bold;
}

td['sold out'], td.soldout {
    color: #c0392b;
    font-weight: bold;
}

td.expired {
    color: #e67e22;
    font-weight: bold;
}

/* ===== Action Buttons ===== */
.actions a {
    display: inline-block;
    padding: 6px 12px;
    margin: 2px 2px;
    border-radius: 6px;
    font-size: 12px;
    text-decoration: none;
    transition: 0.3s;
    color: #fff;
}

.actions .edit {
    background-color: #3498db;
}

.actions .edit:hover {
    background-color: #2980b9;
}

.actions .delete {
    background-color: #e74c3c;
}

.actions .delete:hover {
    background-color: #c0392b;
}

.actions .soldout {
    background-color: #f39c12;
}

.actions .soldout:hover {
    background-color: #d68910;
}

.actions .analytics {
    background-color: #8e44ad;
}

.actions .analytics:hover {
    background-color: #71368a;
}

/* ===== Responsive ===== */
@media (max-width: 768px) {
    .filter-container {
        flex-direction: column;
    }

    table td,
    table th {
        padding: 8px;
        font-size: 13px;
    }

    table td img {
        width: 50px;
        height: 50px;
    }

    .actions a {
        padding: 4px 8px;
        font-size: 11px;
    }
}

</style>
</head>
<body>
    <!-- 🌿 Navigation Bar -->
<nav class="navbar">
    <div class="nav-left">
        <h2>🌾 DMAS Farmer Panel</h2>
    </div>
    <div class="nav-right">
        <a href="farmer_dashboard.php">Dashboard</a>
        <a href="add_product.php">Products</a>
        <a href="farmer_orders.php">Orders</a>
          <a href="price_intelligence.php">Price Intelligence</a>
        <a href="product_analytics.php">Product Analytics</a>
        <a href="order_history.php">Order History</a>
        <a href="reports.php">Reports</a>
        <a href="profile.php">Profile</a>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<div class="container">
    <h1>My Products</h1>

    <!-- Search & Filters -->
    <form class="filter-form" method="GET">
        <input type="text" name="search" placeholder="Search product..." value="<?= htmlspecialchars($search) ?>">
        <select name="status">
            <option value="">All Status</option>
            <option value="Active" <?= ($statusFilter=='Active')?'selected':'' ?>>Active</option>
            <option value="Sold Out" <?= ($statusFilter=='Sold Out')?'selected':'' ?>>Sold Out</option>
            <option value="Expired" <?= ($statusFilter=='Expired')?'selected':'' ?>>Expired</option>
        </select>
        <select name="category">
            <option value="">All Categories</option>
            <option value="Vegetables" <?= ($categoryFilter=='Vegetables')?'selected':'' ?>>Vegetables</option>
            <option value="Fruits" <?= ($categoryFilter=='Fruits')?'selected':'' ?>>Fruits</option>
            <option value="Grains" <?= ($categoryFilter=='Grains')?'selected':'' ?>>Grains</option>
            <option value="Other" <?= ($categoryFilter=='Other')?'selected':'' ?>>Other</option>
        </select>
        <button type="submit">Filter</button>
    </form>

    <!-- Products Table -->
    <table class="products-table">
        <thead>
            <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Quality Grade</th>
                <th>Status</th>
                <th>Views</th>
                <th>Orders</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if(count($products) > 0): ?>
            <?php foreach ($products as $product): 
                $status = $product['status'] ?? 'Active';
                $quantityUnit = $product['unit'] ?? '';
                $price = $product['price'] ?? 0;
                $quality = $product['quality_grade'] ?? 'N/A';
                $views = $product['views'] ?? 0;
                $orders = $product['orders'] ?? 0;
            ?>
                <tr>
                    <td><img src="../uploads/products/<?= htmlspecialchars($product['image'] ?? 'default.jpg') ?>" class="prod-img"></td>
                    <td><?= htmlspecialchars($product['name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($product['quantity'] ?? '-') ?> <?= $quantityUnit ?></td>
                    <td><?= htmlspecialchars($price) ?> LKR</td>
                    <td><?= htmlspecialchars($quality) ?></td>
                    <td class="<?= strtolower(str_replace(' ', '', $status)) ?>"><?= htmlspecialchars($status) ?></td>
                    <td><?= $views ?></td>
                    <td><?= $orders ?></td>
                    <td class="actions">
                        <a href="edit_product.php?id=<?= $product['_id'] ?>" class="edit">Edit</a>
                        <a href="?action=delete&id=<?= $product['_id'] ?>" class="delete" onclick="return confirm('Are you sure?')">Delete</a>
                        <?php if($status !== 'Sold Out'): ?>
                            <a href="?action=soldout&id=<?= $product['_id'] ?>" class="soldout">Mark as Sold Out</a>
                        <?php endif; ?>
                        <a href="product_analytics.php?id=<?= $product['_id'] ?>" class="analytics">View Analytics</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="9" style="text-align:center;">No products found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
