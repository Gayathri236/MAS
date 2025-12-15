// Toggle menu
function toggleMenu(){
    const menu = document.getElementById('menu');
    menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
}


const chartsEl = document.getElementById('chartsData');
const pieDataPHP = JSON.parse(chartsEl.dataset.pie);
const lineDataPHP = JSON.parse(chartsEl.dataset.line);
const spendDataPHP = JSON.parse(chartsEl.dataset.spend);
const topProdPHP = JSON.parse(chartsEl.dataset.topprod);

// Google Charts
google.charts.load('current', {'packages':['corechart','bar']});
google.charts.setOnLoadCallback(drawAll);

function drawAll(){
    drawPie(); drawLine(); drawSpend(); drawTopProd();
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
    const chart = new google.visualization.ColumnChart(document.getElementById('spendchart'));
    chart.draw(data, {
        height:300,
        chartArea:{left:60,top:20,width:'85%',height:'75%'},
        legend:{position:'none'},
        colors:['#f4dcb2'],
        vAxis:{format:'#,###', minValue:0},
        fontName:'Poppins'
    });
}

function drawTopProd(){
    const data = google.visualization.arrayToDataTable(topProdPHP);
    const chart = new google.visualization.BarChart(document.getElementById('topprod'));
    chart.draw(data, {
        height:300,
        chartArea:{left:120,top:20,width:'70%',height:'70%'},
        legend:{position:'none'},
        colors:['#f0ba52'],
        hAxis:{title:'Quantity', minValue:0},
        fontName:'Poppins'
    });
}

// Redraw charts on window resize
window.addEventListener('resize', drawAll);
