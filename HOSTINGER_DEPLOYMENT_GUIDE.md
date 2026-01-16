# JVB Travel System - Hostinger Deployment Guide

**Date:** January 15, 2026  
**Version:** v1.0.0  
**Status:** Ready for Production Deployment

---

## Your Hostinger Credentials

| Item | Value |
|------|-------|
| **Domain** | (Your domain - to be accessed via web) |
| **Account Username** | u157926219 |
| **FTP Host** | 145.79.26.27 |
| **FTP Port** | 21 |
| **FTP Username** | u157926219 |
| **Database Host** | auth-db2093.hstgr.io |
| **Database Name** | u157926219_jvb_travel_db |
| **Database Username** | jvb_admin |
| **Database Password** | Vbx90020012001545900 |

---

## Step 1: Create Database on Hostinger

1. **Login to Hostinger hPanel**
2. Go to **Databases** → **MySQL**
3. Look for the database: `u157926219_jvb_travel_db`
   - If it exists, verify username `jvb_admin` is set
   - If NOT, create it with username `jvb_admin` and password `Vbx90020012001545900`

**Verify:**
```
Host: auth-db2093.hstgr.io
Username: jvb_admin
Database: u157926219_jvb_travel_db
Password: Vbx90020012001545900
```

---

## Step 2: Upload Files via FTP

### Using Windows File Manager (Easiest)

1. **Open File Manager** (Windows Explorer)
2. **Click Address Bar** and type:
   ```
   ftp://u157926219@145.79.26.27
   ```
3. **Press Enter** and enter password: `Vbx90020012001545900!`
4. Navigate to: **public_html**
5. **Upload all files from your local project** (except `.git` and `node_modules`)
   - Drag & drop files from `c:\xampp\htdocs\jvb_travel_system\`

### Using FileZilla (Recommended)

1. **Download FileZilla** (free FTP client)
2. **File** → **Site Manager** → **New site**
3. Configure:
   - **Protocol:** FTP
   - **Host:** 145.79.26.27
   - **Port:** 21
   - **Username:** u157926219
   - **Password:** Vbx90020012001545900!
4. Click **Connect**
5. Navigate to **public_html** on the right panel
6. Upload all files from your local project

**Important:** Make sure `.htaccess` files are uploaded (they may be hidden)

---

## Step 3: Create .env File on Hostinger

**⚠️ CRITICAL: Do NOT upload .env via FTP from git!**

After uploading files, create the `.env` file directly on the server:

### Option A: Using Hostinger File Manager (Easiest)

1. **Login to hPanel** → **Files** → **File Manager**
2. Navigate to **public_html**
3. Click **Create File**
4. Name it: `.env`
5. Copy and paste this content:

```
# Database Configuration (Hostinger)
DB_HOST=auth-db2093.hstgr.io
DB_USER=jvb_admin
DB_PASS=Vbx90020012001545900
DB_NAME=u157926219_jvb_travel_db

# Environment
ENV=production

# External APIs
OPENWEATHER_API_KEY=f42aaf95272e2b1942c4e5a7251231b5

# WebSocket Configuration
WEBSOCKET_HOST=145.79.26.27
WEBSOCKET_PORT=8080
WEBSOCKET_PROTOCOL=ws

# Session Configuration
SESSION_TIMEOUT_ADMIN=120
SESSION_TIMEOUT_CLIENT=30
```

6. Click **Save**

### Option B: Using FTP (via Text Editor)

1. Open FileZilla or File Manager
2. Create new empty file called `.env` in public_html
3. Right-click → **Edit** (or use Notepad)
4. Paste the content above
5. Save and upload

---

## Step 4: Import Database Backup

1. **Download backup** from your local machine:
   - File: `c:\xampp\backups\backup_20260115_181121.sql` (you created this locally)

2. **Access phpMyAdmin on Hostinger:**
   - Via hPanel: **Databases** → **phpMyAdmin**
   - Or direct: https://auth-db2093.hstgr.io/index.php?db=u157926219_jvb_travel_db

3. **Login to phpMyAdmin:**
   - Username: `jvb_admin`
   - Password: `Vbx90020012001545900`

4. **Import the backup:**
   - Click **Import** tab
   - **Choose File** → Select `backup_20260115_181121.sql`
   - Click **Import**

5. **Verify:**
   - Check that 19 tables were created
   - Verify `admin_accounts` table exists with admin user `chriscahill`

---

## Step 5: Set File Permissions

Via SSH (if available) or File Manager:

```bash
# Set directories to 755
chmod 755 public_html/uploads/
chmod 755 public_html/uploads/client_profiles/
chmod 755 public_html/uploads/admin_photo/
chmod 755 public_html/logs/

# Set .env to 600 (read-only for web server)
chmod 600 public_html/.env
```

**Via File Manager:**
1. Right-click each folder → **Properties**
2. Set **Permissions** to `755` (rwx r-x r-x)
3. For `.env`: Set to `600` (rw- --- ---)

---

## Step 6: Verify Security

Test that sensitive files are protected (should return **403 Forbidden**):

```
https://yourdomain.com/db.php              → 403 ✓
https://yourdomain.com/.env                → 403 ✓
https://yourdomain.com/.git/config         → 403 ✓
https://yourdomain.com/actions/db.php      → 403 ✓
```

Test that uploads work (should load image):

```
https://yourdomain.com/uploads/client_profiles/sample.jpg → 200 ✓
```

---

## Step 7: Test the Application

### Admin Login
1. Navigate to: `https://yourdomain.com/client/login.php`
2. Redirect takes you to: `https://yourdomain.com/admin/admin_login.php`
3. **Login with:**
   - Username: `chriscahill`
   - Password: Check database or ask team (it's the superadmin account from backup)

### Client Login
1. Navigate to: `https://yourdomain.com/client/login.php`
2. Login with any client account created in your database

### Verify Dashboard
- Dashboard should load
- Weather widget should display (uses OpenWeatherMap API)
- No errors in browser console

---

## Step 8: Monitor Logs

Check for errors:

1. **Via File Manager:**
   - Navigate to `logs/` folder
   - Open `.log` files to check for errors

2. **Via SSH (if available):**
   ```bash
   tail -f /home/u157926219/logs/*.log
   ```

3. **Check for common issues:**
   - Database connection errors
   - Missing files (404s)
   - Permission denied (403s)

---

## Troubleshooting

### "Connection failed" to database
- Verify DB_HOST is correct: `auth-db2093.hstgr.io`
- Verify .env is in public_html root
- Check that database user has access from your Hostinger IP

### White screen or blank page
- Check `logs/` folder for PHP errors
- Verify all `.htaccess` files were uploaded
- Ensure PHP 8.2+ is enabled in Hostinger settings

### Files returning 404
- Verify all files were uploaded completely
- Check that `.htaccess` isn't blocking legitimate requests
- Ensure `public_html/` is the root directory

### Images not displaying
- Check `uploads/` folder exists and has 755 permissions
- Verify `.htaccess` in uploads allows image files

### WebSocket/Messaging not working
- WebSocket requires persistent processes (VPS-only)
- Shared hosting falls back to polling
- Check that `includes/messages_poller.js` is loaded

---

## Realtime Without VPS (Shared Hosting)

Hostinger Starter (shared) cannot run a persistent PHP process like `websocket_server.php`. Use one of these approaches:

### Option A — Polling (Built-in, simplest)
- **What:** Periodically fetch new messages/notifications via AJAX.
- **Status:** Already implemented via [includes/messages_poller.js](includes/messages_poller.js).
- **Action:** Ensure your frontend initializes the poller when WebSocket init fails; disable any forced WebSocket attempts.
- **Tip:** If you maintain an environment flag, set `REALTIME_MODE=polling` in `.env` and branch your JS accordingly.

### Option B — Managed Realtime Service (Pusher/Ably)
- **What:** Offload WebSocket infra to a hosted provider; PHP triggers events, browser subscribes.
- **Pros:** Reliable, TLS `wss` by default, no server maintenance.
- **Cons:** External dependency, monthly limits/costs apply.

#### Pusher Channels (example)
1. Create a Pusher app; get `app_id`, `key`, `secret`, `cluster`.
2. Add to `.env`:
   ```
   PUSHER_APP_ID=xxx
   PUSHER_KEY=xxx
   PUSHER_SECRET=xxx
   PUSHER_CLUSTER=ap1
   REALTIME_MODE=pusher
   ```
3. Install server SDK on Hostinger:
   ```bash
   composer require pusher/pusher-php-server
   ```
4. In your message/notification action (e.g., [actions/send_message.php](actions/send_message.php)), after saving to DB, publish:
   ```php
   // ...existing code saving message...
   $pusher = new Pusher\Pusher($_ENV['PUSHER_KEY'], $_ENV['PUSHER_SECRET'], $_ENV['PUSHER_APP_ID'], [
       'cluster' => $_ENV['PUSHER_CLUSTER'], 'useTLS' => true
   ]);
   $pusher->trigger('client-'.$clientId, 'new-message', [ 'message' => $messageRow ]);
   ```
5. In the browser, subscribe:
   ```html
   <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
   <script>
     const pusher = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER });
     const channel = pusher.subscribe('client-'+clientId);
     channel.bind('new-message', data => renderIncomingMessage(data.message));
   </script>
   ```

#### Ably (alternative)
- Server: `composer require ably/ably-php` then publish via REST using `ABLY_API_KEY`.
- Client: Use Ably JS SDK; subscribe to `channels.get('client-'+clientId).subscribe('new-message', ...)`.

### Option C — External WebSocket Microservice
- **What:** Run a dedicated WebSocket server on a platform like Render, Railway, Fly.io, Deno Deploy, or Cloudflare Workers.
- **How:** Point clients to `wss://your-realtime.example/ws`; from PHP, call the service via HTTP/REST to broadcast events.
- **Note:** If using Cloudflare Workers/Durable Objects, implementation is in JS; you’ll bridge PHP → Worker via authenticated fetch.

### Option D — Server-Sent Events (SSE)
- **What:** Unidirectional streaming over HTTP.
- **Caveat:** Shared hosting often limits long-running PHP requests; SSE may be terminated by timeout. Prefer options A/B unless you can confirm generous timeouts.

### Option E — Cron + Fast Polling
- **What:** For low-frequency notifications, combine 30–60s polling with cron tasks for housekeeping.
- **Caveat:** Not real-time; acceptable for light workloads only.

---

## Choosing an Option
- **Chat + notifications, production-ready:** Use Pusher or Ably.
- **Budget, simplest:** Use built-in polling; tune interval to balance UX vs load.
- **Custom control, willing to manage infra:** Host an external WebSocket service and integrate.

## Minimal Changes Checklist
- Add a `REALTIME_MODE` toggle in `.env` and branch frontend init logic.
- If using Pusher/Ably, install the PHP SDK via Composer and publish events in your existing `actions/*` after DB writes.
- Ensure TLS: use `wss://` endpoints; on shared hosting all pages should serve over HTTPS.

---

## Important Notes

⚠️ **Keep your .env file safe!** It contains production credentials.

⚠️ **Do NOT commit .env to GitHub!** It's in `.gitignore` for security.

⚠️ **Database backups:** Create regular backups via Hostinger cpanel → Backups

✅ **SSL Certificate:** Hostinger typically provides free SSL (HTTPS)

✅ **Monitoring:** Monitor logs regularly for issues

---

## Next Steps After Deployment

1. **Test all features:**
   - Admin login
   - Client login
   - File uploads
   - Messaging
   - Document approval workflow

2. **Setup automated backups:**
   - Hostinger cpanel → Backups
   - Schedule daily backups

3. **Monitor performance:**
   - Check page load times
   - Monitor database queries
   - Watch error logs

4. **Security:**
   - Change default admin password (if needed)
   - Review audit logs
   - Monitor failed login attempts

---

## Support

If you encounter issues:
1. Check `DEPLOYMENT_CHECKLIST.md` for detailed steps
2. Review `logs/` folder for error messages
3. Contact Hostinger support for server-level issues
4. Check `copilot-instructions.md` for architecture details

---

**Deployment Date:** January 15, 2026  
**Version:** v1.0.0  
**Status:** Ready for Production
