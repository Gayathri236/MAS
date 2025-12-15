<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Colombo');

require __DIR__ . '/../mongodb_config.php';

// MongoDB collection
$usersCollection = $db->users;

// Check if user is logged in and is a farmer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ------------------------- PROFILE -------------------------
$farmerProfile = $usersCollection->findOne(['username' => $username, 'role' => 'farmer']);
if (!$farmerProfile) {
    $farmerProfile = [
        'username' => $username,
        'name'     => $username,
        'phone'    => 'N/A',
        'location'  => 'N/A',
        'image'    => 'default.png'
    ];
}
$displayUsername = htmlspecialchars($farmerProfile['username'] ?? $username);
$displayName     = htmlspecialchars($farmerProfile['name'] ?? 'N/A');
$displayPhone    = htmlspecialchars($farmerProfile['phone'] ?? $farmerProfile['contact'] ?? 'N/A');
$displayAddress  = htmlspecialchars($farmerProfile['location'] ?? 'N/A');
$displayImage = htmlspecialchars($farmerProfile['image'] ?? 'default.png');  
$distanceToMarket = isset($farmerProfile['distance_to_market_km']) ? floatval($farmerProfile['distance_to_market_km']) : 0;

$success_message = "";
$error_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = new MongoDB\BSON\ObjectId($user_id);
    $location = $_POST['location'] ?? $farmerProfile['location'] ?? '';
    $map_link = "https://www.google.com/maps/search/?api=1&query=" . urlencode($location);

    $updateData = [
        'name' => $_POST['name'] ?? $farmerProfile['name'] ?? '',
        'username' => $_POST['username'] ?? $farmerProfile['username'] ?? '',
        'phone' => $_POST['phone'] ?? $farmerProfile['phone'] ?? '',
        'contact' => $_POST['phone'] ?? $farmerProfile['contact'] ?? '',
        'location' => $location,
        'distance_to_market_km' => floatval($_POST['distance_to_market_km'] ?? $distanceToMarket),
        'map_link' => $map_link,
        'updated_at' => new MongoDB\BSON\UTCDateTime()
    ];

    // Handle image upload
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $fileName;

        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['image']['tmp_name']);
        
        if (in_array($fileType, $allowedTypes)) {
            // Check file size (max 2MB)
            if ($_FILES['image']['size'] > 2097152) {
                $error_message = "File is too large. Maximum size is 2MB.";
            } else {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    // Save only the filename
                    $updateData['image'] = $fileName;
                    
                    // Delete old image if it exists and is not the default
                    if (!empty($farmerProfile['image']) && $farmerProfile['image'] !== 'default.png' && $farmerProfile['image'] !== 'default_profile.png') {
                        $oldImage = $uploadDir . $farmerProfile['image'];
                        if (file_exists($oldImage) && is_file($oldImage)) {
                            unlink($oldImage);
                        }
                    }
                } else {
                    $error_message = "Failed to upload image. Please try again.";
                }
            }
        } else {
            $error_message = "Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.";
        }
    }

    // Only update if no error
    if (empty($error_message)) {
        // Update in MongoDB
        $result = $usersCollection->updateOne(
            ['_id' => $userId],
            ['$set' => $updateData]
        );

        if ($result->getModifiedCount() > 0) {
            $success_message = "Profile updated successfully!";
            // Refresh profile data
            $farmerProfile = $usersCollection->findOne(['_id' => $userId]);
            $displayName = htmlspecialchars($farmerProfile['name'] ?? '');
            $displayUsername = htmlspecialchars($farmerProfile['username'] ?? '');
            $displayPhone = htmlspecialchars($farmerProfile['phone'] ?? $farmerProfile['contact'] ?? '');
            $displayAddress = htmlspecialchars($farmerProfile['location'] ?? '');
            $displayImage = htmlspecialchars($farmerProfile['image'] ?? 'default.png');
            $distanceToMarket = isset($farmerProfile['distance_to_market_km']) ? floatval($farmerProfile['distance_to_market_km']) : 0;
            
            // Update session username if changed
            if (isset($farmerProfile['username']) && $farmerProfile['username'] !== $_SESSION['username']) {
                $_SESSION['username'] = $farmerProfile['username'];
                $username = $farmerProfile['username'];
            }
        } else {
            $error_message = "No changes were made to your profile.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/profile.css">
    <style>
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message i,
        .error-message i {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="profile-section">
        <img src="../uploads/<?= htmlspecialchars($displayImage) ?>" alt="Profile" class="profile-img" onerror="this.src='../uploads/default.png'; this.onerror=null;">
        <h3><?= $displayName ?></h3>
        <p><?= $username ?></p>
        <p><i class="fas fa-map-marker-alt"></i> <?= $displayAddress ?></p>
    </div>
    
    <div class="nav-links">
        <a href="farmer_dashboard.php">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="add_product.php">
            <i class="fas fa-plus-circle"></i>
            <span>Add Product</span>
        </a>
        <a href="my_products.php">
            <i class="fas fa-seedling"></i>
            <span>My Products</span>
        </a>
        <a href="farmer_orders.php">
            <i class="fas fa-shopping-cart"></i>
            <span>Orders</span>
        </a>
        <a href="prediction.php">
            <i class="fas fa-chart-line"></i>
            <span>Prediction</span>
        </a>
        <a href="report.php">
            <i class="fas fa-file-export"></i>
            <span>Reports</span>
        </a>
        <a href="profile.php" class="active">
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
    <h1><i class="fas fa-user"></i> My Profile</h1>
    
    <?php if (!empty($success_message)): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="profile-container">
        <!-- Profile Card -->
        <div class="profile-card">
            <img src="../uploads/<?= htmlspecialchars($displayImage) ?>" 
                 alt="Profile Picture" 
                 class="profile-picture"
                 onerror="this.src='../uploads/default.png'; this.onerror=null;">
            
            <div class="profile-info">
                <div class="info-item">
                    <i class="fas fa-user"></i>
                    <div class="info-label">Full Name:</div>
                    <div class="info-value"><?= $displayName ?></div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-at"></i>
                    <div class="info-label">Username:</div>
                    <div class="info-value">@<?= $username ?></div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <div class="info-label">Phone:</div>
                    <div class="info-value"><?= $displayPhone ?></div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div class="info-label">Location:</div>
                    <div class="info-value"><?= $displayAddress ?></div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-road"></i>
                    <div class="info-label">Market Distance:</div>
                    <div class="info-value">
                        <?php if ($distanceToMarket > 0): ?>
                            <span class="distance-badge"><?= number_format($distanceToMarket, 1) ?> km</span>
                        <?php else: ?>
                            <span style="color: #999;">Not set</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Form -->
        <div class="profile-form">
            <h2 style="color: #264653; margin-bottom: 25px; font-size: 1.5rem;">Edit Profile Information</h2>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?= htmlspecialchars($displayName) ?>" 
                           placeholder="Enter your full name"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           value="<?= htmlspecialchars($username) ?>" 
                           placeholder="Enter your username"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" 
                           id="phone" 
                           name="phone" 
                           value="<?= htmlspecialchars($displayPhone) ?>" 
                           placeholder="Enter your phone number">
                </div>
                
                <div class="form-group">
                    <label for="location">Location (Farm Address)</label>
                    <input type="text" 
                           id="location" 
                           name="location" 
                           value="<?= htmlspecialchars($displayAddress) ?>" 
                           placeholder="Enter your farm location"
                           oninput="updateMap()">
                </div>
                
                <div class="form-group">
                    <label for="distance_to_market_km">Distance from Farm to Market (km)</label>
                    <input type="number" 
                           id="distance_to_market_km" 
                           name="distance_to_market_km" 
                           value="<?= $distanceToMarket ?>" 
                           step="0.1" 
                           min="0" 
                           placeholder="e.g., 15.5"
                           style="width: 200px;">
                </div>
                
                <div class="form-group">
                    <label for="image">Profile Picture</label>
                    <div class="file-upload">
                        <div class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Click to upload new profile picture</span>
                            <small>Recommended: Square image, max 2MB</small>
                        </div>
                        <input type="file" 
                               id="image" 
                               name="image" 
                               accept="image/jpeg,image/png,image/gif,image/webp"
                               onchange="previewImage(this)">
                    </div>
                    <div id="imagePreview" style="margin-top: 15px;">
                        <?php if ($displayImage !== 'default.png'): ?>
                            <p>Current image: <?= htmlspecialchars($displayImage) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Map Preview -->
                <div class="form-group">
                    <label>Location Preview</label>
                    <div class="map-container">
                        <iframe id="mapFrame" 
                            src="https://www.google.com/maps?q=<?= urlencode($displayAddress ?: 'Sri Lanka') ?>&output=embed&zoom=12"
                            allowfullscreen>
                        </iframe>
                    </div>
                    <a id="mapLink" 
                       href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($displayAddress ?: 'Sri Lanka') ?>" 
                       target="_blank" 
                       class="map-link">
                        <i class="fas fa-external-link-alt"></i> View on Google Maps
                    </a>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Update Profile
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function updateMap() {
    var location = document.getElementById('location').value;
    if (location.trim() !== '') {
        var mapFrame = document.getElementById('mapFrame');
        var mapLink = document.getElementById('mapLink');
        
        var encodedLocation = encodeURIComponent(location);
        mapFrame.src = 'https://www.google.com/maps?q=' + encodedLocation + '&output=embed&zoom=12';
        mapLink.href = 'https://www.google.com/maps/search/?api=1&query=' + encodedLocation;
    }
}

function previewImage(input) {
    var preview = document.getElementById('imagePreview');
    
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid #ddd;">';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>