<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Inquiries - Janstro Prime Solar IMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --sidebar-width: 260px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header i {
            font-size: 48px;
        }

        .sidebar-header h4 {
            font-weight: 700;
            margin: 10px 0 0;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-section {
            padding: 10px 25px;
            font-size: 11px;
            text-transform: uppercase;
            opacity: 0.7;
            letter-spacing: 1px;
        }

        .menu-item {
            padding: 12px 25px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: white;
            color: white;
        }

        .menu-item i {
            font-size: 18px;
            margin-right: 12px;
            width: 20px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .top-nav {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .top-nav h5 {
            margin: 0;
            font-weight: 700;
            font-size: 24px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
        }

        .content-area {
            padding: 30px;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: #2c3e50;
        }

        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }

        /* Inquiry Cards */
        .inquiry-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border-left: 5px solid #667eea;
            transition: all 0.3s;
        }

        .inquiry-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .inquiry-card.urgent {
            border-left-color: #dc3545;
        }

        .inquiry-card.processing {
            border-left-color: #ffc107;
        }

        .inquiry-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .inquiry-customer {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
        }

        .inquiry-ref {
            font-size: 12px;
            color: #6c757d;
        }

        .inquiry-status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .inquiry-status.new {
            background: #e3f2fd;
            color: #1565c0;
        }

        .inquiry-status.processing {
            background: #fff3e0;
            color: #ef6c00;
        }

        .inquiry-status.quoted {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .inquiry-status.converted {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .inquiry-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item label {
            font-size: 11px;
            color: #6c757d;
            text-transform: uppercase;
            display: block;
            margin-bottom: 3px;
        }

        .detail-item span {
            font-weight: 600;
            color: #2c3e50;
        }

        .inquiry-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .stock-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .stock-badge.available {
            background: #d4edda;
            color: #155724;
        }

        .stock-badge.insufficient {
            background: #f8d7da;
            color: #721c24;
        }

        .stock-badge.checking {
            background: #fff3cd;
            color: #856404;
        }

        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="bi bi-sun"></i>
            <h4>Janstro Prime</h4>
            <small>Solar IMS v3.0</small>
        </div>
        <div class="sidebar-menu">
            <div class="menu-section">Main</div>
            <a href="dashboard.html" class="menu-item"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>

            <div class="menu-section">Customer Service</div>
            <a href="inquiries.html" class="menu-item active"><i class="bi bi-chat-dots"></i><span>Inquiries</span></a>
            <a href="customer-inquiry.html" class="menu-item"><i class="bi bi-person-plus"></i><span>New Inquiry Form</span></a>

            <div class="menu-section">Inventory</div>
            <a href="inventory.html" class="menu-item"><i class="bi bi-box"></i><span>All Items</span></a>
            <a href="stock-movements.html" class="menu-item"><i class="bi bi-arrow-left-right"></i><span>Stock Movements</span></a>

            <div class="menu-section">Orders</div>
            <a href="purchase-orders.html" class="menu-item"><i class="bi bi-cart-plus"></i><span>Purchase Orders</span></a>
            <a href="sales-orders.html" class="menu-item"><i class="bi bi-cart-check"></i><span>Sales Orders</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="bi bi-chat-dots me-2"></i>Customer Inquiries</h5>
            <div class="d-flex align-items-center gap-3">
                <div class="user-avatar" id="userAvatar">S</div>
                <button class="btn-logout" onclick="handleLogout()"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
            </div>
        </div>

        <div class="content-area">
            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e3f2fd; color: #1565c0;"><i class="bi bi-envelope"></i></div>
                    <div class="stat-value" id="statNew">0</div>
                    <div class="stat-label">New Inquiries</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff3e0; color: #ef6c00;"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-value" id="statProcessing">0</div>
                    <div class="stat-label">Processing</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e8f5e9; color: #2e7d32;"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-value" id="statConverted">0</div>
                    <div class="stat-label">Converted to SO</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fce4ec; color: #c2185b;"><i class="bi bi-percent"></i></div>
                    <div class="stat-value" id="statRate">0%</div>
                    <div class="stat-label">Conversion Rate</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Filter by Status</label>
                        <select class="form-select" id="filterStatus" onchange="loadInquiries()">
                            <option value="">All Inquiries</option>
                            <option value="new">🔵 New</option>
                            <option value="processing">🟡 Processing</option>
                            <option value="quoted">🟢 Quoted</option>
                            <option value="converted">🟣 Converted</option>
                            <option value="cancelled">⚫ Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Search</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="Customer name or phone..." onkeyup="filterInquiries()">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" onclick="loadInquiries()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Inquiries List -->
            <div id="inquiriesContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3 text-muted">Loading inquiries...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- View Inquiry Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Inquiry Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/api.js"></script>
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/rbac.js"></script>

    <script>
        const API_URL = 'http://localhost:8080/janstro-inventory/public';
        let allInquiries = [];

        // Check auth
        if (!API.isAuthenticated()) {
            window.location.href = 'index.html';
        }

        document.addEventListener('DOMContentLoaded', async () => {
            RBAC.init();
            const user = API.getUser();
            if (user) {
                document.getElementById('userAvatar').textContent = (user.name || user.username).charAt(0).toUpperCase();
            }
            await loadStatistics();
            await loadInquiries();
        });

        async function loadStatistics() {
            try {
                const token = API.getToken();
                const res = await fetch(`${API_URL}/inquiries/stats`, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('statNew').textContent = data.data.new || 0;
                    document.getElementById('statProcessing').textContent = data.data.processing || 0;
                    document.getElementById('statConverted').textContent = data.data.converted || 0;
                    document.getElementById('statRate').textContent = (data.data.conversion_rate || 0) + '%';
                }
            } catch (e) {
                console.error('Stats error:', e);
            }
        }

        async function loadInquiries() {
            try {
                const token = API.getToken();
                const status = document.getElementById('filterStatus').value;
                const url = status ? `${API_URL}/inquiries?status=${status}` : `${API_URL}/inquiries`;

                const res = await fetch(url, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                const data = await res.json();

                if (data.success && data.data.inquiries) {
                    allInquiries = data.data.inquiries;
                    renderInquiries(allInquiries);
                } else {
                    showEmpty();
                }
            } catch (e) {
                console.error('Load error:', e);
                document.getElementById('inquiriesContainer').innerHTML = `
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Failed to load inquiries. Make sure the database tables exist.</div>
                `;
            }
        }

        function filterInquiries() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const filtered = allInquiries.filter(i =>
                i.customer_name.toLowerCase().includes(search) ||
                i.phone.includes(search)
            );
            renderInquiries(filtered);
        }

        function renderInquiries(inquiries) {
            if (!inquiries || inquiries.length === 0) {
                showEmpty();
                return;
            }

            const html = inquiries.map(i => {
                const urgentClass = i.status === 'new' && i.days_old > 2 ? 'urgent' : (i.status === 'processing' ? 'processing' : '');
                return `
                <div class="inquiry-card ${urgentClass}">
                    <div class="inquiry-header">
                        <div>
                            <div class="inquiry-customer">${Utils.escapeHtml(i.customer_name)}</div>
                            <div class="inquiry-ref">INQ-${String(i.inquiry_id).padStart(8, '0')} • ${i.days_old || 0} days ago</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="stock-badge ${i.stock_availability}">${i.stock_availability}</span>
                            <span class="inquiry-status ${i.status}">${i.status.toUpperCase()}</span>
                        </div>
                    </div>
                    <div class="inquiry-details">
                        <div class="detail-item"><label>Phone</label><span>${Utils.escapeHtml(i.phone)}</span></div>
                        <div class="detail-item"><label>Product</label><span>${Utils.escapeHtml(i.item_name)}</span></div>
                        <div class="detail-item"><label>Quantity</label><span>${i.quantity}</span></div>
                        <div class="detail-item"><label>Budget</label><span>${i.budget_range || 'Not specified'}</span></div>
                        <div class="detail-item"><label>Install Date</label><span>${i.installation_date || 'TBD'}</span></div>
                        <div class="detail-item"><label>Address</label><span>${Utils.escapeHtml(i.address)}</span></div>
                    </div>
                    ${i.notes ? `<div class="mb-3"><small class="text-muted"><strong>Notes:</strong> ${Utils.escapeHtml(i.notes)}</small></div>` : ''}
                    <div class="inquiry-actions">
                        <button class="btn-action btn btn-outline-primary btn-sm" onclick="viewInquiry(${i.inquiry_id})">
                            <i class="bi bi-eye me-1"></i>View
                        </button>
                        ${i.status === 'new' ? `
                            <button class="btn-action btn btn-warning btn-sm" onclick="updateStatus(${i.inquiry_id}, 'processing')">
                                <i class="bi bi-play me-1"></i>Start Processing
                            </button>
                        ` : ''}
                        ${i.status === 'processing' ? `
                            <button class="btn-action btn btn-info btn-sm" onclick="updateStatus(${i.inquiry_id}, 'quoted')">
                                <i class="bi bi-file-text me-1"></i>Mark Quoted
                            </button>
                        ` : ''}
                        ${(i.status === 'quoted' || i.status === 'processing') && i.stock_availability === 'available' ? `
                            <button class="btn-action btn btn-success btn-sm" onclick="convertToSO(${i.inquiry_id})">
                                <i class="bi bi-cart-check me-1"></i>Convert to Sales Order
                            </button>
                        ` : ''}
                        ${i.status !== 'converted' && i.status !== 'cancelled' ? `
                            <button class="btn-action btn btn-outline-danger btn-sm" onclick="updateStatus(${i.inquiry_id}, 'cancelled')">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </button>
                        ` : ''}
                    </div>
                </div>
            `
            }).join('');

            document.getElementById('inquiriesContainer').innerHTML = html;
        }

        function showEmpty() {
            document.getElementById('inquiriesContainer').innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h5>No Inquiries Found</h5>
                    <p>Customer inquiries will appear here when submitted.</p>
                    <a href="customer-inquiry.html" class="btn btn-primary mt-3" target="_blank">
                        <i class="bi bi-plus-circle me-2"></i>Submit Test Inquiry
                    </a>
                </div>
            `;
        }

        async function viewInquiry(id) {
            try {
                const token = API.getToken();
                const res = await fetch(`${API_URL}/inquiries/${id}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                const data = await res.json();
                if (data.success) {
                    const i = data.data;
                    document.getElementById('modalContent').innerHTML = `
                        <div class="row g-3">
                            <div class="col-md-6"><label class="text-muted small">Reference</label><p class="fw-bold">INQ-${String(i.inquiry_id).padStart(8, '0')}</p></div>
                            <div class="col-md-6"><label class="text-muted small">Status</label><p><span class="inquiry-status ${i.status}">${i.status.toUpperCase()}</span></p></div>
                            <div class="col-md-6"><label class="text-muted small">Customer</label><p class="fw-bold">${Utils.escapeHtml(i.customer_name)}</p></div>
                            <div class="col-md-6"><label class="text-muted small">Phone</label><p>${Utils.escapeHtml(i.phone)}</p></div>
                            <div class="col-md-6"><label class="text-muted small">Email</label><p>${i.email || 'N/A'}</p></div>
                            <div class="col-md-6"><label class="text-muted small">Address</label><p>${Utils.escapeHtml(i.address)}</p></div>
                            <div class="col-12"><hr></div>
                            <div class="col-md-6"><label class="text-muted small">Product</label><p class="fw-bold">${Utils.escapeHtml(i.item_name)}</p></div>
                            <div class="col-md-3"><label class="text-muted small">Quantity</label><p>${i.quantity}</p></div>
                            <div class="col-md-3"><label class="text-muted small">Stock</label><p><span class="stock-badge ${i.stock_availability}">${i.stock_availability}</span></p></div>
                            <div class="col-md-6"><label class="text-muted small">Budget</label><p>${i.budget_range || 'Not specified'}</p></div>
                            <div class="col-md-6"><label class="text-muted small">Install Date</label><p>${i.installation_date || 'TBD'}</p></div>
                            ${i.notes ? `<div class="col-12"><label class="text-muted small">Notes</label><p>${Utils.escapeHtml(i.notes)}</p></div>` : ''}
                            <div class="col-12"><hr></div>
                            <div class="col-md-6"><label class="text-muted small">Created</label><p>${Utils.formatDate(i.created_at)}</p></div>
                            <div class="col-md-6"><label class="text-muted small">Assigned To</label><p>${i.assigned_staff_name || 'Unassigned'}</p></div>
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('viewModal')).show();
                }
            } catch (e) {
                console.error('View error:', e);
            }
        }

        async function updateStatus(id, status) {
            if (!confirm(`Update inquiry to "${status}"?`)) return;
            try {
                const token = API.getToken();
                const res = await fetch(`${API_URL}/inquiries/${id}/status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify({
                        status
                    })
                });
                const data = await res.json();
                if (data.success) {
                    Utils.showToast('Status updated!', 'success');
                    await loadStatistics();
                    await loadInquiries();
                } else {
                    Utils.showToast(data.message || 'Update failed', 'error');
                }
            } catch (e) {
                console.error('Update error:', e);
                Utils.showToast('Update failed', 'error');
            }
        }

        async function convertToSO(id) {
            if (!confirm('Convert this inquiry to a Sales Order? This will create a new SO.')) return;
            try {
                const token = API.getToken();
                const res = await fetch(`${API_URL}/inquiries/${id}/convert`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
                const data = await res.json();
                if (data.success) {
                    Utils.showToast(`Converted! Sales Order #${data.data.sales_order_id} created`, 'success');
                    await loadStatistics();
                    await loadInquiries();
                } else {
                    Utils.showToast(data.message || 'Conversion failed', 'error');
                }
            } catch (e) {
                console.error('Convert error:', e);
                Utils.showToast('Conversion failed', 'error');
            }
        }

        function handleLogout() {
            if (confirm('Logout?')) API.logout();
        }
    </script>
</body>

</html>