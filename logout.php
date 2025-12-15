<?php
// logout.php
session_start();

// Check if logout is confirmed
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if ($confirmed) {
    // Unset all session variables
    $_SESSION = array();
    
    // If it's desired to kill the session, also delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page with logout message
    header("Location: ../login.php?logout=success");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout Confirmation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/logout.css">
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        
        <h1 class="logout-title">Logout Confirmation</h1>
        
        <p class="logout-message">
            Are you sure you want to log out? You'll need to sign in again to access your account.
        </p>
        
        <?php if (isset($_SESSION['username']) || isset($_SESSION['user_id'])): ?>
        <div class="user-info">
            <p><strong>Current User:</strong> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></p>
            <p><strong>Role:</strong> <?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'User')) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="warning-box">
            <p><i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> Any unsaved work will be lost.</p>
        </div>
        
        <div class="logout-actions">
            <a href="?confirm=yes" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Yes, Logout
            </a>
            
            <?php 
            // Determine where to go back based on user role
            $backUrl = '../login.php';
            if (isset($_SESSION['role'])) {
                switch ($_SESSION['role']) {
                    case 'transporter':
                        $backUrl = 'Vehicle/transporter_dashboard.php';
                        break;
                    case 'farmer':
                        $backUrl = 'Farmer/farmer_dashboard.php';
                        break;
                    case 'wholesaler':
                        $backUrl = 'wholesaler/dashboard.php';
                        break;
                    case 'admin':
                        $backUrl = 'Admin/admin_dashboard.php';
                        break;
                }
            }
            ?>
            
            <a href="<?= $backUrl ?>" class="btn btn-cancel">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
        
        <div class="back-link">
            <a href="<?= $backUrl ?>">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
<script src="assets/logout.js"></script>
</body>
</html>