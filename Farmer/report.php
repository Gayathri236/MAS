<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Colombo');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../mongodb_config.php';

use Dompdf\Dompdf;

// ------------------ Check login ------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Collections
$usersCollection = $db->users;
$ordersCollection = $db->orders;
$productsCollection = $db->products;
$reportsCollection = $db->reports;

// ------------------------- PROFILE -------------------------
$farmerProfile = $usersCollection->findOne(['username' => $username, 'role' => 'farmer']);
if (!$farmerProfile) {
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

// Check if report generation is requested
$pdfFileName = null;
$reportData = null;
$generated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    // Get form data
    $month = $_POST['month'] ?? date('F');
    $year = $_POST['year'] ?? date('Y');
    $customNetProfit = isset($_POST['net_profit']) ? floatval($_POST['net_profit']) : null;
    
    $reportDate = date('Y-m-d H:i:s');
    $farmerName = htmlspecialchars($farmerProfile['name'] ?? 'N/A');
    $farmerEmail = $farmerProfile['email'] ?? $username;

    // ------------------ Fetch orders for selected month ------------------
    $ordersCursor = $ordersCollection->find([
        'farmer' => $username,
        'status' => ['$in' => ['accepted', 'completed']],
        'order_date' => [
            '$gte' => new MongoDB\BSON\UTCDateTime(strtotime("first day of $month $year")*1000),
            '$lt'  => new MongoDB\BSON\UTCDateTime(strtotime("first day of next month $year")*1000)
        ]
    ]);

    $orders = iterator_to_array($ordersCursor);

    // ------------------ Summary ------------------
    $totalQuantity = 0;
    $totalIncome = 0;
    $productStats = [];

    foreach ($orders as $order) {
        $qty = (int)($order['quantity'] ?? 0);
        $totalAmount = (float)($order['total_amount'] ?? 0);
        
        $totalQuantity += $qty;
        $totalIncome += $totalAmount;
        
        // Product stats
        $productName = 'Unknown';
        if (!empty($order['product_id'])) {
            try {
                $pid = $order['product_id'];
                if (!($pid instanceof MongoDB\BSON\ObjectId) && is_string($pid)) {
                    $pid = new MongoDB\BSON\ObjectId($pid);
                }
                $pdoc = $productsCollection->findOne(['_id' => $pid]);
                if ($pdoc && !empty($pdoc['name'])) {
                    $productName = $pdoc['name'];
                    $productCategory = $pdoc['category'] ?? 'Unknown';
                }
            } catch (Exception $e) { /* ignore */ }
        }
        
        if (!isset($productStats[$productName])) {
            $productStats[$productName] = [
                'quantity' => 0,
                'revenue' => 0,
                'category' => $productCategory ?? 'Unknown'
            ];
        }
        $productStats[$productName]['quantity'] += $qty;
        $productStats[$productName]['revenue'] += $totalAmount;
    }

    // Calculate or use custom net profit
    if ($customNetProfit !== null && $customNetProfit > 0) {
        $netProfit = $customNetProfit;
    } else {
        $netProfit = $totalIncome * 0.85; // Default 15% costs if not specified
    }

    // ------------------ Build HTML for PDF ------------------
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Monthly Sales Report - ' . $month . ' ' . $year . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #264653; margin-bottom: 10px; }
            .header h2 { color: #2a9d8f; margin-bottom: 20px; }
            .summary-box { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 10px; border-left: 5px solid #2a9d8f; }
            .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
            .stat-card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
            .stat-card h3 { color: #264653; margin-bottom: 10px; font-size: 14px; }
            .stat-value { font-size: 24px; font-weight: bold; color: #2a9d8f; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            th { background: #264653; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f8f9fa; }
            .footer { text-align: center; margin-top: 40px; color: #666; font-size: 12px; }
            .logo { text-align: center; margin-bottom: 20px; }
            .logo h1 { color: #264653; }
            .logo .subtitle { color: #2a9d8f; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="logo">
            <h1>MAS</h1>
            <div class="subtitle">Market Automation System</div>
        </div>
         
        <div class="header">
            <h1>MONTHLY SALES REPORT</h1>
            <h2>' . $month . ' ' . $year . '</h2>
            <p><strong>Generated On:</strong> ' . $reportDate . '</p>
        </div>
        
        <div class="summary-box">
            <h3>FARMER INFORMATION</h3>
            <p><strong>Name:</strong> ' . $farmerName . '</p>
            <p><strong>Email:</strong> ' . $farmerEmail . '</p>
            <p><strong>Location:</strong> ' . htmlspecialchars($displayAddress) . '</p>
        </div>
        
        <div class="summary-grid">
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="stat-value">' . count($orders) . '</div>
            </div>
            <div class="stat-card">
                <h3>Quantity Sold</h3>
                <div class="stat-value">' . $totalQuantity . '</div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="stat-value">LKR ' . number_format($totalIncome, 2) . '</div>
            </div>
            <div class="stat-card">
                <h3>Net Profit</h3>
                <div class="stat-value">LKR ' . number_format($netProfit, 2) . '</div>
            </div>
        </div>
        <h3>PRODUCT PERFORMANCE</h3>
        <table>
            <tr>
                <th>Product Name</th>
                <th>Category</th>
                <th>Quantity Sold</th>
                <th>Revenue (LKR)</th>
            </tr>';
    
    foreach ($productStats as $productName => $stats) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($productName) . '</td>
                    <td>' . htmlspecialchars($stats['category']) . '</td>
                    <td>' . $stats['quantity'] . '</td>
                    <td>' . number_format($stats['revenue'], 2) . '</td>
                  </tr>';
    }
    
    $html .= '</table>
        
        <h3>ORDER DETAILS</h3>
        <table>
            <tr>
                <th>Order ID</th>
                <th>Product Name</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total Amount</th>
                <th>Order Date</th>
                <th>Status</th>
            </tr>';
    
    foreach ($orders as $order) {
        $productName = 'Unknown';
        if (!empty($order['product_id'])) {
            try {
                $pid = $order['product_id'];
                if (!($pid instanceof MongoDB\BSON\ObjectId) && is_string($pid)) {
                    $pid = new MongoDB\BSON\ObjectId($pid);
                }
                $pdoc = $productsCollection->findOne(['_id' => $pid]);
                if ($pdoc && !empty($pdoc['name'])) $productName = $pdoc['name'];
            } catch (Exception $e) { /* ignore */ }
        }

        $orderDate = '-';
        if (!empty($order['order_date']) && $order['order_date'] instanceof MongoDB\BSON\UTCDateTime) {
            $dt = $order['order_date']->toDateTime();
            $dt->setTimezone(new DateTimeZone('Asia/Colombo'));
            $orderDate = $dt->format('Y-m-d');
        }
        
        $status = $order['status'] ?? 'pending';

        $html .= '<tr>
                    <td>' . substr((string)$order['_id'], -8) . '</td>
                    <td>' . htmlspecialchars($productName) . '</td>
                    <td>' . ($order['quantity'] ?? 0) . '</td>
                    <td>LKR ' . number_format(($order['unit_price'] ?? 0), 2) . '</td>
                    <td>LKR ' . number_format(($order['total_amount'] ?? 0), 2) . '</td>
                    <td>' . $orderDate . '</td>
                    <td>' . ucfirst($status) . '</td>
                  </tr>';
    }
    
    $html .= '</table>
        
        <div class="footer">
            <p>This report was automatically generated by DMAS - Digital Market Access System</p>
            <p>Report ID: ' . uniqid() . ' | Valid for reference purposes only</p>
        </div>
    </body>
    </html>';

    // ------------------ Generate PDF ------------------
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Save PDF to server
    $pdfFileName = "monthly_sales_report_" . strtolower($month) . "_" . $year . "_" . uniqid() . ".pdf";
    $pdfFilePath = __DIR__ . "/../reports/" . $pdfFileName;
    
    // Create reports directory if it doesn't exist
    if (!is_dir(__DIR__ . "/../reports")) {
        mkdir(__DIR__ . "/../reports", 0755, true);
    }
    
    file_put_contents($pdfFilePath, $dompdf->output());

    // ------------------ Save report metadata to MongoDB ------------------
    $reportData = [
        'farmer' => $username,
        'farmer_name' => $farmerName,
        'farmer_email' => $farmerEmail,
        'month' => $month,
        'year' => $year,
        'generated_at' => new MongoDB\BSON\UTCDateTime(),
        'file_name' => $pdfFileName,
        'file_path' => $pdfFilePath,
        'total_orders' => count($orders),
        'total_quantity' => $totalQuantity,
        'total_income' => $totalIncome,
        'net_profit' => $netProfit,
        'custom_net_profit' => $customNetProfit !== null
    ];
    $reportsCollection->insertOne($reportData);
    
    $generated = true;
}

// Fetch recent reports for this farmer
$recentReports = $reportsCollection->find(
    ['farmer' => $username],
    ['sort' => ['generated_at' => -1], 'limit' => 5]
)->toArray();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/report.css">
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
        <a href="report.php" class="active">
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
    <h1><i class="fas fa-file-export"></i> Sales Reports</h1>
    
    <!-- Report Generator -->
    <div class="report-generator">
        <h2 style="color: #264653; margin-bottom: 25px;">Generate Monthly Report</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="month">Month</label>
                    <select id="month" name="month" required>
                        <?php
                        $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                  'July', 'August', 'September', 'October', 'November', 'December'];
                        $currentMonth = date('F');
                        foreach ($months as $monthOption) {
                            $selected = ($monthOption === $currentMonth) ? 'selected' : '';
                            echo "<option value='$monthOption' $selected>$monthOption</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="year">Year</label>
                    <select id="year" name="year" required>
                        <?php
                        $currentYear = date('Y');
                        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                            $selected = ($y == $currentYear) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="net_profit">Net Profit (LKR) <small style="color: #666; font-weight: normal;">- Optional: Enter custom net profit or leave empty for auto-calculation</small></label>
                <input type="number" 
                       id="net_profit" 
                       name="net_profit" 
                       step="0.01" 
                       min="0" 
                       placeholder="Enter custom net profit (optional)">
            </div>
            
            <button type="submit" name="generate_report" class="btn">
                <i class="fas fa-file-pdf"></i> Generate Monthly Report
            </button>
        </form>
    </div>
    
    <?php if ($generated): ?>
        <!-- Generated Report -->
        <div class="generated-report">
            <div class="report-header">
                <h2>Report Generated Successfully!</h2>
                <div class="report-actions">
                    <a href="../reports/<?= $pdfFileName ?>" target="_blank" class="btn-view">
                        <i class="fas fa-eye"></i> View Report
                    </a>
                    <a href="../reports/<?= $pdfFileName ?>" download class="btn-download">
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                </div>
            </div>
            
            <div class="pdf-preview">
                <iframe src="../reports/<?= $pdfFileName ?>"></iframe>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Recent Reports -->
    <div class="recent-reports">
        <h2 style="color: #264653; margin-bottom: 25px;">Recent Reports</h2>
        
        <?php if (count($recentReports) > 0): ?>
            <div class="reports-list">
                <?php foreach ($recentReports as $report): ?>
                    <div class="report-item">
                        <div class="report-info">
                            <h3><?= htmlspecialchars($report['month'] ?? 'Unknown') ?> <?= htmlspecialchars($report['year'] ?? '') ?></h3>
                            <p>Generated on: <?php 
                                if (isset($report['generated_at']) && $report['generated_at'] instanceof MongoDB\BSON\UTCDateTime) {
                                    $dt = $report['generated_at']->toDateTime();
                                    $dt->setTimezone(new DateTimeZone('Asia/Colombo'));
                                    echo $dt->format('Y-m-d H:i:s');
                                }
                            ?></p>
                        </div>
                        
                        <div class="report-stats">
                            <div class="stat">
                                <div class="stat-value"><?= $report['total_orders'] ?? 0 ?></div>
                                <div class="stat-label">Orders</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value"><?= $report['total_quantity'] ?? 0 ?></div>
                                <div class="stat-label">Quantity</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">LKR <?= number_format($report['total_income'] ?? 0, 0) ?></div>
                                <div class="stat-label">Revenue</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">LKR <?= number_format($report['net_profit'] ?? 0, 0) ?></div>
                                <div class="stat-label">Profit</div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 10px;">
                            <a href="../reports/<?= htmlspecialchars($report['file_name'] ?? '') ?>" 
                               target="_blank" 
                               class="btn-view" 
                               style="padding: 8px 15px; font-size: 0.9rem;">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-pdf"></i>
                <h3>No Reports Yet</h3>
                <p>Generate your first monthly sales report using the form above.</p>
                <p>Reports will help you track your sales performance and make better business decisions.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Add animation to generated report
<?php if ($generated): ?>
document.addEventListener('DOMContentLoaded', function() {
    const generatedReport = document.querySelector('.generated-report');
    if (generatedReport) {
        generatedReport.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
<?php endif; ?>

// Auto-calculate net profit if not provided
document.addEventListener('DOMContentLoaded', function() {
    const netProfitInput = document.getElementById('net_profit');
    
    // Show hint for auto-calculation
    netProfitInput.addEventListener('focus', function() {
        if (!this.value) {
            this.placeholder = 'Leave empty for auto-calculation (85% of revenue)';
        }
    });
    
    netProfitInput.addEventListener('blur', function() {
        if (!this.value) {
            this.placeholder = 'Enter custom net profit (optional)';
        }
    });
});
</script>
</body>
</html>