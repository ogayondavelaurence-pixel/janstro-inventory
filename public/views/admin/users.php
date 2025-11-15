<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Janstro IMS</title>
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

        .role-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .role-superadmin {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .role-admin {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .role-manager {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }

        .role-staff {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .status-active {
            color: #28a745;
        }

        .status-inactive {
            color: #dc3545;
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
        <a href="/views/reports/analytics.php">
            <i class="fas fa-chart-bar"></i> Analytics
        </a>
        <a href="/views/admin/users.php" class="active">
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
                    <i class="fas fa-users"></i> User Management
                </span>
                <div class="d-flex">
                    <button class="btn btn-janstro" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div>
            </div>
        </nav>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h6><i class="fas fa-crown"></i> Superadmins</h6>
                    <h2 id="superadminCount">0</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h6><i class="fas fa-user-shield"></i> Admins</h6>
                    <h2 id="adminCount">0</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <h6><i class="fas fa-user-tie"></i> Managers</h6>
                    <h2 id="managerCount">0</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <h6><i class="fas fa-user"></i> Staff</h6>
                    <h2 id="staffCount">0</h2>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <h5 class="mb-4"><i class="fas fa-list"></i> All Users</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RBAC Permission Matrix -->
        <div class="table-container mt-4">
            <h5 class="mb-4"><i class="fas fa-shield-alt"></i> RBAC Permission Matrix</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Module</th>
                            <th>Superadmin</th>
                            <th>Admin</th>
                            <th>Manager</th>
                            <th>Staff</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>User Management</strong></td>
                            <td><i class="fas fa-check text-success"></i> Full Access</td>
                            <td><i class="fas fa-times text-danger"></i> No Access</td>
                            <td><i class="fas fa-times text-danger"></i> No Access</td>
                            <td><i class="fas fa-times text-danger"></i> No Access</td>
                        </tr>
                        <tr>
                            <td><strong>Inventory Management</strong></td>
                            <td><i class="fas fa-check text-success"></i> Full Access</td>
                            <td><i class="fas fa-check text-success"></i> Full Access</td>
                            <td><i class="fas fa-eye text-info"></i> View Only</td>
                            <td><i class="fas fa-edit text-warning"></i> Create/Edit</td>
                        </tr>
                        <tr>
                            <td><strong>Purchase Orders</strong></td>
                            <td><i class="fas fa-check text-success"></i> Full Access</td>
                            <td><i class="fas fa-check text-success"></i> Approve/Manage</td>
                            <td><i class="fas fa-check text-success"></i> Create/View</td>
                            <td><i class="fas fa-eye text-info"></i> View Only</td>
                        </tr>
                        <tr>
                            <td><strong>Sales Orders</strong></td>
                            <td><i class="fas fa-check text-success"></i> Full Access</td>
                            <td><i class="fas fa-check text-success"></i> Full Access</td>
                            <td><i class="fas fa-check text-success"></i> Approve</td>
                            <td><i class="fas fa-edit text-warning"></i> Create</td>
                        </tr>
                        <tr>
                            <td><strong>Reports & Analytics</strong></td>
                            <td><i class="fas fa-check text-success"></i> Full Access</td>
                            <td><i class="fas fa-check text-success"></i> Full Access</td>
                            <td><i class="fas fa-check text-success"></i> View All</td>
                            <td><i class="fas fa-eye text-info"></i> Basic Reports</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role_id" required id="roleSelect">
                                <option value="">Select Role</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_no">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-janstro" onclick="addUser()">
                        <i class="fas fa-save"></i> Add User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE = '';

        document.addEventListener('DOMContentLoaded', async () => {
            await loadUsers();
            await loadRoles();
        });

        async function loadUsers() {
            try {
                const response = await fetch(`${API_BASE}/users`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                if (!response.ok) throw new Error('Failed to load users');

                const data = await response.json();
                renderUsersTable(data.data || []);
                updateStats(data.data || []);
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load users');
            }
        }

        async function loadRoles() {
            try {
                const response = await fetch(`${API_BASE}/users/roles`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const data = await response.json();
                const select = document.getElementById('roleSelect');

                (data.data || []).forEach(role => {
                    select.innerHTML += `<option value="${role.role_id}">${role.role_name}</option>`;
                });
            } catch (error) {
                console.error('Error loading roles:', error);
            }
        }

        function renderUsersTable(users) {
            const tbody = document.getElementById('usersTableBody');

            if (users.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <p>No users found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>${user.user_id}</td>
                    <td><strong>${user.username}</strong></td>
                    <td>${user.name || 'N/A'}</td>
                    <td><span class="role-badge role-${user.role_name}">${user.role_name}</span></td>
                    <td>${user.contact_no || 'N/A'}</td>
                    <td>
                        <i class="fas fa-circle status-${user.status}"></i>
                        ${user.status}
                    </td>
                    <td>${new Date(user.created_at).toLocaleDateString()}</td>
                    <td>
                        ${user.status === 'active' ? `
                            <button class="btn btn-sm btn-warning" onclick="deactivateUser(${user.user_id})">
                                <i class="fas fa-ban"></i>
                            </button>
                        ` : `
                            <button class="btn btn-sm btn-success" onclick="activateUser(${user.user_id})">
                                <i class="fas fa-check"></i>
                            </button>
                        `}
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.user_id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        async function addUser() {
            const form = document.getElementById('addUserForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            try {
                const response = await fetch(`${API_BASE}/users`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    alert('User added successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
                    form.reset();
                    await loadUsers();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to add user');
            }
        }

        async function deactivateUser(userId) {
            if (!confirm('Deactivate this user?')) return;

            try {
                const response = await fetch(`${API_BASE}/users/${userId}/deactivate`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('User deactivated successfully!');
                    await loadUsers();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to deactivate user');
            }
        }

        async function activateUser(userId) {
            try {
                const response = await fetch(`${API_BASE}/users/${userId}/activate`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('User activated successfully!');
                    await loadUsers();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to activate user');
            }
        }

        async function deleteUser(userId) {
            if (!confirm('Are you sure? This action cannot be undone.')) return;

            try {
                const response = await fetch(`${API_BASE}/users/${userId}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('User deleted successfully!');
                    await loadUsers();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to delete user');
            }
        }

        function updateStats(users) {
            let superadmin = 0,
                admin = 0,
                manager = 0,
                staff = 0;

            users.forEach(user => {
                switch (user.role_name) {
                    case 'superadmin':
                        superadmin++;
                        break;
                    case 'admin':
                        admin++;
                        break;
                    case 'manager':
                        manager++;
                        break;
                    case 'staff':
                        staff++;
                        break;
                }
            });

            document.getElementById('superadminCount').textContent = superadmin;
            document.getElementById('adminCount').textContent = admin;
            document.getElementById('managerCount').textContent = manager;
            document.getElementById('staffCount').textContent = staff;
        }

        function logout() {
            window.location.href = '/janstro-inventory/public/auth/logout.php';
        }
    </script>
</body>

</html>