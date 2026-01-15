# Quick Hostinger Deployment Checklist

**Your Hostinger Account Details:**
```
FTP Host: 145.79.26.27
FTP Username: u157926219
FTP Password: Vbx90020012001545900!
FTP Port: 21

Database Host: auth-db2093.hstgr.io
Database Name: u157926219_jvb_travel_db
Database User: jvb_admin
Database Password: Vbx90020012001545900

Backup File: backup_20260115_181121.sql
Location: c:\xampp\backups\backup_20260115_181121.sql
```

---

## Pre-Deployment Checklist

### Local Preparation
- [x] Database backup created and tested
- [x] Git repository initialized with v1.0.0 tag
- [x] `.env` file created for local development
- [x] All hardcoded credentials removed from code
- [x] Production `.env` created for Hostinger (`.env.hostinger`)
- [ ] **Download backup file to your computer** 
  - Source: `c:\xampp\backups\backup_20260115_181121.sql`
  - Safe location: Keep it accessible for FTP upload

---

## Deployment Steps

### Step 1: Setup Database on Hostinger
- [ ] Login to hPanel
- [ ] Go to **Databases** → **MySQL**
- [ ] Verify database `u157926219_jvb_travel_db` exists
- [ ] Verify username `jvb_admin` with password `Vbx90020012001545900`

### Step 2: Upload Files via FTP
- [ ] Use FileZilla or File Manager
- [ ] Connect to: `ftp://145.79.26.27`
  - Username: `u157926219`
  - Password: `Vbx90020012001545900!`
- [ ] Navigate to **public_html**
- [ ] Upload ALL files from `c:\xampp\htdocs\jvb_travel_system\`
  - **EXCLUDE:** `.git/`, `node_modules/`, `.env` (local), `backup_*.sql`
  - **INCLUDE:** All `.htaccess` files (may be hidden)

### Step 3: Create .env on Server
- [ ] Via hPanel **File Manager**: Create new file `.env`
- [ ] OR: Upload `.env.hostinger` and rename to `.env`
- [ ] Content:
  ```
  DB_HOST=auth-db2093.hstgr.io
  DB_USER=jvb_admin
  DB_PASS=Vbx90020012001545900
  DB_NAME=u157926219_jvb_travel_db
  ENV=production
  OPENWEATHER_API_KEY=f42aaf95272e2b1942c4e5a7251231b5
  WEBSOCKET_HOST=145.79.26.27
  WEBSOCKET_PORT=8080
  WEBSOCKET_PROTOCOL=ws
  SESSION_TIMEOUT_ADMIN=120
  SESSION_TIMEOUT_CLIENT=30
  ```

### Step 4: Set File Permissions
- [ ] Set `uploads/` to 755 (rwxr-xr-x)
- [ ] Set `logs/` to 755 (rwxr-xr-x)
- [ ] Set `.env` to 600 (rw-------)
- [ ] Set other directories to 755

### Step 5: Import Database
- [ ] Access phpMyAdmin: https://auth-db2093.hstgr.io
  - Login: `jvb_admin` / `Vbx90020012001545900`
- [ ] Click **Import** tab
- [ ] Upload `backup_20260115_181121.sql`
- [ ] Click **Import**
- [ ] Verify: 19 tables created

### Step 6: Verify Deployment
- [ ] Test admin login: `https://yourdomain.com/admin/admin_login.php`
  - Username: `chriscahill`
  - Password: *(check your database)*
- [ ] Verify database connection (no error pages)
- [ ] Check that assets load (CSS, JS, images)
- [ ] Test file upload
- [ ] Check logs for errors

### Step 7: Security Verification
- [ ] Test `.env` returns 403: `https://yourdomain.com/.env`
- [ ] Test `db.php` returns 403: `https://yourdomain.com/db.php`
- [ ] Test uploads work: `https://yourdomain.com/uploads/client_1/` (should show files)
- [ ] Test logs are blocked: `https://yourdomain.com/logs/` (should return 403)

---

## Post-Deployment

- [ ] Test all admin features
- [ ] Test all client features
- [ ] Monitor error logs for 24 hours
- [ ] Setup automated backups in Hostinger
- [ ] Document any custom configurations
- [ ] Notify team of live deployment

---

## Important Files to Know

| File | Purpose | Location |
|------|---------|----------|
| `.env` | Production credentials | `public_html/.env` (on server) |
| `.env.hostinger` | Template (local) | `c:\xampp\htdocs\jvb_travel_system\.env.hostinger` |
| `backup_20260115_181121.sql` | Database backup | `c:\xampp\backups\` |
| `HOSTINGER_DEPLOYMENT_GUIDE.md` | Full deployment guide | `c:\xampp\htdocs\jvb_travel_system\` |
| `.htaccess` | Security rules | Multiple locations (root, actions/, includes/, uploads/, logs/) |

---

## Emergency Contacts & Resources

- **Hostinger Support:** support@hostinger.com
- **Your Domain:** *(to be added)*
- **Database Credentials:** Safely stored above
- **Backup Location:** `c:\xampp\backups\backup_20260115_181121.sql`

---

**Ready to deploy? Follow HOSTINGER_DEPLOYMENT_GUIDE.md step by step!** 🚀
