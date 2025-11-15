<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Janstro IMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            transition: all 0.3s;
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

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card.orange {
            background: linear-gradient(135deg, var(--janstro-orange) 0%, #ff8c42 100%);
        }

        .stats-card.blue {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stats-card.green {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
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

        .badge-low-stock {
            background-color: #dc3545;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.6;
            }
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 50px;
        }

        .search-box {
            border-radius: 25px;
            border: 2px solid var(--janstro-orange);
            padding: 10px 20px;
        }

        .search-box:focus {
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 0, 0.25);
            border-color: var(--janstro-orange);
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
        <a href="/views/inventory/index.php" class="active">
            <i class="fas fa-boxes"></i> Inventory
        </a>
        <a href="/views/purchase/orders.php">
            <i class="fas fa-shopping-cart"></i> Purchase Orders
        </a>
        <a href="/views/sales/orders.php">
            <i class="fas fa-dollar-sign"></i> Sales Orders
        </a>
        <a href="/views/reports/analytics.php">
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
                    <i class="fas fa-boxes"></i> Inventory Management
                </span>
                <div class="d-flex align-items-center">
                    <input type="text" id="searchBox" class="form-control search-box me-3" placeholder="Search items...">
                    <button class="btn btn-janstro" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>
        </nav>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card orange">
                    <h6><i class="fas fa-box"></i> Total Items</h6>
                    <h2 id="totalItems">-</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6><i class="fas fa-exclamation-triangle"></i> Low Stock</h6>
                    <h2 id="lowStockCount">-</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card blue">
                    <h6><i class="fas fa-dollar-sign"></i> Total Value</h6>
                    <h2 id="totalValue">-</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card green">
                    <h6><i class="fas fa-folder"></i> Categories</h6>
                    <h2 id="categoriesCount">-</h2>
                </div>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading inventory data...</p>
        </div>

        <!-- Inventory Table -->
        <div class="table-container" id="tableContainer">
            <h5 class="mb-4"><i class="fas fa-list"></i> Inventory Items</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Item ID</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Reorder Level</th>
                            <th>Unit Price</th>
                            <th>Total Value</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addItemForm">
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" name="item_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Select Category</option>
                                <option value="1">Solar Panels</option>
                                <option value="2">Inverters</option>
                                <option value="3">Batteries</option>
                                <option value="4">Mounting Systems</option>
                                <option value="5">Cables & Connectors</option>
                                <option value="6">Monitoring Systems</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="quantity" value="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit</label>
                                <input type="text" class="form-control" name="unit" value="pcs" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" class="form-control" name="reorder_level" value="10" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit Price (₱)</label>
                                <input type="number" step="0.01" class="form-control" name="unit_price" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-janstro" onclick="addItem()">
                        <i class="fas fa-save"></i> Add Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE = '';
        let allItems = [];

        // Load inventory on page load
        document.addEventListener('DOMContentLoaded', async () => {
            await loadInventory();
            await loadStats();
        });

        async function loadInventory() {
            showLoading(true);
            try {
                const response = await fetch(`${API_BASE}/inventory`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                if (!response.ok) {
                    if (response.status === 401) {
                        alert('Session expired. Please login again.');
                        window.location.href = '/views/auth/login.php';
                        return;
                    }
                    throw new Error('Failed to load inventory');
                }

                const data = await response.json();
                allItems = data.data || [];
                renderInventoryTable(allItems);
            } catch (error) {
                console.error('Error loading inventory:', error);
                alert('Failed to load inventory data');
            } finally {
                showLoading(false);
            }
        }

        async function loadStats() {
            try {
                const response = await fetch(`${API_BASE}/inventory/summary`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const data = await response.json();
                if (data.success) {
                    document.getElementById('totalItems').textContent = data.data.overall.total_items;
                    document.getElementById('categoriesCount').textContent = data.data.overall.total_categories;
                    document.getElementById('totalValue').textContent = '₱' + Number(data.data.overall.grand_total_value).toLocaleString();
                }

                const lowStockResponse = await fetch(`${API_BASE}/inventory/low-stock`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });
                const lowStockData = await lowStockResponse.json();
                document.getElementById('lowStockCount').textContent = lowStockData.data.length;
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        function renderInventoryTable(items) {
            const tbody = document.getElementById('inventoryTableBody');

            if (items.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="fas fa-box-open fa-3x mb-3"></i>
                            <p>No items found in inventory</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = items.map(item => `
                <tr>
                    <td>${item.item_id}</td>
                    <td><strong>${item.item_name}</strong></td>
                    <td><span class="badge bg-info">${item.category_name || 'N/A'}</span></td>
                    <td>${item.quantity}</td>
                    <td>${item.unit}</td>
                    <td>${item.reorder_level}</td>
                    <td>₱${Number(item.unit_price).toLocaleString()}</td>
                    <td><strong>₱${Number(item.total_value).toLocaleString()}</strong></td>
                    <td>
                        ${item.is_low_stock ? 
                            '<span class="badge badge-low-stock"><i class="fas fa-exclamation-triangle"></i> Low Stock</span>' : 
                            '<span class="badge bg-success">OK</span>'
                        }
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="editItem(${item.item_id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteItem(${item.item_id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        async function addItem() {
            const form = document.getElementById('addItemForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            try {
                const response = await fetch(`${API_BASE}/inventory`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    alert('Item added successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
                    form.reset();
                    await loadInventory();
                    await loadStats();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error adding item:', error);
                alert('Failed to add item');
            }
        }

        async function deleteItem(itemId) {
            if (!confirm('Are you sure you want to delete this item?')) return;

            try {
                const response = await fetch(`${API_BASE}/inventory/${itemId}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('Item deleted successfully!');
                    await loadInventory();
                    await loadStats();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error deleting item:', error);
                alert('Failed to delete item');
            }
        }

        function showLoading(show) {
            document.getElementById('loadingSpinner').style.display = show ? 'block' : 'none';
            document.getElementById('tableContainer').style.display = show ? 'none' : 'block';
        }

        // Search functionality
        document.getElementById('searchBox').addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const filtered = allItems.filter(item =>
                item.item_name.toLowerCase().includes(searchTerm) ||
                (item.category_name && item.category_name.toLowerCase().includes(searchTerm))
            );
            renderInventoryTable(filtered);
        });

        function logout() {
            localStorage.removeItem('token');
            window.location.href = '/views/auth/login.php';

        }
    </script>
</body>

</html>