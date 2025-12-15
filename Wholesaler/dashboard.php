<?php
session_start();
require __DIR__ . '/../mongodb_config.php';
date_default_timezone_set('Asia/Colombo');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.php");
    exit();
}

$wholesaler = $_SESSION['username'];
$ordersCollection = $db->orders;

// --- Status counts ---
$statusCursor = $ordersCollection->aggregate([
    ['$match' => ['wholesaler' => $wholesaler]],
    ['$group' => ['_id' => ['$ifNull' => ['$status', 'unknown']], 'count' => ['$sum' => 1]]]
])->toArray();

$statusCounts = [];
foreach ($statusCursor as $r) $statusCounts[$r['_id']] = (int)$r['count'];
$statuses = ['pending','accepted','delivery','completed','rejected'];
foreach ($statuses as $s) if (!isset($statusCounts[$s])) $statusCounts[$s]=0;
$total_orders = array_sum($statusCounts);

// --- Last 6 months ---
$months = [];
$dt = new DateTime('first day of this month');
for ($i=5;$i>=0;$i--){
    $m = (clone $dt)->modify("-$i months");
    $months[] = $m->format('Y-m');
}

// Orders per month (all statuses)
$ordersPerMonthCursor = $ordersCollection->aggregate([
    ['$match'=>['wholesaler'=>$wholesaler,'order_date'=>['$exists'=>true]]],
    ['$group'=>['_id'=>['$dateToString'=>['format'=>'%Y-%m','date'=>'$order_date','timezone'=>'Asia/Colombo']], 'count'=>['$sum'=>1]]],
    ['$sort'=>['_id'=>1]]
])->toArray();

$ordersPerMonth = array_fill_keys($months,0);
foreach($ordersPerMonthCursor as $r) {
    if(isset($ordersPerMonth[$r['_id']])) {
        $ordersPerMonth[$r['_id']] = $r['count'];
    }
}

// Calculate spending from ACCEPTED, DELIVERY, and COMPLETED orders
$spendPerMonthCursor = $ordersCollection->aggregate([
    ['$match'=>[
        'wholesaler'=>$wholesaler,
        'status'=>['$in'=>['accepted','delivery','completed']],
        'total_amount'=>['$exists'=>true,'$type'=>'number']
    ]],
    ['$group'=>[
        '_id'=>[
            '$dateToString'=>[
                'format'=>'%Y-%m',
                'date'=>'$order_date',
                'timezone'=>'Asia/Colombo'
            ]
        ],
        'total'=>['$sum'=>'$total_amount']
    ]],
    ['$sort'=>['_id'=>1]]
])->toArray();

$spendPerMonth = array_fill_keys($months,0);
foreach($spendPerMonthCursor as $r) {
    if(isset($spendPerMonth[$r['_id']])) {
        $spendPerMonth[$r['_id']] = (float)$r['total'];
    }
}

// Calculate total spending for last 6 months
$monthly_spending_total = array_sum($spendPerMonth);

// --- JS Data for charts ---
$pieData = json_encode([
    ['Status','Count'],
    ['Pending',$statusCounts['pending']],
    ['Accepted',$statusCounts['accepted']],
    ['In Delivery',$statusCounts['delivery']],
    ['Completed',$statusCounts['completed']],
    ['Rejected',$statusCounts['rejected']]
]);

$lineData = [['Month','Orders']];
foreach($ordersPerMonth as $k=>$v){
    $dt=DateTime::createFromFormat('Y-m',$k); 
    $lineData[]=[$dt->format('M Y'),$v];
}
$lineJson=json_encode($lineData);

$spendData = [['Month','Spent']]; 
foreach($spendPerMonth as $k=>$v){  
    $dt=DateTime::createFromFormat('Y-m',$k); 
    $spendData[]=[$dt->format('M Y'),(float)$v];
}
$spendJson = json_encode($spendData);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Wholesaler Dashboard — DMAS</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/dashboard.css">
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
</head>
<body>

    <nav class="navbar">
        <div class="brand">🌾 DMAS - Wholesaler</div>
        <div class="hamburger" onclick="toggleMenu()">
            <span></span><span></span><span></span>
        </div>
        <div class="menu" id="menu">
            <a href="dashboard.php" class="active">🏠 Dashboard</a>
            <a href="product_marketplace.php">🛒 Marketplace</a>
            <a href="order_management.php">📦 Orders</a>
            <a href="prediction copy.php">📈 Price Prediction</a>
            <a href="view_workers.php">👨‍🔧 View Workers</a>
            <a href="profile.php">👤 My Profile</a>
            <a href="../logout.php" class="logout" onclick="return confirm('Are you sure you want to logout?');">🚪 Logout</a>
        </div>
    </nav>

    <div id="chartsData"
        data-pie='<?= $pieData ?>'
        data-line='<?= $lineJson ?>'
        data-spend='<?= $spendJson ?>'>
    </div>

    <div class="top-cards">
        <div class="card card1"><i class="fa-solid fa-list-check"></i><span><?= $total_orders ?></span><p>Total Orders</p></div>
        <div class="card card2"><i class="fa-solid fa-hourglass-half"></i><span><?= $statusCounts['pending'] ?></span><p>Pending</p></div>
        <div class="card card3"><i class="fa-solid fa-check"></i><span><?= $statusCounts['accepted'] ?></span><p>Accepted</p></div>
        <div class="card card4"><i class="fa-solid fa-money-bill"></i><span>₨ <?= number_format($monthly_spending_total,2) ?></span><p>Spent (Last 6 Months)</p></div>
    </div>

    <div class="charts">
        <div class="chart-container"><h4>Order Status Distribution</h4><div class="chart-box" id="piechart"></div></div>
        <div class="chart-container"><h4>Orders Trend (Last 6 Months)</h4><div class="chart-box" id="linechart"></div></div>
        <div class="chart-container"><h4>Monthly Spending</h4><div class="chart-box" id="spendchart"></div></div>
    </div>

 <script>
        function toggleMenu(){
            const menu = document.getElementById('menu');
            menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
        }

        // Get chart data from PHP
        const chartsEl = document.getElementById('chartsData');
        const pieDataPHP = JSON.parse(chartsEl.dataset.pie);
        const lineDataPHP = JSON.parse(chartsEl.dataset.line);
        const spendDataPHP = JSON.parse(chartsEl.dataset.spend);

        // Debug: Check the data
        console.log("Pie Chart Data:", pieDataPHP);
        console.log("Line Chart Data:", lineDataPHP);
        console.log("Spending Chart Data:", spendDataPHP);

        // Load Google Charts
        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(drawAll);

        function drawAll(){
            drawPie(); 
            drawLine(); 
            drawSpend();
        }

        function drawPie(){
            const data = google.visualization.arrayToDataTable(pieDataPHP);
            const chart = new google.visualization.PieChart(document.getElementById('piechart'));
            chart.draw(data, {
                pieHole: 0.4,
                chartArea: {left:20,top:20,width:'85%',height:'85%'},
                legend:{position:'right', textStyle:{fontSize:12}},
                colors:['#9e2f0f','#f0ba52','#3498db','#2ecc71','#e74c3c'],
                fontName: 'Poppins',
                sliceVisibilityThreshold: 0
            });
        }

        function drawLine(){
            const data = google.visualization.arrayToDataTable(lineDataPHP);
            const chart = new google.visualization.LineChart(document.getElementById('linechart'));
            chart.draw(data, {
                height:300,
                hAxis:{title:'Month'},
                vAxis:{title:'Orders', minValue:0},
                legend:{position:'none'},
                chartArea:{left:60,top:20,width:'85%',height:'75%'},
                colors:['#dc5c26'],
                pointSize:6,
                curveType:'function',
                animation:{startup:true,duration:500,easing:'out'}
            });
        }

        function drawSpend(){
            const data = google.visualization.arrayToDataTable(spendDataPHP);
            
            // Check if we have actual data
            let hasData = false;
            for(let i = 1; i < data.getNumberOfRows(); i++) {
                if(data.getValue(i, 1) > 0) {
                    hasData = true;
                    break;
                }
            }
            
            const chart = new google.visualization.ColumnChart(document.getElementById('spendchart'));
            const options = {
                height:300,
                chartArea:{left:60,top:20,width:'85%',height:'75%'},
                legend:{position:'none'},
                colors:['#f4dcb2'],
                vAxis:{format:'#,###', minValue:0},
                fontName:'Poppins'
            };
            
            chart.draw(data, options);
        }

        // Redraw charts when window is resized
        window.addEventListener('resize', drawAll);
    </script>
</body>
</html>