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
// Set to true to enable visa processing system
// Set to false to disable and hide all visa-related features
define('VISA_PROCESSING_ENABLED', true);
