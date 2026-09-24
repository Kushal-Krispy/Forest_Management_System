# Deployment Guide

## Production Checklist

1. Set MySQL password in `config/database.php`
2. Run `setup/migrate_v2.php` once
3. **Delete** the entire `setup/` directory
4. Enable HTTPS and set `session.cookie_secure = 1` in php.ini
5. Restrict `assets/uploads/` execution via `.htaccess`
6. Set proper file permissions (755 dirs, 644 files)

## Apache Virtual Host (Optional)

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/dbms-demo"
    ServerName forest.local
    <Directory "C:/xampp/htdocs/dbms-demo">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Database Backup

```bash
mysqldump -u root forest_management > backup.sql
```

## Performance

- Indexes created automatically by migration
- Dashboard caches health/fire scores on load
- Use DataTables pagination for large lists

## Monitoring

- Review `activity_logs` for security events
- Check `login_attempts` for brute force patterns
- Monitor `notifications` table growth
