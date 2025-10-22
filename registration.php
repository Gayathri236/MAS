<?php
session_start();
require 'mongodb_config.php';

$successMessage = '';
$errorMessage = '';

// Function to generate next user_id
function generateUserId($usersCollection) {
    $lastUser = $usersCollection->findOne([], [
        'sort' => ['_id' => -1],
        'projection' => ['user_id' => 1]
    ]);

    if ($lastUser && isset($lastUser['user_id'])) {
        $lastIdNum = (int) filter_var($lastUser['user_id'], FILTER_SANITIZE_NUMBER_INT);
        $nextIdNum = $lastIdNum + 1;
    } else {
        $nextIdNum = 1001; // start from U1001
    }
    return 'U' . $nextIdNum;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $location = trim($_POST['location'] ?? '');

    // Handle image upload (optional)
    $imagePath = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $targetDir = "uploads/users/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $imagePath = $targetDir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $imagePath);
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check existing user
    $existingUser = $usersCollection->findOne(['username' => $username]);

    if ($existingUser) {
        $errorMessage = "⚠️ Username already exists! Please choose another.";
    } else {
        // Generate user ID
        $user_id = generateUserId($usersCollection);

        // Insert into MongoDB
        $usersCollection->insertOne([
            'user_id' => $user_id,
            'username' => $username,
            'name' => $name,
            'password' => $hashedPassword,
            'role' => $role,
            'phone' => $phone,
            'address' => $address,
            'location' => $location,
            'image' => $imagePath,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $successMessage = "✅ Registration successful! Redirecting to login...";
        $_POST = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DMAS User Registration</title>
<link rel="stylesheet" href="registration.css">

<script>
<?php if ($successMessage): ?>
setTimeout(() => window.location.href = "login.php", 2500);
<?php endif; ?>
</script>
</head>
<body>
<div class="login-box">
    <h2>DMAS Registration</h2>

    <?php if ($errorMessage): ?>
        <p class="error"><?= $errorMessage ?></p>
    <?php endif; ?>
    <?php if ($successMessage): ?>
        <p class="success"><?= $successMessage ?></p>
    <?php endif; ?>

    <form action="registration.php" method="POST" enctype="multipart/form-data">
        <input type="text" name="username" placeholder="Enter Username" required>
        <input type="text" name="name" placeholder="Full Name" required>
        <input type="password" name="password" placeholder="Enter Password" required>
        <input type="text" name="phone" placeholder="Phone Number" required>
        <input type="text" name="address" placeholder="Full Address" required>

        <select name="location" required>
            <option value="">-- Select Location --</option>
            <option value="Colombo">Colombo</option>
            <option value="Galle">Galle</option>
            <option value="Kandy">Kandy</option>
            <option value="Kurunegala">Kurunegala</option>
            <option value="Matara">Matara</option>
        </select>

        <select name="role" required>
            <option value="">-- Select Role --</option>
            <option value="farmer">Farmer</option>
            <option value="wholesaler">Wholesaler / Buyer</option>
            <option value="transporter">Transporter</option>
            <option value="admin">Admin</option>
        </select>

        <label style="margin-top:8px;">Profile Image:</label>
        <input type="file" name="image" accept="image/*">

        <button type="submit">Register</button>
    </form>

    <p>Already have an account? <a href="login.php">Login here</a></p>
</div>
</body>
</html>
