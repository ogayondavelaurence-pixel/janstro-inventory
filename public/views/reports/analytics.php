<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Janstro IMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --janstro-orange: #FF6B00;
            --janstro-blue: #1A2332;
            --janstro-light: #F8F9FA;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--janstro-light);
        }

        .navbar {
            background: linear-gradient(135deg, var(--janstro-blue) 0%, #2c3e50 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar {
            background-color: var(--janstro-blue);
            min-height: 100vh;
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            padding-top: 20px;
        }

        .sidebar a {
            color: #fff;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background-color: var(--janstro-orange);
            padding-left: 30px;
        }

        .sidebar a i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .kpi-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-left: 5px solid var(--janstro-orange);
            transition: transform 0.3s;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .kpi-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--janstro-blue);
        }

        .kpi-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .btn-janstro {
            background-color: var(--janstro-orange);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-janstro:hover {
            background-color: #e55a00;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 0, 0.3);
        }

        .powerbi-embed {
            border: 2px dashed #ccc;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: white;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <h4 class="text-white">
                <i class="fas fa-solar-panel text-warning"></i>
                JANSTRO IMS
            </h4>
        </div>
        <a href="/views/dashboard/index.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="/views/inventory/index.php">
            <i class="fas fa-boxes"></i> Inventory
        </a>
        <a href="/views/purchase/orders.php">
            <i class="fas fa-shopping-cart"></i> Purchase Orders
        </a>
        <a href="/views/sales/orders.php">
            <i class="fas fa-dollar-sign"></i> Sales Orders
        </a>
        <a href="/views/reports/analytics.php" class="active">
            <i class="fas fa-chart-bar"></i> Analytics
        </a>
        <a href="/views/admin/users.php">
            <i class="fas fa-users"></i> User Management
        </a>
        <hr class="bg-light">
        <a href="#" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark mb-4" style="border-radius: 15px;">
            <div class="container-fluid">
                <span class="navbar-brand">
                    <i class="fas fa-chart-bar"></i> Business Analytics & Reports
                </span>
                <div class="d-flex">
                    <button class="btn btn-janstro me-2" onclick="exportData('revenue')">
                        <i class="fas fa-download"></i> Export Revenue
                    </button>
                    <button class="btn btn-janstro" onclick="exportData('inventory')">
                        <i class="fas fa-download"></i> Export Inventory
                    </button>
                </div>
            </div>
        </nav>

        <!-- KPI Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-label">
                        <i class="fas fa-dollar-sign text-success"></i> Total Revenue
                    </div>
                    <div class="kpi-value text-success" id="kpiRevenue">₱0</div>
                    <small class="text-muted">Last 30 days</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-label">
                        <i class="fas fa-shopping-cart text-primary"></i> Total Expenses
                    </div>
                    <div class="kpi-value text-primary" id="kpiExpenses">₱0</div>
                    <small class="text-muted">Purchase orders</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-label">
                        <i class="fas fa-box text-warning"></i> Inventory Value
                    </div>
                    <div class="kpi-value text-warning" id="kpiInventory">₱0</div>
                    <small class="text-muted">Current stock</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-label">
                        <i class="fas fa-file-invoice text-danger"></i> Outstanding AR
                    </div>
                    <div class="kpi-value text-danger" id="kpiAR">₱0</div>
                    <small class="text-muted">Accounts receivable</small>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-line"></i> Revenue Trend (12 Months)</h5>
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-pie"></i> Inventory by Category</h5>
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-bar"></i> Top 5 Products</h5>
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h5>
                    <canvas id="lowStockChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Power BI Integration Section -->
        <div class="powerbi-embed">
            <i class="fas fa-chart-area fa-5x text-muted mb-3"></i>
            <h4>Microsoft Power BI Integration</h4>
            <p class="text-muted">Connect your Power BI workspace to visualize advanced analytics</p>
            <button class="btn btn-janstro mt-3">
                <i class="fas fa-plug"></i> Connect Power BI
            </button>
            <p class="mt-3 small text-muted">
                <strong>API Endpoints Available:</strong><br>
                GET /analytics/revenue | /analytics/expenses | /analytics/accounts-receivable | /analytics/inventory-valuation
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE = '';
        let revenueChart, categoryChart, topProductsChart, lowStockChart;

        document.addEventListener('DOMContentLoaded', async () => {
            await loadKPIs();
            await loadCharts();
        });

        async function loadKPIs() {
            try {
                const response = await fetch(`${API_BASE}/analytics/kpis`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const data = await response.json();
                if (data.success) {
                    const kpis = data.data;

                    document.getElementById('kpiRevenue').textContent =
                        '₱' + Number(kpis.monthly_revenue?.total_revenue || 0).toLocaleString();

                    document.getElementById('kpiExpenses').textContent =
                        '₱' + Number(kpis.monthly_expenses?.total_expenses || 0).toLocaleString();

                    document.getElementById('kpiInventory').textContent =
                        '₱' + Number(kpis.inventory_status?.total_value || 0).toLocaleString();

                    document.getElementById('kpiAR').textContent =
                        '₱' + Number(kpis.accounts_receivable?.total_outstanding || 0).toLocaleString();
                }
            } catch (error) {
                console.error('Error loading KPIs:', error);
            }
        }

        async function loadCharts() {
            await loadRevenueChart();
            await loadCategoryChart();
            await loadTopProductsChart();
            await loadLowStockChart();
        }

        async function loadRevenueChart() {
            try {
                const response = await fetch(`${API_BASE}/analytics/revenue`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const data = await response.json();
                const revenueData = data.data || [];

                const ctx = document.getElementById('revenueChart');
                revenueChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: revenueData.map(d => new Date(d.date).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric'
                        })),
                        datasets: [{
                            label: 'Revenue',
                            data: revenueData.map(d => d.daily_revenue),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: value => '₱' + value.toLocaleString()
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error loading revenue chart:', error);
            }
        }

        async function loadCategoryChart() {
            try {
                const response = await fetch(`${API_BASE}/analytics/inventory-valuation`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const data = await response.json();
                const categories = data.data || [];

                const ctx = document.getElementById('categoryChart');
                categoryChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: categories.map(c => c.category),
                        datasets: [{
                            data: categories.map(c => c.total_value),
                            backgroundColor: [
                                '#FF6B00',
                                '#1A2332',
                                '#28a745',
                                '#ffc107',
                                '#17a2b8',
                                '#dc3545'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error loading category chart:', error);
            }
        }

        async function loadTopProductsChart() {
            try {
                const response = await fetch(`${API_BASE}/inventory/summary`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const data = await response.json();
                const categories = data.data?.by_category || [];

                const ctx = document.getElementById('topProductsChart');
                topProductsChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: categories.slice(0, 5).map(c => c.category),
                        datasets: [{
                            label: 'Quantity',
                            data: categories.slice(0, 5).map(c => c.total_quantity),
                            backgroundColor: '#FF6B00'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error loading top products chart:', error);
            }
        }

        async function loadLowStockChart() {
            try {
                const response = await fetch(`${API_BASE}/inventory/low-stock`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const data = await response.json();
                const lowStock = (data.data || []).slice(0, 5);

                const ctx = document.getElementById('lowStockChart');
                lowStockChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: lowStock.map(item => item.item_name),
                        datasets: [{
                            label: 'Current Stock',
                            data: lowStock.map(item => item.quantity),
                            backgroundColor: '#dc3545'
                        }, {
                            label: 'Reorder Level',
                            data: lowStock.map(item => item.reorder_level),
                            backgroundColor: '#ffc107'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error loading low stock chart:', error);
            }
        }

        async function exportData(type) {
            try {
                const response = await fetch(`${API_BASE}/analytics/export/csv/${type}`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                if (!response.ok) throw new Error('Export failed');

                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `janstro_${type}_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);

                alert(`${type.toUpperCase()} data exported successfully!`);
            } catch (error) {
                console.error('Export error:', error);
                alert('Failed to export data');
            }
        }

        function logout() {
            localStorage.removeItem('token');
            window.location.href = window.location.href = '/views/auth/login.php';
        }
    </script>
</body>

</html>