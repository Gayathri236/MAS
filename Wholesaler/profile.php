<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../mongodb_config.php'; // adjust path if in Wholesaler folder

// MongoDB collection
$usercollection = $db->users;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Error: No user_id in session. Please log in again.");
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
        'company'  => $_POST['company'] ?? $user['company'],
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
            $updateData['image'] = 'uploads/' . $fileName; // Save relative path
        }
    }

    // Update in MongoDB
    $usercollection->updateOne(
        ['_id' => $userId],
        ['$set' => $updateData]
    );

    header("Location: profile.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Wholesaler Profile | DMAS</title>
  <link rel="stylesheet" href="profile.css">
</head>
<body>
  <nav class="navbar">
    <div class="nav-left">
      <h2>🌾 DMAS - Wholesaler Portal</h2>
    </div>
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
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>

        <label>Company:</label>
        <input type="text" name="company" value="<?= htmlspecialchars($user['company'] ?? '') ?>">

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
