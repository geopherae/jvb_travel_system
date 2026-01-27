<?php
/**
 * Feature Flags Configuration
 * 
 * This file controls which features are enabled/disabled in the system.
 * Useful for development, testing, and production deployments.
 * 
 * Usage: if (VISA_PROCESSING_ENABLED) { ... }
 */

// ============================================
// 🛂 VISA PROCESSING FEATURE
// ============================================
// Set to true to enable visa processing system with full functionality
// Set to false to show visa button as "Coming Soon" teaser (disabled, no link)
define('VISA_PROCESSING_ENABLED', true);
