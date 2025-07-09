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

// Register deactivation hook
register_deactivation_hook(__FILE__, 'qb_deactivate_plugin');

// Add update check
add_action('plugins_loaded', 'qb_check_for_updates');

/**
 * Check for plugin updates and run necessary updates - gentler approach
 */
function qb_check_for_updates() {
    $current_version = get_option('qb_version', '0');
    if (version_compare($current_version, QB_VERSION, '<')) {
        qb_create_database_tables();
        
        // Update settings table - gentler approach
        require_once plugin_dir_path(__FILE__) . 'includes/db/class-quiz-settings-db.php';
        $settings_db = new QB_Quiz_Settings_DB();
        if (!$settings_db->table_exists()) {
            $settings_db->update_table();
        }
        
        // Create or update categories table - gentler approach
        require_once plugin_dir_path(__FILE__) . 'includes/db/class-categories-db.php';
        $categories_db = new QB_Categories_DB();
        if (!$categories_db->table_exists()) {
            $categories_db->create_table();
        }
        
        update_option('qb_version', QB_VERSION);
    }
}

/**
 * Plugin activation function - gentler database handling
 */
function qb_activate_plugin() {
    // Create base tables if they don't exist
    qb_create_database_tables();
    
    // Create or update settings table - gentler approach
    require_once plugin_dir_path(__FILE__) . 'includes/db/class-quiz-settings-db.php';
    $settings_db = new QB_Quiz_Settings_DB();
    
    // Only create table if it doesn't exist (no forced recreation)
    $settings_db->update_table();
    
    // Create or update categories table - gentler approach
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
    
    // Flush rewrite rules to ensure custom URLs work
    // This is crucial for quiz-results URLs to work properly
    flush_rewrite_rules();
}

/**
 * Plugin deactivation function
 */
function qb_deactivate_plugin() {
    // Flush rewrite rules to clean up custom URLs
    flush_rewrite_rules();
    
    // Clear any scheduled events
    wp_clear_scheduled_hook('qb_cleanup_temp_data');
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

/**
 * Add admin notice for permalink flushing if needed
 */
add_action('admin_notices', 'qb_check_permalink_flush_notice');

function qb_check_permalink_flush_notice() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if we're on a Quiz Builder page
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if (strpos($current_page, 'qb-') !== 0 && strpos($current_page, 'quiz-') !== 0) {
        return;
    }
    
    // Check if rewrite rules are properly set
    $rules = get_option('rewrite_rules');
    if (!$rules || !isset($rules['^quiz-results/([^/]+)/?$'])) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Quiz Builder:</strong> If quiz results are not displaying correctly, please ';
        echo '<a href="' . esc_url(admin_url('options-permalink.php')) . '">refresh your permalinks</a> ';
        echo 'by going to Settings > Permalinks and clicking "Save Changes".</p>';
        echo '</div>';
    }
}

/**
 * Add a manual flush function for troubleshooting
 */
add_action('wp_ajax_qb_flush_rewrite_rules', 'qb_manual_flush_rewrite_rules');

function qb_manual_flush_rewrite_rules() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'qb_flush_rules')) {
        wp_die('Invalid nonce');
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    wp_send_json_success('Rewrite rules flushed successfully!');
}
