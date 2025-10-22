<?php
session_start();
require 'mongodb_config.php'; // Make sure the path is correct

// Show success message after registration
if (isset($_SESSION['success'])) {
    echo "<p style='text-align:center; color:green; margin-bottom:10px;'>" . $_SESSION['success'] . "</p>";
    unset($_SESSION['success']);
}

// Get form values safely
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use the correct collection
    $user = $usersCollection->findOne([
        'username' => $username,
        'role' => $role
    ]);

    if ($user && password_verify($password, $user['password'])) {
        // Save session
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        $_SESSION['user_id'] = (string)$user['_id'];

        // Redirect based on role
        switch ($role) {
            case 'admin':
                header("Location: Admin/admin_dashboard.php");
                break;
            case 'farmer':
                header("Location: Farmer/farmer_dashboard.php");
                break;
            case 'wholesaler':
                header("Location: wholesaler/dashboard.php");
                break;
            case 'transporter':
                header("Location: Vehicle/vehicle_management.php");
                break;
            default:
                header("Location: dashboard.html");
        }
        exit();
    } else {
        echo "<script>alert('Invalid login details!'); window.history.back();</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMAS Login</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="login-box">
        <h2>DMAS Login</h2>
        <form action="login.php" method="POST">
            <input type="text" name="username" placeholder="Enter Username" required>
            <input type="password" name="password" placeholder="Enter Password" required>
            
            <select name="role" required>
                <option value="">-- Select Role --</option>
                <option value="farmer">Farmer</option>
                <option value="wholesaler">Wholesaler / Buyer</option>
                <option value="transporter">Transporter</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit">Login</button>
        </form>

        <p style="text-align:center; margin-top:10px;">
            Don't have an account? 
            <a href="registration.php">Register</a>
        </p>
    </div>
</body>
</html>
