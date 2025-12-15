<?php
session_start();
require __DIR__ . '/../mongodb_config.php';
date_default_timezone_set('Asia/Colombo');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Collections
$usersCollection = $db->users;
$products = $db->products;
$orders = $db->orders;

// ---------------- PROFILE ----------------
$farmerProfile = $usersCollection->findOne(['username' => $username, 'role' => 'farmer']);
if (!$farmerProfile) {
    // graceful fallback
    $farmerProfile = [
        'username' => $username,
        'name'     => $username,
        'phone'    => 'N/A',
        'location'  => 'N/A',
        'photo'    => 'default_profile.png'
    ];
}
$displayUsername = htmlspecialchars($farmerProfile['username'] ?? $username);
$displayName     = htmlspecialchars($farmerProfile['name'] ?? 'N/A');
$displayPhone    = htmlspecialchars($farmerProfile['phone'] ?? 'N/A');
$displayAddress  = htmlspecialchars($farmerProfile['location'] ?? ($farmerProfile['location'] ?? 'N/A'));
$displayImage = htmlspecialchars($farmerProfile['image'] ?? 'default.png');  

// Greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 17) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// Stats
$totalProducts   = $products->countDocuments(['farmer' => $username]);
$totalOrders     = $orders->countDocuments(['farmer' => $username]);
$pendingOrders   = $orders->countDocuments(['farmer' => $username, 'status' => 'pending']);

// Total Sales & Product Quantity Per Day - UPDATED
$totalSales = 0;
$productQtyPerDay = [];
$firstDay = strtotime("first day of this month");
$nextMonth = strtotime("first day of next month");
$salesCursor = $orders->find(['farmer' => $username, 'status' => ['$in' => ['accepted', 'completed']]]);

foreach($salesCursor as $order) {
    $amount = 0;
    
    // Check multiple possible amount fields
    if(isset($order['total_amount']) && is_numeric($order['total_amount'])) {
        $amount = floatval($order['total_amount']);
    } elseif(isset($order['amount']) && is_numeric($order['amount'])) {
        $amount = floatval($order['amount']);
    } elseif(isset($order['total']) && is_numeric($order['total'])) {
        $amount = floatval($order['total']);
    } elseif(isset($order['quantity'], $order['unit_price']) && 
             is_numeric($order['quantity']) && is_numeric($order['unit_price'])) {
        $amount = floatval($order['quantity']) * floatval($order['unit_price']);
    }

    $totalSales += $amount;

    // Get order date for monthly calculation
    $orderDate = null;
    
    if(isset($order['order_date']) && $order['order_date'] instanceof MongoDB\BSON\UTCDateTime) {
        $orderDate = $order['order_date']->toDateTime()->getTimestamp();
    } elseif(isset($order['created_at']) && $order['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $orderDate = $order['created_at']->toDateTime()->getTimestamp();
    } elseif(isset($order['accepted_date']) && $order['accepted_date'] instanceof MongoDB\BSON\UTCDateTime) {
        $orderDate = $order['accepted_date']->toDateTime()->getTimestamp();
    }

    // Add to product quantity per day if within current month
    if($orderDate && $orderDate >= $firstDay && $orderDate < $nextMonth) {
        $day = date('Y-m-d', $orderDate);
        $quantity = isset($order['quantity']) && is_numeric($order['quantity']) ? floatval($order['quantity']) : 0;
        $productQtyPerDay[$day] = ($productQtyPerDay[$day] ?? 0) + $quantity;
    }
}

ksort($productQtyPerDay);
$productQtyLabels = array_map(fn($d) => date('d M', strtotime($d)), array_keys($productQtyPerDay));
$productQtyValues = array_values($productQtyPerDay);

// Order Status Distribution
$statusLabels = ['pending', 'accepted', 'completed', 'rejected'];
$statusCounts = [];

foreach($statusLabels as $s) {
    $statusCounts[] = $orders->countDocuments(['farmer' => $username, 'status' => $s]);
}

// Top Products
try {
    $pipeline = [
        ['$match' => ['farmer' => $username, 'status' => ['$in' => ['accepted', 'completed']]]],
        ['$group' => [
            '_id' => '$product_name',
            'qty' => ['$sum' => '$quantity'],
            'total_sales' => ['$sum' => '$total_amount']
        ]],
        ['$sort' => ['total_sales' => -1]],
        ['$limit' => 5]
    ];
    
    $topProductsCursor = $orders->aggregate($pipeline);
    $topProductsArray = iterator_to_array($topProductsCursor);
    $topNames = array_column($topProductsArray, '_id');
    $topQty = array_column($topProductsArray, 'qty');
} catch(Exception $e) {
    $topNames = [];
    $topQty = [];
}

// Stock Levels
$stocks = $products->find(['farmer' => $username])->toArray();
$stockNames = array_column($stocks, 'name');
$stockQtys = array_column($stocks, 'quantity');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/farmer_dashboard.css">
</head>
<body>

<div class="sidebar">
    <div class="profile-section">
        <img src="../uploads/<?= htmlspecialchars($displayImage) ?>" alt="Profile" class="profile-img" onerror="this.src='../uploads/default.png'; this.onerror=null;">
        <p><?= $username ?></p>
        <p><i class="fas fa-map-marker-alt"></i> <?= $displayAddress ?></p>
    </div>
    
    <div class="nav-links">
        <a href="farmer_dashboard.php" class="active">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="add_product.php">
            <i class="fas fa-plus-circle"></i>
            <span>Add Product</span>
        </a>
        <a href="my_products.php">
            <i class="fas fa-seedling"></i>
            <span>My Products</span>
        </a>
        <a href="farmer_orders.php">
            <i class="fas fa-shopping-cart"></i>
            <span>Orders</span>
        </a>
        <a href="prediction.php">
            <i class="fas fa-chart-line"></i>
            <span>Prediction</span>
        </a>
        <a href="report.php">
            <i class="fas fa-file-export"></i>
            <span>Reports</span>
        </a>
        <a href="profile.php">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </div>
    
    <a href="../logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>

<div class="main">
    <div class="header">
        <div class="greeting">
            <h1><?= $greeting ?>, <?= $displayName ?>!</h1>
            <p>Welcome to your farming dashboard. Here's your business overview.</p>
        </div>
        <div class="date-display">
            <i class="fas fa-calendar-alt"></i>
            <?= date('l, F j, Y') ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="cards-grid">
        <!-- Total Products Card -->
        <div class="card" onclick="window.location.href='my_products.php'">
            <div class="card-header">
                <div class="card-icon-container">
                    <i class="fas fa-box"></i>
                </div>
                <div class="card-label">Total Products</div>
            </div>
            <div class="card-value"><?= $totalProducts ?></div>
            <div class="card-footer">Active listings</div>
        </div>

        <!-- Total Orders Card -->
        <div class="card" onclick="window.location.href='farmer_orders.php'">
            <div class="card-header">
                <div class="card-icon-container">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="card-label">Total Orders</div>
            </div>
            <div class="card-value"><?= $totalOrders ?></div>
            <div class="card-footer">All time orders</div>
        </div>

        <!-- Pending Orders Card -->
        <div class="card" onclick="window.location.href='farmer_orders.php?status=pending'">
            <div class="card-header">
                <div class="card-icon-container">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-label">Pending Orders</div>
            </div>
            <div class="card-value"><?= $pendingOrders ?></div>
            <div class="card-footer">Awaiting action</div>
        </div>

        <!-- Total Sales Card -->
        <div class="card" onclick="window.location.href='export_orders.php'">
            <div class="card-header">
                <div class="card-icon-container">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="card-label">Total Sales</div>
            </div>
            <div class="card-value">Rs <?= number_format($totalSales, 0) ?></div>
            <div class="card-footer">Completed orders</div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid">
        <!-- Order Status Chart -->
        <div class="chart-box">
            <h3><i class="fas fa-chart-pie"></i> Order Status Distribution</h3>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
        
        <!-- Product Quantity Per Day Chart -->
        <div class="chart-box">
            <h3><i class="fas fa-chart-bar"></i> Product Quantity Per Day</h3>
            <div class="chart-container">
                <canvas id="productQtyChart"></canvas>
            </div>
        </div>
        
        <!-- Top Products Chart -->
        <div class="chart-box">
            <h3><i class="fas fa-star"></i> Top Selling Products</h3>
            <div class="chart-container">
                <canvas id="topChart"></canvas>
            </div>
        </div>
        
        <!-- Stock Levels Chart -->
        <div class="chart-box">
            <h3><i class="fas fa-boxes"></i> Current Stock Levels</h3>
            <div class="chart-container">
                <canvas id="stockChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// Order Status Chart
const statusChart = new Chart(document.getElementById("statusChart"), {
    type: "doughnut",
    data: {
        labels: ["Pending", "Accepted", "Completed", "Rejected"],
        datasets: [{
            data: <?= json_encode($statusCounts) ?>,
            backgroundColor: ["#E9C46A", "#2A9D8F", "#264653", "#E76F51"],
            borderWidth: 2,
            borderColor: "#fff",
            hoverOffset: 12
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 18,
                    font: {
                        size: 12,
                        family: "'Poppins', sans-serif"
                    },
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                titleColor: '#264653',
                bodyColor: '#666',
                borderColor: '#e0e0e0',
                borderWidth: 1,
                cornerRadius: 8,
                padding: 12,
                callbacks: {
                    label: function(context) {
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = Math.round((context.raw / total) * 100);
                        return `${context.label}: ${context.raw} orders (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Product Quantity Per Day Chart
const productQtyChart = new Chart(document.getElementById("productQtyChart"), {
    type: "bar",
    data: {
        labels: <?= json_encode($productQtyLabels) ?>,
        datasets: [{
            label: "Product Quantity Sold",
            data: <?= json_encode($productQtyValues) ?>,
            backgroundColor: "#2A9D8F",
            borderColor: "#2A9D8F",
            borderWidth: 1,
            borderRadius: 6,
            hoverBackgroundColor: "#34b4a4",
            hoverBorderColor: "#34b4a4",
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    font: {
                        size: 12,
                        family: "'Poppins', sans-serif"
                    },
                    padding: 15
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Quantity: ${context.raw} units`;
                    },
                    title: function(context) {
                        return context[0].label;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
                    font: {
                        size: 11,
                        family: "'Poppins', sans-serif"
                    },
                    padding: 8,
                    callback: function(value) {
                        return value + ' units';
                    }
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
                },
                title: {
                    display: true,
                    text: 'Quantity (units)',
                    font: {
                        size: 12,
                        weight: 'bold',
                        family: "'Poppins', sans-serif"
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 11,
                        family: "'Poppins', sans-serif"
                    },
                    maxRotation: 45
                },
                title: {
                    display: true,
                    text: 'Date',
                    font: {
                        size: 12,
                        weight: 'bold',
                        family: "'Poppins', sans-serif"
                    }
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});

// Top Products Chart
const topChart = new Chart(document.getElementById("topChart"), {
    type: "bar",
    data: {
        labels: <?= json_encode($topNames) ?>,
        datasets: [{
            label: "Quantity Sold",
            data: <?= json_encode($topQty) ?>,
            backgroundColor: "#264653",
            borderRadius: 8,
            borderSkipped: false,
            hoverBackgroundColor: "#2A9D8F"
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
                    font: {
                        size: 11,
                        family: "'Poppins', sans-serif"
                    },
                    padding: 8
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 11,
                        family: "'Poppins', sans-serif"
                    },
                    maxRotation: 45
                }
            }
        }
    }
});

// Stock Levels Chart
const stockChart = new Chart(document.getElementById("stockChart"), {
    type: "bar",
    data: {
        labels: <?= json_encode($stockNames) ?>,
        datasets: [{
            label: "Stock Quantity",
            data: <?= json_encode($stockQtys) ?>,
            backgroundColor: "#E9C46A",
            borderRadius: 8,
            borderSkipped: false,
            hoverBackgroundColor: "#f2d382"
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
                    font: {
                        size: 11,
                        family: "'Poppins', sans-serif"
                    },
                    padding: 8
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 11,
                        family: "'Poppins', sans-serif"
                    },
                    maxRotation: 45
                }
            }
        }
    }
});

// Add smooth click animation to cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.card');
    
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Add click animation
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
                if(this.getAttribute('onclick')) return;
                const href = this.getAttribute('data-href') || this.querySelector('a')?.href;
                if(href && href !== '#') {
                    window.location.href = href;
                }
            }, 150);
        });
    });
    
    // Check profile image load
    const profileImg = document.querySelector('.profile-img');
    if(profileImg) {
        profileImg.addEventListener('error', function() {
            this.src = '../uploads/default.png';
        });
    }
});
</script>

</body>
</html>