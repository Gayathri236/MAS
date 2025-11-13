<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../mongodb_config.php';

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

    header("Location: farmer_profile.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Farmer Profile</title>
<style>
body { font-family:'Segoe UI',sans-serif; background:#f4f7f9; margin:0; color:#333; }
.navbar {
  background:#3e8e41; color:white; display:flex; justify-content:space-between;
  padding:15px 30px; box-shadow:0 3px 6px rgba(0,0,0,0.2);
}
.nav-left h2 { margin:0; font-size:22px; }
.nav-right a { color:white; margin-left:15px; text-decoration:none; font-weight:bold; }
.nav-right a:hover { color:#d9ffd8; }
.logout-btn { background:#e74c3c; padding:6px 12px; border-radius:6px; }
.container { width:90%; max-width:800px; margin:30px auto; background:white; padding:20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
.profile-img { width:120px; height:120px; border-radius:50%; object-fit:cover; display:block; margin:0 auto 15px; border:2px solid #ccc; }
form { display:flex; flex-direction:column; gap:10px; }
label { font-weight:bold; }
input[type="text"], input[type="file"] { padding:8px; border:1px solid #ccc; border-radius:6px; }
button { background:#27ae60; color:white; padding:10px; border:none; border-radius:6px; cursor:pointer; font-size:16px; }
button:hover { background:#219150; }
.map-container { margin-top:15px; text-align:center; }
iframe { width:100%; height:300px; border:0; border-radius:8px; }
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
    <a href="product_analytics.php">Analytics</a>
    <a href="profile.php" class="active">Profile</a>
    <a href="../logout.php" class="logout-btn">Logout</a>
  </div>
</nav>

<div class="container">
  <h1 style="text-align:center;">My Profile</h1>

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

      <label>Location (Address):</label>
      <input type="text" id="locationInput" name="location" 
             value="<?= htmlspecialchars($user['location'] ?? '') ?>" 
             placeholder="Enter your farm location" oninput="updateMap()">

      <label>Profile Image:</label>
      <input type="file" name="image" accept="image/*">

      <div class="map-container">
        <iframe id="mapFrame" 
          src="https://www.google.com/maps?q=<?= urlencode($user['location'] ?? 'Sri Lanka') ?>&output=embed">
        </iframe>
        <p><a id="mapLink" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($user['location'] ?? 'Sri Lanka') ?>" 
              target="_blank">📍 View on Google Maps</a></p>
      </div>

      <button type="submit">Update Profile</button>
    </form>
  </div>
</div>

<script>
function updateMap() {
  const loc = document.getElementById('locationInput').value.trim();
  const mapFrame = document.getElementById('mapFrame');
  const mapLink = document.getElementById('mapLink');
  if (loc) {
    const encoded = encodeURIComponent(loc);
    mapFrame.src = `https://www.google.com/maps?q=${encoded}&output=embed`;
    mapLink.href = `https://www.google.com/maps/search/?api=1&query=${encoded}`;
  }
}
</script>

</body>
</html>
