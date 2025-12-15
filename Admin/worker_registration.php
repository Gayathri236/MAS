<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

$workersCollection = $db->workers;
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $duties = $_POST['duties'] ?? '';

    // Auto-generate Worker ID (e.g., WKR001)
    $count = $workersCollection->countDocuments() + 1;
    $workerId = 'WKR' . str_pad($count, 3, '0', STR_PAD_LEFT);

    // Insert new worker
    $workersCollection->insertOne([
        'worker_id' => $workerId,
        'name' => $name,
        'address' => $address,
        'phone' => $phone,
        'email' => $email,
        'duties' => $duties,
        'status' => 'active',
        'registered_date' => new MongoDB\BSON\UTCDateTime()
    ]);

    $message = "Worker registered successfully! Worker ID: $workerId";
}

// Handle status updates (Activate/Deactivate/Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];

    if ($action === 'activate') {
        $workersCollection->updateOne(['worker_id' => $id], ['$set' => ['status' => 'active']]);
    } elseif ($action === 'deactivate') {
        $workersCollection->updateOne(['worker_id' => $id], ['$set' => ['status' => 'inactive']]);
    } elseif ($action === 'delete') {
        $workersCollection->deleteOne(['worker_id' => $id]);
    }

    header("Location: worker_registration.php");
    exit();
}

// Fetch all workers
$workers = $workersCollection->find([], ['sort' => ['registered_date' => -1]]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Worker Registration - DMAS</title>
<link rel="stylesheet" href="worker_registration.css">
</head>
<body>
    <nav class="navbar">
  <div class="nav-left">
    <h2>🌾 DMAS Admin Panel</h2>
  </div>
  <div class="nav-right">
    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="user_management.php">👥 Manage Users</a>
    <a href="order_management.php">📦  View Orders</a>
    <a href="admin_report.php">📄 Report</a>
    <a href="worker_registration.php"class="active">📝 Worker Registration</a>
    <a href="../logout.php" class="logout">🚪 Logout</a>
  </div>
</nav>

<div class="container">
    <h1>Worker Registration</h1>

    <?php if ($message): ?>
        <p class="success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <!-- Registration Form -->
    <div class="form-box">
        <h2>Add New Worker</h2>
        <form method="POST">
            <label>Full Name:</label>
            <input type="text" name="name" required>

            <label>Address:</label>
            <input type="text" name="address" required>

            <label>Phone Number:</label>
            <input type="text" name="phone" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Assigned Duties:</label>
            <input type="text" name="duties" required>

            <button type="submit">Register Worker</button>
        </form>
    </div>

    <!-- Worker List -->
    <div class="list-box">
        <h2>Existing Workers</h2>
        <table>
            <thead>
                <tr>
                    <th>Worker ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Duties</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $hasData = false;
            foreach ($workers as $w) {
                $hasData = true;
                $statusClass = strtolower($w['status']) === 'active' ? 'active' : 'inactive';
                echo "<tr>
                        <td>{$w['worker_id']}</td>
                        <td>{$w['name']}</td>
                        <td>{$w['phone']}</td>
                        <td>{$w['duties']}</td>
                        <td><span class='status {$statusClass}'>" . ucfirst($w['status']) . "</span></td>
                        <td class='actions'>";
                if ($w['status'] === 'active') {
                    echo "<a href='?action=deactivate&id={$w['worker_id']}' class='deactivate'>Deactivate</a>";
                } else {
                    echo "<a href='?action=activate&id={$w['worker_id']}' class='activate'>Activate</a>";
                }
                echo " <a href='?action=delete&id={$w['worker_id']}' class='delete' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                        </td>
                    </tr>";
            }

            if (!$hasData) {
                echo "<tr><td colspan='6'>No workers found.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
