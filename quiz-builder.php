<?php
/**
 * Plugin Name: Quiz Builder
 * Description: A lightweight and flexible quiz plugin with full control.
 * Version: 1.0.0
 * Author: Sean Venz Quijano
 * License: GPL2
 * Text Domain: quiz-builder
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('QB_PATH', plugin_dir_path(__FILE__));
define('QB_URL', plugin_dir_url(__FILE__));
define('QB_VERSION', '1.0.0');

// Include dependencies
require_once QB_PATH . 'includes/db-functions.php';
require_once QB_PATH . 'includes/ajax-handlers.php';
require_once QB_PATH . 'includes/shortcodes-frontend.php';
require_once QB_PATH . 'admin/quiz-admin-page.php';
require_once QB_PATH . 'admin/manage-questions-page.php';
require_once QB_PATH . 'admin/quiz-settings-page.php';
require_once QB_PATH . 'admin/categories-page.php';
require_once QB_PATH . 'templates/quiz-display.php';
require_once QB_PATH . 'templates/quiz-results.php';
// Include onboarding page
require_once QB_PATH . 'admin/onboarding.php';

// Register activation hook
register_activation_hook(__FILE__, 'qb_activate_plugin');

// Add update check
add_action('plugins_loaded', 'qb_check_for_updates');

/**
 * Check for plugin updates and run necessary updates
 */
function qb_check_for_updates() {
    $current_version = get_option('qb_version', '0');
    if (version_compare($current_version, QB_VERSION, '<')) {
        qb_create_database_tables();
        
        // Update settings table
        require_once plugin_dir_path(__FILE__) . 'includes/db/class-quiz-settings-db.php';
        $settings_db = new QB_Quiz_Settings_DB();
        $settings_db->update_table();
        
        // Create or update categories table
        require_once plugin_dir_path(__FILE__) . 'includes/db/class-categories-db.php';
        $categories_db = new QB_Categories_DB();
        $categories_db->create_table();
        
        update_option('qb_version', QB_VERSION);
    }
}

/**
 * Plugin activation function
 */
function qb_activate_plugin() {
    // Create base tables first
    qb_create_database_tables();
    
    // Create or update settings table
    require_once plugin_dir_path(__FILE__) . 'includes/db/class-quiz-settings-db.php';
    $settings_db = new QB_Quiz_Settings_DB();
    
    // Force table recreation
    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_quiz_settings';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Create fresh table
    $settings_db->update_table();
    
    // Create or update categories table
    require_once plugin_dir_path(__FILE__) . 'includes/db/class-categories-db.php';
    $categories_db = new QB_Categories_DB();
    $categories_db->create_table();
    
    // Set version
    update_option('qb_version', QB_VERSION);
    
    // Mark as fresh installation (user hasn't completed onboarding)
    update_option('qb_onboarding_completed', false);
    
    // Set redirect flag for fresh installation
    update_option('qb_redirect_to_getting_started', true);
    
    // Clear any cached data
    wp_cache_flush();
}

/**
 * Enqueue frontend scripts and styles
 */
function qb_enqueue_scripts() {
    wp_enqueue_style(
        'qb-results-styles',
        QB_URL . 'assets/css/quiz-results.css',
        array(),
        QB_VERSION
    );
}
add_action('wp_enqueue_scripts', 'qb_enqueue_scripts');

/**
 * Redirect to Getting Started on fresh installation
 */
add_action('admin_init', 'qb_check_fresh_installation_redirect');

function qb_check_fresh_installation_redirect() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $should_redirect = get_option('qb_redirect_to_getting_started', false);
    $onboarding_completed = get_option('qb_onboarding_completed', false);
    
    if ($should_redirect && !$onboarding_completed) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter check for admin page routing, not sensitive data processing
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($current_page !== 'qb-getting-started') {
            delete_option('qb_redirect_to_getting_started');
            wp_safe_redirect(admin_url('admin.php?page=qb-getting-started'));
            exit;
        }
    }
}
