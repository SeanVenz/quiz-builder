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

// Remove database tables
global $wpdb;

$tables_to_remove = array(
    $wpdb->prefix . 'qb_quizzes',
    $wpdb->prefix . 'qb_questions', 
    $wpdb->prefix . 'qb_quiz_attempts',
    $wpdb->prefix . 'qb_quiz_settings',
    $wpdb->prefix . 'qb_categories'
);

foreach ($tables_to_remove as $table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Remove plugin options
delete_option('qb_version');
delete_option('qb_onboarding_completed');
delete_option('qb_redirect_to_getting_started');

// Remove any transients
delete_transient('qb_quiz_cache');

// Clear any cached data
wp_cache_flush();
