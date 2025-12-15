<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check login & role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.php");
    exit();
}

$workersCollection = $db->workers;

// Fetch all workers
$workers = $workersCollection->find([], ['sort' => ['registered_date' => -1]]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Workers - DMAS</title>
<link rel="stylesheet" href="assets/view_workers.css">
</head>
<body>
       <nav class="navbar">
            <div class="brand">🌾 DMAS - Wholesaler Portal</div>
            <div class="hamburger" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <div class="menu" id="menu">
                <a href="dashboard.php">🏠 Dashboard</a>
                <a href="product_marketplace.php">🛒 Marketplace</a>
                <a href="order_management.php">📦 Orders</a>
                <a href="prediction copy.php">📈 Price Prediction</a>
                <a href="view_workers.php"class="active">👨‍🔧 View Workers</a>
                <a href="profile.php">👤 My Profile</a>
                <a href="../logout.php" class="logout" onclick="return confirm('Are you sure you want to logout?');">🚪 Logout</a>
            </div>
     </nav>
<div class="container">
    <h1>Workers List</h1>
    <table>
        <thead>
            <tr>
                <th>Worker ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Duties</th>
                <th>Status</th>
                <th>Registered Date</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $hasData = false;
            foreach ($workers as $w) {
                $hasData = true;
                $statusClass = strtolower($w['status']) === 'active' ? 'active' : 'inactive';
                $regDate = isset($w['registered_date']) && $w['registered_date'] instanceof MongoDB\BSON\UTCDateTime
                            ? $w['registered_date']->toDateTime()->format('Y-m-d')
                            : '-';
                echo "<tr>
                        <td>{$w['worker_id']}</td>
                        <td>{$w['name']}</td>
                        <td>{$w['phone']}</td>
                        <td>{$w['duties']}</td>
                        <td><span class='status {$statusClass}'>" . ucfirst($w['status']) . "</span></td>
                        <td>{$regDate}</td>
                      </tr>";
            }

            if (!$hasData) {
                echo "<tr><td colspan='6'>No workers found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>
    <script>
        function toggleMenu() {
            document.getElementById('menu').classList.toggle('show');
        }
    </script>
</body>
</html>
