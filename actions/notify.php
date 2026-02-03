<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * Enhanced Notification System — notify.php
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * A comprehensive, scalable notification management system with:
 * • Rich templating engine with HTML support
 * • Priority levels and categories
 * • Rate limiting and spam prevention
 * • Delivery tracking and retry mechanisms
 * • Batch notification support
 * • User preference handling
 * • Comprehensive audit logging
 * 
 * @version 2.0.0
 * @since 2026-01-12
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    exit('Direct access denied.');
}

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/log_helper.php';

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * NotificationManager Class
 * ═══════════════════════════════════════════════════════════════════════════
 */
class NotificationManager {
    
    private $conn;
    private $rateLimitWindow = 300; // 5 minutes
    private $rateLimitMax = 50; // Max notifications per window
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * ┌─────────────────────────────────────────────────────────────────────┐
     * │ NOTIFICATION TEMPLATE REGISTRY                                      │
     * └─────────────────────────────────────────────────────────────────────┘
     */
    public function getTemplates(): array {
        return [
            // ═══════════════════════════════════════════════════════════════
            // DOCUMENT NOTIFICATIONS
            // ═══════════════════════════════════════════════════════════════
            'document_approved' => [
                'category'    => 'document',
                'title'       => 'Document Approved',
                'message'     => "Your <strong>{document_name}</strong> has been approved! You're one step closer to your journey.",
                'html'        => true,
                'icon'        => '✅',
                'color'       => 'green',
                'priority'    => 'normal',
                'action_url'  => '/client/documents',
                'action_text' => 'View Documents',
                'log_action'  => 'document_approved',
                'expires_days' => 30
            ],
            'document_rejected' => [
                'category'    => 'document',
                'title'       => 'Document Requires Attention',
                'message'     => "Your <strong>{document_name}</strong> needs to be resubmitted. Reason: {reason}",
                'html'        => true,
                'icon'        => '⚠️',
                'color'       => 'red',
                'priority'    => 'high',
                'action_url'  => '/client/documents',
                'action_text' => 'Resubmit Now',
                'log_action'  => 'document_rejected',
                'expires_days' => 7
            ],
            'document_pending_review' => [
                'category'    => 'document',
                'title'       => 'Document Under Review',
                'message'     => "We're reviewing your <strong>{document_name}</strong>. You'll be notified once it's processed.",
                'html'        => true,
                'icon'        => '🔍',
                'color'       => 'blue',
                'priority'    => 'low',
                'action_url'  => '/client/documents',
                'action_text' => 'Check Status',
                'log_action'  => 'document_submitted',
                'expires_days' => 14
            ],
            'document_uploaded_by_admin' => [
                'category'    => 'document',
                'title'       => 'Document Added to Your Account',
                'message'     => "A <strong>{document_type}</strong> titled <em>{document_name}</em> was added to your account by our team.",
                'html'        => true,
                'icon'        => '📄',
                'color'       => 'blue',
                'priority'    => 'normal',
                'action_url'  => '/client/client_dashboard.php',
                'action_text' => 'View Dashboard',
                'log_action'  => 'document_uploaded_by_admin',
                'expires_days' => 30
            ],
            
            // ═══════════════════════════════════════════════════════════════
            // CLIENT DOCUMENT UPLOADS (Admin Recipients)
            // ═══════════════════════════════════════════════════════════════
            'client_uploaded_document' => [
                'category'    => 'admin_alert',
                'title'       => 'New Document Uploaded',
                'message'     => "<strong>{client_name}</strong> uploaded <em>{document_name}</em> for package <strong>{package_name}</strong>.",
                'html'        => true,
                'icon'        => '📥',
                'color'       => 'purple',
                'priority'    => 'high',
                'action_url'  => "/admin/view_client.php?client_id={client_id}",
                'action_text' => 'Review Document',
                'log_action'  => 'client_document_uploaded',
                'expires_days' => 7
            ],
            
            // ═══════════════════════════════════════════════════════════════
            // BOOKING & PACKAGE NOTIFICATIONS
            // ═══════════════════════════════════════════════════════════════
            'booking_confirmed' => [
                'category'    => 'booking',
                'title'       => 'Booking Confirmed! 🎉',
                'message'     => "Congratulations! Your booking for <strong>{package_name}</strong> is confirmed. Your adventure begins {departure_date}!",
                'html'        => true,
                'icon'        => '🎉',
                'color'       => 'green',
                'priority'    => 'high',
                'action_url'  => '/client/view_booking.php?booking={booking_number}',
                'action_text' => 'View Booking',
                'log_action'  => 'booking_confirmed',
                'expires_days' => 90
            ],
            'booking_updated' => [
                'category'    => 'booking',
                'title'       => 'Booking Updated',
                'message'     => "Your booking details have been updated. Please refresh your dashboard to see the latest update.",
                'html'        => true,
                'icon'        => '🔄',
                'color'       => 'orange',
                'priority'    => 'high',
                'action_url'  => '/client/view_booking.php?booking={booking_number}',
                'action_text' => 'Review Changes',
                'log_action'  => 'booking_updated',
                'expires_days' => 30
            ],
            'package_assigned' => [
                'category'    => 'booking',
                'title'       => 'New Package Assigned',
                'message'     => "Exciting news! You've been assigned the <strong>{package_name}</strong> tour package. Check out your personalized itinerary!",
                'html'        => true,
                'icon'        => '🌍',
                'color'       => 'blue',
                'priority'    => 'high',
                'action_url'  => '/client/view_client_itinerary.php',
                'action_text' => 'View Itinerary',
                'log_action'  => 'package_assigned',
                'expires_days' => 60
            ],
            'package_reassigned' => [
                'category'    => 'booking',
                'title'       => 'Package Updated',
                'message'     => "Your tour package has been changed to <strong>{package_name}</strong>. Review your new itinerary and get ready for an amazing trip!",
                'html'        => true,
                'icon'        => '🔄',
                'color'       => 'orange',
                'priority'    => 'high',
                'action_url'  => '/client/view_client_itinerary.php',
                'action_text' => 'View New Itinerary',
                'log_action'  => 'package_reassigned',
                'expires_days' => 30
            ],
            'package_unassigned' => [
                'category'    => 'booking',
                'title'       => 'Package Unassigned',
                'message'     => "Your tour package assignment has been removed. Our team will contact you shortly to assign a new package.",
                'html'        => true,
                'icon'        => '🔓',
                'color'       => 'orange',
                'priority'    => 'high',
                'action_url'  => '/client/client_dashboard.php',
                'action_text' => 'View Dashboard',
                'log_action'  => 'package_unassigned',
                'expires_days' => 14
            ],

            // ═══════════════════════════════════════════════════════════════
            // VISA PACKAGE NOTIFICATIONS
            // ═══════════════════════════════════════════════════════════════
            'visa_package_assigned' => [
                'category'    => 'visa',
                'title'       => 'Visa Package Assigned',
                'message'     => "You have been assigned the <strong>{visa_package_name}</strong> visa package. Please review your visa requirements.",
                'html'        => true,
                'icon'        => '🛂',
                'color'       => 'blue',
                'priority'    => 'high',
                'action_url'  => '/client/client_visa_dashboard.php',
                'action_text' => 'View Visa Dashboard',
                'log_action'  => 'visa_package_assigned',
                'expires_days' => 60
            ],
            'visa_package_reassigned' => [
                'category'    => 'visa',
                'title'       => 'Visa Package Updated',
                'message'     => "Your visa package has been updated to <strong>{visa_package_name}</strong>. Please review your updated requirements.",
                'html'        => true,
                'icon'        => '🔄',
                'color'       => 'orange',
                'priority'    => 'high',
                'action_url'  => '/client/client_visa_dashboard.php',
                'action_text' => 'Review Requirements',
                'log_action'  => 'visa_package_reassigned',
                'expires_days' => 30
            ],
            
            // ═══════════════════════════════════════════════════════════════
            // PHOTO NOTIFICATIONS
            // ═══════════════════════════════════════════════════════════════
            'client_uploaded_photo' => [
                'category'    => 'media',
                'title'       => 'New Photo Uploaded',
                'message'     => "<strong>{client_name}</strong> shared a photo from Day {day} of <em>{package_name}</em>.",
                'html'        => true,
                'icon'        => '📸',
                'color'       => 'purple',
                'priority'    => 'normal',
                'action_url'  => '/admin/client-gallery.php?client_id={client_id}',
                'action_text' => 'View Gallery',
                'log_action'  => 'client_photo_uploaded',
                'expires_days' => 30
            ],
            'client_updated_photo' => [
                'category'    => 'media',
                'title'       => 'Photo Updated',
                'message'     => "<strong>{client_name}</strong> updated their photo for Day {day} in <em>{package_name}</em>.",
                'html'        => true,
                'icon'        => '✏️',
                'color'       => 'blue',
                'priority'    => 'low',
                'action_url'  => '/admin/client-gallery.php?client_id={client_id}',
                'action_text' => 'View Changes',
                'log_action'  => 'client_photo_updated',
                'expires_days' => 14
            ],
            'photo_approved' => [
                'category'    => 'media',
                'title'       => 'Photo Approved',
                'message'     => "Your photo from Day {day} has been approved and is now visible in your gallery!",
                'html'        => true,
                'icon'        => '✨',
                'color'       => 'green',
                'priority'    => 'low',
                'action_url'  => '/client/trip_photo_gallery.php',
                'action_text' => 'View Gallery',
                'log_action'  => 'photo_approved',
                'expires_days' => 30
            ],
            'photo_deleted' => [
                'category'    => 'media',
                'title'       => 'Photo Removed',
                'message'     => "One of your trip photos was removed by our team. If you have questions, please contact support.",
                'html'        => true,
                'icon'        => '🗑️',
                'color'       => 'red',
                'priority'    => 'normal',
                'action_url'  => '/client/trip_photo_gallery.php',
                'action_text' => 'View Gallery',
                'log_action'  => 'photo_deleted',
                'expires_days' => 14
            ],
            'photo_rejected' => [
                'category'    => 'media',
                'title'       => 'Photo Needs Attention',
                'message'     => "Your photo from Day {day} was not approved.{reason} Please review and resubmit.",
                'html'        => true,
                'icon'        => '📸',
                'color'       => 'red',
                'priority'    => 'normal',
                'action_url'  => '/client/trip_photo_gallery.php',
                'action_text' => 'Resubmit Photo',
                'log_action'  => 'photo_rejected',
                'expires_days' => 7
            ],
            
            // ═══════════════════════════════════════════════════════════════
            // TRIP STATUS NOTIFICATIONS
            // ═══════════════════════════════════════════════════════════════
            'trip_starting_soon' => [
                'category'    => 'trip_status',
                'title'       => 'Your Adventure Starts Soon! 🎒',
                'message'     => "Your trip to <strong>{destination}</strong> begins in {days_remaining} days! Make sure all your documents are in order.",
                'html'        => true,
                'icon'        => '🎒',
                'color'       => 'blue',
                'priority'    => 'high',
                'action_url'  => '/client/client_dashboard.php',
                'action_text' => 'Prepare for Trip',
                'log_action'  => 'trip_reminder_sent',
                'expires_days' => 7
            ],
            'trip_ongoing' => [
                'category'    => 'trip_status',
                'title'       => 'Have an Amazing Trip! 🌟',
                'message'     => "Your trip has officially started! Don't forget to share your photos and experiences with us.",
                'html'        => true,
                'icon'        => '✈️',
                'color'       => 'green',
                'priority'    => 'normal',
                'action_url'  => '/client/trip_photo_gallery.php',
                'action_text' => 'Upload Photos',
                'log_action'  => 'trip_status_ongoing',
                'expires_days' => 30
            ],
            'trip_completed' => [
                'category'    => 'trip_status',
                'title'       => 'Welcome Back! 🏡',
                'message'     => "We hope you had an incredible journey! Please share your feedback and final photos to help us serve you better.",
                'html'        => true,
                'icon'        => '🏡',
                'color'       => 'purple',
                'priority'    => 'high',
                'action_url'  => '/client/client_dashboard.php',
                'action_text' => 'Share Feedback',
                'log_action'  => 'trip_status_completed',
                'expires_days' => 14
            ],
            
            // ═══════════════════════════════════════════════════════════════
            // CHECKLIST NOTIFICATIONS
            // ═══════════════════════════════════════════════════════════════
            'checklist_item_completed' => [
                'category'    => 'checklist',
                'title'       => 'Checklist Item Completed! ✓',
                'message'     => "Great progress! You've completed <strong>{item_name}</strong>. {remaining_items} items remaining.",
                'html'        => true,
                'icon'        => '✓',
                'color'       => 'green',
                'priority'    => 'low',
                'action_url'  => '/client/client_dashboard.php',
                'action_text' => 'View Checklist',
                'log_action'  => 'checklist_item_completed',
                'expires_days' => 7
            ],
            'checklist_all_completed' => [
                'category'    => 'checklist',
                'title'       => 'All Set! Checklist Complete 🎊',
                'message'     => "Congratulations! You've completed all required items. You're fully prepared for your adventure!",
                'html'        => true,
                'icon'        => '🎊',
                'color'       => 'green',
                'priority'    => 'high',
                'action_url'  => '/client/client_dashboard.php',
                'action_text' => 'View Dashboard',
                'log_action'  => 'checklist_all_completed',
                'expires_days' => 14
            ],
            
            // ═══════════════════════════════════════════════════════════════
            // SYSTEM NOTIFICATIONS
            // ═══════════════════════════════════════════════════════════════
            'account_created' => [
                'category'    => 'system',
                'title'       => 'Welcome to JVB Travel! 👋',
                'message'     => "Your account has been created successfully. Complete your profile to start planning your dream vacation!",
                'html'        => true,
                'icon'        => '👋',
                'color'       => 'blue',
                'priority'    => 'high',
                'action_url'  => '/client/client_dashboard.php',
                'action_text' => 'Get Started',
                'log_action'  => 'account_created',
                'expires_days' => 30
            ],
            'password_changed' => [
                'category'    => 'security',
                'title'       => 'Password Updated',
                'message'     => "Your password was changed successfully. If you didn't make this change, please contact support immediately.",
                'html'        => true,
                'icon'        => '🔒',
                'color'       => 'orange',
                'priority'    => 'high',
                'action_url'  => '/client/settings.php',
                'action_text' => 'Security Settings',
                'log_action'  => 'password_changed',
                'expires_days' => 7
            ],
            'profile_updated' => [
                'category'    => 'system',
                'title'       => 'Profile Updated',
                'message'     => "Your profile information has been updated successfully.",
                'html'        => true,
                'icon'        => '👤',
                'color'       => 'blue',
                'priority'    => 'low',
                'action_url'  => '/client/client_dashboard.php',
                'action_text' => 'View Profile',
                'log_action'  => 'profile_updated',
                'expires_days' => 7
            ],
            'client_archived' => [
                'category'    => 'system',
                'title'       => 'Account Archived',
                'message'     => "Your account has been archived. If you believe this is an error, please contact support.",
                'html'        => true,
                'icon'        => '📦',
                'color'       => 'gray',
                'priority'    => 'high',
                'action_url'  => '/client/client_dashboard.php',
                'action_text' => 'Contact Support',
                'log_action'  => 'client_archived',
                'expires_days' => 90
            ],
            
            // ═══════════════════════════════════════════════════════════════
            // ADMIN-SPECIFIC NOTIFICATIONS
            // ═══════════════════════════════════════════════════════════════
            'new_client_registered' => [
                'category'    => 'admin_alert',
                'title'       => 'New Client Registration',
                'message'     => "<strong>{client_name}</strong> ({email}) has registered and needs package assignment.",
                'html'        => true,
                'icon'        => '👤',
                'color'       => 'blue',
                'priority'    => 'high',
                'action_url'  => '/admin/view_client.php?client_id={client_id}',
                'action_text' => 'Assign Package',
                'log_action'  => 'new_client_registered',
                'expires_days' => 7
            ],
            'client_needs_review' => [
                'category'    => 'admin_alert',
                'title'       => 'Client Requires Review',
                'message'     => "<strong>{client_name}</strong>'s documents are ready for review. Status: {status}",
                'html'        => true,
                'icon'        => '📋',
                'color'       => 'orange',
                'priority'    => 'high',
                'action_url'  => '/admin/view_client.php?client_id={client_id}',
                'action_text' => 'Review Now',
                'log_action'  => 'client_review_required',
                'expires_days' => 3
            ],
            'low_inventory_alert' => [
                'category'    => 'admin_alert',
                'title'       => 'Low Package Availability',
                'message'     => "Package <strong>{package_name}</strong> has only {available_slots} slots remaining for {departure_date}.",
                'html'        => true,
                'icon'        => '⚠️',
                'color'       => 'red',
                'priority'    => 'urgent',
                'action_url'  => '/admin/tour_packages.php',
                'action_text' => 'Manage Packages',
                'log_action'  => 'inventory_alert',
                'expires_days' => 7
            ],
            'tour_package_created' => [
                'category'    => 'admin_alert',
                'title'       => 'New Tour Package Created',
                'message'     => "A new tour package <strong>{package_name}</strong> ({origin} → {destination}) has been created. Price: <strong>₱{price}</strong> | Duration: <strong>{day_duration}D/{night_duration}N</strong>",
                'html'        => true,
                'icon'        => '✨',
                'color'       => 'green',
                'priority'    => 'normal',
                'action_url'  => '/admin/admin_tour_packages.php',
                'action_text' => 'View Package',
                'log_action'  => 'tour_package_created',
                'expires_days' => 30
            ],
            'new_client_added' => [
                'category'    => 'admin_alert',
                'title'       => 'New Client Added',
                'message'     => "<strong>{client_name}</strong> ({email}) has been added to the system. Assigned to: <strong>{assigned_admin}</strong> | Package: <strong>{package_name}</strong>",
                'html'        => true,
                'icon'        => '👤',
                'color'       => 'blue',
                'priority'    => 'normal',
                'action_url'  => '/admin/view_client.php?client_id={client_id}',
                'action_text' => 'View Client',
                'log_action'  => 'client_added',
                'expires_days' => 14
            ],
            'new_visa_client_added' => [
                'category'    => 'admin_alert',
                'title'       => 'New Visa Client Added',
                'message'     => "<strong>{client_name}</strong> ({email}) has been added for visa processing. Assigned to: <strong>{assigned_admin}</strong> | Package: <strong>{visa_package}</strong> | Type: <strong>{processing_type}</strong>",
                'html'        => true,
                'icon'        => '🛂',
                'color'       => 'blue',
                'priority'    => 'normal',
                'action_url'  => '/admin/admin_visa_dashboard.php',
                'action_text' => 'View Visa Client',
                'log_action'  => 'visa_client_added',
                'expires_days' => 14
            ],
            'new_visa_group_added' => [
                'category'    => 'admin_alert',
                'title'       => 'New Visa Group Application',
                'message'     => "Group visa application received: <strong>{lead_guest}</strong> + <strong>{companion_count}</strong> companion(s). Assigned to: <strong>{assigned_admin}</strong> | Package: <strong>{visa_package}</strong> | Type: <strong>{processing_type}</strong>",
                'html'        => true,
                'icon'        => '👥',
                'color'       => 'purple',
                'priority'    => 'high',
                'action_url'  => '/admin/admin_visa_dashboard.php',
                'action_text' => 'View Group',
                'log_action'  => 'visa_group_added',
                'expires_days' => 14
            ]
        ];
    }
    
    /**
     * ┌─────────────────────────────────────────────────────────────────────┐
     * │ MESSAGE INTERPOLATION WITH ESCAPING                                │
     * └─────────────────────────────────────────────────────────────────────┘
     */
    private function interpolate(string $template, array $context, bool $isHtml = false): string {
        foreach ($context as $key => $value) {
            $escapedValue = $isHtml ? $value : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $template = str_replace("{" . $key . "}", $escapedValue, $template);
        }
        // Clean up any remaining unreplaced placeholders
        $template = preg_replace('/\{[a-z_]+\}/', '', $template);
        return $template;
    }
    
    /**
     * ┌─────────────────────────────────────────────────────────────────────┐
     * │ RATE LIMITING CHECK                                                 │
     * └─────────────────────────────────────────────────────────────────────┘
     */
    private function checkRateLimit(string $recipientType, int $recipientId, string $eventType): bool {
        $cutoff = date('Y-m-d H:i:s', time() - $this->rateLimitWindow);
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE recipient_type = ? 
            AND recipient_id = ? 
            AND event_type = ?
            AND created_at > ?
        ");
        
        if (!$stmt) {
            error_log("[NotificationManager] Rate limit check failed: " . $this->conn->error);
            return true; // Allow on error to prevent blocking
        }
        
        $stmt->bind_param("siss", $recipientType, $recipientId, $eventType, $cutoff);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] >= $this->rateLimitMax) {
            error_log("[NotificationManager] Rate limit exceeded for {$recipientType} ID {$recipientId}, event: {$eventType}");
            return false;
        }
        
        return true;
    }
    
    /**
     * ┌─────────────────────────────────────────────────────────────────────┐
     * │ MAIN NOTIFICATION DISPATCHER                                        │
     * └─────────────────────────────────────────────────────────────────────┘
     * 
     * @param array $params {
     *     @type string $recipient_type  'admin' or 'client'
     *     @type int    $recipient_id    ID of recipient
     *     @type string $event           Event type (must match template key)
     *     @type array  $context         Variables for interpolation
     *     @type string $expires_at      Optional: ISO datetime for expiry
     *     @type string $priority        Optional: override template priority
     *     @type bool   $skip_rate_limit Optional: bypass rate limiting
     * }
     * 
     * @return array ['success' => bool, 'notification_id' => int|null, 'error' => string|null]
     */
    public function send(array $params): array {
        $recipientType = $params['recipient_type'] ?? null;
        $recipientId   = $params['recipient_id'] ?? null;
        $eventType     = $params['event'] ?? null;
        $context       = $params['context'] ?? [];
        $expiresAt     = $params['expires_at'] ?? null;
        $skipRateLimit = $params['skip_rate_limit'] ?? false;
        
        // Validation
        if (!$recipientType || !$recipientId || !$eventType) {
            $error = "Missing required fields: recipient_type, recipient_id, or event";
            error_log("[NotificationManager] {$error}");
            return ['success' => false, 'error' => $error, 'notification_id' => null];
        }
        
        if (!in_array($recipientType, ['admin', 'client'])) {
            $error = "Invalid recipient_type: {$recipientType}";
            error_log("[NotificationManager] {$error}");
            return ['success' => false, 'error' => $error, 'notification_id' => null];
        }
        
        // Get template
        $templates = $this->getTemplates();
        $template  = $templates[$eventType] ?? null;
        
        if (!$template) {
            $error = "Unknown event type: {$eventType}";
            error_log("[NotificationManager] {$error}");
            return ['success' => false, 'error' => $error, 'notification_id' => null];
        }
        
        // Rate limiting
        if (!$skipRateLimit && !$this->checkRateLimit($recipientType, $recipientId, $eventType)) {
            return ['success' => false, 'error' => 'Rate limit exceeded', 'notification_id' => null];
        }
        
        // Build notification
        $isHtml    = $template['html'] ?? false;
        $title     = $this->interpolate($template['title'] ?? 'Notification', $context, $isHtml);
        $message   = $this->interpolate($template['message'], $context, $isHtml);
        $actionUrl = $this->interpolate($template['action_url'] ?? '', $context, false);
        $actionText = $this->interpolate($template['action_text'] ?? 'View', $context, false);
        $icon      = $template['icon'] ?? '🔔';
        $color     = $template['color'] ?? 'blue';
        $priority  = $params['priority'] ?? $template['priority'] ?? 'normal';
        $category  = $template['category'] ?? 'general';
        
        // Calculate expiry
        if (!$expiresAt && isset($template['expires_days'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$template['expires_days']} days"));
        }
        
        // Build metadata
        $metadata = array_merge($context, [
            'title'       => $title,
            'action_text' => $actionText,
            'color'       => $color,
            'category'    => $category,
            'is_html'     => $isHtml,
            'sent_at'     => date('Y-m-d H:i:s')
        ]);
        $metaJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Insert notification
        $stmt = $this->conn->prepare("
            INSERT INTO notifications (
                recipient_type, recipient_id, event_type, message,
                action_url, icon, priority, status, dismissed,
                created_at, metadata_json, expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'unread', 0, NOW(), ?, ?)
        ");
        
        if (!$stmt) {
            $error = "Failed to prepare statement: " . $this->conn->error;
            error_log("[NotificationManager] {$error}");
            return ['success' => false, 'error' => $error, 'notification_id' => null];
        }
        
        $stmt->bind_param(
            "sisssssss",
            $recipientType,
            $recipientId,
            $eventType,
            $message,
            $actionUrl,
            $icon,
            $priority,
            $metaJson,
            $expiresAt
        );
        
        if (!$stmt->execute()) {
            $error = "Insert failed: " . $stmt->error;
            error_log("[NotificationManager] {$error}");
            return ['success' => false, 'error' => $error, 'notification_id' => null];
        }
        
        $notificationId = $stmt->insert_id;
        
        error_log("[NotificationManager] ✓ Notification #{$notificationId} sent to {$recipientType} ID {$recipientId} (event: {$eventType})");
        
        // Audit logging
        if (function_exists('\LogHelper\logClientOnboardingAudit') && isset($context['client_id'])) {
            $actor = $this->getActorContext();
            \LogHelper\logClientOnboardingAudit(
                $this->conn,
                [
                    'actor_id' => $actor['id'] ?? 0,
                    'client_id' => $context['client_id'],
                    'payload' => [
                        'action' => $template['log_action'] ?? $eventType,
                        'notification_id' => $notificationId,
                        'event' => $eventType,
                        'message' => $message
                    ]
                ]
            );
        }
        
        return ['success' => true, 'notification_id' => $notificationId, 'error' => null];
    }
    
    /**
     * ┌─────────────────────────────────────────────────────────────────────┐
     * │ BATCH NOTIFICATION SENDER                                           │
     * └─────────────────────────────────────────────────────────────────────┘
     */
    public function sendBatch(array $notifications): array {
        $results = [
            'total'     => count($notifications),
            'success'   => 0,
            'failed'    => 0,
            'errors'    => []
        ];
        
        foreach ($notifications as $idx => $notification) {
            $result = $this->send($notification);
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Notification #{$idx}: " . ($result['error'] ?? 'Unknown error');
            }
        }
        
        error_log("[NotificationManager] Batch sent: {$results['success']}/{$results['total']} successful");
        return $results;
    }
    
    /**
     * ┌─────────────────────────────────────────────────────────────────────┐
     * │ BROADCAST TO ALL ADMINS                                             │
     * └─────────────────────────────────────────────────────────────────────┘
     */
    public function broadcastToAdmins(string $eventType, array $context, ?string $roleFilter = null): array {
        $roleClause = $roleFilter ? "AND role = ?" : "";
        $stmt = $this->conn->prepare("
            SELECT id FROM admin_accounts 
            WHERE is_active = 1 {$roleClause}
        ");
        
        if (!$stmt) {
            error_log("[NotificationManager] Failed to query admin_accounts: " . $this->conn->error);
            return ['success' => false, 'count' => 0];
        }
        
        if ($roleFilter) {
            $stmt->bind_param("s", $roleFilter);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("[NotificationManager] No active admins found for broadcast");
            return ['success' => true, 'count' => 0];
        }
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'recipient_type' => 'admin',
                'recipient_id'   => (int) $row['id'],
                'event'          => $eventType,
                'context'        => $context,
                'skip_rate_limit' => true
            ];
        }
        
        $batchResult = $this->sendBatch($notifications);
        
        return [
            'success' => $batchResult['failed'] === 0,
            'count'   => $batchResult['success']
        ];
    }
    
    /**
     * ┌─────────────────────────────────────────────────────────────────────┐
     * │ GET ACTOR CONTEXT (for audit logging)                               │
     * └─────────────────────────────────────────────────────────────────────┘
     */
    private function getActorContext(): array {
        if (function_exists('\Auth\getActorContext')) {
            return \Auth\getActorContext();
        }
        
        return [
            'id'         => $_SESSION['admin']['id'] ?? $_SESSION['client_id'] ?? null,
            'role'       => $_SESSION['admin']['role'] ?? 'client',
            'session_id' => session_id(),
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
    }
    
    /**
     * ┌─────────────────────────────────────────────────────────────────────┐
     * │ CLEANUP EXPIRED NOTIFICATIONS                                       │
     * └─────────────────────────────────────────────────────────────────────┘
     */
    public function cleanupExpired(): int {
        $stmt = $this->conn->prepare("
            DELETE FROM notifications 
            WHERE expires_at IS NOT NULL 
            AND expires_at < NOW()
        ");
        
        if (!$stmt || !$stmt->execute()) {
            error_log("[NotificationManager] Failed to cleanup expired notifications: " . $this->conn->error);
            return 0;
        }
        
        $deleted = $stmt->affected_rows;
        error_log("[NotificationManager] Cleaned up {$deleted} expired notifications");
        return $deleted;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * GLOBAL HELPER FUNCTIONS (Backward Compatibility)
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * Send a single notification (legacy compatibility wrapper)
 */
function notify(array $params): array {
    global $conn;
    $manager = new NotificationManager($conn);
    return $manager->send($params);
}

/**
 * Broadcast to all admins (legacy compatibility wrapper)
 */
function notifyAllAdmins(string $eventType, array $context, ?string $roleFilter = null): void {
    global $conn;
    $manager = new NotificationManager($conn);
    $manager->broadcastToAdmins($eventType, $context, $roleFilter);
}

/**
 * Create NotificationManager instance
 */
function getNotificationManager(): NotificationManager {
    global $conn;
    return new NotificationManager($conn);
}

