# Janstro Inventory Management System - User Manual

**Real-Time Web-Based Inventory System for Solar Equipment**

---

## 📋 What This System Does

Transforms **manual weekly inventory tracking** into **real-time automated management**:

- ✅ Track stock levels instantly (no more weekly counts)
- ✅ Create sales orders and purchase orders online
- ✅ Get automatic low-stock alerts
- ✅ Generate invoices and PO documents (PDF)
- ✅ View real-time analytics dashboards
- ✅ Access from any device (PC, tablet, phone)

**Before**: 8 hours/week manual Excel work, 1-week data lag  
**After**: 15 minutes/week, instant updates

---

## 🚀 Quick Start Installation

### Step 1: Download the System

```bash
git clone https://github.com/ogayondavelaurence-pixel/janstro-inventory.git
cd janstro-inventory
```

Or download ZIP from GitHub and extract.

### Step 2: Install Requirements

**You need:**
- PHP 8.1+ ([Download XAMPP](https://www.apachefriends.org/) for Windows)
- MySQL 8.0+ (included in XAMPP)
- Composer ([Download](https://getcomposer.org/))

**Install PHP dependencies:**
```bash
composer install
```

### Step 3: Setup Database

1. **Start XAMPP** (Apache + MySQL)

2. **Open phpMyAdmin**: `http://localhost/phpmyadmin`

3. **Import database**:
   - Click "New" → Create database: `janstro_inventory`
   - Click "Import" → Choose `janstro_inventory.sql`
   - Click "Go"

4. **Create database user** (SQL tab):
```sql
CREATE USER 'janstro_user'@'localhost' IDENTIFIED BY 'janstro2025';
GRANT ALL PRIVILEGES ON janstro_inventory.* TO 'janstro_user'@'localhost';
FLUSH PRIVILEGES;
```

### Step 4: Configure System

1. **Copy environment file**:
```bash
copy .env.example .env    # Windows
cp .env.example .env      # Linux/Mac
```

2. **Edit `.env` file** (use Notepad):
```ini
DB_HOST=localhost
DB_NAME=janstro_inventory
DB_USER=janstro_user
DB_PASS=janstro2025

APP_URL=http://localhost/janstro-inventory
JWT_SECRET=your_random_64_character_secret_key_here_change_this
```

**Generate JWT secret**: Use random 64-character string (letters + numbers)

### Step 5: Setup Web Server

**For XAMPP:**

1. Move `janstro-inventory` folder to `C:\xampp\htdocs\`
2. Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/janstro-inventory/public"
    ServerName janstro.local
    
    <Directory "C:/xampp/htdocs/janstro-inventory/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Add to `C:\Windows\System32\drivers\etc\hosts`:
```
127.0.0.1 janstro.local
```

4. **Restart Apache** in XAMPP Control Panel

### Step 6: Set Folder Permissions

**Windows (Right-click folder → Properties → Security → Edit)**:
- Give "Full Control" to:
  - `storage/` folder
  - `logs/` folder

**Linux/Mac**:
```bash
chmod -R 777 storage/
chmod -R 777 logs/
```

### Step 7: Access System

Open browser: `http://janstro.local` or `http://localhost/janstro-inventory/frontend/`

**Default Login**:
- Username: `BernsVa`
- Password: `password`

⚠️ **Change password immediately after first login!**

---

## 📱 How to Use the System

### Daily Workflow

#### 1️⃣ **Login**
- Go to system URL
- Enter username/password
- Dashboard loads with today's summary

#### 2️⃣ **Check Stock Levels** (Dashboard)
- View real-time inventory quantities
- Red alerts show low-stock items
- Charts show trends

#### 3️⃣ **Receive New Stock** (Goods Receipt)
1. Click **Inventory → Goods Receipt**
2. Scan or type SKU (e.g., `BAT-DC-12V200`)
3. Enter quantity received
4. Add notes (optional)
5. Click **Receive Stock**
6. ✅ Stock updates instantly

#### 4️⃣ **Create Sales Order**
1. Click **Sales → Sales Orders → New Order**
2. Fill customer details:
   - Name, phone, address
   - Installation date
3. Add items:
   - Search by name or SKU
   - Enter quantity
   - System shows available stock
4. Click **Create Order**
5. If stock shortage → System suggests Purchase Requisition

#### 5️⃣ **Request Purchase** (Staff)
1. Click **Purchasing → Purchase Requisitions → New PR**
2. Select item needing restock
3. Enter required quantity
4. Set urgency (Low/Medium/High/Critical)
5. Add reason
6. Submit for admin approval

#### 6️⃣ **Approve Purchase** (Admin)
1. Click **Purchasing → Purchase Requisitions**
2. Review pending requests
3. Click **Approve** or **Reject**
4. Approved PR auto-converts to Purchase Order

#### 7️⃣ **Send Purchase Order**
1. Click **Purchasing → Purchase Orders**
2. Select PO → Click **Download PDF**
3. Email PDF to supplier

#### 8️⃣ **Complete Installation & Invoice**
1. After installation, go to **Sales Orders**
2. Click order → **Mark as Completed**
3. System auto-generates invoice
4. Click **View Invoice** → **Download PDF**
5. Email invoice to customer

#### 9️⃣ **Track Payments**
1. Go to **Invoices**
2. Click invoice → **Record Payment**
3. Enter amount, date, payment method
4. Status updates: Unpaid → Partial → Paid

#### 🔟 **View Reports**
1. Click **Reports**
2. Choose report type:
   - Inventory valuation
   - Sales summary
   - Stock movements
   - Low stock alerts
3. Select date range
4. View charts and export data

---

## 🎯 Key Features Explained

### Real-Time Stock Updates
- Every transaction (IN/OUT) updates stock instantly
- No manual counting needed
- All users see same data simultaneously

### Automatic Alerts
- Low stock notification when quantity ≤ reorder level
- Email alerts sent to admin
- Dashboard shows red warnings

### Bill of Materials (BOM)
For packaged products:
1. Create product families (e.g., "5kW Solar Kit")
2. Add components (panels, inverter, batteries, etc.)
3. System checks component availability
4. Auto-creates shortage list

### Invoice Generation
- PDF invoices with company logo
- Tax calculation (configurable VAT %)
- Payment tracking
- Email delivery

### Stock Requirements Analysis
- Links Sales Orders to current stock
- Shows shortages immediately
- Suggests Purchase Requisitions

### Audit Trail
- Every action logged (who, what, when)
- View in **Settings → Audit Logs**
- IP address and device tracked

---

## 🔧 System Settings

### Email Notifications (Admin)

1. Go to **Settings → Email Settings**
2. Enable email notifications
3. Configure SMTP (Gmail example):
   - SMTP Host: `smtp.gmail.com`
   - Port: `587`
   - Username: your-email@gmail.com
   - Password: [App Password](https://myaccount.google.com/apppasswords)
4. Test email delivery
5. Choose notification types:
   - Low stock alerts
   - New orders
   - Purchase orders approved
   - Deliveries completed

### User Management (SuperAdmin)

1. Go to **Settings → Users**
2. Click **Add User**
3. Assign role:
   - **Staff**: Create orders, receive goods
   - **Admin**: + Approve purchases, manage suppliers
   - **SuperAdmin**: + User management, system settings

### Categories & Products

**Add Categories**:
1. **Inventory → Categories → Add Category**
2. Example: Solar Panels, Batteries, Inverters

**Add Items**:
1. **Inventory → Materials → Add Item**
2. Required fields:
   - Item name
   - SKU (unique code)
   - Category
   - Unit (pcs, units, sets)
   - Unit price
   - Reorder level (low stock threshold)

---

## 📊 Understanding Dashboards

### Main Dashboard
- **Total Stock Value**: Sum of (quantity × unit price)
- **Low Stock Items**: Items at/below reorder level
- **Pending Orders**: Sales orders not completed
- **Recent Activity**: Last 10 transactions

### Charts
- **Stock by Category**: Pie chart showing distribution
- **Sales Trend**: Line chart showing monthly revenue
- **Top Items**: Best-selling products
- **Stock Movement**: IN vs OUT transactions

All charts update in real-time (no page refresh needed).

---

## 🔒 Security Features

- **Password Requirements**: Minimum 8 characters
- **Account Lockout**: 5 failed login attempts = 15-minute lockout
- **Session Timeout**: 24 hours (auto-logout)
- **Role-Based Access**: Users only see allowed features
- **Audit Logging**: All actions tracked with timestamp

**Change Password**:
1. Click profile icon (top-right)
2. **Profile Settings → Change Password**
3. Enter current + new password

---

## 📱 Mobile Usage

System is **fully responsive**:

- **Phone**: Hamburger menu (☰) for navigation
- **Tablet**: Touch-optimized buttons
- **Desktop**: Full sidebar menu

**Mobile Tips**:
- Use landscape mode for charts
- Swipe left/right on tables
- Tap-hold for quick actions

---

## 🆘 Troubleshooting

### Cannot Login

**Problem**: "Invalid credentials"
- Check username spelling (case-sensitive)
- Verify Caps Lock is OFF
- Try default password: `password`

**Problem**: "Account locked"
- Wait 15 minutes
- Or contact admin to reset

### Stock Not Updating

1. Check user permissions (Staff role required)
2. Verify item exists (search by SKU)
3. Clear browser cache (Ctrl+F5)

### Charts Not Loading

1. Enable JavaScript in browser
2. Allow pop-ups for the site
3. Update browser to latest version
4. Check internet connection

### Email Not Sending

1. Go to **Email Settings**
2. Click **Test Email** button
3. If fails, verify SMTP credentials
4. For Gmail: Use [App Password](https://myaccount.google.com/apppasswords), not regular password

### PDF Not Generating

1. Check `storage/pdf/` folder exists
2. Verify folder permissions (777)
3. Check PHP memory limit (increase to 256M if needed)

### Database Connection Error

1. Start MySQL in XAMPP
2. Verify credentials in `.env` file
3. Check database exists: `janstro_inventory`

---

## 🔄 Backup & Maintenance

### Daily Backup (Recommended)

**Database**:
1. Open phpMyAdmin
2. Select `janstro_inventory`
3. Click **Export** → **Go**
4. Save file with date: `backup_2026-01-03.sql`

**Files**:
- Copy `storage/` folder weekly

### Scheduled Backups (Advanced)

**Windows Task Scheduler**:
1. Create batch file `backup.bat`:
```batch
@echo off
set DATE=%date:~-4,4%%date:~-10,2%%date:~-7,2%
"C:\xampp\mysql\bin\mysqldump" -u janstro_user -pjanstro2025 janstro_inventory > "C:\backups\db_%DATE%.sql"
```

2. Schedule daily at 2 AM

**Linux CRON**:
```bash
0 2 * * * mysqldump -u janstro_user -pjanstro2025 janstro_inventory > /backups/db_$(date +\%Y\%m\%d).sql
```

---

## 🔌 API Usage (Advanced)

For integrations with other systems (POS, accounting software):

**Base URL**: `http://janstro.local/api/`

**Authentication**:
1. Login via API to get JWT token:
```bash
curl -X POST http://janstro.local/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"BernsVa","password":"password"}'
```

2. Use token in subsequent requests:
```bash
curl -X GET http://janstro.local/api/items \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Import Postman Collection**:
- File included: `Janstro_API.postman_collection.json`
- Import into [Postman](https://www.postman.com/)
- Test all 40+ endpoints

---

## 📚 Training Materials

### Video Tutorials (Create These)
- [ ] Installation walkthrough (10 min)
- [ ] Daily operations demo (15 min)
- [ ] Admin functions (20 min)
- [ ] Reporting tutorial (10 min)

### Quick Reference Cards
Print and distribute:
- SKU lookup sheet
- Common procedures checklist
- Admin approval workflow
- Emergency contacts

---

## 🆘 Support

**Technical Issues**:
- Email: janstrosolar@gmail.com
- Phone: +63 917 715 1100

**Bug Reports**:
- GitHub Issues: [Report Here](https://github.com/ogayondavelaurence-pixel/janstro-inventory/issues)

**Feature Requests**:
- Email with subject: "Feature Request: [Description]"

---

## 📈 System Statistics

- **Lines of Code**: 50,628
- **Database Tables**: 30+
- **API Endpoints**: 40+
- **Supported Users**: Unlimited
- **Response Time**: <100ms
- **Uptime**: 24/7

---

## 🎓 Best Practices

### Daily
- ✅ Review low-stock alerts each morning
- ✅ Process pending purchase requisitions
- ✅ Mark completed installations
- ✅ Generate invoices same day

### Weekly
- ✅ Backup database
- ✅ Review audit logs
- ✅ Check overdue invoices
- ✅ Update reorder levels if needed

### Monthly
- ✅ Run inventory valuation report
- ✅ Analyze sales trends
- ✅ Review supplier performance
- ✅ Archive old notifications

---

## 🚀 Next Level Features (Coming Soon)

- [ ] Mobile app (iOS/Android)
- [ ] Barcode scanner integration
- [ ] WhatsApp notifications
- [ ] AI demand forecasting
- [ ] Multi-warehouse support
- [ ] Supplier portal
- [ ] E-commerce integration

---

## 📄 License

MIT License - Free to use and modify.

---

**Last Updated**: January 3, 2026  
**Version**: 2.0.0  
**Developed by**: Group 89
**For**: Janstro Prime Corporation