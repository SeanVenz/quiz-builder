<?php
/**
 * Uninstall Quiz Builder Plugin
 * 
 * This file is executed when the plugin is deleted from WordPress admin.
 * It removes all plugin data including tables and options.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove database tables in correct order (respecting foreign key constraints)
global $wpdb;

// Step 1: Disable foreign key checks temporarily for MySQL
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
$wpdb->query("SET FOREIGN_KEY_CHECKS = 0");

// Step 2: Drop tables in dependency order (child tables first, parent tables last)
$tables_to_remove = array(
    // Child tables (have foreign keys pointing to other tables)
    $wpdb->prefix . 'qb_options',           // References qb_questions
    $wpdb->prefix . 'qb_quiz_settings',     // References qb_quizzes
    $wpdb->prefix . 'qb_attempts',          // References qb_quizzes
    $wpdb->prefix . 'qb_questions',         // References qb_quizzes and qb_categories
    // Parent tables (referenced by other tables)
    $wpdb->prefix . 'qb_categories',        // Referenced by qb_questions
    $wpdb->prefix . 'qb_quizzes'            // Referenced by multiple tables
);

foreach ($tables_to_remove as $table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Step 3: Re-enable foreign key checks
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching 
$wpdb->query("SET FOREIGN_KEY_CHECKS = 1");

// Remove plugin options
delete_option('qb_version');
delete_option('qb_onboarding_completed');
delete_option('qb_redirect_to_getting_started');

// Remove any transients
delete_transient('qb_quiz_cache');

// Clear any cached data
wp_cache_flush();
