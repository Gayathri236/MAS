<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only admin can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Collections
$ordersCollection = $db->orders;
$usersCollection = $db->users;
$timeSlotsCollection = $db->time_slots;

// ===== Handle Create Time Slot =====
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_slot'])) {
    $orderId = $_POST['order_id'] ?? '';
    $driverId = $_POST['driver_id'] ?? '';
    $scheduleTime = $_POST['schedule_time'] ?? '';
    $pickup = $_POST['pickup_location'] ?? '';
    $destination = $_POST['destination'] ?? '';
    $instructions = $_POST['instructions'] ?? '';

    if ($orderId && $driverId && $scheduleTime) {
        $timeSlotsCollection->insertOne([
            'order_id' => $orderId,
            'driver_id' => $driverId,
            'schedule_time' => $scheduleTime,
            'pickup_location' => $pickup,
            'destination' => $destination,
            'instructions' => $instructions,
            'status' => 'Scheduled',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $ordersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($orderId)],
            ['$set' => ['status' => 'Scheduled']]
        );

        $successMessage = "Time slot created successfully!";
    } else {
        $errorMessage = "Please fill all required fields.";
    }
}

// ===== Filter Orders =====
$filterStatus = $_GET['status'] ?? 'Pending';
$filter = [];
if (strtolower($filterStatus) !== 'all') {
    $filter['status'] = ['$regex' => "^$filterStatus$", '$options' => 'i'];
}

// Fetch Orders
$orders = $ordersCollection->find($filter, ['sort'=>['_id'=>-1]])->toArray();

// Fetch Drivers
$drivers = $usersCollection->find(['role'=>'transporter','status'=>'active'])->toArray();

// Fetch Active Time Slots
$activeSlots = $timeSlotsCollection->find([], ['sort'=>['schedule_time'=>1]])->toArray();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Time Slot Management</title>

<style>
/* ==== Basic CSS ==== */
body { font-family: Arial, sans-serif; background: #f4f6f8; margin:0; padding:0; }
.navbar { background: linear-gradient(135deg, #141e30, #243b55); display:flex; justify-content:space-between; padding:16px 50px; color:#fff; }
.navbar a { color:#eaeaea; text-decoration:none; margin-left:10px; }
.navbar a.active { background:#27ae60; padding:5px 10px; border-radius:5px; }
.admin-container { width:95%; margin:20px auto; }
h1, h2 { text-align:center; color:#2c3e50; }
table { width:100%; border-collapse:collapse; margin-bottom:20px; }
th, td { padding:10px; border:1px solid #ccc; text-align:center; }
th { background:#007bff; color:#fff; }
form label { display:block; margin:10px 0 5px; font-weight:bold; }
form input, form select, form textarea { width:100%; padding:8px; margin-bottom:10px; }
form button { padding:10px 15px; background:#27ae60; color:#fff; border:none; border-radius:5px; cursor:pointer; }
form button:hover { background:#219150; }
.success { color:green; text-align:center; }
.error { color:red; text-align:center; }
.tabs { display:flex; justify-content:center; gap:10px; margin-bottom:20px; }
.tab { padding:8px 15px; border-radius:5px; background:#ddd; text-decoration:none; color:#333; }
.tab.active { background:#007bff; color:#fff; }
.status.Scheduled { color:#17a2b8; font-weight:bold; }
.status.Pending { color:#ffc107; font-weight:bold; }
.status.Completed { color:#28a745; font-weight:bold; }
</style>
</head>
<body>
<nav class="navbar">
    <div>🌾 DMAS Admin Panel</div>
    <div>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="user_management.php">Users</a>
        <a href="order_management.php">Orders</a>
        <a href="time_slot_management.php" class="active">Time Slots</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="admin-container">
<h1>Time Slot Management</h1>

<?php if($successMessage): ?><p class="success"><?=htmlspecialchars($successMessage)?></p><?php endif; ?>
<?php if($errorMessage): ?><p class="error"><?=htmlspecialchars($errorMessage)?></p><?php endif; ?>

<!-- Filter Tabs -->
<div class="tabs">
    <?php foreach(['Pending','Scheduled','Completed','All'] as $status): ?>
        <a href="?status=<?=$status?>" class="tab <?=($filterStatus==$status)?'active':''?>"><?=$status?></a>
    <?php endforeach; ?>
</div>

<!-- Orders Table -->
<h2>Orders</h2>
<?php if(count($orders)>0): ?>
<table>
<thead>
<tr>
<th>Order ID</th>
<th>Farmer</th>
<th>Wholesaler</th>
<th>Products</th>
<th>Order Date</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php foreach($orders as $order): 
    $farmer = $usersCollection->findOne(['username'=>$order['farmer']]) ?? [];
    $wholesaler = $usersCollection->findOne(['username'=>$order['wholesaler']]) ?? [];
?>
<tr>
<td><?= (string)$order['_id'] ?></td>
<td><?= htmlspecialchars($farmer['name'] ?? $order['farmer']) ?></td>
<td><?= htmlspecialchars($wholesaler['name'] ?? $order['wholesaler']) ?></td>
<td><?= htmlspecialchars(implode(", ", $order['products'] ?? [])) ?></td>
<td><?= htmlspecialchars($order['order_date'] ?? '-') ?></td>
<td><span class="status <?=htmlspecialchars($order['status'])?>"><?=htmlspecialchars($order['status'])?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?><p>No orders found.</p><?php endif; ?>

<!-- Create Time Slot Form -->
<h2>Create Time Slot</h2>
<form method="POST">
    <label>Order</label>
    <select name="order_id" required>
        <option value="">-- Select Order --</option>
        <?php foreach($orders as $order): ?>
            <option value="<?= (string)$order['_id'] ?>">
                <?= htmlspecialchars($order['farmer_name'] ?? $order['farmer']) ?> → <?= htmlspecialchars($order['wholesaler_name'] ?? $order['wholesaler']) ?> (<?= htmlspecialchars($order['status'] ?? '-') ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <label>Driver</label>
    <select name="driver_id" required>
        <option value="">-- Select Driver --</option>
        <?php foreach($drivers as $driver): ?>
            <option value="<?= (string)$driver['_id'] ?>">
                <?= htmlspecialchars($driver['name']) ?> (<?= htmlspecialchars($driver['vehicle_type'] ?? 'Vehicle') ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <label>Schedule Date & Time</label>
    <input type="datetime-local" name="schedule_time" required>

    <label>Pickup Location</label>
    <input type="text" name="pickup_location" placeholder="Auto-filled from farmer" required>

    <label>Destination</label>
    <input type="text" name="destination" placeholder="Auto-filled from wholesaler" required>

    <label>Special Instructions</label>
    <textarea name="instructions" placeholder="Optional"></textarea>

    <button type="submit" name="create_slot">Create Time Slot</button>
</form>

<!-- Active Time Slots -->
<h2>Active Time Slots</h2>
<?php if(count($activeSlots)>0): ?>
<table>
<thead>
<tr>
<th>Slot ID</th>
<th>Driver</th>
<th>Vehicle</th>
<th>Order ID</th>
<th>Schedule Time</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php foreach($activeSlots as $slot):
    $driver = $usersCollection->findOne(['_id'=>new MongoDB\BSON\ObjectId($slot['driver_id'])]) ?? [];
?>
<tr>
<td><?= (string)$slot['_id'] ?></td>
<td><?= htmlspecialchars($driver['name'] ?? 'Unknown') ?></td>
<td><?= htmlspecialchars($driver['vehicle_type'] ?? '-') ?></td>
<td><?= htmlspecialchars($slot['order_id'] ?? '-') ?></td>
<td><?= htmlspecialchars($slot['schedule_time']) ?></td>
<td><?= htmlspecialchars($slot['status']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?><p>No active time slots.</p><?php endif; ?>

</div>
</body>
</html>
