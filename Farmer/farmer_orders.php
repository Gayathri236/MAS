<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only farmers can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];

$ordersCollection = $db->orders;
$productsCollection = $db->products;
$usersCollection = $db->users;

// Handle order actions
if (isset($_GET['complete_order'])) {
    $order_id = $_GET['complete_order'];
    try {
        $result = $ordersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($order_id), 'farmer' => $username],
            ['$set' => ['status' => 'completed', 'completed_date' => new MongoDB\BSON\UTCDateTime()]]
        );
        header("Location: farmer_orders.php?success=1");
        exit();
    } catch (Exception $e) {
        header("Location: farmer_orders.php?error=1");
        exit();
    }
}

if (isset($_GET['accept_order'])) {
    $order_id = $_GET['accept_order'];
    try {
        $result = $ordersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($order_id), 'farmer' => $username],
            ['$set' => ['status' => 'accepted']]
        );
        header("Location: farmer_orders.php?success=2");
        exit();
    } catch (Exception $e) {
        header("Location: farmer_orders.php?error=2");
        exit();
    }
}

if (isset($_GET['reject_order'])) {
    $order_id = $_GET['reject_order'];
    try {
        $result = $ordersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($order_id), 'farmer' => $username],
            ['$set' => ['status' => 'rejected']]
        );
        header("Location: farmer_orders.php?success=3");
        exit();
    } catch (Exception $e) {
        header("Location: farmer_orders.php?error=3");
        exit();
    }
}

// Fetch orders
try {
    $ordersCursor = $ordersCollection->find(
        ['farmer' => $username],
        ['sort' => ['order_date' => -1]]
    );
    $orders = iterator_to_array($ordersCursor);
} catch (Exception $e) {
    $orders = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Orders Received - DMAS</title>
    <style>
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: #f4f7f9; 
            margin: 0; 
            color: #333; 
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
        .container { 
            width: 95%; 
            max-width: 1200px; 
            margin: 20px auto; 
        }
        
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .order-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        
        .order-card img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .image-placeholder {
            width: 120px;
            height: 120px;
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
            text-align: center;
        }
        
        .order-details {
            flex: 1;
        }
        
        .order-details h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 18px;
        }
        
        .order-details p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .status.pending { background: #fff3cd; color: #856404; }
        .status.accepted { background: #d1ecf1; color: #0c5460; }
        .status.completed { background: #d4edda; color: #155724; }
        .status.rejected { background: #f8d7da; color: #721c24; }
        .status.delivery { background: #cce7ff; color: #004085; }
        
        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 150px;
        }
        
        .actions button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
            font-size: 12px;
        }
        
        .actions .complete { background: #27ae60; color: white; }
        .actions .complete:hover { background: #219150; }
        
        .actions .accept { background: #3498db; color: white; }
        .actions .accept:hover { background: #2980b9; }
        
        .actions .reject { background: #e74c3c; color: white; }
        .actions .reject:hover { background: #c0392b; }
        
        .actions .contact { background: #25D366; color: white; }
        .actions .contact:hover { background: #128C7E; }
        
        .actions a {
            text-decoration: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
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
        
        .debug-info {
            background: #fff3cd;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .order-card {
                flex-direction: column;
                text-align: center;
            }
            
            .actions {
                flex-direction: row;
                justify-content: center;
                flex-wrap: wrap;
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
         <a href="">Price Intelligence</a>
        <a href="">Product Analytics</a>
        <a href="">Order History</a>
        <a href="reports.php">Reports</a>
        <a href="profile.php">Profile</a>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

    <div class="container">
        <h1>Received Orders</h1>
        
        
        <?php if(isset($_GET['success'])): ?>
            <div class="success-message">
                <?php 
                switch($_GET['success']) {
                    case 1: echo "✅ Order marked as completed successfully!"; break;
                    case 2: echo "✅ Order accepted successfully!"; break;
                    case 3: echo "✅ Order rejected successfully!"; break;
                    default: echo "✅ Action completed successfully!";
                }
                ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['error'])): ?>
            <div class="error-message">
                ❌ Failed to process the order. Please try again.
            </div>
        <?php endif; ?>
        
        <?php if(count($orders) > 0): ?>
            <?php foreach($orders as $order): 
                $order_id = $order['_id'] ?? '';
                $product_id = $order['product_id'] ?? null;
                $wholesaler_username = $order['wholesaler'] ?? 'Unknown Wholesaler';
                $quantity = $order['quantity'] ?? 0;
                $unit_price = $order['unit_price'] ?? 0;
                $total_amount = $order['total_amount'] ?? ($quantity * $unit_price);
                $status = $order['status'] ?? 'pending';
                $order_date = $order['order_date'] ?? new MongoDB\BSON\UTCDateTime();
                
                // Get product details
                $product = [];
                $product_image = 'image';
                $product_name = 'name';
                $product_category = 'category';
                $product_unit = 'units';
                
                if ($product_id) {
                    try {
                        // Method 1: Direct product lookup
                        $product = $productsCollection->findOne(['_id' => $product_id]);
                        
                        if ($product) {
                            $product_image = $product['image'] ?? 'default.jpg';
                            $product_name = $product['name'] ?? 'Unknown Product';
                            $product_category = $product['category'] ?? 'Not specified';
                            $product_unit = $product['unit'] ?? 'units';
                        } else {
                            // Method 2: Check if product data is embedded in order
                            $product_name = $order['product_name'] ?? 'Unknown Product';
                              $product_category = $product['category'] ?? 'Not specified';
                            $product_unit = $order['unit'] ?? 'units';
                        }
                        } catch (Exception $e) {
                        error_log("Error fetching product: " . $e->getMessage());
                        // Method 3: Use order data as fallback
                        $product_name = $order['product_name'] ?? 'Unknown Product';
                           $product_category = $product['category'] ?? 'Not specified';
                        $product_unit = $order['unit'] ?? 'units';
                    }
                } else {
                    // If no product_id, try to get data from order itself
                    $product_name = $order['product_name'] ?? 'Unknown Product';
                    $product_category = $product['category'] ?? 'Not specified';
                    $product_unit = $order['unit'] ?? 'units';
                }
                
                // Get wholesaler details
                $wholesaler = $usersCollection->findOne(['username' => $wholesaler_username]) ?? [];
                $wholesaler_name = $wholesaler['name'] ?? $wholesaler_username;
                $wholesaler_contact = $wholesaler['phone'] ?? $wholesaler['contact'] ?? '';
                
                // Format date
                $order_date_formatted = date('d M Y, H:i', $order_date->toDateTime()->getTimestamp());
                
                // Check if image exists
                $image_path = "../uploads/products/" . $product_image;
                $image_exists = file_exists($image_path) && is_file($image_path);
            ?>
            <div class="order-card">
                <!-- Image with proper error handling -->
                <?php if($image_exists && $product_image !== 'default.jpg'): ?>
                    <img src="../uploads/products/<?= htmlspecialchars($product_image) ?>" 
                         alt="<?= htmlspecialchars($product_name) ?>"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="image-placeholder" style="display: none;">
                        No Image<br>Available
                    </div>
                <?php else: ?>
                    <div class="image-placeholder">
                        No Image<br>Available
                    </div>
                <?php endif; ?>
                
                <div class="order-details">
                    <h3><?= htmlspecialchars($product_name) ?></h3>
                    <p><strong>Category:</strong> <?= htmlspecialchars($product_category) ?></p>
                    <p><strong>Quantity:</strong> <?= number_format($quantity, 2) ?> <?= htmlspecialchars($product_unit) ?></p>
                    <p><strong>Price per unit:</strong> LKR <?= number_format($unit_price, 2) ?></p>
                    <p><strong>Total Price:</strong> LKR <?= number_format($total_amount, 2) ?></p>
                    <p><strong>Wholesaler:</strong> <?= htmlspecialchars($wholesaler_name) ?></p>
                    <p><strong>Order Date:</strong> <?= $order_date_formatted ?></p>
                    <span class="status <?= htmlspecialchars($status) ?>"><?= ucfirst($status) ?></span>
                </div>
                
                <div class="actions">
                    <?php if($status === 'pending'): ?>
                        <a href="?accept_order=<?= $order_id ?>"><button class="accept">Accept Order</button></a>
                        <a href="?reject_order=<?= $order_id ?>" onclick="return confirm('Are you sure you want to reject this order?')">
                            <button class="reject">Reject Order</button>
                        </a>
                    <?php elseif($status === 'accepted'): ?>
                        <a href="?complete_order=<?= $order_id ?>"><button class="complete">Mark as Completed</button></a>
                    <?php endif; ?>
                    
                    <?php if(!empty($wholesaler_contact)): 
                        $whatsapp_number = preg_replace('/[^0-9]/', '', $wholesaler_contact);
                        if (!empty($whatsapp_number)) {
                            $wa_link = "https://wa.me/$whatsapp_number"; 
                    ?>
                        <a href="<?= $wa_link ?>" target="_blank"><button class="contact">Contact via WhatsApp</button></a>
                    <?php } endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <h3>No orders received yet</h3>
                <p>When wholesalers place orders for your products, they will appear here.</p>
                <p>Make sure your products are listed and visible in the marketplace.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Enhanced image error handling
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.order-card img');
            images.forEach(img => {
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    const placeholder = this.nextElementSibling;
                    if (placeholder && placeholder.classList.contains('image-placeholder')) {
                        placeholder.style.display = 'flex';
                    }
                });
                
                img.addEventListener('load', function() {
                    const placeholder = this.nextElementSibling;
                    if (placeholder && placeholder.classList.contains('image-placeholder')) {
                        placeholder.style.display = 'none';
                    }
                });
            });
            
            // Reject order confirmation
            const rejectLinks = document.querySelectorAll('a[href*="reject_order"]');
            rejectLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to reject this order? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>