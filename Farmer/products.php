<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../mongodb_config.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];

// ---------- Handle Add/Edit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['edit_id'] ?? null;
    $data = [
        'name' => $_POST['name'],
        'category' => $_POST['category'],
        'quantity' => (int)$_POST['quantity'],
        'unit' => $_POST['unit'],
        'price' => (float)$_POST['price'],
        'bulk_price' => (float)($_POST['bulk_price'] ?? 0),
        'status' => $_POST['status'] ?? 'Available',
        'farmer' => $username
    ];

    // Handle image
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $data['image'] = $uploadDir . time() . "_" . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $data['image']);
    }

    if ($id) {
        $productsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($id), 'farmer' => $username],
            ['$set' => $data]
        );
    } else {
        $productsCollection->insertOne($data);
    }

    header("Location: products.php");
    exit();
}

// ---------- Handle Delete ----------
if (isset($_GET['delete'])) {
    $productsCollection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($_GET['delete']), 'farmer' => $username]);
    header("Location: products.php");
    exit();
}

// ---------- Fetch Products ----------
$products = $productsCollection->find(['farmer' => $username]);

// ---------- Fetch Product to Edit ----------
$edit_product = isset($_GET['edit']) ? $productsCollection->findOne([
    '_id' => new MongoDB\BSON\ObjectId($_GET['edit']),
    'farmer' => $username
]) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Products - Farmer</title>
<link rel="stylesheet" href="products.css">
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
<h1>Welcome <?= htmlspecialchars($username) ?> (Farmer)</h1>

<h3><?= $edit_product ? 'Edit Product' : 'Add Product' ?></h3>
<form method="POST" enctype="multipart/form-data">
    <?php if ($edit_product): ?>
        <input type="hidden" name="edit_id" value="<?= $edit_product['_id'] ?>">
    <?php endif; ?>
    <input type="text" name="name" placeholder="Product Name" value="<?= $edit_product['name'] ?? '' ?>" required>
    <input type="text" name="category" placeholder="Category" value="<?= $edit_product['category'] ?? '' ?>" required>
    <input type="number" name="quantity" placeholder="Quantity" value="<?= $edit_product['quantity'] ?? '' ?>" required>
    <input type="text" name="unit" placeholder="Unit" value="<?= $edit_product['unit'] ?? '' ?>" required>
    <input type="number" step="0.01" name="price" placeholder="Price per Unit" value="<?= $edit_product['price'] ?? '' ?>" required>
    <input type="number" step="0.01" name="bulk_price" placeholder="Bulk Price" value="<?= $edit_product['bulk_price'] ?? '' ?>">
    <select name="status">
        <option value="Available" <?= ($edit_product['status'] ?? '') === 'Available' ? 'selected' : '' ?>>Available</option>
        <option value="Sold" <?= ($edit_product['status'] ?? '') === 'Sold' ? 'selected' : '' ?>>Sold</option>
    </select>
    <input type="file" name="image">
    <button type="submit"><?= $edit_product ? 'Update' : 'Add' ?> Product</button>
</form>

<h3>My Products</h3>
<table border="1" cellpadding="5">
<tr>
    <th>Image</th><th>Name</th><th>Category</th><th>Quantity</th>
    <th>Unit Price</th><th>Bulk Price</th><th>Status</th><th>Actions</th>
</tr>
<?php foreach ($products as $p): ?>
<tr>
    <td><img src="<?= htmlspecialchars($p['image']) ?>" width="60"></td>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td><?= htmlspecialchars($p['category']) ?></td>
    <td><?= htmlspecialchars($p['quantity']) ?> <?= htmlspecialchars($p['unit']) ?></td>
    <td><?= htmlspecialchars($p['price']) ?> LKR/<?= htmlspecialchars($p['unit']) ?></td>
    <td><?= htmlspecialchars($p['bulk_price'] ?? 'N/A') ?> LKR</td>
    <td><?= htmlspecialchars($p['status'] ?? 'Available') ?></td>
    <td>
        <a href="?edit=<?= $p['_id'] ?>">Edit</a> | 
        <a href="?delete=<?= $p['_id'] ?>" onclick="return confirm('Delete this product?')">Delete</a>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>
</body>
</html>
