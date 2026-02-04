# Deployment Guide - Quality System

## Overview
This guide provides step-by-step instructions for deploying the Quality System to production, including pre-deployment checks, deployment procedures, rollback steps, and post-deployment monitoring.

---

## Pre-Deployment Checklist

### Code Quality
- [ ] All Priority 0-3 tasks completed
- [ ] All unit tests passing (`composer test`)
- [ ] No critical security vulnerabilities
- [ ] Code reviewed and approved
- [ ] Documentation updated

### Database
- [ ] All migrations reviewed and tested on staging
- [ ] Migration rollback scripts prepared
- [ ] Database backup completed
- [ ] Indexes optimized

### Configuration
- [ ] Environment variables configured (`.env` file)
- [ ] Database credentials verified
- [ ] Session configuration reviewed
- [ ] CSRF protection enabled
- [ ] Error reporting set to production mode

### Security
- [ ] SSL/TLS certificates valid and installed
- [ ] Password hashing verified (ARGON2ID)
- [ ] CSRF tokens implemented
- [ ] SQL injection prevention verified
- [ ] XSS protection in place
- [ ] Security headers configured

### Infrastructure
- [ ] Server resources adequate (CPU, RAM, Disk)
- [ ] PHP 8+ installed and configured
- [ ] MySQL/MariaDB running and optimized
- [ ] Backup system configured and tested
- [ ] Monitoring tools configured

---

## Environment Configuration

### Required Environment Variables

Create a `.env` file in the project root:

```env
# Application Environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database Configuration
DB_HOST=localhost
DB_NAME=citcoder_Quality
DB_USER=your_db_user
DB_PASS=your_secure_password
DB_CHARSET=utf8mb4

# Session Configuration
SESSION_LIFETIME=1800
SESSION_SECURE=true
SESSION_HTTPONLY=true
SESSION_SAMESITE=Strict

# Security
CSRF_TOKEN_LENGTH=32
METADATA_SIZE_LIMIT=50000

# Backup Configuration
BACKUP_RETENTION_DAYS=30
BACKUP_DIR=/path/to/backups

# Admin Configuration
MASTER_ADMIN_USERNAME=DrGabriel
```

### PHP Configuration (`php.ini`)

```ini
; Error Reporting
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = /var/log/php/error.log

; Security
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

; Performance
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
post_max_size = 20M
upload_max_filesize = 10M

; Session
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
session.cookie_samesite = Strict
```

---

## Deployment Steps

### 1. Enable Maintenance Mode

Create `maintenance.php` in the web root and redirect all traffic:

```php
<?php
http_response_code(503);
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>صيانة مجدولة</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            text-align: center;
            max-width: 500px;
        }
        h1 { color: #667eea; }
        p { color: #666; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 صيانة مجدولة</h1>
        <p>نظام الجودة قيد الصيانة حالياً</p>
        <p>سنعود قريباً. نعتذر عن الإزعاج.</p>
        <p><strong>الوقت المتوقع للانتهاء:</strong> ساعة واحدة</p>
        <p>للاستفسارات: quality@college.edu</p>
    </div>
</body>
</html>
```

Add to `.htaccess` (Apache) or nginx config:

```apache
# .htaccess
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/maintenance\.php$
RewriteRule ^(.*)$ /maintenance.php [R=503,L]
```

### 2. Backup Production Database

```bash
php scripts/backup_database.php --type=monthly
```

Verify backup created:
```bash
ls -lh backups/monthly/
```

### 3. Pull Latest Code

```bash
# Navigate to project directory
cd /path/to/quality-system

# Stash any local changes
git stash

# Pull latest code
git pull origin main

# Verify correct branch and commit
git log -1
```

### 4. Update Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 5. Run Database Migrations

```bash
# Review migrations first
ls -l database/migrations/

# Run migrations
mysql -u username -p citcoder_Quality < database/migrations/015_create_audit_log.sql

# Verify migration
mysql -u username -p citcoder_Quality -e "SHOW TABLES LIKE 'AuditLog';"
```

### 6. Clear Caches

```bash
# Clear PHP OPcache
php -r "opcache_reset();"

# Clear application cache if applicable
rm -rf cache/*

# Clear session files (optional, will log out all users)
# rm -rf sessions/*
```

### 7. Set Correct Permissions

```bash
# Set ownership
chown -R www-data:www-data /path/to/quality-system

# Set directory permissions
find /path/to/quality-system -type d -exec chmod 755 {} \;

# Set file permissions
find /path/to/quality-system -type f -exec chmod 644 {} \;

# Make scripts executable
chmod +x scripts/*.php

# Secure sensitive files
chmod 600 config/*.php
chmod 600 .env
```

### 8. Verify Configuration

```bash
# Check PHP version
php -v

# Check database connection
php -r "require 'config/DbConnection.php'; echo 'DB Connected';"

# Verify environment
php -r "echo getenv('APP_ENV');"
```

### 9. Test Critical Paths

Test the following manually or with automated tests:

- [ ] Homepage loads
- [ ] Admin login works
- [ ] Form creation works
- [ ] Form submission works
- [ ] Statistics display correctly
- [ ] Audit log recording
- [ ] Backup script runs

### 10. Disable Maintenance Mode

Remove maintenance redirect from `.htaccess` or nginx config.

```bash
# Verify site is accessible
curl -I https://your-domain.com
```

### 11. Monitor Logs

```bash
# Watch error log
tail -f /var/log/php/error.log

# Watch audit log
tail -f backups/logs/backup_log.txt

# Watch database log
tail -f /var/log/mysql/error.log
```

---

## Rollback Procedure

If deployment fails or critical issues are discovered:

### 1. Enable Maintenance Mode
(See step 1 above)

### 2. Restore Database Backup

```bash
php scripts/restore_database.php backups/monthly/backup_YYYY-MM-DD_HH-ii-ss.sql.gz
```

### 3. Revert Code

```bash
# Find previous commit
git log -5

# Revert to previous version
git reset --hard <previous-commit-hash>

# Or checkout previous tag
git checkout v1.0.0
```

### 4. Clear Caches

```bash
php -r "opcache_reset();"
rm -rf cache/*
```

### 5. Verify Functionality

Test critical paths to ensure rollback was successful.

### 6. Disable Maintenance Mode

Remove maintenance redirect.

### 7. Investigate and Document

- Document what went wrong
- Create post-mortem report
- Plan fixes for next deployment

---

## Post-Deployment Monitoring

### Immediate Checks (First Hour)

- [ ] Monitor error logs for new errors
- [ ] Check response times (should be < 2 seconds)
- [ ] Verify backup script ran successfully
- [ ] Check audit log for suspicious activity
- [ ] Test critical user flows
- [ ] Monitor database performance

### First 24 Hours

- [ ] Review audit log statistics
- [ ] Check backup retention working
- [ ] Monitor disk space usage
- [ ] Review user-reported issues
- [ ] Check email notifications working

### First Week

- [ ] Analyze performance metrics
- [ ] Review security logs
- [ ] Check backup integrity
- [ ] Gather user feedback
- [ ] Plan optimization if needed

---

## Scheduled Tasks

### Daily Tasks (Automated)

```bash
# Crontab entry for daily backup at 2 AM
0 2 * * * php /path/to/quality-system/scripts/backup_database.php --type=daily

# Weekly backup on Sunday at 3 AM
0 3 * * 0 php /path/to/quality-system/scripts/backup_database.php --type=weekly

# Monthly backup on 1st at 4 AM
0 4 1 * * php /path/to/quality-system/scripts/backup_database.php --type=monthly
```

### Windows Task Scheduler

```powershell
# Daily backup
schtasks /create /tn "QualitySystem_DailyBackup" /tr "php C:\path\to\scripts\backup_database.php --type=daily" /sc daily /st 02:00

# Weekly backup
schtasks /create /tn "QualitySystem_WeeklyBackup" /tr "php C:\path\to\scripts\backup_database.php --type=weekly" /sc weekly /d SUN /st 03:00
```

---

## Monitoring and Alerts

### Key Metrics to Monitor

1. **Response Time**: < 2 seconds for 95th percentile
2. **Error Rate**: < 0.1% of requests
3. **Database Query Time**: < 500ms average
4. **Disk Space**: > 20% free
5. **Backup Success Rate**: 100%
6. **Failed Login Attempts**: < 10 per hour

### Alert Thresholds

- **Critical**: Response time > 5 seconds
- **Warning**: Disk space < 20%
- **Info**: Backup completed successfully

---

## Troubleshooting

### Common Issues

**Issue**: White screen / 500 error
- Check PHP error log
- Verify database connection
- Check file permissions

**Issue**: Slow performance
- Check database indexes
- Review slow query log
- Check server resources

**Issue**: Session issues
- Verify session configuration
- Check session directory permissions
- Clear old session files

**Issue**: Backup fails
- Check disk space
- Verify mysqldump installed
- Check database credentials

---

## Security Hardening

### Web Server Configuration

**Apache (.htaccess)**:
```apache
# Disable directory listing
Options -Indexes

# Protect sensitive files
<FilesMatch "\.(env|sql|log)$">
    Require all denied
</FilesMatch>

# Security headers
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

**Nginx**:
```nginx
# Deny access to sensitive files
location ~ /\.(env|git|sql) {
    deny all;
}

# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
```

---

## Contact Information

**Technical Support**: tech-support@college.edu  
**Emergency Contact**: +218-XX-XXXXXXX  
**Developer**: Mohamed Fouad Bala

---

**Last Updated**: 2026-02-03  
**Version**: 1.0.0
