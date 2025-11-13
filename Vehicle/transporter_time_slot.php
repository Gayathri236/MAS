<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

// Only transporters can access
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'transporter') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];
$timeSlots = $db->time_slots;
$vehicles = $db->vehicles;
$orders = $db->orders;

$successMessage = '';
$errorMessage = '';

// ===== Find this driver's vehicle =====
$vehicle = $vehicles->findOne(['username' => $username]);
$vehicleId = $vehicle ? (string)$vehicle['_id'] : null;

// ===== Handle actions =====
if (isset($_GET['start'])) {
    $slotId = $_GET['start'];
    try {
        $timeSlots->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($slotId)],
            ['$set' => ['status' => 'InProgress', 'started_at' => date('Y-m-d H:i:s')]]
        );
        $successMessage = "Delivery started.";
    } catch (Exception $e) {
        $errorMessage = "Error updating status: " . $e->getMessage();
    }
}

if (isset($_GET['deliver'])) {
    $slotId = $_GET['deliver'];
    try {
        // update timeslot status
        $timeSlots->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($slotId)],
            ['$set' => ['status' => 'Completed', 'delivered_at' => date('Y-m-d H:i:s')]]
        );

        // also mark related order as completed
        $slot = $timeSlots->findOne(['_id' => new MongoDB\BSON\ObjectId($slotId)]);
        if ($slot && isset($slot['order_id'])) {
            $orders->updateOne(
                ['_id' => $slot['order_id']],
                ['$set' => ['status' => 'completed']]
            );
        }

        $successMessage = "Delivery marked as completed.";
    } catch (Exception $e) {
        $errorMessage = "Error completing delivery: " . $e->getMessage();
    }
}

// ===== Fetch assigned time slots =====
$query = $vehicleId ? ['vehicle_id' => new MongoDB\BSON\ObjectId($vehicleId)] : [];
$assignedSlots = iterator_to_array($timeSlots->find($query, ['sort' => ['schedule_time' => 1]]));

function safe($v) {
    return htmlspecialchars(is_scalar($v) ? $v : json_encode($v));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transporter Time Slots - DMAS</title>
<style>
body {
  font-family: 'Segoe UI', sans-serif;
  background-color: #f7f9fb;
  margin: 0;
  color: #333;
}
.navbar {
  background: linear-gradient(135deg, #243b55, #141e30);
  color: #fff;
  padding: 14px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.navbar a {
  color: #fff;
  text-decoration: none;
  margin-left: 15px;
  font-weight: 500;
}
.container {
  width: 95%;
  max-width: 1100px;
  margin: 25px auto;
  background: #fff;
  padding: 25px;
  border-radius: 10px;
  box-shadow: 0 4px 14px rgba(0,0,0,0.1);
}
h1 { color: #243b55; }
.success { background:#eafaf1; color:#155724; padding:10px; border-left:4px solid #28a745; margin-bottom:10px; }
.error { background:#fdecea; color:#721c24; padding:10px; border-left:4px solid #dc3545; margin-bottom:10px; }
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}
th, td {
  padding: 12px 10px;
  border: 1px solid #ddd;
  text-align: center;
}
th {
  background-color: #f1f5f9;
  color: #243b55;
}
.badge {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 18px;
  font-size: 13px;
  color: #fff;
  font-weight: 600;
}
.badge.Scheduled { background:#007bff; }
.badge.InProgress { background:#f39c12; }
.badge.Completed { background:#28a745; }
button {
  padding: 6px 10px;
  border: none;
  border-radius: 5px;
  color: #fff;
  cursor: pointer;
  font-weight: 600;
}
.start { background: #3498db; }
.deliver { background: #28a745; }
.start:hover { background: #2980b9; }
.deliver:hover { background: #218838; }
</style>
</head>
<body>

<div class="navbar">
  <div><strong>🚚 DMAS Transporter Panel</strong></div>
  <div>
    <a href="transporter_time_slots.php">Time Slots</a>
    <a href="../logout.php">Logout</a>
  </div>
</div>

<div class="container">
  <h1>My Assigned Deliveries</h1>

  <?php if ($successMessage): ?><div class="success"><?= $successMessage ?></div><?php endif; ?>
  <?php if ($errorMessage): ?><div class="error"><?= $errorMessage ?></div><?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>Order ID</th>
        <th>Vehicle</th>
        <th>Schedule</th>
        <th>Pickup</th>
        <th>Destination</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($assignedSlots) === 0): ?>
        <tr><td colspan="7">No time slots assigned yet.</td></tr>
      <?php else: ?>
        <?php foreach ($assignedSlots as $slot): ?>
          <tr>
            <td><?= safe(substr((string)$slot['order_id'], -6)) ?></td>
            <td><?= safe($slot['vehicle_number'] ?? 'N/A') ?></td>
            <td><?= safe($slot['schedule_time'] ?? '-') ?></td>
            <td><?= safe($slot['pickup_location'] ?? '-') ?></td>
            <td><?= safe($slot['destination'] ?? '-') ?></td>
            <td><span class="badge <?= safe($slot['status'] ?? 'Scheduled') ?>"><?= safe($slot['status'] ?? 'Scheduled') ?></span></td>
            <td>
              <?php if (($slot['status'] ?? '') === 'Scheduled'): ?>
                <a href="?start=<?= safe($slot['_id']) ?>"><button class="start">Start Delivery</button></a>
              <?php elseif (($slot['status'] ?? '') === 'InProgress'): ?>
                <a href="?deliver=<?= safe($slot['_id']) ?>"><button class="deliver">Mark Delivered</button></a>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>
