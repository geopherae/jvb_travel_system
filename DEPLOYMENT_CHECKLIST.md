# JVB Travel System - Production Deployment Checklist

Use this checklist to ensure a smooth deployment to Hostinger.

## Pre-Deployment (Local)

### Code Preparation
- [ ] All features tested locally in `development` mode
- [ ] No debug statements or `var_dump()` calls left in code
- [ ] No hardcoded credentials in any PHP files
- [ ] Database migrations documented (schema changes, new columns, etc.)
- [ ] All `.htaccess` files are in place (see repo root and subdirectories)

### Environment Setup
- [ ] Create `.env` file locally (copy from `.env.example`)
- [ ] Fill in real Hostinger database credentials in `.env`
- [ ] Fill in OpenWeatherMap API key in `.env`
- [ ] Test that `actions/db.php` correctly reads from `$_ENV` variables
- [ ] Run `composer install --no-dev` to optimize for production

### Database
- [ ] Create fresh database backup: `mysqldump -u root jvb_travel_db > backup_$(date +%Y%m%d_%H%M%S).sql`
- [ ] Store backup safely outside the project directory
- [ ] Test restore on a local copy to verify backup integrity

### Version Control
- [ ] Commit all changes to `develop` branch
- [ ] Create pull request, review, and merge to `main`
- [ ] Tag release: `git tag -a v1.0.0 -m "Initial production release"`
- [ ] Push tags to remote: `git push origin main --tags`

---

## Hostinger Deployment

### Create Hosting Account & Database
- [ ] Create Hostinger account (if not already done)
- [ ] Set up MySQL database via Hostinger cpanel
- [ ] Note down database host, username, password
- [ ] Create database user with full privileges on database only
- [ ] Test connection locally with test credentials

### Upload Files
- [ ] Upload all project files to Hostinger public_html folder (via FTP/SFTP)
- [ ] **DO NOT upload** `.env` file via version control
- [ ] Create `.env` file directly on Hostinger with production credentials
- [ ] Verify all `.htaccess` files are present and readable

### Configure PHP & Server
- [ ] Verify PHP version ≥ 8.2: `php -v`
- [ ] Enable required PHP extensions:
  - [ ] `mysqli` (for database)
  - [ ] `gd` (for image compression)
  - [ ] `json` (for JSON encoding)
  - [ ] `finfo` (for MIME type detection)
- [ ] Set PHP memory limit to at least 256MB
- [ ] Set upload_max_filesize and post_max_size to at least 10MB

### Database Setup
- [ ] Create database via Hostinger cpanel
- [ ] Use phpMyAdmin to import `jvb_travel_db.sql`
- [ ] Verify all tables created successfully
- [ ] Check that checksums validate (JSON columns have constraints)

### File Permissions
- [ ] Set permissions on uploads directory: `chmod 755 uploads/`
- [ ] Set permissions on uploads subdirectories: `chmod 755 uploads/client_profiles/`, `uploads/admin_photo/`
- [ ] Set permissions on logs directory: `chmod 755 logs/`
- [ ] Ensure `.env` file is readable by PHP only: `chmod 600 .env`

### WebSocket Server (if using Supervisor)
- [ ] Contact Hostinger support to confirm Supervisor availability
- [ ] If VPS: Install Supervisor: `apt-get install supervisor`
- [ ] Create `/etc/supervisor/conf.d/websocket.conf` with Supervisor config
- [ ] Restart Supervisor: `supervisorctl reread && supervisorctl update`
- [ ] Verify WebSocket running: `supervisorctl status jvb-websocket`

### WebSocket Server (Fallback - Polling Only)
- [ ] If shared hosting without Supervisor, disable WebSocket
- [ ] Ensure `includes/messages_poller.js` is the only messaging mechanism
- [ ] Update client-side config to use polling only

### Security Verification
- [ ] Test that `actions/db.php` is not directly accessible
- [ ] Test that `.env` file returns 403 Forbidden
- [ ] Test that `.sql` files return 403 Forbidden
- [ ] Test that `/uploads/` directory serves images but blocks PHP execution
- [ ] Test that `/logs/` directory is not web-accessible

### API Configuration
- [ ] Verify OpenWeatherMap API key in `.env` is valid
- [ ] Test weather widget on admin dashboard loads correctly
- [ ] Confirm IP geolocation works (should show actual user location)

---

## Post-Deployment Testing

### Functional Testing
- [ ] Test admin login from clean browser session
- [ ] Test client login with sample account
- [ ] Test file upload (documents, photos) - verify compression works
- [ ] Test messaging system (real-time or polling)
- [ ] Test notifications dismissal
- [ ] Test client checklist progression
- [ ] Test itinerary confirmation

### Load Testing
- [ ] Verify site loads within 3 seconds
- [ ] Check database response times with current data volume
- [ ] Monitor server logs for errors or warnings

### Browser Compatibility
- [ ] Test on Chrome, Firefox, Safari, Edge
- [ ] Test on mobile (iOS Safari, Chrome Android)
- [ ] Verify responsive design on small screens

### Security Audit
- [ ] Run HTTPS/SSL check (verify Hostinger provides free SSL)
- [ ] Check for mixed content warnings (all assets must be HTTPS)
- [ ] Verify no sensitive data in browser console or Network tab
- [ ] Test session timeout works (wait 30+ minutes, refresh)
- [ ] Verify audit logs are being recorded correctly

---

## Maintenance & Monitoring

### Daily
- [ ] Monitor error logs: `tail -f logs/*.log`
- [ ] Check WebSocket process status (if applicable)

### Weekly
- [ ] Review audit logs for suspicious activity
- [ ] Check database backup completion
- [ ] Monitor disk usage on Hostinger

### Monthly
- [ ] Review performance metrics (response times, database queries)
- [ ] Check for PHP/dependency updates
- [ ] Create release notes for any bug fixes applied

### Backup Strategy
- [ ] Automate daily database backups (Hostinger cpanel → Backups)
- [ ] Store off-site backups (cloud storage, external drive)
- [ ] Test restore process monthly
- [ ] Keep at least 30 days of backups

---

## Rollback Plan (If Issues Occur)

1. **Immediate Actions:**
   - Stop new connections/notify users of maintenance
   - Take note of current time and error conditions

2. **Revert Database:**
   - Via phpMyAdmin: Drop current database
   - Import previous backup from 1 day ago
   - Verify data integrity

3. **Revert Code:**
   - Via FTP: Download current code (for forensics)
   - Delete all files from public_html
   - Extract previous tagged release from git: `git checkout v1.0.0`
   - Upload extracted files to public_html

4. **Clear Sessions:**
   - Delete all files in temp/session directory on Hostinger
   - Force users to log in again

5. **Post-Rollback Verification:**
   - Test all core functionality
   - Monitor logs for 1 hour
   - Notify stakeholders

---

## Useful Hostinger Commands (SSH/VPS)

```bash
# Check PHP version
php -v

# Check installed extensions
php -m

# Restart PHP-FPM
systemctl restart php-fpm

# Check MySQL status
systemctl status mysql

# Check disk usage
df -h

# Check file permissions
ls -la uploads/

# Monitor real-time logs
tail -f /var/log/php-fpm/www-error.log

# Supervisor commands
supervisorctl status
supervisorctl restart jvb-websocket
supervisorctl reread && supervisorctl update

# Database dump
mysqldump -u username -p database_name > backup.sql

# Database restore
mysql -u username -p database_name < backup.sql
```

---

## Contacts & Resources

- **Hostinger Support:** support@hostinger.com
- **Git Repository:** [Your repo URL]
- **Database Backups Location:** [Specify your backup storage location]
- **Incident Contact:** [Team lead or emergency contact]
