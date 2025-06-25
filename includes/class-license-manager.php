<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * License Manager Class
 * Handles license validation and feature locking/unlocking
 */
class QB_License_Manager {
    
    private static $instance = null;
    private $api_url = 'http://localhost:3000/api'; 
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add hooks
        add_action('wp_ajax_qb_validate_license', array($this, 'ajax_validate_license'));
        add_action('wp_ajax_qb_deactivate_license', array($this, 'ajax_deactivate_license'));
    }
    
    /**
     * Validate license key with API
     */
    public function validate_license($license_key) {
        if (empty($license_key)) {
            return array(
                'success' => false,
                'message' => 'License key is required'
            );
        }        // Make API call to validate license
        $response = wp_remote_post($this->api_url . '/licenses/validate', array(
            'body' => json_encode(array(
                'licenseKey' => $license_key,
                'siteUrl' => home_url(),
                'siteName' => get_bloginfo('name'),
                'wpVersion' => get_bloginfo('version'),
                'phpVersion' => PHP_VERSION,
                'userAgent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                'ipAddress' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : ''
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Could not connect to license server: ' . $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) === 200 && isset($data['success']) && $data['success']) {
            // Save license data
            update_option('qb_license_key', $license_key);
            update_option('qb_license_status', 'active');
            update_option('qb_license_features', $data['data']['features']);
            update_option('qb_license_validated_at', current_time('mysql'));
            
            return array(
                'success' => true,
                'message' => 'License activated successfully!',
                'features' => $data['data']['features']
            );
        } else {
            return array(
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : 'Invalid license key'
            );
        }
    }
    
    /**
     * Deactivate license
     */
    public function deactivate_license() {
        delete_option('qb_license_key');
        delete_option('qb_license_status');
        delete_option('qb_license_features');
        delete_option('qb_license_validated_at');
        
        return array(
            'success' => true,
            'message' => 'License deactivated successfully!'
        );
    }
    
    /**
     * Check if a specific feature is unlocked
     */
    public function is_feature_unlocked($feature_name) {
        $license_status = get_option('qb_license_status', 'inactive');
        $license_features = get_option('qb_license_features', array());
        
        if ($license_status !== 'active') {
            return false;
        }
        
        return in_array($feature_name, $license_features);
    }
    
    /**
     * Get license status
     */
    public function get_license_status() {
        return array(
            'status' => get_option('qb_license_status', 'inactive'),
            'key' => get_option('qb_license_key', ''),
            'features' => get_option('qb_license_features', array()),
            'validated_at' => get_option('qb_license_validated_at', '')
        );
    }
    
    /**
     * AJAX handler for license validation
     */
    public function ajax_validate_license() {
        check_ajax_referer('qb_license_nonce', 'nonce');        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
        $result = $this->validate_license($license_key);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for license deactivation
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('qb_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $result = $this->deactivate_license();
        wp_send_json($result);
    }
    
    /**
     * Display premium feature notice
     */
    public static function premium_feature_notice($feature_name = '', $message = '') {
        if (empty($message)) {
            $message = 'This is a premium feature. Please activate your license to unlock it.';
        }
        
        $addon_url = admin_url('admin.php?page=qb-addons');
        
        echo '<div class="qb-premium-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 15px 0;">';
        echo '<div style="display: flex; align-items: center;">';
        echo '<span class="dashicons dashicons-lock" style="color: #856404; margin-right: 10px; font-size: 20px;"></span>';
        echo '<div>';
        echo '<strong style="color: #856404;">Premium Feature</strong><br>';
        echo '<span style="color: #856404;">' . esc_html($message) . '</span><br>';
        echo '<a href="' . esc_url($addon_url) . '" class="button button-primary" style="margin-top: 10px;">Activate License</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Simple wrapper to check if premium features are available
     */
    public static function is_premium_active() {
        return self::get_instance()->get_license_status()['status'] === 'active';
    }
}

// Initialize the license manager
QB_License_Manager::get_instance();
