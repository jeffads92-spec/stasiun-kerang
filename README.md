# 🦪 Stasiun Kerang - Restaurant Management System

Sistem manajemen restoran lengkap berbasis web untuk mengelola pesanan, menu, inventory, pembayaran, dan analitik real-time.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql)
![Railway](https://img.shields.io/badge/Railway-Deployed-0B0D0E?style=flat&logo=railway)

## ✨ Fitur Utama

### 👨‍💼 Manajemen
- **Dashboard** - Real-time overview dengan statistik penjualan
- **Analytics** - Laporan detail dan trend analysis
- **Reports** - Export laporan ke CSV/Excel
- **Discounts** - Sistem promo dan diskon

### 🍽️ Operasional
- **Menu Management** - CRUD menu items dengan kategori
- **Orders System** - Multi-type orders (Dine In, Takeaway, Delivery)
- **Kitchen Display** - Real-time order tracking untuk chef
- **Payment Processing** - Multi-payment methods (Cash, Card, QRIS, Transfer)
- **Table Management** - Status dan reservasi meja

### 📦 Inventory & Settings
- **Inventory Control** - Monitoring stok bahan baku
- **Settings** - Konfigurasi sistem (pajak, biaya layanan, notifikasi)
- **Import/Export** - Import data dari Excel/CSV

## 🛠️ Tech Stack

- **Backend**: PHP 7.4+ dengan PDO
- **Database**: MySQL 8.0+ / MariaDB 10.3+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Charts**: Chart.js
- **Icons**: Font Awesome 6.4.0
- **Deployment**: Railway (Docker support)
- **Package Manager**: Composer

## 📋 Requirements

- PHP >= 7.4
- MySQL >= 8.0 atau MariaDB >= 10.3
- Composer
- Web Server (Apache/Nginx) atau PHP Built-in Server
- Extensions: PDO, pdo_mysql, mbstring, json

## 💻 Installation

### 1. Clone Repository
```bash
git clone https://github.com/jeffads92-spec/stasiun-kerang.git
cd stasiun-kerang
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Setup Database

Edit file `config/database.php` dengan kredensial database Anda:

```php
$host = 'mysql.railway.internal';  // Ganti dengan host Anda
$port = 3306;
$database = 'railway';              // Ganti dengan nama database
$username = 'root';                 // Ganti dengan username
$password = 'your_password';        // Ganti dengan password
```

### 4. Import Database Schema
```bash
mysql -u username -p database_name < config/schema.sql
```

Atau gunakan phpMyAdmin/MySQL Workbench untuk import file `config/schema.sql`

### 5. Jalankan Aplikasi

#### Menggunakan PHP Built-in Server
```bash
php -S localhost:8000
```

#### Menggunakan Apache/Nginx
Arahkan document root ke folder project

### 6. Akses Aplikasi
```
http://localhost:8000
```

## 👥 Default User Credentials

```
Admin
Username: admin
Password: password

Cashier
Username: cashier1
Password: password

Kitchen
Username: kitchen1
Password: password
```

⚠️ **PENTING**: Segera ganti password default setelah instalasi pertama!

## 📁 Struktur Folder

```
stasiun-kerang/
├── api/                          # REST API Endpoints
│   ├── auth.php                  # Authentication (login, logout, register)
│   ├── dashboard.php             # Dashboard statistics
│   ├── kitchen.php               # Kitchen display & order status
│   ├── menu.php                  # Menu & category management
│   ├── orders.php                # Order processing
│   ├── payments.php              # Payment processing
│   ├── reports.php               # Reports & analytics
│   ├── settings.php              # System settings
│   ├── tables.php                # Table management
│   └── index.php                 # API documentation
│
├── config/                       # Configuration files
│   ├── database.php              # Database connection
│   └── schema.sql                # Database schema
│
├── *.html                        # Frontend pages
│   ├── index.html                # Login page
│   ├── dashboard.html            # Main dashboard
│   ├── orders.html               # Orders management
│   ├── menu-management.html      # Menu management
│   ├── kitchen.html              # Kitchen display
│   ├── payment.html              # Payment processing
│   ├── reports.html              # Reports & analytics
│   ├── tables.html               # Table management
│   ├── inventory.html            # Inventory management
│   ├── discounts.html            # Discount management
│   ├── analytics.html            # Advanced analytics
│   └── settings.html             # System settings
│
├── import.php                    # Data import utility
├── composer.json                 # PHP dependencies
├── Dockerfile                    # Docker configuration
├── railway.toml                  # Railway deployment config
├── .gitignore                    # Git ignore rules
└── README.md                     # This file
```

## 🔌 API Endpoints

### Authentication
- `POST /api/auth.php?action=login` - User login
- `POST /api/auth.php?action=logout` - User logout
- `POST /api/auth.php?action=register` - Register new user
- `GET /api/auth.php?action=check` - Check session status

### Orders
- `GET /api/orders.php` - Get all orders
- `GET /api/orders.php?id={id}` - Get specific order
- `POST /api/orders.php` - Create new order
- `PUT /api/orders.php?id={id}` - Update order
- `DELETE /api/orders.php?id={id}` - Cancel order

### Menu
- `GET /api/menu.php` - Get all menu items
- `GET /api/menu.php?id={id}` - Get specific menu item
- `POST /api/menu.php` - Create menu item
- `PUT /api/menu.php?id={id}` - Update menu item
- `DELETE /api/menu.php?id={id}` - Delete menu item
- `GET /api/menu.php?resource=categories` - Get categories

### Kitchen
- `GET /api/kitchen.php` - Get active orders
- `GET /api/kitchen.php?action=queue` - Get kitchen queue
- `GET /api/kitchen.php?action=stats` - Get kitchen statistics
- `POST /api/kitchen.php?action=start` - Start cooking order
- `POST /api/kitchen.php?action=complete` - Mark order as ready

### Payments
- `POST /api/payments.php?action=process` - Process payment
- `GET /api/payments.php?action=history` - Get payment history
- `GET /api/payments.php?action=methods` - Get payment methods

### Reports
- `GET /api/reports.php?action=summary` - Sales summary
- `GET /api/reports.php?action=sales_trend` - Sales trend
- `GET /api/reports.php?action=menu_performance` - Menu performance
- `GET /api/reports.php?action=transactions` - Transaction list
- `GET /api/reports.php?action=export&format=csv` - Export to CSV

### Dashboard
- `GET /api/dashboard.php?action=stats` - Dashboard statistics
- `GET /api/dashboard.php?action=sales` - Sales trend
- `GET /api/dashboard.php?action=top_menu` - Top selling menu

### Tables
- `GET /api/tables.php` - Get all tables
- `POST /api/tables.php` - Create table
- `PUT /api/tables.php?id={id}` - Update table
- `DELETE /api/tables.php?id={id}` - Delete table

### Settings
- `GET /api/settings.php` - Get all settings
- `GET /api/settings.php?key={key}` - Get specific setting
- `POST /api/settings.php` - Update setting

**Dokumentasi lengkap**: Lihat [API_DOCUMENTATION.md](docs/API_DOCUMENTATION.md)

## 📊 Database Schema

### Core Tables
- **users** - User accounts dan roles
- **categories** - Menu categories
- **menu_items** - Menu items dengan harga dan info
- **tables** - Restaurant tables
- **orders** - Customer orders
- **order_items** - Order detail items
- **payments** - Payment transactions
- **inventory** - Inventory management
- **settings** - System settings

## 🚀 Deployment

### Railway Deployment

1. **Connect Repository**
```bash
# Repository sudah terkoneksi dengan Railway
```

2. **Environment Variables**
Database credentials sudah dikonfigurasi di `config/database.php`

3. **Deploy**
```bash
git push origin main
# Railway akan otomatis deploy
```

### Docker Deployment

```bash
# Build image
docker build -t stasiun-kerang .

# Run container
docker run -p 8000:80 stasiun-kerang
```

## 🧪 Testing

```bash
# Coming soon
composer test
```

## 📝 API Response Format

### Success Response
```json
{
  "success": true,
  "message": "Success message",
  "data": {
    // Response data
  },
  "timestamp": "2026-01-06 10:30:00"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "timestamp": "2026-01-06 10:30:00"
}
```

## 🔒 Security

- Password hashing dengan `password_hash()` (bcrypt)
- Prepared statements untuk prevent SQL injection
- CORS headers untuk API security
- Session management untuk authentication
- Input validation dan sanitization

## 🤝 Contributing

1. Fork repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

## 📄 License

This project is licensed under the MIT License.

## 👨‍💻 Developer

**Repository**: [https://github.com/jeffads92-spec/stasiun-kerang](https://github.com/jeffads92-spec/stasiun-kerang)

## 🙏 Acknowledgments

- [Font Awesome](https://fontawesome.com/) - Icons
- [Chart.js](https://www.chartjs.org/) - Charts and graphs
- [Railway](https://railway.app/) - Hosting platform

## 📞 Support

Jika ada pertanyaan atau masalah, silakan buat issue di repository atau hubungi developer.

---

**Made with ❤️ for Stasiun Kerang Restaurant**
