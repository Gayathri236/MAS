<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Colombo');

require __DIR__ . '/../mongodb_config.php';

// MongoDB collection
$usercollection = $db->users;

// Check if user is logged in and is a transporter
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'transporter') {
    header("Location: ../login.php");
    exit();
}

$transporter = $_SESSION['username'];

// Fetch transporter profile
$user = $usercollection->findOne(['username' => $transporter, 'role' => 'transporter']);
if (!$user) {
    $user = [
        'username' => $transporter,
        'name'     => $transporter,
        'phone'    => '',
        'vehicle_type' => '',
        'vehicle_number' => '',
        'image'    => 'default.png',
        'vehicle_image' => ''
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [
        'name' => $_POST['name'] ?? ($user['name'] ?? ''),
        'phone' => $_POST['phone'] ?? ($user['phone'] ?? ''),
        'vehicle_type' => $_POST['vehicle_type'] ?? ($user['vehicle_type'] ?? ''),
        'vehicle_number' => $_POST['vehicle_number'] ?? ($user['vehicle_number'] ?? ''),
        'username' => $transporter,
        'role' => 'transporter',
        'updated_at' => new MongoDB\BSON\UTCDateTime()
    ];

    // Handle profile image upload
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $fileName;
        
        // Check file size (max 5MB)
        if ($_FILES['image']['size'] > 5000000) {
            $error = "File is too large. Max size is 5MB.";
        } else {
            // Check if file is an image
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($imageFileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $updateData['image'] = $fileName;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
            }
        }
    } else {
        // Keep existing image if no new one uploaded
        if (isset($user['image'])) {
            $updateData['image'] = $user['image'];
        }
    }

    // Handle vehicle image upload
    if (!empty($_FILES['vehicle_image']['name']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . "_vehicle_" . basename($_FILES['vehicle_image']['name']);
        $targetFile = $uploadDir . $fileName;
        
        // Check file size (max 5MB)
        if ($_FILES['vehicle_image']['size'] > 5000000) {
            $error = "Vehicle image file is too large. Max size is 5MB.";
        } else {
            // Check if file is an image
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($imageFileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $targetFile)) {
                    $updateData['vehicle_image'] = $fileName;
                } else {
                    $error = "Sorry, there was an error uploading your vehicle image.";
                }
            } else {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed for vehicle image.";
            }
        }
    } else {
        // Keep existing vehicle image if no new one uploaded
        if (isset($user['vehicle_image'])) {
            $updateData['vehicle_image'] = $user['vehicle_image'];
        }
    }

    // Only update if no errors
    if (!isset($error)) {
        try {
            $result = $usercollection->updateOne(
                ['username' => $transporter, 'role' => 'transporter'],
                ['$set' => $updateData],
                ['upsert' => true]
            );
            
            if ($result->getModifiedCount() > 0 || $result->getUpsertedCount() > 0) {
                header("Location: transporter_profile.php?success=1");
                exit();
            } else {
                $error = "No changes were made to your profile.";
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$displayName = htmlspecialchars($user['name'] ?? $transporter);
$displayPhone = htmlspecialchars($user['phone'] ?? '');
$displayVehicleType = htmlspecialchars($user['vehicle_type'] ?? '');
$displayVehicleNumber = htmlspecialchars($user['vehicle_number'] ?? '');
$displayImage = htmlspecialchars($user['image'] ?? 'default.png');
$displayVehicleImage = htmlspecialchars($user['vehicle_image'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transporter Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/profile.css">
</head>
<body>

<div class="sidebar">
    <div class="profile-section">
        <img src="../uploads/<?= $displayImage ?>" alt="Profile" class="profile-img" onerror="this.src='../uploads/default.png'; this.onerror=null;">
        <h3><?= $displayName ?></h3>
        <p><?= $transporter ?></p>
        <p><i class="fas fa-truck"></i> Transporter</p>
    </div>
    
    <div class="nav-links">
        <a href="transporter_dashboard.php">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="transporter_time_slot.php">
            <i class="fas fa-clock"></i>
            <span>Time Slots</span>
        </a>
        <a href="transporter_delivered.php">
            <i class="fas fa-check-circle"></i>
            <span>Delivered Orders</span>
        </a>
        <a href="transporter_profile.php" class="active">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </div>
    
    <a href="../logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>

<div class="main">
    <h1>👤 Transporter Profile</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>
    
    <div class="profile-container">
        <div class="profile-header">
            <img src="../uploads/<?= $displayImage ?>" alt="Current Profile" class="current-avatar" onerror="this.src='../uploads/default.png'; this.onerror=null;">
            <h2><?= $displayName ?></h2>
            <p><?= $transporter ?> • <?= $displayVehicleType ? $displayVehicleType . ' (' . $displayVehicleNumber . ')' : 'Vehicle not set' ?></p>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="profile-form">
            <!-- Personal Information Section -->
            <div class="form-section">
                <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?= $displayName ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?= $displayPhone ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="image">Profile Picture</label>
                    <input type="file" id="image" name="image" accept="image/*">
                    <span class="file-hint">JPG, PNG, or GIF. Max 5MB.</span>
                </div>
            </div>
            
            <!-- Vehicle Information Section -->
            <div class="form-section">
                <h3><i class="fas fa-truck"></i> Vehicle Information</h3>
                
                <div class="form-group">
                    <label for="vehicle_type">Vehicle Type</label>
                    <input type="text" id="vehicle_type" name="vehicle_type" value="<?= $displayVehicleType ?>" 
                           placeholder="e.g., Van, Truck, Lorry, Three-wheeler" required>
                </div>
                
                <div class="form-group">
                    <label for="vehicle_number">Vehicle Number</label>
                    <input type="text" id="vehicle_number" name="vehicle_number" value="<?= $displayVehicleNumber ?>" 
                           placeholder="e.g., WP KA-1234" required>
                </div>
                
                <div class="form-group">
                    <label for="vehicle_image">Vehicle Photo</label>
                    <input type="file" id="vehicle_image" name="vehicle_image" accept="image/*">
                    <span class="file-hint">Upload a clear photo of your vehicle</span>
                    
                    <?php if (!empty($displayVehicleImage)): ?>
                        <img src="../uploads/<?= htmlspecialchars($displayVehicleImage) ?>" 
                             alt="Current Vehicle" 
                             class="current-vehicle-image"
                             onerror="this.style.display='none'; this.parentElement.innerHTML += '<div class=\'no-image\'>Vehicle image not found or removed.</div>';">
                    <?php else: ?>
                        <div class="no-image">No vehicle image uploaded yet.</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i> Update Profile
            </button>
        </form>
    </div>
</div>
 <script src="assets/profile.js"></script>
</body>
</html>