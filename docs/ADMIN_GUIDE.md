# Admin Guide

## User Management

Promote public users to officer roles via Users List page.

## Forest Management

Add forests with coordinates (required for GIS), unique forest codes, and region data.

## Security

- CSRF protection on all POST forms
- Rate limiting on login (5 attempts per 5 minutes)
- PDO prepared statements throughout
- Activity logging with IP and device info
- Delete `setup/` folder after deployment

## Database Maintenance

- Run health/fire risk recalculation from Fire Risk page
- Review `activity_logs` and `audit_log` regularly
- Backup `forest_management` database before upgrades

## Roles

| Role | Permissions |
|------|-------------|
| admin | Full access |
| forest_officer | Incidents, forests view, workflow |
| wildlife_officer | Animals, QR tracking |
| data_analyst | Analytics, reports, exports |
| public | View, report incidents |

## Report Scheduler

Configure daily/weekly/monthly reports in `report_schedules` table (admin SQL access).
