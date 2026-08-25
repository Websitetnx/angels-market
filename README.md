# Angel's Beauty Co. - Seller Market

A full-featured PHP e-commerce web application for Angel's Beauty Co., built with PHP, MySQL (PDO), Bootstrap 5, and jQuery.

## Features
- 🛍️ Product catalog with categories, search, and filters
- 🛒 Shopping cart with AJAX add/remove/update
- 📦 Order management (COD & GCash)
- 🔐 Client registration and login
- 👩‍💼 Admin panel (products, categories, orders, customers)
- 💅 Beauty product categories: Skincare, Makeup, Hair Care, Fragrances, Nail Care, Lip Care, Body Care, Beauty Tools

## Tech Stack
- **Backend:** PHP 8+ with PDO
- **Database:** MySQL
- **Frontend:** Bootstrap 5, jQuery, Bootstrap Icons
- **Server:** Apache (XAMPP)

## Setup Instructions

1. Clone or extract the repository anywhere inside your XAMPP `htdocs` folder. The application detects its folder name automatically.
   ```
   git clone https://github.com/Websitetnx/angels-market
   ```
2. For a fresh installation, import `database/clothing_ordering.sql` through phpMyAdmin.
3. For an existing installation, import the migration files in numeric order:
   - `database/001_beauty_product_images.sql`
   - `database/002_system_bugfixes.sql`
4. Edit `includes/db.php` if your MySQL credentials differ from the XAMPP defaults. You may also set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables.
5. Make sure PHP extensions `pdo_mysql` and `fileinfo` are enabled.
6. Start Apache and MySQL, then open `client/home.php` inside the folder you chose, for example `http://localhost/angels-market/client/home.php`.

## Admin Access
- URL: `http://localhost/Seller Market/admin/login.php`
- Email: `angelombao@gmail.com`
- Password: `admin12345`
