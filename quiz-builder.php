<?php
/**
 * Plugin Name: Quiz Builder
 * Description: A lightweight and flexible quiz plugin with full control.
 * Version: 1.0.0
 * Author: Sean Venz Quijano
 * License: GPL2
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
require_once QB_PATH . 'admin/quiz-admin-page.php';
require_once QB_PATH . 'admin/manage-questions-page.php';
require_once QB_PATH . 'admin/quiz-settings-page.php';
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
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Create fresh table
    $settings_db->update_table();
    
    // Set version
    update_option('qb_version', QB_VERSION);
    
    // Clear any cached data
    wp_cache_flush();
}

/**
 * Display quiz shortcode handler
 */
function qb_display_quiz($atts) {
    global $wpdb;

    $atts = shortcode_atts([
        'quiz_id' => 0,
    ], $atts, 'quiz_builder');

    $quiz_id = intval($atts['quiz_id']);
    if (!$quiz_id) {
        return '<div class="error"><p>Quiz ID is required!</p></div>';
    }

    $quiz_table = $wpdb->prefix . 'qb_quizzes';
    $questions_table = $wpdb->prefix . 'qb_questions';
    $options_table = $wpdb->prefix . 'qb_options';
    $settings_table = $wpdb->prefix . 'qb_quiz_settings';

    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $quiz_id));
    if (!$quiz) {
        return '<div class="error"><p>Quiz not found!</p></div>';
    }

    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE quiz_id = %d ORDER BY `order` ASC, id ASC", $quiz_id));
    if (!$questions) {
        return '<div class="error"><p>No questions available for this quiz.</p></div>';
    }

    $options = $wpdb->get_results($wpdb->prepare("SELECT * FROM $options_table WHERE question_id IN (SELECT id FROM $questions_table WHERE quiz_id = %d)", $quiz_id));

    // Get quiz settings
    $settings = $wpdb->get_row($wpdb->prepare("SELECT * FROM $settings_table WHERE quiz_id = %d", $quiz_id));
    if (!$settings) {
        $settings = (object) array(
            'is_paginated' => 0,
            'questions_per_page' => 1
        );
    }

    return qb_get_quiz_display($quiz, $questions, $options, $settings);
}

function qb_generate_random_id() {
    return bin2hex(random_bytes(16));
}

// Register the action for both logged-in and non-logged-in users
add_action('template_redirect', function() {
    if (isset($_POST['action']) && $_POST['action'] === 'qb_handle_quiz_submission') {
        qb_handle_quiz_submission();
    }
});

function qb_handle_quiz_submission() {
    // Debug logging
    error_log('Quiz submission handler started');
    error_log('POST data: ' . print_r($_POST, true));

    // Verify nonce
    if (!isset($_POST['qb_quiz_nonce']) || !wp_verify_nonce($_POST['qb_quiz_nonce'], 'qb_quiz_submission')) {
        error_log('Invalid nonce');
        if (wp_doing_ajax()) {
            wp_send_json_error('Invalid nonce');
        }
        return;
    }

    if (!isset($_POST['quiz_id'])) {
        error_log('Missing required POST data: quiz_id');
        if (wp_doing_ajax()) {
            wp_send_json_error('Missing quiz ID');
        }
        return;
    }

    global $wpdb;
    $quiz_id = intval($_POST['quiz_id']);
    $quiz_table = $wpdb->prefix . 'qb_quizzes';
    $questions_table = $wpdb->prefix . 'qb_questions';
    $options_table = $wpdb->prefix . 'qb_options';
    $attempts_table = $wpdb->prefix . 'qb_attempts';

    error_log('Processing quiz ID: ' . $quiz_id);

    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id = %d", $quiz_id));
    if (!$quiz) {
        error_log('Quiz not found with ID: ' . $quiz_id);
        if (wp_doing_ajax()) {
            wp_send_json_error('Quiz not found');
        }
        return;
    }

    $score = 0;
    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $questions_table WHERE quiz_id = %d", $quiz_id));
    $answers = array();

    foreach ($questions as $question) {
        $selected_option_id = isset($_POST['question_' . $question->id]) ? intval($_POST['question_' . $question->id]) : 0;
        if ($selected_option_id) {
            $selected_option = $wpdb->get_row($wpdb->prepare("SELECT * FROM $options_table WHERE id = %d", $selected_option_id));
            if ($selected_option) {
                $score += $selected_option->points;
                $answers[$question->id] = array(
                    'question_id' => $question->id,
                    'option_id' => $selected_option_id,
                    'points' => $selected_option->points
                );
            }
        }
    }

    // Get total possible points
    $total_possible_points = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(max_points) FROM (
            SELECT MAX(points) as max_points 
            FROM $options_table o 
            JOIN $questions_table q ON o.question_id = q.id 
            WHERE q.quiz_id = %d 
            GROUP BY q.id
        ) as question_max_points",
        $quiz_id
    ));

    // Generate random ID
    $random_id = qb_generate_random_id();
    error_log('Generated random ID: ' . $random_id);

    // Get user ID, set to NULL if not logged in
    $user_id = get_current_user_id();
    if (!$user_id) {
        $user_id = null;
    }

    // Store the attempt
    $insert_result = $wpdb->insert($attempts_table, array(
        'random_id' => $random_id,
        'quiz_id' => $quiz_id,
        'user_id' => $user_id,
        'score' => $score,
        'total_points' => $total_possible_points,
        'answers' => json_encode($answers),
        'created_at' => current_time('mysql')
    ));

    if ($insert_result === false) {
        error_log('Failed to insert quiz attempt: ' . $wpdb->last_error);
        if (wp_doing_ajax()) {
            wp_send_json_error('Failed to save quiz attempt');
        }
        return;
    }

    error_log('Successfully stored quiz attempt with random ID: ' . $random_id);

    // Get the results page URL
    $results_page = get_page_by_path('quiz-results');
    if ($results_page) {
        $redirect_url = get_permalink($results_page) . $random_id . '/';
    } else {
        // Fallback to home URL if page not found
        $redirect_url = home_url('/quiz-results/' . $random_id . '/');
    }

    error_log('Redirecting to: ' . $redirect_url);
    
    if (wp_doing_ajax()) {
        wp_send_json_success(array('redirect_url' => $redirect_url));
    } else {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Ensure no headers are sent
        if (!headers_sent()) {
            wp_safe_redirect($redirect_url);
            exit;
        } else {
            error_log('Headers already sent, using JavaScript redirect');
            echo '<script>window.location.href="' . esc_url($redirect_url) . '";</script>';
            exit;
        }
    }
}

/**
 * Display quiz results
 */
function qb_display_quiz_results() {
    global $wpdb;
    $attempts_table = $wpdb->prefix . 'qb_attempts';
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    
    // Get the random ID from the URL
    $random_id = get_query_var('quiz_result_id');
    if (!$random_id) {
        return '<div class="error"><p>No quiz results to display.</p></div>';
    }

    // Get the attempt using the random ID
    $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM $attempts_table WHERE random_id = %s", $random_id));
    if (!$attempt) {
        return '<div class="error"><p>Quiz results not found.</p></div>';
    }    // Get the quiz
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quizzes_table WHERE id = %d", $attempt->quiz_id));
    if (!$quiz) {
        return '<div class="error"><p>Quiz not found.</p></div>';
    }

    // Check if detailed results are enabled
    require_once QB_PATH . 'includes/db/class-quiz-settings-db.php';
    $settings_db = new QB_Quiz_Settings_DB();
    $settings = $settings_db->get_settings($attempt->quiz_id);
    
    // Use detailed results if enabled, otherwise use basic template
    if ($settings && $settings->show_user_answers) {
        // Use the detailed results display class
        require_once QB_PATH . 'includes/results/class-quiz-results-display.php';
        $results_display = new QB_Quiz_Results_Display();
        return $results_display->display_results($attempt->id);
    } else {
        // Use basic template output
        $score = $attempt->score;
        $total_possible_points = $attempt->total_points;
        ob_start();
        include QB_PATH . 'templates/quiz-results-html.php';
        return ob_get_clean();
    }
}

// Register shortcodes
function qb_register_shortcodes() {
    add_shortcode('quiz_builder', 'qb_display_quiz');
    add_shortcode('quiz_results', 'qb_display_quiz_results');
}
add_action('init', 'qb_register_shortcodes', 10);

// Add form submission handler with higher priority
add_action('template_redirect', 'qb_handle_quiz_submission', 1);

// Register the query var
function qb_register_query_vars($vars) {
    $vars[] = 'quiz_result_id';
    return $vars;
}
add_filter('query_vars', 'qb_register_query_vars');

// Add AJAX handler for attempt details
add_action('wp_ajax_qb_get_attempt_details', 'qb_get_attempt_details');
function qb_get_attempt_details() {
    check_ajax_referer('qb_attempt_details', 'nonce');

    global $wpdb;
    $attempt_id = sanitize_text_field($_POST['attempt_id']);
    $attempts_table = $wpdb->prefix . 'qb_attempts';
    $questions_table = $wpdb->prefix . 'qb_questions';
    $options_table = $wpdb->prefix . 'qb_options';
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $users_table = $wpdb->users;

    $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM $attempts_table WHERE random_id = %s", $attempt_id));
    if (!$attempt) {
        wp_send_json_error('Attempt not found');
    }

    // Get quiz and user details
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quizzes_table WHERE id = %d", $attempt->quiz_id));
    $user = $attempt->user_id ? $wpdb->get_row($wpdb->prepare("SELECT display_name FROM $users_table WHERE ID = %d", $attempt->user_id)) : null;

    $answers = json_decode($attempt->answers, true);

    // Use output buffering to capture template output
    ob_start();
    include QB_PATH . 'templates/attempt-details.php';
    $output = ob_get_clean();

    wp_send_json_success(array('html' => $output));
}

// Add rewrite rules for the new URL structure
function qb_add_rewrite_rules() {
    add_rewrite_rule(
        '^quiz-results/([^/]+)/?$',
        'index.php?pagename=quiz-results&quiz_result_id=$matches[1]',
        'top'
    );
}
add_action('init', 'qb_add_rewrite_rules');

// Add AJAX handler for CSV export
add_action('wp_ajax_qb_export_attempts_csv', 'qb_export_attempts_csv');
function qb_export_attempts_csv() {
    check_ajax_referer('qb_export_csv', 'nonce');

    global $wpdb;
    $attempts_table = $wpdb->prefix . 'qb_attempts';
    $quizzes_table = $wpdb->prefix . 'qb_quizzes';
    $users_table = $wpdb->users;

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="quiz-attempts.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, array('Attempt ID', 'Quiz Title', 'User', 'Score', 'Total Points', 'Percentage', 'Date'));
    
    $attempts = $wpdb->get_results("
        SELECT a.*, q.title as quiz_title, u.display_name as user_name 
        FROM $attempts_table a
        LEFT JOIN $quizzes_table q ON a.quiz_id = q.id
        LEFT JOIN $users_table u ON a.user_id = u.ID
        ORDER BY a.created_at DESC
    ");
    
    foreach ($attempts as $attempt) {
        $percentage = round(($attempt->score / $attempt->total_points) * 100);
        fputcsv($output, array(
            $attempt->random_id,
            $attempt->quiz_title,
            $attempt->user_name ?: 'Guest',
            $attempt->score,
            $attempt->total_points,
            $percentage . '%',
            $attempt->created_at
        ));
    }
    
    fclose($output);
    exit;
}

// Add AJAX handler for getting quiz settings
add_action('wp_ajax_qb_get_quiz_settings', 'qb_get_quiz_settings');
function qb_get_quiz_settings() {
    check_ajax_referer('qb_get_settings', 'nonce');

    $quiz_id = intval($_POST['quiz_id']);
    require_once plugin_dir_path(__FILE__) . 'includes/db/class-quiz-settings-db.php';
    $settings_db = new QB_Quiz_Settings_DB();
    
    $settings = $settings_db->get_settings($quiz_id);

    if ($settings) {
        // Ensure boolean values are properly handled
        $settings->is_paginated = (int)$settings->is_paginated;
        $settings->questions_per_page = (int)$settings->questions_per_page;
        $settings->show_user_answers = (int)$settings->show_user_answers;
        wp_send_json_success($settings);
    } else {
        // Return default settings
        wp_send_json_success(array(
            'is_paginated' => 0,
            'questions_per_page' => 1,
            'show_user_answers' => 0
        ));
    }
}

/**
 * Enqueue admin scripts and styles
 */
function qb_admin_enqueue_scripts($hook) {
    // ... existing code ...
}

// Add AJAX handlers for quiz answers
add_action('wp_ajax_qb_save_quiz_answers', 'qb_save_quiz_answers');
add_action('wp_ajax_nopriv_qb_save_quiz_answers', 'qb_save_quiz_answers');
add_action('wp_ajax_qb_get_quiz_answers', 'qb_get_quiz_answers');
add_action('wp_ajax_nopriv_qb_get_quiz_answers', 'qb_get_quiz_answers');
add_action('wp_ajax_qb_clear_quiz_answers', 'qb_clear_quiz_answers');
add_action('wp_ajax_nopriv_qb_clear_quiz_answers', 'qb_clear_quiz_answers');

function qb_save_quiz_answers() {
    check_ajax_referer('qb_save_answers', 'nonce');

    if (!isset($_POST['quiz_id']) || !isset($_POST['answers'])) {
        wp_send_json_error('Missing required data');
    }

    $quiz_id = intval($_POST['quiz_id']);
    $answers = $_POST['answers'];

    // Generate a unique key for this user's quiz attempt
    $user_id = get_current_user_id();
    $key = 'quiz_answers_' . ($user_id ? $user_id : 'guest') . '_' . $quiz_id;

    // Store answers in transient (expires in 1 hour)
    set_transient($key, $answers, HOUR_IN_SECONDS);

    wp_send_json_success();
}

function qb_get_quiz_answers() {
    check_ajax_referer('qb_get_answers', 'nonce');

    if (!isset($_POST['quiz_id'])) {
        wp_send_json_error('Missing quiz ID');
    }

    $quiz_id = intval($_POST['quiz_id']);

    // Generate the key
    $user_id = get_current_user_id();
    $key = 'quiz_answers_' . ($user_id ? $user_id : 'guest') . '_' . $quiz_id;

    // Get answers from transient
    $answers = get_transient($key);
    if ($answers === false) {
        $answers = array();
    }

    wp_send_json_success($answers);
}

function qb_clear_quiz_answers() {
    check_ajax_referer('qb_clear_answers', 'nonce');

    if (!isset($_POST['quiz_id'])) {
        wp_send_json_error('Missing quiz ID');
    }

    $quiz_id = intval($_POST['quiz_id']);

    // Generate the key
    $user_id = get_current_user_id();
    $key = 'quiz_answers_' . ($user_id ? $user_id : 'guest') . '_' . $quiz_id;

    // Delete the transient
    delete_transient($key);

    wp_send_json_success();
}

function qb_enqueue_scripts() {
    // Enqueue results styles
    wp_enqueue_style(
        'qb-results-styles',
        plugins_url('assets/css/quiz-results.css', __FILE__),
        array(),
        QB_VERSION
    );
}
add_action('wp_enqueue_scripts', 'qb_enqueue_scripts');

// Remove duplicate onboarding page to the admin menu
// (Handled in admin/quiz-admin-page.php)

// Redirect to onboarding only on fresh install
register_activation_hook(__FILE__, function() {
    add_option('qb_show_onboarding', true);
});

add_action('admin_init', function() {
    if (get_option('qb_show_onboarding', false)) {
        delete_option('qb_show_onboarding');
        // Only redirect if not already on dashboard page
        if (!isset($_GET['page']) || $_GET['page'] !== 'quiz-builder') {
            wp_safe_redirect(admin_url('admin.php?page=quiz-builder'));
            exit;
        }
    }
});

// Add AJAX handlers for onboarding
add_action('wp_ajax_qb_onboarding_create_quiz', 'qb_onboarding_create_quiz');
add_action('wp_ajax_qb_onboarding_add_question', 'qb_onboarding_add_question');
add_action('wp_ajax_qb_onboarding_add_questions', 'qb_onboarding_add_questions');
add_action('wp_ajax_qb_onboarding_add_options', 'qb_onboarding_add_options');
add_action('wp_ajax_qb_onboarding_add_all_options', 'qb_onboarding_add_all_options');

function qb_onboarding_create_quiz() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $title = sanitize_text_field($_POST['quiz_title']);
    $description = sanitize_textarea_field($_POST['quiz_description']);

    if (empty($title)) {
        wp_send_json_error('Quiz title is required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_quizzes';

    $result = $wpdb->insert($table_name, [
        'title' => $title,
        'description' => $description,
        'created_at' => current_time('mysql')
    ]);

    if ($result === false) {
        wp_send_json_error('Failed to create quiz');
    }

    wp_send_json_success(['quiz_id' => $wpdb->insert_id]);
}

function qb_onboarding_add_question() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }    $quiz_id = intval($_POST['quiz_id']);
    $question_text = sanitize_text_field($_POST['question_text']);
    
    if (empty($question_text)) {
        wp_send_json_error('Question text is required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_questions';    $result = $wpdb->insert($table_name, [
        'quiz_id' => $quiz_id,
        'question' => $question_text,
        'order' => 1
    ]);

    if ($result === false) {
        wp_send_json_error('Failed to create question');
    }

    wp_send_json_success(['question_id' => $wpdb->insert_id]);
}

function qb_onboarding_add_options() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $question_id = intval($_POST['question_id']);
    $options = $_POST['options'];

    if (empty($options) || !is_array($options)) {
        wp_send_json_error('Options are required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_options';

    foreach ($options as $option) {
        $option_text = sanitize_text_field($option['text']);
        $points = intval($option['points']);

        if (empty($option_text)) {
            continue;
        }

        $wpdb->insert($table_name, [
            'question_id' => $question_id,
            'option_text' => $option_text,
            'points' => $points
        ]);
    }

    wp_send_json_success(['message' => 'Options added successfully']);
}

// New AJAX handler for adding multiple questions
function qb_onboarding_add_questions() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $quiz_id = intval($_POST['quiz_id']);
    $questions = $_POST['questions'];
    
    if (empty($questions) || !is_array($questions)) {
        wp_send_json_error('Questions are required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_questions';
    $question_ids = [];

    foreach ($questions as $index => $question_text) {
        $question_text = sanitize_text_field($question_text);
        
        if (empty($question_text)) {
            continue;
        }

        $result = $wpdb->insert($table_name, [
            'quiz_id' => $quiz_id,
            'question' => $question_text,
            'order' => $index + 1
        ]);

        if ($result !== false) {
            $question_ids[] = $wpdb->insert_id;
        }
    }

    if (empty($question_ids)) {
        wp_send_json_error('Failed to create questions');
    }

    wp_send_json_success(['question_ids' => $question_ids]);
}

// New AJAX handler for adding options to all questions
function qb_onboarding_add_all_options() {
    check_ajax_referer('qb_onboarding_quiz', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $question_options = $_POST['question_options'];

    if (empty($question_options) || !is_array($question_options)) {
        wp_send_json_error('Question options are required');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'qb_options';
    $total_options_added = 0;

    foreach ($question_options as $question_id => $options) {
        $question_id = intval($question_id);
        
        if (!is_array($options)) {
            continue;
        }

        foreach ($options as $option) {
            $option_text = sanitize_text_field($option['text']);
            $points = intval($option['points']);

            if (empty($option_text)) {
                continue;
            }

            $result = $wpdb->insert($table_name, [
                'question_id' => $question_id,
                'option_text' => $option_text,
                'points' => $points
            ]);

            if ($result !== false) {
                $total_options_added++;
            }
        }
    }

    if ($total_options_added === 0) {
        wp_send_json_error('Failed to add options');
    }

    wp_send_json_success(['message' => 'Options added successfully', 'total_options' => $total_options_added]);
}

// Add AJAX handler for PDF export
add_action('wp_ajax_qb_export_pdf', 'qb_export_pdf');
add_action('wp_ajax_nopriv_qb_export_pdf', 'qb_export_pdf');

function qb_export_pdf() {
    $attempt_id = intval($_GET['attempt_id']);
    $nonce = sanitize_text_field($_GET['nonce']);
    
    // Verify nonce
    if (!wp_verify_nonce($nonce, 'qb_pdf_export_' . $attempt_id)) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $attempts_table = $wpdb->prefix . 'qb_attempts';
    
    // Get attempt details
    $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM $attempts_table WHERE id = %d", $attempt_id));
    if (!$attempt) {
        wp_die('Quiz attempt not found');
    }
    
    // Get quiz details
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}qb_quizzes WHERE id = %d", $attempt->quiz_id));
    if (!$quiz) {
        wp_die('Quiz not found');
    }
    
    // Check if PDF export is enabled for this quiz
    require_once QB_PATH . 'includes/db/class-quiz-settings-db.php';
    $settings_db = new QB_Quiz_Settings_DB();
    $settings = $settings_db->get_settings($attempt->quiz_id);
    
    if (!$settings || !$settings->allow_pdf_export) {
        wp_die('PDF export is not enabled for this quiz');
    }
    
    // Generate PDF
    qb_generate_pdf($attempt, $quiz);
}

/**
 * Generate PDF from quiz results
 */
function qb_generate_pdf($attempt, $quiz) {
    // Get quiz results data
    require_once QB_PATH . 'includes/results/class-quiz-results-display.php';
    $results_display = new QB_Quiz_Results_Display();
    $answers = $results_display->get_attempt_answers($attempt->id);
    
    $percentage = round(($attempt->score / $attempt->total_points) * 100);
    $current_date = current_time('Y-m-d H:i:s');
    
    // Create PDF content using HTML
    $html_content = qb_generate_pdf_html($quiz, $attempt, $answers, $percentage, $current_date);
    
    // Check if we can use DomPDF or similar library
    if (class_exists('Dompdf\Dompdf')) {
        qb_generate_pdf_with_dompdf($html_content, $quiz->title);
    } else {
        // Fallback to basic HTML output with print styles
        qb_generate_pdf_fallback($html_content, $quiz->title);
    }
}

/**
 * Generate HTML content for PDF
 */
function qb_generate_pdf_html($quiz, $attempt, $answers, $percentage, $current_date) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html($quiz->title); ?> - Quiz Results</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #ddd;
                padding-bottom: 20px;
            }
            .header h1 {
                color: #2271b1;
                margin: 0;
            }
            .score-summary {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 5px;
                margin-bottom: 30px;
                text-align: center;
            }
            .score-summary .score {
                font-size: 24px;
                font-weight: bold;
                color: #2271b1;
            }
            .answers-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 30px;
            }
            .answers-table th,
            .answers-table td {
                border: 1px solid #ddd;
                padding: 12px;
                text-align: left;
            }
            .answers-table th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            .correct-answer {
                background-color: #d4edda;
            }
            .incorrect-answer {
                background-color: #f8d7da;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 12px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo esc_html($quiz->title); ?></h1>
            <p>Quiz Results Report</p>
        </div>
        
        <div class="score-summary">
            <div class="score">Final Score: <?php echo $attempt->score; ?> / <?php echo $attempt->total_points; ?> (<?php echo $percentage; ?>%)</div>
            <p>Date Completed: <?php echo esc_html($current_date); ?></p>
        </div>
        
        <?php if (!empty($answers)): ?>
        <h2>Your Answers</h2>
        <table class="answers-table">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Your Answer</th>
                    <th>Correct Answer</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($answers as $answer): 
                    $is_correct = $answer->selected_option == $answer->correct_option;
                ?>
                <tr class="<?php echo $is_correct ? 'correct-answer' : 'incorrect-answer'; ?>">
                    <td><?php echo esc_html($answer->question_text); ?></td>
                    <td><?php echo esc_html($answer->selected_text); ?></td>
                    <td><?php echo esc_html($answer->correct_text); ?></td>
                    <td><?php echo $is_correct ? '✓ Correct' : '✗ Incorrect'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div class="footer">
            <p>Generated on <?php echo current_time('F j, Y \a\t g:i A'); ?></p>
            <p>Quiz Builder Plugin - WordPress</p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Fallback PDF generation using HTML with print-friendly styles
 */
function qb_generate_pdf_fallback($html_content, $quiz_title) {
    // Set headers for download
    $filename = sanitize_file_name($quiz_title . '_results_' . date('Y-m-d_H-i-s') . '.html');
    
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
      // Add print styles to the HTML
    $print_styles = '<style media="print">
        @page { margin: 1in; }
        body { -webkit-print-color-adjust: exact; }
        .header { page-break-after: avoid; }
        .answers-table { page-break-inside: avoid; }
        tr { page-break-inside: avoid; }
    </style>';
    
    // Insert print styles before closing head tag
    $html_content = str_replace('</head>', $print_styles . '</head>', $html_content);
    
    echo $html_content;
    exit;
}
