<?php
session_start();
require __DIR__ . '/../mongodb_config.php';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $actionId = $_GET['id']; // This must be the MongoDB _id
    $action = $_GET['action'];

    try {
        $objectId = new MongoDB\BSON\ObjectId($actionId); // Convert to ObjectId safely

        if ($action === 'delete') {
            $usersCollection->deleteOne(['_id' => $objectId]);
        } elseif ($action === 'activate') {
            $usersCollection->updateOne(['_id' => $objectId], ['$set' => ['status' => 'active']]);
        } elseif ($action === 'deactivate') {
            $usersCollection->updateOne(['_id' => $objectId], ['$set' => ['status' => 'inactive']]);
        }
    } catch (Exception $e) {
        // Invalid ObjectId, ignore
    }

    // Redirect to avoid repeated actions
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// ==========================
// Filters
// ==========================
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';

$filter = [];

if ($search !== '') {
    $filter['$or'] = [
        ['name' => ['$regex' => $search, '$options' => 'i']],
        ['username' => ['$regex' => $search, '$options' => 'i']],
        ['phone' => ['$regex' => $search, '$options' => 'i']]
    ];
}
if ($type !== '') {
    $filter['role'] = $type;
}
if ($status !== '') {
    $filter['status'] = $status;
}

// Fetch users from MongoDB
$users = $usersCollection->find($filter, ['sort' => ['_id' => -1]]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management</title>
<link rel="stylesheet" href="user_managemnet.css">

</head>
<body>
     <nav class="navbar">
  <div class="nav-left">
    <h2>🌾 DMAS Admin Panel</h2>
  </div>
  <div class="nav-right">
    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="user_management.php"class="active">👥 Manage Users</a>
    <a href="order_management.php">📦  Manage Orders</a>
    <a href="time_slot_management.php">🚚 Time Slot</a>
    <a href="worker_registration.php">📝 Worker Registration</a>
    <a href="../logout.php" class="logout">🚪 Logout</a>
  </div>
</nav>
  

<div class="admin-container">
    <header>
        <h1>User Management</h1>
    </header>

    <!-- Tabs -->
    <div class="tabs">
        <a href="?type=farmer" class="tab <?= ($type=='farmer')?'active':'' ?>">Farmers</a>
        <a href="?type=wholesaler" class="tab <?= ($type=='wholesaler')?'active':'' ?>">Wholesalers</a>
        <a href="?type=transporter" class="tab <?= ($type=='transporter')?'active':'' ?>">Transporters</a>
        <a href="?type=" class="tab <?= ($type=='')?'active':'' ?>">All Users</a>
    </div>

    <!-- Search & Filter -->
    <form class="filter-container" method="GET">
        <input type="text" name="search" placeholder="Search by name, email, or phone..." class="search-input" value="<?= htmlspecialchars($search) ?>">
        <select name="type" class="filter-select">
            <option value="">Filter by Type</option>
            <option value="farmer" <?= ($type=='farmer')?'selected':'' ?>>Farmer</option>
            <option value="wholesaler" <?= ($type=='wholesaler')?'selected':'' ?>>Wholesaler</option>
            <option value="transporter" <?= ($type=='transporter')?'selected':'' ?>>Transporter</option>
        </select>
        <select name="status" class="filter-select">
            <option value="">Filter by Status</option>
            <option value="active" <?= ($status=='active')?'selected':'' ?>>Active</option>
            <option value="inactive" <?= ($status=='inactive')?'selected':'' ?>>Inactive</option>
        </select>
        <button type="submit" class="btn filter-btn">Filter</button>
    </form>

    <!-- User Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $hasData = false;
            foreach ($users as $user) {
                $hasData = true;

                $displayId = $user['user_id'] ?? ''; // Show user_id if exists
                $objectId = (string)$user['_id'];    // Use _id for actions

                $statusClass = isset($user['status']) && strtolower($user['status']) === 'active' ? 'active' : 'inactive';
                $statusText = ucfirst($statusClass);

                echo "<tr>
                        <td>{$displayId}</td>
                        <td>{$user['name']}</td>
                        <td>{$user['username']}</td>
                        <td>{$user['role']}</td>
                        <td>{$user['phone']}</td>
                        <td>{$user['address']}</td>
                        <td><span class='status {$statusClass}'>{$statusText}</span></td>
                        <td class='actions'>
                            <a class='view' href='#' onclick=\"alert('Viewing user: {$user['name']}')\">View</a>";

                if ($statusClass === 'active') {
                    echo "<a class='deactivate' href='?action=deactivate&id={$objectId}'>Deactivate</a>";
                } else {
                    echo "<a class='activate' href='?action=activate&id={$objectId}'>Activate</a>";
                }

                echo "<a class='delete' href='?action=delete&id={$objectId}' onclick=\"return confirm('Are you sure to delete this user?')\">Delete</a>
                        </td>
                      </tr>";
            }

            if (!$hasData) {
                echo "<tr><td colspan='8'>No users found.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
