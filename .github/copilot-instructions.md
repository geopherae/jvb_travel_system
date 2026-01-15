# AI Coding Agent Instructions for JVB Travel System

A comprehensive itinerary and document management system for travel clients and administrators.

## Architecture Overview

**Stack:** PHP 8.2+ (procedural), MySQL 10.4+, Tailwind CSS 4.1, WebSocket (Ratchet), vanilla JS

**Key Entrypoints:**
- `index.php` → redirects to `client/login.php`
- `client/` folder: Client-facing dashboard, documents, itinerary, photos
- `admin/` folder: Admin dashboard, client management, tour packages, metrics
- `actions/` folder: PHP controllers handling POST/AJAX requests
- `includes/` folder: Shared utilities (auth, helpers, session checks)
- `components/` folder: Reusable PHP template components

**Core Data Models** (from `jvb_travel_db.sql`):
- `admin_accounts`: Admins/superadmins with roles (superadmin, admin, Read-Only)
- `clients`: Client profiles with status (Awaiting Docs, Confirmed, Trip Ongoing, Trip Completed, Archived, etc.)
- `tour_packages`: Travel packages with day/night duration, inclusions (JSON), itinerary
- `client_checklist_progress`: Multi-step onboarding checklist (survey, upload ID, confirm itinerary, upload photos, etc.)
- `client_itinerary`: Per-client itinerary confirmation status
- `client_trip_photos`: Uploaded trip photos with approval workflow
- `audit_logs`: Comprehensive action tracking with actor context (IP, session ID, changes as JSON)
- `messages`: WebSocket chat logs between admin and clients
- `notifications`: Real-time notifications with delivery tracking
- `user_survey_status`: Tracks first-login and post-trip surveys

## Authentication & Authorization Pattern

**Files:** `includes/auth.php`, `admin/admin_session_check.php`, `includes/client_session_check.php`

```php
// At top of protected pages:
require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('admin');  // or 'client' or 'any'
```

**Key Guards:**
- `guard('admin')`: Blocks non-admin, redirects to `../admin/admin_login.php`, checks session timeout
- `guard('client')`: Blocks non-client, redirects to `../client/login.php`
- **Session timeout is dynamic**: Fetched from `admin_accounts.session_timeout` (in minutes, converted to seconds). Clients default to 1800s.
- **AJAX Detection:** Responds with `application/json` if `X-Requested-With: XMLHttpRequest` header present
- **Last activity tracking:** Updated on each `guard()` call via `$_SESSION['last_activity']`

**Actor Context** (for auditing):
```php
use function Auth\getActorContext;
$actor = getActorContext(); // Returns: id, role, session_id, ip, user_agent
```

## Critical Workflow Patterns

### 1. Database Connection
**File:** `actions/db.php`
- Global `$conn` (mysqli) variable available after `require_once`
- **Environment-aware errors:** `ENV = 'development'` shows detailed errors; `'production'` returns generic HTTP 500
- **Always prevent direct access:** `if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) exit('Access denied.');`
- **Charset enforced:** UTF8MB4 for multi-language support

### 2. Form Processing Pattern
**Pattern exemplified in:** `actions/process_add_client.php`
1. Guard authentication at top
2. Include DB connection and dependencies
3. Sanitize/trim all `$_POST` inputs
4. Validate inputs (emails, phone regex `^09\d{9}$` for PH numbers)
5. Handle file uploads (MIME type check with `finfo_open`, size limits, compress with `compressImage()`)
6. Execute prepared statements with `bind_param()` to prevent SQL injection
7. Log action via `logClientOnboardingAudit()` if data changed
8. Return JSON for AJAX: `header('Content-Type: application/json'); echo json_encode(['success' => bool, 'message' => string]);`

### 3. Image Processing
**File:** `includes/image_compression_helper.php`
- Use `compressImage($inputPath, $outputPath, $mimeType, $quality)` for all client uploads
- Always save as JPG internally: `'client_' . time() . '_' . rand(100, 999) . '.jpg'`
- Upload directory: `/uploads/client_profiles/` or `/uploads/admin_photo/`
- **Avatar retrieval:** Use `getClientAvatar($client, $default)` / `getAdminAvatar($admin, $default)` with static caching to avoid repeated `file_exists()` calls

### 4. JSON Data in Database
**Pattern:** Complex data (itinerary, inclusions, checklist, audit changes) stored as JSON strings in LONGTEXT columns with CHECK constraints
```php
// Encode:
$json = json_encode($arrayData, JSON_UNESCAPED_UNICODE);
// Store in LONGTEXT field

// Decode & normalize:
$decoded = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) { /* Handle error */ }
```
**Fields using JSON:**
- `tour_packages.inclusions_json`: Array of included items
- `tour_packages.itinerary_json`: Day-by-day breakdown
- `checklist_templates.checklist_json`: Template with deps, labels, points
- `client_checklist_progress.progress_json`: Status per checklist item
- `client_itinerary.itinerary_json`: Client's confirmed itinerary
- `audit_logs.changes`: Changed fields (old → new values)

### 5. Notification & Real-time Messaging
**WebSocket Server:** `websocket_server.php` (Ratchet-based)
- **Runs separately:** Start with `php websocket_server.php` (listens on port 8080 by default)
- **Actions:** `subscribe` (join channel), `send_message` (broadcast)
- **Database logging:** All messages saved to `messages` table
- **Client-side polling:** `includes/messages_poller.js` for fallback
- **Real-time notifications:** `includes/notifications.js` monitors `notifications` table
- **Dismiss notifications:** `actions/dismiss_notification.php`, `actions/dismiss_bulk_notifications.php`

### 6. Audit Logging Pattern
**File:** `includes/log_helper.php`
```php
use function LogHelper\logClientOnboardingAudit;
logClientOnboardingAudit(
    $conn,
    $clientId,
    'client_created',
    ['full_name' => $fullName, 'email' => $email],
    $actor = getActorContext()
);
```
**Stored in:** `audit_logs` table with JSON `changes` column, severity levels (Low/Medium/High/Critical), module tags, and KPI impact tracking

### 7. Client Status Lifecycle
**Statuses (enum):** Awaiting Docs → Under Review → Resubmit Files → Confirmed → Trip Ongoing → Trip Completed (or Cancelled, Archived, No Assigned Package)
- **Automatic status updates:** `actions/client_status_checker.php` runs on dashboard loads
- **Checklist triggers:** Completing checklist items (survey, upload ID, confirm itinerary) advance status
- **Admin override:** Via `update_booking.php` or `approve_document.php`

### 8. Checklist System
**Files:** `includes/checklist_helpers.php`, `includes/checklist.js`
- **Template structure:** JSON array with items (id, label, depends_on, required, points, status_key, action_url)
- **Progress tracking:** `client_checklist_progress.progress_json` stores completion status per item
- **Frontend:** `components/edit_checklist.php` (admin edit), `components/client_checklist_card.php` (client view)
- **Dependencies:** Items can depend on other items being complete before unlocking

### 9. Document Upload & Approval Workflow
**Flow:** Client uploads → Pending → Admin reviews → Approved/Rejected
- **Upload endpoints:** `client/upload_document_client.php`, `admin/upload_document_admin.php`
- **Status updates:** `actions/update_document_status.php`, `actions/approve_document.php`, `actions/reject_document.php`
- **Tracking:** `client_trip_photos` table stores status (Pending/Approved/Rejected), approved_at timestamp, approval admin name
- **Compression:** Trip photos auto-compressed on upload

### 10. Survey Management
**Types:** `first_login` (client), `trip_complete` (client), `admin_weekly_survey` (admin)
- **Tracking:** `user_survey_status` table (user_id, user_role, survey_type, is_completed, created_at)
- **Modal triggers:** Set `$_SESSION['show_client_survey_modal']`, `$_SESSION['show_trip_completion_survey']`, or `$_SESSION['show_weekly_survey_modal']`
- **Submission:** `actions/submit_client_first_time_survey.php`, `actions/submit_client_trip_completion_survey.php`, `actions/submit_admin_weekly_survey.php`
- **Render:** `components/client_first_time_survey_modal.php`, etc.

## Styling & Frontend Patterns

**CSS Framework:** Tailwind CSS 4.1 + PostCSS
- **Config:** `tailwind.config.js` (extends with brand colors: #007AFF, #FF5630 red, #38CB89 green)
- **Build:** `npm run dev` (watch), `npm run build` (minify)
- **Safelist:** Common sizes (w-6, h-12, rounded-full, etc.) pre-generated to prevent purging
- **Output:** `output.css` (generated, do NOT edit directly)
- **Input:** `input.css` (source, import Tailwind directives)

**Component Philosophy:**
- **PHP Components:** Stateless template fragments in `components/` (accept `$variable` parameters)
- **Include pattern:** `<?php include __DIR__ . '/../components/my_component.php'; ?>`
- **Modals:** Generic `general_confirmation_modal.php` (reusable), specific modals like `client_first_time_survey_modal.php`
- **Icons:** `includes/icon_map.php` returns SVG strings by icon name (e.g., `getIcon('checkmark')`)

## Common Conventions

**Naming:**
- Database tables: snake_case (e.g., `admin_accounts`, `client_trip_photos`)
- PHP functions: camelCase (e.g., `getClientAvatar()`, `logClientOnboardingAudit()`)
- JavaScript: camelCase (e.g., `fetchMessages()`, `updateNotifications()`)
- CSS classes: kebab-case (Tailwind standard, e.g., `text-gray-600`, `rounded-lg`)
- File uploads: `{entity}_{timestamp}_{randomNum}.{ext}` (e.g., `client_1692432100_542.jpg`)

**Timezone:** All timestamps in `Asia/Manila` (set via `date_default_timezone_set()` at top of processing scripts)

**Session Variables:**
- Admin: `$_SESSION['admin']['id']`, `$_SESSION['admin']['role']`, `$_SESSION['is_admin']`
- Client: `$_SESSION['client_id']`, `$_SESSION['last_activity']`
- Survey flags: `$_SESSION['show_client_survey_modal']`, `$_SESSION['show_trip_completion_survey']`
- Errors/success: `$_SESSION['message']`, `$_SESSION['message_type']` (used by `status_alert.php`)

**Error Handling:**
- **User-facing validation:** Collect in `$errors = []` array, display via `components/status_alert.php`
- **Database errors:** Log to `error_log()` before responding, never expose DB internals to client
- **File operations:** Check `file_exists()`, `is_dir()`, create with `mkdir(..., 0755, true)` if needed
- **JSON decode errors:** Always check `json_last_error() !== JSON_ERROR_NONE`

## Database Restoration & Backups

**Location:** `!--DATABASE BACKUP--!/` folder
- `jvb_travel_db (Clean Slate).sql` - Fresh database with no client data
- `jvb_travel_db.sql` - Latest full snapshot
- **Restore:** `mysql -u root -p jvb_travel_db < backup.sql`

## Development vs Production Workflows

### Local Development
1. **Environment Setup:**
   - Set `ENV = 'development'` in `actions/db.php` for detailed error messages
   - Database: Local MySQL with credentials in `actions/db.php` (hardcoded is OK for local)
   
2. **WebSocket Development:**
   - Navigate to project root: `cd c:\xampp\htdocs\jvb_travel_system`
   - Start server: `php websocket_server.php` (runs on port 8080 by default)
   - Keep terminal window open; server runs in foreground
   - Test messaging and real-time notifications in browser console

3. **Testing & Debugging:**
   - Use browser DevTools Network tab to monitor AJAX calls to `actions/*.php`
   - Check browser console for JavaScript errors in `includes/*.js`
   - Check server logs in `logs/` folder for PHP/WebSocket errors

### Production Deployment (Hostinger)

**Sensitive File Protection:**
- **DO NOT commit** `actions/db.php` with credentials to version control
- Create `.env` file (root directory) with database credentials:
  ```
  DB_HOST=your_hostinger_db_host
  DB_USER=your_hostinger_db_user
  DB_PASS=your_hostinger_db_password
  DB_NAME=jvb_travel_db
  ENV=production
  ```
- Update `actions/db.php` to read from `$_ENV` variables:
  ```php
  $host = $_ENV['DB_HOST'] ?? 'localhost';
  $user = $_ENV['DB_USER'] ?? 'root';
  $pass = $_ENV['DB_PASS'] ?? '';
  $db   = $_ENV['DB_NAME'] ?? 'jvb_travel_db';
  define('ENV', $_ENV['ENV'] ?? 'production');
  ```
- Add `.htaccess` rules to block direct access and prevent `.env` exposure:
  ```apache
  <Files "db.php">
      Deny from all
  </Files>
  <Files ".env">
      Deny from all
  </Files>
  <FilesMatch "\.(env|sql)$">
      Deny from all
  </FilesMatch>
  ```
- Verify `uploads/`, `logs/`, and `includes/` directories are not web-accessible (add `.htaccess` with `Deny from all`)

**WebSocket Server in Production:**
- **Option 1 (Recommended):** Use Supervisor to manage WebSocket process as system service
  - Install Supervisor: `apt-get install supervisor` (on Hostinger VPS)
  - Create config file `/etc/supervisor/conf.d/websocket.conf`:
    ```ini
    [program:jvb-websocket]
    process_name=%(program_name)s_%(process_num)02d
    command=php /home/your-user/public_html/websocket_server.php
    autostart=true
    autorestart=true
    numprocs=1
    user=nobody
    redirect_stderr=true
    stdout_logfile=/var/log/websocket.log
    ```
  - Restart Supervisor: `supervisorctl reread && supervisorctl update`
  - Check status: `supervisorctl status jvb-websocket`

- **Option 2 (Shared Hosting):** If Hostinger doesn't support persistent processes, fall back to polling-only (disable real-time, use `messages_poller.js` exclusively)

**Other Deployment Tasks:**
- Set `ENV = 'production'` in `actions/db.php` (or via `.env`)
- Upload database backup via Hostinger cpanel → phpMyAdmin, restore `jvb_travel_db.sql`
- Verify Composer dependencies: `composer install --no-dev` (minimize package size)
- Set directory permissions: `chmod 755 uploads/`, `logs/`, `uploads/client_profiles/`, `uploads/admin_photo/`
- Confirm PHP version ≥ 8.2 on Hostinger

## Versioning Strategy for Live Systems

**Git Workflow with Live Deployments:**

1. **Branch Structure:**
   - `main` → Production-ready code (tagged releases only)
   - `develop` → Integration branch for features
   - `feature/*` → Feature branches off `develop`
   - `hotfix/*` → Emergency fixes from `main`

2. **Pre-Deployment Checklist:**
   - Database backup before any update: `mysqldump -u user -p jvb_travel_db > backup_2025-01-12.sql`
   - Tag release: `git tag -a v1.0.1 -m "Fix audit log JSON encoding"`
   - Create release notes documenting schema changes, API changes, config changes

3. **Zero-Downtime Updates:**
   - Deploy to staging environment first (separate Hostinger folder)
   - Test on staging with live data copy
   - Database migrations: Always backward-compatible (add columns, don't drop)
   - Cache invalidation: Update `output.css` version hash in `<link>` tag to force reload
   - Session clearing: Consider notifying active users of maintenance window

4. **Rollback Plan:**
   - Keep previous database backups (at least 3 versions)
   - Keep previous code backups
   - If critical error: Restore DB backup → Restore code from previous tag → Clear session data → Test

**Environment Branching:**
```
# Local development
git checkout develop
# ... make changes ...
git push origin feature/my-feature
# Create PR, review, merge to develop

# Staging deployment (when ready)
git checkout main
git merge develop
git tag -a v1.0.1 -m "Release notes"
# Deploy tag to staging Hostinger folder
# Test...

# Production deployment (after staging validation)
git push origin main --tags
# Deploy tag to production Hostinger folder
```

## External APIs & Integrations

**Current Integrations:**
- **OpenWeatherMap API** (in `components/dashboard_widget.php`): Retrieves location temperature and weather condition for admin dashboard display
  - Uses geolocation via IP (ipinfo.io) to determine user location
  - Caches API response for 6 hours to minimize API calls (see `widget_cache.json`)
  - API key stored directly in component (`f42aaf95272e2b1942c4e5a7251231b5`) - **move to `.env` on production**
  - Graceful fallback: Shows "Unavailable" if API fails; continues displaying dashboard
  - Response structure: `main.temp`, `weather[0].main`, `weather[0].icon`

**Future Integrations (Not Yet Implemented):**
- Email notifications (for booking confirmations, document approvals, reminders)
- SMS verification (for phone number validation)
- Payment gateway (if monetization added)

**API Integration Pattern:**
```php
// Always use try-catch for external API calls
try {
    $response = file_get_contents($apiUrl, false, stream_context_create($options));
    if ($response === false) {
        throw new Exception('API request failed');
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid API response');
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    // Return fallback data or cached response
}
```

**For API Keys in Production:**
- Store in `.env` file (never in committed code): `OPENWEATHER_API_KEY=xxx`
- Load via: `$apiKey = $_ENV['OPENWEATHER_API_KEY'] ?? '';`
- Add `.htaccess` rule: `<Files ".env"> Deny from all </Files>`

## Key Files Quick Reference

| File | Purpose |
|------|---------|
| `actions/db.php` | Database connection, environment check |
| `includes/auth.php` | Guard function, session management, actor context |
| `includes/helpers.php` | Avatar URLs, image validation |
| `includes/log_helper.php` | Audit logging function |
| `includes/image_compression_helper.php` | Image resize/compress |
| `websocket_server.php` | Real-time chat/notifications server |
| `components/status_alert.php` | Error/success message display |
| `tailwind.config.js` | Styling configuration |
