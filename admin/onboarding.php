<?php
if (!defined('ABSPATH')) exit;

/**
 * Getting Started page - Always accessible onboarding
 */
function qb_getting_started_page() {
    // Get plugin URL
    $plugin_url = plugins_url('', dirname(__FILE__));
    
    // Enqueue CSS and JavaScript files
    wp_enqueue_style('qb-onboarding-css', $plugin_url . '/assets/css/onboarding.css', array(), '1.0.0');
    wp_enqueue_script('qb-onboarding-js', $plugin_url . '/assets/js/onboarding.js', array('jquery'), '1.0.0', true);
    
    // Localize script for AJAX
    wp_localize_script('qb-onboarding-js', 'qb_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'admin_url' => admin_url(),
        'nonce' => wp_create_nonce('qb_onboarding_quiz')
    ));
    
    // Load the enhanced onboarding template
    include_once QB_PATH . 'templates/onboarding.php';
}

/**
 * Legacy onboarding function for backward compatibility
 */
function qb_onboarding_page() {
    // Redirect to Getting Started page
    wp_redirect(admin_url('admin.php?page=qb-getting-started'));
    exit;
}
