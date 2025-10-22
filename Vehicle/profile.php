<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only allow drivers to access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'transporter') {
    header("Location: ../login.php");
    exit();
}

$vehicles = $db->vehicles;
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = $_POST['full_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $location = $_POST['location'] ?? '';
    $vehicleType = $_POST['vehicle_type'] ?? '';
    $vehicleNumber = $_POST['vehicle_number'] ?? '';

    // Handle image upload
    $imagePath = '';
    if (!empty($_FILES['vehicle_image']['name'])) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = time() . "_" . basename($_FILES['vehicle_image']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $targetFile)) {
            $imagePath = 'uploads/' . $fileName;
        } else {
            $errorMessage = "Failed to upload image!";
        }
    }

    if (!$errorMessage) {
        $vehicles->insertOne([
            'driver_id' => $_SESSION['user_id'],
            'full_name' => $fullName,
            'username' => $username,
            'location' => $location,
            'vehicle_type' => $vehicleType,
            'vehicle_number' => $vehicleNumber,
            'vehicle_image' => $imagePath,
            'status' => 'Pending',  // admin will approve later
            'time_slot' => '',      // assigned by admin
        ]);

        $successMessage = "Vehicle registered successfully! Waiting for admin approval.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Driver Vehicle Registration</title>
<link rel="stylesheet" href="profile.css">
</head>
<body>
<div class="container">
    <h2>Vehicle Registration</h2>

    <?php if ($successMessage): ?>
        <p style="color: green;"><?= $successMessage ?></p>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <p style="color: red;"><?= $errorMessage ?></p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Full Name:</label>
        <input type="text" name="full_name" placeholder="Your Full Name" required>

        <label>Username:</label>
        <input type="text" name="username" placeholder="Your Username" value="<?= $_SESSION['username'] ?? '' ?>" required>

        <label>Location:</label>
        <input type="text" name="location" placeholder="Your Location" required>

        <label>Vehicle Type:</label>
        <select name="vehicle_type" required>
            <option value="">-- Select Vehicle Type --</option>
            <option value="Van">Van</option>
            <option value="Truck">Truck</option>
            <option value="Lorry">Lorry</option>
            <option value="Motorbike">Motorbike</option>
        </select>

        <label>Vehicle Number:</label>
        <input type="text" name="vehicle_number" placeholder="Vehicle Number" required>

        <label>Vehicle Image:</label>
        <input type="file" name="vehicle_image" accept="image/*">

        <button type="submit">Register Vehicle</button>
    </form>
</div>
</body>
</html>
