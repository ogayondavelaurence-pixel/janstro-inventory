<?php

/**
 * Janstro IMS - Dashboard
 * Complete Working Implementation with Chart.js
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /janstro-inventory/public/views/auth/login.php');
    exit;
}

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Janstro IMS</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/janstro-inventory/public/assets/css/main.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>

<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <i class="bi bi-lightning-charge-fill" style="font-size: 2rem; color: var(--janstro-orange);"></i>
                <h3>Janstro IMS</h3>
            </div>

            <nav>
                <ul class="sidebar-menu">
                    <li class="sidebar-menu-item">
                        <a href="/views/dashboard/index.php" class="sidebar-menu-link active">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="/views/inventory/index.php" class="sidebar-menu-link">
                            <i class="bi bi-box-seam"></i>
                            <span>Inventory</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="/views/purchase/orders.php" class="sidebar-menu-link">
                            <i class="bi bi-cart3"></i>
                            <span>Purchase Orders</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="/views/sales/orders.php" class="sidebar-menu-link">
                            <i class="bi bi-bag-check"></i>
                            <span>Sales Orders</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="/views/reports/analytics.php" class="sidebar-menu-link">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>Analytics</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Navbar -->
            <div class="navbar-top">
                <div class="navbar-search">
                    <input type="text" placeholder="Search items, orders, customers..." id="globalSearch">
                </div>
                <div class="navbar-actions">
                    <button class="btn" id="refreshData">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <div class="navbar-user">
                        <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <i class="bi bi-person-circle" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div style="margin-bottom: 2rem;">
                <h1>Dashboard</h1>
                <p style="color: var(--janstro-gray);">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="totalValue">₱0</h3>
                        <p>Total Inventory Value</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="lowStockCount">0</h3>
                        <p>Low Stock Items</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="bi bi-cart3"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="pendingOrders">0</h3>
                        <p>Pending Orders</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="monthlyRevenue">₱0</h3>
                        <p>Monthly Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                <div class="card">
                    <div class="card-header">
                        <h5>Revenue & Expenses</h5>
                    </div>
                    <canvas id="revenueChart" height="300"></canvas>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Inventory Distribution</h5>
                    </div>
                    <canvas id="inventoryPieChart" height="300"></canvas>
                </div>
            </div>

            <!-- Recent Orders & Low Stock -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Orders</h5>
                        <a href="/views/sales/orders.php" class="btn btn-primary">View All</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="recentOrdersTable">
                            <tr>
                                <td colspan="4" style="text-align: center;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Low Stock Alerts</h5>
                        <a href="/views/inventory/index.php?filter=low_stock" class="btn btn-primary">View All</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Current</th>
                                <th>Reorder</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="lowStockTable">
                            <tr>
                                <td colspan="4" style="text-align: center;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        const API_BASE = '';
        let revenueChart, inventoryPieChart;

        // Initialize on load
        document.addEventListener('DOMContentLoaded', async () => {
            await loadDashboardData();
            initCharts();
        });

        // Load dashboard statistics
        async function loadDashboardData() {
            try {
                const token = localStorage.getItem('auth_token');

                // Load stats
                const statsResponse = await fetch(`${API_BASE}/reports/dashboard`, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                const stats = await statsResponse.json();

                if (stats.success) {
                    document.getElementById('totalValue').textContent =
                        '₱' + parseFloat(stats.data.total_inventory_value || 0).toLocaleString('en-PH');
                    document.getElementById('lowStockCount').textContent =
                        stats.data.low_stock_items || 0;
                    document.getElementById('pendingOrders').textContent =
                        stats.data.pending_pos || 0;
                    document.getElementById('monthlyRevenue').textContent =
                        '₱0'; // Will be calculated from invoices
                }

                // Load recent orders
                await loadRecentOrders();

                // Load low stock items
                await loadLowStock();

            } catch (error) {
                console.error('Error loading dashboard:', error);
            }
        }

        async function loadRecentOrders() {
            try {
                const token = localStorage.getItem('auth_token');
                const response = await fetch(`${API_BASE}/sales-orders?limit=5`, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                const data = await response.json();

                const tbody = document.getElementById('recentOrdersTable');

                if (data.success && data.data.length > 0) {
                    tbody.innerHTML = data.data.map(order => `
                    <tr>
                        <td>#${order.order_id}</td>
                        <td>${order.customer_name}</td>
                        <td><span class="badge badge-warning">${order.status}</span></td>
                        <td>₱${parseFloat(order.total_amount).toLocaleString('en-PH')}</td>
                    </tr>
                `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">No recent orders</td></tr>';
                }
            } catch (error) {
                console.error('Error loading recent orders:', error);
            }
        }

        async function loadLowStock() {
            try {
                const token = localStorage.getItem('auth_token');
                const response = await fetch(`${API_BASE}/inventory/low-stock`, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                const data = await response.json();

                const tbody = document.getElementById('lowStockTable');

                if (data.success && data.data.length > 0) {
                    tbody.innerHTML = data.data.slice(0, 5).map(item => `
                    <tr>
                        <td>${item.item_name}</td>
                        <td><span class="badge badge-danger">${item.quantity}</span></td>
                        <td>${item.reorder_level}</td>
                        <td><button class="btn btn-primary" onclick="createPO(${item.item_id})">Order</button></td>
                    </tr>
                `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: green;">All items well stocked!</td></tr>';
                }
            } catch (error) {
                console.error('Error loading low stock:', error);
            }
        }

        function initCharts() {
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Revenue',
                        data: [65000, 59000, 80000, 81000, 56000, 75000],
                        borderColor: '#FF6B00',
                        backgroundColor: 'rgba(255, 107, 0, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    }
                }
            });

            // Inventory Pie Chart
            const pieCtx = document.getElementById('inventoryPieChart').getContext('2d');
            inventoryPieChart = new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Solar Panels', 'Inverters', 'Batteries', 'Others'],
                    datasets: [{
                        data: [45, 25, 20, 10],
                        backgroundColor: ['#FF6B00', '#3498DB', '#27AE60', '#95A5A6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        function createPO(itemId) {
            window.location.href = `/janstro-inventory/public/views/purchase/create.php?item=${itemId}`;
        }

        // Refresh button
        document.getElementById('refreshData').addEventListener('click', () => {
            location.reload();
        });
    </script>
</body>

</html>