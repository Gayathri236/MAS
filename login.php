<?php
session_start();
require 'mongodb_config.php'; 

// Show success message after logout
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $logoutMessage = "You have been successfully logged out.";
}

// Show success message after registration
if (isset($_SESSION['success'])) {
    $registrationMessage = $_SESSION['success'];
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
                header("Location: Vehicle/transporter_dashboard.php");
                break;
            default:
                header("Location: dashboard.html");
        }
        exit();
    } else {
        $errorMessage = 'Invalid login details!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMAS Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>
                <i class="fas fa-tractor"></i>
                DMAS
            </h1>
            <p>Dambulla Market Automation System - Secure Login</p>
        </div>

        <div class="login-box">
            <h2>Sign In to Your Account</h2>
            
            <?php if (isset($logoutMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $logoutMessage ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($registrationMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $registrationMessage ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $errorMessage ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Enter Username" required 
                           value="<?= htmlspecialchars($username) ?>">
                </div>

                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Enter Password" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-user-tag"></i>
                    <select name="role" required style="appearance: none; -webkit-appearance: none; -moz-appearance: none;">
                        <option value="">-- Select Your Role --</option>
                        <option value="farmer" <?= $role === 'farmer' ? 'selected' : '' ?>>
                            <div class="role-option">
                                <i class="fas fa-tractor"></i> Farmer
                            </div>
                        </option>
                        <option value="wholesaler" <?= $role === 'wholesaler' ? 'selected' : '' ?>>
                            <div class="role-option">
                                <i class="fas fa-store"></i> Wholesaler / Buyer
                            </div>
                        </option>
                        <option value="transporter" <?= $role === 'transporter' ? 'selected' : '' ?>>
                            <div class="role-option">
                                <i class="fas fa-truck"></i> Transporter
                            </div>
                        </option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>
                            <div class="role-option">
                                <i class="fas fa-user-shield"></i> Admin
                            </div>
                        </option>
                    </select>
                    <i class="fas fa-chevron-down select-icon"></i>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login to Dashboard</span>
                    <i class="fas fa-spinner loading"></i>
                </button>
            </form>

            <div class="register-link">
                <p>
                    Don't have an account? 
                    <a href="registration.php">
                        <i class="fas fa-user-plus"></i> Register Now
                    </a>
                </p>
            </div>
        </div>
    </div>
<script src="assets/login.js"></script>
</body>
</html>