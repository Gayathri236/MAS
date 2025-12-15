<?php
session_start();
require __DIR__ . '/../mongodb_config.php';
date_default_timezone_set('Asia/Colombo');

// Only farmers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Collections
$usersCollection = $db->users;
$productsCollection = $db->products;

// ---------------- PROFILE ----------------
$farmerProfile = $usersCollection->findOne(['username' => $username, 'role' => 'farmer']);
if (!$farmerProfile) {
    // graceful fallback
    $farmerProfile = [
        'username' => $username,
        'name'     => $username,
        'phone'    => 'N/A',
        'location'  => 'N/A',
        'photo'    => 'default_profile.png'
    ];
}
$displayUsername = htmlspecialchars($farmerProfile['username'] ?? $username);
$displayName     = htmlspecialchars($farmerProfile['name'] ?? 'N/A');
$displayPhone    = htmlspecialchars($farmerProfile['phone'] ?? 'N/A');
$displayAddress  = htmlspecialchars($farmerProfile['location'] ?? ($farmerProfile['location'] ?? 'N/A'));
$displayImage = htmlspecialchars($farmerProfile['image'] ?? 'default.png');  

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $category = $_POST['category'];
        $quantity = floatval($_POST['quantity']);
        $price = floatval($_POST['price']);
        $unit = $_POST['unit'];
        $description = trim($_POST['description']);
        $harvest_date = $_POST['harvest_date'];

        if (empty($name) || empty($category) || $quantity <= 0 || $price <= 0) {
            throw new Exception("Please fill in all required fields with valid values.");
        }

        $image_filename = 'default.jpg';
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_extension, $allowed_extensions)) throw new Exception("Only JPG, JPEG, PNG, GIF allowed.");

            $image_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $_FILES['product_image']['name']);
            $upload_path = $upload_dir . $image_filename;
            if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                throw new Exception("Failed to upload image. Please try again.");
            }
        }

        $product = [
            'name' => $name,
            'category' => $category,
            'quantity' => $quantity,
            'price' => $price,
            'unit' => $unit,
            'description' => $description,
            'farmer' => $username,
            'image' => $image_filename,
            'harvest_date' => new MongoDB\BSON\UTCDateTime(strtotime($harvest_date) * 1000),
            'status' => 'active',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'views' => 0,
            'orders_count' => 0
        ];

        $result = $productsCollection->insertOne($product);
        if ($result->getInsertedCount() > 0) {
            $success_message = "Product added successfully! It's now visible to wholesalers.";
            $_POST = [];
        } else {
            throw new Exception("Failed to add product. Please try again.");
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/add_product.css">
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
        <a href="add_product.php" class="active">
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
        <a href="profile.php">
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
    <h1>Add New Product</h1>

    <?php if($success_message): ?>
        <div class="success-message">
            <?= htmlspecialchars($success_message) ?>
            <div style="margin-top:10px;">
                <a href="my_products.php" style="color:#155724;font-weight:bold;">View My Products</a> |
                <a href="add_product.php" style="color:#155724;font-weight:bold;">Add Another Product</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if($error_message): ?>
        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" enctype="multipart/form-data" id="productForm">
            <div class="form-group">
                <label for="name" class="required">Product Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g., Organic Tomatoes" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category" class="required">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <?php
                        $categories = ['Vegetables','Fruits','Grains','Spices','Herbs','Other'];
                        foreach($categories as $c){
                            $selected = ($_POST['category'] ?? '') === $c ? 'selected':'';
                            echo "<option value='$c' $selected>$c</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="unit" class="required">Unit</label>
                    <select id="unit" name="unit" required>
                        <option value="">Select Unit</option>
                        <?php
                        $units = ['kg'=>'Kilogram','g'=>'Gram','lb'=>'Pound','piece'=>'Piece','bunch'=>'Bunch','bag'=>'Bag'];
                        foreach($units as $k=>$v){
                            $selected = ($_POST['unit'] ?? '') === $k ? 'selected':'';
                            echo "<option value='$k' $selected>$v</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="quantity" class="required">Quantity</label>
                    <input type="number" id="quantity" name="quantity" value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>" min="0.1" step="0.1" placeholder="50.5" required>
                </div>
                <div class="form-group">
                    <label for="price" class="required">Price per Unit (LKR)</label>
                    <input type="number" id="price" name="price" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" min="1" step="0.01" placeholder="150.00" required>
                </div>
            </div>

            <div class="form-group">
                <label for="harvest_date" class="required">Harvest Date</label>
                <input type="date" id="harvest_date" name="harvest_date" value="<?= htmlspecialchars($_POST['harvest_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Describe your product (quality, organic status, storage info, etc.)"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="product_image">Product Image</label>
                <div class="file-upload" onclick="document.getElementById('product_image').click()">
                    <div style="font-size:48px;color:#bdc3c7;"><i class="fas fa-cloud-upload-alt"></i></div>
                    <div style="color:#7f8c8d;margin:10px 0;font-weight:500;">Click to upload image<br><small>Recommended: 500x500px (JPG, PNG, GIF)</small></div>
                </div>
                <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif" onchange="previewImage(this)" style="display: none;">
                <div class="file-preview" id="imagePreview"></div>
            </div>

            <button type="submit" class="btn"><i class="fas fa-plus-circle"></i> Add Product</button>
        </form>
    </div>

</div>
    <script src="assets/add_product.js"></script>
</body>
</html>