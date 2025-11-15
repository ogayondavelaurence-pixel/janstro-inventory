<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Janstro Inventory System</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #FF6B00 0%, #1A2332 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            padding: 3rem;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo i {
            font-size: 4rem;
            color: #FF6B00;
            margin-bottom: 1rem;
        }

        .logo h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            color: #1A2332;
            margin-bottom: 0.5rem;
        }

        .logo p {
            color: #95A5A6;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #1A2332;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #ECF0F1;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #FF6B00;
            box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 0.875rem;
            background: #FF6B00;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: #FF8533;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: #E74C3C;
            border-left: 4px solid #E74C3C;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: #27AE60;
            border-left: 4px solid #27AE60;
        }

        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #95A5A6;
            font-size: 0.85rem;
        }

        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">
            <i class="bi bi-lightning-charge-fill"></i>
            <h1>Janstro IMS</h1>
            <p>Prime Renewable Energy Solutions</p>
        </div>

        <div id="alertBox" class="alert"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                Login
            </button>
        </form>

        <div class="footer">
            <p>Developed by Group 89 - City College of Calamba</p>
            <p>ISO/IEC 25010:2023 Compliant</p>
        </div>
    </div>

    <script>
        const API_BASE = '/janstro-inventory/public';

        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const loginBtn = document.getElementById('loginBtn');
            const alertBox = document.getElementById('alertBox');

            // Disable button
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="spinner"></span> Logging in...';

            try {
                const response = await fetch(`${API_BASE}/auth/login`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username,
                        password
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Store token
                    localStorage.setItem('auth_token', data.data.token);
                    localStorage.setItem('user', JSON.stringify(data.data.user));

                    // Show success
                    alertBox.className = 'alert alert-success';
                    alertBox.textContent = 'Login successful! Redirecting...';
                    alertBox.style.display = 'block';

                    // Redirect
                    setTimeout(() => {
                        window.location.href = '/janstro-inventory/public/views/dashboard/index.php';
                    }, 1000);
                } else {
                    // Show error
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = data.message || 'Invalid username or password';
                    alertBox.style.display = 'block';

                    loginBtn.disabled = false;
                    loginBtn.textContent = 'Login';
                }
            } catch (error) {
                console.error('Login error:', error);
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Network error. Please try again.';
                alertBox.style.display = 'block';

                loginBtn.disabled = false;
                loginBtn.textContent = 'Login';
            }
        });

        // Hide alert on input
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('input', () => {
                document.getElementById('alertBox').style.display = 'none';
            });
        });
    </script>
</body>

</html>