<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../mongodb_config.php'; // adjust path if in Farmer folder

// MongoDB collection
$usercollection = $db->users;

// Check if user is logged in and is a farmer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    die("Error: Please log in as a farmer.");
}

$userId = new MongoDB\BSON\ObjectId($_SESSION['user_id']);

// Fetch user
$user = $usercollection->findOne(['_id' => $userId]);
if (!$user) {
    die("User not found!");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [
        'name' => $_POST['name'] ?? $user['name'],
        'username' => $_POST['username'] ?? $user['username'],
        'contact'  => $_POST['contact'] ?? $user['contact'],
        'location' => $_POST['location'] ?? $user['location'],
    ];

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $updateData['image'] = 'uploads/' . $fileName;
        }
    }

    // Update in MongoDB
    $usercollection->updateOne(
        ['_id' => $userId],
        ['$set' => $updateData]
    );

    // Reload page to see changes
    header("Location: farmer_profile.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Farmer Profile</title>
<link rel="stylesheet" href="profile.css">
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
    <h1>My Profile</h1>

    <div class="profile-box">
        <?php if (!empty($user['image'])): ?>
            <img src="<?= htmlspecialchars($user['image']) ?>" alt="Profile Image" class="profile-img">
        <?php else: ?>
            <img src="default.png" alt="Profile Image" class="profile-img">
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <label>Name:</label>
            <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>

            <label>Username:</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>

            <label>Contact:</label>
            <input type="text" name="contact" value="<?= htmlspecialchars($user['contact'] ?? '') ?>">

            <label>Location:</label>
            <input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>">

            <label>Profile Image:</label>
            <input type="file" name="image">

            <button type="submit">Update Profile</button>
        </form>
    </div>
</div>

</body>
</html>
