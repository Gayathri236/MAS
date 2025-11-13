<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];
$timeSlotsCollection = $db->time_slots;
$slots = $timeSlotsCollection->find(['farmer_name' => $username])->toArray();
?>

<!DOCTYPE html>
<html>
<head><title>Farmer - Delivery Slots</title></head>
<body>
<h1>My Delivery Time Slots</h1>
<table border="1" cellpadding="8">
<tr><th>Order ID</th><th>Driver</th><th>Vehicle</th><th>Schedule</th><th>Destination</th><th>Status</th></tr>
<?php foreach ($slots as $s): ?>
<tr>
<td><?= $s['order_id'] ?></td>
<td><?= $s['driver_name'] ?></td>
<td><?= $s['vehicle_number'] ?></td>
<td><?= $s['schedule_time'] ?></td>
<td><?= $s['destination'] ?></td>
<td><?= $s['status'] ?></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
