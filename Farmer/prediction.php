<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Colombo');

require __DIR__ . '/../mongodb_config.php';

// ------------------ Check login ------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Collections
$usersCollection = $db->users;
$productsCollection = $db->products;

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

// Fetch farmer's products for suggestions
$farmerProducts = $productsCollection->find(
    ['farmer' => $username, 'status' => 'active'],
    ['limit' => 20]
)->toArray();

$productSuggestions = [];
foreach ($farmerProducts as $product) {
    $productSuggestions[] = htmlspecialchars($product['name']);
}

// API Configuration
$api_base = "https://extremal-relentlessly-layton.ngrok-free.dev"; // REPLACE with your ngrok public URL printed in Colab

// Handle prediction request
$veg = $_GET['vegetable_name'] ?? '';
$forecast = null;
$error = null;
$apiData = null;

if ($veg != '') {
    $veg_enc = rawurlencode($veg);
    $url = "{$api_base}/predict_next7/{$veg_enc}";
    
    // Use cURL for better error handling
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($resp === FALSE || $httpCode != 200) {
        $error = "Could not connect to prediction API. Please check your internet connection and ensure the API server is running.";
    } else {
        $json = json_decode($resp, true);
        if (isset($json['error'])) {
            $error = "API error: " . htmlspecialchars($json['error']);
        } elseif (isset($json['forecast'])) {
            $forecast = $json['forecast'];
            $apiData = $json;
        } else {
            $error = "Unexpected response from API.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Prediction - Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/prediction.css">
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
        <a href="prediction.php" class="active">
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
    <h1><i class="fas fa-chart-line"></i> Price Prediction</h1>
    
    <!-- Prediction Form -->
    <div class="prediction-form">
        <h2 style="color: #264653; margin-bottom: 20px;">Get 7-Day Price Forecast</h2>
        <form method="get">
            <div class="form-group">
                <label for="vegetable_name">Product Name</label>
                <input type="text" 
                       id="vegetable_name" 
                       name="vegetable_name" 
                       value="<?= htmlspecialchars($veg) ?>" 
                       placeholder="Enter Product name (e.g., Tomato, Carrot, Apple)"
                       required
                       autocomplete="off">
                
                <?php if (!empty($productSuggestions)): ?>
                <div class="suggestions">
                    <div>Your products: </div>
                    <div class="suggestion-tags">
                        <?php foreach ($productSuggestions as $suggestion): ?>
                            <div class="suggestion-tag" onclick="document.getElementById('vegetable_name').value = '<?= $suggestion ?>'">
                                <?= $suggestion ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-search"></i> Get Price Forecast
            </button>
        </form>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($forecast)): ?>
        <!-- Results Container -->
        <div class="results-container">
            <div class="results-header">
                <h2>7-Day Forecast for <span class="veg-name"><?= htmlspecialchars($veg) ?></span></h2>
                <div style="color: #666; font-size: 0.95rem;">
                    <i class="far fa-calendar-alt"></i> From <?= htmlspecialchars($apiData['from'] ?? 'today') ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <?php
                // Calculate stats
                $prices = array_column($forecast, 'predicted_price');
                $avgPrice = array_sum($prices) / count($prices);
                $minPrice = min($prices);
                $maxPrice = max($prices);
                $trend = end($prices) > reset($prices) ? 'up' : 'down';
                ?>
                <div class="stat-card">
                    <i class="fas fa-chart-line"></i>
                    <div class="stat-number">LKR <?= number_format($avgPrice, 2) ?></div>
                    <div class="stat-label">Average Price</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-arrow-down"></i>
                    <div class="stat-number">LKR <?= number_format($minPrice, 2) ?></div>
                    <div class="stat-label">Lowest Price</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-arrow-up"></i>
                    <div class="stat-number">LKR <?= number_format($maxPrice, 2) ?></div>
                    <div class="stat-label">Highest Price</div>
                </div>
            </div>

            <!-- Chart -->
            <div class="chart-container">
                <canvas id="priceChart"></canvas>
            </div>

            <!-- Forecast Table -->
            <table class="forecast-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Predicted Price (LKR/kg)</th>
                        <th>Low Range</th>
                        <th>High Range</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forecast as $index => $row): 
                        $prevPrice = $index > 0 ? $forecast[$index-1]['predicted_price'] : $row['predicted_price'];
                        $trend = $row['predicted_price'] > $prevPrice ? 'up' : ($row['predicted_price'] < $prevPrice ? 'down' : 'stable');
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['ds']) ?></td>
                        <td class="price-<?= $trend ?>">LKR <?= number_format($row['predicted_price'], 2) ?></td>
                        <td>LKR <?= number_format($row['low'], 2) ?></td>
                        <td>LKR <?= number_format($row['high'], 2) ?></td>
                        <td>
                            <?php if ($trend == 'up'): ?>
                                <span style="color: #2a9d8f;"><i class="fas fa-arrow-up"></i> Up</span>
                            <?php elseif ($trend == 'down'): ?>
                                <span style="color: #e74c3c;"><i class="fas fa-arrow-down"></i> Down</span>
                            <?php else: ?>
                                <span style="color: #666;"><i class="fas fa-minus"></i> Stable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Note -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 25px; border-left: 4px solid #2a9d8f;">
                <p style="color: #666; font-size: 0.9rem; margin: 0;">
                    <i class="fas fa-info-circle"></i> Based on historical data analysis and predictive modeling. Prices are in LKR per kg.
                </p>
            </div>
        </div>
    <?php elseif (empty($error) && empty($forecast) && !empty($veg)): ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar"></i>
            <h3>No Data Available</h3>
            <p>We couldn't find sufficient historical data for "<?= htmlspecialchars($veg) ?>" to generate a reliable forecast.</p>
            <p>Try with a different vegetable name or check your spelling.</p>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($forecast)): ?>
<script>
// Initialize the price chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('priceChart').getContext('2d');
    const forecastData = <?= json_encode($forecast) ?>;
    
    const dates = forecastData.map(item => item.ds);
    const prices = forecastData.map(item => item.predicted_price);
    const lowRange = forecastData.map(item => item.low);
    const highRange = forecastData.map(item => item.high);
    
    // Calculate trend line
    const firstPrice = prices[0];
    const lastPrice = prices[prices.length - 1];
    const trendColor = lastPrice > firstPrice ? '#2a9d8f' : (lastPrice < firstPrice ? '#e74c3c' : '#E9C46A');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'Predicted Price',
                    data: prices,
                    borderColor: trendColor,
                    backgroundColor: trendColor + '20',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Low Range',
                    data: lowRange,
                    borderColor: '#e74c3c',
                    borderWidth: 1,
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0
                },
                {
                    label: 'High Range',
                    data: highRange,
                    borderColor: '#2a9d8f',
                    borderWidth: 1,
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += 'LKR ' + context.parsed.y.toFixed(2) + '/kg';
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Price (LKR/kg)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'LKR ' + value.toFixed(2);
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });
    
    // Add animation to results container
    const resultsContainer = document.querySelector('.results-container');
    resultsContainer.style.opacity = '0';
    resultsContainer.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        resultsContainer.style.transition = 'all 0.5s ease';
        resultsContainer.style.opacity = '1';
        resultsContainer.style.transform = 'translateY(0)';
    }, 100);
});
</script>
<?php endif; ?>

<script>
// Add click event to suggestion tags
document.addEventListener('DOMContentLoaded', function() {
    const suggestionTags = document.querySelectorAll('.suggestion-tag');
    const vegetableInput = document.getElementById('vegetable_name');
    
    suggestionTags.forEach(tag => {
        tag.addEventListener('click', function() {
            vegetableInput.value = this.textContent.trim();
            vegetableInput.focus();
        });
    });
});
</script>
</body>
</html>