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
        $fileName = time() . '_' . basename($_FILES["image"]["name"]);
        $imagePath = $targetDir . $fileName;
        move_uploaded_file($_FILES["image"]["tmp_name"], $imagePath);
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check existing user
    $existingUser = $usersCollection->findOne(['username' => $username]);

    if ($existingUser) {
        $errorMessage = "Username already exists! Please choose another.";
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
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        
        // Store success message in session for login page
        $_SESSION['success'] = "Registration successful! Please login with your credentials.";
        header("Location: login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMAS User Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/registration.css">
</head>
<body>
    <div class="registration-container">
        <div class="registration-header">
            <h1>
                <i class="fas fa-user-plus"></i>
                DMAS
            </h1>
            <p>Dambulla Market Automation System - User Registration</p>
        </div>

        <div class="registration-box">
            <h2>Create Your Account</h2>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $errorMessage ?>
                </div>
            <?php endif; ?>

            <form action="registration.php" method="POST" enctype="multipart/form-data" id="registrationForm">
                <div class="form-row">
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" placeholder="Enter Username" required 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="input-group">
                        <i class="fas fa-user-circle"></i>
                        <input type="text" name="name" placeholder="Full Name" required
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                </div>

                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Enter Password" required>
                    <div class="password-strength">
                        <div class="password-strength-meter" id="passwordStrength"></div>
                    </div>
                </div>

                <div class="input-group">
                    <i class="fas fa-phone"></i>
                    <input type="text" name="phone" placeholder="Phone Number" required
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>

                <div class="input-group">
                    <i class="fas fa-map-marker-alt"></i>
                    <input type="text" name="address" placeholder="Full Address" required
                           value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <i class="fas fa-location-dot"></i>
                        <select name="location" required>
                            <option value="">-- Select Location --</option>
                            <option value="Colombo" <?= ($_POST['location'] ?? '') === 'Colombo' ? 'selected' : '' ?>>Colombo</option>
                            <option value="Galle" <?= ($_POST['location'] ?? '') === 'Galle' ? 'selected' : '' ?>>Galle</option>
                            <option value="Kandy" <?= ($_POST['location'] ?? '') === 'Kandy' ? 'selected' : '' ?>>Kandy</option>
                            <option value="Kurunegala" <?= ($_POST['location'] ?? '') === 'Kurunegala' ? 'selected' : '' ?>>Kurunegala</option>
                            <option value="Matara" <?= ($_POST['location'] ?? '') === 'Matara' ? 'selected' : '' ?>>Matara</option>
                        </select>
                        <i class="fas fa-chevron-down select-icon"></i>
                    </div>

                    <div class="input-group">
                        <i class="fas fa-user-tag"></i>
                        <select name="role" required>
                            <option value="">-- Select Role --</option>
                            <option value="farmer" <?= ($_POST['role'] ?? '') === 'farmer' ? 'selected' : '' ?>>Farmer</option>
                            <option value="wholesaler" <?= ($_POST['role'] ?? '') === 'wholesaler' ? 'selected' : '' ?>>Wholesaler / Buyer</option>
                            <option value="transporter" <?= ($_POST['role'] ?? '') === 'transporter' ? 'selected' : '' ?>>Transporter</option>
                        </select>
                        <i class="fas fa-chevron-down select-icon"></i>
                    </div>
                </div>

                <div class="file-input-group">
                    <label>Profile Image (Optional)</label>
                    <div class="file-input-container">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload profile image</p>
                        <p class="file-hint">JPG, PNG, or GIF. Max 5MB.</p>
                        <input type="file" name="image" accept="image/*" id="imageUpload">
                    </div>
                    <div class="file-preview" id="imagePreview"></div>
                </div>

                <button type="submit" class="register-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Create Account</span>
                    <i class="fas fa-spinner loading"></i>
                </button>
            </form>

            <div class="login-link">
                <p>
                    Already have an account? 
                    <a href="login.php">
                        <i class="fas fa-sign-in-alt"></i> Login here
                    </a>
                </p>
            </div>
        </div>
    </div>
 <script src="assets/registration.js"></script>
</body>
</html>