<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only farmers can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$farmer_username = $_SESSION['username'];
$productsCollection = $db->products;
$usersCollection = $db->users;

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $name = trim($_POST['name']);
        $category = $_POST['category'];
        $quantity = floatval($_POST['quantity']);
        $price = floatval($_POST['price']);
        $unit = $_POST['unit'];
        $description = trim($_POST['description']);
        $harvest_date = $_POST['harvest_date'];
        
        // Basic validation
        if (empty($name) || empty($category) || $quantity <= 0 || $price <= 0) {
            throw new Exception("Please fill in all required fields with valid values.");
        }
        
        // Handle image upload
        $image_filename = 'default.jpg';
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Only JPG, JPEG, PNG, and GIF files are allowed.");
            }
            
            // Generate unique filename
            $image_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $_FILES['product_image']['name']);
            $upload_path = $upload_dir . $image_filename;
            
            if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                throw new Exception("Failed to upload image. Please try again.");
            }
        }
        
        // Create product document
        $product = [
            'name' => $name,
            'category' => $category,
            'quantity' => $quantity,
            'price' => $price,
            'unit' => $unit,
            'description' => $description,
            'farmer' => $farmer_username,
            'image' => $image_filename,
            'quality_grade' => 'B', // Default grade, can be updated by AI later
            'harvest_date' => new MongoDB\BSON\UTCDateTime(strtotime($harvest_date) * 1000),
            'status' => 'active',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'views' => 0,
            'orders_count' => 0
        ];
        
        // Insert into database
        $result = $productsCollection->insertOne($product);
        
        if ($result->getInsertedCount() > 0) {
            $success_message = "Product added successfully! It's now visible to wholesalers.";
            
            // Reset form fields
            $_POST = array();
        } else {
            throw new Exception("Failed to add product. Please try again.");
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get farmer details for pre-filling
$farmer = $usersCollection->findOne(['username' => $farmer_username]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - DMAS</title>
    <style>
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: #f4f7f9; 
            margin: 0; 
            color: #333; 
        }

        .container { 
            width: 95%; 
            max-width: 800px; 
            margin: 20px auto; 
        }

            /* 🌿 Navigation Bar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #3e8e41;
            color: white;
            padding: 15px 30px;
            box-shadow: 0 3px 6px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 10;
            }

        .nav-left h2 {
            margin: 0;
            font-size: 22px;
            letter-spacing: 0.5px;
            }

        .nav-right a {
            color: white;
            text-decoration: none;
            margin: 0 12px;
            font-weight: 500;
            transition: color 0.3s;
            }

        .nav-right a:hover {
            color: #d9ffd8;
            }

        .logout-btn {
            background-color: #ff4d4d;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            }

        .logout-btn:hover {
            background-color: #e60000;
            }
        
        .form-card { 
            background: #fff; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: bold; 
            color: #2c3e50; 
        }
        .required::after { 
            content: " *"; 
            color: #e74c3c; 
        }
        input, select, textarea { 
            width: 100%; 
            padding: 12px; 
            border: 2px solid #e0e0e0; 
            border-radius: 8px; 
            font-size: 16px; 
            box-sizing: border-box; 
            transition: border-color 0.3s; 
        }
        input:focus, select:focus, textarea:focus { 
            border-color: #27ae60; 
            outline: none; 
        }
        textarea { 
            resize: vertical; 
            min-height: 100px; 
        }
        
        .file-upload { 
            border: 2px dashed #bdc3c7; 
            padding: 20px; 
            text-align: center; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: border-color 0.3s; 
        }
        .file-upload:hover { 
            border-color: #27ae60; 
        }
        .file-upload input { 
            display: none; 
        }
        .file-preview { 
            margin-top: 10px; 
            text-align: center; 
        }
        .file-preview img { 
            max-width: 200px; 
            max-height: 200px; 
            border-radius: 8px; 
        }
        
        .btn { 
            background: #27ae60; 
            color: white; 
            padding: 14px 28px; 
            border: none; 
            border-radius: 8px; 
            font-size: 16px; 
            font-weight: bold; 
            cursor: pointer; 
            width: 100%; 
            transition: background 0.3s; 
        }
        .btn:hover { 
            background: #219150; 
        }
        .btn:disabled { 
            background: #bdc3c7; 
            cursor: not-allowed; 
        }
        
        .success-message { 
            background: #d4edda; 
            color: #155724; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            border-left: 4px solid #27ae60; 
        }
        .error-message { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            border-left: 4px solid #e74c3c; 
        }
        
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 15px; 
        }
        
        .ai-suggestion { 
            background: #e8f4fd; 
            padding: 15px; 
            border-radius: 8px; 
            margin-top: 10px; 
            border-left: 4px solid #3498db; 
        }
        .ai-suggestion h4 { 
            margin: 0 0 10px 0; 
            color: #2c3e50; 
        }
        
        @media (max-width: 768px) {
            .form-row { 
                grid-template-columns: 1fr; 
            }
        }
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
        <a href="product_analytics.php">Product Analytics</a>
        <a href="order_history.php">Order History</a>
        <a href="reports.php">Reports</a>
        <a href="profile.php">Profile</a>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
    <div class="container">
        <h1>Add New Product</h1>
        
        <?php if($success_message): ?>
            <div class="success-message">
                ✅ <?= htmlspecialchars($success_message) ?>
                <div style="margin-top: 10px;">
                    <a href="my_products.php" style="color: #155724; font-weight: bold;">View My Products</a> | 
                    <a href="add_product.php" style="color: #155724; font-weight: bold;">Add Another Product</a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="error-message">❌ <?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <div class="form-group">
                    <label for="name" class="required">Product Name</label>
                    <input type="text" id="name" name="name" 
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                           placeholder="e.g., Organic Tomatoes, Fresh Carrots" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category" class="required">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="Vegetables" <?= ($_POST['category'] ?? '') === 'Vegetables' ? 'selected' : '' ?>>Vegetables</option>
                            <option value="Fruits" <?= ($_POST['category'] ?? '') === 'Fruits' ? 'selected' : '' ?>>Fruits</option>
                            <option value="Grains" <?= ($_POST['category'] ?? '') === 'Grains' ? 'selected' : '' ?>>Grains</option>
                            <option value="Spices" <?= ($_POST['category'] ?? '') === 'Spices' ? 'selected' : '' ?>>Spices</option>
                            <option value="Herbs" <?= ($_POST['category'] ?? '') === 'Herbs' ? 'selected' : '' ?>>Herbs</option>
                            <option value="Other" <?= ($_POST['category'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="unit" class="required">Unit</label>
                        <select id="unit" name="unit" required>
                            <option value="">Select Unit</option>
                            <option value="kg" <?= ($_POST['unit'] ?? '') === 'kg' ? 'selected' : '' ?>>Kilogram (kg)</option>
                            <option value="g" <?= ($_POST['unit'] ?? '') === 'g' ? 'selected' : '' ?>>Gram (g)</option>
                            <option value="lb" <?= ($_POST['unit'] ?? '') === 'lb' ? 'selected' : '' ?>>Pound (lb)</option>
                            <option value="piece" <?= ($_POST['unit'] ?? '') === 'piece' ? 'selected' : '' ?>>Piece</option>
                            <option value="bunch" <?= ($_POST['unit'] ?? '') === 'bunch' ? 'selected' : '' ?>>Bunch</option>
                            <option value="bag" <?= ($_POST['unit'] ?? '') === 'bag' ? 'selected' : '' ?>>Bag</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity" class="required">Available Quantity</label>
                        <input type="number" id="quantity" name="quantity" 
                               value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>" 
                               min="0.1" step="0.1" placeholder="e.g., 50.5" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="price" class="required">Price per Unit (LKR)</label>
                        <input type="number" id="price" name="price" 
                               value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" 
                               min="1" step="0.01" placeholder="e.g., 150.00" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="harvest_date" class="required">Harvest Date</label>
                    <input type="date" id="harvest_date" name="harvest_date" 
                           value="<?= htmlspecialchars($_POST['harvest_date'] ?? date('Y-m-d')) ?>" 
                           max="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Product Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Describe your product (quality, freshness, special features, etc.)"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="product_image">Product Image</label>
                    <div class="file-upload" onclick="document.getElementById('product_image').click()">
                        <div style="font-size: 48px; color: #bdc3c7;">📷</div>
                        <div style="color: #7f8c8d; margin: 10px 0;">
                            Click to upload product image<br>
                            <small>Recommended: 500x500px, JPG/PNG (Max 2MB)</small>
                        </div>
                        <input type="file" id="product_image" name="product_image" 
                               accept="image/jpeg,image/png,image/gif" onchange="previewImage(this)">
                    </div>
                    <div class="file-preview" id="imagePreview"></div>
                </div>
                
                <!-- AI Price Suggestion Section -->
                <div class="ai-suggestion" id="priceSuggestion" style="display: none;">
                    <h4>💡 AI Price Suggestion</h4>
                    <p>Based on current market trends, we suggest: <strong id="suggestedPrice">0.00</strong> LKR</p>
                    <p style="font-size: 14px; color: #666;" id="suggestionReason"></p>
                    <button type="button" onclick="useSuggestedPrice()" style="background: #3498db; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                        Use Suggested Price
                    </button>
                </div>
                
                <button type="submit" class="btn">Add Product to Marketplace</button>
            </form>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="my_products.php" style="color: #27ae60; text-decoration: none; font-weight: bold;">
                ← Back to My Products
            </a>
        </div>
    </div>

    <script>
        // Image preview functionality
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = '';
            }
        }
        
        // AI Price Suggestion (Simulated - you can integrate with your AI API)
        const priceInput = document.getElementById('price');
        const categorySelect = document.getElementById('category');
        const priceSuggestion = document.getElementById('priceSuggestion');
        const suggestedPrice = document.getElementById('suggestedPrice');
        const suggestionReason = document.getElementById('suggestionReason');
        
        // Market price data (you can replace this with API call)
        const marketPrices = {
            'Vegetables': { min: 80, max: 300, avg: 150 },
            'Fruits': { min: 100, max: 500, avg: 250 },
            'Grains': { min: 120, max: 400, avg: 200 },
            'Spices': { min: 200, max: 1000, avg: 500 },
            'Herbs': { min: 150, max: 600, avg: 300 },
            'Other': { min: 50, max: 200, avg: 100 }
        };
        
        function suggestPrice() {
            const category = categorySelect.value;
            const currentPrice = parseFloat(priceInput.value) || 0;
            
            if (category && marketPrices[category]) {
                const marketData = marketPrices[category];
                let suggested = marketData.avg;
                let reason = '';
                
                // Simple suggestion logic
                if (currentPrice > marketData.max) {
                    suggested = marketData.avg;
                    reason = `Your price is above market maximum. Suggested competitive price.`;
                } else if (currentPrice < marketData.min) {
                    suggested = marketData.avg;
                    reason = `Your price is below market minimum. Suggested fair price.`;
                } else {
                    suggested = currentPrice; // Keep current if reasonable
                    reason = `Your price is within market range. Good job!`;
                }
                
                suggestedPrice.textContent = suggested.toFixed(2);
                suggestionReason.textContent = reason;
                priceSuggestion.style.display = 'block';
            } else {
                priceSuggestion.style.display = 'none';
            }
        }
        
        function useSuggestedPrice() {
            priceInput.value = suggestedPrice.textContent;
            priceSuggestion.style.display = 'none';
        }
        
        // Event listeners for price suggestion
        priceInput.addEventListener('input', suggestPrice);
        categorySelect.addEventListener('change', suggestPrice);
        
        // Form validation
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const quantity = parseFloat(document.getElementById('quantity').value);
            const price = parseFloat(document.getElementById('price').value);
            
            if (quantity <= 0) {
                alert('Please enter a valid quantity greater than 0');
                e.preventDefault();
                return;
            }
            
            if (price <= 0) {
                alert('Please enter a valid price greater than 0');
                e.preventDefault();
                return;
            }
            
            // You can add more validation here
        });
        
        // Set max date for harvest date to today
        document.getElementById('harvest_date').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>