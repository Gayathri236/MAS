<?php
$api_base = "https://extremal-relentlessly-layton.ngrok-free.dev"; // Your ngrok URL
$veg = $_GET['vegetable_name'] ?? '';
if ($veg != '') {
    $veg_enc = rawurlencode($veg);
    $url = "{$api_base}/predict_next7/{$veg_enc}";
    $resp = @file_get_contents($url);
    if ($resp === FALSE) {
        $error = "Could not connect to prediction API. Make sure Colab + ngrok are running.";
    } else {
        $json = json_decode($resp, true);
        if (isset($json['error'])) {
            $error = "API error: " . htmlspecialchars($json['error']);
        } else {
            $forecast = $json['forecast'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>7-day Veg Price Forecast</title>
<link rel="stylesheet" href="assets/prediction.css">
</head>
<body>
     <nav class="navbar">
            <div class="brand">🌾 DMAS - Wholesaler Portal</div>

            <div class="menu" id="menu">
                <a href="dashboard.php">🏠 Dashboard</a>
                <a href="product_marketplace.php">🛒 Marketplace</a>
                <a href="order_management.php">📦 Orders</a>
                <a href="prediction copy.php"class="active">📈 Price Prediction</a>
                <a href="view_workers.php">👨‍🔧 View Workers</a>
                <a href="profile.php">👤 My Profile</a>
                <a href="../logout.php" class="logout" onclick="return confirm('Are you sure you want to logout?');">🚪 Logout</a>
            </div>
            <div class="hamburger" onclick="toggleMenu()">
            <span></span><span></span><span></span>
     </nav>
        <div class="container">
        <h1>7-day Vegetable Price Forecast</h1>

        <form method="get">
            <input type="text" name="vegetable_name" placeholder="Enter vegetable name" value="<?= htmlspecialchars($veg); ?>" required>
            <button type="submit">Get Forecast</button>
        </form>

        <?php if(!empty($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <?php if(!empty($forecast)): ?>
            <div class="forecast-card">
                <h2>Forecast for <?= htmlspecialchars($veg) ?> (from <?= htmlspecialchars($json['from'] ?? 'today') ?>)</h2>
                <table>
                    <tr><th>Date</th><th>Predicted Price (LKR/kg)</th><th>Low</th><th>High</th></tr>
                    <?php foreach($forecast as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['ds']) ?></td>
                        <td><?= number_format($row['predicted_price'],2) ?></td>
                        <td><?= number_format($row['low'],2) ?></td>
                        <td><?= number_format($row['high'],2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
        </div>
        <script>
            function toggleMenu() {
                document.getElementById('menu').classList.toggle('show');
            }
       </script>
</body>
</html>
