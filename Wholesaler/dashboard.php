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

        // Orders per month
        $ordersPerMonthCursor = $ordersCollection->aggregate([
            ['$match'=>['wholesaler'=>$wholesaler,'order_date'=>['$exists'=>true]]],
            ['$group'=>['_id'=>['$dateToString'=>['format'=>'%Y-%m','date'=>'$order_date','timezone'=>'Asia/Colombo']], 'count'=>['$sum'=>1]]],
            ['$sort'=>['_id'=>1]]
        ])->toArray();
        $ordersPerMonth = array_fill_keys($months,0);
        foreach($ordersPerMonthCursor as $r) if(isset($ordersPerMonth[$r['_id']])) $ordersPerMonth[$r['_id']]=$r['count'];

        // Spending per month
        $spendPerMonthCursor = $ordersCollection->aggregate([
            ['$match'=>['wholesaler'=>$wholesaler,'status'=>'completed']],
            ['$group'=>['_id'=>['$dateToString'=>['format'=>'%Y-%m','date'=>'$order_date','timezone'=>'Asia/Colombo']],'total'=>['$sum'=>['$ifNull'=>['$total_amount',0]]]]],
            ['$sort'=>['_id'=>1]]
        ])->toArray();
        $spendPerMonth = array_fill_keys($months,0);
        foreach($spendPerMonthCursor as $r) if(isset($spendPerMonth[$r['_id']])) $spendPerMonth[$r['_id']] = $r['total'];
        $monthly_spending_total = array_sum($spendPerMonth);

        // Top products
        $topProductsCursor = $ordersCollection->aggregate([
            ['$match'=>['wholesaler'=>$wholesaler,'product_name'=>['$exists'=>true]]],
            ['$group'=>['_id'=>'$product_name','qty'=>['$sum'=>['$ifNull'=>['$quantity',0]]]]],
            ['$sort'=>['qty'=>-1]], ['$limit'=>5]
        ])->toArray();
        $topProducts = [];
        foreach($topProductsCursor as $r) $topProducts[] = ['name'=>$r['_id'],'qty'=>$r['qty']];

        // --- JS Data for charts ---
        $pieData = json_encode([['Status','Count'],['Pending',$statusCounts['pending']],['Accepted',$statusCounts['accepted']],['In Delivery',$statusCounts['delivery']],['Completed',$statusCounts['completed']],['Rejected',$statusCounts['rejected']]]);
        $lineData = [['Month','Orders']];
        foreach($ordersPerMonth as $k=>$v){$dt=DateTime::createFromFormat('Y-m',$k); $lineData[]=[$dt->format('M Y'),$v];}
        $lineJson=json_encode($lineData);
        $spendData = [['Month','Spent']]; foreach($spendPerMonth as $k=>$v){$dt=DateTime::createFromFormat('Y-m',$k); $spendData[]=[$dt->format('M Y'),$v];}
        $spendJson = json_encode($spendData);
        $topProdData=[['Product','Qty']]; foreach($topProducts as $p) $topProdData[]=[$p['name'],$p['qty']];
        $topProdJson=json_encode($topProdData);
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
        data-spend='<?= $spendJson ?>'
        data-topprod='<?= $topProdJson ?>'>
    </div>

    <div class="top-cards">
        <div class="card card1"><i class="fa-solid fa-list-check"></i><span><?= $total_orders ?></span><p>Total Orders</p></div>
        <div class="card card2"><i class="fa-solid fa-hourglass-half"></i><span><?= $statusCounts['pending'] ?></span><p>Pending</p></div>
        <div class="card card3"><i class="fa-solid fa-check"></i><span><?= $statusCounts['accepted'] ?></span><p>Accepted</p></div>
        <div class="card card4"><i class="fa-solid fa-money-bill"></i><span>₨ <?= number_format($monthly_spending_total,2) ?></span><p>Spent (6 months)</p></div>
    </div>

    <div class="charts">
        <div class="chart-container"><h4>Order Status Distribution</h4><div class="chart-box" id="piechart"></div></div>
        <div class="chart-container"><h4>Orders Trend (Last 6 Months)</h4><div class="chart-box" id="linechart"></div></div>
        <div class="chart-container"><h4>Monthly Spending</h4><div class="chart-box" id="spendchart"></div></div>
        <div class="chart-container"><h4>Top Products</h4><div class="chart-box" id="topprod"></div></div>
    </div>

    <script src="assets/dashboard.js"></script>
</body>
</html>
