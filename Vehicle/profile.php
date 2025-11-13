<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../mongodb_config.php';

// MongoDB collection
$usercollection = $db->users;

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'transporter') {
    die("Error: Please log in as a driver.");
}

$userId = new MongoDB\BSON\ObjectId($_SESSION['user_id']);

// Fetch user
$user = $usercollection->findOne(['_id' => $userId]);
if (!$user) die("User not found!");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [
        'name' => $_POST['name'] ?? $user['name'],
        'username' => $_POST['username'] ?? $user['username'],
        'contact' => $_POST['contact'] ?? $user['contact'],
        'vehicle_type' => $_POST['vehicle_type'] ?? $user['vehicle_type'],
        'vehicle_number' => $_POST['vehicle_number'] ?? $user['vehicle_number'],
    ];

    // Handle profile image upload
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $updateData['image'] = 'uploads/' . $fileName;
        }
    }

    // Handle vehicle image upload
    if (!empty($_FILES['vehicle_image']['name'])) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . "_vehicle_" . basename($_FILES['vehicle_image']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $targetFile)) {
            $updateData['vehicle_image'] = 'uploads/' . $fileName;
        }
    }

    // Update user document
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
<title>Driver Profile | DMAS</title>
<link rel="stylesheet" href="profile.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-left">
        <h2>🚚 DMAS - Driver Portal</h2>
    </div>
    <div class="nav-right">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="orders.php">📦 Orders</a>
        <a href="profile.php" class="active">👤 My Profile</a>
        <a href="../logout.php" class="logout">🚪 Logout</a>
    </div>
</nav>

<div class="container">
    <h1>My Profile</h1>

    <div class="profile-box">
        <!-- Driver Profile Image -->
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

            <label>Vehicle Type:</label>
            <input type="text" name="vehicle_type" value="<?= htmlspecialchars($user['vehicle_type'] ?? '') ?>" placeholder="Van / Truck / Lorry" required>

            <label>Vehicle Number:</label>
            <input type="text" name="vehicle_number" value="<?= htmlspecialchars($user['vehicle_number'] ?? '') ?>" required>

            <label>Profile Image:</label>
            <input type="file" name="image" accept="image/*">

            <label>Vehicle Image:</label>
            <input type="file" name="vehicle_image" accept="image/*">

            <!-- Show vehicle image if uploaded -->
            <?php if (!empty($user['vehicle_image'])): ?>
                <img src="<?= htmlspecialchars($user['vehicle_image']) ?>" alt="Vehicle Image" class="profile-img" style="width:150px; margin-top:10px;">
            <?php endif; ?>

            <button type="submit">Update Profile</button>
        </form>
    </div>
</div>
</body>
</html>
