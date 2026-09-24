# Installation Guide - Smart Forest Monitoring Platform v2.0

## Requirements

- XAMPP (Apache + MySQL + PHP 8.0+)
- Modern web browser

## Quick Install

1. Place project in `C:\xampp\htdocs\dbms-demo\`
2. Start **Apache** and **MySQL** in XAMPP Control Panel
3. Open: `http://localhost/dbms-demo/setup/migrate_v2.php`
4. After success, open: `http://localhost/dbms-demo/`
5. Login with demo accounts (password: **password123**)
6. Delete the `setup/` folder after installation

## Fresh Install (Empty Database)

1. Open: `http://localhost/dbms-demo/setup/install.php`
2. Or import `database/schema.sql` via phpMyAdmin **Import** tab
3. Run `setup/migrate_v2.php` for v2 tables if needed

## Demo Accounts

| Username | Role | Access |
|----------|------|--------|
| admin | Admin | Full system |
| officer1 | Forest Officer | Incidents, animals, workflow |
| wildlife1 | Wildlife Officer | Animals, QR tracking |
| analyst1 | Data Analyst | Analytics, reports, exports |
| public1 | Public | View data, report incidents |

## Configuration

Edit `config/database.php` if MySQL has a password:

```php
define('DB_PASS', 'your_password');
```

Base URL is auto-detected from folder name. No manual path configuration needed.

## Troubleshooting

- **Connection failed:** Start MySQL in XAMPP
- **Procedures missing:** Run `setup/repair_procedures.php`
- **Missing v2 tables:** Run `setup/migrate_v2.php`
- **Maps empty:** Ensure forests have latitude/longitude set
