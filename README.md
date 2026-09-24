# Smart Forest Monitoring & Wildlife Management Platform v2.0

Enterprise-grade forest administration system built with **PHP, MySQL, Bootstrap 5, Chart.js, Leaflet.js, DataTables, and SweetAlert2**.

## Quick Start (XAMPP)

1. Start **Apache** and **MySQL** in XAMPP Control Panel.
2. Open: `http://localhost/dbms-demo/setup/migrate_v2.php`
3. After success, open: `http://localhost/dbms-demo/`
4. Login with demo accounts (password: **password123**):

| Username | Role | Access |
|----------|------|--------|
| admin | Admin | Full system + user management |
| officer1 | Forest Officer | Animals, incidents, workflow |
| wildlife1 | Wildlife Officer | Animals, QR tracking |
| analyst1 | Data Analyst | Analytics, reports, exports |
| public1 | Public | View data + report incidents |

5. Delete `setup/` folder after install.

## v2.0 Features

- **Interactive Forest GIS Map** (Leaflet + OpenStreetMap)
- **Incident Heatmap** with severity color coding
- **Wildlife Population Analytics** with forecasts
- **Fire Risk Prediction Engine**
- **Forest Health Score Engine**
- **Real-time Notification Center** (AJAX polling)
- **Environmental Monitoring** (weather, air, water quality)
- **QR Code Wildlife Tracking**
- **Officer Workflow Management** (5-stage status)
- **Report Generator** with AI executive summaries
- **Export System** (PDF, Excel, CSV)
- **Enhanced Security** (CSRF, rate limiting, PDO, audit logs)

## Project Structure

```
dbms-demo/
├── api/                    # AJAX endpoints
├── config/                 # database.php, app.php
├── database/
│   ├── schema.sql          # Full v2 schema
│   └── migration_v2.sql    # Upgrade script
├── docs/                   # Guides, ER diagram, testing report
├── includes/
│   ├── services/           # FireRisk, HealthScore, Reports, etc.
│   └── auth.php, security.php
├── pages/                  # 20+ views
├── actions/                # Form handlers
└── assets/                 # CSS, JS, uploads
```

## Documentation

- [Installation Guide](docs/INSTALLATION.md)
- [User Guide](docs/USER_GUIDE.md)
- [Admin Guide](docs/ADMIN_GUIDE.md)
- [Deployment Guide](docs/DEPLOYMENT.md)
- [ER Diagram](docs/ER_DIAGRAM.md)
- [Testing Report](docs/TESTING_REPORT.md)

## Configuration

Edit `config/database.php` if MySQL has a password. Base URL auto-detects from folder name.

## License

Educational/production project — free to modify and extend.
