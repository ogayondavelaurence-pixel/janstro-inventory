<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Orders - Janstro IMS</title>
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

        .badge-scheduled {
            background-color: #17a2b8;
        }

        .badge-in_progress {
            background-color: #6c757d;
        }

        .badge-completed {
            background-color: #28a745;
        }

        .badge-cancelled {
            background-color: #dc3545;
        }

        .invoice-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
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
        <a href="/janstro-inventory/public/views/purchase/orders.php">
            <i class="fas fa-shopping-cart"></i> Purchase Orders
        </a>
        <a href="/janstro-inventory/public/views/sales/orders.php" class="active">
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
                    <i class="fas fa-dollar-sign"></i> Sales Orders
                </span>
                <div class="d-flex">
                    <button class="btn btn-janstro" data-bs-toggle="modal" data-bs-target="#createSOModal">
                        <i class="fas fa-plus"></i> Create Sales Order
                    </button>
                </div>
            </div>
        </nav>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #ffc107 0%, #ff6f00 100%);">
                    <h6><i class="fas fa-clock"></i> Pending</h6>
                    <h2 id="pendingCount">0</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                    <h6><i class="fas fa-calendar-check"></i> Scheduled</h6>
                    <h2 id="scheduledCount">0</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%);">
                    <h6><i class="fas fa-check-circle"></i> Completed</h6>
                    <h2 id="completedCount">0</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h6><i class="fas fa-dollar-sign"></i> Total Revenue</h6>
                    <h2 id="totalRevenue">₱0</h2>
                </div>
            </div>
        </div>

        <!-- SO Table -->
        <div class="table-container">
            <h5 class="mb-4"><i class="fas fa-list"></i> All Sales Orders</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Installation Date</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="soTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create SO Modal -->
    <div class="modal fade" id="createSOModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Create Sales Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createSOForm">
                        <div class="mb-3">
                            <label class="form-label">Customer</label>
                            <select class="form-select" name="customer_id" required id="customerSelect">
                                <option value="">Select Customer</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Installation Address</label>
                                <input type="text" class="form-control" name="installation_address">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Installation Date</label>
                                <input type="date" class="form-control" name="installation_date" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        <hr>
                        <h6>Items <small class="text-muted">(Solar panels and components)</small></h6>
                        <div id="soItemsContainer">
                            <div class="row mb-2 so-item-row">
                                <div class="col-md-6">
                                    <select class="form-select item-select" name="item_id[]" required>
                                        <option value="">Select Item</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control qty-input" name="quantity[]" placeholder="Qty" required min="1">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control price-display" placeholder="Price" readonly>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addSOItemRow()">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                        <div class="mt-3 p-3 bg-light rounded">
                            <h5>Total: <span id="soTotalAmount">₱0.00</span></h5>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="createSO()">
                        <i class="fas fa-save"></i> Create Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE = '/janstro-inventory/public';
        let itemsData = [];

        document.addEventListener('DOMContentLoaded', async () => {
            await loadSalesOrders();
            await loadCustomers();
            await loadItems();
            setupItemCalculation();
        });

        async function loadSalesOrders() {
            try {
                const response = await fetch(`${API_BASE}/sales-orders`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                if (!response.ok) throw new Error('Failed to load orders');

                const data = await response.json();
                renderSOTable(data.data || []);
                updateStats(data.data || []);
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load sales orders');
            }
        }

        async function loadCustomers() {
            try {
                const response = await fetch(`${API_BASE}/customers`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const data = await response.json();
                const select = document.getElementById('customerSelect');

                (data.data || []).forEach(customer => {
                    select.innerHTML += `<option value="${customer.customer_id}">${customer.customer_name}</option>`;
                });
            } catch (error) {
                console.error('Error loading customers:', error);
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
                itemsData = data.data || [];

                document.querySelectorAll('.item-select').forEach(select => {
                    itemsData.forEach(item => {
                        select.innerHTML += `<option value="${item.item_id}" data-price="${item.unit_price}">${item.item_name} - ₱${Number(item.unit_price).toLocaleString()}</option>`;
                    });
                });
            } catch (error) {
                console.error('Error loading items:', error);
            }
        }

        function setupItemCalculation() {
            document.getElementById('soItemsContainer').addEventListener('change', calculateTotal);
            document.getElementById('soItemsContainer').addEventListener('input', calculateTotal);
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.so-item-row').forEach(row => {
                const select = row.querySelector('.item-select');
                const qtyInput = row.querySelector('.qty-input');
                const priceDisplay = row.querySelector('.price-display');

                if (select.value && qtyInput.value) {
                    const selectedOption = select.options[select.selectedIndex];
                    const price = parseFloat(selectedOption.getAttribute('data-price') || 0);
                    const qty = parseInt(qtyInput.value || 0);
                    const lineTotal = price * qty;

                    priceDisplay.value = '₱' + lineTotal.toLocaleString();
                    total += lineTotal;
                }
            });

            document.getElementById('soTotalAmount').textContent = '₱' + total.toLocaleString();
        }

        function renderSOTable(orders) {
            const tbody = document.getElementById('soTableBody');

            if (orders.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                            <p>No sales orders found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = orders.map(order => `
                <tr>
                    <td><strong>#${order.order_id}</strong></td>
                    <td>${order.customer_name || 'N/A'}</td>
                    <td>${order.installation_date ? new Date(order.installation_date).toLocaleDateString() : 'TBD'}</td>
                    <td>${order.item_count || 0} items</td>
                    <td><strong>₱${Number(order.total_amount || 0).toLocaleString()}</strong></td>
                    <td>
                        <span class="badge badge-${order.status}">
                            ${order.status.replace('_', ' ').toUpperCase()}
                        </span>
                    </td>
                    <td>
                        ${order.status === 'pending' || order.status === 'scheduled' ? `
                            <button class="btn btn-sm btn-success" onclick="completeInstallation(${order.order_id})">
                                <i class="fas fa-check"></i> Complete
                            </button>
                        ` : ''}
                        ${order.status === 'completed' ? `
                            <span class="invoice-badge">
                                <i class="fas fa-file-invoice-dollar"></i> Invoice Generated
                            </span>
                        ` : ''}
                        <button class="btn btn-sm btn-info" onclick="viewSO(${order.order_id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        async function createSO() {
            const form = document.getElementById('createSOForm');
            const formData = new FormData(form);

            const items = [];
            const itemIds = formData.getAll('item_id[]');
            const quantities = formData.getAll('quantity[]');

            for (let i = 0; i < itemIds.length; i++) {
                if (itemIds[i] && quantities[i]) {
                    items.push({
                        item_id: parseInt(itemIds[i]),
                        quantity: parseInt(quantities[i])
                    });
                }
            }

            if (items.length === 0) {
                alert('Please add at least one item');
                return;
            }

            const soData = {
                customer_id: parseInt(formData.get('customer_id')),
                installation_address: formData.get('installation_address'),
                installation_date: formData.get('installation_date'),
                notes: formData.get('notes'),
                items: items
            };

            try {
                const response = await fetch(`${API_BASE}/sales-orders`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    },
                    body: JSON.stringify(soData)
                });

                const result = await response.json();

                if (result.success) {
                    alert(`Sales Order #${result.data.order_id} created successfully!`);
                    bootstrap.Modal.getInstance(document.getElementById('createSOModal')).hide();
                    form.reset();
                    await loadSalesOrders();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to create sales order');
            }
        }

        async function completeInstallation(orderId) {
            if (!confirm('Mark this installation as completed? This will generate an invoice and update inventory.')) return;

            try {
                const response = await fetch(`${API_BASE}/sales-orders/complete/${orderId}`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert(`Installation completed! Invoice #${result.data.invoice_number} generated.`);
                    await loadSalesOrders();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to complete installation');
            }
        }

        function addSOItemRow() {
            const container = document.getElementById('soItemsContainer');
            const newRow = document.querySelector('.so-item-row').cloneNode(true);
            newRow.querySelectorAll('input').forEach(input => input.value = '');
            newRow.querySelectorAll('select').forEach(select => select.value = '');

            // Re-populate items
            const select = newRow.querySelector('.item-select');
            select.innerHTML = '<option value="">Select Item</option>';
            itemsData.forEach(item => {
                select.innerHTML += `<option value="${item.item_id}" data-price="${item.unit_price}">${item.item_name} - ₱${Number(item.unit_price).toLocaleString()}</option>`;
            });

            container.appendChild(newRow);
        }

        function updateStats(orders) {
            let pending = 0,
                scheduled = 0,
                completed = 0,
                totalRevenue = 0;

            orders.forEach(order => {
                if (order.status === 'pending') pending++;
                if (order.status === 'scheduled') scheduled++;
                if (order.status === 'completed') {
                    completed++;
                    totalRevenue += Number(order.total_amount || 0);
                }
            });

            document.getElementById('pendingCount').textContent = pending;
            document.getElementById('scheduledCount').textContent = scheduled;
            document.getElementById('completedCount').textContent = completed;
            document.getElementById('totalRevenue').textContent = '₱' + totalRevenue.toLocaleString();
        }

        function viewSO(orderId) {
            alert('View details for Order #' + orderId);
        }

        function logout() {
            localStorage.removeItem('token');
            window.location.href = '/janstro-inventory/public/views/auth/login.php';
        }
    </script>
</body>

</html>