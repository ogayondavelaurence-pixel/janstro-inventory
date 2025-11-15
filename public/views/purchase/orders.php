<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders - Janstro IMS</title>
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

        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-approved {
            background-color: #17a2b8;
        }

        .badge-delivered {
            background-color: #28a745;
        }

        .badge-cancelled {
            background-color: #dc3545;
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
        <a href="/janstro-inventory/public/views/dashboard/index.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="/janstro-inventory/public/views/inventory/index.php">
            <i class="fas fa-boxes"></i> Inventory
        </a>
        <a href="/janstro-inventory/public/views/purchase/orders.php" class="active">
            <i class="fas fa-shopping-cart"></i> Purchase Orders
        </a>
        <a href="/janstro-inventory/public/views/sales/orders.php">
            <i class="fas fa-dollar-sign"></i> Sales Orders
        </a>
        <a href="/janstro-inventory/public/views/reports/analytics.php">
            <i class="fas fa-chart-bar"></i> Analytics
        </a>
        <a href="/janstro-inventory/public/views/admin/users.php">
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
                    <i class="fas fa-shopping-cart"></i> Purchase Orders
                </span>
                <div class="d-flex">
                    <button class="btn btn-janstro" data-bs-toggle="modal" data-bs-target="#createPOModal">
                        <i class="fas fa-plus"></i> Create PO
                    </button>
                </div>
            </div>
        </nav>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #ffc107 0%, #ff6f00 100%);">
                    <h6><i class="fas fa-clock"></i> Pending</h6>
                    <h2 id="pendingCount">-</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                    <h6><i class="fas fa-check-circle"></i> Approved</h6>
                    <h2 id="approvedCount">-</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%);">
                    <h6><i class="fas fa-truck"></i> Delivered</h6>
                    <h2 id="deliveredCount">-</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                    <h6><i class="fas fa-ban"></i> Cancelled</h6>
                    <h2 id="cancelledCount">-</h2>
                </div>
            </div>
        </div>

        <!-- PO Table -->
        <div class="table-container">
            <h5 class="mb-4"><i class="fas fa-list"></i> All Purchase Orders</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>PO #</th>
                            <th>Supplier</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="poTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create PO Modal -->
    <div class="modal fade" id="createPOModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Create Purchase Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createPOForm">
                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id" required id="supplierSelect">
                                <option value="">Select Supplier</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        <hr>
                        <h6>Items</h6>
                        <div id="itemsContainer">
                            <div class="row mb-2 item-row">
                                <div class="col-md-6">
                                    <select class="form-select" name="item_id[]" required>
                                        <option value="">Select Item</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control" name="quantity[]" placeholder="Qty" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" step="0.01" class="form-control" name="unit_price[]" placeholder="Price">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addItemRow()">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-janstro" onclick="createPO()">
                        <i class="fas fa-save"></i> Create PO
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE = '/janstro-inventory/public';

        document.addEventListener('DOMContentLoaded', async () => {
            await loadPurchaseOrders();
            await loadSuppliers();
            await loadItems();
            updateStats();
        });

        async function loadPurchaseOrders() {
            try {
                const response = await fetch(`${API_BASE}/purchase-orders`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                if (!response.ok) throw new Error('Failed to load orders');

                const data = await response.json();
                renderPOTable(data.data || []);
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load purchase orders');
            }
        }

        async function loadSuppliers() {
            try {
                const response = await fetch(`${API_BASE}/suppliers`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const data = await response.json();
                const select = document.getElementById('supplierSelect');

                (data.data || []).forEach(supplier => {
                    select.innerHTML += `<option value="${supplier.supplier_id}">${supplier.supplier_name}</option>`;
                });
            } catch (error) {
                console.error('Error loading suppliers:', error);
            }
        }

        async function loadItems() {
            try {
                const response = await fetch(`${API_BASE}/inventory`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const data = await response.json();
                const items = data.data || [];

                document.querySelectorAll('[name="item_id[]"]').forEach(select => {
                    items.forEach(item => {
                        select.innerHTML += `<option value="${item.item_id}">${item.item_name} (₱${item.unit_price})</option>`;
                    });
                });
            } catch (error) {
                console.error('Error loading items:', error);
            }
        }

        function renderPOTable(orders) {
            const tbody = document.getElementById('poTableBody');

            if (orders.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                            <p>No purchase orders found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = orders.map(order => `
                <tr>
                    <td><strong>#${order.po_id}</strong></td>
                    <td>${order.supplier_name || 'N/A'}</td>
                    <td>${new Date(order.po_date).toLocaleDateString()}</td>
                    <td>${order.item_count || 1} items</td>
                    <td><strong>₱${Number(order.total_amount || 0).toLocaleString()}</strong></td>
                    <td>
                        <span class="badge badge-${order.status}">
                            ${order.status.toUpperCase()}
                        </span>
                    </td>
                    <td>
                        ${order.status === 'pending' ? `
                            <button class="btn btn-sm btn-success" onclick="receivePO(${order.po_id})">
                                <i class="fas fa-truck"></i> Receive
                            </button>
                        ` : ''}
                        <button class="btn btn-sm btn-info" onclick="viewPO(${order.po_id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        async function createPO() {
            const form = document.getElementById('createPOForm');
            const formData = new FormData(form);

            const items = [];
            const itemIds = formData.getAll('item_id[]');
            const quantities = formData.getAll('quantity[]');
            const prices = formData.getAll('unit_price[]');

            for (let i = 0; i < itemIds.length; i++) {
                if (itemIds[i]) {
                    items.push({
                        item_id: parseInt(itemIds[i]),
                        quantity: parseInt(quantities[i]),
                        unit_price: prices[i] ? parseFloat(prices[i]) : null
                    });
                }
            }

            const poData = {
                supplier_id: parseInt(formData.get('supplier_id')),
                notes: formData.get('notes'),
                items: items
            };

            try {
                const response = await fetch(`${API_BASE}/purchase-orders`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    },
                    body: JSON.stringify(poData)
                });

                const result = await response.json();

                if (result.success) {
                    alert('Purchase Order created successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('createPOModal')).hide();
                    form.reset();
                    await loadPurchaseOrders();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to create purchase order');
            }
        }

        async function receivePO(poId) {
            if (!confirm('Mark this PO as received? This will update inventory.')) return;

            try {
                const response = await fetch(`${API_BASE}/purchase-orders/receive/${poId}`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('PO received successfully! Inventory updated.');
                    await loadPurchaseOrders();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to receive PO');
            }
        }

        function addItemRow() {
            const container = document.getElementById('itemsContainer');
            const newRow = document.querySelector('.item-row').cloneNode(true);
            newRow.querySelectorAll('input').forEach(input => input.value = '');
            newRow.querySelectorAll('select').forEach(select => select.value = '');
            container.appendChild(newRow);
        }

        function updateStats() {
            const tbody = document.getElementById('poTableBody');
            const rows = tbody.querySelectorAll('tr');

            let pending = 0,
                approved = 0,
                delivered = 0,
                cancelled = 0;

            rows.forEach(row => {
                const badge = row.querySelector('.badge');
                if (badge) {
                    if (badge.classList.contains('badge-pending')) pending++;
                    if (badge.classList.contains('badge-approved')) approved++;
                    if (badge.classList.contains('badge-delivered')) delivered++;
                    if (badge.classList.contains('badge-cancelled')) cancelled++;
                }
            });

            document.getElementById('pendingCount').textContent = pending;
            document.getElementById('approvedCount').textContent = approved;
            document.getElementById('deliveredCount').textContent = delivered;
            document.getElementById('cancelledCount').textContent = cancelled;
        }

        function logout() {
            localStorage.removeItem('token');
            window.location.href = '/janstro-inventory/public/views/auth/login.php';
        }
    </script>
</body>

</html>