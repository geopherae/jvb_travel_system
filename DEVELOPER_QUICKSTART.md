# JVB Travel System - Developer Quick Start

Welcome! This guide helps you get the project running locally.

## Prerequisites

- PHP 8.2+ (check with `php -v`)
- MySQL 10.4+ (local server or XAMPP)
- Composer (for dependencies)
- Node.js & npm (for Tailwind CSS builds)
- Git

## 1. Clone & Setup

```bash
cd c:\xampp\htdocs
git clone <your-repo-url> jvb_travel_system
cd jvb_travel_system
```

## 2. Install Dependencies

```bash
# PHP dependencies (Ratchet WebSocket)
composer install

# Node dependencies (Tailwind CSS)
npm install
```

## 3. Environment Configuration

```bash
# Copy template to create local .env file
cp .env.example .env

# Edit .env and set:
ENV=development
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=jvb_travel_db
OPENWEATHER_API_KEY=<your-api-key>
```

## 4. Database Setup

```bash
# Create database and import schema
mysql -u root -p jvb_travel_db < '!--DATABASE BACKUP--!/jvb_travel_db.sql'

# If fresh start, use clean slate
mysql -u root -p jvb_travel_db < '!--DATABASE BACKUP--!/jvb_travel_db (Clean Slate).sql'
```

## 5. Enable Development Mode

Edit `actions/db.php`:
```php
if (!defined('ENV')) define('ENV', 'development'); // Shows detailed errors
```

## 6. Start Development Servers

### Terminal 1: Tailwind CSS (Auto-compile)
```bash
npm run dev
```

### Terminal 2: WebSocket Server
```bash
php websocket_server.php
```

### Terminal 3: Open Browser
Navigate to: `http://localhost/jvb_travel_system/client/login.php`

## 7. Test Accounts

**Admin Login:**
- Username: `chriscahill`
- Password: Check database or ask team lead

**Client Access:**
- Create via admin dashboard, or find in `clients` table
- Access code available in database

## 8. Common Development Tasks

### Build Tailwind CSS (One-time minify)
```bash
npm run build
```

### View Database
```bash
# Option 1: XAMPP phpMyAdmin
http://localhost/phpmyadmin
# Username: root
# Password: (leave blank)

# Option 2: MySQL CLI
mysql -u root -p jvb_travel_db
```

### Check PHP Configuration
```bash
php -r "phpinfo();"
```

### Monitor Logs
```bash
# PHP Errors
tail -f logs/*.log

# Database Errors
# Check XAMPP MySQL error log
```

### Clear Session Files (if session issues)
```bash
# Windows Command Prompt
del C:\xampp\tmp\sess_*

# Or restart Apache: XAMPP Control Panel → Stop/Start Apache
```

## 9. Code Structure at a Glance

| Folder | Purpose |
|--------|---------|
| `client/` | Client-facing pages (dashboard, upload, itinerary) |
| `admin/` | Admin pages (client management, analytics, settings) |
| `actions/` | PHP controllers for form processing & AJAX |
| `includes/` | Shared utilities (auth, helpers, session checks) |
| `components/` | Reusable PHP template fragments |
| `uploads/` | User-uploaded files (profiles, photos) |
| `vendor/` | Composer dependencies (Ratchet WebSocket) |

## 10. Important Files

| File | Purpose |
|------|---------|
| `index.php` | Entry point (redirects to login) |
| `actions/db.php` | Database connection & environment config |
| `includes/auth.php` | Authentication guard function |
| `websocket_server.php` | WebSocket server (run in terminal) |
| `.env.example` | Environment variables template |
| `.gitignore` | Prevent committing sensitive files |

## 11. Troubleshooting

### "Connection failed: php_network_getaddresses"
- MySQL not running. Start XAMPP MySQL server.

### "Access denied for user 'root'@'localhost'"
- Update `DB_PASS` in `.env` to match your MySQL password.

### WebSocket not working
- Ensure terminal with `php websocket_server.php` is still running.
- Check for port 8080 conflicts: `netstat -ano | findstr 8080` (Windows)

### CSS not updating
- Ensure `npm run dev` terminal is running.
- Clear browser cache: `Ctrl+Shift+Delete` in Chrome.

### Permission denied on uploads/
- Check folder permissions: `ls -la uploads/`
- Fix: `chmod 755 uploads/ uploads/client_profiles/ uploads/admin_photo/ logs/`

### Sessions timing out
- Check `admin_accounts.session_timeout` in database.
- Default: 30 minutes (1800 seconds) for clients, 120 minutes for admins.

## 12. Git Workflow

### Create Feature Branch
```bash
git checkout develop
git pull origin develop
git checkout -b feature/my-feature-name
```

### Commit & Push
```bash
git add .
git commit -m "Brief description of changes"
git push origin feature/my-feature-name
```

### Merge to Develop (via Pull Request)
- Create PR on GitHub/GitLab
- Request code review
- Merge when approved

### Release to Production
```bash
git checkout main
git pull origin main
git merge develop
git tag -a v1.0.1 -m "Release notes"
git push origin main --tags
```

## 13. Need Help?

- Check `copilot-instructions.md` for architecture details
- Check `DEPLOYMENT_CHECKLIST.md` for production setup
- Review component code: `components/*.php`
- Check action handlers: `actions/*.php`
- Read database schema: `!--DATABASE BACKUP--!/jvb_travel_db.sql`

---

**Happy coding! 🚀**
