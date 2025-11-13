<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../mongodb_config.php';

// ✅ Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$ordersCollection = $db->orders;
$usersCollection = $db->users;
$timeSlotsCollection = $db->time_slots;

// Handle form submission for assigning a time slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderId = $_POST['order_id'];
    $driverId = $_POST['driver_id'];
    $slotDate = $_POST['slot_date'];
    $slotTime = $_POST['slot_time'];

    // Get driver info
    $driver = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($driverId)]);

    $slotData = [
        'order_id' => $orderId,
        'driver_id' => $driverId,
        'driver_name' => $driver['name'] ?? 'Unknown',
        'driver_contact' => $driver['contact'] ?? 'N/A',
        'vehicle_number' => $driver['vehicle_number'] ?? '-',
        'slot_date' => $slotDate,
        'slot_time' => $slotTime,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];

    $timeSlotsCollection->insertOne($slotData);
    $ordersCollection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($orderId)],
        ['$set' => ['delivery_status' => 'Scheduled']]
    );
}

// Fetch accepted orders
$acceptedOrders = $ordersCollection->find(['status' => 'accepted'], ['sort' => ['_id' => -1]]);

// Fetch all drivers (transporters)
$drivers = $usersCollection->find(['role' => 'transporter']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Time Slot | DMAS</title>
<style>
body {font-family: Arial, sans-serif; background-color: #f2f5fa; margin: 0;}
.navbar {background: #2c3e50; color: white; display: flex; justify-content: space-between; padding: 15px 30px;}
.navbar a {color: white; margin-left: 15px; text-decoration: none;}
.container {margin: 30px auto; width: 95%; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);}
table {width: 100%; border-collapse: collapse; margin-top: 15px;}
th, td {border: 1px solid #ccc; padding: 10px; text-align: center;}
th {background: #34495e; color: white;}
form {display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;}
input, select {padding: 5px; border-radius: 5px; border: 1px solid #bbb;}
button {background: #27ae60; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer;}
button:hover {background: #219150;}
.empty {text-align: center; color: #777; padding: 15px;}
</style>
</head>
<body>

<nav class="navbar">
    <h2>🕒 DMAS - Admin Time Slot Management</h2>
    <div>
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="time_slot_management.php" class="active">⏰ Time Slots</a>
        <a href="../logout.php" class="logout">🚪 Logout</a>
    </div>
</nav>

<div class="container">
    <h2>Accepted Orders - Assign Delivery Time Slots</h2>

    <table>
        <tr>
            <th>Slot ID</th>
            <th>Order ID</th>
            <th>Farmer Details</th>
            <th>Wholesaler Details</th>
            <th>Driver</th>
            <th>Vehicle Number</th>
            <th>Date</th>
            <th>Time</th>
            <th>Action</th>
        </tr>

        <?php
        $hasOrders = false;
        foreach ($acceptedOrders as $order):
            $hasOrders = true;

            // Fetch farmer & wholesaler details
            $farmer = null;
            $wholesaler = null;
            if (!empty($order['farmer_id'])) {
                $farmer = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($order['farmer_id'])]);
            }
            if (!empty($order['wholesaler_id'])) {
                $wholesaler = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($order['wholesaler_id'])]);
            }

            // Check if a slot already exists
            $slot = $timeSlotsCollection->findOne(['order_id' => (string)$order['_id']]);
        ?>
        <tr>
            <td><?= $slot ? (string)$slot['_id'] : '-' ?></td>
            <td><?= (string)$order['_id'] ?></td>

            <td>
                <?= htmlspecialchars($farmer['name'] ?? 'N/A') ?><br>
                📞 <?= htmlspecialchars($farmer['contact'] ?? '-') ?><br>
                📍 <?= htmlspecialchars($farmer['location'] ?? '-') ?>
            </td>

            <td>
                <?= htmlspecialchars($wholesaler['name'] ?? 'N/A') ?><br>
                📞 <?= htmlspecialchars($wholesaler['contact'] ?? '-') ?><br>
                📍 <?= htmlspecialchars($wholesaler['location'] ?? '-') ?>
            </td>

            <td>
                <?php if ($slot): ?>
                    <?= htmlspecialchars($slot['driver_name'] ?? 'Assigned') ?><br>
                    📞 <?= htmlspecialchars($slot['driver_contact'] ?? '-') ?>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?= (string)$order['_id'] ?>">
                        <select name="driver_id" required>
                            <option value="">-- Select Driver --</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?= (string)$driver['_id'] ?>">
                                    <?= htmlspecialchars($driver['name']) ?> (<?= htmlspecialchars($driver['vehicle_number'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                <?php endif; ?>
            </td>

            <td><?= htmlspecialchars($slot['vehicle_number'] ?? '-') ?></td>

            <td>
                <?php if (!$slot): ?>
                    <input type="date" name="slot_date" required>
                <?php else: ?>
                    <?= htmlspecialchars($slot['slot_date'] ?? '-') ?>
                <?php endif; ?>
            </td>

            <td>
                <?php if (!$slot): ?>
                    <input type="time" name="slot_time" required>
                <?php else: ?>
                    <?= htmlspecialchars($slot['slot_time'] ?? '-') ?>
                <?php endif; ?>
            </td>

            <td>
                <?php if (!$slot): ?>
                    <button type="submit">Assign</button>
                    </form>
                <?php else: ?>
                    ✅ Assigned
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (!$hasOrders): ?>
        <tr><td colspan="9" class="empty">⚠️ No accepted orders found.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
